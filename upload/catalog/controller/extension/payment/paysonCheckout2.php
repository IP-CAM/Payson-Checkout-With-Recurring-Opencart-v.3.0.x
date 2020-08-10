<?php
class ControllerExtensionPaymentPaysonCheckout2 extends Controller {
    private $testMode;
    public $data = array();
    public $recurringCurrency = 'SEK';
    public $recurringEnable;
    
    //test 9

    const MODULE_VERSION = 'paysonEmbedded_with_recurring_1.0.0.1';

    function __construct($registry) {
        parent::__construct($registry);
        $this->testMode = ($this->config->get('payment_paysonCheckout2_mode') == 0);  
    }

    public function index() {
        $this->load->language('extension/payment/paysonCheckout2');
        $this->data['text_payson_comments'] = $this->language->get('text_payson_comments');
        $this->data['is_comments'] = $this->config->get('payment_paysonCheckout2_comments') == 1?1:0;
        $this->data['error_checkout_id'] = $this->language->get('error_checkout_id');
        $this->data['info_checkout'] = $this->language->get('info_checkout');
        $this->data['country_code'] = isset($this->session->data['payment_address']['iso_code_2'])? $this->session->data['payment_address']['iso_code_2'] : NULL;
        $this->data['customerIsLogged'] = $this->customer->isLogged() == 1 ? true : false ;
        
        $this->recurringEnable = $this->cart->hasRecurringProducts() > 0 ? true : false;
        
        $this->data['recurring'] = $this->recurringEnable == 1 ? $this->language->get('text_subscription') : 0;
        
        if($this->recurringEnable == 1 AND $this->session->data['currency'] != 'SEK'){
            $this->data['recurring_currency'] = $this->language->get('text_subscription_currency');
            $this->recurringEnable = false;
            return $this->load->view('extension/payment/paysonCheckout2', $this->data);  
        }

        //The customer return from Payson with status 'readyToPay' or 'denied'
        
        if (isset($this->request->get['snippet']) and $this->request->get['snippet'] !== Null) {
            $this->load->model('checkout/order'); 
            $this->response->redirect($this->url->link('extension/payment/paysonCheckout21', 'status=readyToPay&snippet='.$this->getSnippetUrl($this->request->get['snippet']), true));
        } else {
            $this->setupPurchaseData();
            return $this->load->view('extension/payment/paysonCheckout2', $this->data);
        }
    }

    public function getSnippetUrl($snippet) {
        $url = explode("url='", $snippet);
        $checkoutUrl = explode("'", $url[1]);
        return $checkoutUrl[0];
    }

    public function confirm() {
        if ($this->session->data['payment_method']['code'] == 'paysonCheckout2') {
            $this->setupPurchaseData();
        }
    }

    private function setupPurchaseData() {
        $this->load->language('extension/payment/paysonCheckout2');
        $this->load->model('checkout/order');

        $order_data = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $this->data['store_name'] = html_entity_decode($order_data['store_name'], ENT_QUOTES, 'UTF-8');
        $this->data['payson_comment'] = html_entity_decode($order_data['comment'], ENT_QUOTES, 'UTF-8');
        
        // URL:s
        if ($this->recurringEnable) {
            $this->data['ok_url'] = $this->url->link('extension/payment/paysonCheckout2/returnFromPayson&order_id=' . $this->session->data['order_id'].'&checkoutRef=PaysonRecurringSub'); 
            $this->data['ipn_url'] = $this->url->link('extension/payment/paysonCheckout2/paysonIpn&order_id=' . $this->session->data['order_id'].'&checkoutRef=PaysonRecurringSub');
            $this->data['checkout_url'] = $this->url->link('extension/payment/paysonCheckout2/returnFromPayson&order_id=' . $this->session->data['order_id'].'&checkoutRef=PaysonRecurringSub');
        }else{
            $this->data['ok_url'] = $this->url->link('extension/payment/paysonCheckout2/returnFromPayson&order_id=' . $this->session->data['order_id'].'&checkoutRef=PaysonCheckout');
            $this->data['ipn_url'] = $this->url->link('extension/payment/paysonCheckout2/paysonIpn&order_id=' . $this->session->data['order_id'].'&checkoutRef=PaysonCheckout');
            $this->data['checkout_url'] = $this->url->link('extension/payment/paysonCheckout2/returnFromPayson&order_id=' . $this->session->data['order_id'].'&checkoutRef=PaysonCheckout');
        }    
        
        $this->data['terms_url'] = $this->url->link('information/information/agree', 'information_id=5');
        // Order
        $this->data['order_id'] = $order_data['order_id'];
        $this->data['amount'] = $this->currency->format($order_data['total'] * 100, $order_data['currency_code'], $order_data['currency_value'], false) / 100;
        $this->data['currency_code'] = $order_data['currency_code'];
        $this->data['language_code'] = $order_data['language_code'];
        $this->data['salt'] = md5($this->config->get('payment_paysonCheckout2_secure_word')) . '1-' . $this->data['order_id'];
        // Customer info
        $this->data['sender_email'] = (int)$order_data['customer_id'] > 0 ? $order_data['email'] : '';
        $this->data['sender_first_name'] = $this->customer->isLogged()? html_entity_decode($order_data['payment_firstname'], ENT_QUOTES, 'UTF-8') : $this->session->data['payment_address']['firstname'];
        $this->data['sender_last_name'] = $this->customer->isLogged()? html_entity_decode($order_data['payment_lastname'], ENT_QUOTES, 'UTF-8') : $this->session->data['payment_address']['lastname'];
        $this->data['sender_telephone'] = html_entity_decode($order_data['telephone'], ENT_QUOTES, 'UTF-8');
        $this->data['sender_address'] = $this->customer->isLogged()? html_entity_decode($order_data['payment_address_1'], ENT_QUOTES, 'UTF-8'): $this->session->data['payment_address']['address_1'];
        $this->data['sender_postcode'] = $this->customer->isLogged() ? html_entity_decode($order_data['payment_postcode'], ENT_QUOTES, 'UTF-8'): $this->session->data['payment_address']['postcode'];
        $this->data['sender_city'] = $this->customer->isLogged()? html_entity_decode($order_data['payment_city'], ENT_QUOTES, 'UTF-8') : $this->session->data['payment_address']['city'];
        $this->data['sender_countrycode'] = $this->customer->isLogged()? html_entity_decode($order_data['payment_iso_code_2'], ENT_QUOTES, 'UTF-8'): $this->session->data['payment_address']['iso_code_2'];
       
        // Check if this cart has recurring products
        if ($this->recurringEnable) {
            $result = $this->initPaysonRecurringSubscription();
        } else {
            $result = $this->initPaysonCheckout();
        }
        
        $returnData = array();
        
        if ($result != NULL AND $result['status'] == "created") {
            $this->data['checkoutId'] = $result['id'];
            $this->data['width'] = (int) $this->config->get('payment_paysonCheckout2_iframe_size_width');
            $this->data['height'] = (int) $this->config->get('payment_paysonCheckout2_iframe_size_height');
            $this->data['width_type'] = $this->config->get('payment_paysonCheckout2_iframe_size_width_type');
            $this->data['height_type'] = $this->config->get('payment_paysonCheckout2_iframe_size_height_type');
            $this->data['testMode'] = !$this->testMode ? TRUE : FALSE;
            $this->data['snippet'] = $result['snippet'];
            $this->data['status'] = $result['status'];
        } else {
            $returnData["error"] = $this->language->get("text_payson_payment_error");
        }
    }

