<?php
class ControllerExtensionModuleQmenu extends Controller {
    private $error = [];
    private $route_cache = null;
    private $entity_cache = [
        'category' => [],
        'product' => [],
        'information' => []
    ];

    private const COLOR_PATTERN = '~^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$~';
    private const DEFAULT_COLOR = '#000000';
    private const MAX_ITEMS = 50;
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'ftp'];

    private const TYPE_HANDLERS = [
        'link' => ['field' => 'href', 'required' => true],
        'route' => ['field' => 'route', 'required' => true],
        'category' => ['field' => 'category_id', 'entity' => true],
        'product' => ['field' => 'product_id', 'entity' => true],
        'information' => ['field' => 'information_id', 'entity' => true]
    ];

    public function index(): void {
        $this->load->language('extension/module/qmenu');

        $this->document->setTitle($this->language->get('heading_title'));

        // jQuery UI from CDN
        $this->document->addScript('https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js');
        $this->document->addStyle('https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css');

        // Module assets
        $this->document->addScript('view/javascript/qmenu.js');
        $this->document->addStyle('view/stylesheet/qmenu.css');

        $this->load->model('setting/setting');

        $default_label = $this->language->get('text_default_label');

        if ($default_label === 'text_default_label' || $default_label === '') {
            $default_label = 'qmenu';
        }

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $status = !empty($this->request->post['module_qmenu_status']) ? 1 : 0;

            $label = isset($this->request->post['module_qmenu_label']) ? trim((string) $this->request->post['module_qmenu_label']) : '';
            $label = $label !== '' ? $label : $default_label;

            $items_input = $this->request->post['module_qmenu_items'] ?? [];
            $items = $this->sanitizeItems($items_input, $default_label);

            $settings = [
                'module_qmenu_status' => $status,
                'module_qmenu_label' => $label,
                'module_qmenu_items' => json_encode($items, JSON_UNESCAPED_UNICODE)
            ];

            $this->model_setting_setting->editSetting('module_qmenu', $settings);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['error_warning'] = $this->error['warning'] ?? '';

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ],
            [
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/qmenu', 'user_token=' . $this->session->data['user_token'], true)
            ]
        ];

        $data['action'] = $this->url->link('extension/module/qmenu', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['user_token'] = $this->session->data['user_token'];

        $data['module_qmenu_status'] = isset($this->request->post['module_qmenu_status'])
            ? (!empty($this->request->post['module_qmenu_status']) ? 1 : 0)
            : (int) $this->config->get('module_qmenu_status');

        if (isset($this->request->post['module_qmenu_label'])) {
            $data['module_qmenu_label'] = trim((string) $this->request->post['module_qmenu_label']);
        } else {
            $stored_label = $this->config->get('module_qmenu_label');
            if (is_array($stored_label)) {
                $stored_label = implode(' ', $stored_label);
            }
            $data['module_qmenu_label'] = trim((string) $stored_label);
        }

        if ($data['module_qmenu_label'] === '') {
            $data['module_qmenu_label'] = $default_label;
        }

        if (isset($this->request->post['module_qmenu_items'])) {
            $items = $this->sanitizeItems($this->request->post['module_qmenu_items'], $default_label, true);
        } else {
            $stored_items = $this->decodeSettingValue($this->config->get('module_qmenu_items'));
            $items = $this->sanitizeItems(is_array($stored_items) ? $stored_items : [], $default_label, true);

            if (!$items) {
                $items = $this->buildDefaultItems($default_label);
            }
        }

        if (!$items) {
            $items = $this->buildDefaultItems($default_label);
        }

        $data['items'] = $items;
        $data['qmenu_routes'] = $this->getAvailableRoutes();

        foreach (['heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_extension', 'text_home',
                  'entry_status', 'entry_button_label', 'entry_items',
                  'column_label', 'column_color', 'column_type', 'column_destination', 'column_enabled', 'column_new_tab',
                  'text_drag_to_reorder', 'text_type_link', 'text_type_route', 'text_type_category', 'text_type_product', 'text_type_information',
                  'help_link', 'help_route', 'help_category', 'help_product', 'help_information',
                  'button_save', 'button_cancel', 'button_add_item', 'button_clear_color'] as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/qmenu', $data));
    }

    protected function validate(): bool {
        if (!$this->user->hasPermission('modify', 'extension/module/qmenu')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        // Check maximum items limit
        if (isset($this->request->post['module_qmenu_items']) && is_array($this->request->post['module_qmenu_items'])) {
            if (count($this->request->post['module_qmenu_items']) > self::MAX_ITEMS) {
                $this->error['warning'] = sprintf('Maximum %d menu items allowed. Please remove some items.', self::MAX_ITEMS);
            }
        }

        return !$this->error;
    }

    private function sanitizeItems($items, string $fallback_label, bool $for_display = false): array {
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        $seen_keys = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $processed = $this->processMenuItem($item, $fallback_label);

            if (!$processed) {
                continue;
            }

            $unique_key = $this->buildUniqueKey($processed);

            if (isset($seen_keys[$unique_key])) {
                continue;
            }

            $seen_keys[$unique_key] = true;
            $result[] = $processed;
        }

        return $result;
    }

    private function processMenuItem(array $item, string $fallback_label): ?array {
        $type = isset($item['type']) ? (string) $item['type'] : 'link';

        if (!isset(self::TYPE_HANDLERS[$type])) {
            return null;
        }

        $enabled_raw = $item['enabled'] ?? 1;
        if (is_array($enabled_raw)) {
            $enabled_raw = end($enabled_raw);
        }

        $color = $this->sanitizeColor($item['color'] ?? '');

        $data = [
            'label' => '',
            'type' => $type,
            'href' => '',
            'route' => '',
            'category_id' => 0,
            'category_name' => '',
            'product_id' => 0,
            'product_name' => '',
            'information_id' => 0,
            'information_name' => '',
            'color' => $color,
            'new_tab' => !empty($item['new_tab']) ? 1 : 0,
            'enabled' => !empty($enabled_raw) ? 1 : 0
        ];

        $label = isset($item['label']) ? trim((string) $item['label']) : '';

        if ($type === 'link') {
            $href = isset($item['href']) ? trim((string) $item['href']) : '';
            if ($href === '') {
                return null;
            }

            // XSS Protection: Validate URL scheme
            if (!$this->isValidUrl($href)) {
                return null;
            }

            $data['href'] = $href;
            $label = $label ?: $href;
        } elseif ($type === 'route') {
            $route = isset($item['route']) ? trim((string) $item['route']) : '';
            if ($route === '') {
                return null;
            }
            $route = preg_replace(['~^/+~', '~/+~'], ['', '/'], $route);
            if ($route === '') {
                return null;
            }
            $data['route'] = $route;
            $label = $label ?: $route;
        } else {
            $entity_result = $this->processEntityItem($item, $type, $fallback_label);
            if (!$entity_result) {
                return null;
            }
            $data = array_merge($data, $entity_result['data']);
            $label = $label ?: $entity_result['label'];
        }

        $data['label'] = $label ?: $fallback_label;

        return $data;
    }

    private function processEntityItem(array $item, string $type, string $fallback_label): ?array {
        $id_field = "{$type}_id";
        $name_field = "{$type}_name";

        $id = isset($item[$id_field]) ? (int) $item[$id_field] : 0;

        if (!$id) {
            return null;
        }

        $name = isset($item[$name_field]) ? trim((string) $item[$name_field]) : '';

        if ($name === '') {
            $name = $this->getEntityName($type, $id);
        }

        $label = $name !== '' ? $name : $fallback_label;

        return [
            'data' => [
                $id_field => $id,
                $name_field => $name !== '' ? $name : $fallback_label
            ],
            'label' => $label
        ];
    }

    private function sanitizeColor(string $color): string {
        $color = trim($color);

        if ($color === '' || !preg_match(self::COLOR_PATTERN, $color)) {
            return self::DEFAULT_COLOR;
        }

        return $color;
    }

    private function buildUniqueKey(array $data): string {
        $type = $data['type'];

        switch ($type) {
            case 'link':
                return "$type::{$data['href']}";
            case 'route':
                return "$type::{$data['route']}";
            case 'category':
                return "$type::{$data['category_id']}";
            case 'product':
                return "$type::{$data['product_id']}";
            case 'information':
                return "$type::{$data['information_id']}";
            default:
                return "$type::unknown";
        }
    }

    private function buildDefaultItems(string $fallback_label): array {
        $definitions = $this->getDefaultLinkDefinitions();
        $raw = [];

        foreach ($definitions as $definition) {
            $raw[] = [
                'label' => '',
                'type' => $definition['type'],
                'href' => $definition['type'] === 'link' ? ($definition['href'] ?? '') : '',
                'route' => $definition['type'] === 'route' ? ($definition['route'] ?? '') : '',
                'new_tab' => !empty($definition['new_tab']) ? 1 : 0,
                'enabled' => 1
            ];
        }

        return $this->sanitizeItems($raw, $fallback_label, true);
    }

    private function getDefaultLinkDefinitions(): array {
        return [
            [
                'label_key' => 'text_link_store_settings',
                'type' => 'route',
                'route' => 'setting/store',
                'href' => '',
                'new_tab' => false
            ],
            [
                'label_key' => 'text_link_refresh_modifications',
                'type' => 'route',
                'route' => 'marketplace/modification/refresh',
                'href' => '',
                'new_tab' => false
            ],
            [
                'label_key' => 'text_link_clear_mod_log',
                'type' => 'route',
                'route' => 'marketplace/modification/clearlog',
                'href' => '',
                'new_tab' => false
            ],
            [
                'label_key' => 'text_link_error_log',
                'type' => 'route',
                'route' => 'tool/log',
                'href' => '',
                'new_tab' => false
            ]
        ];
    }

    private function getEntityName(string $type, int $id): string {
        if ($id <= 0 || !isset($this->entity_cache[$type])) {
            return '';
        }

        if (!array_key_exists($id, $this->entity_cache[$type])) {
            $this->load->model("catalog/$type");
            $model_name = "model_catalog_$type";
            $method_name = 'get' . ucfirst($type);

            $entity = $this->$model_name->$method_name($id);
            $name_field = $type === 'information' ? 'title' : 'name';
            $this->entity_cache[$type][$id] = $entity ? $entity[$name_field] : '';
        }

        return $this->entity_cache[$type][$id];
    }

    private function getAvailableRoutes(): array {
        if ($this->route_cache !== null) {
            return $this->route_cache;
        }

        $base = rtrim(DIR_APPLICATION . 'controller/', '/');
        $length = strlen($base) + 1;
        $routes = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();

                $route = substr($path, $length, -4);
                $route = str_replace('\\', '/', $route);
                $route = preg_replace('~/+~', '/', $route);

                if ($route === false || $route === '') {
                    continue;
                }

                $routes[$route] = $route;
            }

            ksort($routes);
        } catch (\Exception $e) {
            // Log error but don't break functionality
            error_log('qmenu: Failed to scan routes - ' . $e->getMessage());
            $routes = [];
        }

        $this->route_cache = array_values($routes);

        return $this->route_cache;
    }

    public function autocomplete(): void {
        $json = [];

        if (!isset($this->request->get['user_token']) || $this->request->get['user_token'] !== $this->session->data['user_token']) {
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $filter = isset($this->request->get['filter_name']) ? trim((string) $this->request->get['filter_name']) : '';
        $type = isset($this->request->get['type']) ? (string) $this->request->get['type'] : 'route';

        if ($type === 'route') {
            $routes = $this->getAvailableRoutes();

            if ($filter !== '') {
                $filter_lower = strtolower($filter);
                $routes = array_filter($routes, fn($route) => stripos($route, $filter_lower) !== false);
            }

            $routes = array_slice(array_values($routes), 0, 20);

            foreach ($routes as $route) {
                $json[] = [
                    'label' => $route,
                    'value' => $route
                ];
            }
        } elseif ($type === 'category') {
            $this->load->model('catalog/category');

            $filter_data = [
                'filter_name' => $filter,
                'sort' => 'name',
                'order' => 'ASC',
                'start' => 0,
                'limit' => 20
            ];

            $categories = $this->model_catalog_category->getCategories($filter_data);

            foreach ($categories as $category) {
                $json[] = [
                    'label' => html_entity_decode($category['name'], ENT_QUOTES, 'UTF-8'),
                    'value' => (int) $category['category_id']
                ];
            }
        } elseif ($type === 'product') {
            $this->load->model('catalog/product');

            $filter_data = [
                'filter_name' => $filter,
                'sort' => 'pd.name',
                'order' => 'ASC',
                'start' => 0,
                'limit' => 20
            ];

            $products = $this->model_catalog_product->getProducts($filter_data);

            foreach ($products as $product) {
                $json[] = [
                    'label' => html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8'),
                    'value' => (int) $product['product_id']
                ];
            }
        } elseif ($type === 'information') {
            $this->load->model('catalog/information');

            $informations = $this->model_catalog_information->getInformations();

            foreach ($informations as $information) {
                if ($filter !== '' && stripos($information['title'], $filter) === false) {
                    continue;
                }

                $json[] = [
                    'label' => html_entity_decode($information['title'], ENT_QUOTES, 'UTF-8'),
                    'value' => (int) $information['information_id']
                ];

                if (count($json) >= 20) {
                    break;
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function decodeSettingValue($value) {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Validates URL to prevent XSS attacks via javascript:, data:, vbscript: schemes
     *
     * @param string $url URL to validate
     * @return bool True if URL is safe, false otherwise
     */
    private function isValidUrl(string $url): bool {
        if ($url === '' || $url === '#') {
            return true; // Allow empty anchors
        }

        // Allow relative URLs (starting with / or ./ or ../)
        if (preg_match('~^(\.{0,2}/|#)~', $url)) {
            return true;
        }

        // Parse URL and validate scheme
        $parsed = parse_url($url);

        // If no scheme (relative URL like "index.php?route=...")
        if (!isset($parsed['scheme'])) {
            return true;
        }

        // Check if scheme is in whitelist
        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, self::ALLOWED_URL_SCHEMES, true)) {
            error_log('qmenu: Blocked dangerous URL scheme: ' . $scheme);
            return false;
        }

        return true;
    }
}
