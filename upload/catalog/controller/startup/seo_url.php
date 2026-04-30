<?php
/**
 * DockerCart SEO URL Controller
 *
 * @author Mykyta Tkachenko
 * @license MIT
 *
 * Licensed under the MIT License. You may obtain a copy of the License at
 * https://opensource.org/licenses/MIT
 */
class ControllerStartupSeoUrl extends Controller
{
    private const SEO_URL_CACHE_VERSION_KEY = "dockercart.seo_url_cache.version";
    private const SEO_URL_CACHE_KEY = "dockercart.seo_url_cache.map";
    private const BLOG_SEO_URL_CACHE_KEY = "dockercart.blog_seo_url_cache.map";

    // Request state properties (initialized once per request)
    private $isGetRequest;
    private $isXhr;
    private $storeId;
    private $languageId;

    // SEO URL maps (loaded from cache/memcached once per request)
    private $seoUrlCacheLoaded = false;
    private $blogSeoUrlCacheLoaded = false;
    private $seoKeywordsByQuery = [];
    private $seoKeywordsByQueryAny = [];
    private $seoQueriesByKeyword = [];
    private $seoQueriesByKeywordAny = [];
    private $seoKeywordQueryPairs = [];
    private $blogKeywordsByQuery = [];
    private $blogKeywordsByQueryAny = [];
    private $blogQueriesByKeyword = [];
    private $blogQueriesByKeywordAny = [];

    // DEBUG: Uncomment for debugging redirects
    // private function debug_log($message) {
    // 	file_put_contents('/var/www/storage/logs/error.log', date('Y-m-d H:i:s') . " DEBUG: $message\n", FILE_APPEND);
    // }
    private function debug_log($message)
    {
        // Disabled debug logging
    }

    public function index()
    {
        // Initialize request state properties once
        $this->initializeRequestState();

        // If this is a non-GET request (POST/PUT/DELETE) or an XHR (AJAX) request,
        // avoid running any SEO redirect/enforcement logic that could turn the
        // request into a 302/301 redirect (breaking AJAX POST flows). We should
        // however still register the URL rewriter when SEO is enabled so URL
        // generation continues to work.

        // Handle trailing slash redirect (only for GET requests)
        // Redirect URLs with trailing slash to version without slash
        // Example: /macbook/ → /macbook
        if ($this->isGetRequest && !$this->isXhr) {
            $this->handleTrailingSlashRedirect();
        }

        // Detect and set language from URL prefix BEFORE checking method
        // This ensures _route_ is properly decoded for both GET and POST requests
        $this->detectAndSetLanguageFromUrl();

        // Decode SEO URL for both GET and POST requests
        // This ensures POST requests to SEO URLs (e.g., /login) are routed correctly
        $this->decodeSeoUrl();

        if (!$this->isGetRequest || $this->isXhr) {
            if ($this->config->get("config_seo_url")) {
                // Register rewrite but skip any redirect/enforcement that follows
                $this->url->addRewrite($this);
            }
            return;
        }

        // SEO URL enforcement: redirect old format (index.php?route=...) to clean URL
        // Check this BEFORE adding rewriter to avoid conflicts
        if (
            $this->config->get("config_seo_url") &&
            !$this->isFeedRouteRequest()
        ) {
            $this->enforceCleanSeoUrls();
        }

        // Add rewrite to url class
        if ($this->config->get("config_seo_url")) {
            $this->url->addRewrite($this);
        }
    }

    /**
     * Feed routes should be served directly without SEO canonical redirects.
     * This keeps XML file URLs stable (no /uk-ua/... redirect).
     *
     * @return bool
     */
    private function isFeedRouteRequest()
    {
        $route = isset($this->request->get["route"])
            ? (string) $this->request->get["route"]
            : "";

        if ($route === "") {
            return false;
        }

        return strpos($route, "extension/feed/") === 0;
    }

    /**
     * Initialize request state properties
     * Called once at the beginning of index() to avoid repeated calculations
     */
    private function initializeRequestState()
    {
        $method = isset($this->request->server["REQUEST_METHOD"])
            ? strtoupper($this->request->server["REQUEST_METHOD"])
            : "GET";
        $this->isGetRequest = $method === "GET";
        $this->isXhr =
            isset($this->request->server["HTTP_X_REQUESTED_WITH"]) &&
            strtolower($this->request->server["HTTP_X_REQUESTED_WITH"]) ===
                "xmlhttprequest";
        $this->storeId = (int) $this->config->get("config_store_id");

        // Initialize languageId based on user's session language, not global config
        // This allows each user to have their own language preference
        $user_language = isset($this->session->data["language"])
            ? $this->session->data["language"]
            : $this->config->get("config_language");

        // Load language model to get language_id from code
        $this->load->model("localisation/language");
        $languages = $this->model_localisation_language->getLanguages();
        // languages array is indexed by code: ['uk-ua' => [...], 'en-gb' => [...]]

        if (isset($languages[$user_language])) {
            $this->languageId = (int) $languages[$user_language]["language_id"];
        } else {
            // Fallback to default system language
            $this->languageId = (int) $this->config->get("config_language_id");
        }
    }

    /**
     * Handle trailing slash redirect
     * Redirects URLs with trailing slash to version without trailing slash
     * Example: /macbook/ → /macbook
     * This ensures canonical URL format without trailing slashes
     */
    private function handleTrailingSlashRedirect()
    {
        // Get the request URI (path part only, without query string)
        $request_uri = isset($this->request->server["REQUEST_URI"])
            ? $this->request->server["REQUEST_URI"]
            : "";

        // Parse to separate path from query string
        $uri_parts = explode("?", $request_uri);
        $path = $uri_parts[0];
        $query_string = isset($uri_parts[1]) ? "?" . $uri_parts[1] : "";

        // Check if path has trailing slash and is not just '/'
        if (strlen($path) > 1 && substr($path, -1) === "/") {
            // Remove trailing slash
            $canonical_path = rtrim($path, "/");

            // Build and perform redirect
            $redirect_url = $this->buildRedirectUrl(
                $canonical_path . $query_string,
            );
            $this->response->redirect($redirect_url, 301);
            exit();
        }
    }

