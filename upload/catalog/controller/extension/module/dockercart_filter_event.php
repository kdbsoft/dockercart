<?php
/**
 * DockerCart Filter — Event handlers
 *
 * Event-based integrations used to modify SEO, titles and product queries
 * when filter parameters are applied.
 *
 * License: Commercial — All rights reserved.
 * Copyright (c) mathflow-bit
 *
 * This module is distributed under a commercial/proprietary license.
 * Use, copying, modification, and distribution are permitted only under
 * the terms of a valid commercial license agreement with the copyright owner.
 *
 * For licensing inquiries contact: licensing@mathflow-bit.example
 */

class ControllerExtensionModuleDockercartFilterEvent extends Controller {
     private $logger;

    /**
     * Constructor - Initialize logger
     */
    public function __construct($registry) {
        parent::__construct($registry);
        
        // Initialize centralized logger
        require_once DIR_SYSTEM . 'library/dockercart_logger.php';
        $this->logger = new DockercartLogger($this->registry, 'filter');
    }


    public function modifyMetaTitle(&$route, &$args) {
        $this->logger->debug('modifyMetaTitle: CALLED');


        if (!isset($this->request->get['dcf'])) {
            $this->logger->debug('modifyMetaTitle: No dcf parameter, returning');
            return;
        }

        $category_id = 0;


        if (isset($this->request->get['path'])) {
            $path = explode('_', $this->request->get['path']);
            $category_id = (int)end($path);
            $this->logger->debug('modifyMetaTitle: Found category_id from path: ' . $category_id);
        }

        if (!$category_id) {
            $this->logger->debug('modifyMetaTitle: No category_id found, returning');
            return;
        }

        $this->logger->debug('modifyMetaTitle: Building dynamic title for category ' . $category_id);

        $this->load->model('extension/module/dockercart_filter');
        $this->load->model('catalog/category');


        $dcf = $this->request->get['dcf'];
        $json = @hex2bin($dcf);
        $decoded = @json_decode($json, true);

        if (!is_array($decoded)) {
            return;
        }

        $filter_manufacturer = isset($decoded['manufacturers']) ? $decoded['manufacturers'] : [];
        $filter_attribute = isset($decoded['attributes']) ? $decoded['attributes'] : [];
        $filter_option = isset($decoded['options']) ? $decoded['options'] : [];

        $filter_data = array('filter_category_id' => $category_id);
        $manufacturers_data = $this->model_extension_module_dockercart_filter->getManufacturers($filter_data);
        $attributes_data = $this->model_extension_module_dockercart_filter->getAttributes($filter_data);
        $options_data = $this->model_extension_module_dockercart_filter->getOptions($filter_data);

        $category_info = $this->model_catalog_category->getCategory($category_id);
        if (!$category_info) {
            $this->logger->debug('modifyMetaTitle: Category not found');
            return;
        }

        $heading_parts = array($category_info['name']);


        if (!empty($filter_manufacturer)) {
            $man_names = array();
            foreach ($manufacturers_data as $m) {
                if (in_array($m['manufacturer_id'], $filter_manufacturer)) {
                    $man_names[] = $m['name'];
                }
            }
            if (!empty($man_names)) {
                $heading_parts[] = implode(', ', $man_names);
            }
        }


        if (!empty($filter_attribute)) {
            $attr_parts = array();
            foreach ($attributes_data as $a) {
                if (isset($filter_attribute[$a['attribute_id']])) {
                    $values = $filter_attribute[$a['attribute_id']];
                    $attr_parts[] = $a['name'] . ': ' . implode(', ', $values);
                }
            }
            if (!empty($attr_parts)) {
                $heading_parts[] = implode(' | ', $attr_parts);
            }
        }


        if (!empty($filter_option)) {
            $option_parts = array();
            foreach ($options_data as $o) {
                if (isset($filter_option[$o['option_id']])) {
                    $value_ids = $filter_option[$o['option_id']];
                    $value_names = array();
                    foreach ($value_ids as $vid) {
                        foreach ($o['values'] as $ov) {
                            if ($ov['option_value_id'] == $vid) {
                                $value_names[] = $ov['name'];
                                break;
                            }
                        }
                    }
                    if (!empty($value_names)) {
                        $option_parts[] = $o['name'] . ': ' . implode(', ', $value_names);
                    }
                }
            }
            if (!empty($option_parts)) {
                $heading_parts[] = implode(' | ', $option_parts);
            }
        }

        $new_title = implode(' - ', $heading_parts);
        $this->document->setTitle($new_title);

        $this->logger->debug('modifyMetaTitle: Set title to: ' . $new_title);
    }



