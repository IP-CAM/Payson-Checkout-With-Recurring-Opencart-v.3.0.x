<?php

class ModelExtensionPaymentPaysonCheckout2 extends Model {

    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payson_embedded_order` (
            `payson_embedded_id` int(11) AUTO_INCREMENT,
            `order_id` int(15) NOT NULL,
            `checkout_id` varchar(40) DEFAULT NULL,
            `purchase_id` varchar(50) DEFAULT NULL,
            `payment_status` varchar(20) DEFAULT NULL,
            `added` datetime DEFAULT NULL,
            `updated` datetime DEFAULT NULL,
            `sender_email` varchar(50) DEFAULT NULL,
            `currency_code` varchar(5) DEFAULT NULL,
            `tracking_id` varchar(100) DEFAULT NULL,
            `type` varchar(50) DEFAULT NULL,
            `shippingAddress_name` varchar(50) DEFAULT NULL,
            `shippingAddress_lastname` varchar(50) DEFAULT NULL,
            `shippingAddress_street_ddress` varchar(60) DEFAULT NULL,
            `shippingAddress_postal_code` varchar(20) DEFAULT NULL,
            `shippingAddress_city` varchar(60) DEFAULT NULL,
            `shippingAddress_country` varchar(60) DEFAULT NULL,
            PRIMARY KEY (`payson_embedded_id`)
        ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payson_embedded_order_rec` (
            `payson_embedded_order_rec_id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `payson_embedded_subscription_id` VARCHAR(50),
            `payson_embedded_order_id` VARCHAR(50),
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            `refund_status` INT(1) DEFAULT NULL,
            `currency_code` CHAR(3) NOT NULL,
            `total` DECIMAL( 10, 2 ) NOT NULL,
            PRIMARY KEY (`payson_embedded_order_rec_id`)
        ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payson_embedded_order_transaction` (
            `payson_embedded_order_transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
            `payson_embedded_order_rec_id` INT(11) NOT NULL,
            `date_added` DATETIME NOT NULL,
            `type` ENUM('payment', 'refund') DEFAULT NULL,
            `amount` DECIMAL( 10, 2 ) NOT NULL,
            PRIMARY KEY (`payson_embedded_order_transaction_id`)
        ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payson_embedded_order_recurring` (
            `payson_embedded_order_recurring_id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `order_recurring_id` INT(11) NOT NULL,
            `payson_embedded_subscription_id` VARCHAR(50),
            `payson_embedded_order_id` VARCHAR(50),
            `token` VARCHAR(50),
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            `next_payment` DATETIME NOT NULL,
            `trial_end` datetime DEFAULT NULL,
            `subscription_end` datetime DEFAULT NULL,
            `currency_code` CHAR(3) NOT NULL,
            `tax_class_id` VARCHAR(11) DEFAULT NULL,
            `total` DECIMAL( 10, 2 ) NOT NULL,
            `shipping_courier` VARCHAR(70) DEFAULT NULL,
            PRIMARY KEY (`payson_embedded_order_recurring_id`)
        ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
    }
}
?>