#!/usr/bin/env python3
"""
Live end-to-end test of the DockerCart customer (storefront) REST API.

This is NOT part of the dependency-free unit harness in tools/tests/cases
(those run without a database). It talks to a *running* stack over HTTP and
prepares its own fixtures through the MariaDB container:

  * creates two customer groups + four customers + a set of API keys with
    different scopes / expiry / IP allowlist / rate limit,
  * enables the customer API kill switch (restored afterwards),
  * exercises every endpoint of /store-api/v1 (happy paths, validation,
    auth, scoping, customer isolation, ETag/If-Match, Idempotency-Key,
    checkouts and order placement),
  * removes every row it created and restores every setting/product value
    it changed (unless --keep is passed).

Usage:
    python3 tools/tests/live/customer_api_e2e.py
    STORE_API_BASE=http://other-host:8080 python3 tools/tests/live/customer_api_e2e.py
    python3 tools/tests/live/customer_api_e2e.py --keep --verbose

Exit code is non-zero when at least one check fails.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
BASE = os.environ.get("STORE_API_BASE", "http://dockercart.local:8080")
API = BASE.rstrip("/") + "/store-api"

TOKEN_PREFIX = "dcapi_"
CUSTOMER_EMAIL_PREFIX = "e2e-api-"
CUSTOMER_EMAIL_DOMAIN = "@example.invalid"

ALL_SCOPES = ["products:read", "orders:read", "returns:read", "returns:write", "checkout:write"]

# Demo catalogue rows used by the tests (see docker/mysql/init.sql).
PRODUCT_SIMPLE = 5083          # non-configurable, in stock, shipping, price > 0
PRODUCT_OUT_OF_STOCK = 5034    # quantity 0, no preorder
PRODUCT_CONFIGURABLE = 5002    # 3 axes + a required non-axis option
PRODUCT_CALL_FOR_PRICE = 5001  # call_for_price = 1
VARIANT_OK = 615               # variant of PRODUCT_CONFIGURABLE, in stock
OPTION_WARRANTY = 9009         # required non-axis option of PRODUCT_CONFIGURABLE
OPTION_WARRANTY_VALUE = 9885
COUNTRY_UA = 220
ZONE_UA = 3480
COUNTRY_DE = 81
CATEGORY_WITH_PRODUCTS = 101

SHIPPING_CODE = "dockercart_universal.dockercart_universal_3"   # fixed cost 10
PAYMENT_CODE = "dockercart_universal.dockercart_universal_4"    # cash on delivery
COUPON_OK = "DEMO10"
COUPON_BAD = "NO-SUCH-COUPON"
VOUCHER_CODE = "E2E100"
RETURN_REASON_ID = 1
RETURN_ACTION_ID = 1

R = None          # Runner
S: dict = {}      # shared ids/tokens/state


# ─────────────────────────────── console ────────────────────────────────

class Runner:
    def __init__(self) -> None:
        self.passed = 0
        self.failed: list[tuple[str, str]] = []

    def section(self, title: str) -> None:
        print(f"\n== {title}")

    def check(self, name: str, condition: bool, detail: str = "") -> bool:
        if condition:
            self.passed += 1
            print(f"  ok   {name}")
            return True

        self.failed.append((name, detail))
        print(f"  FAIL {name}" + (f"  [{detail}]" if detail else ""))
        return False

    def eq(self, name: str, actual, expected) -> bool:
        return self.check(name, actual == expected, f"expected {expected!r}, got {actual!r}")

    def summary(self) -> int:
        total = self.passed + len(self.failed)
        print("\n" + "=" * 72)
        print(f"checks: {total}  passed: {self.passed}  failed: {len(self.failed)}")

        if self.failed:
            print("\nfailed checks:")

            for name, detail in self.failed:
                print(f"  - {name}" + (f"  [{detail}]" if detail else ""))

        print("=" * 72)

        return 0 if not self.failed else 1


# ─────────────────────────────── http ──────────────────────────────────

class Resp:
    def __init__(self, status: int, headers: dict, body: bytes) -> None:
        self.status = status
        self.headers = headers
        self.body = body

    @property
    def json(self):
        try:
            return json.loads(self.body.decode("utf-8"))
        except Exception:
            return None

    @property
    def data(self):
        payload = self.json
        return payload.get("data") if isinstance(payload, dict) else None

    @property
    def code(self):
        payload = self.json
        return payload.get("code") if isinstance(payload, dict) else None

    def header(self, name: str) -> str:
        for key, value in self.headers.items():
            if key.lower() == name.lower():
                return value

        return ""


def api(
    method: str,
    path: str,
    token: str | None = None,
    body=None,
    headers: dict | None = None,
    raw: bytes | None = None,
    token_header: str = "bearer",
    timeout: int = 90,
) -> Resp:
    url = API + path
    hdrs = {
        "Accept": "application/json",
        "Accept-Encoding": "identity",
        "User-Agent": "dockercart-customer-api-e2e/1.0",
    }

    data = None

    if raw is not None:
        data = raw
    elif body is not None:
        data = json.dumps(body).encode("utf-8")

    if data is not None:
        hdrs["Content-Type"] = "application/json"

    if token:
        if token_header.lower() == "x-api-key":
            hdrs["X-Api-Key"] = token
        else:
            hdrs["Authorization"] = "Bearer " + token

    hdrs.update(headers or {})

    request = urllib.request.Request(url, data=data, headers=hdrs, method=method)

    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return Resp(response.status, dict(response.headers), response.read())
    except urllib.error.HTTPError as error:
        return Resp(error.code, dict(error.headers), error.read())


def code_of(resp: Resp) -> str:
    payload = resp.json

    return payload.get("code", "") if isinstance(payload, dict) else ""


def fields_of(resp: Resp) -> dict:
    payload = resp.json
    fields = payload.get("fields", {}) if isinstance(payload, dict) else {}

    return fields if isinstance(fields, dict) else {}


def expect(name: str, resp: Resp, status: int, code: str | None = None) -> Resp:
    ok = R.eq(name + ": status", resp.status, status)

    if code is not None:
        R.eq(name + ": code", code_of(resp), code)

    if not ok:
        print(f"       body: {resp.body[:600]!r}")

    return resp


def money(value) -> float:
    return round(float(value), 4)


# ──────────────────────────────── db ───────────────────────────────────

def load_env() -> dict:
    values: dict[str, str] = {}
    path = ROOT / ".env"

    if not path.is_file():
        return values

    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()

        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")

    return values


ENV = load_env()
DB_NAME = ENV.get("DB_DATABASE", "dockercart")
DB_USER = ENV.get("DB_USERNAME", "dockercart")
DB_PASS = ENV.get("DB_PASSWORD", "")


def db(sql: str) -> list[list[str]]:
    command = [
        "docker", "compose",
        "-f", str(ROOT / "docker-compose.yml"),
        "exec", "-T", "mariadb", "mariadb",
        f"-u{DB_USER}", f"-p{DB_PASS}",
        "--batch", "--skip-column-names",
        "-D", DB_NAME,
        "-e", sql,
    ]

    result = subprocess.run(command, cwd=str(ROOT), capture_output=True, text=True)

    if result.returncode != 0:
        raise RuntimeError(f"SQL failed: {result.stderr.strip()}\nSQL: {sql[:400]}")

    return [line.split("\t") for line in result.stdout.splitlines() if line != ""]


def db1(sql: str):
    rows = db(sql)

    return rows[0][0] if rows else None


def setting(key: str) -> str:
    value = db1(f"SELECT `value` FROM oc_setting WHERE `key` = '{key}' AND store_id = 0")

    return "" if value is None else value


def set_setting(key: str, value: str) -> None:
    db(f"UPDATE oc_setting SET `value` = '{value}' WHERE `key` = '{key}' AND store_id = 0")


def token(slug: str) -> str:
    digest = hashlib.sha256(("dockercart-e2e|" + slug).encode("utf-8")).hexdigest()[:48]

    return TOKEN_PREFIX + digest


def token_hash(value: str) -> str:
    return hashlib.sha256(value.encode("utf-8")).hexdigest()


# ─────────────────────────────── setup ─────────────────────────────────

def sql_quote(value: str) -> str:
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


def purge_fixtures() -> None:
    """Remove everything a crashed previous run may have left behind."""
    rows = db(
        "SELECT customer_id, api_cart_scope FROM oc_customer WHERE email LIKE "
        + sql_quote(CUSTOMER_EMAIL_PREFIX + "%" + CUSTOMER_EMAIL_DOMAIN)
    )
    ids = [str(int(row[0])) for row in rows if row and row[0]]
    scopes = [row[1] for row in rows if len(row) > 1 and row[1]]

    if ids:
        customers = ",".join(ids)
        db(f"DELETE FROM oc_api_idempotency WHERE api_key_id IN (SELECT api_key_id FROM oc_api_key WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_api_log WHERE api_key_id IN (SELECT api_key_id FROM oc_api_key WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_api_key WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_api_checkout WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_cart WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_return_history WHERE return_id IN (SELECT return_id FROM oc_return WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_return_product WHERE return_id IN (SELECT return_id FROM oc_return WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_return WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_order_claim WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_history WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_product WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_total WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_option WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_voucher WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_stock_reservation WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_address WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_reward WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_transaction WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_wishlist WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_search WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_login WHERE email LIKE {sql_quote(CUSTOMER_EMAIL_PREFIX + '%' + CUSTOMER_EMAIL_DOMAIN)}")
        db(f"DELETE FROM oc_customer_ip WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_activity WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_history WHERE customer_id IN ({customers})")

        for scope in scopes:
            db(f"DELETE FROM oc_stock_reservation WHERE session_id = {sql_quote(scope)}")

        db(f"DELETE FROM oc_customer WHERE customer_id IN ({customers})")

    marker_groups = [row[0] for row in db("SELECT customer_group_id FROM oc_customer_group WHERE sort_order IN (987641, 987642)")]

    if marker_groups:
        group_list = ",".join(marker_groups)
        db("DELETE FROM oc_product_reward WHERE customer_group_id IN (" + group_list + ")")
        db("DELETE FROM oc_dockercart_product_customer_group_price WHERE customer_group_id IN (" + group_list + ")")

    db("DELETE FROM oc_customer_group WHERE sort_order IN (987641, 987642)")
    db(f"DELETE FROM oc_voucher WHERE code = {sql_quote(VOUCHER_CODE)}")
    db(f"DELETE FROM oc_api_key WHERE token_hash = {sql_quote(token_hash(token('admin')))}")


def setup() -> None:
    R.section("setup: fixtures")

    purge_fixtures()

    S["orig_setting_api"] = setting("config_api_customer_status")
    S["orig_setting_if_match"] = setting("config_api_require_if_match")
    S["orig_product"] = db(
        f"SELECT quantity, minimum, quantity_step FROM oc_product WHERE product_id = {PRODUCT_SIMPLE}"
    )[0]

    # groups: full access + API disabled
    db(
        "INSERT INTO oc_customer_group (approval, discount_percent, markup_percent, api_status, api_scopes, sort_order) "
        f"VALUES (0, 0, 0, 1, {sql_quote(json.dumps(ALL_SCOPES))}, 987641)"
    )

    S["group_full"] = int(db1("SELECT customer_group_id FROM oc_customer_group WHERE sort_order = 987641"))

    db(
        "INSERT INTO oc_customer_group (approval, discount_percent, markup_percent, api_status, api_scopes, sort_order) "
        "VALUES (0, 0, 0, 0, NULL, 987642)"
    )
    S["group_off"] = int(db1("SELECT customer_group_id FROM oc_customer_group WHERE sort_order = 987642"))

    def add_customer(suffix: str, group_id: int, api_status: int, api_scopes: str | None) -> int:
        email = CUSTOMER_EMAIL_PREFIX + suffix + CUSTOMER_EMAIL_DOMAIN
        scopes = "NULL" if api_scopes is None else sql_quote(api_scopes)

        db(
            "INSERT INTO oc_customer (customer_group_id, store_id, language_id, firstname, lastname, email, telephone, "
            "fax, password, salt, custom_field, ip, status, api_status, api_scopes, safe, token, code, date_added) "
            f"VALUES ({group_id}, 0, 1, 'E2E', '{suffix.capitalize()}', {sql_quote(email)}, '+380000000000', "
            f"'', 'x', 'x', '', '127.0.0.1', 1, {api_status}, {scopes}, 0, '', '', NOW())"
        )

        return int(db1(f"SELECT customer_id FROM oc_customer WHERE email = {sql_quote(email)}"))

    S["cust_a"] = add_customer("a", S["group_full"], -1, None)
    S["cust_b"] = add_customer("b", S["group_full"], -1, None)
    S["cust_off"] = add_customer("off", S["group_full"], 0, None)
    S["cust_group_off"] = add_customer("groupoff", S["group_off"], -1, None)

    def add_key(name: str, customer: int, slug: str, *, scopes=None, status=1, expires=None, ip="",
                rate=0, user_id=0, user_group_id=0) -> tuple[int, str]:
        plain = token(slug)
        scopes_sql = "NULL" if scopes is None else sql_quote(json.dumps(scopes))
        expires_sql = "NULL" if expires is None else expires

        db(
            "INSERT INTO oc_api_key (name, user_id, customer_id, comment, prefix, token_hash, user_group_id, status, "
            "expires_at, ip_allowlist, rate_limit, scopes, date_added, date_modified) "
            f"VALUES ({sql_quote(name)}, {user_id}, {customer}, '', {sql_quote(plain[:14])}, {sql_quote(token_hash(plain))}, "
            f"{user_group_id}, {status}, {expires_sql}, {sql_quote(ip)}, {rate}, {scopes_sql}, NOW(), NOW())"
        )

        key_id = int(db1(f"SELECT api_key_id FROM oc_api_key WHERE token_hash = {sql_quote(token_hash(plain))}"))

        return key_id, plain

    S["tok"] = {}
    S["key"] = {}

    S["key"]["full"], S["tok"]["full"] = add_key("e2e full", S["cust_a"], "full", rate=5000)
    S["key"]["products"], S["tok"]["products"] = add_key(
        "e2e products", S["cust_a"], "products", scopes=["products:read"]
    )
    S["key"]["orders"], S["tok"]["orders"] = add_key("e2e orders", S["cust_a"], "orders", scopes=["orders:read"])
    S["key"]["returns"], S["tok"]["returns"] = add_key(
        "e2e returns", S["cust_a"], "returns", scopes=["returns:read", "returns:write"]
    )
    S["key"]["checkout"], S["tok"]["checkout"] = add_key(
        "e2e checkout", S["cust_a"], "checkout", scopes=["checkout:write"]
    )
    S["key"]["expired"], S["tok"]["expired"] = add_key(
        "e2e expired", S["cust_a"], "expired", expires="DATE_SUB(NOW(), INTERVAL 1 DAY)"
    )
    S["key"]["revoked"], S["tok"]["revoked"] = add_key("e2e revoked", S["cust_a"], "revoked", status=0)
    S["key"]["ip"], S["tok"]["ip"] = add_key("e2e ip", S["cust_a"], "ip", ip="10.255.255.1")
    S["key"]["rate"], S["tok"]["rate"] = add_key("e2e rate", S["cust_a"], "rate", rate=2)
    S["key"]["b"], S["tok"]["b"] = add_key("e2e b", S["cust_b"], "b")
    S["key"]["off"], S["tok"]["off"] = add_key("e2e off", S["cust_off"], "off")
    S["key"]["group_off"], S["tok"]["group_off"] = add_key("e2e group off", S["cust_group_off"], "groupoff")
    S["key"]["admin"], S["tok"]["admin"] = add_key(
        "e2e admin", 0, "admin", user_id=1, user_group_id=1
    )

    # reward points: an earnable product reward for the new group + a balance
    db(
        "INSERT INTO oc_product_reward (product_id, customer_group_id, points) VALUES "
        f"({PRODUCT_SIMPLE}, {S['group_full']}, 100), ({PRODUCT_CONFIGURABLE}, {S['group_full']}, 613)"
    )

    # per-group price: the catalogue must return the buyer's group price, not the
    # default-group price
    db(
        "INSERT INTO oc_dockercart_product_customer_group_price (product_id, customer_group_id, price) "
        f"VALUES ({PRODUCT_SIMPLE}, {S['group_full']}, 600.0000)"
    )
    db(
        "INSERT INTO oc_customer_reward (customer_id, order_id, description, points, operation_type, date_added) "
        f"VALUES ({S['cust_a']}, 0, 'e2e reward', 50, 'legacy', NOW())"
    )

    # a redeemable gift voucher
    db(
        "DELETE FROM oc_voucher WHERE code = " + sql_quote(VOUCHER_CODE)
    )
    db(
        "INSERT INTO oc_voucher (order_id, code, from_name, from_email, to_name, to_email, voucher_theme_id, message, "
        f"amount, status, date_added) VALUES (0, {sql_quote(VOUCHER_CODE)}, 'e2e', 'e2e@example.invalid', 'e2e', "
        "'e2e@example.invalid', 6, '', 100, 1, NOW())"
    )

    set_setting("config_api_customer_status", "1")

    R.eq("api enabled", setting("config_api_customer_status"), "1")
    R.check("fixture group/customer/key ids", all([
        S["group_full"] > 0, S["group_off"] > 0, S["cust_a"] > 0, S["cust_b"] > 0,
        S["key"]["full"] > 0,
    ]), json.dumps({k: v for k, v in S.items() if isinstance(v, int)}))


# ───────────────────────────── discovery ───────────────────────────────

def t_discovery() -> None:
    R.section("discovery, transport, error envelope")

    r = api("GET", "/v1/openapi.json")
    expect("openapi without a key", r, 200)
    doc = r.json if isinstance(r.json, dict) else {}
    R.check("openapi document", doc.get("openapi", "").startswith("3.") and "/v1/products" in doc.get("paths", {}))
    etag = r.header("ETag")
    R.check("openapi ETag", etag.startswith('"'), etag)
    R.check("openapi public cache", "public" in r.header("Cache-Control"), r.header("Cache-Control"))

    r = api("GET", "/v1/openapi.json", headers={"If-None-Match": etag})
    expect("openapi If-None-Match", r, 304)
    R.eq("openapi 304 body", r.body, b"")

    r = api("GET", "/v1/does-not-exist")
    expect("unknown route", r, 404, "not_found")
    R.check("problem content type", "application/problem+json" in r.header("Content-Type"), r.header("Content-Type"))
    R.check("problem type urn", str((r.json or {}).get("type", "")).startswith("urn:dockercart:api:error:"))
    R.check("problem request id", bool((r.json or {}).get("request_id")))

    r = api("GET", "/v2/products")
    expect("unsupported version", r, 404, "unsupported_version")

    r = api("POST", "/v1/products")
    expect("method not allowed", r, 405, "method_not_allowed")
    R.check("Allow header", "GET" in r.header("Allow"), r.header("Allow"))

    r = api("GET", "/v1/products")
    expect("missing token", r, 401, "unauthorized")

    r = api("GET", "/v1/products?api_key=" + S["tok"]["full"])
    expect("token in query string is ignored", r, 401, "unauthorized")

    r = api("GET", "/v1/products", token=S["tok"]["admin"])
    expect("admin key rejected by store api", r, 401, "unauthorized")

    r = api("GET", "/v1/products", token=TOKEN_PREFIX + "0" * 48)
    expect("unknown token", r, 401, "unauthorized")

    r = api("GET", "/v1/products", token=S["tok"]["full"])
    expect("bearer token", r, 200)
    R.check("X-Request-Id", len(r.header("X-Request-Id")) >= 8, r.header("X-Request-Id"))
    R.check("rate limit headers", r.header("X-RateLimit-Limit") != "" and r.header("X-RateLimit-Remaining") != "", str(r.headers))
    R.eq("no Set-Cookie", r.header("Set-Cookie"), "")

    r = api("GET", "/v1/products", token=S["tok"]["full"], token_header="x-api-key")
    expect("X-Api-Key token", r, 200)

    r = api("GET", "/v1/products", token=S["tok"]["full"], headers={"X-Request-Id": "e2e-request-0001"})
    R.eq("incoming X-Request-Id echoed", r.header("X-Request-Id"), "e2e-request-0001")

    r = api("POST", "/v1/cart/items", token=S["tok"]["full"], raw=b"{not json")
    expect("invalid JSON", r, 400, "invalid_json")

    r = api("GET", "/v1/products/5083/", token=S["tok"]["full"])
    expect("trailing slash", r, 200)

    r = api("HEAD", "/v1/products", token=S["tok"]["full"])
    R.eq("HEAD status", r.status, 200)
    R.eq("HEAD empty body", r.body, b"")


# ───────────────────────────── auth edges ──────────────────────────────

def t_auth() -> None:
    R.section("authentication edge cases")

    r = api("GET", "/v1/products", token=S["tok"]["expired"])
    expect("expired key", r, 401, "token_expired")

    r = api("GET", "/v1/products", token=S["tok"]["revoked"])
    expect("revoked key", r, 401, "token_revoked")

    r = api("GET", "/v1/products", token=S["tok"]["off"])
    expect("customer api_status=0", r, 403, "api_access_disabled")

    r = api("GET", "/v1/products", token=S["tok"]["group_off"])
    expect("customer group api disabled", r, 403, "api_access_disabled")

    r = api("GET", "/v1/products", token=S["tok"]["ip"])
    expect("IP allowlist mismatch", r, 403, "ip_not_allowed")

    client_ip = db1(
        f"SELECT ip FROM oc_api_log WHERE api_key_id = {S['key']['ip']} ORDER BY api_log_id DESC LIMIT 1"
    )
    R.check("journal recorded the client ip", bool(client_ip), str(client_ip))

    if client_ip:
        db(f"UPDATE oc_api_key SET ip_allowlist = {sql_quote(client_ip)} WHERE api_key_id = {S['key']['ip']}")
        r = api("GET", "/v1/products", token=S["tok"]["ip"])
        expect("IP allowlist exact match", r, 200)

        db(f"UPDATE oc_api_key SET ip_allowlist = {sql_quote(client_ip + '/32')} WHERE api_key_id = {S['key']['ip']}")
        r = api("GET", "/v1/products", token=S["tok"]["ip"])
        expect("IP allowlist CIDR match", r, 200)

        db(f"UPDATE oc_api_key SET ip_allowlist = '10.255.255.1' WHERE api_key_id = {S['key']['ip']}")

    # customer status 0 invalidates the key
    db(f"UPDATE oc_customer SET status = 0 WHERE customer_id = {S['cust_a']}")
    r = api("GET", "/v1/products", token=S["tok"]["full"])
    expect("disabled customer invalidates key", r, 401, "token_revoked")
    db(f"UPDATE oc_customer SET status = 1 WHERE customer_id = {S['cust_a']}")

    # deleted key stops working immediately
    temp_id, temp_token = S["key"]["temp"], S["tok"]["temp"]
    db(f"DELETE FROM oc_api_key WHERE api_key_id = {temp_id}")
    r = api("GET", "/v1/products", token=temp_token)
    expect("deleted key", r, 401, "unauthorized")

    # per-key rate limit
    limited = False

    for _ in range(8):
        r = api("GET", "/v1/meta/return-reasons", token=S["tok"]["rate"])

        if r.status == 429:
            limited = True
            expect("rate limited", r, 429, "rate_limited")
            R.check("rate limit remaining is 0", r.header("X-RateLimit-Remaining") == "0", r.header("X-RateLimit-Remaining"))
            break

        time.sleep(0.05)

    R.check("per-key rate limit reached", limited)

    # journal row reflects the accepted request
    time.sleep(0.2)
    row = db(
        f"SELECT status_code, error_code FROM oc_api_log WHERE api_key_id = {S['key']['full']} "
        "AND status_code = 200 ORDER BY api_log_id DESC LIMIT 1"
    )
    R.check("journal written", bool(row) and row[0][0] == "200", str(row))


# ───────────────────────────── scope matrix ────────────────────────────

def t_scopes() -> None:
    R.section("scope enforcement")

    matrix = {
        "products": {"products": 200, "orders": 403, "returns": 403, "cart": 403, "countries": 403, "reasons": 403},
        "orders": {"products": 403, "orders": 200, "returns": 403, "cart": 403, "countries": 403, "reasons": 403},
        "returns": {"products": 403, "orders": 403, "returns": 200, "cart": 403, "countries": 403, "reasons": 200},
        "checkout": {"products": 403, "orders": 403, "returns": 403, "cart": 200, "countries": 200, "reasons": 403},
    }

    paths = {
        "products": "/v1/products?limit=1",
        "orders": "/v1/orders?limit=1",
        "returns": "/v1/returns?limit=1",
        "cart": "/v1/cart",
        "countries": "/v1/meta/countries",
        "reasons": "/v1/meta/return-reasons",
    }

    for scope_key, expectations in matrix.items():
        token_value = S["tok"][scope_key]

        for target, status in expectations.items():
            r = api("GET", paths[target], token=token_value)
            name = f"{scope_key} key -> {target}"
            R.eq(name, r.status, status)

            if status == 403:
                R.eq(name + " code", code_of(r), "forbidden")

    # write scope: returns:read alone can not create a return
    r = api("POST", "/v1/returns", token=S["tok"]["products"], body={})
    R.eq("products key cannot POST returns", r.status, 403)

    time.sleep(0.2)
    row = db(
        f"SELECT status_code, error_code FROM oc_api_log WHERE api_key_id = {S['key']['products']} "
        "ORDER BY api_log_id DESC LIMIT 1"
    )
    R.check("journal records forbidden", bool(row) and row[0][0] == "403" and row[0][1] == "forbidden", str(row))

    # a customer-level scope override replaces the group scopes
    db(f"UPDATE oc_customer SET api_status = 1, api_scopes = '[\"products:read\"]' WHERE customer_id = {S['cust_a']}")
    r = api("GET", "/v1/products?limit=1", token=S["tok"]["full"])
    expect("customer scope override: products", r, 200)
    r = api("GET", "/v1/orders?limit=1", token=S["tok"]["full"])
    expect("customer scope override narrows", r, 403, "forbidden")
    db(f"UPDATE oc_customer SET api_status = -1, api_scopes = NULL WHERE customer_id = {S['cust_a']}")

    # key scopes are intersected with the customer scopes
    r = api("GET", "/v1/orders?limit=1", token=S["tok"]["full"])
    expect("restored full access", r, 200)


# ────────────────────────────── catalogue ──────────────────────────────

def t_catalogue() -> None:
    R.section("catalogue: products and categories")

    token_value = S["tok"]["full"]

    r = api("GET", "/v1/products?limit=5", token=token_value)
    expect("products list", r, 200)
    items = r.data or []
    meta = (r.json or {}).get("meta", {})
    R.check("products list non-empty", len(items) > 0, str(len(items)))
    R.check("products pagination meta", meta.get("page") == 1 and meta.get("limit") == 5 and meta.get("total", 0) > 0 and meta.get("pages", 0) > 0, json.dumps(meta))

    if items:
        first = items[0]
        R.check("product summary keys", all(key in first for key in [
            "product_id", "name", "model", "price", "currency_code", "is_configurable", "quantity", "in_stock",
            "manufacturer",
        ]), json.dumps(first)[:400])
        R.eq("product currency", first.get("currency_code"), "UAH")
        R.check("product price is a 4-decimal string", bool(re.fullmatch(r"-?\d+\.\d{4}", str(first.get("price")))), str(first.get("price")))

    r2 = api("GET", "/v1/products?limit=5&page=2", token=token_value)
    ids1 = {item["product_id"] for item in items}
    ids2 = {item["product_id"] for item in (r2.data or [])}
    R.check("page 2 differs", not (ids1 & ids2), f"{ids1} vs {ids2}")

    r = api("GET", "/v1/products?limit=1000", token=token_value)
    R.eq("limit clamped to 100", ((r.json or {}).get("meta", {})).get("limit"), 100)

    r = api("GET", "/v1/products?limit=100&sort=price&order=ASC", token=token_value)
    ordered_ids = [item["product_id"] for item in (r.data or [])]
    raw_prices = {
        int(row[0]): float(row[1])
        for row in db(
            "SELECT product_id, price FROM oc_product WHERE product_id IN ("
            + ",".join(str(product_id) for product_id in ordered_ids)
            + ")"
        )
    } if ordered_ids else {}
    raw_sequence = [raw_prices.get(product_id, 0.0) for product_id in ordered_ids]
    R.check("sort=price queries the base price in ascending order", raw_sequence == sorted(raw_sequence), str(raw_sequence[:8]))

    r = api("GET", "/v1/products?search=iPhone", token=token_value)
    R.check("search filter", ((r.json or {}).get("meta", {})).get("total", 0) >= 1, str(r.json)[:300])

    r = api("GET", "/v1/products?name=Apple", token=token_value)
    names = [item["name"] for item in (r.data or [])]
    R.check("name filter", bool(names) and all("Apple" in name for name in names), str(names[:3]))

    r = api("GET", f"/v1/products?product_ids={PRODUCT_SIMPLE},{PRODUCT_CONFIGURABLE}", token=token_value)
    got = {item["product_id"] for item in (r.data or [])}
    R.check("product_ids filter", got == {PRODUCT_SIMPLE, PRODUCT_CONFIGURABLE}, str(got))

    r = api("GET", f"/v1/products?category_id={CATEGORY_WITH_PRODUCTS}&limit=5", token=token_value)
    R.check("category filter", ((r.json or {}).get("meta", {})).get("total", 0) > 0, str(r.json)[:300])

    r = api("GET", "/v1/products?in_stock=1&limit=20", token=token_value)
    R.check("in_stock filter", all(item["in_stock"] for item in (r.data or [])), str([item["product_id"] for item in (r.data or [])]))

    r = api("GET", "/v1/products?special=1&limit=5", token=token_value)
    expect("special filter", r, 200)

    r = api("GET", f"/v1/products/{PRODUCT_SIMPLE}", token=token_value)
    expect("product card", r, 200)
    product = r.data or {}
    R.check("product card keys", all(key in product for key in [
        "descriptions", "images", "videos", "attributes", "options", "categories", "variants", "discounts",
        "related", "upsell", "accessory", "weight", "dimensions", "reward",
    ]), json.dumps(sorted(product.keys())))
    R.check("product descriptions keyed by language", "en-gb" in (product.get("descriptions") or {}), str(list((product.get("descriptions") or {}).keys())))
    R.eq("product uses the buyer's group price", product.get("price"), "600.0000")
    etag = r.header("ETag")
    R.check("product ETag", etag.startswith('"'), etag)

    r = api("GET", f"/v1/products/{PRODUCT_SIMPLE}", token=token_value, headers={"If-None-Match": etag})
    expect("product If-None-Match", r, 304)

    r = api("GET", f"/v1/products/{PRODUCT_SIMPLE}", token=token_value, headers={"If-None-Match": "W/" + etag})
    expect("product weak If-None-Match", r, 304)

    r = api("GET", "/v1/products/99999999", token=token_value)
    expect("unknown product", r, 404, "not_found")

    r = api("GET", f"/v1/products/{PRODUCT_SIMPLE}/variants", token=token_value)
    expect("variants of a simple product", r, 200)
    simple_variants = r.data or {}
    R.check("simple product has no variants", len(simple_variants.get("variants") or []) == 0, json.dumps(simple_variants)[:300])

    r = api("GET", f"/v1/products/{PRODUCT_CONFIGURABLE}/variants", token=token_value)
    expect("variants of a configurable product", r, 200)
    variants = r.data or {}
    R.check("variant axes", len(variants.get("options") or []) == 3, json.dumps(variants.get("options"))[:400])
    R.check("variant list", len(variants.get("variants") or []) > 0, str(len(variants.get("variants") or [])))
    R.check("variant ETag", r.header("ETag").startswith('"'))

    r = api("GET", "/v1/products/99999999/variants", token=token_value)
    expect("variants of unknown product", r, 404, "not_found")

    r = api("GET", f"/v1/products/{PRODUCT_SIMPLE}?language=ru-ua", token=token_value)
    ru_name = ((r.data or {}).get("descriptions") or {}).get("ru-ua", {}).get("name")
    en_name = ((product.get("descriptions") or {}).get("en-gb") or {}).get("name")
    R.check("language override", bool(ru_name) and ru_name != en_name, f"{en_name!r} vs {ru_name!r}")

    r = api("GET", f"/v1/products/{PRODUCT_SIMPLE}?language=xx-xx", token=token_value)
    expect("unknown language", r, 422, "validation_failed")
    R.check("unknown language field", "language" in fields_of(r), json.dumps(fields_of(r)))

    r = api("GET", "/v1/categories?limit=5", token=token_value)
    expect("categories list", r, 200)
    categories = r.data or []
    R.check("categories non-empty", len(categories) > 0, str(len(categories)))
    R.check("category summary keys", bool(categories) and all(key in categories[0] for key in [
        "category_id", "name", "parent_id",
    ]), json.dumps(categories[:1])[:300])

    r = api("GET", "/v1/categories/tree", token=token_value)
    expect("category tree", r, 200)
    R.check("category tree non-empty", isinstance(r.data, list) and len(r.data) > 0)

    if categories:
        category_id = categories[0]["category_id"]
        r = api("GET", f"/v1/categories/{category_id}", token=token_value)
        expect("category card", r, 200)
        R.check("category breadcrumb", isinstance((r.data or {}).get("path"), list), str((r.data or {}).get("path"))[:200])

    r = api("GET", "/v1/categories/99999999", token=token_value)
    expect("unknown category", r, 404, "not_found")


# ────────────────────────────── addresses ──────────────────────────────

def t_addresses() -> None:
    R.section("address book")

    token_value = S["tok"]["full"]

    r = api("GET", "/v1/addresses", token=token_value)
    expect("empty address book", r, 200)
    R.eq("empty address book size", len(r.data or []), 0)

    r = api("POST", "/v1/addresses", token=token_value, body={})
    expect("address required fields", r, 422, "validation_failed")
    R.check("address required fields listed", {"address_1", "city", "country_id"} <= set(fields_of(r)), json.dumps(fields_of(r)))

    r = api("POST", "/v1/addresses", token=token_value, body={"address_1": "x", "city": "Kyiv", "country_id": COUNTRY_UA})
    expect("address_1 too short", r, 422, "validation_failed")

    r = api("POST", "/v1/addresses", token=token_value, body={
        "address_1": "Main street 1", "city": "Kyiv", "country_id": 999999,
    })
    expect("unknown country", r, 422, "validation_failed")
    R.check("unknown country field", "country_id" in fields_of(r))

    r = api("POST", "/v1/addresses", token=token_value, body={
        "address_1": "Main street 1", "city": "Kyiv", "country_id": COUNTRY_UA,
    })
    expect("zone required for UA", r, 422, "validation_failed")
    R.check("missing zone field", "zone_id" in fields_of(r))

    r = api("POST", "/v1/addresses", token=token_value, body={
        "address_1": "Main street 1", "city": "Kyiv", "country_id": COUNTRY_UA, "zone_id": ZONE_UA,
        "postcode": "01001", "company": "E2E",
    })
    expect("create UA address", r, 201)
    address = r.data or {}
    S["addr1"] = int(address.get("address_id", 0))
    R.check("created address id", S["addr1"] > 0)
    R.eq("first address is default", address.get("is_default"), True)
    R.eq("address country name", address.get("country"), db1(f"SELECT name FROM oc_country WHERE country_id = {COUNTRY_UA}"))
    R.check("address zone name", bool(address.get("zone")), str(address.get("zone")))
    R.eq("address Location", r.header("Location"), f"/store-api/v1/addresses/{S['addr1']}")
    etag = r.header("ETag")
    R.check("address ETag", etag.startswith('"'), etag)

    r = api("GET", f"/v1/addresses/{S['addr1']}", token=token_value)
    expect("read address", r, 200)
    R.eq("read address id", (r.data or {}).get("address_id"), S["addr1"])

    r = api("GET", f"/v1/addresses/{S['addr1']}", token=token_value, headers={"If-None-Match": etag})
    expect("address If-None-Match", r, 304)

    r = api("PUT", f"/v1/addresses/{S['addr1']}", token=token_value, body={"city": "Lviv"}, headers={"If-Match": '"bogus"'})
    expect("address stale If-Match", r, 412, "precondition_failed")

    r = api("PUT", f"/v1/addresses/{S['addr1']}", token=token_value, body={"city": "Lviv"})
    expect("address update without If-Match", r, 200)
    R.eq("address updated", (r.data or {}).get("city"), "Lviv")
    new_etag = r.header("ETag")
    R.check("address ETag changes", new_etag != etag, f"{etag} vs {new_etag}")

    r = api("PUT", f"/v1/addresses/{S['addr1']}", token=token_value, body={"city": "Kyiv"}, headers={"If-Match": new_etag})
    expect("address update with If-Match", r, 200)
    R.eq("address updated again", (r.data or {}).get("city"), "Kyiv")

    # second address: postcode-required country, becomes the default
    r = api("GET", f"/v1/meta/countries/{COUNTRY_DE}/zones", token=token_value)
    expect("country zones", r, 200)
    zones = r.data or []
    R.check("DE zones", len(zones) > 0)

    if zones:
        de_zone = zones[0]["zone_id"]

        r = api("POST", "/v1/addresses", token=token_value, body={
            "address_1": "Hauptstrasse 1", "city": "Berlin", "country_id": COUNTRY_DE, "zone_id": de_zone,
        })
        expect("postcode required", r, 422, "validation_failed")
        R.check("postcode field", "postcode" in fields_of(r))

        r = api("POST", "/v1/addresses", token=token_value, body={
            "address_1": "Hauptstrasse 1", "city": "Berlin", "country_id": COUNTRY_DE, "zone_id": de_zone,
            "postcode": "10115", "default": True,
        })
        expect("create DE address", r, 201)
        S["addr2"] = int((r.data or {}).get("address_id", 0))
        R.eq("DE address is default", (r.data or {}).get("is_default"), True)

        r = api("GET", "/v1/addresses", token=token_value)
        rows = {row["address_id"]: row for row in (r.data or [])}
        R.eq("address book size", len(rows), 2)
        R.eq("default switched", (rows.get(S["addr1"], {}) or {}).get("is_default"), False)

        r = api("DELETE", f"/v1/addresses/{S['addr2']}", token=token_value, headers={"If-Match": '"bogus"'})
        expect("delete stale If-Match", r, 412, "precondition_failed")

        r = api("DELETE", f"/v1/addresses/{S['addr2']}", token=token_value)
        expect("delete address", r, 204)
        R.eq("delete body", r.body, b"")

        r = api("GET", f"/v1/addresses/{S['addr2']}", token=token_value)
        expect("deleted address", r, 404, "not_found")

        r = api("DELETE", f"/v1/addresses/{S['addr2']}", token=token_value)
        expect("delete twice", r, 404, "not_found")

    r = api("GET", "/v1/meta/countries", token=token_value)
    expect("countries meta", r, 200)
    countries = r.data or []
    R.check("countries non-empty", len(countries) > 0)
    R.check("country keys", bool(countries) and all(key in countries[0] for key in [
        "country_id", "name", "iso_code_2", "postcode_required",
    ]))

    r = api("GET", "/v1/meta/return-reasons", token=token_value)
    expect("return reasons meta", r, 200)
    R.check("return reason keys", bool(r.data) and {"return_reason_id", "name"} <= set(r.data[0]))

    r = api("GET", "/v1/meta/return-actions", token=token_value)
    expect("return actions meta", r, 200)
    R.check("return action keys", bool(r.data) and {"return_action_id", "name"} <= set(r.data[0]))

    # isolation: customer B must not see or change A's address
    b = S["tok"]["b"]
    r = api("GET", "/v1/addresses", token=b)
    R.eq("B sees no address", len(r.data or []), 0)

    for method, body in [("GET", None), ("PUT", {"city": "Hacked"}), ("DELETE", None)]:
        r = api(method, f"/v1/addresses/{S['addr1']}", token=b, body=body)
        R.eq(f"B {method} A address", r.status, 404)

    r = api("GET", f"/v1/addresses/{S['addr1']}", token=S["tok"]["full"])
    expect("A address untouched", r, 200)
    R.eq("A address city untouched", (r.data or {}).get("city"), "Kyiv")


# ───────────────────────────────── cart ────────────────────────────────

def t_cart() -> None:
    R.section("cart: lines, quantity rules, discounts, delivery quotes")

    token_value = S["tok"]["full"]

    r = api("DELETE", "/v1/cart", token=token_value)
    expect("clear cart", r, 200)
    R.eq("cart empty", len((r.data or {}).get("cart") or []), 0)
    R.eq("cart total", (r.data or {}).get("total"), "0.0000")

    r = api("GET", "/v1/cart", token=token_value)
    expect("read cart", r, 200)
    cart = r.data or {}
    R.check("cart representation keys", all(key in cart for key in [
        "cart", "items", "units", "weight", "totals", "total", "total_text", "currency_code", "has_shipping",
        "has_stock", "has_download", "applied", "warnings",
    ]), json.dumps(sorted(cart.keys())))
    R.eq("cart applied empty", cart.get("applied"), {"coupon": None, "voucher": None, "reward": 0, "vouchers": 0})

    r = api("POST", "/v1/cart/items", token=token_value, body={})
    expect("cart item product_id required", r, 422, "validation_failed")
    R.check("product_id field", "product_id" in fields_of(r))

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": 99999999})
    expect("cart unknown product", r, 404, "not_found")

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_OUT_OF_STOCK})
    expect("cart out of stock", r, 422, "validation_failed")
    R.check("out of stock field", "product_id" in fields_of(r))

    for quantity in (0, -1):
        r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": quantity})
        R.eq(f"cart quantity {quantity}", r.status, 422)

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 2})
    expect("add simple product", r, 201)
    cart = r.data or {}
    lines = cart.get("cart") or []
    R.eq("one cart line", len(lines), 1)
    line = lines[0] if lines else {}
    S["cart_line"] = int(line.get("cart_id", 0))
    R.eq("line product", line.get("product_id"), PRODUCT_SIMPLE)
    R.eq("line quantity", money(line.get("quantity")), 2.0)
    R.check("line price", money(line.get("price")) > 0, line.get("price"))
    R.eq("line total", money(line.get("total")), money(money(line.get("price")) * 2))
    R.eq("cart units", money(cart.get("units")), 2.0)

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 1})
    expect("add same product merges", r, 201)
    lines = (r.data or {}).get("cart") or []
    R.eq("merge keeps one line", len(lines), 1)
    R.eq("merge quantity", money((lines[0] if lines else {}).get("quantity")), 3.0)

    r = api("PATCH", f"/v1/cart/items/{S['cart_line']}", token=token_value, body={"quantity": 5})
    expect("update quantity", r, 200)
    R.eq("updated quantity", money((((r.data or {}).get("cart") or [{}])[0]).get("quantity")), 5.0)

    r = api("PATCH", f"/v1/cart/items/{S['cart_line']}", token=token_value, body={})
    expect("update quantity required", r, 422, "validation_failed")

    r = api("PATCH", "/v1/cart/items/99999999", token=token_value, body={"quantity": 1})
    expect("update unknown line", r, 404, "not_found")

    r = api("PATCH", f"/v1/cart/items/{S['cart_line']}", token=token_value, body={"quantity": 0})
    expect("quantity 0 removes the line", r, 200)
    R.eq("line removed", len((r.data or {}).get("cart") or []), 0)

    r = api("DELETE", f"/v1/cart/items/{S['cart_line']}", token=token_value)
    expect("delete removed line", r, 404, "not_found")

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 2})
    expect("re-add simple product", r, 201)
    S["cart_line"] = int((((r.data or {}).get("cart") or [{}])[0]).get("cart_id", 0))

    r = api("DELETE", f"/v1/cart/items/{S['cart_line']}", token=token_value)
    expect("delete line", r, 200)
    R.eq("cart empty after line delete", len((r.data or {}).get("cart") or []), 0)

    # configurable product: variant + required non-axis option
    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_CONFIGURABLE})
    expect("variant required", r, 422, "validation_failed")
    R.check("variant_id field", "variant_id" in fields_of(r))

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_CONFIGURABLE, "variant_id": 999999})
    expect("unknown variant", r, 422, "validation_failed")
    R.check("variant_id unknown field", "variant_id" in fields_of(r))

    r = api("POST", "/v1/cart/items", token=token_value, body={
        "product_id": PRODUCT_CONFIGURABLE, "variant_id": VARIANT_OK,
    })
    expect("required option missing", r, 422, "validation_failed")
    R.check("required option field", f"options.{OPTION_WARRANTY}" in fields_of(r), json.dumps(fields_of(r)))

    r = api("POST", "/v1/cart/items", token=token_value, body={
        "product_id": PRODUCT_CONFIGURABLE, "variant_id": VARIANT_OK,
        "options": {str(OPTION_WARRANTY): OPTION_WARRANTY_VALUE},
    })
    expect("add configurable product", r, 201)
    lines = (r.data or {}).get("cart") or []
    R.eq("configurable line", (lines[0] if lines else {}).get("variant_id"), VARIANT_OK)
    S["cart_line"] = int((lines[0] if lines else {}).get("cart_id", 0))

    r = api("POST", "/v1/cart/items", token=token_value, body={
        "product_id": PRODUCT_SIMPLE, "variant_id": VARIANT_OK,
    })
    expect("variant on a simple product", r, 422, "validation_failed")

    r = api("POST", "/v1/cart/items", token=token_value, body={
        "product_id": PRODUCT_SIMPLE, "options": {"999999": 1},
    })
    expect("unknown option", r, 422, "validation_failed")
    R.check("unknown option field", "options.999999" in fields_of(r), json.dumps(fields_of(r)))

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_CALL_FOR_PRICE, "variant_id": 0})
    expect("call for price product", r, 422, "validation_failed")

    # minimum quantity / step rules (temporarily reconfigured on the demo product)
    db(f"UPDATE oc_product SET minimum = 2, quantity_step = 3 WHERE product_id = {PRODUCT_SIMPLE}")

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 1})
    expect("below minimum quantity", r, 422, "validation_failed")
    R.check("minimum field", "quantity" in fields_of(r))

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 3})
    expect("quantity step mismatch", r, 422, "validation_failed")

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 5})
    expect("minimum + step multiple", r, 201)

    db(f"UPDATE oc_product SET minimum = 1, quantity_step = 1 WHERE product_id = {PRODUCT_SIMPLE}")

    r = api("PATCH", f"/v1/cart/items/{S['cart_line']}", token=token_value, body={"quantity": 1})
    expect("update configurable line", r, 200)

    # coupon
    r = api("POST", "/v1/cart/coupon", token=token_value, body={})
    expect("coupon required", r, 422, "validation_failed")

    r = api("POST", "/v1/cart/coupon", token=token_value, body={"coupon": COUPON_BAD})
    expect("invalid coupon", r, 422, "validation_failed")
    R.check("coupon field", "coupon" in fields_of(r))

    before = money(((api("GET", "/v1/cart", token=token_value).data) or {}).get("total"))
    r = api("POST", "/v1/cart/coupon", token=token_value, body={"coupon": COUPON_OK})
    expect("apply coupon", r, 200)
    cart = r.data or {}
    R.eq("coupon applied", (cart.get("applied") or {}).get("coupon"), COUPON_OK)
    after = money(cart.get("total"))
    R.check("coupon reduces total", after < before, f"{before} -> {after}")
    R.check("coupon total line", any(row.get("code") == "coupon" for row in (cart.get("totals") or [])), json.dumps(cart.get("totals"))[:400])

    r = api("DELETE", "/v1/cart/coupon", token=token_value)
    expect("remove coupon", r, 200)
    R.eq("coupon removed", (r.data or {}).get("applied", {}).get("coupon"), None)

    # gift voucher
    r = api("POST", "/v1/cart/voucher", token=token_value, body={"voucher": "NOPE"})
    expect("invalid voucher", r, 422, "validation_failed")

    r = api("POST", "/v1/cart/voucher", token=token_value, body={"voucher": VOUCHER_CODE})
    expect("apply voucher", r, 200)
    R.eq("voucher applied", (r.data or {}).get("applied", {}).get("voucher"), VOUCHER_CODE)

    r = api("DELETE", "/v1/cart/voucher", token=token_value)
    expect("remove voucher", r, 200)
    R.eq("voucher removed", (r.data or {}).get("applied", {}).get("voucher"), None)

    # reward points (balance 50, cart can spend 613)
    r = api("POST", "/v1/cart/reward", token=token_value, body={"reward": 0})
    expect("reward min", r, 422, "validation_failed")

    r = api("POST", "/v1/cart/reward", token=token_value, body={"reward": 1000})
    expect("reward above balance", r, 422, "validation_failed")

    before = money(((api("GET", "/v1/cart", token=token_value).data) or {}).get("total"))
    r = api("POST", "/v1/cart/reward", token=token_value, body={"reward": 10})
    expect("apply reward points", r, 200)
    cart = r.data or {}
    R.eq("reward applied", (cart.get("applied") or {}).get("reward"), 10)
    R.check("reward reduces total", money(cart.get("total")) < before, f"{before} -> {cart.get('total')}")

    r = api("DELETE", "/v1/cart/reward", token=token_value)
    expect("remove reward", r, 200)
    R.eq("reward removed", (r.data or {}).get("applied", {}).get("reward"), 0)

    # shipping / payment quotes
    r = api("GET", f"/v1/cart/shipping-methods?country_id={COUNTRY_UA}&zone_id={ZONE_UA}&postcode=01001&city=Kyiv", token=token_value)
    expect("cart shipping quotes", r, 200)
    quotes = r.data or []
    R.check("shipping quotes non-empty", len(quotes) >= 1, str(quotes)[:400])
    R.check("shipping quote keys", bool(quotes) and {"code", "title", "cost", "text"} <= set(quotes[0]))
    R.check("shipping quote code format", bool(quotes) and quotes[0]["code"].startswith("dockercart_universal."), str(quotes[:1])[:200])

    r = api("GET", f"/v1/cart/shipping-methods?address_id={S['addr1']}", token=token_value)
    expect("cart shipping quotes by address id", r, 200)
    R.check("address quotes non-empty", len(r.data or []) >= 1)

    r = api("GET", "/v1/cart/shipping-methods?address_id=99999999", token=token_value)
    expect("foreign/unknown quote address", r, 404, "not_found")

    r = api("GET", "/v1/cart/shipping-methods", token=token_value)
    expect("quote without address", r, 422, "validation_failed")

    r = api("GET", f"/v1/cart/payment-methods?country_id={COUNTRY_UA}&zone_id={ZONE_UA}", token=token_value)
    expect("cart payment methods", r, 200)
    methods = r.data or []
    R.check("payment methods non-empty", len(methods) >= 1, str(methods)[:300])
    R.check("payment method keys", bool(methods) and {"code", "title"} <= set(methods[0]))

    # the cart is the customer's real cart (same session scope as the storefront)
    scope = db1(f"SELECT api_cart_scope FROM oc_customer WHERE customer_id = {S['cust_a']}")
    R.check("api_cart_scope assigned", bool(scope), str(scope))
    cart_rows = db(
        f"SELECT customer_id, session_id FROM oc_cart WHERE customer_id = {S['cust_a']}"
    )
    R.check("cart rows belong to the customer scope", bool(cart_rows) and all(
        int(row[0]) == S["cust_a"] and row[1] == scope for row in cart_rows
    ), str(cart_rows))

    # isolation: B can not see or mutate A's cart line
    b = S["tok"]["b"]
    r = api("GET", "/v1/cart", token=b)
    R.eq("B cart empty", len((r.data or {}).get("cart") or []), 0)

    r = api("PATCH", f"/v1/cart/items/{S['cart_line']}", token=b, body={"quantity": 3})
    expect("B updates A line", r, 404, "not_found")

    r = api("DELETE", f"/v1/cart/items/{S['cart_line']}", token=b)
    expect("B deletes A line", r, 404, "not_found")

    # leave the cart with the configurable line for the checkout tests
    r = api("DELETE", "/v1/cart", token=token_value)
    expect("reset cart", r, 200)

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 2})
    expect("final cart line", r, 201)
    S["cart_line"] = int((((r.data or {}).get("cart") or [{}])[0]).get("cart_id", 0))


# ─────────────────────────────── checkout ──────────────────────────────

def t_checkout_main() -> None:
    R.section("checkout: open, quote, select, confirm")

    token_value = S["tok"]["full"]

    r = api("GET", "/v1/checkout", token=token_value)
    expect("no checkout session yet", r, 404, "not_found")

    # empty cart is a conflict
    r = api("DELETE", "/v1/cart", token=token_value)
    expect("cart emptied for conflict test", r, 200)
    r = api("POST", "/v1/checkout", token=token_value, body={"agree": True})
    expect("checkout with empty cart", r, 409, "cart_empty")

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 2})
    expect("cart restored", r, 201)

    r = api("POST", "/v1/checkout", token=token_value, body={"total": 1})
    expect("client-owned checkout field", r, 422, "validation_failed")
    R.check("rejected field listed", "total" in fields_of(r))

    r = api("POST", "/v1/checkout", token=token_value, body={"email": "not-an-email"})
    expect("invalid checkout email", r, 422, "validation_failed")

    r = api("POST", "/v1/checkout", token=token_value, body={
        "payment_address": {"address_id": S["addr1"]},
        "agree": True,
    })
    expect("open checkout", r, 201)
    state = r.data or {}
    checkout_id = int(state.get("checkout_id", 0))
    S["checkout_id"] = checkout_id
    R.check("checkout id", checkout_id > 0)
    R.eq("checkout Location", r.header("Location"), f"/store-api/v1/checkout/{checkout_id}")
    R.check("checkout ETag", r.header("ETag").startswith('"'))
    R.check("checkout state keys", all(key in state for key in [
        "checkout_id", "payment_address", "shipping_address", "shipping_code", "payment_code", "shipping_methods",
        "payment_methods", "cart", "missing", "can_confirm",
    ]), json.dumps(sorted(state.keys())))
    R.eq("checkout payment address id", (state.get("payment_address") or {}).get("address_id"), S["addr1"])
    R.eq("checkout shipped to payment address", (state.get("shipping_address") or {}).get("address_id"), S["addr1"])
    R.eq("checkout cannot confirm yet", state.get("can_confirm"), False)
    R.check("missing methods", {"shipping_method", "payment_method"} <= set(state.get("missing") or []), json.dumps(state.get("missing")))
    R.check("checkout shipping quotes offered", len(state.get("shipping_methods") or []) >= 1)
    R.check("checkout payment methods offered", len(state.get("payment_methods") or []) >= 1)

    r = api("GET", "/v1/checkout", token=token_value)
    expect("current checkout", r, 200)
    R.eq("current checkout id", (r.data or {}).get("checkout_id"), checkout_id)

    r = api("GET", f"/v1/checkout/{checkout_id}", token=token_value)
    expect("read checkout", r, 200)
    etag = r.header("ETag")
    R.check("checkout read ETag", etag.startswith('"'), etag)

    r = api("GET", f"/v1/checkout/{checkout_id}", token=token_value, headers={"If-None-Match": etag})
    expect("checkout If-None-Match", r, 304)

    r = api("GET", f"/v1/checkout/{checkout_id}/shipping-methods", token=token_value)
    expect("checkout shipping methods", r, 200)
    R.check("checkout shipping methods non-empty", len(r.data or []) >= 1)

    r = api("GET", f"/v1/checkout/{checkout_id}/payment-methods", token=token_value)
    expect("checkout payment methods", r, 200)
    R.check("checkout payment methods non-empty", len(r.data or []) >= 1)

    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={"shipping_code": "nope.nope"})
    expect("invalid shipping code", r, 422, "validation_failed")
    R.check("shipping_code field", "shipping_code" in fields_of(r))

    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={"shipping_code": SHIPPING_CODE})
    expect("select shipping", r, 200)
    R.eq("shipping selected", (r.data or {}).get("shipping_code"), SHIPPING_CODE)

    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={"payment_code": "nope.nope"})
    expect("invalid payment code", r, 422, "validation_failed")

    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={
        "payment_code": PAYMENT_CODE,
        "comment": "<b>API order</b>",
        "telephone": "+380501112233",
    })
    expect("select payment + comment", r, 200)
    state = r.data or {}
    R.eq("payment selected", state.get("payment_code"), PAYMENT_CODE)
    R.eq("comment is sanitised", state.get("comment"), "API order")
    R.eq("checkout can confirm", state.get("can_confirm"), True)
    R.eq("checkout missing", state.get("missing"), [])
    R.check("cart total > 0", money((state.get("cart") or {}).get("total")) > 0, str((state.get("cart") or {}).get("total")))

    # terms are required by config_checkout_id=5
    r = api("POST", f"/v1/checkout/{checkout_id}/confirm", token=token_value, body={"agree": False})
    expect("confirm without terms", r, 422, "validation_failed")
    R.check("agree field", "agree" in fields_of(r), json.dumps(fields_of(r)))

    r = api("POST", f"/v1/checkout/{checkout_id}/confirm", token=token_value, body={"agree": True, "total": 1})
    expect("confirm rejects client total", r, 422, "validation_failed")

    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={"agree": True})
    expect("accept terms", r, 200)
    R.eq("terms accepted", (r.data or {}).get("agree"), True)

    r = api("POST", f"/v1/checkout/{checkout_id}/confirm", token=token_value, body={"agree": True})
    expect("place order", r, 201)
    order = r.data or {}
    order_id = int(order.get("order_id", 0))
    S["order_id"] = order_id
    R.check("order id", order_id > 0)
    R.eq("order Location", r.header("Location"), f"/store-api/v1/orders/{order_id}")
    R.check("order ETag", r.header("ETag").startswith('"'))
    R.check("order line count", len(order.get("products") or []) >= 1, str(len(order.get("products") or [])))
    paid_lines = [
        line for line in (order.get("products") or [])
        if line.get("product_id") == PRODUCT_SIMPLE and money(line.get("price")) > 0
    ]
    R.eq("order contains the paid line", len(paid_lines), 1)
    R.check(
        "order contains the zero-priced gift lines",
        any(money(line.get("price")) == 0 for line in (order.get("products") or [])),
        json.dumps(order.get("products"))[:500],
    )
    R.eq("order customer", order.get("email"), CUSTOMER_EMAIL_PREFIX + "a" + CUSTOMER_EMAIL_DOMAIN)
    R.eq("order status", order.get("order_status_id"), 1)
    R.eq("order currency", order.get("currency_code"), "UAH")
    R.eq("order payment method", order.get("payment_method"), "Cash on Delivery")
    R.check("order shipping method", str(order.get("shipping_method", "")).startswith("Standard Shipping"), str(order.get("shipping_method")))
    R.eq("order address city", ((order.get("payment_address") or {}).get("city")), "Kyiv")
    R.check("order totals", any(row.get("code") == "total" for row in (order.get("totals") or [])), json.dumps(order.get("totals"))[:400])
    R.check("order history", len(order.get("history") or []) >= 1, json.dumps(order.get("history"))[:300])

    if paid_lines:
        S["order_product_id"] = int(paid_lines[0].get("order_product_id", 0))
        R.eq("order product quantity", money(paid_lines[0].get("quantity")), 2.0)
    elif order.get("products"):
        S["order_product_id"] = int(order["products"][0].get("order_product_id", 0))

    gift_lines = [line for line in (order.get("products") or []) if money(line.get("price")) == 0]
    R.check("gift lines are free", all(money(line.get("price")) == 0 for line in gift_lines), json.dumps(gift_lines)[:300])

    # the session is finished: cart cleared, no *open* session, confirm is a 409
    r = api("GET", "/v1/cart", token=token_value)
    R.eq("cart cleared after order", len((r.data or {}).get("cart") or []), 0)

    r = api("GET", "/v1/checkout", token=token_value)
    expect("no open checkout after order", r, 404, "not_found")

    # the completed session stays readable, so a client that lost the 201 can
    # still discover the order id
    r = api("GET", f"/v1/checkout/{checkout_id}", token=token_value)
    expect("completed checkout is readable", r, 200)
    summary = r.data or {}
    R.eq("completed checkout status", summary.get("status"), "completed")
    R.eq("completed checkout order id", summary.get("order_id"), order_id)
    R.eq("completed checkout is not payable twice", summary.get("payment_started"), False)
    R.check("completed checkout is a summary", "cart" not in summary, json.dumps(summary)[:300])

    r = api("POST", f"/v1/checkout/{checkout_id}/confirm", token=token_value, body={"agree": True})
    expect("double confirm", r, 409, "checkout_completed")

    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={"comment": "late"})
    expect("completed checkout cannot change", r, 409, "checkout_completed")

    r = api("DELETE", f"/v1/checkout/{checkout_id}", token=token_value)
    expect("completed checkout cannot be cancelled", r, 409, "checkout_completed")

    payment = t_checkout_payment(checkout_id, token_value, order_id)
    S["payment_kind"] = payment.get("kind")

    # stock hold bound to the order
    holds = db(
        f"SELECT COUNT(*) FROM oc_stock_reservation WHERE order_id = {order_id}"
    )
    R.check("stock reservation bound to the order", int(holds[0][0]) >= 1, str(holds))

    # the order row exists exactly once
    count = db1(f"SELECT COUNT(*) FROM oc_order WHERE customer_id = {S['cust_a']}")
    R.eq("one order row", int(count), 1)


def t_checkout_payment(checkout_id: int, token_value: str, order_id: int) -> dict:
    """The payment step of a confirmed checkout (runs the payment extension)."""
    R.section("checkout: start the payment")

    # only a confirmed checkout can be paid
    r = api("GET", "/v1/checkout", token=token_value)
    expect("no open checkout to pay", r, 404, "not_found")

    r = api("POST", f"/v1/checkout/{checkout_id}/payment", token=token_value, body={},
            headers={"Idempotency-Key": "e2e-payment-0001"})
    expect("start payment", r, 200)
    payment = r.data or {}

    R.eq("payment order id", payment.get("order_id"), order_id)
    R.eq("payment code", payment.get("payment_code"), PAYMENT_CODE)
    R.eq("payment module", payment.get("module"), "dockercart_universal")
    # the built-in method is passive: it redirects to the store's own success page
    R.eq("payment kind", payment.get("kind"), "internal")
    R.check("payment redirect is a store URL", str(payment.get("redirect_url", "")).find("checkout/success") >= 0,
            str(payment.get("redirect_url")))
    R.check("payment instructions", "cash" in str(payment.get("instructions", "")).lower(),
            str(payment.get("instructions"))[:200])
    R.check("payment hide internal keys", "_status_after" not in payment, json.dumps(payment)[:300])
    R.check("payment gateway object", isinstance(payment.get("gateway"), (dict, list, type(None))),
            str(type(payment.get("gateway"))))

    stored = db1(f"SELECT COUNT(*) FROM oc_api_checkout WHERE api_checkout_id = {checkout_id} AND payment_init IS NOT NULL")
    R.eq("payment answer is stored", int(stored), 1)

    # a repeat replays the stored answer instead of opening a second session
    r = api("POST", f"/v1/checkout/{checkout_id}/payment", token=token_value, body={})
    expect("payment replay", r, 200)
    R.eq("payment replay is identical", (r.data or {}).get("redirect_url"), payment.get("redirect_url"))
    R.eq("payment replay keeps created_at", (r.data or {}).get("created_at"), payment.get("created_at"))
    R.eq("payment replay adds refreshed_at", (r.data or {}).get("refreshed_at"), None)

    # refresh is allowed while the order has not moved on
    r = api("POST", f"/v1/checkout/{checkout_id}/payment?refresh=1", token=token_value, body={})
    expect("payment refresh", r, 200)
    R.check("payment refresh is stamped", (r.data or {}).get("refreshed_at") is not None,
            json.dumps(r.data)[:300])
    R.eq("payment refresh keeps created_at", (r.data or {}).get("created_at"), payment.get("created_at"))

    # once the order moves on, a refresh must refuse instead of charging twice
    db(f"UPDATE oc_order SET order_status_id = 132 WHERE order_id = {order_id}")

    r = api("POST", f"/v1/checkout/{checkout_id}/payment?refresh=1", token=token_value, body={})
    expect("payment refresh after a status change", r, 409, "payment_already_started")

    # ... but a plain replay still answers the stored result
    r = api("POST", f"/v1/checkout/{checkout_id}/payment", token=token_value, body={})
    expect("payment replay after a status change", r, 200)

    db(f"UPDATE oc_order SET order_status_id = 1 WHERE order_id = {order_id}")

    # a key without checkout:write cannot start a payment
    r = api("POST", f"/v1/checkout/{checkout_id}/payment", token=S["tok"]["orders"])
    expect("payment without the checkout scope", r, 403, "forbidden")

    # an unconfirmed checkout cannot be paid
    r = api("POST", "/v1/checkout/1/payment", token=token_value, body={})
    expect("payment of an unknown checkout", r, 404, "not_found")

    return payment


# ──────────────────────────────── orders ───────────────────────────────

def t_orders() -> None:
    R.section("orders: own orders only")

    token_value = S["tok"]["full"]
    order_id = S["order_id"]

    r = api("GET", "/v1/orders", token=token_value)
    expect("orders list", r, 200)
    rows = r.data or []
    meta = (r.json or {}).get("meta", {})
    R.eq("orders list size", len(rows), 1)
    R.eq("orders total", meta.get("total"), 1)
    R.eq("listed order id", (rows[0] if rows else {}).get("order_id"), order_id)

    r = api("GET", f"/v1/orders?status_id=1", token=token_value)
    R.eq("orders status filter hit", len(r.data or []), 1)

    r = api("GET", "/v1/orders?status_id=136", token=token_value)
    R.eq("orders status filter miss", len(r.data or []), 0)

    r = api("GET", "/v1/orders?sort=order_id&order=ASC&limit=1", token=token_value)
    expect("orders sorting/pagination", r, 200)

    r = api("GET", f"/v1/orders/{order_id}", token=token_value)
    expect("read own order", r, 200)
    full = r.data or {}
    R.check("order full keys", all(key in full for key in [
        "order_id", "order_status", "total", "products", "totals", "history", "payment_address", "shipping_address",
    ]), json.dumps(sorted(full.keys())))
    total_lines = [row for row in (full.get("totals") or []) if row.get("code") == "total"]
    R.eq(
        "order total line matches the summary",
        money(total_lines[0].get("value")) if total_lines else None,
        money(full.get("total")),
    )
    etag = r.header("ETag")
    R.check("order ETag", etag.startswith('"'), etag)

    r = api("GET", f"/v1/orders/{order_id}", token=token_value, headers={"If-None-Match": etag})
    expect("order If-None-Match", r, 304)

    r = api("GET", f"/v1/orders/{order_id}/history", token=token_value)
    expect("order history", r, 200)
    R.check("history non-empty", len(r.data or []) >= 1)
    R.check("history keys", bool(r.data) and {"order_status_id", "order_status", "date_added"} <= set(r.data[0]))

    r = api("GET", f"/v1/orders/{order_id}/history?limit=1", token=token_value)
    R.check("history limit", len(r.data or []) <= 1)

    # another customer's order is a 404, never a 403
    r = api("GET", "/v1/orders/99619", token=token_value)
    expect("foreign order", r, 404, "not_found")

    r = api("GET", "/v1/orders/99619/history", token=token_value)
    expect("foreign order history", r, 404, "not_found")

    r = api("GET", "/v1/orders/99999999", token=token_value)
    expect("unknown order", r, 404, "not_found")

    b = S["tok"]["b"]
    r = api("GET", "/v1/orders", token=b)
    R.eq("B sees no orders", len(r.data or []), 0)
    r = api("GET", f"/v1/orders/{order_id}", token=b)
    expect("B reads A order", r, 404, "not_found")


# ──────────────────────────────── returns ──────────────────────────────

def t_returns() -> None:
    R.section("returns: create, read, scope, validation, idempotency")

    token_value = S["tok"]["full"]
    order_id = S["order_id"]
    order_product_id = S["order_product_id"]

    r = api("GET", "/v1/returns", token=token_value)
    expect("empty returns list", r, 200)
    R.eq("empty returns size", len(r.data or []), 0)

    # validation
    r = api("POST", "/v1/returns", token=token_value, body={})
    expect("return required fields", r, 422, "validation_failed")
    R.check("return required fields listed", {"order_id", "products", "return_reason_id"} <= set(fields_of(r)), json.dumps(fields_of(r)))

    r = api("POST", "/v1/returns", token=token_value, body={
        "order_id": order_id, "products": [], "return_reason_id": RETURN_REASON_ID,
    })
    expect("return without lines", r, 422, "validation_failed")

    r = api("POST", "/v1/returns", token=token_value, body={
        "order_id": order_id, "products": [{"order_product_id": order_product_id, "quantity": 1}],
        "return_reason_id": 999,
    })
    expect("unknown return reason", r, 422, "validation_failed")
    R.check("return_reason_id field", "return_reason_id" in fields_of(r))

    r = api("POST", "/v1/returns", token=token_value, body={
        "order_id": order_id, "products": [{"order_product_id": order_product_id, "quantity": 99}],
        "return_reason_id": RETURN_REASON_ID,
    })
    expect("return quantity above ordered", r, 422, "validation_failed")

    r = api("POST", "/v1/returns", token=token_value, body={
        "order_id": order_id, "products": [{"order_product_id": 31, "quantity": 1}],
        "return_reason_id": RETURN_REASON_ID,
    })
    expect("line of another order", r, 422, "validation_failed")

    r = api("POST", "/v1/returns", token=token_value, body={
        "order_id": 99619, "products": [{"order_product_id": 37, "quantity": 1}],
        "return_reason_id": RETURN_REASON_ID,
    })
    expect("return for a foreign order", r, 422, "validation_failed")

    r = api("POST", "/v1/returns", token=token_value, body={
        "order_id": order_id, "products": [{"order_product_id": order_product_id, "quantity": 1}],
        "return_reason_id": RETURN_REASON_ID, "customer_id": 1,
    })
    expect("return rejects server-owned field", r, 422, "validation_failed")
    R.check("customer_id rejected", "customer_id" in fields_of(r))

    r = api("POST", "/v1/returns", token=token_value, body={
        "order_id": order_id, "products": [{"order_product_id": order_product_id, "quantity": 1}],
        "return_reason_id": RETURN_REASON_ID, "date_ordered": "not-a-date",
    })
    expect("return invalid date", r, 422, "validation_failed")

    # happy path, with an Idempotency-Key
    payload = {
        "order_id": order_id,
        "products": [{"order_product_id": order_product_id, "quantity": 1}],
        "return_reason_id": RETURN_REASON_ID,
        "return_action_id": RETURN_ACTION_ID,
        "opened": True,
        "comment": "e2e return",
    }

    r = api("POST", "/v1/returns", token=token_value, body=payload, headers={"Idempotency-Key": "e2e-return-0001"})
    expect("create return", r, 201)
    ret = r.data or {}
    return_id = int(ret.get("return_id", 0))
    S["return_id"] = return_id
    R.check("return id", return_id > 0)
    R.eq("return Location", r.header("Location"), f"/store-api/v1/returns/{return_id}")
    R.check("return ETag", r.header("ETag").startswith('"'))
    R.eq("return order", ret.get("order_id"), order_id)
    R.eq("return status forced to store default", ret.get("return_status_id"), 2)
    R.eq("return action", ret.get("return_action_id"), RETURN_ACTION_ID)
    R.eq("return opened", ret.get("opened"), True)
    R.eq("return type", ret.get("type"), "full")
    R.check("return product snapshot", len(ret.get("products") or []) == 1 and ret["products"][0]["order_product_id"] == order_product_id, json.dumps(ret.get("products"))[:400])
    R.eq("return product quantity", (ret.get("products") or [{}])[0].get("quantity"), 1)
    R.check("return comment", ret.get("comment") == "e2e return", str(ret.get("comment")))

    # replay
    r = api("POST", "/v1/returns", token=token_value, body=payload, headers={"Idempotency-Key": "e2e-return-0001"})
    expect("idempotent return replay", r, 201)
    R.eq("idempotent replay header", r.header("Idempotent-Replay"), "true")
    R.eq("idempotent replay body", (r.data or {}).get("return_id"), return_id)
    R.eq("one return row", int(db1(f"SELECT COUNT(*) FROM oc_return WHERE customer_id = {S['cust_a']}")), 1)

    r = api("POST", "/v1/returns", token=token_value, body=dict(payload, comment="different"),
            headers={"Idempotency-Key": "e2e-return-0001"})
    expect("idempotency conflict", r, 409, "idempotency_conflict")

    r = api("POST", "/v1/returns", token=token_value, body=payload, headers={"Idempotency-Key": "short"})
    expect("invalid Idempotency-Key", r, 400, "bad_request")
    R.check("Idempotency-Key field", "Idempotency-Key" in fields_of(r), json.dumps(fields_of(r)))

    # read back
    r = api("GET", f"/v1/returns/{return_id}", token=token_value)
    expect("read return", r, 200)
    R.eq("return read id", (r.data or {}).get("return_id"), return_id)
    etag = r.header("ETag")
    R.check("return read ETag", etag.startswith('"'), etag)

    r = api("GET", f"/v1/returns/{return_id}", token=token_value, headers={"If-None-Match": etag})
    expect("return If-None-Match", r, 304)

    r = api("GET", "/v1/returns", token=token_value)
    expect("returns list", r, 200)
    R.eq("returns list size", len(r.data or []), 1)
    R.eq("returns list id", (r.data or [{}])[0].get("return_id"), return_id)

    r = api("GET", f"/v1/returns?order_id={order_id}", token=token_value)
    R.eq("returns order filter", len(r.data or []), 1)

    r = api("GET", "/v1/returns?order_id=99619", token=token_value)
    R.eq("returns order filter miss", len(r.data or []), 0)

    r = api("GET", f"/v1/returns?status_id={ret.get('return_status_id')}", token=token_value)
    R.eq("returns status filter", len(r.data or []), 1)

    # isolation
    r = api("GET", f"/v1/returns/{return_id}", token=S["tok"]["b"])
    expect("B reads A return", r, 404, "not_found")

    r = api("GET", "/v1/returns", token=S["tok"]["b"])
    R.eq("B sees no returns", len(r.data or []), 0)

    r = api("POST", "/v1/returns", token=S["tok"]["b"], body=payload)
    expect("B returns against A order", r, 422, "validation_failed")

    # returns:read + returns:write key
    r = api("GET", "/v1/returns", token=S["tok"]["returns"])
    expect("returns key reads", r, 200)

    r = api("POST", "/v1/returns", token=S["tok"]["returns"], body={
        "order_id": order_id,
        "products": [{"order_product_id": order_product_id, "quantity": 1}],
        "return_reason_id": 2,
    })
    expect("returns key writes", r, 201)
    S["return_id_2"] = int((r.data or {}).get("return_id", 0))


# ──────────────────────── checkout: extra flows ────────────────────────

def t_checkout_extra() -> None:
    R.section("checkout: inline/saved addresses, idempotency, stock, cancel, isolation")

    token_value = S["tok"]["full"]

    # inline address with save: true, configurable product line
    r = api("DELETE", "/v1/cart", token=token_value)
    expect("reset cart", r, 200)

    r = api("POST", "/v1/cart/items", token=token_value, body={
        "product_id": PRODUCT_CONFIGURABLE, "variant_id": VARIANT_OK,
        "options": {str(OPTION_WARRANTY): OPTION_WARRANTY_VALUE}, "quantity": 1,
    })
    expect("cart: configurable", r, 201)

    before_addresses = len((api("GET", "/v1/addresses", token=token_value).data or []))

    r = api("POST", "/v1/checkout", token=token_value, body={
        "payment_address": {
            "firstname": "E2E", "lastname": "Inline", "address_1": "Inline street 7", "city": "Odesa",
            "country_id": COUNTRY_UA, "zone_id": ZONE_UA, "postcode": "65000", "save": True,
        },
        "ship_to_payment_address": True,
    })
    expect("checkout with inline saved address", r, 201)
    state = r.data or {}
    checkout_id = int(state.get("checkout_id", 0))
    R.check("inline address saved", len((api("GET", "/v1/addresses", token=token_value).data or [])) == before_addresses + 1)
    R.eq("inline checkout address city", ((state.get("payment_address") or {}).get("city")), "Odesa")

    r = api("POST", "/v1/checkout", token=token_value, body={"payment_address": {"address_id": S["addr1"]}})
    expect("reopen checkout replaces the session", r, 201)
    new_checkout_id = int((r.data or {}).get("checkout_id", 0))
    R.check("new checkout id", new_checkout_id != checkout_id)

    r = api("GET", f"/v1/checkout/{checkout_id}", token=token_value)
    expect("previous checkout replaced", r, 404, "not_found")

    checkout_id = new_checkout_id
    S["checkout_extra"] = checkout_id

    # PATCH with address_id / ship_to_payment_address
    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={
        "shipping_address": {"address_id": S["addr1"]},
        "ship_to_payment_address": True,
    })
    expect("patch shipping address", r, 200)

    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={"payment_address": {"address_id": 99999999}})
    expect("patch with unknown address", r, 404, "not_found")

    # idempotent confirm
    r = api("PATCH", f"/v1/checkout/{checkout_id}", token=token_value, body={
        "shipping_code": SHIPPING_CODE, "payment_code": PAYMENT_CODE, "agree": True,
    })
    expect("select methods", r, 200)
    R.eq("ready to confirm", (r.data or {}).get("can_confirm"), True)

    body = {"agree": True, "comment": "idempotent order"}
    r = api("POST", f"/v1/checkout/{checkout_id}/confirm", token=token_value, body=body,
            headers={"Idempotency-Key": "e2e-confirm-0001"})
    expect("idempotent confirm", r, 201)
    order_id = int((r.data or {}).get("order_id", 0))
    S["order_id_2"] = order_id

    r = api("POST", f"/v1/checkout/{checkout_id}/confirm", token=token_value, body=body,
            headers={"Idempotency-Key": "e2e-confirm-0001"})
    expect("confirm replay", r, 201)
    R.eq("confirm replay header", r.header("Idempotent-Replay"), "true")
    R.eq("confirm replay body", (r.data or {}).get("order_id"), order_id)
    R.eq("still one confirm order row", int(db1(f"SELECT COUNT(*) FROM oc_order WHERE customer_id = {S['cust_a']}")), 2)

    r = api("POST", f"/v1/checkout/{checkout_id}/confirm", token=token_value,
            body={"agree": True, "comment": "different"}, headers={"Idempotency-Key": "e2e-confirm-0001"})
    expect("confirm idempotency conflict", r, 409, "idempotency_conflict")

    # cancel flow
    r = api("DELETE", "/v1/cart", token=token_value)
    expect("reset cart", r, 200)

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 1})
    expect("cart line for cancel", r, 201)

    r = api("POST", "/v1/checkout", token=token_value, body={"payment_address": {"address_id": S["addr1"]}})
    expect("checkout to cancel", r, 201)
    cancel_id = int((r.data or {}).get("checkout_id", 0))

    r = api("DELETE", f"/v1/checkout/{cancel_id}", token=token_value)
    expect("cancel checkout", r, 204)
    R.eq("cancel body", r.body, b"")
    r = api("GET", f"/v1/checkout/{cancel_id}", token=token_value)
    expect("cancelled checkout", r, 404, "not_found")
    cart_scope = db1(f"SELECT api_cart_scope FROM oc_customer WHERE customer_id = {S['cust_a']}") or ""
    open_holds = int(db1(
        "SELECT COUNT(*) FROM oc_stock_reservation WHERE order_id IS NULL AND session_id = "
        + sql_quote(cart_scope)
    ) or 0)
    R.eq("holds released on cancel", open_holds, 0)

    # stock shortage blocks the order
    r = api("DELETE", "/v1/cart", token=token_value)
    expect("reset cart", r, 200)

    r = api("POST", "/v1/cart/items", token=token_value, body={"product_id": PRODUCT_SIMPLE, "quantity": 1000})
    expect("cart above stock", r, 201)

    r = api("POST", "/v1/checkout", token=token_value, body={"payment_address": {"address_id": S["addr1"]}})
    expect("checkout above stock", r, 201)
    stock_checkout = int((r.data or {}).get("checkout_id", 0))
    R.check("stock missing flagged", "stock" in ((r.data or {}).get("missing") or []), json.dumps((r.data or {}).get("missing")))

    r = api("POST", f"/v1/checkout/{stock_checkout}/confirm", token=token_value, body={"agree": True, "shipping_code": SHIPPING_CODE, "payment_code": PAYMENT_CODE})
    expect("insufficient stock", r, 409, "insufficient_stock")

    r = api("DELETE", f"/v1/checkout/{stock_checkout}", token=token_value)
    R.check("cleanup stock checkout", r.status in (204, 404), str(r.status))

    r = api("DELETE", "/v1/cart", token=token_value)
    expect("reset cart", r, 200)

    # checkout isolation
    r = api("GET", f"/v1/checkout/{S['checkout_id']}", token=S["tok"]["b"])
    expect("B reads A checkout", r, 404, "not_found")

    r = api("PATCH", f"/v1/checkout/{S['checkout_id']}", token=S["tok"]["b"], body={"comment": "x"})
    expect("B patches A checkout", r, 404, "not_found")

    r = api("DELETE", f"/v1/checkout/{S['checkout_id']}", token=S["tok"]["b"])
    expect("B deletes A checkout", r, 404, "not_found")


# ─────────────────────── precondition configuration ────────────────────

def t_preconditions() -> None:
    R.section("optimistic locking configuration")

    set_setting("config_api_require_if_match", "1")

    try:
        r = api("PUT", f"/v1/addresses/{S['addr1']}", token=S["tok"]["full"], body={"city": "Kyiv"})
        expect("If-Match required", r, 428, "precondition_required")

        r = api("GET", f"/v1/addresses/{S['addr1']}", token=S["tok"]["full"])
        etag = r.header("ETag")

        r = api("PUT", f"/v1/addresses/{S['addr1']}", token=S["tok"]["full"], body={"city": "Dnipro"},
                headers={"If-Match": etag})
        expect("If-Match satisfies the requirement", r, 200)

        r = api("DELETE", f"/v1/addresses/{S['addr1']}", token=S["tok"]["full"],
                headers={"If-Match": etag})
        expect("stale If-Match on delete", r, 412, "precondition_failed")
    finally:
        set_setting("config_api_require_if_match", S["orig_setting_if_match"])


# ───────────────────────────── kill switch ─────────────────────────────

def t_disabled() -> None:
    R.section("kill switch")

    set_setting("config_api_customer_status", "0")

    try:
        r = api("GET", "/v1/products", token=S["tok"]["full"])
        expect("disabled API: request", r, 503, "api_disabled")
        R.check("disabled API content type", "application/problem+json" in r.header("Content-Type"))

        r = api("GET", "/v1/openapi.json")
        expect("disabled API: openapi", r, 503, "api_disabled")
    finally:
        set_setting("config_api_customer_status", S["orig_setting_api"])


# ─────────────────────────────── cleanup ───────────────────────────────

def cleanup() -> None:
    R.section("cleanup")

    customer_ids = [str(S.get(key, 0)) for key in ("cust_a", "cust_b", "cust_off", "cust_group_off")]
    customers = ",".join(customer_ids)
    key_ids = ",".join(str(key_id) for key_id in S.get("key", {}).values() if key_id)

    try:
        set_setting("config_api_customer_status", S.get("orig_setting_api", "0"))
        set_setting("config_api_require_if_match", S.get("orig_setting_if_match", "0"))

        if S.get("orig_product"):
            quantity, minimum, quantity_step = S["orig_product"]
            db(
                f"UPDATE oc_product SET quantity = '{quantity}', minimum = '{minimum}', "
                f"quantity_step = '{quantity_step}' WHERE product_id = {PRODUCT_SIMPLE}"
            )

        if key_ids:
            db(f"DELETE FROM oc_api_idempotency WHERE api_key_id IN ({key_ids})")
            db(f"DELETE FROM oc_api_log WHERE api_key_id IN ({key_ids})")

        db(f"DELETE FROM oc_api_checkout WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_cart WHERE customer_id IN ({customers})")

        # returns + order children
        db(f"DELETE FROM oc_return_history WHERE return_id IN (SELECT return_id FROM oc_return WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_return_product WHERE return_id IN (SELECT return_id FROM oc_return WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_return WHERE customer_id IN ({customers})")

        db(f"DELETE FROM oc_order_claim WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_history WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_product WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_total WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_option WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order_voucher WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_stock_reservation WHERE order_id IN (SELECT order_id FROM oc_order WHERE customer_id IN ({customers}))")
        db(f"DELETE FROM oc_order WHERE customer_id IN ({customers})")

        if S.get("cust_a"):
            scope = db1(f"SELECT api_cart_scope FROM oc_customer WHERE customer_id = {S['cust_a']}")

            if scope:
                db(f"DELETE FROM oc_stock_reservation WHERE session_id = {sql_quote(scope)}")

        db(f"DELETE FROM oc_address WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_reward WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_transaction WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_wishlist WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_search WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_login WHERE email LIKE {sql_quote(CUSTOMER_EMAIL_PREFIX + '%' + CUSTOMER_EMAIL_DOMAIN)}")
        db(f"DELETE FROM oc_customer_ip WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_activity WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer_history WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_customer WHERE customer_id IN ({customers})")
        db(f"DELETE FROM oc_api_key WHERE customer_id IN ({customers})")

        # admin key we created (customer_id = 0)
        if S.get("key", {}).get("admin"):
            db(f"DELETE FROM oc_api_key WHERE api_key_id = {S['key']['admin']}")

        if S.get("group_full"):
            db(f"DELETE FROM oc_product_reward WHERE customer_group_id = {S['group_full']}")
            db(f"DELETE FROM oc_dockercart_product_customer_group_price WHERE customer_group_id = {S['group_full']}")

        db("DELETE FROM oc_customer_group WHERE sort_order IN (987641, 987642)")
        db(f"DELETE FROM oc_voucher WHERE code = {sql_quote(VOUCHER_CODE)}")

        left = int(db1(
            f"SELECT COUNT(*) FROM oc_customer WHERE email LIKE {sql_quote(CUSTOMER_EMAIL_PREFIX + '%' + CUSTOMER_EMAIL_DOMAIN)}"
        ))
        R.eq("fixtures removed", left, 0)
    except Exception as exception:  # noqa: BLE001 - cleanup must report, not crash
        R.check("cleanup", False, str(exception))
    finally:
        R.eq("setting restored: api", setting("config_api_customer_status"), S.get("orig_setting_api", "0"))
        R.eq("setting restored: if-match", setting("config_api_require_if_match"), S.get("orig_setting_if_match", "0"))


# ──────────────────────────────── main ─────────────────────────────────

def main() -> int:
    global R

    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--keep", action="store_true", help="do not remove fixtures / restore settings")
    parser.add_argument("--verbose", action="store_true", help="print response bodies of failing checks")
    args = parser.parse_args()

    R = Runner()

    # leftover fixtures from a crashed previous run
    try:
        leftovers = db1(
            f"SELECT COUNT(*) FROM oc_customer WHERE email LIKE {sql_quote(CUSTOMER_EMAIL_PREFIX + '%' + CUSTOMER_EMAIL_DOMAIN)}"
        )

        if int(leftovers) > 0:
            print(f"warning: {leftovers} leftover e2e customer(s) found; they will not be touched until cleanup")
    except Exception as exception:  # noqa: BLE001
        print(f"cannot reach the database: {exception}")
        return 2

    print(f"store API base: {API}")

    steps = [
        t_discovery,
        t_auth,
        t_scopes,
        t_catalogue,
        t_addresses,
        t_cart,
        t_checkout_main,
        t_orders,
        t_returns,
        t_checkout_extra,
        t_preconditions,
        t_disabled,
    ]

    step = None

    try:
        setup()

        # prepare a temp key entry (deleted inside t_auth)
        plain = token("temp")
        db(
            "INSERT INTO oc_api_key (name, user_id, customer_id, comment, prefix, token_hash, user_group_id, status, "
            f"expires_at, ip_allowlist, rate_limit, scopes, date_added, date_modified) VALUES ('e2e temp', 0, {S['cust_a']}, '', "
            f"{sql_quote(plain[:14])}, {sql_quote(token_hash(plain))}, 0, 1, NULL, '', 0, NULL, NOW(), NOW())"
        )
        S["tok"]["temp"] = plain
        S["key"]["temp"] = int(db1(f"SELECT api_key_id FROM oc_api_key WHERE token_hash = {sql_quote(token_hash(plain))}"))

        for step in steps:
            step()
    except Exception as exception:  # noqa: BLE001
        import traceback

        traceback.print_exc()
        R.check(f"{step.__name__ if step else 'setup'} crashed", False, f"{type(exception).__name__}: {exception}")
    finally:
        if args.keep:
            print("\n--keep: fixtures and settings were left in place")
        else:
            cleanup()

    return R.summary()


if __name__ == "__main__":
    sys.exit(main())