    public function replaceProductModel(&$route, &$args)
    {
        $this->logger->debug('replaceProductModel: CALLED with route=' . $route);


        if (!isset($this->request->get['dcf'])) {
            $this->logger->debug('replaceProductModel: No dcf parameter, skipping');
            return;
        }

        $dcf = $this->request->get['dcf'];
        $json = @hex2bin($dcf);
        $decoded = @json_decode($json, true);

        if (!is_array($decoded)) {
            $this->logger->debug('replaceProductModel: Failed to decode dcf');
            return;
        }

        $this->logger->debug('replaceProductModel: Decoded dcf = ' . json_encode($decoded));

        $filter_data = [];
        $has_filters = false;


        if (isset($decoded['manufacturers']) && !empty($decoded['manufacturers'])) {
            $filter_data['filter_manufacturers'] = array_map('intval', $decoded['manufacturers']);
            $has_filters = true;
            $this->logger->debug('replaceProductModel: Manufacturers = ' . json_encode($filter_data['filter_manufacturers']));
        }


        if (isset($decoded['attributes']) && is_array($decoded['attributes']) && !empty($decoded['attributes'])) {
            $filter_data['filter_attributes'] = [];
            foreach ($decoded['attributes'] as $attr_id => $values) {
                if (!empty($values)) {
                    $filter_data['filter_attributes'][(int)$attr_id] = (array)$values;
                    $has_filters = true;
                }
            }
            $this->logger->debug('replaceProductModel: Attributes = ' . json_encode($filter_data['filter_attributes']));
        }


        if (isset($decoded['options']) && is_array($decoded['options']) && !empty($decoded['options'])) {
            $filter_data['filter_options'] = [];
            foreach ($decoded['options'] as $opt_id => $values) {
                if (!empty($values)) {
                    $filter_data['filter_options'][(int)$opt_id] = array_map('intval', (array)$values);
                    $has_filters = true;
                }
            }
            $this->logger->debug('replaceProductModel: Options = ' . json_encode($filter_data['filter_options']));
        }


        if (isset($decoded['price_min']) && $decoded['price_min'] !== '') {
            $filter_data['filter_price_min'] = (float)$decoded['price_min'];
            $has_filters = true;
        }

        if (isset($decoded['price_max']) && $decoded['price_max'] !== '') {
            $filter_data['filter_price_max'] = (float)$decoded['price_max'];
            $has_filters = true;
        }

        if ($has_filters) {
            $this->logger->debug('replaceProductModel: has_filters=true, filter_data=' . json_encode($filter_data));


            $this->registry->set('dockercart_filter_data', $filter_data);


            $this->load->model('extension/module/dockercart_filter_product');
            $this->registry->set('model_catalog_product', $this->model_extension_module_dockercart_filter_product);

            $this->logger->debug('replaceProductModel: Model replaced successfully');
        } else {
            $this->logger->debug('replaceProductModel: No filters detected');
        }
    }



