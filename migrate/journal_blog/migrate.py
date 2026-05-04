#!/usr/bin/env python3
"""Journal Blog → DockerCart Migration.

Scrapes a Journal-themed OpenCart blog from a donor site and imports:
- Categories (oc_blog_category + description + seo_url)
- Posts (oc_blog_post + description + seo_url + category mapping)
- Images (downloaded locally, paths rewritten)

Features:
- Multi-language support via configurable index URLs per language
- HTML parsing with BeautifulSoup4 (configurable CSS selectors)
- Category scraping from listing page sidebar/filter
- SEO URL generation (preserves donor site URL slugs)
- Image downloading and path rewriting
- Pagination handling
- Dry-run mode
"""

from __future__ import annotations

import argparse
import hashlib
import os
import re
import sys
import time
from pathlib import Path
from typing import Any
from urllib.parse import urljoin
from urllib.parse import urlparse as urlparse_fn

import httpx
import pymysql
import yaml
from bs4 import BeautifulSoup

# ── Database helpers ──────────────────────────────────────────────────────────


class Db:
    """Thin wrapper around pymysql for the target database."""

    def __init__(self, cfg: dict[str, Any]) -> None:
        self.conn = pymysql.connect(
            host=cfg["host"],
            port=cfg["port"],
            user=cfg["user"],
            password=cfg["password"],
            database=cfg["database"],
            charset="utf8mb4",
            autocommit=False,
        )
        self.prefix = cfg["prefix"]

    def close(self) -> None:
        self.conn.close()

    def execute(self, sql: str, params: tuple[Any, ...] | None = None) -> int:
        with self.conn.cursor() as cur:
            return cur.execute(sql, params or ())

    def query_one(
        self, sql: str, params: tuple[Any, ...] | None = None
    ) -> dict[str, Any] | None:
        with self.conn.cursor() as cur:
            cur.execute(sql, params or ())
            return cur.fetchone()

    def commit(self) -> None:
        self.conn.commit()

    def rollback(self) -> None:
        self.conn.rollback()


# ── Slug / SEO helpers ────────────────────────────────────────────────────────


def slugify(text: str, max_len: int = 100) -> str:
    """Generate a URL-safe slug from a title string (transliterates Cyrillic)."""
    cyrillic_map = {
        "а": "a",
        "б": "b",
        "в": "v",
        "г": "g",
        "д": "d",
        "е": "e",
        "ё": "yo",
        "ж": "zh",
        "з": "z",
        "и": "i",
        "й": "y",
        "к": "k",
        "л": "l",
        "м": "m",
        "н": "n",
        "о": "o",
        "п": "p",
        "р": "r",
        "с": "s",
        "т": "t",
        "у": "u",
        "ф": "f",
        "х": "h",
        "ц": "ts",
        "ч": "ch",
        "ш": "sh",
        "щ": "sch",
        "ъ": "",
        "ы": "y",
        "ь": "",
        "э": "e",
        "ю": "yu",
        "я": "ya",
        "і": "i",
        "ї": "yi",
        "є": "ye",
        "ґ": "g",
    }
    slug = text.lower().strip()
    result = ""
    for ch in slug:
        result += cyrillic_map.get(ch, ch)
    slug = re.sub(r"[^a-z0-9]+", "-", result)
    return slug.strip("-")[:max_len]


def extract_keyword_from_url(url: str) -> str:
    """Extract the last path segment as a keyword/slug from a URL."""
    parsed = urlparse_fn(url)
    path = parsed.path.rstrip("/")
    if not path:
        return ""
    # Get last segment
    segments = [s for s in path.split("/") if s]
    if not segments:
        return ""
    keyword = segments[-1]
    # If it ends with .html or similar, strip extension
    keyword = re.sub(r"\.[^.]+$", "", keyword)
    return keyword


# ── Image downloader ──────────────────────────────────────────────────────────


def download_image(
    client: httpx.Client,
    img_url: str,
    image_dir: Path,
    image_base: str,
) -> str | None:
    """Download an image and return the relative path for the DB."""
    parsed = urlparse_fn(img_url)
    filename = os.path.basename(parsed.path)
    if not filename or "." not in filename:
        filename = hashlib.md5(img_url.encode()).hexdigest()[:12] + ".jpg"
    else:
        filename = re.sub(r"[^a-zA-Z0-9._-]", "_", filename)

    dest_path = image_dir / filename
    if dest_path.exists():
        return f"{image_base}/{filename}"

    try:
        resp = client.get(img_url)
        resp.raise_for_status()
    except Exception as exc:
        print(f"  [WARN] Failed to download image {img_url}: {exc}", file=sys.stderr)
        return None

    dest_path.write_bytes(resp.content)
    return f"{image_base}/{filename}"