    public function rewrite($link)
    {
        $url_info = parse_url(str_replace("&amp;", "&", $link));

        $url = "";
        $data = [];

        // Check if query string exists before parsing
        if (isset($url_info["query"])) {
            parse_str($url_info["query"], $data);
        }

        $route = isset($data["route"]) ? $data["route"] : "";

        // Handle product/manufacturer/information (single entity with priority over path)
        if ($route == "product/product" && isset($data["product_id"])) {
            $product_id = (int) $data["product_id"];
            $url = $this->getSeoKeyword("product_id=" . $product_id);
            if ($url) {
                unset($data["product_id"], $data["path"], $data["route"]);
            } else {
                $fallback = $this->buildEntityFallbackKeyword(
                    "prod",
                    $product_id,
                    "product_id=" . $product_id,
                    $route,
                );
                if ($fallback) {
                    $url = $fallback;
                    unset($data["product_id"], $data["path"], $data["route"]);
                }
            }
        } elseif (
            $route == "product/manufacturer/info" &&
            isset($data["manufacturer_id"])
        ) {
            $manufacturer_id = (int) $data["manufacturer_id"];
            $url = $this->getSeoKeyword("manufacturer_id=" . $manufacturer_id);
            if ($url) {
                unset($data["manufacturer_id"], $data["route"]);
            } else {
                $fallback = $this->buildEntityFallbackKeyword(
                    "man",
                    $manufacturer_id,
                    "manufacturer_id=" . $manufacturer_id,
                    $route,
                );
                if ($fallback) {
                    $url = $fallback;
                    unset($data["manufacturer_id"], $data["route"]);
                }
            }
        } elseif (
            $route == "information/information" &&
            isset($data["information_id"])
        ) {
            $information_id = (int) $data["information_id"];
            $url = $this->getSeoKeyword("information_id=" . $information_id);
            if ($url) {
                unset($data["information_id"], $data["route"]);
            } else {
                $fallback = $this->buildEntityFallbackKeyword(
                    "inf",
                    $information_id,
                    "information_id=" . $information_id,
                    $route,
                );
                if ($fallback) {
                    $url = $fallback;
                    unset($data["information_id"], $data["route"]);
                }
            }
        } elseif (
            $route == "blog/post" &&
            (isset($data["blog_post_id"]) || isset($data["post_id"]))
        ) {
            // Blog post SEO URL - support both blog_post_id and post_id
            $post_id = isset($data["blog_post_id"])
                ? (int) $data["blog_post_id"]
                : (int) $data["post_id"];
            $url = $this->getBlogSeoKeyword("blog_post_id=" . $post_id);
            // Fallback: generate clean URL if no SEO entry exists
            if (!$url) {
                $url = "blog/post-" . $post_id;
            }
            unset($data["blog_post_id"], $data["post_id"], $data["route"]);
        } elseif (
            $route == "blog/category" &&
            (isset($data["blog_category_id"]) || isset($data["category_id"]))
        ) {
            // Blog category SEO URL - support both blog_category_id and category_id
            $cat_id = isset($data["blog_category_id"])
                ? (int) $data["blog_category_id"]
                : (int) $data["category_id"];
            $url = $this->getBlogSeoKeyword("blog_category_id=" . $cat_id);
            // Fallback: generate clean URL if no SEO entry exists
            if (!$url) {
                $url = "blog/category-" . $cat_id;
            }
            unset(
                $data["blog_category_id"],
                $data["category_id"],
                $data["route"],
            );
        } elseif (
            $route == "blog/author" &&
            (isset($data["blog_author_id"]) || isset($data["author_id"]))
        ) {
            // Blog author SEO URL - support both blog_author_id and author_id
            $author_id = isset($data["blog_author_id"])
                ? (int) $data["blog_author_id"]
                : (int) $data["author_id"];
            $url = $this->getBlogSeoKeyword("blog_author_id=" . $author_id);
            // Fallback: generate clean URL if no SEO entry exists
            if (!$url) {
                $url = "blog/author-" . $author_id;
            }
            unset($data["blog_author_id"], $data["author_id"], $data["route"]);
        } elseif (isset($data["path"])) {
            // Handle category path (only if no product/manufacturer/information)
            $categories = explode("_", $data["path"]);
            $url = "";
            $last_category_id = (int) end($categories);
            reset($categories);

            foreach ($categories as $category_id) {
                $keyword = $this->getSeoKeyword(
                    "category_id=" . (int) $category_id,
                );
                if ($keyword) {
                    $url .= "/" . $keyword;
                } else {
                    $url = "";
                    break;
                }
            }

            // If we have a path parameter, remove it and the route
            // Even if the URL couldn't be generated from SEO keywords,
            // we should not include the route in the output
            unset($data["path"]);
            if ($url) {
                unset($data["route"]);
            } else {
                $fallback = $this->buildEntityFallbackKeyword(
                    "cat",
                    $last_category_id,
                    "category_id=" . $last_category_id,
                    "product/category",
                );
                if ($fallback) {
                    $url = $fallback;
                    unset($data["route"]);
                }
            }
        } elseif ($route == "common/home") {
            // Home page - just use language prefix
            $url = "";
            unset($data["route"]);
        } elseif ($route == "product/category") {
            // Category should always be handled through path parameter
            // If we reach here with product/category route but no path,
            // don't generate SEO URL - let it be handled normally
            $url = "";
            // Note: We keep $data['route'] as is, so it will be returned as normal link
        } elseif ($route) {
            if ($this->isModuleRoute($route) && isset($data["module_id"])) {
                $module_id = (int) $data["module_id"];
                $module_keyword = $this->getSeoKeyword(
                    "module_id=" . $module_id,
                );

                if (!$module_keyword) {
                    $module_keyword = $this->buildEntityFallbackKeyword(
                        "mod",
                        $module_id,
                        "module_id=" . $module_id,
                        $route,
                    );
                }

                if ($module_keyword) {
                    $url = $module_keyword;
                    unset($data["module_id"], $data["route"]);
                }
            }

            if ($url) {
                // URL already resolved by module_id fallback
            } else {
                // Check if this route (controller) has a SEO URL in database
                // Examples: checkout/cart, information/contact, etc.
                $keyword = $this->getSeoKeyword($route);
                if ($keyword) {
                    $url = $keyword;
                    unset($data["route"]);
                } else {
                    // If not found in DB, generate SEO URL on the fly
                    // Only for routes without entity IDs like account/return/add
                    $generated_keyword = $this->generateSeoUrlFromRoute($route);
                    if ($generated_keyword) {
                        // PROTECTION: Check if generated keyword conflicts with existing SEO URL entry
                        // If our auto-generated URL matches an existing SEO URL with different query,
                        // it means we should NOT use the generated URL to avoid conflicts
                        if (
                            $this->hasConflictingSeoPrefixInDatabase(
                                $generated_keyword,
                                $route,
                            )
                        ) {
                            // Conflict detected - don't use the generated URL
                            // Let the route remain in query string instead
                            $url = "";
                        } else {
                            // No conflict - safe to use the generated URL
                            $url = $generated_keyword;
                            unset($data["route"]);
                        }
                    }
                }
            }
        }

        // Build query string
        $query = "";
        if ($data) {
            foreach ($data as $key => $value) {
                $query .=
                    "&" .
                    rawurlencode((string) $key) .
                    "=" .
                    rawurlencode(
                        is_array($value)
                            ? http_build_query($value)
                            : (string) $value,
                    );
            }

            if ($query) {
                $query = "?" . str_replace("&", "&amp;", trim($query, "&"));
            }
        }

        $base_path = str_replace("/index.php", "", $url_info["path"]);
        // Ensure base path ends with / for proper concatenation
        if (!$base_path || $base_path === "") {
            $base_path = "/";
        } elseif ($base_path !== "/" && substr($base_path, -1) !== "/") {
            $base_path .= "/";
        }

        // For home page or SEO URL, build with language prefix
        // IMPORTANT: Do NOT return generated SEO URLs for product/category without path
        // This prevents "product-category" from being generated as a fallback
        if (
            $url !== "" ||
            $route == "common/home" ||
            !isset($data["route"]) ||
            $route == "product/category"
        ) {
            // If product/category has no path, return the original link to prevent "product-category"
            if ($route == "product/category" && $url === "") {
                return $link;
            }
            return $url_info["scheme"] .
                "://" .
                $url_info["host"] .
                (isset($url_info["port"]) ? ":" . $url_info["port"] : "") .
                $base_path .
                $this->calculateLanguagePrefix() .
                ltrim($url, "/") .
                $query;
        }

        return $link;
    }