    public function modifyCategoryHeading(&$route, &$data) {
        $this->logger->debug('modifyCategoryHeading: CALLED');


        if (!isset($this->request->get['dcf'])) {
            $this->logger->debug('modifyCategoryHeading: No dcf parameter, returning');
            return;
        }

        $category_id = 0;
        if (isset($this->request->get['path'])) {
            $path = explode('_', $this->request->get['path']);
            $category_id = (int)end($path);
        }

        if (!$category_id) {
            return;
        }

        $this->load->model('extension/module/dockercart_filter');
        $this->load->model('catalog/category');


        $dcf = $this->request->get['dcf'];
        $json = @hex2bin($dcf);
        $decoded = @json_decode($json, true);

        if (!is_array($decoded)) {
            $this->logger->debug('modifyCategoryHeading: Failed to decode dcf');
            return;
        }

        $this->logger->debug('modifyCategoryHeading: Decoded dcf = ' . json_encode($decoded));

        $filter_manufacturer = isset($decoded['manufacturers']) ? $decoded['manufacturers'] : [];
        $filter_attribute = isset($decoded['attributes']) ? $decoded['attributes'] : [];
        $filter_option = isset($decoded['options']) ? $decoded['options'] : [];

        $filter_data = array('filter_category_id' => $category_id);
        $manufacturers_data = $this->model_extension_module_dockercart_filter->getManufacturers($filter_data);
        $attributes_data = $this->model_extension_module_dockercart_filter->getAttributes($filter_data);
        $options_data = $this->model_extension_module_dockercart_filter->getOptions($filter_data);

        $category_info = $this->model_catalog_category->getCategory($category_id);
        if (!$category_info) {
            return;
        }

        $heading_parts = array($category_info['name']);


        if (!empty($filter_manufacturer)) {
            $man_names = array();

            foreach ($filter_manufacturer as $mfr_id) {
                foreach ($manufacturers_data as $m) {
                    if ($m['manufacturer_id'] == $mfr_id) {
                        $man_names[] = $m['name'];
                        break;
                    }
                }
            }
            if (!empty($man_names)) {
                $heading_parts[] = implode(', ', $man_names);
            }
        }


        if (!empty($filter_attribute)) {
            $attr_parts = array();
            foreach ($attributes_data as $a) {
                if (isset($filter_attribute[$a['attribute_id']])) {
                    $values = $filter_attribute[$a['attribute_id']];

                    $attr_parts[] = $a['name'] . ': ' . implode(', ', $values);
                }
            }
            if (!empty($attr_parts)) {
                $heading_parts[] = implode(' | ', $attr_parts);
            }
        }


        if (!empty($filter_option)) {
            $option_parts = array();
            foreach ($options_data as $o) {
                if (isset($filter_option[$o['option_id']])) {
                    $value_ids = $filter_option[$o['option_id']];
                    $value_names = array();

                    foreach ($value_ids as $vid) {
                        foreach ($o['values'] as $ov) {
                            if ($ov['option_value_id'] == $vid) {
                                $value_names[] = $ov['name'];
                                break;
                            }
                        }
                    }
                    if (!empty($value_names)) {
                        $option_parts[] = $o['name'] . ': ' . implode(', ', $value_names);
                    }
                }
            }
            if (!empty($option_parts)) {
                $heading_parts[] = implode(' | ', $option_parts);
            }
        }

        $new_heading = implode(' - ', $heading_parts);
        $data['heading_title'] = $new_heading;

        $this->logger->debug('modifyCategoryHeading: New heading = ' . $new_heading);
    }