    // Initialize recurring subscription
    private function initPaysonRecurringSubscription() {
        require_once(DIR_SYSTEM . '../system/library/paysonpayments/include.php');
        $this->load->language('extension/payment/paysonCheckout2');

        $paysonApi = $this->getAPIInstanceMultiShop();
        $apiClient = new \Payson\Payments\RecurringSubscriptionClient($paysonApi);
        
        $paysonMerchant = $this->getMerchantData();
        
        $agreement = array(
            'currency' => 'sek'
        );
        
        $paysonGui = $this->getGuiData();
        
        $paysonCustomer = $this->getCustomerData();
        
        $checkoutData = array('merchant' => $paysonMerchant, 'agreement' => $agreement, 'gui' => $paysonGui, 'customer' => $paysonCustomer);
        
        try {
            if ($this->getCheckoutIdPayson($this->session->data['order_id']) != Null) {
                $checkout = $apiClient->get(array('id' => $this->getCheckoutIdPayson($this->session->data['order_id'])));
            }

            if ($this->getCheckoutIdPayson($this->session->data['order_id']) != Null AND $checkout['status'] == 'created') {
                $checkout = $apiClient->create($checkoutData);
                
            } else {
                $checkout = $apiClient->create($checkoutData);
            }

            if ($checkout['id'] != null) {
                $this->storePaymentResponseDatabase($checkout['id'], $this->session->data['order_id']);
            }

            return $checkout;
        } catch (Exception $e) {
            $this->writeToLog($e->getMessage());
            $this->load->model('extension/payment/paysonCheckout2');
        }
    }
    
    public function getGuiData() {
        $countries = $this->recurringEnable == 1 ? ['SE'] : '';
        $paysonGui = array(
             'colorScheme' => $this->config->get('payment_paysonCheckout2_color_scheme'),
             'locale' => $this->languagepaysonCheckout2() ,
             'verification' => $this->config->get('payment_paysonCheckout2_gui_verification'),
             'requestPhone' => (int) $this->config->get('payment_paysonCheckout2_request_phone'),
             'countries' => $countries,
         );

        return $paysonGui;
    }

    public function getCustomerData() {
        if (!$this->testMode) {
            $paysonCustomer = array(
                'firstName' => $this->data['sender_first_name'],
                'lastName' => $this->data['sender_last_name'],
                'email' => $this->data['sender_email'],
                'phone' => $this->data['sender_telephone'],
                'identityNumber' => '',
                'city' => $this->data['sender_city'],
                'countryCode' => $this->data['sender_countrycode'],
                'postalCode' => $this->data['sender_postcode'],
                'street' => $this->data['sender_address']
            );
        } else {
            $paysonCustomer = array(
                'firstName' => isset($this->data['sender_first_name']) ? $this->data['sender_first_name'] : 'Name',
                'lastName' => isset($this->data['sender_last_name']) ? $this->data['sender_last_name'] : 'Last name',
                'email' => isset($this->data['sender_email']) ? $this->data['sender_email'] : 'test@payson.se',
                'phone' => isset($this->data['sender_telephone']) ? $this->data['sender_telephone'] : '11111111',
                'identityNumber' => '4605092222',
                'city' => isset($this->data['sender_city']) ? $this->data['sender_city'] : 'Stockholm',
                'countryCode' => isset($this->data['sender_countrycode']) ? $this->data['sender_countrycode'] : 'SE',
                'postalCode' => '999 99',
                'street' => isset($this->data['sender_address']) ? $this->data['sender_address'] : 'Test address'
            );
        }
        
        return $paysonCustomer;
    }
    
    public function getMerchantData() {
        $paysonMerchant = array(
            'termsUri' => $this->data['terms_url'],
            'checkoutUri' => $this->data['checkout_url'],
            'confirmationUri' => $this->data['ok_url'],
            'notificationUri' => $this->data['ipn_url'],
            'integrationInfo' => ('PaysonCheckout_With_Recurring_Opencart-3-0|' . $this->config->get('payment_paysonCheckout2_modul_version') . '|' . VERSION),
            'reference' => $this->session->data['order_id']
        );
        return $paysonMerchant;
    }
    
