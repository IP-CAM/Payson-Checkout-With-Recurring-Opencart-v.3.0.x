<?php
class ModelExtensionPaymentPaysonCheckout2 extends Model {

    private $currency_supported_by_p_direct = array('SEK', 'EUR');
    private $minimumAmountSEK = 6;
    private $minimumAmountEUR = 0.6;
    private $maxAmountSEK = 40000;
    private $maxAmountEUR = 3000;

    public function getMethod($address, $total) {
        $this->load->language('extension/payment/paysonCheckout2');

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get('payment_paysonCheckout2_geo_zone_id') . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");

        if ($this->config->get('payment_paysonCheckout2_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('payment_paysonCheckout2_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }
        if(strtoupper($this->config->get('config_currency')) == 'SEK' && ($total < $this->minimumAmountSEK || $total > $this->maxAmountSEK)){
            $status = false;
        }
        if(strtoupper($this->config->get('config_currency')) == 'EUR' && ($total < $this->minimumAmountEUR || $total > $this->maxAmountEUR)){
            $status = false;
        }
        if (!in_array(strtoupper($this->session->data['currency']), $this->currency_supported_by_p_direct)) {
            $status = false;
        }

        $paysonLogotype = '';
        if($this->config->get('payment_paysonCheckout2_logotype') == 3){
            $paysonLogotype =  $this->language->get('text_title') .' <img src="image/payment/paysonCheckout2/paysonCheckout2_P.png">';
        }else if($this->config->get('payment_paysonCheckout2_logotype') == 2){
           $paysonLogotype =  '<img src="image/payment/paysonCheckout2/paysonCheckout2_P.png"> '. $this->language->get('text_title');
        }else{
            $paysonLogotype = $this->language->get('text_title');
        }
        
        $method_data = array();

        if ($status) {
            $method_data = array(
                'code' => 'paysonCheckout2',
                'title' => $paysonLogotype,
                'terms' => '',
                'sort_order' => $this->config->get('payment_paysonCheckout2_sort_order')
            );
        }
        return $method_data;
    }
    
    public function recurringPayments() {
            $this->load->model('checkout/recurring');
            /*
             * Used by the checkout to state the module
             * supports recurring recurrings.
             */
            return true;
    }
    
    public function recurringPayment($item, $order_id, $payson_embedded_subscription_id, $payson_embedded_order_id) {
                                                                    
        $this->load->model('checkout/recurring');
        $this->load->model('extension/payment/paysonCheckout2');
        $this->load->language('extension/payment/paysonCheckout2');
        //trial information
        if ($item['recurring']['trial'] == 1) {
            $price = $item['recurring']['trial_price'];
            $trial_amt = $this->currency->format($this->tax->calculate($item['recurring']['trial_price'], $item['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], false, false) * $item['quantity'] . ' ' . $this->session->data['currency'];
            $trial_text = sprintf($this->language->get('text_trial'), $trial_amt, $item['recurring']['trial_cycle'], $item['recurring']['trial_frequency'], $item['recurring']['trial_duration']);
        } else {
            //$price = $item['recurring']['price'] * $item['quantity'];
            $price_transaction = $this->currency->format($this->tax->calculate($item['recurring']['price'], $item['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], false, false) * $item['quantity'] . ' ' . $this->session->data['currency'];
            $price = $this->currency->format($item['recurring']['price'], $this->session->data['currency'], false, false) * $item['quantity'] . ' ' . $this->session->data['currency'];
            $trial_text = '';
        }

        $recurring_amt = $this->currency->format($this->tax->calculate($item['recurring']['price'], $item['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'], false, false) * $item['quantity'] . ' ' . $this->session->data['currency'];
        $recurring_description = $trial_text . sprintf($this->language->get('text_recurring'), $recurring_amt, $item['recurring']['cycle'], $item['recurring']['frequency']);
                
        if ($item['recurring']['duration'] > 0) {
            $recurring_description .= sprintf($this->language->get('text_length'), $item['recurring']['duration']);
        }
                
        $item['recurring']['product_id'] = $item['product_id'];
        $item['recurring']['quantity'] = $item['quantity'];
                
        $order_recurring_id = $this->model_checkout_recurring->addRecurring($this->session->data['order_id'], $recurring_description, $item['recurring']);
        
        $this->model_checkout_recurring->editReference($order_recurring_id, $payson_embedded_subscription_id);

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
              
        $next_payment = new DateTime('now');
        $trial_end = new DateTime('now');
        $subscription_end = new DateTime('now');

        //If trial is enabled
        if ($item['recurring']['trial'] == 1){
            if ($item['recurring']['trial_duration'] != 0) {
                $next_payment = $this->calculateSchedule($item['recurring']['trial_frequency'], $next_payment, $item['recurring']['trial_cycle']);
                $trial_end = $this->calculateSchedule($item['recurring']['trial_frequency'], $trial_end, $item['recurring']['trial_cycle'] * $item['recurring']['trial_duration']);
            } else {
                $next_payment = $this->calculateSchedule($item['recurring']['trial_frequency'], $next_payment, $item['recurring']['trial_cycle']);
                $trial_end = new DateTime('0000-00-00');
            }

            if (date_format($trial_end, 'Y-m-d H:i') > date_format($subscription_end, 'Y-m-d H:i') && $item['recurring']['duration'] != 0) {
                $subscription_end = new DateTime(date_format($trial_end, 'Y-m-d H:i:s'));
                $subscription_end = $this->calculateSchedule($item['recurring']['frequency'], $subscription_end, $item['recurring']['cycle'] * $item['recurring']['duration']);
            } elseif (date_format($trial_end, 'Y-m-d H:i') == date_format($subscription_end, 'Y-m-d H:i')&& $item['recurring']['duration'] > 0) {
                $next_payment = $this->calculateSchedule($item['recurring']['frequency'], $next_payment, $item['recurring']['cycle']);
                $subscription_end = $this->calculateSchedule($item['recurring']['frequency'], $subscription_end, $item['recurring']['cycle'] * $item['recurring']['duration']);
            } elseif (date_format($trial_end, 'Y-m-d H:i') > date_format($subscription_end, 'Y-m-d H:i') && $item['recurring']['duration'] == 0) {
                $subscription_end = new DateTime('0000-00-00');
            } elseif (date_format($trial_end, 'Y-m-d H:i') == date_format($subscription_end, 'Y-m-d H:i') && $item['recurring']['duration'] == 0) {
                $next_payment = $this->calculateSchedule($item['recurring']['frequency'], $next_payment, $item['recurring']['cycle']);
                $subscription_end = new DateTime('0000-00-00');
            }
        }else{
            $next_payment = $this->calculateSchedule($item['recurring']['frequency'], $next_payment, $item['recurring']['cycle']);
            
            if (date_format($next_payment, 'Y-m-d H:i') > date_format($subscription_end, 'Y-m-d H:i') && $item['recurring']['duration'] != 0) {
                $subscription_end = new DateTime(date_format($trial_end, 'Y-m-d H:i:s'));
                $subscription_end = $this->calculateSchedule($item['recurring']['frequency'], $subscription_end, $item['recurring']['cycle'] * $item['recurring']['duration']);
            } elseif (date_format($next_payment, 'Y-m-d H:i') == date_format($subscription_end, 'Y-m-d H:i')&& $item['recurring']['duration'] > 0) {
                $next_payment = $this->calculateSchedule($item['recurring']['frequency'], $next_payment, $item['recurring']['cycle']);
                $subscription_end = $this->calculateSchedule($item['recurring']['frequency'], $subscription_end, $item['recurring']['cycle'] * $item['recurring']['duration']);
            } elseif (date_format($next_payment, 'Y-m-d H:i') > date_format($subscription_end, 'Y-m-d H:i') && $item['recurring']['duration'] == 0) {
                $subscription_end = new DateTime('0000-00-00');
            } elseif (date_format($next_payment, 'Y-m-d H:i') == date_format($subscription_end, 'Y-m-d H:i') && $item['recurring']['duration'] == 0) {
                $next_payment = $this->calculateSchedule($item['recurring']['frequency'], $next_payment, $item['recurring']['cycle']);
                $subscription_end = new DateTime('0000-00-00');
            }           
        }
                
        $this->addRecurringOrder($order_info, $payson_embedded_subscription_id, $payson_embedded_order_id, '', $price, $order_recurring_id, date_format($next_payment, 'Y-m-d H:i:s'), date_format($trial_end, 'Y-m-d H:i:s'), date_format($subscription_end, 'Y-m-d H:i:s'), $item['tax_class_id']);
                
        $this->addProfileTransaction($order_recurring_id, $payson_embedded_subscription_id, $price_transaction, 1);  
    }
        
    public function cronPayment($recurringPaymentClient) {
        require_once(DIR_SYSTEM . '../system/library/paysonpayments/include.php');
        $this->load->model('account/order');
        $this->load->model('checkout/order');
        $this->load->model('checkout/recurring');
        $this->load->model('account/customer');
        $this->load->language('extension/payment/paysonCheckout2');

        $profiles = $this->getProfiles();

        $cron_data = array();
        $i = 1;
        foreach ($profiles as $profile) {
            $recurring_order = $this->getRecurringOrder($profile['order_recurring_id']);

            if(!$recurring_order){
               // $this->log->write('No recurring_order found on Cron-call');
               
            }else{

            $profile['name'] = $profile['recurring_name'];
            $profile['tax_class_id'] = $recurring_order['tax_class_id'];
            

            $today = new DateTime('now');
            $unlimited = new DateTime('0000-00-00');     
            $next_payment = new DateTime($recurring_order['next_payment']);
            $trial_end = new DateTime($recurring_order['trial_end']);
            $subscription_end = new DateTime($recurring_order['subscription_end']);

            $order_info = $this->model_checkout_order->getOrder($profile['order_id']);
           
            $price_recurring_product = $this->currency->format($profile['recurring_price'], $order_info['currency_code'], false, false); //8
            $recurring_product_tax_rate = $this->tax->getTax($profile['recurring_price'], $profile['tax_class_id']);

            if (($today > $next_payment) && ($trial_end > $today || $trial_end == $unlimited)) {
                    $price = $this->currency->format($profile['trial_price'], $order_info['currency_code'], false, false);
                    $frequency = $profile['trial_frequency'];
                    $cycle = $profile['trial_cycle'];
            } elseif (($today > $next_payment) && ($subscription_end > $today || $subscription_end == $unlimited)) {
                    $price = $this->currency->format($profile['recurring_price'], $order_info['currency_code'], false, false);
                    $frequency = $profile['recurring_frequency'];
                    $cycle = $profile['recurring_cycle'];
            } else {
                    continue;
            }

                $shipping_total = $this->getShippingTotal($order_info['order_id']);
                $shipping_tax_rate = $this->tax->getTax($shipping_total['value'], $profile['tax_class_id']); 
                $shipping = $this->getOrderShipping($shipping_total, $shipping_tax_rate);
            
            $price = $this->currency->format($profile['recurring_price'], $order_info['currency_code'], false, false) ;
            $order_info['total'] = ($this->tax->calculate($price, $recurring_order['tax_class_id'], $this->config->get('config_tax')) * $profile['product_quantity']) + $shipping_total['value'] + $shipping_tax_rate; //$price;
            
            if($order_info['customer_id'] == 0){
               $customer_group_id = 1; 
            } else {
               $customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']); 
               $customer_group_id = $customer_info['customer_group_id'];
            }
            
            $order_info['customer_group_id'] =  $customer_group_id;
            $order_info['marketing_id'] = 0;
            $order_info['tracking'] = null;

            $new_order_id  = $this->model_checkout_order->addOrder($order_info);
            
            $products = $this->model_account_order->getOrderProducts($profile['order_id']);
            $profile['tax'] = $recurring_product_tax_rate;
            
            $this->addProductOrderRecurring($profile['order_id'], $new_order_id, $profile['product_id']);
                $this->addOrderTotalRecurring($profile['order_id'], $new_order_id, $price_recurring_product, $recurring_product_tax_rate, $profile['product_quantity'],  $shipping_total['value'], $shipping_tax_rate);

            $paysonRecurringPayment = $this->createSubPayson($recurringPaymentClient, $recurring_order['payson_embedded_subscription_id'], $new_order_id, $profile, $price_recurring_product + $recurring_product_tax_rate, $recurring_product_tax_rate, $shipping);

            if (isset($paysonRecurringPayment['status']) && $paysonRecurringPayment['status'] == 'readyToShip') {
                $comment = 'Payson Subscription ID : '. $paysonRecurringPayment['subscriptionId'] . "\n\n";
                $comment .= 'Payson Recurring Payment ID : ' . $paysonRecurringPayment['id'] . "\n\n";

                $this->model_checkout_order->addOrderHistory($new_order_id, $this->config->get('payment_paysonCheckout2_order_status_id'), $comment, false, false);
                $order_info2 = $this->model_checkout_order->getOrder($new_order_id);
                
                    $price_transaction = (($price_recurring_product + $recurring_product_tax_rate) * $profile['product_quantity']) + $shipping_total['value'] + $shipping_tax_rate;
                
                    $this->addProfileTransaction($profile['order_recurring_id'], $paysonRecurringPayment['subscriptionId'], $price_transaction, 1);

                $this->addRecurringOrder($order_info2, $paysonRecurringPayment['subscriptionId'], $paysonRecurringPayment['id'], '', $order_info2['total'], $profile['order_recurring_id'], date_format($next_payment, 'Y-m-d H:i:s'), date_format($trial_end, 'Y-m-d H:i:s'), date_format($subscription_end, 'Y-m-d H:i:s'), $recurring_order['tax_class_id']);       

                $next_payment = $this->calculateSchedule($frequency, $next_payment, $cycle);

                $next_payment = date_format($next_payment, 'Y-m-d H:i:s');

                $this->updateRecurringOrder($profile['order_recurring_id'], $next_payment);

            } else {

            }
            }
        }
    }
        
    public function getProfiles() {
        $sql = "
            SELECT `or`.order_recurring_id
            FROM `" . DB_PREFIX . "order_recurring` `or`
            JOIN `" . DB_PREFIX . "order` `o` USING(`order_id`)
            WHERE o.payment_code = 'paysonCheckout2'";

        $qry = $this->db->query($sql);

        $order_recurring = array();

        foreach ($qry->rows as $profile) {
            $order_recurring[] = $this->getProfile($profile['order_recurring_id']);
        }
        
        return $order_recurring;
    }
        
    public function getProfile($order_recurring_id) {
            $qry = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_recurring WHERE order_recurring_id = " . (int)$order_recurring_id);
            return $qry->row;
    }
        
    private function calculateSchedule($frequency, $next_payment, $cycle) {
        if ($frequency == 'semi_month') {
            $day = date_format($next_payment, 'd');
            $value = 15 - $day;
            $isEven = false;
            
            if ($cycle % 2 == 0) {
                
                $isEven = true;
            }

            $odd = ($cycle + 1) / 2;
            $plus_even = ($cycle / 2) + 1;
            $minus_even = $cycle / 2;

            if ($day == 1) {
                $odd = $odd - 1;
                $plus_even = $plus_even - 1;
                $day = 16;
            }

            if ($day <= 15 && $isEven) {
                $next_payment->modify('+' . $value . ' day');
                $next_payment->modify('+' . $minus_even . ' month');
            } elseif ($day <= 15) {
                $next_payment->modify('first day of this month');
                $next_payment->modify('+' . $odd . ' month');
            } elseif ($day > 15 && $isEven) {
                $next_payment->modify('first day of this month');
                $next_payment->modify('+' . $plus_even . ' month');
            } elseif ($day > 15) {
                $next_payment->modify('+' . $value . ' day');
                $next_payment->modify('+' . $odd . ' month');
            }       
        } else {
            
            $next_payment->modify('+' . $cycle . ' ' . $frequency);       
        }
        return $next_payment;
    }
        
    private function addProfileTransaction($order_recurring_id, $payson_embedded_subscription_id, $price, $type) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring_transaction` SET `order_recurring_id` = '" . (int)$order_recurring_id . "', `date_added` = CURRENT_TIMESTAMP, `amount` = '" . (float)$price . "', `type` = '" . (int)$type . "', `reference` = '" . $this->db->escape($payson_embedded_subscription_id) . "'");
            
            //return $this->db->getLastId();
    }
        
    private function getRecurringOrder($order_recurring_id) {     
       $qry = $this->db->query("SELECT * FROM " . DB_PREFIX . "payson_embedded_order_recurring WHERE order_recurring_id = '" . (int)$order_recurring_id . "' AND next_payment <= CURRENT_TIMESTAMP AND next_payment <= subscription_end");

            if ($qry->num_rows) {
                return $qry->row;
            } else {
                return 0;
            }
    }
        
    private function addRecurringOrder($order_info, $payson_embedded_subscription_id, $payson_embedded_order_id, $token, $price, $order_recurring_id, $next_payment, $trial_end, $subscription_end, $item_tax_class_id = null) {  
        $this->db->query("INSERT INTO `" . DB_PREFIX . "payson_embedded_order_recurring` SET `order_id` = '" . (int)$order_info['order_id'] . "', `order_recurring_id` = '" . (int)$order_recurring_id . "', `payson_embedded_subscription_id` = '" . $this->db->escape($payson_embedded_subscription_id) . "', `payson_embedded_order_id` = '" . $this->db->escape($payson_embedded_order_id) ."', `token` = '" . $this->db->escape($token) . "', `date_added` = CURRENT_TIMESTAMP, `date_modified` = CURRENT_TIMESTAMP, `next_payment` = '" . $this->db->escape($next_payment) . "', `trial_end` = '" . $this->db->escape($trial_end) . "', `subscription_end` = '" . $this->db->escape($subscription_end) . "', `currency_code` = '" . $this->db->escape($order_info['currency_code']) . "', `tax_class_id` = '" . (int)$item_tax_class_id . "', `total` = '" . $this->currency->format($price, $order_info['currency_code'], false, false) . "'");
            
            //return $this->db->getLastId();
    }
        
    private function updateRecurringOrder($order_recurring_id, $next_payment) {
            $this->db->query("UPDATE `" . DB_PREFIX . "payson_embedded_order_recurring` SET `next_payment` = '" . $this->db->escape($next_payment) . "', `date_modified` = CURRENT_TIMESTAMP WHERE `order_recurring_id` = '" . (int)$order_recurring_id . "'");
    }
    
    private function addProductOrderRecurring($order_id, $new_order_id, $product_id){
        $query = $this->db->query("INSERT INTO `" . DB_PREFIX . "order_product` (SELECT NULL, " . (int)$new_order_id . ", `product_id`, `name`, `model`, `quantity`, `price`, `total`, `tax`, `reward` FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = " . (int)$order_id  . " AND `product_id` = " . (int)$product_id  . ")");

        return $this->db->getLastId();
        //if ($this->isProductRecurringInOrder($product['product_id']) != false){}
    }
    
    private function addOrderTotalRecurring($order_id, $new_order_id, $price_recurring_product, $recurring_product_tax_rate, $product_quantity, $shipping, $shipping_tax_rate){
        $query = $this->db->query("SELECT NULL, " . (int)$new_order_id . ", `code`, `title`, `value`, `sort_order` FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = " . (int)$order_id);
  
        foreach ($query->rows as $total){
            if (strtoupper($total['code']) === 'TOTAL'){
                $total['value'] = (($price_recurring_product + $recurring_product_tax_rate) * $product_quantity) + $shipping + $shipping_tax_rate; /*5 FRAKT with tax*/
                $sql2 = "INSERT INTO `" . DB_PREFIX . "order_total` (`order_total_id`, `order_id`, `code`, `title`, `value`, `sort_order`) VALUES (null," .(int)$new_order_id. ", '" .$this->db->escape($total['code']). "', '" .$this->db->escape($total['title']). "'," .(float)$total['value']. "," .(int)$total['sort_order']. ")"; 
            $this->db->query($sql2); 
                
            }if (strtoupper($total['code']) === 'TAX'){
                $total['value'] = ($recurring_product_tax_rate * $product_quantity) + $shipping_tax_rate; /*1 FRAKT tax*/
                $sql2 = "INSERT INTO `" . DB_PREFIX . "order_total` (`order_total_id`, `order_id`, `code`, `title`, `value`, `sort_order`) VALUES (null," .(int)$new_order_id. ", '" .$this->db->escape($total['code']). "', '" .$this->db->escape($total['title']). "'," .(float)$total['value']. "," .(int)$total['sort_order']. ")";
            $this->db->query($sql2); 
                
            }if (strtoupper($total['code']) === 'SUB_TOTAL'){
                $total['value'] = $price_recurring_product * $product_quantity;
                $sql2 = "INSERT INTO `" . DB_PREFIX . "order_total` (`order_total_id`, `order_id`, `code`, `title`, `value`, `sort_order`) VALUES (null," .(int)$new_order_id. ", '" .$this->db->escape($total['code']). "', '" .$this->db->escape($total['title']). "'," .(float)$total['value']. "," .(int)$total['sort_order']. ")";
                $this->db->query($sql2); 
            }if (strtoupper($total['code']) === 'SHIPPING'){
                $sql2 = "INSERT INTO `" . DB_PREFIX . "order_total` (`order_total_id`, `order_id`, `code`, `title`, `value`, `sort_order`) VALUES (null," .(int)$new_order_id. ", '" .$this->db->escape($total['code']). "', '" .$this->db->escape($total['title']). "'," .(float)$total['value']. "," .(int)$total['sort_order']. ")";
                $this->db->query($sql2);
            }
        }
    } 
    
    private function createSubPayson($apiClient, $subscription_id, $order_id, $product_list, $price_recurring_product, $recurring_product_tax_rate, $shipping){  
        $orderId = $order_id;
        $subscriptionId = $subscription_id;
        $a = array();
        $a[] = array(
                   'name' => $product_list['product_name'],
                   'unitPrice' => $price_recurring_product,
                   'quantity' => $product_list['product_quantity'],
                   'taxRate' => $recurring_product_tax_rate/$product_list['recurring_price'],
                  );
       if($shipping['unitPrice'] > 0){
            $a[] = $shipping;
        }
        
        $order = array(
            'currency' => 'SEK', // TODO: Support for other currencies
            'items' => $a
        );

        $paymentData = array('subscriptionid' => $subscriptionId, 'order' => $order, 'notificationUri' => $this->url->link('extension/payment/paysonCheckout2/paysonIpn&order_id=' . $orderId.'&checkoutRef=PaysonPaymentSub'), 'description' => 'Order ' . $orderId);

        try {
            if (isset($subscriptionId)) {
                $recurringPayment = $apiClient->create($paymentData);
            } else {
                error_log('No subscription ID found!');
            }
   
        } catch(Exception $e) {
            // Print error message and error code
            error_log($subscriptionId);
            error_log(print_r($e->getMessage() . $e->getCode(), true));
            //continue;
        }

        return $recurringPayment;
    }
    
    public function getShippingTotal($order_id) {
        $sql = "SELECT * FROM `" . DB_PREFIX . "order_total` WHERE order_id=" . (int)$order_id . " AND `code`='shipping'";
        $res = $this->db->query($sql)->row;

        return $res;
    } 
    
    public function getOrderShipping($shipping_total, $shipping_tax_rate){        
        $shipping = array(
                   'name' => $shipping_total['title'],
                   'unitPrice' => $shipping_total['value'] + $shipping_tax_rate,
                   'quantity' => 1,
                   'taxRate' => $shipping_tax_rate/($shipping_total['value'] == 0 ? 1 : $shipping_total['value']),
                  );
        
        return $shipping;
    }
}
