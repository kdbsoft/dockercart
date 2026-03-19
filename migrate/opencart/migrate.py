#!/usr/bin/env python3
"""Universal data migration from OpenCart to DockerCart.

Migrates the following entities:
- categories
- products
- manufacturers
- information pages
- articles (if the table exists)

Features:
- Auto-detection of table prefix (or manual --source-prefix / --target-prefix)
- Interactive language_id mapping via console
- Support for different SEO-URL schemas:
  - query/keyword  (OC 2/3)
  - key/value/keyword  (OC 4)
- SEO keywords are preserved exactly as in the source database
"""

from __future__ import annotations

import argparse
import importlib
import re
import sys
from dataclasses import dataclass
from typing import Any, Iterable

try:
    pymysql = importlib.import_module("pymysql")
    DictCursor = importlib.import_module("pymysql.cursors").DictCursor
except Exception as exc:  # noqa: BLE001
    print("Package 'pymysql' not found. Install it with: pip install pymysql", file=sys.stderr)
    raise SystemExit(2) from exc


@dataclass
class DbConfig:
    host: str
    port: int
    user: str
    password: str
    database: str
    prefix: str | None


@dataclass
class TableMeta:
    name: str
    columns: list[str]
    primary_key: list[str]
    not_null_cols: list[str]  # NOT NULL columns with no default (INSERT would fail if NULL)


class Db:
    def __init__(self, cfg: DbConfig):
        self.cfg = cfg
        self.conn = pymysql.connect(
            host=cfg.host,
            port=cfg.port,
            user=cfg.user,
            password=cfg.password,
            database=cfg.database,
            cursorclass=DictCursor,
            charset="utf8mb4",
            autocommit=False,
        )

    def close(self) -> None:
        self.conn.close()

    def query(self, sql: str, params: tuple[Any, ...] | None = None) -> list[dict[str, Any]]:
        with self.conn.cursor() as cur:
            cur.execute(sql, params or ())
            return list(cur.fetchall())

    def execute(self, sql: str, params: tuple[Any, ...] | None = None) -> int:
        with self.conn.cursor() as cur:
            return cur.execute(sql, params or ())

    def executemany(self, sql: str, rows: list[tuple[Any, ...]]) -> int:
        if not rows:
            return 0
        with self.conn.cursor() as cur:
            result = cur.executemany(sql, rows)
            return int(result or 0)

    def commit(self) -> None:
        self.conn.commit()

    def rollback(self) -> None:
        self.conn.rollback()


def detect_prefix(db: Db) -> str:
    rows = db.query(
        """
        SELECT DISTINCT SUBSTRING_INDEX(table_name, 'language', 1) AS pref
        FROM information_schema.tables
        WHERE table_schema = %s
          AND table_name LIKE %s
        """,
        (db.cfg.database, "%language"),
    )
    candidates = sorted([r["pref"] for r in rows if r.get("pref")])
    if not candidates:
        raise RuntimeError(
            f"Could not detect table prefix in database {db.cfg.database}. "
            "Specify the prefix manually with --source-prefix / --target-prefix."
        )
    return candidates[0]


def table_exists(db: Db, table_name: str) -> bool:
    rows = db.query(
        """
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = %s AND table_name = %s
        LIMIT 1
        """,
        (db.cfg.database, table_name),
    )
    return bool(rows)


def get_table_meta(db: Db, table_name: str) -> TableMeta:
    cols = db.query(
        """
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = %s AND table_name = %s
        ORDER BY ordinal_position
        """,
        (db.cfg.database, table_name),
    )
    pks = db.query(
        """
        SELECT column_name
        FROM information_schema.key_column_usage
        WHERE table_schema = %s AND table_name = %s AND constraint_name = 'PRIMARY'
        ORDER BY ordinal_position
        """,
        (db.cfg.database, table_name),
    )
    required = db.query(
        """
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = %s AND table_name = %s
          AND is_nullable = 'NO'
          AND column_default IS NULL
          AND extra NOT LIKE '%%auto_increment%%'
        ORDER BY ordinal_position
        """,
        (db.cfg.database, table_name),
    )
    return TableMeta(
        name=table_name,
        columns=[c["column_name"] for c in cols],
        primary_key=[k["column_name"] for k in pks],
        not_null_cols=[c["column_name"] for c in required],
    )