    // Initialize Payson Checkout
    private function initPaysonCheckout() {
        require_once(DIR_SYSTEM . '../system/library/paysonpayments/include.php');
        $this->load->language('extension/payment/paysonCheckout2');

        $paysonApi = $this->getAPIInstanceMultiShop();
        $apiClient = new \Payson\Payments\CheckoutClient($paysonApi);
        
        $paysonMerchant = $this->getMerchantData();
        
        $paysonOrder = array(
            'currency' => $this->currencypaysonCheckout2(),
            'items' => $this->getOrderItems(),
        );
        
        $paysonGui = $this->getGuiData();
        
        
        $paysonCustomer = $this->getCustomerData();
        
        $checkoutData = array('merchant' => $paysonMerchant, 'order' => $paysonOrder, 'gui' => $paysonGui, 'customer' => $paysonCustomer);
        
        try {
            if ($this->getCheckoutIdPayson($this->session->data['order_id']) != Null) {
                $checkout = $apiClient->get(array('id' => $this->getCheckoutIdPayson($this->session->data['order_id'])));
            }

            if ($this->getCheckoutIdPayson($this->session->data['order_id']) != Null AND $checkout['status'] == 'created') {
                $checkout = $apiClient->create($checkoutData);
                
            } else {
                $checkout = $apiClient->create($checkoutData);
            }

            if ($checkout['id'] != null) {
                $this->storePaymentResponseDatabase($checkout['id'], $this->session->data['order_id']);
            }

            return $checkout;
        } catch (Exception $e) {
            $this->writeToLog($e->getMessage());
            $this->load->model('extension/payment/paysonCheckout2');
        }
    }

    // Returns from Payson after the transaction has ended.
    public function returnFromPayson() {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/paysonCheckout2');
        
        if(isset($_GET['address_data']) && $_GET['address_data'] != NULL){
            $payment_address_payson = json_decode($_GET['address_data'], true); 
            
            $country_info = $this->db->query("SELECT * FROM `" . DB_PREFIX . "country` WHERE `iso_code_2` = '" . $this->db->escape($payment_address_payson['CountryCode']) . "' AND `status` = '1' LIMIT 1")->row;
            
            $this->session->data['payment_address']['firstname'] = $payment_address_payson['FirstName'];
            $this->session->data['payment_address']['lastname'] = $payment_address_payson['LastName'];
            $this->session->data['payment_address']['address_1'] = $payment_address_payson['Street'];
            $this->session->data['payment_address']['city'] = $payment_address_payson['City'];
            $this->session->data['payment_address']['postcode'] = $payment_address_payson['PostalCode'];         
            $this->session->data['payment_address']['country'] = $country_info['name'];
            $this->session->data['payment_address']['country_id'] = $country_info['country_id'];
            $this->session->data['payment_address']['iso_code_2'] = $country_info['iso_code_2'];
        
            $this->session->data['shipping_address']['firstname'] = $payment_address_payson['FirstName'];
            $this->session->data['shipping_address']['lastname'] = $payment_address_payson['LastName'];
            $this->session->data['shipping_address']['address_1'] = $payment_address_payson['Street'];
            $this->session->data['shipping_address']['city'] = $payment_address_payson['City'];
            $this->session->data['shipping_address']['postcode'] = $payment_address_payson['PostalCode'];
            $this->session->data['shipping_address']['country'] = $country_info['name'];
            $this->session->data['shipping_address']['country_id'] = $country_info['country_id'];
            $this->session->data['shipping_address']['iso_code_2'] = $country_info['iso_code_2'];    
                        
            $this->response->redirect($this->url->link('checkout/checkout'));
        } 
        
        $paysonApi = $this->getAPIInstanceMultiShop();
        
        // Check if this order has recurring products
        if ($this->cart->hasRecurringProducts()) {
            $apiClient = new \Payson\Payments\RecurringSubscriptionClient($paysonApi);
        } else {
            $apiClient = new \Payson\Payments\CheckoutClient($paysonApi);
        }
        try {
            //Check if the checkoutid exist in the database.
            if (isset($this->request->get['order_id'])) {
                $orderId = $this->request->get['order_id'];
                $checkout = $apiClient->get(array('id' => $this->getCheckoutIdPayson($orderId)));

                //This row update database with info from the return object.
                $this->updatePaymentResponseDatabase($checkout, $this->getCheckoutIdPayson($orderId), 'returnCall');
                
                //Create the order order
                $this->handlePaymentDetails($checkout, $orderId, 'returnCall');
            } else {
                $this->writeToLog('orderid: ' . isset($this->request->get['order_id']) ? $this->request->get['order_id'] : $this->session->data['order_id']);
                $this->response->redirect($this->url->link('checkout/checkout'));
            }
        } catch (Exception $e) {
            $message = '<Payson Checkout - Return-Exception> ' . $e->getMessage();
            $this->writeToLog($message);
        }
    }

    // TODO: Check if this is a recurring or regular order
    function paysonIpn() {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/paysonCheckout2');
        
        $paysonApi = $this->getAPIInstanceMultiShop();

               // Check if this order has recurring products

        $apiClient = new \Payson\Payments\RecurringSubscriptionClient($paysonApi);

        if (isset($this->request->get['checkoutRef']) && $this->request->get['checkoutRef'] == 'PaysonCheckout') {
            //Query parameter ref is for this example only
            //Handle Payson Checkout 2.0 notification
            //Create the client
        $apiClient = new \Payson\Payments\CheckoutClient($paysonApi);    

        } elseif (isset($this->request->get['checkoutRef']) && $this->request->get['checkoutRef'] == 'PaysonRecurringSub') {
            //Query parameter ref is for this example only
            //Handle subscription notification
            //Create the client
            $apiClient = new \Payson\Payments\RecurringSubscriptionClient($paysonApi);           
            
        } elseif (isset($this->request->get['checkoutRef']) && $this->request->get['checkoutRef'] == 'PaysonPaymentSub') {
            //Query parameter ref is for this example only
            //Handle recurring payment notification
            //Create the client
            $apiClient = new \Payson\Payments\RecurringSubscriptionClient($paysonApi);
            
        } else {
            // Log to file writeToLog
            $message = '<Payson Checkout - IPN-Exception> No checkout ID found ';
            $this->writeToLog($message);
            
            // Return 500 since we got no ID to work with
            returnResponse(500);
        }    
       
        
        //$apiClient = new \Payson\Payments\CheckoutClient($paysonApi);    
        //$apiClient = new \Payson\Payments\RecurringSubscriptionClient($paysonApi);
        try {
                //Check if the checkoutid exist in the database.
                if (isset($this->request->get['checkout'])) {
                    $checkoutID = $this->request->get['checkout'];
                    $checkout = $apiClient->get(array('id' => $checkoutID));
                    //This row update database with info from the return object.
                    $this->updatePaymentResponseDatabase($checkout , $checkoutID, 'ipnCall');
                    //Create, canceled or dinaid the order.
                    $this->handlePaymentDetails($checkout, $this->request->get['order_id'], 'ipnCall');
                }
         
        } catch (Exception $e) {
            $message = '<Payson Checkout - IPN-Exception> ' . $e->getMessage();
            $this->writeToLog($message);
        }
    }

