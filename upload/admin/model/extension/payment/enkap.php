<?php

class ModelExtensionPaymentEnkap extends Model
{

    public function getOrderTransactionId($order_id)
    {
        return $this->db->query("SELECT `order_transaction_id`, `merchantReference` FROM `" . DB_PREFIX . "enkap_transaction` WHERE `order_id` = '" . (int)$order_id . "'")->row;
    }

    public function setOrderStatus($order_id, $order_status_id)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
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

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "enkap_transaction`");
    }


    public function updateOrderStatus($capture_status, $order_id)
    {
        $clientIp = $this->request->server['REMOTE_ADDR'];
        $this->db->query("UPDATE `" . DB_PREFIX . "enkap_transaction` SET `status_date` = NOW(), `status` = '" . $this->db->escape($capture_status) . "', `remote_ip` = '".$this->db->escape($clientIp)."' WHERE `order_id` = '" . (int)$order_id . "'");
    }

    public function getCurrencies()
    {
        return [
            'XAF',
            'XOF',
            'EUR',
            'USD',
        ];
    }

}
