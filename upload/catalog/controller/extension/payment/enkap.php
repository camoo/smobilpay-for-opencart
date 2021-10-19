<?php

require_once __DIR__.'/../../../../enkap/vendor/autoload.php';

use \Enkap\OAuth\Services\OrderService;
use \Enkap\OAuth\Model\Order;
use \Enkap\OAuth\Model\Status;
use \Enkap\OAuth\Services\StatusService;

class ControllerExtensionPaymentEnkap extends Controller 
{
    public function index()
    {
        $public     = $this->config->get('payment_enkap_public');
        $private    = $this->config->get('payment_enkap_private');
        $sandbox    = !$this->config->get('payment_enkap_test') ? false : true;
        
        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            
            $this->load->model('checkout/order');

            if(!isset($this->session->data['order_id'])) {
                return false;
            }
        
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            if ($order_info) {
                $items = []; $item = [];
                foreach ($this->cart->getProducts() as $product) {
                    $item['itemId'] = $product['product_id'];
                    $item['particulars'] = $product['name'];
                    $item['unitCost'] = (int)$product['price'];
                    $item['quantity'] = (int)$product['quantity'];
                    $items[] = $item;         
                }
            }

            $merchantReference = uniqid('secure', true);
            $orderService = new OrderService($public, $private, [], $sandbox);
            $order = $orderService->loadModel(Order::class);
            $dataData = [
                'merchantReference' => $merchantReference,
                'email' => $order_info['email'],
                'customerName' => $order_info['payment_lastname'].' '.$order_info['payment_firstname'],
                'totalAmount' => (int)$order_info['total'],
                'description' => 'Camoo Payment',
                'currency' => 'XAF', 
                'items' => $items
            ];

            try {
                $order->fromStringArray($dataData);
                $response = $orderService->place($order);

                // Save references into your Database 
                $this->load->model('extension/payment/enkap');
                $this->model_extension_payment_enkap->addOrderTransactionId($this->session->data['order_id'], $merchantReference, $response->getOrderTransactionId());
                
                return $this->response->redirect($response->getRedirectUrl());
                
            } catch (\Throwable $e) {
                var_dump($e->getMessage());
            }
		}

        $this->load->language('extension/payment/enkap');

        $data['text_testmode']  = $this->language->get('text_testmode');
        $data['button_confirm'] = $this->language->get('button_confirm');

        $data['testmode'] = $this->config->get('payment_enkap_test');

        $data['action'] = $this->url->link('extension/payment/enkap', '', true);

		return $this->load->view('extension/payment/enkap', $data);
    }

    public function callback(){
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/enkap');

        $merchantReference = str_replace('/', '', $this->request->server['PATH_INFO']);

        $public     = $this->config->get('payment_enkap_public');
        $private    = $this->config->get('payment_enkap_private');
        $sandbox    = !$this->config->get('payment_enkap_test') ? false : true;
 
        $value = $this->model_extension_payment_enkap->getOrderTransactionId($merchantReference);
        
        $statusService = new StatusService($public, $private, [], $sandbox);
        $status = $statusService->getByTransactionId($value['order_transaction_id']);

        switch($this->request->get['status']) {
            case Status::IN_PROGRESS_STATUS:
                $order_status_id = $this->config->get('payment_enkap_in_progress_status_id');
                break;
            case Status::CREATED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_created_status_id');
                break;
            case Status::CANCELED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_canceled_status_id');
                break;
            case Status::INITIALISED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_initialised_status_id');
                break;
            case Status::FAILED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_failed_status_id');
                break;
            case Status::CONFIRMED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_confirmed_status_id');
                break;
        }
        $this->model_checkout_order->addOrderHistory($value['order_id'], $order_status_id);

        if ($status->confirmed()){
            // Payment successfully completed
            // send Item to user/customer
        }

        if ($status->failed() || $status->canceled()) {
            // delete that reference from your Database
            return $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
        return $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    public function notify(){
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/enkap');

        $merchantReference = str_replace('/', '', $this->request->server['PATH_INFO']);

        $value = $this->model_extension_payment_enkap->getOrderTransactionId($merchantReference);
        
        $output = json_decode(html_entity_decode(file_get_contents('php://input')), true);
        
        switch($output['status']) {
            case Status::IN_PROGRESS_STATUS:
                $order_status_id = $this->config->get('payment_enkap_in_progress_status_id');
                break;
            case Status::CREATED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_created_status_id');
                break;
            case Status::CANCELED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_canceled_status_id');
                break;
            case Status::INITIALISED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_initialised_status_id');
                break;
            case Status::FAILED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_failed_status_id');
                break;
            case Status::CONFIRMED_STATUS:
                $order_status_id = $this->config->get('payment_enkap_confirmed_status_id');
                break;
        }

        $this->model_checkout_order->addOrderHistory($value['order_id'], $order_status_id);
    }
}