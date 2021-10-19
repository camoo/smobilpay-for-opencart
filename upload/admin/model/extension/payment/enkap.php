<?php

class ModelExtensionPaymentEnkap extends Model {
	
    public function getOrderTransactionId($order_id){
		return $this->db->query("SELECT `order_transaction_id` FROM `" . DB_PREFIX . "enkap_transaction` WHERE `order_id` = '" . (int)$order_id . "'")->row;
	}

    public function setOrderStatus($order_id, $order_status_id){
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
    }
}