    public function modifyCategoryPageSEO(&$route, &$data, &$output) {
        $this->logger->debug('modifyCategoryPageSEO: CALLED');


        $has_dcf = isset($this->request->get['dcf']);

        if (!$has_dcf) {
            return;
        }


        if ($has_dcf && isset($data['heading_title'])) {
            $new_title = $data['heading_title'];
            $output = preg_replace('/<title>([^<]*)<\/title>/', '<title>' . htmlspecialchars($new_title, ENT_QUOTES, 'UTF-8') . '</title>', $output, 1);
            $this->logger->debug('modifyCategoryPageSEO: Updated meta title to: ' . $new_title);
        }

        if (!$this->config->get('module_dockercart_filter_seo_mode')) {
            return;
        }


        $dcf = $this->request->get['dcf'];
        $json = @hex2bin($dcf);
        $decoded = @json_decode($json, true);

        if (!is_array($decoded)) {
            return;
        }

        $filter_manufacturer = isset($decoded['manufacturers']) ? $decoded['manufacturers'] : [];
        $filter_attribute = isset($decoded['attributes']) ? $decoded['attributes'] : [];
        $filter_option = isset($decoded['options']) ? $decoded['options'] : [];
        $filter_price_min = isset($decoded['price_min']) ? $decoded['price_min'] : '';
        $filter_price_max = isset($decoded['price_max']) ? $decoded['price_max'] : '';

        $should_index = true;
        $seo_reason = 'Unknown filter combination';


        if ($filter_price_min !== '' || $filter_price_max !== '') {
            $should_index = false;
            $seo_reason = 'Price filter - always noindex';
        }
        else {
            $has_mfr = !empty($filter_manufacturer);
            $has_attr = !empty($filter_attribute);
            $has_opt = !empty($filter_option);
            $mfr_count = count($filter_manufacturer);


            if ($has_mfr && !$has_attr && !$has_opt && $mfr_count == 1) {
                $should_index = true;
                $seo_reason = 'Single manufacturer only - INDEX';
            }

            elseif ($has_attr && !$has_mfr && !$has_opt && count($filter_attribute) == 1) {
                $should_index = true;
                $seo_reason = 'Single attribute filter - INDEX';
            }

            elseif ($has_opt && !$has_mfr && !$has_attr && count($filter_option) == 1) {
                $should_index = true;
                $seo_reason = 'Single option filter - INDEX';
            }

            elseif ($has_mfr && $has_attr && !$has_opt && $mfr_count == 1 && count($filter_attribute) == 1) {
                $should_index = true;
                $seo_reason = 'Single manufacturer + single attribute - INDEX';
            }

            elseif ($has_mfr && $has_opt && !$has_attr && $mfr_count == 1 && count($filter_option) == 1) {
                $should_index = true;
                $seo_reason = 'Single manufacturer + single option - INDEX';
            }

            else {
                $should_index = false;
                $seo_reason = 'Multiple or complex filters - noindex';
            }
        }

        $this->logger->debug('modifyCategoryPageSEO: should_index=' . ($should_index ? 'true' : 'false') . ', reason=' . $seo_reason);

        if (!empty($output)) {



            $attr_value_count = 0;
            if (!empty($filter_attribute)) {
                foreach ($filter_attribute as $attr_id => $values) {
                    $attr_value_count += is_array($values) ? count($values) : 1;
                }
            }

            $opt_value_count = 0;
            if (!empty($filter_option)) {
                foreach ($filter_option as $opt_id => $values) {
                    $opt_value_count += is_array($values) ? count($values) : 1;
                }
            }


            if (!($filter_price_min !== '' || $filter_price_max !== '')) {
                $has_mfr = !empty($filter_manufacturer);
                $has_attr = !empty($filter_attribute);
                $has_opt = !empty($filter_option);
                $mfr_count = count($filter_manufacturer);


                if ($has_mfr && !$has_attr && !$has_opt && $mfr_count == 1) {
                    $should_index = true;
                    $seo_reason = 'Single manufacturer only - INDEX';
                }

                elseif ($has_attr && !$has_mfr && !$has_opt && $attr_value_count == 1) {
                    $should_index = true;
                    $seo_reason = 'Single attribute with 1 value - INDEX';
                }

                elseif ($has_opt && !$has_mfr && !$has_attr && $opt_value_count == 1) {
                    $should_index = true;
                    $seo_reason = 'Single option with 1 value - INDEX';
                }




                else {
                    $should_index = false;
                    $seo_reason = 'Multiple or complex filters - noindex';
                }
            }

            $this->logger->debug('modifyCategoryPageSEO: After re-evaluation: should_index=' . ($should_index ? 'true' : 'false') . ', attr_values=' . $attr_value_count . ', opt_values=' . $opt_value_count . ', reason=' . $seo_reason);


            if (!$should_index) {

                $category_id = 0;
                if (isset($this->request->get['path'])) {
                    $path = explode('_', $this->request->get['path']);
                    $category_id = (int)end($path);
                }

                if ($category_id) {
                    $canonical_url = $this->url->link('product/category', 'path=' . $this->request->get['path']);
                } else {
                    $canonical_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') .
                                   $_SERVER['HTTP_HOST'] .
                                   strtok($_SERVER['REQUEST_URI'], '?');
                }

                $this->logger->debug('modifyCategoryPageSEO: noindex page, canonical to category: ' . $canonical_url);
            } else {

                $canonical_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') .
                               $_SERVER['HTTP_HOST'] .
                               $_SERVER['REQUEST_URI'];

                $this->logger->debug('modifyCategoryPageSEO: indexed page, canonical to self: ' . $canonical_url);
            }

            $canonical_link = '<link href="' . htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8') . '" rel="canonical" />';


            $output = preg_replace(
                '/<link[^>]*rel=["\']canonical["\'][^>]*>/i',
                $canonical_link,
                $output
            );

            if (strpos($output, $canonical_link) === false && preg_match('/<head[^>]*>/i', $output)) {
                $output = preg_replace(
                    '/(<head[^>]*>)/i',
                    '$1' . "\n" . $canonical_link,
                    $output,
                    1
                );
            }


            if (!$should_index) {
                if (preg_match('/<head[^>]*>/i', $output)) {
                    if (!preg_match('/<meta\s+name=["\']robots["\'][^>]*content=["\'][^"\']*noindex/i', $output)) {
                        $output = preg_replace(
                            '/(<head[^>]*>)/i',
                            '$1' . "\n" . '<meta name="robots" content="noindex,follow">',
                            $output,
                            1
                        );
                    }
                }
            }
        }
    }