# ── HTML helpers ──────────────────────────────────────────────────────────────


def extract_text(
    soup: BeautifulSoup | None, selector: str, attr: str | None = None
) -> str:
    """Extract text from the first matching element."""
    if soup is None:
        return ""
    el = soup.select_one(selector)
    if el is None:
        return ""
    if attr:
        return (el.get(attr) or "").strip()
    return el.get_text(strip=True)


def extract_html_inner(soup: BeautifulSoup | None, selector: str) -> str:
    """Extract inner HTML from the first matching element."""
    if soup is None:
        return ""
    el = soup.select_one(selector)
    if el is None:
        return ""
    return el.decode_contents()


def parse_views(text: str) -> int:
    """Extract an integer from view count text like 'Views: 1 234'."""
    match = re.search(r"(\d[\d\s]*\d)", text)
    if match:
        return int(match.group(1).replace(" ", ""))
    return 0


# ── Scrapers ──────────────────────────────────────────────────────────────────


def scrape_listing(
    client: httpx.Client,
    base_url: str,
    index_url: str,
    selectors: dict[str, Any],
) -> list[str]:
    """Scrape the blog listing page(s) and return all article URLs."""
    article_urls: list[str] = []
    page_url = urljoin(base_url, index_url)
    page_num = 0

    while page_url:
        page_num += 1
        print(f"  Listing page {page_num}: {page_url}")

        try:
            resp = client.get(page_url)
            resp.raise_for_status()
        except Exception as exc:
            print(f"  [ERROR] Failed to fetch listing: {exc}", file=sys.stderr)
            break

        soup = BeautifulSoup(resp.text, "html.parser")
        article_sel = selectors["listing"]["articles"]
        link_sel = selectors["listing"]["article_link"]

        items = soup.select(article_sel)
        if not items:
            links = soup.select(link_sel)
            for link in links:
                href = link.get("href")
                if href:
                    full_url = urljoin(base_url, href)
                    if full_url not in article_urls:
                        article_urls.append(full_url)
        else:
            for item in items:
                link = item.select_one(link_sel)
                if link is None:
                    link = item if item.name == "a" else item.find("a")
                if link is None:
                    continue
                href = link.get("href")
                if href:
                    full_url = urljoin(base_url, href)
                    if full_url not in article_urls:
                        article_urls.append(full_url)

        print(f"    Found {len(article_urls)} articles total")

        # Pagination
        next_sel = selectors["listing"]["pagination_next"]
        next_link = soup.select_one(next_sel)
        if next_link and next_link.get("href"):
            next_href = next_link.get("href", "")
            if next_href and next_href != "#":
                page_url = urljoin(base_url, next_href)
            else:
                page_url = ""
        else:
            page_url = ""

        if page_url:
            time.sleep(0.3)

    return article_urls


def scrape_article(
    client: httpx.Client,
    article_url: str,
    selectors: dict[str, Any],
) -> dict[str, Any] | None:
    """Scrape a single article page and return parsed data."""
    try:
        resp = client.get(article_url)
        resp.raise_for_status()
    except Exception as exc:
        print(f"  [ERROR] Failed to fetch {article_url}: {exc}", file=sys.stderr)
        return None

    soup = BeautifulSoup(resp.text, "html.parser")
    art_sel = selectors["article"]

    # Title
    title = extract_text(soup, art_sel["title"])
    if not title:
        title_tag = soup.find("title")
        title = title_tag.get_text(strip=True) if title_tag else "Untitled"
    title = re.sub(r"\s*[—–|-]+\s*[^—–|-]+$", "", title).strip()

    # Meta
    meta_title = title
    meta_desc = extract_text(soup, art_sel["meta_description"], "content")
    meta_keywords = extract_text(soup, art_sel["meta_keywords"], "content")
    if not meta_desc:
        meta_desc = extract_text(soup, "meta[property='og:description']", "content")

    # Views
    views_text = extract_text(soup, art_sel["views"])
    views = parse_views(views_text)

    # Content
    content_html = extract_html_inner(soup, art_sel["content"])
    if not content_html:
        for fallback in ["article", ".article-body", ".post-body", ".blog-post"]:
            content_html = extract_html_inner(soup, fallback)
            if content_html:
                break

    # Description (plain text snippet)
    desc_soup = BeautifulSoup(content_html, "html.parser") if content_html else None
    desc_text = desc_soup.get_text() if desc_soup else ""
    description = desc_text[:500].strip()

    # Extract SEO keyword from original URL
    seo_keyword = extract_keyword_from_url(article_url)

    # Extract categories from .p-category elements (Journal microformat)
    categories = []
    cat_elements = soup.select(".p-category")
    for cat_elem in cat_elements:
        cat_name = cat_elem.get_text(strip=True)
        cat_href = cat_elem.get("href", "")
        if cat_name:
            cat_keyword = (
                extract_keyword_from_url(cat_href) if cat_href else slugify(cat_name)
            )
            categories.append(
                {
                    "name": cat_name,
                    "keyword": cat_keyword,
                    "url": urljoin(article_url, cat_href) if cat_href else "",
                }
            )
    # Deduplicate by name
    seen_cats = set()
    unique_categories = []
    for cat in categories:
        if cat["name"].lower() not in seen_cats:
            seen_cats.add(cat["name"].lower())
            unique_categories.append(cat)
    categories = unique_categories

    return {
        "title": title,
        "meta_title": meta_title,
        "meta_description": meta_desc[:255] if meta_desc else "",
        "meta_keyword": meta_keywords[:255] if meta_keywords else "",
        "views": views,
        "content": content_html,
        "description": description,
        "url": article_url,
        "seo_keyword": seo_keyword,
        "categories": categories,
    }