    /**
     * Get SEO keyword for a query (unified method)
     * Handles both entity IDs (product_id=123) and routes (checkout/cart)
     * @param string $query Query string like 'product_id=123' or route like 'checkout/cart'
     * @return string SEO keyword or empty string if not found
     */
    private function getSeoKeyword($query)
    {
        $this->loadSeoUrlCache();

        if (isset($this->seoKeywordsByQuery[$this->languageId][$query])) {
            return $this->seoKeywordsByQuery[$this->languageId][$query];
        }

        if (isset($this->seoKeywordsByQueryAny[$query])) {
            return $this->seoKeywordsByQueryAny[$query];
        }

        return "";
    }

    /**
     * Get blog SEO keyword from blog_seo_url table
     * Handles blog-specific entities (blog_post_id, blog_category_id, blog_author_id)
     * @param string $query Query string like 'blog_post_id=123'
     * @return string SEO keyword or empty string if not found
     */
    private function getBlogSeoKeyword($query)
    {
        $this->loadBlogSeoUrlCache();

        if (isset($this->blogKeywordsByQuery[$this->languageId][$query])) {
            return $this->blogKeywordsByQuery[$this->languageId][$query];
        }

        if (isset($this->blogKeywordsByQueryAny[$query])) {
            return $this->blogKeywordsByQueryAny[$query];
        }

        return "";
    }

    /**
     * Resolve query by SEO keyword from core seo_url table.
     */
    private function getSeoQueryByKeyword($keyword)
    {
        $this->loadSeoUrlCache();

        if (isset($this->seoQueriesByKeyword[$this->languageId][$keyword])) {
            return $this->seoQueriesByKeyword[$this->languageId][$keyword];
        }

        if (isset($this->seoQueriesByKeywordAny[$keyword])) {
            return $this->seoQueriesByKeywordAny[$keyword];
        }

        return "";
    }

    /**
     * Resolve query by SEO keyword from blog_seo_url table.
     */
    private function getBlogSeoQueryByKeyword($keyword)
    {
        $this->loadBlogSeoUrlCache();

        if (isset($this->blogQueriesByKeyword[$this->languageId][$keyword])) {
            return $this->blogQueriesByKeyword[$this->languageId][$keyword];
        }

        if (isset($this->blogQueriesByKeywordAny[$keyword])) {
            return $this->blogQueriesByKeywordAny[$keyword];
        }

        return "";
    }

    /**
     * Returns and initializes SEO cache version used for cache invalidation.
     */
    private function getSeoUrlCacheVersion()
    {
        $version = (int) $this->cache->get(self::SEO_URL_CACHE_VERSION_KEY);

        if ($version < 1) {
            $version = 1;
            $this->cache->set(self::SEO_URL_CACHE_VERSION_KEY, $version);
        }

        return $version;
    }

    /**
     * Ensure request context values are initialized before cache map loading.
     */
    private function ensureSeoUrlContext()
    {
        if ($this->storeId === null || $this->languageId === null) {
            $this->initializeRequestState();
        }
    }

    /**
     * Load and map seo_url table for current store from shared cache.
     */
    private function loadSeoUrlCache()
    {
        if ($this->seoUrlCacheLoaded) {
            return;
        }

        $this->ensureSeoUrlContext();

        $version = $this->getSeoUrlCacheVersion();
        $cache_key =
            self::SEO_URL_CACHE_KEY .
            "." .
            $version .
            ".store." .
            (int) $this->storeId;
        $payload = $this->cache->get($cache_key);

        if (!is_array($payload)) {
            $payload = [
                "query_to_keyword" => [],
                "query_to_keyword_any" => [],
                "keyword_to_query" => [],
                "keyword_to_query_any" => [],
                "keyword_query_pairs" => [],
            ];

            $query = $this->db->query(
                "SELECT query, keyword, language_id FROM " .
                    DB_PREFIX .
                    "seo_url
				WHERE store_id = '" .
                    (int) $this->storeId .
                    "'
				ORDER BY seo_url_id ASC",
            );

            foreach ($query->rows as $row) {
                $language_id = (int) $row["language_id"];
                $query_value = (string) $row["query"];
                $keyword = (string) $row["keyword"];

                if ($query_value === "" || $keyword === "") {
                    continue;
                }

                if (
                    !isset(
                        $payload["query_to_keyword"][$language_id][
                            $query_value
                        ],
                    )
                ) {
                    $payload["query_to_keyword"][$language_id][
                        $query_value
                    ] = $keyword;
                }

                if (!isset($payload["query_to_keyword_any"][$query_value])) {
                    $payload["query_to_keyword_any"][$query_value] = $keyword;
                }

                if (
                    !isset($payload["keyword_to_query"][$language_id][$keyword])
                ) {
                    $payload["keyword_to_query"][$language_id][
                        $keyword
                    ] = $query_value;
                }

                if (!isset($payload["keyword_to_query_any"][$keyword])) {
                    $payload["keyword_to_query_any"][$keyword] = $query_value;
                }

                $payload["keyword_query_pairs"][
                    $keyword . "||" . $query_value
                ] = true;
            }

            $this->cache->set($cache_key, $payload);
        }

        $this->seoKeywordsByQuery = isset($payload["query_to_keyword"])
            ? $payload["query_to_keyword"]
            : [];
        $this->seoKeywordsByQueryAny = isset($payload["query_to_keyword_any"])
            ? $payload["query_to_keyword_any"]
            : [];
        $this->seoQueriesByKeyword = isset($payload["keyword_to_query"])
            ? $payload["keyword_to_query"]
            : [];
        $this->seoQueriesByKeywordAny = isset($payload["keyword_to_query_any"])
            ? $payload["keyword_to_query_any"]
            : [];
        $this->seoKeywordQueryPairs = isset($payload["keyword_query_pairs"])
            ? $payload["keyword_query_pairs"]
            : [];
        $this->seoUrlCacheLoaded = true;
    }