    public function modifySearchPageSEO(&$route, &$data, &$output) {
        $this->modifyCategoryPageSEO($route, $data, $output);
    }

    /**
     * Inject `dcf` parameter into category view HTML (pagination, product links, rel links).
     * Runs on `catalog/view/product/category/after` as a view-time event.
     */
    public function injectDcfInCategoryView(&$route, &$data, &$output) {
        $this->logger->debug('injectDcfInCategoryView: CALLED');

        if (empty($this->request->get['dcf'])) {
            $this->logger->debug('injectDcfInCategoryView: no dcf present, skipping');
            return;
        }

        $dcf = rawurlencode($this->request->get['dcf']);

        // Build expected first-page href for this category (may be SEO-friendly)
        $first_page_href = '';
        if (isset($this->request->get['path'])) {
            $first_page_href = $this->url->link('product/category', 'path=' . $this->request->get['path']);
        }

        // Callback for anchor tags
            // Callback for anchor tags — use parse_url/http_build_query to avoid corrupting SEO URLs
            $anchorCallback = function($m) use ($dcf, $first_page_href) {
                $prefix = $m[1];
                $href = $m[2];
                $suffix = $m[3];

                $low = strtolower($href);
                if ($low === '' || $low[0] === '#' || strpos($low, 'javascript:') === 0 || strpos($low, 'mailto:') === 0 || strpos($low, 'tel:') === 0) {
                    return $m[0];
                }

                // already has dcf
                if (strpos($href, 'dcf=') !== false) {
                    return $m[0];
                }

                // Only operate on "interesting" links or the first-page category URL
                $interesting = array('page=', 'path=', 'product_id=', 'sort=', 'limit=', 'filter=');
                $found = false;
                foreach ($interesting as $pat) {
                    if (strpos($href, $pat) !== false) { $found = true; break; }
                }
                if (!$found && $first_page_href !== '') {
                    $href_trim = rtrim($href, '/');
                    $first_trim = rtrim($first_page_href, '/');
                    if ($href === $first_page_href || $href_trim === $first_trim) {
                        $found = true;
                    }
                }
                if (!$found) {
                    return $m[0];
                }

                // IMPORTANT: Skip adding dcf if href doesn't contain any query params and looks like a filter removal link
                // (i.e., it's pointing to clean category page without filters)
                if (strpos($href, '?') === false && strpos($href, '&') === false) {
                    // This is a clean URL with no query parameters - likely a filter removal link
                    return $m[0];
                }

                // If href is relative (no scheme), preserve original string and append param
                if (strpos($href, '://') === false) {
                    $uses_amp_html = (strpos($href, '&amp;') !== false);
                    $sep = (strpos($href, '?') === false) ? '?' : ($uses_amp_html ? '&amp;' : '&');
                    $newHref = $href . $sep . 'dcf=' . $dcf;
                    return $prefix . $newHref . $suffix;
                }

                // Normalize HTML entities for parsing for absolute URLs
                $uses_amp_html = (strpos($href, '&amp;') !== false);
                $href_for_parse = str_replace('&amp;', '&', $href);

                $parts = parse_url($href_for_parse);

                $query = array();
                if (!empty($parts['query'])) {
                    parse_str($parts['query'], $query);
                }

                // add dcf
                $query['dcf'] = $dcf;

                // rebuild query and URL
                $new_query = http_build_query($query);

                $newHref = '';
                if (isset($parts['scheme'])) {
                    $newHref .= $parts['scheme'] . '://';
                }
                if (isset($parts['host'])) {
                    $newHref .= $parts['host'];
                }
                if (isset($parts['path'])) {
                    $newHref .= $parts['path'];
                }
                if ($new_query !== '') {
                    $newHref .= '?' . $new_query;
                }

                // restore HTML-entity ampersands if original used them
                if ($uses_amp_html) {
                    $newHref = str_replace('&', '&amp;', $newHref);
                }

                return $prefix . $newHref . $suffix;
            };

        // Callback for <link href="..."> (canonical/prev/next)
        $linkCallback = function($m) use ($dcf, $first_page_href) {
            $prefix = $m[1];
            $href = $m[2];
            $suffix = $m[3];

            if (strpos($href, 'dcf=') !== false) {
                return $m[0];
            }

            $interesting = array('page=', 'path=', 'product_id=');
            $found = false;
            foreach ($interesting as $pat) {
                if (strpos($href, $pat) !== false) { $found = true; break; }
            }
            if (!$found && $first_page_href !== '') {
                $href_trim = rtrim($href, '/');
                $first_trim = rtrim($first_page_href, '/');
                if ($href === $first_page_href || $href_trim === $first_trim) {
                    $found = true;
                }
            }
            if (!$found) {
                return $m[0];
            }

            // If href is relative, append param preserving original format
            if (strpos($href, '://') === false) {
                $uses_amp_html = (strpos($href, '&amp;') !== false);
                $sep = (strpos($href, '?') === false) ? '?' : ($uses_amp_html ? '&amp;' : '&');
                $newHref = $href . $sep . 'dcf=' . $dcf;
                return $prefix . $newHref . $suffix;
            }

            $uses_amp_html = (strpos($href, '&amp;') !== false);
            $href_for_parse = str_replace('&amp;', '&', $href);

            $parts = parse_url($href_for_parse);
            $query = array();
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            $query['dcf'] = $dcf;
            $new_query = http_build_query($query);

            $newHref = '';
            if (isset($parts['scheme'])) {
                $newHref .= $parts['scheme'] . '://';
            }
            if (isset($parts['host'])) {
                $newHref .= $parts['host'];
            }
            if (isset($parts['path'])) {
                $newHref .= $parts['path'];
            }
            if ($new_query !== '') {
                $newHref .= '?' . $new_query;
            }
            if ($uses_amp_html) {
                $newHref = str_replace('&', '&amp;', $newHref);
            }

            return $prefix . $newHref . $suffix;
        };

        // Apply to anchors and link tags
        $output = preg_replace_callback('#(<a[^>]+href=[\'\"])([^\'\"]*)([\'\"])#is', $anchorCallback, $output);
        $output = preg_replace_callback('#(<link[^>]+href=[\'\"])([^\'\"]*)([\'\"])#i', $linkCallback, $output);

        $this->logger->debug('injectDcfInCategoryView: modification completed');
    }
}
