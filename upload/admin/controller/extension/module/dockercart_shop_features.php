<?php
class ControllerExtensionModuleDockercartShopFeatures extends Controller {
    private $error = array();

    public function index() {
        $data = $this->load->language('extension/module/dockercart_shop_features');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/module');
        $this->load->model('localisation/language');

        $selected_module_id = isset($this->request->get['module_id']) ? (int)$this->request->get['module_id'] : 0;

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['icon_options'] = $this->getLucideIconOptions();

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $module_data = $this->request->post;
            $module_data['features'] = $this->normalizeFeatures(isset($module_data['features']) ? $module_data['features'] : array(), $data['languages']);

            if ($selected_module_id > 0) {
                $this->model_setting_module->editModule($selected_module_id, $module_data);
                $saved_module_id = $selected_module_id;
            } else {
                $this->model_setting_module->addModule('dockercart_shop_features', $module_data);
                $saved_module_id = (int)$this->db->getLastId();
            }

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $saved_module_id, true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : '';

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'] . ($selected_module_id > 0 ? '&module_id=' . $selected_module_id : ''), true)
        );

        $data['action'] = $this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'] . ($selected_module_id > 0 ? '&module_id=' . $selected_module_id : ''), true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['new_widget'] = $this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'], true);

        if ($selected_module_id > 0 && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
            $module_info = $this->model_setting_module->getModule($selected_module_id);
        } else {
            $module_info = array();
        }

        $defaults = array(
            'name' => $this->language->get('text_default_module_name'),
            'status' => 1
        );

        foreach ($defaults as $key => $default) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } elseif (!empty($module_info) && isset($module_info[$key])) {
                $data[$key] = $module_info[$key];
            } else {
                $data[$key] = $default;
            }
        }

        if (isset($this->request->post['features'])) {
            $data['features'] = $this->normalizeFeatures($this->request->post['features'], $data['languages']);
        } elseif (!empty($module_info) && isset($module_info['features']) && is_array($module_info['features'])) {
            $data['features'] = $this->normalizeFeatures($module_info['features'], $data['languages']);
        } else {
            $data['features'] = $this->getDefaultFeatures($data['languages']);
        }

        $modules = $this->model_setting_module->getModulesByCode('dockercart_shop_features');
        $data['widgets'] = array();

        foreach ($modules as $module) {
            $module_id = isset($module['module_id']) ? (int)$module['module_id'] : 0;

            $data['widgets'][] = array(
                'module_id' => $module_id,
                'name' => !empty($module['name']) ? $module['name'] : $this->language->get('text_default_module_name') . ' #' . $module_id,
                'href' => $this->url->link('extension/module/dockercart_shop_features', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $module_id, true),
                'active' => ($selected_module_id === $module_id)
            );
        }

        $data['current_module_id'] = $selected_module_id;

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/dockercart_shop_features', $data));
    }

    public function install() {
        $this->load->model('user/user_group');

        $group_id = (int)$this->user->getGroupId();
        $this->model_user_user_group->addPermission($group_id, 'access', 'extension/module/dockercart_shop_features');
        $this->model_user_user_group->addPermission($group_id, 'modify', 'extension/module/dockercart_shop_features');
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/dockercart_shop_features')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
            $this->error['name'] = $this->language->get('error_name');
        }

        return !$this->error;
    }

    private function normalizeFeatures($features, $languages) {
        $normalized = array();
        $icon_options = $this->getLucideIconOptions();
        $allowed_icons = array_flip($icon_options);

        if (!is_array($features)) {
            return $this->getDefaultFeatures($languages);
        }

        foreach ($features as $feature) {
            if (!is_array($feature)) {
                continue;
            }

            $icon = isset($feature['icon']) ? trim((string)$feature['icon']) : 'truck';
            if (!isset($allowed_icons[$icon])) {
                $icon = 'truck';
            }

            $sort_order = isset($feature['sort_order']) ? (int)$feature['sort_order'] : 0;
            $item = array(
                'icon' => $icon,
                'sort_order' => $sort_order,
                'title' => array(),
                'text' => array()
            );

            $has_content = false;

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];

                $title = '';
                if (isset($feature['title'][$language_id])) {
                    $title = trim((string)$feature['title'][$language_id]);
                }

                $text = '';
                if (isset($feature['text'][$language_id])) {
                    $text = trim((string)$feature['text'][$language_id]);
                }

                if ($title !== '' || $text !== '') {
                    $has_content = true;
                }

                $item['title'][$language_id] = $title;
                $item['text'][$language_id] = $text;
            }

            if ($has_content) {
                $normalized[] = $item;
            }
        }

        usort($normalized, function($a, $b) {
            return (int)$a['sort_order'] <=> (int)$b['sort_order'];
        });

        if (!$normalized) {
            return $this->getDefaultFeatures($languages);
        }

        return array_values($normalized);
    }

    private function getDefaultFeatures($languages) {
        $defaults = array(
            array('icon' => 'truck', 'title_key' => 'text_default_feature_1_title', 'text_key' => 'text_default_feature_1_text'),
            array('icon' => 'shield-check', 'title_key' => 'text_default_feature_2_title', 'text_key' => 'text_default_feature_2_text'),
            array('icon' => 'refresh-ccw', 'title_key' => 'text_default_feature_3_title', 'text_key' => 'text_default_feature_3_text'),
            array('icon' => 'headset', 'title_key' => 'text_default_feature_4_title', 'text_key' => 'text_default_feature_4_text')
        );

        $features = array();

        foreach ($defaults as $index => $default) {
            $feature = array(
                'icon' => $default['icon'],
                'sort_order' => $index,
                'title' => array(),
                'text' => array()
            );

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $feature['title'][$language_id] = $this->language->get($default['title_key']);
                $feature['text'][$language_id] = $this->language->get($default['text_key']);
            }

            $features[] = $feature;
        }

        return $features;
    }

    private function getLucideIconOptions() {
        return array(
            // E-commerce & Shopping
            'shopping-cart', 'shopping-bag', 'shopping-basket', 'bag', 'barcode',
            'tag', 'ticket', 'percent', 'badge-percent', 'badge-dollar-sign',
            'badge-check', 'badge-info', 'badge-x', 'dollar-sign', 'euro',
            'pound-sterling', 'yen', 'wallet', 'credit-card', 'receipt',
            'receipt-text', 'scale', 'package', 'package-check', 'package-x',
            'gift', 'truck', 'warehouse', 'boxes',

            // Shipping & Transport
            'car', 'car-front', 'bus', 'bus-front', 'train', 'train-front',
            'plane', 'plane-landing', 'plane-takeoff', 'ship', 'bike',
            'fuel', 'map-pin', 'map', 'map-pinned', 'compass', 'navigation',
            'navigation-2', 'route', 'signpost', 'locate', 'locate-fixed',
            'waypoints',

            // Communication & Support
            'headset', 'phone', 'phone-call', 'phone-forwarded',
            'phone-incoming', 'phone-missed', 'phone-off', 'phone-outgoing',
            'mail', 'mail-open', 'mail-check', 'mail-x', 'mail-search',
            'mail-question', 'message-square', 'message-circle',
            'message-square-text', 'message-circle-text', 'chat',
            'chat-text', 'send', 'reply', 'reply-all', 'forward',
            'at-sign', 'contact', 'contact-round',

            // Media & Entertainment
            'camera', 'camera-off', 'video', 'video-off', 'image',
            'image-plus', 'image-minus', 'film', 'clapperboard',
            'music', 'music-3', 'mic', 'mic-2', 'mic-off',
            'radio', 'headphones', 'speaker', 'volume-1', 'volume-2',
            'volume-x', 'monitor-speaker', 'tv', 'monitor', 'tablet',
            'smartphone', 'gamepad-2', 'puzzle',

            // Technology & Devices
            'laptop', 'laptop-2', 'computer', 'hard-drive', 'database',
            'cpu', 'memory-stick', 'printer', 'scanner', 'keyboard',
            'mouse', 'monitor-check', 'monitor-dot', 'monitor-pause',
            'monitor-stop', 'monitor-x', 'watch', 'smartwatch',
            'battery-charging', 'battery-full', 'battery-low',
            'battery-medium', 'battery-warning', 'plug', 'plug-zap',
            'power', 'power-off', 'usb',

            // Files & Documents
            'file-text', 'file', 'file-plus', 'file-minus', 'file-search',
            'file-check', 'file-x', 'file-image', 'file-video',
            'file-audio', 'file-archive', 'file-code', 'file-spreadsheet',
            'file-type', 'folder', 'folder-plus', 'folder-minus',
            'folder-open', 'folder-search', 'folder-check', 'folder-x',
            'folder-root', 'folder-tree', 'clipboard', 'clipboard-list',
            'clipboard-check', 'clipboard-x', 'copy', 'copy-check',
            'scissors', 'pen', 'pen-tool', 'pencil', 'pencil-line',
            'pencil-ruler', 'eraser', 'highlighter', 'type', 'sticky-note',
            'book-open', 'book', 'bookmark', 'bookmark-check', 'bookmark-x',
            'notebook', 'notebook-pen', 'notebook-text', 'notepad-text',

            // Arrows & Direction
            'arrow-up', 'arrow-down', 'arrow-left', 'arrow-right',
            'arrow-up-down', 'arrow-left-right',
            'arrow-up-circle', 'arrow-down-circle',
            'arrow-left-circle', 'arrow-right-circle',
            'chevron-up', 'chevron-down', 'chevron-left', 'chevron-right',
            'chevrons-up-down', 'chevrons-left-right',
            'chevron-up-circle', 'chevron-down-circle',
            'move-up', 'move-down', 'move-left', 'move-right',
            'up-down', 'corner-up-left', 'corner-up-right',
            'corner-left-down', 'corner-left-up',
            'corner-right-down', 'corner-right-up',
            'maximize', 'minimize', 'maximize-2', 'minimize-2',
            'fullscreen', 'expand', 'shrink', 'zoom-in', 'zoom-out',

            // UI & Navigation
            'menu', 'menu-square', 'home', 'search', 'search-x',
            'search-check', 'x', 'x-circle', 'check', 'check-circle',
            'check-circle-2', 'check-square', 'plus', 'plus-circle',
            'plus-square', 'minus', 'minus-circle', 'minus-square',
            'trash', 'trash-2', 'edit', 'settings', 'cog',
            'sliders-horizontal', 'sliders-vertical', 'toggle-left',
            'toggle-right', 'filter', 'list', 'list-checks',
            'list-ordered', 'grid-3x3', 'columns', 'rows', 'layout-grid',
            'layout-list', 'layout-dashboard', 'panel-left',
            'panel-right', 'panel-top', 'panel-bottom',
            'sidebar', 'sidebar-close', 'sidebar-open',

            // Social & Users
            'user', 'users', 'user-plus', 'user-minus', 'user-check',
            'user-x', 'user-cog', 'user-round', 'users-round',
            'user-round-plus', 'user-round-minus', 'user-round-check',
            'user-round-x', 'user-round-cog', 'profile', 'fingerprint',
            'shield', 'shield-check', 'shield-off', 'shield-alert',
            'shield-ban', 'shield-plus', 'lock', 'unlock',
            'key', 'key-round', 'eye', 'eye-off', 'scan',

            // Weather & Nature
            'sun', 'sunrise', 'sunset', 'moon', 'cloud',
            'cloud-sun', 'cloud-moon', 'cloud-lightning', 'cloud-rain',
            'cloud-snow', 'cloud-drizzle', 'cloud-fog', 'cloud-hail',
            'cloudy', 'haze', 'snowflake', 'wind', 'umbrella',
            'thermometer', 'droplets', 'leaf', 'tree-deciduous',
            'tree-pine', 'flower-2', 'sprout', 'seedling',

            // Shapes & Symbols
            'circle', 'circle-dot', 'circle-ellipsis', 'square',
            'square-dot', 'triangle', 'triangle-right', 'diamond',
            'hexagon', 'octagon', 'pentagon', 'infinity',
            'heart', 'star', 'sparkles', 'flame', 'fire',
            'crown', 'award', 'trophy', 'medal', 'ribbon',
            'gem', 'dice-5', 'dices', 'target', 'crosshair',

            // Time & Status
            'clock', 'clock-1', 'clock-2', 'clock-3', 'clock-4',
            'clock-5', 'clock-6', 'clock-7', 'clock-8', 'clock-9',
            'clock-10', 'clock-11', 'clock-12', 'alarm-clock',
            'alarm-clock-check', 'alarm-clock-off', 'timer',
            'timer-off', 'hourglass', 'calendar', 'calendar-check',
            'calendar-plus', 'calendar-minus', 'calendar-x',
            'calendar-days', 'calendar-range', 'calendar-arrow-up',
            'calendar-arrow-down', 'calendar-clock',
            'activity', 'pulse', 'waves', 'signal', 'signal-high',
            'signal-low', 'signal-medium', 'signal-zero', 'wifi',

            // Alerts & Notifications
            'bell', 'bell-plus', 'bell-minus', 'bell-off',
            'bell-ring', 'bell-dot', 'alert-circle', 'alert-triangle',
            'alert-octagon', 'info', 'help-circle', 'question-mark',
            'message-circle-warning', 'message-square-warning',

            // Misc
            'anchor', 'atom', 'axe', 'bean', 'beer', 'bomb',
            'bone', 'book-open-text', 'brick-wall', 'brush',
            'bug', 'building', 'building-2', 'cable-car',
            'cake', 'calculator', 'candy', 'candy-cane',
            'carrot', 'cat', 'cherry', 'chef-hat', 'church',
            'cigarette-off', 'citrus', 'clover', 'cocktail',
            'coffee', 'coins', 'combine', 'cone', 'concierge-bell',
            'cookie', 'cooking-pot', 'cookie', 'copyleft', 'copyright',
            'crab', 'crop', 'cross', 'curly-braces', 'disc-3',
            'donut', 'door-open', 'drama', 'drill', 'drop',
            'droplet', 'ear', 'ear-off', 'egg', 'egg-fried',
            'egg-off', 'electric-car', 'electric-vehicle',
            'factory', 'fan', 'fast-forward', 'feather', 'fence',
            'ferris-wheel', 'figma', 'flag', 'flag-off', 'flashlight',
            'flask-conical', 'flask-round', 'flip-horizontal',
            'flip-vertical', 'flower', 'flying-saucer', 'focus',
            'fold-horizontal', 'fold-vertical', 'food', 'footprints',
            'forklift', 'form-input', 'frame', 'framer', 'frown',
            'funnel', 'gallery-horizontal', 'gallery-vertical',
            'gamepad', 'gantt-chart', 'gauge', 'ghost', 'gir',
            'glass-water', 'glasses', 'grab', 'grape', 'grip',
            'grip-horizontal', 'grip-vertical', 'group',
            'ham', 'hammer', 'hand', 'hand-coins', 'hand-heart',
            'hand-helping', 'hand-metal', 'hand-platter',
            'handshake', 'hard-hat', 'hash', 'hat',
            'heading-1', 'heading-2', 'heading-3', 'heading-4',
            'heading-5', 'heading-6', 'headphones', 'hearing',
            'heater', 'helping-hand', 'hexagon', 'hiking',
            'history', 'hop', 'hopping', 'hospital', 'hotel',
            'ice-cream', 'ice-cream-bowl', 'ice-cream-cone',
            'igloo', 'inbox', 'indent', 'indian-rupee',
            'inspection-panel', 'instagram', 'italic', 'japan',
            'joystick', 'kanban', 'kanban-square', 'kanban-square-dashed',
            'kip', 'knife', 'lamp', 'lamp-ceiling', 'lamp-desk',
            'lamp-floor', 'lamp-wall-down', 'lamp-wall-up',
            'land-plot', 'languages', 'lasso', 'lasso-select',
            'laugh', 'layers', 'layers-2', 'layers-3',
            'layout-panel-left', 'layout-panel-top',
            'lectern', 'library', 'life-buoy', 'ligature',
            'lightbulb', 'lightbulb-off', 'link', 'link-2',
            'list-collapse', 'list-end', 'list-filter',
            'list-music', 'list-ordered', 'list-plus',
            'list-restart', 'list-start', 'list-tree',
            'list-video', 'list-x', 'loader', 'loader-2',
            'loader-pinwheel', 'locate', 'log-in', 'log-out',
            'luggage', 'magnet', 'mailbox', 'mars', 'mars-stroke',
            'martini', 'masonry', 'massage', 'math-hand',
            'maximize-2', 'meat', 'megaphone', 'megaphone-off',
            'memory-stick', 'menu', 'menu-square', 'merge',
            'message-square', 'message-square-diff',
            'message-square-dot', 'message-square-lock',
            'message-square-plus', 'message-square-quote',
            'message-square-share', 'message-square-text',
            'message-square-warning', 'message-square-x',
            'messages-square', 'metronome', 'microwave',
            'milk', 'milk-off', 'minimize-2', 'minus',
            'monitor', 'monitor-check', 'monitor-cog',
            'monitor-dot', 'monitor-down', 'monitor-off',
            'monitor-pause', 'monitor-play', 'monitor-smartphone',
            'monitor-speaker', 'monitor-stop', 'monitor-up',
            'monitor-x', 'moon-star', 'more-horizontal',
            'more-vertical', 'mountain', 'mountain-snow',
            'mouse-pointer', 'mouse-pointer-2', 'mouse-pointer-bolt',
            'mouse-pointer-click', 'move', 'move-3d',
            'move-diagonal', 'move-diagonal-2', 'move-horizontal',
            'move-vertical', 'moves', 'museum', 'music-2',
            'music-4', 'navigation-2-off', 'navigation-off',
            'necktie', 'newspaper', 'nfc', 'non-binary',
            'notebook', 'notebook-pen', 'notebook-text',
            'notepad-text', 'nut', 'nut-off', 'octagon-alert',
            'octagon-minus', 'octagon-pause', 'octagon-x',
            'omega', 'option', 'orbit', 'origami',
            'outdent', 'oven', 'package-minus', 'package-plus',
            'package-search', 'paintbrush', 'paintbrush-2',
            'paintbrush-vertical', 'palette', 'palmtree',
            'panel-bottom', 'panel-bottom-close',
            'panel-bottom-dashed', 'panel-bottom-open',
            'panel-left', 'panel-left-close', 'panel-left-dashed',
            'panel-left-open', 'panel-right', 'panel-right-close',
            'panel-right-dashed', 'panel-right-open',
            'panel-top', 'panel-top-close', 'panel-top-dashed',
            'panel-top-open', 'panels-left-bottom',
            'panels-right-bottom', 'panels-top-left',
            'panels-top-right', 'paperclip', 'parentheses',
            'parking-meter', 'party-popper', 'pause',
            'paw-print', 'paypal', 'peace', 'pen-line',
            'pen-off', 'pencil-ruler', 'penguin', 'pentagon',
            'percent-diamond', 'person-standing', 'philippine-peso',
            'phone-off', 'pi', 'piano', 'pickaxe', 'picture-in-picture',
            'picture-in-picture-2', 'piggy-bank', 'pilcrow',
            'pilcrow-left', 'pilcrow-right', 'pill', 'pill-bottle',
            'pin', 'pin-off', 'pipette', 'pizza', 'plane-landing',
            'plane-takeoff', 'play', 'plug-2', 'plug-zap-2',
            'plus', 'pocket', 'pocket-knife', 'podcast',
            'pointer', 'pointer-off', 'popcorn', 'popsicle',
            'pound-sterling', 'power-circle', 'power-square',
            'presentation', 'printer-check', 'printer-x',
            'projector', 'proportions', 'puzzle', 'qr-code',
            'quote', 'radar', 'radio-receiver', 'radish',
            'rainbow', 'rat', 'rattle', 'recycle',
            'redo', 'redo-2', 'refresh-ccw', 'refresh-cw',
            'refrigerator', 'regex', 'remove-formatting',
            'repeat', 'repeat-1', 'repeat-2',
            'replace', 'replace-all', 'reply-all', 'rewind',
            'ribbon', 'rocket', 'rocking-chair', 'roller-coaster',
            'rotate-3d', 'rotate-ccw', 'rotate-ccw-square',
            'rotate-cw', 'rotate-cw-square', 'route-off',
            'router', 'rows', 'rows-2', 'rows-3', 'rows-4',
            'rss', 'ruler', 'ruler', 'russian-ruble', 'sailboat',
            'salad', 'sandwich', 'satellite', 'satellite-dish',
            'saudi-riyal', 'save-all', 'save-off', 'scale-3d',
            'scaling', 'scan-barcode', 'scan-eye', 'scan-face',
            'scan-line', 'scan-qr-code', 'scan-search', 'scan-text',
            'scatter-chart', 'school', 'school-2', 'scissors',
            'screencast', 'screen-share', 'screen-share-off',
            'scroll', 'scroll-text', 'search-code', 'search-slash',
            'section', 'send-horizonal', 'send-to-back',
            'separator-horizonal', 'separator-vertical',
            'server', 'server-cog', 'server-crash', 'server-off',
            'settings-2', 'shapes', 'share', 'share-2',
            'sheet', 'shelves', 'shirt', 'shopping-bag',
            'shopping-cart', 'shower-head', 'shrink', 'shrub',
            'shuffle', 'sidebar', 'sidebar-close', 'sidebar-open',
            'sigma', 'signal-high', 'signal-low', 'signal-medium',
            'signal-zero', 'signpost-big', 'siren', 'skateboard',
            'skip-back', 'skip-forward', 'skull', 'slash',
            'slash', 'slice', 'sliders-horizontal',
            'sliders-vertical', 'smartphone-charging', 'smartphone-nfc',
            'smile-plus', 'snail', 'snowflake', 'sofa', 'solar-panel',
            'soup', 'space', 'spade', 'sparkle', 'sparkles',
            'speaker', 'speech', 'spell-check', 'spell-check-2',
            'spline', 'split', 'split-square-horizontal',
            'split-square-vertical', 'spray-can', 'sprout',
            'square-activity', 'square-arrow-left', 'square-arrow-out-down-left',
            'square-arrow-out-down-right', 'square-arrow-out-up-left',
            'square-arrow-out-up-right', 'square-arrow-right',
            'square-arrow-up', 'square-asterisk', 'square-bottom-dashed-scroll',
            'square-check', 'square-check-big', 'square-code',
            'square-dashed', 'square-dashed-bottom', 'square-dashed-mouse-pointer',
            'square-divide', 'square-dot', 'square-equal',
            'square-function', 'square-gantt-chart', 'square-kanban',
            'square-library', 'square-m', 'square-menu',
            'square-minus', 'square-mouse-pointer', 'square-parking',
            'square-parking-off', 'square-pen', 'square-percent',
            'square-pi', 'square-pilcrow', 'square-play',
            'square-plus', 'square-power', 'square-radical',
            'square-scroll', 'square-sigma', 'square-slash',
            'square-split-horizontal', 'square-split-vertical',
            'square-user', 'square-user-round', 'square-x',
            'squircle', 'squirrel', 'stamp', 'star-half',
            'star-off', 'step-back', 'step-forward',
            'stethoscope', 'sticker', 'sticky-note',
            'store', 'stretch-horizontal', 'stretch-vertical',
            'strikethrough', 'subscript', 'sugar',
            'superscript', 'swatch-book', 'swiss-franc',
            'switch-camera', 'sword', 'swords', 'syringe',
            'table', 'table-2', 'table-cells-merge', 'table-cells-split',
            'table-columns-split', 'table-of-contents',
            'table-properties', 'table-rows-split', 'tablet-smartphone',
            'tablets', 'tag', 'tags', 'tally-1', 'tally-2',
            'tally-3', 'tally-4', 'tally-5', 'tangent',
            'target', 'telescope', 'tent', 'terminal',
            'terminal-square', 'test-tube-2', 'test-tubes',
            'text', 'text-cursor', 'text-cursor-input',
            'text-quote', 'text-search', 'text-select',
            'theater', 'thermometer-snowflake', 'thermometer-sun',
            'thumbs-down', 'thumbs-up', 'ticket',
            'ticket-check', 'ticket-minus', 'ticket-percent',
            'ticket-plus', 'ticket-slash', 'ticket-x',
            'tickets', 'tickets-plane', 'timer-off',
            'timer-reset', 'toggle-left', 'toggle-right',
            'tornado', 'torus', 'touchpad', 'touchpad-off',
            'tower-control', 'toy-brick', 'tractor',
            'traffic-cone', 'train-front-tunnel', 'train-track',
            'tram-front', 'transgender', 'trash',
            'tree-deciduous', 'tree-pine', 'trees',
            'trello', 'triangle-alert', 'triangle-right',
            'triange', 'trophy', 'truck', 'trash-2',
            'turkey', 'turtle', 'tv-minimal', 'tv-minimal-play',
            'twitch', 'twitter', 'type', 'umbrella-off',
            'underline', 'undo', 'undo-2', 'unfold-horizontal',
            'unfold-vertical', 'ungroup', 'unlink', 'unlink-2',
            'unplug', 'upload', 'usb', 'user-check',
            'user-cog', 'user-minus', 'user-plus', 'user-round-check',
            'user-round-cog', 'user-round-minus', 'user-round-plus',
            'user-round-search', 'user-round-x', 'user-search',
            'user-x', 'utensils', 'utensils-crossed',
            'utility-pole', 'vacuum-cleaner', 'variable',
            'vault', 'vegan', 'venetian-mask', 'venus',
            'venus-and-mars', 'venus-mars', 'verified',
            'vibrate', 'vibrate-off', 'video', 'video-off',
            'videotape', 'view', 'voicemail', 'volleyball',
            'volume', 'volume-1', 'volume-2', 'volume-off',
            'volume-x', 'vote', 'wallet-cards', 'wallet-minimal',
            'wallpaper', 'wand', 'wand-sparkles', 'warehouse',
            'washing-machine', 'watch', 'waves', 'waypoints',
            'webcam', 'webhook', 'weight', 'wheat',
            'wheat-off', 'whole-word', 'wifi', 'wifi-off',
            'wind', 'wine', 'wine-off', 'workflow',
            'worm', 'wrap-text', 'wrench', 'x',
            'youtube', 'zap', 'zap-off', 'zoom-in',
            'zoom-out'
        );
    }
}
