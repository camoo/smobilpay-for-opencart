<?php

class ControllerExtensionPaymentEnkap extends Controller
{
    private $error = [];

    protected static function getPhpVersion()
    {
        if (!defined('PHP_VERSION_ID')) {
            $version = explode('.', PHP_VERSION);
            define('PHP_VERSION_ID', $version[0] * 10000 + $version[1] * 100 + $version[2]);
        }
        return 'PHP/' . PHP_VERSION_ID;
    }
    public function sendCurl($url, $data, $authorization = null, $is_post = true, $isPut = false)
    {
        $ch = curl_init($url);

        $header = [
            "Content-Type: application/json",
        ];
        if (null !== $authorization) {
            $header[] = "Authorization: Bearer " . $authorization;
        }
        curl_setopt( $ch, CURLOPT_USERAGENT, "SmobilPay-OC/CamooClient/". self::getPhpVersion());
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if ($is_post) {
            if ($isPut === true) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            } else {
                curl_setopt($ch, CURLOPT_POST, 1);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

        $response = curl_exec($ch);
        if (curl_errno($ch) != CURLE_OK) {
            $response = new stdClass();
            $response->Errors = "POST Error: " . curl_error($ch) . " URL: $url";
            $this->log->write(array('error' => curl_error($ch), 'errno' => curl_errno($ch)), 'cURL failed');
            $response = json_encode($response);
        } else {
            $info = curl_getinfo($ch);
            if ($info['http_code'] != 200) {
                $response = new stdClass();
                if ($info['http_code'] == 401 || $info['http_code'] == 404 || $info['http_code'] == 403) {
                    $response->Errors = "Please check the API Key and Password";
                } else {
                    $response->Errors = 'Error connecting : ' . $info['http_code'];
                }
                $response = json_encode($response);
            }
        }

        curl_close($ch);

        return $response;
    }

    protected function getToken($key, $secret, $sandbox = false)
    {
        $apiUrls = $this->getApiUrls($sandbox);
        $url = $apiUrls['token_url'];
        $authorization = base64_encode($key . ':' . $secret);
        $headers = ["content-type: application/x-www-form-urlencoded", "Authorization: Basic " . $authorization];
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => "SmobilPay-OC/CamooClient/". self::getPhpVersion(),
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $resp = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($code !== 200) {
            return null;
        }
        $data = json_decode($resp);
        return $data->access_token;
    }

    protected function getApiUrls($sandbox = false)
    {
        $order_url = $sandbox === false ? "https://api.enkap.cm/purchase/v1.2/api/order" : "https://api.enkap.maviance.info/purchase/api/order";
        $token_url = $sandbox === false ? "https://api.enkap.cm/token" : "https://api.enkap.maviance.info/token";
        $setup_url = $sandbox === false ? "https://api.enkap.cm/purchase/v1.2/api/order/setup" : "https://api.enkap.maviance.info/purchase/api/order/setup";
        return [
            'order_url' => $order_url,
            'token_url' => $token_url,
            'setup_url' => $setup_url
        ];
    }

    public function index()
    {

        $this->load->language('extension/payment/enkap');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] === 'POST') && $this->validate()) {

            $key = $this->request->post['payment_enkap_public'];
            $secret = $this->request->post['payment_enkap_private'];

            $this->model_setting_setting->editSetting('payment_enkap', $this->request->post);

            $url = new Url(HTTP_CATALOG, $this->config->get('config_secure') ? HTTP_CATALOG : HTTPS_CATALOG);

            $sandbox = (bool)$this->request->post['payment_enkap_test'];
            $token = $this->getToken($key, $secret, $sandbox);

            $data = [
                'returnUrl' => $url->link('extension/payment/enkap/callback', '', true),
                'notificationUrl' => $url->link('extension/payment/enkap/notify', '', true)
            ];
            $apiUrls = $this->getApiUrls($sandbox);
            $setupUrl = $apiUrls['setup_url'];
            $setup = $this->sendCurl($setupUrl, $data, $token, true, true);
            if (empty($setup)) {
                $this->session->data['success'] = $this->language->get('text_success');
            } else {
                $this->session->data['error'] = $this->language->get('text_error');
            }
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));

        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['public'])) {
            $data['error_public'] = $this->error['public'];
        } else {
            $data['error_public'] = '';
        }

        if (isset($this->error['private'])) {
            $data['error_private'] = $this->error['private'];
        } else {
            $data['error_private'] = '';
        }

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/enkap', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/payment/enkap', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_enkap_public'])) {
            $data['payment_enkap_public'] = $this->request->post['payment_enkap_public'];
        } else {
            $data['payment_enkap_public'] = $this->config->get('payment_enkap_public');
        }

        if (isset($this->request->post['payment_enkap_private'])) {
            $data['payment_enkap_private'] = $this->request->post['payment_enkap_private'];
        } else {
            $data['payment_enkap_private'] = $this->config->get('payment_enkap_private');
        }

        if (isset($this->request->post['payment_enkap_test'])) {
            $data['payment_enkap_test'] = $this->request->post['payment_enkap_test'];
        } else {
            $data['payment_enkap_test'] = $this->config->get('payment_enkap_test');
        }

        if (isset($this->request->post['payment_enkap_geo_zone_id'])) {
            $data['payment_enkap_geo_zone_id'] = $this->request->post['payment_enkap_geo_zone_id'];
        } else {
            $data['payment_enkap_geo_zone_id'] = $this->config->get('payment_enkap_geo_zone_id');
        }

        if (isset($this->request->post['payment_enkap_initialised_status_id'])) {
            $data['payment_enkap_initialised_status_id'] = $this->request->post['payment_enkap_initialised_status_id'];
        } else {
            $data['payment_enkap_initialised_status_id'] = $this->config->get('payment_enkap_initialised_status_id');
        }

        if (isset($this->request->post['payment_enkap_created_status_id'])) {
            $data['payment_enkap_created_status_id'] = $this->request->post['payment_enkap_created_status_id'];
        } else {
            $data['payment_enkap_created_status_id'] = $this->config->get('payment_enkap_created_status_id');
        }

        if (isset($this->request->post['payment_enkap_in_progress_status_id'])) {
            $data['payment_enkap_in_progress_status_id'] = $this->request->post['payment_enkap_in_progress_status_id'];
        } else {
            $data['payment_enkap_in_progress_status_id'] = $this->config->get('payment_enkap_in_progress_status_id');
        }

        if (isset($this->request->post['payment_enkap_confirmed_status_id'])) {
            $data['payment_enkap_confirmed_status_id'] = $this->request->post['payment_enkap_confirmed_status_id'];
        } else {
            $data['payment_enkap_confirmed_status_id'] = $this->config->get('payment_enkap_confirmed_status_id');
        }

        if (isset($this->request->post['payment_enkap_canceled_status_id'])) {
            $data['payment_enkap_canceled_status_id'] = $this->request->post['payment_enkap_canceled_status_id'];
        } else {
            $data['payment_enkap_canceled_status_id'] = $this->config->get('payment_enkap_canceled_status_id');
        }

        if (isset($this->request->post['payment_enkap_failed_status_id'])) {
            $data['payment_enkap_failed_status_id'] = $this->request->post['payment_enkap_failed_status_id'];
        } else {
            $data['payment_enkap_failed_status_id'] = $this->config->get('payment_enkap_failed_status_id');
        }

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        if (isset($this->request->post['payment_enkap_status'])) {
            $data['payment_enkap_status'] = $this->request->post['payment_enkap_status'];
        } else {
            $data['payment_enkap_status'] = $this->config->get('payment_enkap_status');
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_enkap_sort_order'])) {
            $data['payment_enkap_sort_order'] = $this->request->post['payment_enkap_sort_order'];
        } else {
            $data['payment_enkap_sort_order'] = $this->config->get('payment_enkap_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/enkap', $data));
    }

    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/enkap')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_enkap_public']) {
            $this->error['public'] = $this->language->get('error_public');
        }

        if (!$this->request->post['payment_enkap_private']) {
            $this->error['private'] = $this->language->get('error_private');
        }

        return !$this->error;
    }

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "enkap_transaction` (
			`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			`order_id` int(11) NOT NULL,
			`order_transaction_id` varchar(255) NOT NULL,
			`merchantReference` varchar(255) NOT NULL,
			`status`                varchar(50)   DEFAULT NULL,
			`status_date`           datetime      NOT NULL DEFAULT '2021-05-20 00:00:00',
            `created_at`            timestamp     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`            timestamp     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `remote_ip`             varbinary(64) NOT NULL DEFAULT '0.0.0.0',
			PRIMARY KEY (`id`)
		  ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    public function status()
    {

        $this->load->model('sale/order');

        $order_id = $this->request->get['order_id'];

        if (isset($order_id)) {
            $public = $this->config->get('payment_enkap_public');
            $private = $this->config->get('payment_enkap_private');
            $sandbox = (bool)$this->config->get('payment_enkap_test');

            $this->load->model('extension/payment/enkap');
            $value = $this->model_extension_payment_enkap->getOrderTransactionId($order_id);

            if ($value && $value['order_transaction_id']) {

                $token = $this->getToken($public, $private, $sandbox);
                $apiUrls = $this->getApiUrls($sandbox);
                $statusUrl = $apiUrls['order_url'] . '/status?txid=' . $value['order_transaction_id'];
                $jsonStatus = $this->sendCurl($statusUrl, [], $token, false);
                $statusObj = json_decode($jsonStatus);
                $status = $statusObj->status;
                switch ($status) {
                    case 'IN_PROGRESS':
                        $order_status_id = $this->config->get('payment_enkap_in_progress_status_id');
                        break;
                    case 'CREATED':
                        $order_status_id = $this->config->get('payment_enkap_created_status_id');
                        break;
                    case 'CANCELED':
                        $order_status_id = $this->config->get('payment_enkap_canceled_status_id');
                        break;
                    case 'INITIALISED':
                        $order_status_id = $this->config->get('payment_enkap_initialised_status_id');
                        break;
                    case 'FAILED':
                        $order_status_id = $this->config->get('payment_enkap_failed_status_id');
                        break;
                    case 'CONFIRMED':
                        $order_status_id = $this->config->get('payment_enkap_confirmed_status_id');
                        break;
                    default:
                        break;
                }

                $this->model_extension_payment_enkap->updateOrderStatus($status, $order_id);
                $this->model_extension_payment_enkap->setOrderStatus($order_id, $order_status_id);
            }

        }
        return $this->response->redirect($this->request->server['HTTP_REFERER']);
    }

}
