<?php
class ControllerExtensionModuleQmenu extends Controller {
    private $error = [];
    private $route_cache = null;
    private $category_cache = [];
    private $product_cache = [];
    private $information_cache = [];

    public function index(): void {
        $this->load->language('extension/module/qmenu');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('view/javascript/jquery/ui/jquery-ui.min.js');
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

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/qmenu', $data));
    }

    protected function validate(): bool {
        if (!$this->user->hasPermission('modify', 'extension/module/qmenu')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    private function sanitizeItems($items, string $fallback_label, bool $for_display = false): array {
        $result = [];

        if (!is_array($items)) {
            return $result;
        }

        $seen_keys = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = isset($item['type']) ? (string) $item['type'] : 'link';
            $href = isset($item['href']) ? trim((string) $item['href']) : '';
            $route = isset($item['route']) ? trim((string) $item['route']) : '';
            $new_tab = !empty($item['new_tab']) ? 1 : 0;
            $enabled_raw = $item['enabled'] ?? 1;

            if (is_array($enabled_raw)) {
                $enabled_raw = end($enabled_raw);
            }

            $enabled = !empty($enabled_raw) ? 1 : 0;

            $label = isset($item['label']) ? trim((string) $item['label']) : '';

            $color = isset($item['color']) ? trim((string) $item['color']) : '#000000';

            if ($color === '' || !preg_match('~^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$~', $color)) {
                $color = '#000000';
            }

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
                'new_tab' => $new_tab,
                'enabled' => $enabled
            ];

            if ($type === 'link') {
                if ($href === '') {
                    continue;
                }

                $data['href'] = $href;

                if ($label === '') {
                    $label = $href;
                }
            } elseif ($type === 'route') {
                if ($route === '') {
                    continue;
                }

                $route = preg_replace('~^/+~', '', $route);
                $route = preg_replace('~/+~', '/', $route);

                if ($route === '') {
                    continue;
                }

                $data['route'] = $route;

                if ($label === '') {
                    $label = $route;
                }
            } elseif ($type === 'category') {
                $category_id = isset($item['category_id']) ? (int) $item['category_id'] : 0;

                if (!$category_id) {
                    continue;
                }

                $category_name = isset($item['category_name']) ? trim((string) $item['category_name']) : '';

                if ($category_name === '') {
                    $category_name = $this->getEntityName('category', $category_id);
                }

                if ($label === '') {
                    $label = $category_name !== '' ? $category_name : $fallback_label;
                }

                $data['category_id'] = $category_id;
                $data['category_name'] = $category_name !== '' ? $category_name : $fallback_label;
            } elseif ($type === 'product') {
                $product_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;

                if (!$product_id) {
                    continue;
                }

                $product_name = isset($item['product_name']) ? trim((string) $item['product_name']) : '';

                if ($product_name === '') {
                    $product_name = $this->getEntityName('product', $product_id);
                }

                if ($label === '') {
                    $label = $product_name !== '' ? $product_name : $fallback_label;
                }

                $data['product_id'] = $product_id;
                $data['product_name'] = $product_name !== '' ? $product_name : $fallback_label;
            } elseif ($type === 'information') {
                $information_id = isset($item['information_id']) ? (int) $item['information_id'] : 0;

                if (!$information_id) {
                    continue;
                }

                $information_name = isset($item['information_name']) ? trim((string) $item['information_name']) : '';

                if ($information_name === '') {
                    $information_name = $this->getEntityName('information', $information_id);
                }

                if ($label === '') {
                    $label = $information_name !== '' ? $information_name : $fallback_label;
                }

                $data['information_id'] = $information_id;
                $data['information_name'] = $information_name !== '' ? $information_name : $fallback_label;
            } else {
                continue;
            }

            $unique_key_target = '';

            switch ($type) {
                case 'link':
                    $unique_key_target = $data['href'];
                    break;
                case 'route':
                    $unique_key_target = $data['route'];
                    break;
                case 'category':
                    $unique_key_target = (string) $data['category_id'];
                    break;
                case 'product':
                    $unique_key_target = (string) $data['product_id'];
                    break;
                case 'information':
                    $unique_key_target = (string) $data['information_id'];
                    break;
            }

            $unique_key = $type . '::' . $unique_key_target;

            if (isset($seen_keys[$unique_key])) {
                continue;
            }

            $seen_keys[$unique_key] = true;

            if ($label === '') {
                $label = $fallback_label;
            }

            $data['label'] = $label;
            $data['color'] = $color;

            $result[] = $data;
        }

        return $result;
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
        if ($id <= 0) {
            return '';
        }

        switch ($type) {
            case 'category':
                if (!array_key_exists($id, $this->category_cache)) {
                    $this->load->model('catalog/category');
                    $category = $this->model_catalog_category->getCategory($id);
                    $this->category_cache[$id] = $category ? $category['name'] : '';
                }

                return $this->category_cache[$id];

            case 'product':
                if (!array_key_exists($id, $this->product_cache)) {
                    $this->load->model('catalog/product');
                    $product = $this->model_catalog_product->getProduct($id);
                    $this->product_cache[$id] = $product ? $product['name'] : '';
                }

                return $this->product_cache[$id];

            case 'information':
                if (!array_key_exists($id, $this->information_cache)) {
                    $this->load->model('catalog/information');
                    $information = $this->model_catalog_information->getInformation($id);
                    $this->information_cache[$id] = $information ? $information['title'] : '';
                }

                return $this->information_cache[$id];
        }

        return '';
    }

    private function getAvailableRoutes(): array {
        if ($this->route_cache !== null) {
            return $this->route_cache;
        }

        $base = rtrim(DIR_APPLICATION . 'controller/', '/');
        $length = strlen($base) + 1;
        $routes = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

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
                $routes = array_filter($routes, function ($route) use ($filter_lower) {
                    return stripos($route, $filter_lower) !== false;
                });
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
}
