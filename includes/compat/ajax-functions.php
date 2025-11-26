<?php
/**
 * WordPress AJAX Functions Compatibility Layer
 */

declare(strict_types=1);

use ARM\Auth\AuthService;

if (!function_exists('send_json_success')) {
    function send_json_success($data = null, int $status_code = 200, int $options = 0): void
    {
        $response = ['success' => true];

        if (isset($data)) {
            $response['data'] = $data;
        }

        send_json($response, $status_code, $options);
    }
}

if (!function_exists('send_json')) {
    function send_json($response, int $status_code = null, int $options = 0): void
    {
        if ($status_code) {
            http_response_code($status_code);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, $options);

        exit;
    }
}

if (!function_exists('json_encode')) {
    function json_encode($data, int $options = 0, int $depth = 512)
    {
        $json = json_encode($data, $options, $depth);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return false;
        }

        return $json;
    }
}

if (!function_exists('check_ajax_referer')) {
    /**
     * Verify AJAX nonce
     */
    function check_ajax_referer(string $action = 'default', string $query_arg = 'nonce', bool $stop = true)
    {
        $nonce = '';

        if (isset($_REQUEST[$query_arg])) {
            $nonce = $_REQUEST[$query_arg];
        } elseif (isset($_REQUEST['_ajax_nonce'])) {
            $nonce = $_REQUEST['_ajax_nonce'];
        } elseif (isset($_REQUEST['_wpnonce'])) {
            $nonce = $_REQUEST['_wpnonce'];
        }

        $result = verify_nonce($nonce, $action);

        do_action('check_ajax_referer', $action, $result);

        if ($stop && !$result) {
            die('Security check failed', 403);
        }

        return $result;
    }
}

if (!function_exists('doing_ajax')) {
    function doing_ajax(): bool
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}

if (!function_exists('ajax_response')) {
    /**
     * Send XML response for legacy AJAX
     */
    function ajax_response($args = []): void
    {
        $defaults = [
            'what' => 'object',
            'action' => false,
            'id' => '0',
            'old_id' => false,
            'data' => '',
            'supplemental' => []
        ];

        $parsed_args = array_merge($defaults, $args);

        $x = new Ajax_Response($parsed_args);
        $x->send();
    }
}

// Simple XML response class for legacy support
if (!class_exists('Ajax_Response')) {
    class Ajax_Response
    {
        public array $responses = [];

        public function __construct($args = [])
        {
            if (!empty($args)) {
                $this->add($args);
            }
        }

        public function add($args = []): void
        {
            $this->responses[] = $args;
        }

        public function send(): void
        {
            header('Content-Type: text/xml; charset=utf-8');
            echo "<?xml version='1.0' encoding='utf-8' standalone='yes'?>\n";
            echo "<ajax>\n";

            foreach ($this->responses as $response) {
                $id = $response['id'] ?? 0;
                $what = $response['what'] ?? 'object';
                $action = $response['action'] ?? false;
                $old_id = $response['old_id'] ?? false;
                $data = $response['data'] ?? '';

                echo "<response";
                if ($action) {
                    echo " action='$action'";
                }
                echo ">\n";

                echo "<$what id='$id'";
                if ($old_id) {
                    echo " old_id='$old_id'";
                }
                echo ">";

                if (is_scalar($data)) {
                    echo "<![CDATA[$data]]>";
                } elseif (is_array($data) || is_object($data)) {
                    echo "<![CDATA[" . json_encode($data) . "]]>";
                }

                echo "</$what>\n";

                if (!empty($response['supplemental'])) {
                    foreach ($response['supplemental'] as $key => $value) {
                        echo "<supplemental>\n";
                        echo "<$key><![CDATA[$value]]></$key>\n";
                        echo "</supplemental>\n";
                    }
                }

                echo "</response>\n";
            }

            echo "</ajax>";
            exit;
        }
    }
}