def get_languages(db: Db, prefix: str) -> list[dict[str, Any]]:
    table = f"{prefix}language"
    if not table_exists(db, table):
        return []
    return db.query(f"SELECT language_id, code, name, status FROM `{table}` ORDER BY language_id")


def parse_language_map(value: str, src_langs: list[dict[str, Any]], dst_langs: list[dict[str, Any]]) -> dict[int, int]:
    src_by_id = {int(l["language_id"]): l for l in src_langs}
    src_by_code = {str(l["code"]).lower(): l for l in src_langs if l.get("code")}
    dst_ids = {int(l["language_id"]) for l in dst_langs}

    result: dict[int, int] = {}
    for pair in [p.strip() for p in value.split(",") if p.strip()]:
        if ":" not in pair:
            raise ValueError(f"Invalid pair format '{pair}'. Use SRC:DST")
        left, right = [s.strip() for s in pair.split(":", 1)]
        dst_id = int(right)
        if dst_id not in dst_ids:
            raise ValueError(f"Target language_id={dst_id} not found")

        if left.isdigit():
            src_id = int(left)
            if src_id not in src_by_id:
                raise ValueError(f"Source language_id={src_id} not found")
        else:
            src = src_by_code.get(left.lower())
            if not src:
                raise ValueError(f"Source language code='{left}' not found")
            src_id = int(src["language_id"])

        result[src_id] = dst_id

    return result


def interactive_language_map(src_langs: list[dict[str, Any]], dst_langs: list[dict[str, Any]]) -> dict[int, int]:
    print("\n=== Language mapping ===")
    print("Source languages:")
    for l in src_langs:
        print(f"  {l['language_id']}: {l.get('code', '')} ({l.get('name', '')})")

    print("\nTarget languages:")
    for l in dst_langs:
        print(f"  {l['language_id']}: {l.get('code', '')} ({l.get('name', '')})")

    dst_ids = {int(l["language_id"]) for l in dst_langs}
    mapping: dict[int, int] = {}

    for src in src_langs:
        src_id = int(src["language_id"])
        while True:
            answer = input(
                f"Map source language_id={src_id} ({src.get('code')}/{src.get('name')}) "
                f"to target language_id (or 'skip'): "
            ).strip().lower()
            if answer == "skip":
                break
            if answer.isdigit() and int(answer) in dst_ids:
                mapping[src_id] = int(answer)
                break
            print("  Invalid input. Enter an ID from the list above or 'skip'.")

    if not mapping:
        raise RuntimeError("No language mappings defined.")

    return mapping


def safe_query_filter(pattern: str) -> str:
    # Guard against SQL wildcard-injection in LIKE: only allow patterns like xxx_id=%
    if not re.fullmatch(r"[a-z_]+_id=%", pattern):
        raise ValueError(f"Unsafe pattern: {pattern}")
    return pattern


def build_insert_sql(table: str, cols: list[str], pk_cols: list[str]) -> str:
    col_sql = ", ".join(f"`{c}`" for c in cols)
    placeholders = ", ".join(["%s"] * len(cols))

    non_pk = [c for c in cols if c not in pk_cols]
    if non_pk:
        updates = ", ".join(f"`{c}`=VALUES(`{c}`)" for c in non_pk)
        return f"INSERT INTO `{table}` ({col_sql}) VALUES ({placeholders}) ON DUPLICATE KEY UPDATE {updates}"

    return f"INSERT IGNORE INTO `{table}` ({col_sql}) VALUES ({placeholders})"


