<?php
class ControllerExtensionModuleQmenu extends Controller {
    private $error = [];

    public function index(): void {
        $this->load->language('extension/module/qmenu');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('localisation/language');

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $language_ids = [];

        foreach ($data['languages'] as $language) {
            $language_ids[] = (int) $language['language_id'];
        }

        $default_language_id = (int) $this->config->get('config_language_id');
        $fallback_label = $this->language->get('text_default_label');

        if ($fallback_label === 'text_default_label' || $fallback_label === '') {
            $fallback_label = 'qmenu';
        }

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $status = !empty($this->request->post['module_qmenu_status']) ? 1 : 0;

            $label_input = $this->request->post['module_qmenu_label'] ?? [];
            $labels = $this->prepareLabelSet($label_input, $language_ids, $default_language_id, $fallback_label);

            $items_input = $this->request->post['module_qmenu_items'] ?? [];
            $items = $this->sanitizeItems($items_input, $language_ids, $default_language_id, $fallback_label);

            $settings = [
                'module_qmenu_status' => $status,
                'module_qmenu_label' => json_encode($labels, JSON_UNESCAPED_UNICODE),
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
        $data['default_language_id'] = $default_language_id;

        if (isset($this->request->post['module_qmenu_status'])) {
            $data['module_qmenu_status'] = !empty($this->request->post['module_qmenu_status']) ? 1 : 0;
        } else {
            $data['module_qmenu_status'] = (int) $this->config->get('module_qmenu_status');
        }

        if (isset($this->request->post['module_qmenu_label'])) {
            $data['module_qmenu_label'] = $this->prepareLabelSet(
                $this->request->post['module_qmenu_label'],
                $language_ids,
                $default_language_id,
                $fallback_label
            );
        } else {
            $stored_label = $this->config->get('module_qmenu_label');
            $decoded_label = $this->decodeSettingValue($stored_label);

            if (is_array($decoded_label)) {
                $source = $decoded_label;
            } else {
                $source = [$default_language_id => trim((string) $decoded_label)];
            }

            $data['module_qmenu_label'] = $this->prepareLabelSet($source, $language_ids, $default_language_id, $fallback_label);
        }

        if (isset($this->request->post['module_qmenu_items'])) {
            $data['items'] = $this->sanitizeItems($this->request->post['module_qmenu_items'], $language_ids, $default_language_id, $fallback_label);
        } else {
            $stored_items = $this->config->get('module_qmenu_items');
            $decoded_items = $this->decodeSettingValue($stored_items);

            $data['items'] = $this->sanitizeItems(is_array($decoded_items) ? $decoded_items : [], $language_ids, $default_language_id, $fallback_label);
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

        return !$this->error;
    }

    private function prepareLabelSet($labels, array $language_ids, int $default_language_id, string $fallback): array {
        if (!is_array($labels)) {
            $labels = [];
        }

        $prepared = [];

        foreach ($language_ids as $language_id) {
            $prepared[$language_id] = isset($labels[$language_id]) ? trim((string) $labels[$language_id]) : '';
        }

        $primary = $prepared[$default_language_id] ?? '';

        if ($primary === '') {
            foreach ($prepared as $value) {
                if ($value !== '') {
                    $primary = $value;
                    break;
                }
            }
        }

        if ($primary === '') {
            $primary = $fallback;
        }

        $prepared[$default_language_id] = $primary;

        return $prepared;
    }

    private function sanitizeItems($items, array $language_ids, int $default_language_id, string $fallback_label): array {
        $result = [];

        if (!is_array($items)) {
            return $result;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = isset($item['type']) && $item['type'] === 'route' ? 'route' : 'link';
            $href = isset($item['href']) ? trim((string) $item['href']) : '';
            $route = isset($item['route']) ? trim((string) $item['route']) : '';
            $new_tab = !empty($item['new_tab']) ? 1 : 0;

            if ($type === 'link' && $href === '') {
                continue;
            }

            if ($type === 'route' && $route === '') {
                continue;
            }

            $raw_labels = $item['label'] ?? [];

            if (!is_array($raw_labels)) {
                $raw_labels = [];
            }

            $labels = [];

            foreach ($language_ids as $language_id) {
                $labels[$language_id] = isset($raw_labels[$language_id]) ? trim((string) $raw_labels[$language_id]) : '';
            }

            $primary = $labels[$default_language_id] ?? '';

            if ($primary === '') {
                foreach ($labels as $value) {
                    if ($value !== '') {
                        $primary = $value;
                        break;
                    }
                }
            }

            if ($primary === '') {
                $primary = $type === 'route' ? $route : $href;
            }

            if ($primary === '') {
                $primary = $fallback_label;
            }

            $labels[$default_language_id] = $primary;

            $result[] = [
                'label' => $labels,
                'type' => $type,
                'href' => $type === 'link' ? $href : '',
                'route' => $type === 'route' ? $route : '',
                'new_tab' => $new_tab
            ];
        }

        return $result;
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