    /**
     * Load and map blog_seo_url table for current store from shared cache.
     */
    private function loadBlogSeoUrlCache()
    {
        if ($this->blogSeoUrlCacheLoaded) {
            return;
        }

        $this->ensureSeoUrlContext();

        $version = $this->getSeoUrlCacheVersion();
        $cache_key =
            self::BLOG_SEO_URL_CACHE_KEY .
            "." .
            $version .
            ".store." .
            (int) $this->storeId;
        $payload = $this->cache->get($cache_key);

        if (!is_array($payload)) {
            $payload = [
                "query_to_keyword" => [],
                "query_to_keyword_any" => [],
                "keyword_to_query" => [],
                "keyword_to_query_any" => [],
            ];

            $query = $this->db->query(
                "SELECT query, keyword, language_id FROM " .
                    DB_PREFIX .
                    "blog_seo_url
				WHERE store_id = '" .
                    (int) $this->storeId .
                    "'
				ORDER BY language_id ASC, query ASC, keyword ASC",
            );

            foreach ($query->rows as $row) {
                $language_id = (int) $row["language_id"];
                $query_value = (string) $row["query"];
                $keyword = (string) $row["keyword"];

                if ($query_value === "" || $keyword === "") {
                    continue;
                }

                if (
                    !isset(
                        $payload["query_to_keyword"][$language_id][
                            $query_value
                        ],
                    )
                ) {
                    $payload["query_to_keyword"][$language_id][
                        $query_value
                    ] = $keyword;
                }

                if (!isset($payload["query_to_keyword_any"][$query_value])) {
                    $payload["query_to_keyword_any"][$query_value] = $keyword;
                }

                if (
                    !isset($payload["keyword_to_query"][$language_id][$keyword])
                ) {
                    $payload["keyword_to_query"][$language_id][
                        $keyword
                    ] = $query_value;
                }

                if (!isset($payload["keyword_to_query_any"][$keyword])) {
                    $payload["keyword_to_query_any"][$keyword] = $query_value;
                }
            }

            $this->cache->set($cache_key, $payload);
        }

        $this->blogKeywordsByQuery = isset($payload["query_to_keyword"])
            ? $payload["query_to_keyword"]
            : [];
        $this->blogKeywordsByQueryAny = isset($payload["query_to_keyword_any"])
            ? $payload["query_to_keyword_any"]
            : [];
        $this->blogQueriesByKeyword = isset($payload["keyword_to_query"])
            ? $payload["keyword_to_query"]
            : [];
        $this->blogQueriesByKeywordAny = isset($payload["keyword_to_query_any"])
            ? $payload["keyword_to_query_any"]
            : [];
        $this->blogSeoUrlCacheLoaded = true;
    }

    /**
     * Build deterministic fallback keyword for entity IDs.
     * Example: product_id=10 => prod10, category_id=20 => cat20.
     *
     * Returns empty string when fallback would conflict with existing explicit SEO URL.
     */
    private function buildEntityFallbackKeyword(
        $prefix,
        $entity_id,
        $entity_query,
        $route = null,
    ) {
        $entity_id = (int) $entity_id;

        if ($entity_id <= 0 || $prefix === "") {
            return "";
        }

        $candidate = $prefix . $entity_id;

        if ($this->hasConflictingSeoPrefixInDatabase($candidate, $route)) {
            return "";
        }

        return $candidate;
    }

    /**
     * Check whether the route points to a storefront module controller.
     */
    private function isModuleRoute($route)
    {
        return strpos((string) $route, "extension/module/") === 0;
    }

    /**
     * Resolve module route by module_id from OpenCart module table.
     * module_id=5 with code='featured' => extension/module/featured.
     */
    private function resolveModuleRouteById($module_id)
    {
        $module_id = (int) $module_id;

        if ($module_id <= 0) {
            return "";
        }

        $module = $this->db->query(
            "SELECT code FROM " .
                DB_PREFIX .
                "module WHERE module_id = '" .
                $module_id .
                "' LIMIT 1",
        );

        if (!$module->num_rows || empty($module->row["code"])) {
            return "";
        }

        $code = (string) $module->row["code"];

        if (!preg_match('/^[a-z0-9_]+$/', $code)) {
            return "";
        }

        return "extension/module/" . $code;
    }

    /**
     * Generate SEO URL on the fly from route
     * Examples: account/return/add → account-return-add, checkout/cart → checkout-cart
     */
    private function generateSeoUrlFromRoute($route)
    {
        // Skip generating URLs for admin, api, install routes
        if (
            strpos($route, "admin") === 0 ||
            strpos($route, "api") === 0 ||
            strpos($route, "install") === 0
        ) {
            return "";
        }

        // Skip common/home
        if ($route === "common/home") {
            return "";
        }

        // Replace slashes with dashes to create clean URL
        // account/return/add → account-return-add
        return str_replace("/", "-", $route);
    }