def migrate_table_rows(
    source: Db,
    target: Db,
    source_table: str,
    target_table: str,
    where_sql: str = "",
    where_params: tuple[Any, ...] | None = None,
    language_map: dict[int, int] | None = None,
) -> int:
    if not table_exists(source, source_table) or not table_exists(target, target_table):
        return 0

    src_meta = get_table_meta(source, source_table)
    dst_meta = get_table_meta(target, target_table)

    common_cols = [c for c in src_meta.columns if c in dst_meta.columns]
    if not common_cols:
        return 0

    sql = f"SELECT {', '.join(f'`{c}`' for c in common_cols)} FROM `{source_table}`"
    if where_sql:
        sql += f" WHERE {where_sql}"

    src_rows = source.query(sql, where_params)
    if not src_rows:
        return 0

    rows_to_insert: list[tuple[Any, ...]] = []
    has_lang = "language_id" in common_cols and language_map is not None
    lang_map = language_map or {}

    for row in src_rows:
        mutable = dict(row)
        if has_lang:
            src_lang = int(mutable["language_id"])
            if src_lang not in lang_map:
                continue
            mutable["language_id"] = lang_map[src_lang]
        rows_to_insert.append(tuple(mutable[c] for c in common_cols))

    if not rows_to_insert:
        return 0

    # Drop rows that would violate NOT NULL constraints in the target table
    required_in_common = [c for c in dst_meta.not_null_cols if c in common_cols]
    if required_in_common:
        col_idx = {c: i for i, c in enumerate(common_cols)}
        valid: list[tuple[Any, ...]] = []
        skipped = 0
        for row_tuple in rows_to_insert:
            if all(row_tuple[col_idx[c]] is not None for c in required_in_common):
                valid.append(row_tuple)
            else:
                skipped += 1
        if skipped:
            print(f"    Skipped {skipped} row(s) with NULL in required column(s): {required_in_common}")
        rows_to_insert = valid

    if not rows_to_insert:
        return 0

    insert_sql = build_insert_sql(target_table, common_cols, dst_meta.primary_key)
    return target.executemany(insert_sql, rows_to_insert)


def detect_seo_mode(db: Db, table: str) -> str:
    meta = get_table_meta(db, table)
    cols = set(meta.columns)
    if {"query", "keyword"}.issubset(cols):
        return "query"
    if {"key", "value", "keyword"}.issubset(cols):
        return "keyvalue"
    raise RuntimeError(f"Unsupported SEO table schema in `{table}`")


def read_seo_rows(source: Db, table: str, mode: str, query_like: str) -> list[dict[str, Any]]:
    query_like = safe_query_filter(query_like)
    if mode == "query":
        return source.query(
            f"SELECT store_id, language_id, `query`, keyword FROM `{table}` WHERE `query` LIKE %s",
            (query_like,),
        )

    raw = source.query(
        f"SELECT store_id, language_id, `key`, `value`, keyword FROM `{table}`",
    )
    result: list[dict[str, Any]] = []
    for r in raw:
        q = f"{r['key']}={r['value']}"
        if q.startswith(query_like.replace("%", "")):
            result.append(
                {
                    "store_id": r["store_id"],
                    "language_id": r["language_id"],
                    "query": q,
                    "keyword": r["keyword"],
                }
            )
    return result


def write_seo_rows(
    target: Db,
    table: str,
    mode: str,
    rows: Iterable[dict[str, Any]],
    language_map: dict[int, int],
) -> int:
    prepared: list[tuple[Any, ...]] = []

    if mode == "query":
        for r in rows:
            src_lang = int(r["language_id"])
            if src_lang not in language_map:
                continue
            prepared.append((r["store_id"], language_map[src_lang], r["query"], r["keyword"]))

        sql = (
            f"INSERT INTO `{table}` (`store_id`, `language_id`, `query`, `keyword`) "
            "VALUES (%s, %s, %s, %s) "
            "ON DUPLICATE KEY UPDATE `keyword`=VALUES(`keyword`)"
        )
        return target.executemany(sql, prepared)

    for r in rows:
        src_lang = int(r["language_id"])
        if src_lang not in language_map:
            continue
        query_str = str(r["query"])
        if "=" in query_str:
            k, v = query_str.split("=", 1)
        else:
            k, v = query_str, ""
        prepared.append((r["store_id"], language_map[src_lang], k, v, r["keyword"]))

    sql = (
        f"INSERT INTO `{table}` (`store_id`, `language_id`, `key`, `value`, `keyword`) "
        "VALUES (%s, %s, %s, %s, %s) "
        "ON DUPLICATE KEY UPDATE `keyword`=VALUES(`keyword`)"
    )
    return target.executemany(sql, prepared)


