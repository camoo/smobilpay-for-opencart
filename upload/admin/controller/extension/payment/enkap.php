<?php

require_once __DIR__.'/../../../../enkap/vendor/autoload.php';

use \Enkap\OAuth\Services\CallbackUrlService;
use \Enkap\OAuth\Model\CallbackUrl;
use \Enkap\OAuth\Services\PaymentService;
use \Enkap\OAuth\Model\Status;

class ControllerExtensionPaymentEnkap extends Controller 
{
    private $error = array();

    public function index() 
    {
		$this->load->language('extension/payment/enkap');

		$this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_enkap', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$setup = new CallbackUrlService($this->request->post['payment_enkap_public'], $this->request->post['payment_enkap_private'], [], !$this->request->post['payment_enkap_test'] ? false : true);
			$callBack = $setup->loadModel(CallbackUrl::class);

			$url = new Url(HTTP_CATALOG, $this->config->get('config_secure') ? HTTP_CATALOG : HTTPS_CATALOG);
			# The URL where to redirect the user after the payment is completed. It will contain the reference id generated by your system which was provided in the initial order placement request. E-nkap will append your reference id in the path of the URL with the form: http://localhost/action/return/{yourReferenceId}
			$callBack->return_url = $url->link('extension/payment/enkap/callback', '', true);
		
			# The URL used by E-nkap to instantly notify you about the status of the payment. E-nkap would append your reference Id (generated by your system and provided in the initial order placement request) as path variable and send a PUT with the status of the payment in the body as {"status":"[txStatus]"}, where [txStatus] the payment status.
			$callBack->notification_url = $url->link('extension/payment/enkap/notify', '', true); // this action should accept PUT Request
			$setup->set($callBack); 

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

        $data['breadcrumbs'] = array();

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

    private function validate() {
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

	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "enkap_transaction` (
			`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
			`order_id` int(11) NOT NULL,
			`order_transaction_id` varchar(255) NOT NULL,
			`merchantReference` varchar(255) NOT NULL,
			PRIMARY KEY (`id`)
		  ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
	}

	
	public function status(){
		
		$this->load->model('sale/order');

		$order_id = $this->request->get['order_id'];

		if (isset($order_id)){
			$public     = $this->config->get('payment_enkap_public');
        	$private    = $this->config->get('payment_enkap_private');
        	$sandbox    = !$this->config->get('payment_enkap_test') ? false : true;

			$this->load->model('extension/payment/enkap');
            $value = $this->model_extension_payment_enkap->getOrderTransactionId($order_id);
			
			if($value && $value['order_transaction_id']){
				$paymentService = new PaymentService($public, $private, [], $sandbox);
				$payment = $paymentService->getByTransactionId($value['order_transaction_id']);
				// status
				$status = $payment->getPaymentStatus();
			
				switch($status) {
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

				$this->model_extension_payment_enkap->setOrderStatus($order_id, $order_status_id);
			}

		}
		return $this->response->redirect($this->request->server['HTTP_REFERER']);
	}

}