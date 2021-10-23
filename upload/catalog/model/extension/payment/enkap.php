<?php

class ModelExtensionPaymentEnkap extends Model
{

    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/enkap');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_enkap_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        if (!$this->config->get('payment_enkap_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $currencies = [
            'XOF',
            'XAF',
            'USD',
            'EUR',
        ];

        if (!in_array(strtoupper($this->session->data['currency']), $currencies)) {
            $status = false;
        }

        $method_data = [];

        if ($status) {
            $method_data = [
                'code' => 'enkap',
                'title' => $this->language->get('text_title'),
                'terms' => '',
                'sort_order' => $this->config->get('payment_enkap_sort_order')
            ];
        }

        return $method_data;
    }

    public function addOrderTransactionId($order_id, $merchantReference, $order_transaction_id)
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "enkap_transaction` SET order_id = '" . (int)$order_id . "', `order_transaction_id` = '" . $this->db->escape($order_transaction_id) . "', `merchantReference` = '" . $this->db->escape($merchantReference) . "'");
    }

    public function getOrderTransactionId($merchantReference)
    {
        return $this->db->query("SELECT `order_transaction_id`, `order_id` FROM `" . DB_PREFIX . "enkap_transaction` WHERE `merchantReference` = '" . $merchantReference . "'")->row;
    }


    public function updateOrderStatus($capture_status, $order_id)
    {
        $clientIp = $this->request->server['REMOTE_ADDR'];
        $this->db->query("UPDATE `" . DB_PREFIX . "enkap_transaction` SET `status_date` = NOW(), `status` = '" . $this->db->escape($capture_status) . "', `remote_ip` = '".$this->db->escape($clientIp)."' WHERE `order_id` = '" . (int)$order_id . "'");
    }


}