    /**
     * 
     * @param Checkout $checkout
     */
    private function handlePaymentDetails($checkout, $orderId = 0, $returnCallUrl = Null) {
        $this->load->language('extension/payment/paysonCheckout2');
        $this->load->model('checkout/order');
        $this->load->model('account/recurring');
        $this->load->model('extension/payment/paysonCheckout2');

        $orderIdTemp = $orderId ? $orderId : $this->session->data['order_id'];
        
        $order_info = $this->model_checkout_order->getOrder($orderIdTemp);
        if (!$order_info) {
            return false;
        }

        $succesfullStatus = null;
        
        switch ($checkout['status']) {
            case "readyToShip": // Payson Checkout payment OK
            case "customerSubscribed": // Subscription registration OK
                $succesfullStatus = $this->config->get('payment_paysonCheckout2_order_status_id');
                $comment = "";
                if($this->testMode){
                    $comment .= "Checkout ID: " . $checkout['id'] . "\n\n";
                    $comment .= "Payson status: " . $checkout['status'] . "\n\n";
                    $comment .= "Paid Order: " . $orderIdTemp;
                    $this->testMode ? $comment .= "\n\nPayment mode: " . 'TEST MODE' : '';
                }
                
                // Update order
                $this->db->query("UPDATE `" . DB_PREFIX . "order` SET
                                firstname  = '" . $checkout['customer']['firstName'] . "',
                                lastname  = '" . $checkout['customer']['lastName'] . "',
                                telephone  = '" . ($checkout['customer']['phone']?$checkout['customer']['phone']:'')."',
                                email               = '" . $checkout['customer']['email'] . "',
                                payment_firstname  = '" . $checkout['customer']['firstName'] . "',
                                payment_lastname   = '" . $checkout['customer']['lastName'] . "',
                                payment_address_1  = '" . $checkout['customer']['street'] . "',
                                payment_city       = '" . $checkout['customer']['city'] . "', 
                                payment_country    = '" . $checkout['customer']['countryCode'] . "',
                                payment_postcode   = '" . $checkout['customer']['postalCode'] . "', 
                                shipping_firstname  = '" . $checkout['customer']['firstName'] . "',
                                shipping_lastname   = '" . $checkout['customer']['lastName'] . "',
                                shipping_address_1  = '" . $checkout['customer']['street'] . "',
                                shipping_city       = '" . $checkout['customer']['city'] . "', 
                                shipping_country    = '" . $checkout['customer']['countryCode'] . "', 
                                shipping_postcode   = '" . $checkout['customer']['postalCode'] . "',            
                                payment_code        = 'paysonCheckout2'
                                WHERE order_id      = '" . $orderIdTemp . "'");
                
               
                $this->writeArrayToLog($comment);
                
                // Add order history
                //order_id, order_status_id, comment = '', notify = false, override = false
                $orderHistoryId = $this->model_checkout_order->addOrderHistory($orderIdTemp, $succesfullStatus, $comment, false, true);
                
                if ($this->cart->hasRecurringProducts()) {
                    
                    $paysonRecurringPayment = $this->createPaysonRecurringPayment($checkout['id']);
         
                    $payson_embedded_order_rec_id = $this->addOrderRec($order_info, $paysonRecurringPayment['subscriptionId'], $paysonRecurringPayment['id']);
                    $this->addTransaction($payson_embedded_order_rec_id, 'payment', $order_info);
                    $recurring_products = $this->cart->getRecurringProducts();

                    foreach ($recurring_products as $item) {
                        $this->model_extension_payment_paysonCheckout2->recurringPayment($item, $this->session->data['order_id'], $paysonRecurringPayment['subscriptionId'], $paysonRecurringPayment['id']);
                    }
                    //$this->model_extension_payment_paysonCheckout2->recurringPayment($recurring_products, $this->session->data['order_id'], $paysonRecurringPayment['subscriptionId'], $paysonRecurringPayment['id']);

                }
                
                $showReceiptPage = $this->config->get('payment_paysonCheckout2_receipt');
                if ($showReceiptPage == 1) {
                    $this->unsetData($orderIdTemp);
                    $this->response->redirect($this->url->link('extension/payment/paysonCheckout2/index', 'snippet=' . $checkout['snippet']));
                } else {
                    $this->response->redirect($this->url->link('checkout/success'));
                }
                break;
            case "readyToPay":
                if ($checkout['id'] != Null) {
                    //$this->response->redirect($this->url->link('checkout/cart'));
                    $this->response->redirect($this->url->link('extension/payment/paysonCheckout2/index', 'snippet=' . $checkout['snippet']));
                }
                break;
            case "denied":
                $this->paysonApiError($this->language->get('text_denied'));
                $this->updatePaymentResponseDatabase($checkout, $orderId, $returnCallUrl);
                $this->response->redirect($this->url->link('checkout/cart'));
                break;
            case "canceled":
                $this->updatePaymentResponseDatabase($checkout, $orderId, $returnCallUrl);
                $this->response->redirect($this->url->link('checkout/cart'));
                break;
            case "Expired":
                $this->writeToLog('Order was Expired by payson.&#10;Checkout status:&#9;&#9;' . $checkout['status'] . '&#10;Checkout id:&#9;&#9;&#9;&#9;' . $checkout['id'], $checkout);
                return false;
                break;
            default:
                $this->response->redirect($this->url->link('checkout/cart'));
        }
    }

    public function createPaysonRecurringPayment($subscriptionId = 0) {
        if ($subscriptionId) {
            $paysonApi = $this->getAPIInstanceMultiShop();
            $apiClient = new \Payson\Payments\RecurringPaymentClient($paysonApi);
            $orderId = $this->getOrderIdPaysonRecurring($subscriptionId);
          
            $order = array(
                'currency' => 'SEK', // TODO: Support for other currencies
                'items' => $this->getOrderItems($orderId),
            );
            $paymentData = array(
                'subscriptionid' => $subscriptionId, 
                'order' => $order, 
                'notificationUri' => $this->url->link('extension/payment/paysonCheckout2/paysonIpn&order_id=' . $orderId.'&checkoutRef=PaysonPaymentSub'),
                'description' => 'Order ' . $orderId);
            
            // Create recurring payment
            $recurringPayment = $apiClient->create($paymentData);

            
            
            if (isset($recurringPayment['status']) && $recurringPayment['status'] == 'readyToShip') {
                $order_info = $this->model_checkout_order->getOrder($orderId);
                $recurring_products = $this->cart->getRecurringProducts();
                return $recurringPayment;
            }
            return $recurringPayment;
        }
    }
    
    // Set up a cron job to access: index.php?route=extension/payment/paysonCheckout2/recurringCron
    public function cron() {
        if ($this->request->get['token'] == $this->config->get('payment_paysonCheckout2_secret_token')) {
           $this->load->model('extension/payment/paysonCheckout2');  
           $paysonApi = $this->getAPIInstanceMultiShop();
           $apiClient = new \Payson\Payments\RecurringPaymentClient($paysonApi); 
           $orders = $this->model_extension_payment_paysonCheckout2->cronPayment($apiClient);  
        }
    }
    
    public function getRecurringOrders($status = 1) {
        $recurringOrders = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_recurring` WHERE `status` = '" . (int)$status . "'")->rows;
        if (!$recurringOrders) {
            return false;
        }
        return $recurringOrders;
    }
    
     public function getRecurringTransaction($recurringOrderId = 0) {
        $recurringTransaction = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_recurring_transaction WHERE order_recurring_id = '" . (int)$recurringOrderId . "'")->rows;
        if (!$recurringTransaction) {
            return false;
        }
        return $recurringTransaction;
    }
    
    public function getRecurringOrder($recurringOrderId = 0) {
        $recurringOrder = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_recurring` WHERE `order_recurring_id` = '" . (int)$recurringOrderId . "'")->row;
        if (!$recurringOrder) {
            return false;
        }
        return $recurringOrder;
    }
    
    // Status - 1=Active, 2=Inactive, 3=Cancelled, 4=Suspended, 5=Expired, 6=Pending
    public function setRecurringOrderStatus($recurringOrderId, $statusId = 6) {
        $this->db->query("UPDATE `" . DB_PREFIX . "order_recurring` SET `status` = '" . (int)$statusId . "' WHERE `order_recurring_id` = '" . (int)$recurringOrderId . "'");
        return $this->db->getLastId();
    }

    public function createRecurringOrder($orderId, $description, $recurringProduct, $subscriptionId) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "order_recurring` SET `order_id` = '" . (int)$orderId . "', `date_added` = NOW(), `status` = 6, `product_id` = '" . (int)$recurringProduct['product_id'] . "', `product_name` = '" . $this->db->escape($recurringProduct['name']) . "', `product_quantity` = '" . $this->db->escape($recurringProduct['quantity']) . "', `recurring_id` = '" . (int)$recurringProduct['recurring']['recurring_id'] . "', `recurring_name` = '" . $this->db->escape($recurringProduct['recurring']['name']) . "', `recurring_description` = '" . $this->db->escape($description) . "', `recurring_frequency` = '" . $this->db->escape($recurringProduct['recurring']['frequency']) . "', `recurring_cycle` = '" . (int)$recurringProduct['recurring']['cycle'] . "', `recurring_duration` = '" . (int)$recurringProduct['recurring']['duration'] . "', `recurring_price` = '" . (float)$recurringProduct['recurring']['price'] . "', `trial` = '" . (int)$recurringProduct['recurring']['trial'] . "', `trial_frequency` = '" . $this->db->escape($recurringProduct['recurring']['trial_frequency']) . "', `trial_cycle` = '" . (int)$recurringProduct['recurring']['trial_cycle'] . "', `trial_duration` = '" . (int)$recurringProduct['recurring']['trial_duration'] . "', `trial_price` = '" . (float)$recurringProduct['recurring']['trial_price'] . "', `reference` = '$subscriptionId'");
        return $this->db->getLastId();
    }
    
    private function getCredentials() {
        $storesInShop = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "store`");
        $numberOfStores = $storesInShop->rows;
        $keys = array_keys($numberOfStores);
        //Since the store table do not contain the fist storeID this must be entered manualy in the $shopArray below
        $shopArray = array(0 => 0);
        for ($i = 0; $i < count($numberOfStores); $i++) {
            foreach ($numberOfStores[$keys[$i]] as $value) {
                array_push($shopArray, $value);
            }
        }
        return $shopArray;
    }

    private function getAPIInstanceMultiShop() {
        require_once(DIR_SYSTEM . '../system/library/paysonpayments/include.php');
        
        $apiUrl = \Payson\Payments\Transport\Connector::PROD_BASE_URL;
        
        $merchant = explode('##', $this->config->get('payment_paysonCheckout2_merchant_id'));
        $key = explode('##', $this->config->get('payment_paysonCheckout2_api_key'));
        $storeID = $this->config->get('config_store_id');
        $shopArray = $this->getCredentials();
        $multiStore = array_search($storeID, $shopArray);
        $agentId = $merchant[$multiStore];
        $apiKey = $key[$multiStore];
        
        if ($this->testMode) {
            $apiUrl = \Payson\Payments\Transport\Connector::TEST_BASE_URL;
//            if (strlen($agentId) < 1 && strlen($apiKey) < 1) {
//                $agentId = '4';
//                $apiKey = '2acab30d-fe50-426f-90d7-8c60a7eb31d4';
//            }
        }
        
        return \Payson\Payments\Transport\Connector::init($agentId, $apiKey, $apiUrl);
    }

    private function getOrderItems($ocOrderid = 0) {
        $orderitemslist = array();
        $this->load->language('extension/payment/paysonCheckout2');

        $orderId = $ocOrderid ? $ocOrderid : $this->session->data['order_id'];

        $order_data = $this->model_checkout_order->getOrder($this->session->data['order_id']);

         $query = "SELECT `product_id`, `name`, `model`, `price`, `quantity`, `tax` / `price` as 'tax_rate' FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = " . (int) $orderId . " UNION ALL SELECT 0, '" . $this->language->get('text_gift_card') . "', `code`, `amount`, '1', 0.00 FROM `" . DB_PREFIX . "order_voucher` WHERE `order_id` = " . (int) $orderId;
        $product_query = $this->db->query($query)->rows;

        foreach ($this->cart->getProducts() as $product) {
            //$productOptions = $this->db->query("SELECT name, value FROM " . DB_PREFIX . 'order_option WHERE order_id = ' . (int) $orderId . ' AND order_product_id=' . (int) $product['order_product_id'])->rows;
            $optionsArray = array();
            //$option_data = array();
            //if ($productOptions) {
                foreach ($product['option'] as $option) {
                    $optionsArray[] = $option['name'] . ': ' . $option['value'];
                }
            //}

            $tax_rate_product = '';
            foreach ($product_query as $product1) {
                if($product['product_id'] == $product1['product_id']){
                    $tax_rate_product = $product1['tax_rate'];
                }
            }

            $productTitle = $product['name'];

            if (!empty($optionsArray))
                $productTitle .= ' | ' . join('; ', $optionsArray);

            $productTitle = (strlen($productTitle) > 180 ? substr($productTitle, 0, strpos($productTitle, ' ', 180)) : $productTitle);
            $product_price = $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency'],  '', false);

            $orderitemslist[] = array(
                'name' => html_entity_decode($productTitle, ENT_QUOTES, 'UTF-8'),
                'unitPrice' => $product_price,
                'quantity' => $product['quantity'],
                'taxrate' => $tax_rate_product,
                'reference' => $product['model']
            );
        }

        $orderTotals = $this->getOrderTotals();
       
        foreach ($orderTotals as $orderTotal) {
            $orderTotalType = 'SERVICE';

            $orderTotalAmountTemp = 0;
            if((int)$orderTotal['sort_order'] >= (int)$this->config->get('total_tax_sort_order')){
              $orderTotalAmountTemp = $orderTotal['value'];  
            }else{
                $orderTotalAmountTemp = $orderTotal['value'] * (1 + ($orderTotal['lpa_tax'] > 0 ? $orderTotal['lpa_tax'] / 100 : 0));
            }
            
            //$orderTotalAmount = $this->currency->format($orderTotalAmountTemp, $order_data['currency_code'], $order_data['currency_value'], false) ;
            $orderTotalAmount = $this->currency->format($orderTotalAmountTemp, $this->session->data['currency'], '', false);
            
            if ($orderTotalAmount == null || $orderTotalAmount == 0) {
                continue;
            }

            if ($orderTotal['code'] == 'coupon') {
                $orderTotalType = 'DISCOUNT';
            }

            if ($orderTotal['code'] == 'voucher') {
                $orderTotalType = 'DISCOUNT';
            }

            if ($orderTotal['code'] == 'shipping') {
                $orderTotalType = 'SERVICE';
            }

            if($orderTotalAmount < 0) {
                $orderTotalType = 'DISCOUNT';
            }  

            $orderitemslist[] = array(
                'name' => html_entity_decode($orderTotal['title'], ENT_QUOTES, 'UTF-8'),
                'unitPrice' => $orderTotalAmount,
                'quantity' => 1,
                'taxrate' => ($orderTotal['lpa_tax']) / 100,
                'reference' => $orderTotal['code'],
                'type' => $orderTotalType
            );
        }
        
        $this->writeArrayToLog($orderitemslist, 'Items list: ');
        
        return $orderitemslist;
     
    }

    private function getOrderTotals() {
        // Totals
        $this->load->model('setting/extension');
        $totals = array();
        $taxes = $this->cart->getTaxes();
        $total = 0;

        // Because __call can not keep var references so we put them into an array.
        $total_data = array(
            'totals' => &$totals,
            'taxes' => &$taxes,
            'total' => &$total
        );

        $old_taxes = $taxes;
        $lpa_tax = array();

        $sort_order = array();

        $results = $this->model_setting_extension->getExtensions('total');

        foreach ($results as $key => $value) {
                if (isset($value['code'])) {
                        $code = $value['code'];
                } else {
                        $code = $value['key'];
                }

                $sort_order[$key] = $this->config->get('total_' . $code . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if (isset($result['code'])) {
                $code = $result['code'];
            } else {
                $code = $result['key'];
            }

            if ($this->config->get('total_' . $code . '_status')) {
                $this->load->model('extension/total/' . $code);

                // We have to put the totals in an array so that they pass by reference.
                $this->{'model_extension_total_' . $code}->getTotal($total_data);

                if (!empty($totals[count($totals) - 1]) && !isset($totals[count($totals) - 1]['code'])) {
                    $totals[count($totals) - 1]['code'] = $code;
                }

                $tax_difference = 0;

                foreach ($taxes as $tax_id => $value) {
                    if (isset($old_taxes[$tax_id])) {
                        $tax_difference += $value - $old_taxes[$tax_id];
                    } else {
                        $tax_difference += $value;
                    }
                }

                if ($tax_difference != 0) {
                    $lpa_tax[$code] = $tax_difference;
                }

                $old_taxes = $taxes;
            }
        }

        $sort_order = array();    

        foreach ($totals as $key => $value) {
            $sort_order[$key] = $value['sort_order'];

            if (isset($lpa_tax[$value['code']])) {
                $total_data['totals'][$key]['lpa_tax'] = abs($lpa_tax[$value['code']] / $value['value'] * 100);
            } else {
                $total_data['totals'][$key]['lpa_tax'] = 0;
            }
        }

        $ignoredTotals = $this->config->get('payment_paysonCheckout2_ignored_order_totals');
        
        if ($ignoredTotals == null)
            $ignoredTotals = 'sub_total, total, tax';

        $ignoredOrderTotals = array_map('trim', explode(',', $ignoredTotals));
        
        foreach ($totals as $key => $orderTotal) {
            if (in_array($orderTotal['code'], $ignoredOrderTotals)) {
                unset($totals[$key]);
            }
        }

        return $totals;
    }
    
    /** 
     * @param $checkout
     * @param checkout_id int $id
     */
    private function updatePaymentResponseDatabase($checkout, $id, $call = 'returnCall') {
        $this->db->query("UPDATE `" . DB_PREFIX . "payson_embedded_order` SET 
                        payment_status  = '" . $checkout['status'] . "',
                        updated                       = NOW(), 
                        sender_email                  = 'sender_email', 
                        currency_code                 = 'currency_code',
                        tracking_id                   = 'tracking_id',
                        type                          = 'type',
                        shippingAddress_name          = '" . $checkout['customer']['firstName'] . "', 
                        shippingAddress_lastname      = '" . $checkout['customer']['lastName'] . "', 
                        shippingAddress_street_ddress = '" . str_replace( array( '\'', '"', ',' , ';', '<', '>', '&' ), ' ', $checkout['customer']['street']) . "',
                        shippingAddress_postal_code   = '" . $checkout['customer']['postalCode'] . "',
                        shippingAddress_city          = '" . $checkout['customer']['city'] . "', 
                        shippingAddress_country       = '" . $checkout['customer']['countryCode'] . "'
            WHERE  checkout_id            = '" . $id . "'"
        );
    }

    private function storePaymentResponseDatabase($checkoutId, $orderId) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "payson_embedded_order SET 
                            payson_embedded_id  = '',
                            order_id            = '" . $orderId . "', 
                            checkout_id         = '" . $checkoutId . "', 
                            purchase_id         = '" . $checkoutId . "',
                            payment_status      = 'created', 
                            added               = NOW(), 
                            updated             = NOW()"
        );
    }

    private function getCheckoutIdPayson($order_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "payson_embedded_order` WHERE order_id = '" . (int) $order_id . "' ORDER BY `added` DESC");
        if ($query->num_rows && $query->row['checkout_id']) {
            if ($query->row['payment_status'] == ('created' || 'readyToPay')) {

                return $query->row['checkout_id'];
            } else {
                return null;
            }
        }
    }

    private function getPaysonEmbeddedOrder($order_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "payson_embedded_order` WHERE order_id = '" . (int) $order_id . "' ORDER BY `added` DESC");
        if($query->num_rows){
           return $query->row;
        } else {
            return null;
        } 
    }

    private function getOrderIdPayson($checkoutId) {
        $query = $this->db->query("SELECT `order_id` FROM `" . DB_PREFIX . "payson_embedded_order` WHERE checkout_id = '" . $this->db->escape($checkoutId) . "' AND payment_status = 'created'");
        if ($query->num_rows && $query->row['checkout_id']) {
            return $query->row['order_id'];
        } else {
            return null;
        }
    }
    
    private function getOrderIdPaysonRecurring($subscriptionid) {
        $query = $this->db->query("SELECT `order_id` FROM `" . DB_PREFIX . "payson_embedded_order` WHERE checkout_id = '" . $this->db->escape($subscriptionid) . "' AND payment_status = 'customerSubscribed'");
        if ($query->num_rows) {
            return $query->row['order_id'];
        } else {
            return null;
        }
    }

    private function unsetData($order_id) {

        $this->cart->clear();

        // Add to activity log
        $this->load->model('account/activity');

        if ($this->customer->isLogged()) {
            $activity_data = array(
                'customer_id' => $this->customer->getId(),
                'name' => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                'order_id' => $order_id
            );

            $this->model_account_activity->addActivity('order_account', $activity_data);
        }

        unset($this->session->data['shipping_method']);
        unset($this->session->data['shipping_methods']);
        unset($this->session->data['payment_method']);
        unset($this->session->data['payment_methods']);
        unset($this->session->data['guest']);
        unset($this->session->data['comment']);
        unset($this->session->data['order_id']);
        unset($this->session->data['coupon']);
        unset($this->session->data['reward']);
        unset($this->session->data['voucher']);
        unset($this->session->data['vouchers']);
        unset($this->session->data['totals']);
    }

    public function languagepaysonCheckout2() {
        $language = explode("-", $this->data['language_code']);
        switch (strtoupper($language[0])) {    
            case "SE":
            case "SV":
                return "SV";
            case "FI":
                return "FI";
            case "DA":
            case "DK":
                return "DA";
            case "NB":
            case "NO":
                return "NO";
            case "CA":
            case "GL":
            case "ES":
                return "ES";
            case "DE":
                return "DE";
            default:
                return "EN";
        }
    }

    public function currencypaysonCheckout2() {
        switch (strtoupper($this->data['currency_code'])) {
            case "SEK":
                return "SEK";
            default:
                return "EUR";
        }
    }

    /**
     * 
     * @param string $message
     * @param PaymentResponsObject $paymentResponsObject
     */
    function writeToLog($message, $paymentResponsObject = False) {
        $paymentDetailsFormat = "Payson reference:&#9;%s&#10;Correlation id:&#9;%s&#10;";
        if ($this->config->get('payment_paysonCheckout2_logg') == 1) {
            $this->log->write('PAYSON CHECKOUT WITH RECURRING&#10;' . $message . '&#10;' . ($paymentResponsObject != false ? sprintf($paymentDetailsFormat, $paymentResponsObject->status, $paymentResponsObject->id) : '') . $this->writeModuleInfoToLog());
        }
    }

    private function writeArrayToLog($array, $additionalInfo = "") {
        if ($this->config->get('payment_paysonCheckout2_logg') == 1) {
            $this->log->write('PAYSON CHECKOUT WITH RECURRING&#10;Additional information:&#9;' . $additionalInfo . '&#10;&#10;' . print_r($array, true) . '&#10;' . $this->writeModuleInfoToLog());
        }
    }

    private function writeModuleInfoToLog() {
        return 'Module version: ' . $this->config->get('payment_paysonCheckout2_modul_version') . '&#10;------------------------------------------------------------------------&#10;';
    }

    private function writeTextToLog($additionalInfo = "") {
        $module_version = 'Module version: ' . $this->config->get('payment_paysonCheckout2_modul_version') . '&#10;------------------------------------------------------------------------&#10;';
        $this->log->write('PAYSON CHECKOUT  WITH RECURRING' . $additionalInfo . '&#10;&#10;'.$module_version);
    }

    public function paysonComments(){
        $this->load->model('checkout/order');
        if(isset($this->request->get['payson_comments']) && !empty($this->request->get['payson_comments'])){
            $p_comments = $this->request->get['payson_comments'];
            if(is_string($p_comments)){
                $this->session->data['comment'] = $p_comments;
                $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
                comment  = '" . nl2br($p_comments) . "'
                WHERE order_id      = '" . $this->session->data['order_id'] . "'");  
            }
        }
    }

    public function paysonApiError($error) {
        $this->writeToLog('Run paysonApiError()');
        $this->load->language('extension/payment/paysonCheckout2');
        $error_code = '<html>
                            <head>
                                <script type="text/javascript"> 
                                    alert("' . $error . $this->language->get('text_payson_payment_method') . '");
                                    window.location="' . (HTTPS_SERVER . 'index.php?route=checkout/cart') . '";
                                </script>
                            </head>
                    </html>';
        echo ($error_code);
        exit;
    }

    // TODO: Check if this is a recurring or regular order
    public function notifyStatusToPayson($route, &$data){
        //require_once(DIR_SYSTEM . '../system/library/paysonpayments/include.php');
        $getCheckoutObject = $this->getPaysonEmbeddedOrder($data[0]);
        if($getCheckoutObject['checkout_id'] && ($getCheckoutObject['payment_status'] == 'readyToShip' || $getCheckoutObject['payment_status'] == 'shipped' || $getCheckoutObject['payment_status'] == 'paidToAccount'))
        {
            try
            {
                $additionalInfo = '';
                $paysonApi = $this->getAPIInstanceMultiShop();
                $apiClient = new \Payson\Payments\CheckoutClient($paysonApi);
                //$apiClient = new \Payson\Payments\RecurringPaymentClient($paysonApi);
                $checkout = $apiClient->get(array('id' => $getCheckoutObject['checkout_id']));
                
                if($data[1] == $this->config->get('payment_paysonCheckout2_order_status_shipped_id')) 
                { 
                    $checkout['status'] = 'shipped';
                    $checkout = $apiClient->update($checkout);
                }
                elseif($data[1] == $this->config->get('payment_paysonCheckout2_order_status_canceled_id')) 
                {
                    $checkout['status'] = 'canceled';
                    $checkout = $apiClient->update($checkout);
                }
                elseif($data[1] == $this->config->get('payment_paysonCheckout2_order_status_refunded_id')) 
                {
                    if ($checkout['status'] == 'readyToShip' || $checkout['status']== 'shipped' || $checkout['status'] == 'paidToAccount') 
                    {
                        if ($checkout['status'] == 'readyToShip') 
                        {
                            $checkout['status'] = 'shipped';
                            $checkout = $apiClient->update($checkout);
                        }
                        //$checkoutsList = $checkoutClient->listCheckouts($data);
                        foreach ($checkout['order']['items'] as &$item) 
                        {
                            $item['creditedAmount'] = ($item['totalPriceIncludingTax']);
                        }
                        unset($item);
                        $checkout = $apiClient->update($checkout);
                    }
                }
                else
                {
                    // Do nothing
                }
                $additionalInfo = '&#10;Notification is sent on and the order has been: &#9;'. $checkout['status']. '&#10;&#10; Order: ' . $data[0]. '&#10;&#10; checkout: ' . $checkout['id'] . '&#10;&#10; Payson-ref: '. $checkout['purchaseId'];
                $this->writeTextToLog($additionalInfo);
            } 
            catch (Exception $e) 
            {
                $message = '<Payson OpenCart Checkout 2.0 -  - Payson Order Status> &#10;' . $e->getMessage() . '&#10;'.  $e->getCode();
                $this->writeToLog($message);
            }
        }
        else
        {
            //Do nothing
        }
    }
    
    public function addOrderRec($order_info, $payson_embedded_subscription_id, $payson_embedded_order_id) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "payson_embedded_order_rec` SET `order_id` = '" . (int)$order_info['order_id'] . "', `payson_embedded_subscription_id` = '" . $this->db->escape($payson_embedded_subscription_id) . "', `payson_embedded_order_id` = '" . $this->db->escape($payson_embedded_order_id) . "', `date_added` = now(), `date_modified` = now(), `currency_code` = '" . $this->db->escape($order_info['currency_code']) . "', `total` = '" . $this->currency->format($order_info['total'], $order_info['currency_code'], false, false) . "'");

    return $this->db->getLastId();
    }
        
    public function addTransaction($payson_embedded_order_rec_id, $type, $order_info) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "payson_embedded_order_transaction` SET `payson_embedded_order_rec_id` = '" . (int)$payson_embedded_order_rec_id . "', `date_added` = now(), `type` = '" . $this->db->escape($type) . "', `amount` = '" . $this->currency->format($order_info['total'], $order_info['currency_code'], false, false) . "'");
    }
}
