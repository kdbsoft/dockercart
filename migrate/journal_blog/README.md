# Journal Blog → DockerCart Migration

Scrapes a Journal-themed OpenCart blog from a donor site and imports:

- **Categories** → `oc_blog_category` + `oc_blog_category_description` + `oc_blog_seo_url`
- **Posts** → `oc_blog_post` + `oc_blog_post_description` + `oc_blog_seo_url` + `oc_blog_post_to_category`
- **Images** — downloaded locally, paths rewritten

## How it works

1. **Scrapes categories** — from the blog listing sidebar/filter area (configurable CSS selectors). Categories are shared across languages (same `category_id`, different descriptions per language).
2. **Scrapes the blog listing** — given an index URL (e.g. `/blog` for Ukrainian, `/ru/blog` for Russian), follows pagination and collects all article URLs.
3. **Scrapes each article** — extracts title, meta tags, view count, full HTML content, and original URL slug.
4. **Downloads images** — rewrites `<img src>` paths to point to the DockerCart `image/blog/journal/` directory.
5. **Inserts into DB** — populates all blog tables with proper SEO URLs.

## Configuration

All settings live in `config.yaml` (copy from `config.example.yaml`):

```yaml
source:
  base_url: "https://vorota-shop.com.ua"
  timeout: 30
  delay: 0.5
  user_agent: "DockerCart-JournalBlog-Migrator/1.0"

languages:
  - code: "uk"
    index_url: "/blog"
    language_id: 2
  - code: "ru"
    index_url: "/ru/blog"
    language_id: 3

selectors:
  listing:
    articles: ".blog-posts .post-item, .main-posts .post-item"
    article_link: "a.post-title, h2 a, .post-title a"
    pagination_next: ".pagination .next a, .pagination a.next"
    categories: ".blog-categories, .sidebar .blog-category"
    category_link: "a"
  article:
    title: "h1.title, .post-title h1, .journal-post-title"
    meta_description: "meta[name='description']"
    meta_keywords: "meta[name='keywords']"
    views: ".post-views, .view-count"
    content: ".post-content, .journal-post-content, article .content"

defaults:
  author_id: 1
  store_id: 0
  category_id: 0         # fallback category if no scraped category matches
  image_dir: "./images"
  image_base: "blog/journal"

dry_run: false
```

### Database credentials

Database credentials can be set in the `target` section of `config.yaml`. If omitted, the script will try to use environment variables (`DB_HOSTNAME`, `DB_PORT`, etc.), but for Docker Compose, it's recommended to rely on the mounted `config.yaml`.


### Key settings

| Field | Description |
|---|---|
| `languages[].index_url` | Blog listing path per language |
| `languages[].language_id` | Target `language_id` in `oc_language` |
| `selectors.listing.categories` | CSS selector for the category sidebar/filter container (leave empty to skip) |
| `selectors.listing.category_link` | CSS selector for `<a>` tags inside the category container |
| `selectors` (other) | CSS selectors for article parsing — tune these for non-standard themes |
| `defaults.author_id` | Author ID in `oc_blog_author` (create one first if needed) |
| `defaults.category_id` | Fallback category ID if no matching scraped category is found (0 = uncategorized) |

## Running

### Docker (recommended)

```bash
cd migrate/journal_blog/

# 1. Build the image
docker compose build

# 2. Dry-run — scrape and preview without writing to DB
docker compose run --rm migrate --dry-run

# 3. Full migration
docker compose run --rm migrate
```

The service attaches to `dockercart-network` (external), so `TARGET_HOST=mariadb` resolves to your DockerCart MariaDB container.

### Local / direct

```bash
cd migrate/journal_blog/
pip install -r requirements.txt

python migrate.py -c config.yaml --dry-run
python migrate.py -c config.yaml
```

## Tuning selectors

The default CSS selectors work with standard Journal 3 blog markup. If your donor site uses a custom theme:

1. Run with `--dry-run` first to verify articles and categories are found.
2. Inspect the donor site's HTML and adjust selectors in `config.yaml`.
3. Re-run dry-run until all data is captured correctly.

## Category handling

- Categories are scraped from the blog listing page sidebar/filter area.
- Categories are **shared across languages**: the same `category_id` gets descriptions in each language.
- Category SEO URLs are preserved from the donor site (the last path segment → keyword).
- If a post's URL contains a category keyword, the post is auto-assigned to that category.
- Use `defaults.category_id` as a fallback for posts that don't match any scraped category.

## SEO URLs

- Post SEO keywords are extracted from the original article URL path.
  Example: `https://example.com/blog/kak-vybrat-smartfon` → keyword: `kak-vybrat-smartfon`
- Category SEO keywords are extracted from the category link path.
- Inserts go to `oc_blog_seo_url` with proper query format:
  - Posts: `blog_post_id={id}`
  - Categories: `blog_category_id={id}`

## Multi-language posts

The script creates **separate `blog_post` rows per language** — each language gets its own `post_id`. If you want all languages to share one `post_id`, post-process after migration (merge `oc_blog_post_description` rows to point to a single `post_id`).

## Post-migration steps

1. Copy downloaded images to the DockerCart image directory:
   ```bash
   cp -r migrate/journal_blog/images/* upload/image/blog/journal/
   ```
2. Clear OpenCart cache: `make shell` then `rm -rf /var/www/storage/cache/*`.
mage directory:
   ```bash
   cp -r migrate/journal_blog/images/* upload/image/blog/journal/
   ```
2. Clear OpenCart cache: `make shell` then `rm -rf /var/www/storage/cache/*`.
