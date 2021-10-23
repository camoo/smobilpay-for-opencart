<?php

class ControllerExtensionPaymentEnkap extends Controller
{
    public function index()
    {
        $public = $this->config->get('payment_enkap_public');
        $private = $this->config->get('payment_enkap_private');
        $sandbox = (bool)$this->config->get('payment_enkap_test');

        if ($this->request->server['REQUEST_METHOD'] === 'POST') {

            $this->load->model('checkout/order');

            if (!isset($this->session->data['order_id'])) {
                return false;
            }

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $items = [];
            $currencyRate = $this->getCurrencyRate();
            if ($currencyRate === null) {
                return false;
            }
            if ($order_info) {
                $item = [];
                foreach ($this->cart->getProducts() as $product) {
                    $item['itemId'] = $product['product_id'];
                    $item['particulars'] = $product['name'];
                    $item['unitCost'] = (float)ceil($product['price'] * $currencyRate);
                    $item['quantity'] = (float)$product['quantity'];
                    $item['subTotal'] = (float)ceil($product['price'] * $currencyRate);
                    $items[] = $item;
                }
            }

            $merchantReference = uniqid('secure', true);
            $currencyRate = $this->getCurrencyRate();
            $orderData = [
                'merchantReference' => $merchantReference,
                'email' => $order_info['email'],
                'customerName' => $order_info['payment_lastname'] . ' ' . $order_info['payment_firstname'],
                'totalAmount' => (float)ceil($order_info['total'] * $currencyRate),
                'description' => 'SmobilPay for e-commerce',
                'currency' => 'XAF',
                'items' => $items
            ];

            try {
                $token = $this->getToken($public, $private, $sandbox);
                $apiUrls = $this->getApiUrls($sandbox);
                $orderUrl = $apiUrls['order_url'];
                $jsonStatus = $this->sendCurl($orderUrl, $orderData, $token, true);
                $orderObj = json_decode($jsonStatus);
                // Save references into your Database 
                $this->load->model('extension/payment/enkap');
                $this->model_extension_payment_enkap->addOrderTransactionId(
                    $this->session->data['order_id'],
                    $merchantReference,
                    $orderObj->orderTransactionId
                );

                return $this->response->redirect($orderObj->redirectUrl);

            } catch (Exception $e) {
                var_dump($e->getMessage());
            }
        }

        $this->load->language('extension/payment/enkap');

        $data['text_testmode'] = $this->language->get('text_testmode');
        $data['button_confirm'] = $this->language->get('button_confirm');

        $data['testmode'] = $this->config->get('payment_enkap_test');

        $data['action'] = $this->url->link('extension/payment/enkap', '', true);

        return $this->load->view('extension/payment/enkap', $data);
    }

    public function callback()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/enkap');

        $merchantReference = str_replace('/', '', $this->request->server['PATH_INFO']);

        $value = $this->model_extension_payment_enkap->getOrderTransactionId($merchantReference);

        switch ($this->request->get['status']) {
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
        $this->model_checkout_order->addOrderHistory($value['order_id'], $order_status_id);
        $this->model_extension_payment_enkap->updateOrderStatus($this->request->get['status'], $value['order_id']);
        if (in_array($this->request->get['status'], ['FAILED', 'CANCELED'])) {
            return $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
        return $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    public function notify()
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/enkap');

        $merchantReference = str_replace('/', '', $this->request->server['PATH_INFO']);

        $value = $this->model_extension_payment_enkap->getOrderTransactionId($merchantReference);

        $output = json_decode(html_entity_decode(file_get_contents('php://input')), true);

        switch ($output['status']) {

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

        $this->model_extension_payment_enkap->updateOrderStatus($output['status'], $value['order_id']);

        $this->model_checkout_order->addOrderHistory($value['order_id'], $order_status_id);
    }

    public function sendCurl($url, $data, $is_post = true)
    {
        $ch = curl_init($url);


        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        if ($is_post) {
            curl_setopt($ch, CURLOPT_POST, 1);
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

    protected function getCurrencyRate()
    {
        $cache = $this->getCache();

        $siteCurrency = $this->session->data['currency'];
        if ($siteCurrency === 'XAF') {
            return 1;
        }
        $currencyCacheKey = 'currency_' . $siteCurrency;
        $url = 'https://open.er-api.com/v6/latest/' . $siteCurrency;
        $currencyData = [];
        if ($cache !== null) {
            $cache::$gcLifetime = 86400;
            $currencies = $cache->getCache('compile', $currencyCacheKey);

            if (empty($currencies)) {
                $currencies = $this->sendCurl($url, [], false);
                $currencyData = json_decode($currencies, true);

               /* $expiresIn = $currencyData['time_next_update_unix'] - time();
                if (empty($expiresIn) || $expiresIn < 0) {
                    $expiresIn = 86400;
                }*/
                #$cache::$gcLifetime = $expiresIn;
                $cache->setCache('compile', $currencyCacheKey, $currencies);
            }
        }else{
            $currencies = $this->sendCurl($url, [], false);
            $currencyData = json_decode($currencies, true);
        }

        $rates = $currencyData['rates'];
        if (!array_key_exists('XAF', $rates)) {
            return null;
        }
        return $rates['XAF'];
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

    private function getCache()
    {
        if (!class_exists(\ScssPhp\ScssPhp\Cache::class)) {
            return null;
        }
        return new \ScssPhp\ScssPhp\Cache(['cacheDir' => DIR_CACHE, 'enkap_']);
    }
}