# ── DB insert helpers ─────────────────────────────────────────────────────────


def insert_seo_url(
    db: Db,
    query: str,
    keyword: str,
    store_id: int,
    language_id: int,
) -> None:
    """Insert or update a blog SEO URL record."""
    if not keyword:
        return
    db.execute(
        f"""
        INSERT INTO `{db.prefix}blog_seo_url`
        (store_id, language_id, `query`, keyword)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE keyword = VALUES(keyword)
        """,
        (store_id, language_id, query, keyword),
    )


# ── Main migration ────────────────────────────────────────────────────────────


def migrate(config: dict[str, Any]) -> None:
    """Run the full migration."""
    src_cfg = config["source"]
    # Target DB config: YAML -> environment variables -> defaults
    tgt_yaml = config.get("target", {})
    tgt_cfg = {
        "host": tgt_yaml.get("host") or os.environ.get("DB_HOSTNAME", "mariadb"),
        "port": tgt_yaml.get("port") or int(os.environ.get("DB_PORT", "3306")),
        "user": tgt_yaml.get("user") or os.environ.get("DB_USERNAME", "dockercart"),
        "password": tgt_yaml.get("password")
        or os.environ.get("DB_PASSWORD", "dockercart"),
        "database": tgt_yaml.get("database")
        or os.environ.get("DB_DATABASE", "dockercart"),
        "prefix": tgt_yaml.get("prefix") or os.environ.get("DB_PREFIX", "oc_"),
    }
    defaults = config["defaults"]
    selectors = config["selectors"]
    dry_run = config.get("dry_run", False)
    clean = config.get("clean", False)

    base_url = src_cfg["base_url"].rstrip("/")

    config_dir = Path.cwd()
    image_dir = config_dir / defaults["image_dir"]
    image_base = defaults["image_base"]

    if not dry_run:
        image_dir.mkdir(parents=True, exist_ok=True)
        db: Db | None = Db(tgt_cfg)
    else:
        db = None
        print("\n=== DRY RUN — no DB writes, no image downloads ===\n")

    client = httpx.Client(
        headers={"User-Agent": src_cfg["user_agent"]},
        timeout=src_cfg["timeout"],
        follow_redirects=True,
    )

    sort_order_base = defaults["sort_order"]
    store_id = defaults["store_id"]
    total_posts = 0
    total_categories = 0

    # Track category_id -> keyword mapping for SEO URLs across languages
    # Key: category name (lowercase), Value: {language_id: (category_id, keyword)}
    category_map: dict[str, dict[int, tuple[int, str]]] = {}

    try:
        if clean and not dry_run:
            assert db is not None
            prefix = db.prefix
            print("\n[0] Cleaning blog tables...")
            # Clean blog post related tables
            db.execute(f"TRUNCATE TABLE `{prefix}blog_post`")
            db.execute(f"TRUNCATE TABLE `{prefix}blog_post_description`")
            db.execute(f"TRUNCATE TABLE `{prefix}blog_post_to_store`")
            db.execute(f"TRUNCATE TABLE `{prefix}blog_post_to_category`")
            # Clean blog category related tables
            db.execute(f"TRUNCATE TABLE `{prefix}blog_category`")
            db.execute(f"TRUNCATE TABLE `{prefix}blog_category_description`")
            db.execute(f"TRUNCATE TABLE `{prefix}blog_category_to_store`")
            # Clean blog SEO URLs for blog posts and categories
            db.execute(
                f"""
                DELETE FROM `{prefix}blog_seo_url`
                WHERE `query` LIKE 'blog_post_id=%' OR `query` LIKE 'blog_category_id=%'
                """
            )
            db.commit()
            print("  Blog tables cleaned.")
        elif clean:
            print("\n[0] [DRY] Would clean blog tables.")

        for lang_cfg in config["languages"]:
            code = lang_cfg["code"]
            index_url = lang_cfg["index_url"]
            language_id = lang_cfg["language_id"]

            print(f"\n{'=' * 60}")
            print(f"Language: {code} (lang_id={language_id})")
            print(f"Index:   {base_url}{index_url}")
            print(f"{'=' * 60}")

            # ── 2. Scrape article listing ────────────────────────────────
            print("\n[2] Scraping article listing...")
            article_urls = scrape_listing(client, base_url, index_url, selectors)
            print(f"  Total articles: {len(article_urls)}")

            if not article_urls:
                print("  No articles found — skipping language.")
                continue

            # ── 3. Scrape each article ───────────────────────────────────
            print(f"\n[3] Scraping {len(article_urls)} articles...")
            for idx, article_url in enumerate(article_urls):
                sort_order = sort_order_base + idx
                print(f"\n  [{idx + 1}/{len(article_urls)}] {article_url}")

                article = scrape_article(client, article_url, selectors)
                if article is None:
                    continue

                print(f"    Title:    {article['title'][:80]}")
                print(f"    Views:    {article['views']}")
                print(f"    Slug:     {article['seo_keyword']}")
                print(f"    Content:  {len(article['content'])} chars")

                # ── Create categories from article data ──────────────────
                if article.get("categories") and not dry_run:
                    assert db is not None
                    prefix = db.prefix
                    for cat in article["categories"]:
                        cat_name_key = cat["name"].lower()

                        if cat_name_key not in category_map:
                            # Create new category (shared across languages)
                            db.execute(
                                f"""
                                INSERT INTO `{prefix}blog_category`
                                (parent_id, status, sort_order, date_added, date_modified)
                                VALUES (%s, %s, %s, NOW(), NOW())
                                """,
                                (0, 1, total_categories),
                            )
                            category_id = db.conn.insert_id()
                            category_map[cat_name_key] = {}

                            # Category-to-store
                            db.execute(
                                f"""
                                INSERT IGNORE INTO `{prefix}blog_category_to_store`
                                (category_id, store_id) VALUES (%s, %s)
                                """,
                                (category_id, store_id),
                            )

                            total_categories += 1
                            print(f"  + category_id={category_id}: {cat['name']}")
                        else:
                            # Get existing category_id from first language entry
                            category_id = next(
                                iter(category_map[cat_name_key].values())
                            )[0]

                        # Store mapping for this language
                        category_map[cat_name_key][language_id] = (
                            category_id,
                            cat["keyword"],
                        )

                        # Category description (per language)
                        db.execute(
                            f"""
                            INSERT INTO `{prefix}blog_category_description`
                            (category_id, language_id, name, description,
                             meta_title, meta_description, meta_keyword)
                            VALUES (%s, %s, %s, %s, %s, %s, %s)
                            ON DUPLICATE KEY UPDATE
                                name = VALUES(name),
                                meta_title = VALUES(meta_title)
                            """,
                            (
                                category_id,
                                language_id,
                                cat["name"],
                                "",
                                cat["name"],
                                "",
                                "",
                            ),
                        )

                        # Category SEO URL (per language)
                        if cat["keyword"]:
                            insert_seo_url(
                                db,
                                f"blog_category_id={category_id}",
                                cat["keyword"],
                                store_id,
                                language_id,
                            )

                    db.commit()
                elif article.get("categories"):
                    for cat in article["categories"]:
                        print(f"  [DRY] Would create category: {cat['name']}")

                if dry_run:
                    continue

                # Rewrite images in content
                assert db is not None
                content_soup = BeautifulSoup(article["content"], "html.parser")
                images = content_soup.find_all("img")
                main_image: str | None = None

                for img in images:
                    src = img.get("src") or ""
                    if not src:
                        continue
                    img_url = urljoin(article_url, src)
                    print(f"    Image:    {img_url[:100]}...")
                    rel_path = download_image(client, img_url, image_dir, image_base)
                    if rel_path:
                        img["src"] = f"image/{rel_path}"
                        if main_image is None:
                            main_image = rel_path
                        if img.get("data-src"):
                            img["data-src"] = f"image/{rel_path}"

                rewritten_content = (
                    content_soup.decode_contents() if images else article["content"]
                )

                # Insert post
                prefix = db.prefix
                db.execute(
                    f"""
                    INSERT INTO `{prefix}blog_post`
                    (author_id, image, status, featured, allow_comments,
                     sort_order, views, date_published, date_added, date_modified)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW(), NOW())
                    """,
                    (
                        defaults["author_id"],
                        main_image or "",
                        defaults["status"],
                        0,
                        defaults["allow_comments"],
                        sort_order,
                        article["views"],
                    ),
                )
                post_id = db.conn.insert_id()
                print(f"    post_id:  {post_id}")

                # Post description
                db.execute(
                    f"""
                    INSERT INTO `{prefix}blog_post_description`
                    (post_id, language_id, name, description, content,
                     meta_title, meta_description, meta_keyword)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        description = VALUES(description),
                        content = VALUES(content),
                        meta_title = VALUES(meta_title),
                        meta_description = VALUES(meta_description),
                        meta_keyword = VALUES(meta_keyword)
                    """,
                    (
                        post_id,
                        language_id,
                        article["title"],
                        article["description"],
                        rewritten_content,
                        article["meta_title"],
                        article["meta_description"],
                        article["meta_keyword"],
                    ),
                )

                # Post-to-store
                db.execute(
                    f"""
                    INSERT IGNORE INTO `{prefix}blog_post_to_store`
                    (post_id, store_id) VALUES (%s, %s)
                    """,
                    (post_id, store_id),
                )

                # Post-to-category — assign from article's categories
                assigned_cat_id = 0
                if article.get("categories"):
                    # Use first category from the article
                    first_cat = article["categories"][0]
                    cat_name_key = first_cat["name"].lower()
                    if (
                        cat_name_key in category_map
                        and language_id in category_map[cat_name_key]
                    ):
                        assigned_cat_id = category_map[cat_name_key][language_id][0]

                # Fallback: use default category_id from config
                if assigned_cat_id == 0 and defaults.get("category_id", 0) > 0:
                    assigned_cat_id = defaults["category_id"]

                if assigned_cat_id > 0:
                    db.execute(
                        f"""
                        INSERT IGNORE INTO `{prefix}blog_post_to_category`
                        (post_id, category_id) VALUES (%s, %s)
                        """,
                        (post_id, assigned_cat_id),
                    )
                    print(f"    category: {assigned_cat_id}")

                # Post SEO URL
                if article["seo_keyword"]:
                    insert_seo_url(
                        db,
                        f"blog_post_id={post_id}",
                        article["seo_keyword"],
                        store_id,
                        language_id,
                    )

                db.commit()
                total_posts += 1
                time.sleep(src_cfg["delay"])

        # ── Summary ─────────────────────────────────────────────────────
        print(f"\n{'=' * 60}")
        print(f"Migration complete.")
        print(f"  Categories: {total_categories}")
        print(f"  Posts:      {total_posts}")
        if dry_run:
            print("  DRY RUN — no changes were made.")
        print(f"{'=' * 60}")

    except Exception:
        if db is not None:
            db.rollback()
        raise
    finally:
        client.close()
        if db is not None:
            db.close()


# ── CLI ───────────────────────────────────────────────────────────────────────


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Journal Blog → DockerCart Migration",
    )
    parser.add_argument(
        "-c",
        "--config",
        default="config.yaml",
        help="Path to configuration YAML file (default: config.yaml)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        default=None,
        help="Scrape and parse but do NOT write to DB or download images",
    )
    parser.add_argument(
        "--clean",
        action="store_true",
        default=None,
        help="Truncate all blog tables before migration (USE WITH CAUTION)",
    )
    args = parser.parse_args()

    config_path = Path(args.config)
    if not config_path.exists():
        print(f"Config file not found: {config_path}", file=sys.stderr)
        print(
            "Copy config.example.yaml to config.yaml and edit it.",
            file=sys.stderr,
        )
        sys.exit(1)

    with open(config_path, "r", encoding="utf-8") as f:
        config = yaml.safe_load(f)

    if args.dry_run is not None:
        config["dry_run"] = args.dry_run
    if args.clean is not None:
        config["clean"] = args.clean

    migrate(config)


if __name__ == "__main__":
    main()