def migrate_seo_for_entity(
    source: Db,
    target: Db,
    source_prefix: str,
    target_prefix: str,
    language_map: dict[int, int],
    query_pattern: str,
) -> int:
    src_table = f"{source_prefix}seo_url"
    dst_table = f"{target_prefix}seo_url"
    if not table_exists(source, src_table) or not table_exists(target, dst_table):
        return 0

    src_mode = detect_seo_mode(source, src_table)
    dst_mode = detect_seo_mode(target, dst_table)

    rows = read_seo_rows(source, src_table, src_mode, query_pattern)
    return write_seo_rows(target, dst_table, dst_mode, rows, language_map)


def migrate_categories(source: Db, target: Db, sp: str, tp: str, lang_map: dict[int, int]) -> None:
    print("\n[Categories]")
    total = 0
    total += migrate_table_rows(source, target, f"{sp}category", f"{tp}category")
    total += migrate_table_rows(source, target, f"{sp}category_description", f"{tp}category_description", language_map=lang_map)
    total += migrate_table_rows(source, target, f"{sp}category_to_store", f"{tp}category_to_store")
    total += migrate_table_rows(source, target, f"{sp}category_to_layout", f"{tp}category_to_layout")
    total += migrate_table_rows(source, target, f"{sp}category_path", f"{tp}category_path")
    seo = migrate_seo_for_entity(source, target, sp, tp, lang_map, "category_id=%")
    print(f"  Rows migrated: {total}, SEO rows: {seo}")


def migrate_manufacturers(source: Db, target: Db, sp: str, tp: str, lang_map: dict[int, int]) -> None:
    print("\n[Manufacturers]")
    total = 0
    total += migrate_table_rows(source, target, f"{sp}manufacturer", f"{tp}manufacturer")
    total += migrate_table_rows(source, target, f"{sp}manufacturer_description", f"{tp}manufacturer_description", language_map=lang_map)
    total += migrate_table_rows(source, target, f"{sp}manufacturer_to_store", f"{tp}manufacturer_to_store")
    total += migrate_table_rows(source, target, f"{sp}manufacturer_to_layout", f"{tp}manufacturer_to_layout")
    seo = migrate_seo_for_entity(source, target, sp, tp, lang_map, "manufacturer_id=%")
    print(f"  Rows migrated: {total}, SEO rows: {seo}")


def migrate_products(source: Db, target: Db, sp: str, tp: str, lang_map: dict[int, int]) -> None:
    print("\n[Products]")
    total = 0
    total += migrate_table_rows(source, target, f"{sp}product", f"{tp}product")
    total += migrate_table_rows(source, target, f"{sp}product_description", f"{tp}product_description", language_map=lang_map)
    total += migrate_table_rows(source, target, f"{sp}product_to_category", f"{tp}product_to_category")
    total += migrate_table_rows(source, target, f"{sp}product_to_store", f"{tp}product_to_store")
    total += migrate_table_rows(source, target, f"{sp}product_to_layout", f"{tp}product_to_layout")
    total += migrate_table_rows(source, target, f"{sp}product_image", f"{tp}product_image")
    total += migrate_table_rows(source, target, f"{sp}product_attribute", f"{tp}product_attribute", language_map=lang_map)
    total += migrate_table_rows(source, target, f"{sp}product_related", f"{tp}product_related")
    seo = migrate_seo_for_entity(source, target, sp, tp, lang_map, "product_id=%")
    print(f"  Rows migrated: {total}, SEO rows: {seo}")


def migrate_information(source: Db, target: Db, sp: str, tp: str, lang_map: dict[int, int]) -> None:
    print("\n[Information pages]")
    total = 0
    total += migrate_table_rows(source, target, f"{sp}information", f"{tp}information")
    total += migrate_table_rows(source, target, f"{sp}information_description", f"{tp}information_description", language_map=lang_map)
    total += migrate_table_rows(source, target, f"{sp}information_to_store", f"{tp}information_to_store")
    total += migrate_table_rows(source, target, f"{sp}information_to_layout", f"{tp}information_to_layout")
    seo = migrate_seo_for_entity(source, target, sp, tp, lang_map, "information_id=%")
    print(f"  Rows migrated: {total}, SEO rows: {seo}")