    /**
     * Check if generated SEO keyword has conflicts with database entries
     *
     * Protection mechanism: If we're about to use an auto-generated SEO URL,
     * we need to check if it conflicts with existing SEO URL entries.
     *
     * Example conflict:
     * - We want to generate 'checkout-cart' for route 'checkout/cart'
     * - But in DB exists: keyword='cart' for query='checkout/cart' (explicitly configured)
     * - Or exists: keyword='checkout-cart' for query='product_id=123' (different entity)
     * - In both cases, we should NOT use the generated 'checkout-cart' to avoid duplicates
     *
     * @param string $generated_keyword The auto-generated SEO URL keyword (e.g., 'checkout-cart')
     * @param string|null $route The route for which we're generating (optional, for more precise checking)
     * @return bool True if conflict detected, False if safe to use
     */
    private function hasConflictingSeoPrefixInDatabase(
        $generated_keyword,
        $route = null,
    ) {
        $this->loadSeoUrlCache();

        if (isset($this->seoQueriesByKeywordAny[$generated_keyword])) {
            return true;
        }

        if ($route) {
            $parts = explode("-", $generated_keyword);
            if (count($parts) > 1) {
                $last_part = end($parts);
                if (
                    isset(
                        $this->seoKeywordQueryPairs[$last_part . "||" . $route],
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Perform canonical redirect with query string preservation
     */
    private function performCanonicalRedirect($canonical_keyword)
    {
        $excluded_params = [
            "route",
            "_route_",
            "product_id",
            "path",
            "manufacturer_id",
            "information_id",
        ];
        $remaining_query = $this->buildRemainingQueryString($excluded_params);
        $redirect_url = $this->buildRedirectUrl(
            "/" . $canonical_keyword . $remaining_query,
        );

        $this->response->redirect($redirect_url, 301);
        exit();
    }

    /**
     * Enforce clean SEO URLs - redirect from old format (index.php?route=...) to clean URL
     * if a SEO URL alias exists
     */
    private function enforceCleanSeoUrls()
    {
        // Only process direct index.php requests with query string
        if (
            !isset($this->request->get["route"]) ||
            isset($this->request->get["_route_"])
        ) {
            return;
        }

        $route = $this->request->get["route"];

        // Don't redirect for admin/install
        if (strpos($route, "admin") === 0 || strpos($route, "install") === 0) {
            return;
        }

        // Build query parameter for SEO URL lookup
        $query_param = "";
        $is_blog_route = false;

        // Ensure blog identifier variables are always defined to satisfy static analysis
        $post_id = 0;
        $cat_id = 0;
        $author_id = 0;

        if (
            $route === "product/product" &&
            isset($this->request->get["product_id"])
        ) {
            $query_param =
                "product_id=" . (int) $this->request->get["product_id"];
        } elseif (
            $route === "product/category" &&
            isset($this->request->get["path"])
        ) {
            $path_parts = explode("_", $this->request->get["path"]);
            $category_id = (int) end($path_parts);
            $query_param = "category_id=" . $category_id;
        } elseif (
            $route === "product/manufacturer/info" &&
            isset($this->request->get["manufacturer_id"])
        ) {
            $query_param =
                "manufacturer_id=" .
                (int) $this->request->get["manufacturer_id"];
        } elseif (
            $route === "information/information" &&
            isset($this->request->get["information_id"])
        ) {
            $query_param =
                "information_id=" . (int) $this->request->get["information_id"];
        } elseif ($route === "blog/post") {
            $post_id = isset($this->request->get["blog_post_id"])
                ? (int) $this->request->get["blog_post_id"]
                : (isset($this->request->get["post_id"])
                    ? (int) $this->request->get["post_id"]
                    : 0);
            if ($post_id) {
                $query_param = "blog_post_id=" . $post_id;
                $is_blog_route = true;
            }
        } elseif ($route === "blog/category") {
            $cat_id = isset($this->request->get["blog_category_id"])
                ? (int) $this->request->get["blog_category_id"]
                : (isset($this->request->get["category_id"])
                    ? (int) $this->request->get["category_id"]
                    : 0);
            if ($cat_id) {
                $query_param = "blog_category_id=" . $cat_id;
                $is_blog_route = true;
            }
        } elseif ($route === "blog/author") {
            $author_id = isset($this->request->get["blog_author_id"])
                ? (int) $this->request->get["blog_author_id"]
                : (isset($this->request->get["author_id"])
                    ? (int) $this->request->get["author_id"]
                    : 0);
            if ($author_id) {
                $query_param = "blog_author_id=" . $author_id;
                $is_blog_route = true;
            }
        } else {
            // Check if the route itself has a SEO URL (for controllers without entity IDs)
            // Examples: checkout/cart, information/contact, common/home, etc.
            $query_param = $route;
        }

        // If we have a query parameter, check if SEO URL alias exists
        if ($query_param) {
            // For blog routes check blog_seo_url table; for everything else use seo_url table
            $seo_keyword = $is_blog_route
                ? $this->getBlogSeoKeyword($query_param)
                : $this->getSeoKeyword($query_param);

            // Redirect if SEO URL exists in database
            if ($seo_keyword) {
                $this->performEnforceCleanUrlRedirect($seo_keyword);
            } elseif ($route === "blog/post" && $post_id) {
                // Fallback redirect to generated blog post URL
                $redirect_url = $this->buildRedirectUrl(
                    "/" .
                        $this->calculateLanguagePrefix() .
                        "blog/post-" .
                        $post_id,
                );
                $this->response->redirect($redirect_url, 301);
                exit();
            } elseif ($route === "blog/category" && $cat_id) {
                // Fallback redirect to generated blog category URL
                $redirect_url = $this->buildRedirectUrl(
                    "/" .
                        $this->calculateLanguagePrefix() .
                        "blog/category-" .
                        $cat_id,
                );
                $this->response->redirect($redirect_url, 301);
                exit();
            } elseif ($route === "blog/author" && $author_id) {
                // Fallback redirect to generated blog author URL
                $redirect_url = $this->buildRedirectUrl(
                    "/" .
                        $this->calculateLanguagePrefix() .
                        "blog/author-" .
                        $author_id,
                );
                $this->response->redirect($redirect_url, 301);
                exit();
            } elseif ($query_param === $route) {
                $fallback_keyword = $this->generateFallbackKeywordForRequestRoute(
                    $route,
                );
                if ($fallback_keyword) {
                    $this->performEnforceCleanUrlRedirect($fallback_keyword);
                }

                // query_param is a route (not an entity ID)
                // Try to generate SEO URL on the fly and redirect
                // Examples: product/special, account/login, etc.

                // Only generate if it's a valid route format
                if ($this->isValidGeneratedRoute($route)) {
                    $generated_keyword = $this->generateSeoUrlFromRoute($route);

                    if ($generated_keyword) {
                        // Check for conflicts with existing DB entries
                        $has_conflict = $this->hasConflictingSeoPrefixInDatabase(
                            $generated_keyword,
                            $route,
                        );

                        if (!$has_conflict) {
                            // Safe to redirect to generated URL
                            $this->performEnforceCleanUrlRedirect(
                                $generated_keyword,
                            );
                        }
                    }
                }
            } else {
                $fallback_keyword = $this->generateFallbackKeywordForRequestRoute(
                    $route,
                );
                if ($fallback_keyword) {
                    $this->performEnforceCleanUrlRedirect($fallback_keyword);
                }
            }
        }
    }

    /**
     * Perform redirect for enforcing clean SEO URLs
     * Helper method to avoid code duplication in enforceCleanSeoUrls()
     *
     * @param string $seo_keyword The SEO keyword to redirect to
     */
    private function performEnforceCleanUrlRedirect($seo_keyword)
    {
        // Get remaining query parameters (everything except route and main identifier)
        $excluded_params = [
            "route",
            "product_id",
            "path",
            "manufacturer_id",
            "information_id",
            "module_id",
            "blog_post_id",
            "post_id",
            "blog_category_id",
            "category_id",
            "blog_author_id",
            "author_id",
        ];
        $remaining_query = $this->buildRemainingQueryString($excluded_params);

        // Build and perform redirect with language prefix
        $redirect_url = $this->buildRedirectUrl(
            "/" .
                $this->calculateLanguagePrefix() .
                $seo_keyword .
                $remaining_query,
        );

        $this->response->redirect($redirect_url, 301);

        exit();
    }

    /**
     * Decode SEO URL from _route_ parameter
     * Processes both GET and POST requests to set the correct route
     * Handles: product keywords, category paths, and auto-generated route URLs
     * This must be done before language detection and before early returns for POST/AJAX
     */
    private function decodeSeoUrl()
    {
        // Only process if we have a _route_ parameter
        if (
            !isset($this->request->get["_route_"]) ||
            empty($this->request->get["_route_"])
        ) {
            return;
        }

        $route_path = $this->request->get["_route_"];
        $parts = explode("/", $route_path);

        // Remove empty arrays from trailing/leading slashes
        $parts = array_filter($parts, function ($part) {
            return $part !== "";
        });

        // If all parts were empty, it's a 404
        if (empty($parts)) {
            $this->request->get["route"] = "error/not_found";
            return;
        }

        // First check if the entire route_path (single segment or multi-segment)
        // is a generated SEO URL like "account-return-add" or a DB entry
        if (count($parts) === 1) {
            $single_part = $parts[0];
            $this->decodeSingleSegmentUrl($single_part);
            return;
        }

        // Intercept blog URL fallback patterns BEFORE generic multi-segment handler.
        // Handles: blog/post-123, blog/category-123, blog/author-123
        $parts_indexed = array_values($parts);
        if (count($parts_indexed) === 2 && $parts_indexed[0] === "blog") {
            if ($this->decodeBlogPattern($parts_indexed[1])) {
                return;
            }
        }

        // Multi-segment handling for category paths
        $this->decodeMultiSegmentUrl($parts);
    }

    /**
     * Decode single-segment SEO URLs
     * Handles: product keywords, information keywords, and generated route URLs
     * Includes protection against ambiguous SEO URLs
     */
    private function decodeSingleSegmentUrl($keyword)
    {
        // Custom mappings
        if ($keyword === "product-categories") {
            $this->request->get["route"] = "product/categories";
            return;
        }

        // Handle bare 'blog' segment → main blog listing page
        if ($keyword === "blog") {
            $this->request->get["route"] = "blog/category";
            return;
        }

        $resolved_query = $this->getSeoQueryByKeyword($keyword);

        if ($resolved_query === "") {
            $resolved_query = $this->getBlogSeoQueryByKeyword($keyword);
        }

        if ($resolved_query !== "") {
            $url = explode("=", $resolved_query, 2);
            $query_value = isset($url[1]) ? $url[1] : "";

            if ($url[0] == "product_id") {
                $this->request->get["product_id"] = $query_value;
                $this->request->get["route"] = "product/product";
            } elseif ($url[0] == "category_id") {
                $category_id = (int) $query_value;

                // Check if this is a nested category (has parent_id)
                // If so, redirect to canonical URL with full path
                $this->handleNestedCategoryRedirect($category_id, $keyword);

                $this->request->get["path"] = $category_id;
                $this->request->get["route"] = "product/category";
            } elseif ($url[0] == "manufacturer_id") {
                $this->request->get["manufacturer_id"] = $query_value;
                $this->request->get["route"] = "product/manufacturer/info";
            } elseif ($url[0] == "information_id") {
                $this->request->get["information_id"] = $query_value;
                $this->request->get["route"] = "information/information";
            } elseif ($url[0] == "blog_post_id") {
                // Blog post
                $this->request->get["blog_post_id"] = $query_value;
                $this->request->get["route"] = "blog/post";
            } elseif ($url[0] == "blog_category_id") {
                // Blog category
                $this->request->get["blog_category_id"] = $query_value;
                $this->request->get["route"] = "blog/category";
            } elseif ($url[0] == "blog_author_id") {
                // Blog author
                $this->request->get["blog_author_id"] = $query_value;
                $this->request->get["route"] = "blog/author";
            } else {
                // This is a route query like account/login or checkout/cart
                $this->request->get["route"] = $resolved_query;
            }
            return;
        }

        // Not found in DB - check fallback short aliases (cat/prod/inf/man/mod)
        if ($this->decodeFallbackEntityKeyword($keyword)) {
            return;
        }

        // Not found in DB - check if it's a generated SEO URL (format: account-return-add)
        // These are auto-generated from routes that contain dashes
        if (strpos($keyword, "-") !== false) {
            $potential_route = str_replace("-", "/", $keyword);

            // Verify it's a valid generated route (not products/categories/etc)
            if ($this->isValidGeneratedRoute($potential_route)) {
                // PROTECTION: Check if using this generated URL would conflict with existing DB entry
                // Example: if keyword='checkout-cart' wants to map to route='checkout/cart',
                // but in DB there's already keyword='checkout-cart' for product_id=5,
                // we should NOT use this generated URL (it's already taken)
                if ($this->isKeywordUsedForNonRouteEntity($keyword)) {
                    // This keyword is already used for a product/category/etc
                    // Don't treat it as a generated route, treat as 404 instead
                    $this->request->get["route"] = "error/not_found";
                    return;
                }

                // Before generating, check if there's a shorter SEO URL in DB for this route
                // For example: if checkout-cart maps to checkout/cart, but cart already exists in DB for checkout/cart
                $shorter_seo_keyword = $this->getSeoKeyword($potential_route);

                if ($shorter_seo_keyword) {
                    // There's a shorter version in DB, so this generated long URL should be 404
                    // e.g., /cart exists in DB, so /checkout-cart is wrong and should be 404
                    $this->request->get["route"] = "error/not_found";
                    return;
                }

                // No shorter version found in DB, so this generated URL is valid
                $this->request->get["route"] = $potential_route;
                return;
            }
        }

        // Not found anywhere - 404
        $this->request->get["route"] = "error/not_found";
    }

    /**
     * Decode multi-segment SEO URLs
     * Primarily handles category paths where each segment is a category keyword
     */
    private function decodeMultiSegmentUrl($parts)
    {
        $detected_types = [];
        $matched = [];

        // Process each segment of the URL
        foreach ($parts as $part) {
            $resolved_query = $this->getSeoQueryByKeyword($part);

            if ($resolved_query !== "") {
                $matched[] = ["keyword" => $part, "query" => $resolved_query];
                $url = explode("=", $resolved_query, 2);
                $query_value = isset($url[1]) ? $url[1] : "";

                if ($url[0] == "product_id") {
                    $this->request->get["product_id"] = $query_value;
                    $detected_types[] = "product";
                } elseif ($url[0] == "category_id") {
                    if (!isset($this->request->get["path"])) {
                        $this->request->get["path"] = $query_value;
                    } else {
                        $this->request->get["path"] .= "_" . $query_value;
                    }
                    $detected_types[] = "category";
                } elseif ($url[0] == "manufacturer_id") {
                    $this->request->get["manufacturer_id"] = $query_value;
                    $detected_types[] = "manufacturer";
                } elseif ($url[0] == "information_id") {
                    $this->request->get["information_id"] = $query_value;
                    $detected_types[] = "information";
                } else {
                    // This is a route
                    $this->request->get["route"] = $resolved_query;
                    $detected_types[] = "route";
                }
            } else {
                // Part not found in DB - invalid SEO URL segment
                $this->request->get["route"] = "error/not_found";
                return;
            }
        }

        // Handle multi-segment URLs with canonical redirect (only for GET requests)
        if ($this->isGetRequest && count($parts) > 1) {
            $unique_types = array_values(array_unique($detected_types));
            $has_product = isset($this->request->get["product_id"]);
            $has_manufacturer = isset($this->request->get["manufacturer_id"]);
            $has_information = isset($this->request->get["information_id"]);
            $is_all_categories =
                count($unique_types) === 1 && $unique_types[0] === "category";

            // If product/manufacturer/information or invalid mix found, redirect to canonical (only for GET)
            if (
                $has_product ||
                $has_manufacturer ||
                $has_information ||
                !$is_all_categories
            ) {
                // Find canonical keyword (prefer product > manufacturer > information > category > first match)
                $canonical_keyword = null;
                $priority_order = [
                    "product_id=",
                    "manufacturer_id=",
                    "information_id=",
                    "category_id=",
                ];

                foreach ($priority_order as $query_prefix) {
                    foreach ($matched as $m) {
                        if (strpos($m["query"], $query_prefix) === 0) {
                            $canonical_keyword = $m["keyword"];
                            break 2;
                        }
                    }
                }

                // Fallback to first matched
                if (!$canonical_keyword && !empty($matched)) {
                    $canonical_keyword = $matched[0]["keyword"];
                }

                if ($canonical_keyword) {
                    $this->performCanonicalRedirect($canonical_keyword);
                } elseif (!$is_all_categories) {
                    $this->request->get["route"] = "error/not_found";
                }
            }
        }

        // Set route based on detected entities if not already set
        if (!isset($this->request->get["route"])) {
            if (isset($this->request->get["product_id"])) {
                $this->request->get["route"] = "product/product";

                // Get primary category for product
                if (!isset($this->request->get["path"])) {
                    $product_categories = $this->db->query(
                        "SELECT category_id FROM " .
                            DB_PREFIX .
                            "product_to_category
						WHERE product_id = '" .
                            (int) $this->request->get["product_id"] .
                            "'
						LIMIT 1",
                    );

                    if ($product_categories->num_rows) {
                        $category_id = $product_categories->row["category_id"];

                        // Get full category path from root using category_path table
                        $category_path = $this->db->query(
                            "SELECT path_id FROM " .
                                DB_PREFIX .
                                "category_path
							WHERE category_id = '" .
                                (int) $category_id .
                                "'
							ORDER BY level ASC",
                        );

                        if ($category_path->num_rows) {
                            $path_ids = [];
                            foreach ($category_path->rows as $path_row) {
                                $path_ids[] = $path_row["path_id"];
                            }
                            $this->request->get["path"] = implode(
                                "_",
                                $path_ids,
                            );
                        }
                    }
                }
            } elseif (isset($this->request->get["path"])) {
                $this->request->get["route"] = "product/category";
            } elseif (isset($this->request->get["manufacturer_id"])) {
                $this->request->get["route"] = "product/manufacturer/info";
            } elseif (isset($this->request->get["information_id"])) {
                $this->request->get["route"] = "information/information";
            } else {
                // If we have parts but nothing was found, it's a 404
                $this->request->get["route"] = "error/not_found";
            }
        }
    }

    /**
     * Check if a keyword is already used for a non-route entity (product, category, etc)
     *
     * Protection mechanism: When decoding a URL that looks like a generated route keyword,
     * we need to ensure it hasn't been explicitly used for a different type of entity.
     *
     * Example: If keyword='checkout-cart' exists in DB for product_id=5,
     * we should NOT treat it as a generated route for 'checkout/cart'
     *
     * @param string $keyword The SEO URL keyword to check
     * @return bool True if keyword is used for product/category/etc, False if safe to treat as generated route
     */
    private function isKeywordUsedForNonRouteEntity($keyword)
    {
        $query_string = $this->getSeoQueryByKeyword($keyword);

        // If no keyword found at all, it's safe
        if ($query_string === "") {
            return false;
        }

        // Check if it's a non-route entity (product_id, category_id, etc)
        // These have format: "product_id=123" or "category_id=456", etc
        if (
            preg_match(
                "/^(product_id|category_id|manufacturer_id|information_id|module_id)=/",
                $query_string,
            )
        ) {
            // This keyword is already assigned to a product/category/manufacturer/information
            // So it's NOT available for use as a generated route keyword
            return true;
        }

        // If it's a route query (like "account/login" or "checkout/cart"),
        // it's not a "non-route entity", so return false
        return false;
    }

    /**
     * Check if a potential route string is a valid generated route
     * Valid routes are patterns like: account/login, account/return/add, checkout/cart
     * Invalid routes would be: product_id/123, category_id/456, etc
     */
    private function isValidGeneratedRoute($potential_route)
    {
        // Skip routes that look like DB queries or contain numbers followed by IDs
        if (
            preg_match(
                "/^(product_id|category_id|manufacturer_id|information_id|path)/",
                $potential_route,
            )
        ) {
            return false;
        }

        // Skip admin, api, install routes
        if (
            strpos($potential_route, "admin") === 0 ||
            strpos($potential_route, "api") === 0 ||
            strpos($potential_route, "install") === 0
        ) {
            return false;
        }

        // Skip home route
        if ($potential_route === "common/home") {
            return false;
        }

        // Valid format should be: module/controller or module/controller/action
        $parts = explode("/", $potential_route);
        if (count($parts) < 2 || count($parts) > 3) {
            return false;
        }

        // Each part should be alphanumeric with underscores
        foreach ($parts as $part) {
            if (!preg_match('/^[a-z0-9_]+$/', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Decode short fallback aliases generated when explicit SEO keyword is missing.
     * If explicit SEO keyword exists, redirect fallback alias to canonical SEO URL.
     * Supported patterns:
     * - cat123  => product/category, path=123
     * - prod123 => product/product, product_id=123
     * - man123  => product/manufacturer/info, manufacturer_id=123
     * - inf123  => information/information, information_id=123
     * - mod123  => extension/module/{code}, module_id=123
     */
    private function decodeFallbackEntityKeyword($keyword)
    {
        if (preg_match('/^cat(\d+)$/', $keyword, $matches)) {
            $category_id = (int) $matches[1];
            $seo_keyword = $this->getSeoKeyword("category_id=" . $category_id);

            if ($seo_keyword) {
                $this->performCanonicalRedirect($seo_keyword);
            }

            $this->handleNestedCategoryRedirect($category_id, $keyword);
            $this->request->get["path"] = $category_id;
            $this->request->get["route"] = "product/category";
            return true;
        }

        if (preg_match('/^prod(\d+)$/', $keyword, $matches)) {
            $product_id = (int) $matches[1];
            $seo_keyword = $this->getSeoKeyword("product_id=" . $product_id);

            if ($seo_keyword) {
                $this->performCanonicalRedirect($seo_keyword);
            }

            $this->request->get["product_id"] = $product_id;
            $this->request->get["route"] = "product/product";
            return true;
        }

        if (preg_match('/^man(\d+)$/', $keyword, $matches)) {
            $manufacturer_id = (int) $matches[1];
            $seo_keyword = $this->getSeoKeyword(
                "manufacturer_id=" . $manufacturer_id,
            );

            if ($seo_keyword) {
                $this->performCanonicalRedirect($seo_keyword);
            }

            $this->request->get["manufacturer_id"] = $manufacturer_id;
            $this->request->get["route"] = "product/manufacturer/info";
            return true;
        }

        if (preg_match('/^inf(\d+)$/', $keyword, $matches)) {
            $information_id = (int) $matches[1];
            $seo_keyword = $this->getSeoKeyword(
                "information_id=" . $information_id,
            );

            if ($seo_keyword) {
                $this->performCanonicalRedirect($seo_keyword);
            }

            $this->request->get["information_id"] = $information_id;
            $this->request->get["route"] = "information/information";
            return true;
        }

        if (preg_match('/^mod(\d+)$/', $keyword, $matches)) {
            $module_id = (int) $matches[1];
            $module_route = $this->resolveModuleRouteById($module_id);

            if (!$module_route) {
                return false;
            }

            if (
                $this->getSeoKeyword("module_id=" . $module_id) ||
                $this->getSeoKeyword($module_route)
            ) {
                $this->request->get["route"] = "error/not_found";
                return true;
            }

            $this->request->get["module_id"] = $module_id;
            $this->request->get["route"] = $module_route;
            return true;
        }

        return false;
    }

    /**
     * Generate fallback keyword for direct index.php?route=... requests when
     * explicit SEO keyword is not configured.
     */
    private function generateFallbackKeywordForRequestRoute($route)
    {
        if (
            $route === "product/product" &&
            isset($this->request->get["product_id"])
        ) {
            $product_id = (int) $this->request->get["product_id"];
            return $this->buildEntityFallbackKeyword(
                "prod",
                $product_id,
                "product_id=" . $product_id,
                $route,
            );
        }

        if (
            $route === "product/category" &&
            isset($this->request->get["path"])
        ) {
            $parts = explode("_", (string) $this->request->get["path"]);
            $category_id = (int) end($parts);
            return $this->buildEntityFallbackKeyword(
                "cat",
                $category_id,
                "category_id=" . $category_id,
                $route,
            );
        }

        if (
            $route === "product/manufacturer/info" &&
            isset($this->request->get["manufacturer_id"])
        ) {
            $manufacturer_id = (int) $this->request->get["manufacturer_id"];
            return $this->buildEntityFallbackKeyword(
                "man",
                $manufacturer_id,
                "manufacturer_id=" . $manufacturer_id,
                $route,
            );
        }

        if (
            $route === "information/information" &&
            isset($this->request->get["information_id"])
        ) {
            $information_id = (int) $this->request->get["information_id"];
            return $this->buildEntityFallbackKeyword(
                "inf",
                $information_id,
                "information_id=" . $information_id,
                $route,
            );
        }

        if (
            $this->isModuleRoute($route) &&
            isset($this->request->get["module_id"])
        ) {
            $module_id = (int) $this->request->get["module_id"];
            $module_keyword = $this->getSeoKeyword("module_id=" . $module_id);

            if ($module_keyword) {
                return $module_keyword;
            }

            return $this->buildEntityFallbackKeyword(
                "mod",
                $module_id,
                "module_id=" . $module_id,
                $route,
            );
        }

        return "";
    }

    /**
     * Build redirect URL with protocol, host and path
     * Helper method to avoid code duplication
     * @param string $path Path with query string (e.g., '/product?sort=name')
     * @return string Full redirect URL
     */
    private function buildRedirectUrl($path)
    {
        $protocol =
            !empty($this->request->server["HTTPS"]) &&
            $this->request->server["HTTPS"] !== "off"
                ? "https://"
                : "http://";
        $host = isset($this->request->server["HTTP_HOST"])
            ? $this->request->server["HTTP_HOST"]
            : $_SERVER["HTTP_HOST"];

        // Append port if using non-standard port and it's not already in HTTP_HOST
        $port = "";
        if (isset($this->request->server["SERVER_PORT"])) {
            $is_https = $protocol === "https://";
            $default_port = $is_https ? "443" : "80";
            $server_port = $this->request->server["SERVER_PORT"];

            if (
                $server_port !== $default_port &&
                strpos($host, ":" . $server_port) === false
            ) {
                $port = ":" . $server_port;
            }
        }

        return $protocol . $host . $port . $path;
    }

    /**
     * Build remaining query string from GET parameters, excluding specified params
     * Helper method to avoid code duplication
     * @param array $excluded_params Parameters to exclude from query string
     * @return string Query string (with leading '?' if not empty, otherwise empty string)
     */
    private function buildRemainingQueryString($excluded_params)
    {
        $remaining_query = "";

        foreach ($this->request->get as $key => $value) {
            if (!in_array($key, $excluded_params)) {
                $remaining_query .=
                    "&" . urlencode($key) . "=" . urlencode($value);
            }
        }

        if ($remaining_query) {
            $remaining_query = "?" . ltrim($remaining_query, "&");
        }

        return $remaining_query;
    }

    /**
     * Calculate language prefix for URL generation
     * Returns empty string for default language, language code for others
     * Called dynamically to support language switching
     */
    private function calculateLanguagePrefix()
    {
        $default_language = $this->config->get("config_language");
        $current_language = isset($this->session->data["language"])
            ? $this->session->data["language"]
            : $default_language;

        // No prefix for default language
        if ($current_language === $default_language) {
            return "";
        }

        return $current_language . "/";
    }

    /**
     * Detect language from URL prefix
     * URL format: /uk-ua/page or /en-gb/page
     * Sets language in session when detected in URL
     */
    private function detectAndSetLanguageFromUrl()
    {
        if (!isset($this->request->get["_route_"])) {
            return;
        }

        $route = $this->request->get["_route_"];
        $parts = explode("/", trim($route, "/"));

        if (empty($parts[0])) {
            return;
        }

        // Check if first part is a language code
        $potential_lang = $parts[0];

        // Get all available languages
        $this->load->model("localisation/language");
        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            if ($language["code"] === $potential_lang && $language["status"]) {
                // Language found in URL
                // Remove language prefix from _route_ for further processing
                array_shift($parts);
                $this->request->get["_route_"] = implode("/", $parts);

                // Set language in session (will be saved by OpenCart automatically)
                $this->session->data["language"] = $language["code"];

                // Update languageId property for all subsequent operations
                // This ensures all DB queries use the correct language
                $this->languageId = (int) $language["language_id"];

                break;
            }
        }
    }

    /**
     * Handle nested category redirect
     * If a category has a parent, redirect to the canonical URL with full breadcrumb path
     * Prevents SEO duplicate content issue (e.g., /mac vs /desktops/mac)
     */
    private function handleNestedCategoryRedirect(
        $category_id,
        $current_keyword,
    ) {
        // Only for GET requests
        if (!$this->isGetRequest) {
            return;
        }

        // Check if category has a parent
        $category_query = $this->db->query(
            "SELECT parent_id FROM " .
                DB_PREFIX .
                "category
			WHERE category_id = '" .
                (int) $category_id .
                "'
			LIMIT 1",
        );

        if (!$category_query->num_rows) {
            return; // Category not found
        }

        $parent_id = (int) $category_query->row["parent_id"];

        if ($parent_id === 0) {
            // This is a top-level category, no redirect needed
            return;
        }

        // This is a nested category - we need to build its full breadcrumb path
        // and redirect to the canonical URL
        $canonical_path = $this->getCategoryBreadcrumbPath($category_id);

        if ($canonical_path) {
            // Build redirect URL with language prefix
            $language_prefix = $this->calculateLanguagePrefix();
            $redirect_path =
                "/" . trim($language_prefix . "/" . $canonical_path, "/");

            // Preserve query parameters (excluding _route_)
            $query_string = "";
            foreach ($this->request->get as $key => $value) {
                if ($key !== "_route_" && $key !== "path") {
                    $query_string .=
                        ($query_string ? "&" : "?") .
                        urlencode($key) .
                        "=" .
                        urlencode($value);
                }
            }

            $redirect_url = $this->buildRedirectUrl(
                $redirect_path . $query_string,
            );
            $this->response->redirect($redirect_url, 301);
            exit();
        }
    }

    /**
     * Get full breadcrumb path for a category
     * Returns path like: desktops/mac (parent/child)
     */
    private function getCategoryBreadcrumbPath($category_id)
    {
        $breadcrumb = [];
        $current_id = (int) $category_id;

        // Build path from child to parent
        while ($current_id > 0) {
            $category_keyword = $this->getSeoKeyword(
                "category_id=" . (int) $current_id,
            );

            if (!$category_keyword) {
                return null; // SEO keyword not found
            }

            array_unshift($breadcrumb, $category_keyword);

            // Get parent category ID
            $parent_query = $this->db->query(
                "SELECT parent_id FROM " .
                    DB_PREFIX .
                    "category
				WHERE category_id = '" .
                    (int) $current_id .
                    "'
				LIMIT 1",
            );

            if (!$parent_query->num_rows) {
                return null; // Category not found
            }

            $current_id = (int) $parent_query->row["parent_id"];
        }

        return implode("/", $breadcrumb);
    }

    /**
     * Decode blog URL patterns
     * Handles patterns like 'blog', 'post-123', 'category-456', 'author-789'
     *
     * @param string $part URL segment to check
     * @return bool True if pattern matched and decoded
     */
    private function decodeBlogPattern($part)
    {
        // Special case: 'blog' segment alone
        if ($part === "blog") {
            $this->request->get["route"] = "blog/category";
            return true;
        }

        // Check for pattern: type-id (e.g., 'post-123', 'category-456')
        if (preg_match('/^(post|category|author)-(\d+)$/', $part, $matches)) {
            $type = $matches[1];
            $id = (int) $matches[2];

            if ($type === "post") {
                $this->request->get["blog_post_id"] = $id;
                $this->request->get["route"] = "blog/post";
                return true;
            } elseif ($type === "category") {
                $this->request->get["blog_category_id"] = $id;
                $this->request->get["route"] = "blog/category";
                return true;
            } elseif ($type === "author") {
                $this->request->get["blog_author_id"] = $id;
                $this->request->get["route"] = "blog/author";
                return true;
            }
        }

        return false;
    }
}