def migrate_article(source: Db, target: Db, sp: str, tp: str, lang_map: dict[int, int]) -> None:
    print("\n[Articles]")
    total = 0
    total += migrate_table_rows(source, target, f"{sp}article", f"{tp}article")
    total += migrate_table_rows(source, target, f"{sp}article_description", f"{tp}article_description", language_map=lang_map)
    total += migrate_table_rows(source, target, f"{sp}article_to_store", f"{tp}article_to_store")
    total += migrate_table_rows(source, target, f"{sp}article_to_layout", f"{tp}article_to_layout")
    seo = migrate_seo_for_entity(source, target, sp, tp, lang_map, "article_id=%")
    print(f"  Rows migrated: {total}, SEO rows: {seo}")


def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(description="Migrate OpenCart data to DockerCart")

    p.add_argument("--source-host", required=True)
    p.add_argument("--source-port", type=int, default=3306)
    p.add_argument("--source-user", required=True)
    p.add_argument("--source-password", required=True)
    p.add_argument("--source-database", required=True)
    p.add_argument("--source-prefix", default=None)

    p.add_argument("--target-host", required=True)
    p.add_argument("--target-port", type=int, default=3306)
    p.add_argument("--target-user", required=True)
    p.add_argument("--target-password", required=True)
    p.add_argument("--target-database", required=True)
    p.add_argument("--target-prefix", default=None)

    p.add_argument(
        "--entities",
        default="categories,products,manufacturers,information,article",
        help="Comma-separated list of entities to migrate: categories,products,manufacturers,information,article",
    )
    p.add_argument(
        "--language-map",
        default=None,
        help="Language ID mapping, e.g. '1:2,2:3' or 'en-gb:1,uk-ua:2'",
    )
    p.add_argument("--dry-run", action="store_true", help="Preview steps without committing changes")

    return p.parse_args()


def main() -> int:
    args = parse_args()

    src = Db(
        DbConfig(
            host=args.source_host,
            port=args.source_port,
            user=args.source_user,
            password=args.source_password,
            database=args.source_database,
            prefix=args.source_prefix,
        )
    )
    dst = Db(
        DbConfig(
            host=args.target_host,
            port=args.target_port,
            user=args.target_user,
            password=args.target_password,
            database=args.target_database,
            prefix=args.target_prefix,
        )
    )

    try:
        sp = src.cfg.prefix or detect_prefix(src)
        tp = dst.cfg.prefix or detect_prefix(dst)

        print(f"Source prefix: {sp}")
        print(f"Target prefix: {tp}")

        src_langs = get_languages(src, sp)
        dst_langs = get_languages(dst, tp)
        if not src_langs or not dst_langs:
            raise RuntimeError("Could not retrieve language list from source or target database")

        if args.language_map:
            lang_map = parse_language_map(args.language_map, src_langs, dst_langs)
        else:
            lang_map = interactive_language_map(src_langs, dst_langs)

        print("\nLanguage mapping:")
        for k, v in sorted(lang_map.items()):
            print(f"  {k} -> {v}")

        entities = {e.strip().lower() for e in args.entities.split(",") if e.strip()}

        dst.execute("SET FOREIGN_KEY_CHECKS=0")

        if "categories" in entities:
            migrate_categories(src, dst, sp, tp, lang_map)
        if "manufacturers" in entities:
            migrate_manufacturers(src, dst, sp, tp, lang_map)
        if "products" in entities:
            migrate_products(src, dst, sp, tp, lang_map)
        if "information" in entities:
            migrate_information(src, dst, sp, tp, lang_map)
        if "article" in entities:
            migrate_article(src, dst, sp, tp, lang_map)

        dst.execute("SET FOREIGN_KEY_CHECKS=1")

        if args.dry_run:
            dst.rollback()
            print("\nDRY-RUN complete: all changes rolled back.")
        else:
            dst.commit()
            print("\nMigration completed successfully (COMMIT).")

        return 0

    except Exception as exc:  # noqa: BLE001
        dst.rollback()
        print(f"\nMigration error: {exc}", file=sys.stderr)
        return 1
    finally:
        src.close()
        dst.close()


if __name__ == "__main__":
    raise SystemExit(main())
