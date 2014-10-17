<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE_AFL.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     GlobalExperts_Hypercharge
 * @copyright   Copyright (c) 2014 Global Experts GmbH (http://www.globalexperts.ch/)
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class GlobalExperts_Hypercharge_Model_Mobile extends Mage_Payment_Model_Method_Abstract {
    
    const REQUEST_TYPE_AUTH_CAPTURE         = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY            = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY         = 'CAPTURE_ONLY';
    const REQUEST_TYPE_CREDIT               = 'CREDIT';
    const REQUEST_TYPE_VOID                 = 'VOID';
    const REQUEST_TYPE_PRIOR_AUTH_CAPTURE   = 'PRIOR_AUTH_CAPTURE';
    const RESPONSE_CODE_APPROVED            = 1;
    const RESPONSE_CODE_DECLINED            = 2;
    const RESPONSE_CODE_ERROR               = 3;
    const RESPONSE_CODE_HELD                = 4;
    
    // unique internal payment method identifier
    // @var string [a-z0-9_]
    protected $_code = 'hypercharge_mobile';
    // Is this payment method a gateway (online auth/charge) ?
    protected $_isGateway = true;
    // Can authorize online?
    protected $_canAuthorize = false;    
    // Can capture partial amounts online?
    protected $_canCapturePartial = false;    
    // Is initialize needed?
    protected $_isInitializeNeeded = true;

    protected $_jsonTransactionType = "";
    
    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function initialize($paymentAction, $stateObject) {        
        // get order
        $order = $this->getInfoInstance()->getOrder();        
        $paymentMethod = get_class($order->getPayment()->getMethodInstance());
        if ($paymentMethod)
            $modelPayment = Mage::getModel($paymentMethod);
        else 
            $modelPayment = $this;       
        
        // check if payment amount is greater than 0
        $amount = $order->getBaseGrandTotal();
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('bithypercharge')->__('Invalid amount for authorization.'));
        }        
        $payment = $order->getPayment();
        Mage::log(var_export($modelPayment->_jsonTransactionType, true), null, "payment.log");
        // set transaction amount
        $payment->setAmount($amount);        
        // prepare hypercharge gateway channels for calling
        $hypercharge_channels = $this->getConfigChannels();
        // check if test mode
        $test = $modelPayment->getConfigData('test');        
        // set API call mode
        if ($test) {
            $mode = Hypercharge\Config::ENV_SANDBOX;
        } else {
            $mode = Hypercharge\Config::ENV_LIVE;
        }
        // start logging transaction
        Mage::helper('bithypercharge')->logger("\n" . str_repeat("*", 80) . "\n Initialize started");              
        // get order's billing info
        $billing = $order->getBillingAddress();
        // check if biliing data is set
        if (empty($billing)) {
            Mage::helper('bithypercharge')->logger("\n Billing address is not set");            
            $order->addStatusToHistory($order->getStatus(), 'Billing address is not set');
            Mage::throwException(Mage::helper('bithypercharge')->__('Billing address is not set'));            
        }
        // check if gateway channels are set
        if(!$hypercharge_channels) {
            Mage::helper('bithypercharge')->logger("\n Channels not configured");            
            $order->addStatusToHistory($order->getStatus(), 'Channels not configured');
            Mage::throwException(Mage::helper('bithypercharge')->__('Channels not configured'));            
        }
        // check if there is a channel for order currency
        $currency = $order->getBaseCurrencyCode();
        if(!array_key_exists($currency, $hypercharge_channels)) {
            Mage::helper('bithypercharge')->logger("\n Currency channel does not exist");
            Mage::getSingleton('core/session')->addError('Could not find currency channel in configuration');
            $order->addStatusToHistory($order->getStatus(), 'Could not find currency channel in configuration');
            Mage::throwException(Mage::helper('bithypercharge')->__('Could not find currency channel in configuration'));            
        }
        // set gateway data
        Hypercharge\Config::set(
                $hypercharge_channels[$currency]['login']
                ,$hypercharge_channels[$currency]['pass']
                ,$mode
            );
        $channelToken = $hypercharge_channels[$currency]['channel'];
        $amount = sprintf("%.02f", $order->getBaseGrandTotal()) * 100;
        // prepare initialize data

        $paymentData = array(
            'currency' => $currency
            , 'amount' => (int)$amount // in cents
            , 'transaction_id' => $order->getRealOrderId() //uniqid(time())
            , 'usage' => Mage::app()->getStore()->getName() . ' order authorization'
            , 'customer_email' => $order->getCustomerEmail()
            , 'customer_phone' => $billing->getTelephone()
            , 'notification_url' => Mage::getUrl('bit-hypercharge/notification/response', array('_secure' => true))
            , 'billing_address' => array(
                    'first_name' => $billing->getFirstname()
                , 'last_name' => $billing->getLastname()
                , 'address1' => $billing->getStreet(1)
                , 'zip_code' => $billing->getPostcode()
                , 'city' => $billing->getCity()
                , 'country' => $billing->getCountry()
                )
        );

        $transactionType = $modelPayment->_jsonTransactionType;
        if (isset($transactionType) && $transactionType != '') {
            $paymentData['transaction_types'] = array('transaction_type' => $transactionType);
        }

        if (in_array($paymentData['billing_address']['country'], array('US', 'CA'))) {
            $paymentData['billing_address']['state'] = $billing->getRegionCode();
        };

        $initialize = Hypercharge\Payment::mobile($paymentData);
        // check if authorization is approved
        if ($initialize->unique_id && $initialize->status == 'new' && !$initialize->error) {
            Mage::helper('bithypercharge')->logger("Initialize transaction approved");             
            // Your call to API for retrieving unique token for transaction at payment gateway
            $uniqueTransactionToken = $initialize->unique_id;
            $transactionToken = $initialize->transaction_id;
            $redirectUrl = $initialize->redirect_url;
            $cancelUrl = $initialize->cancel_url;            
            // Storing this token in our payment method additional information property
            // It will be later on used for checking payment status of transaction            
            $this->getInfoInstance()->setAdditionalInformation('unique_id', $uniqueTransactionToken);
            $this->getInfoInstance()->setAdditionalInformation('transaction_id', $transactionToken);
            $this->getInfoInstance()->setAdditionalInformation('redirect_url', $redirectUrl);
            $this->getInfoInstance()->setAdditionalInformation('cancel_url', $cancelUrl);
            // Also setting this token to quote payment object,
            // It will be used to generate payment submit url, when order gets placed
            // There is a small issue in Magento, that it uses quote payment, instead of order payment
            Mage::register('hyper_redirect_url', $redirectUrl);
            $this->getInfoInstance()->getOrder()/*->getQuote()*/->getPayment()->setAdditionalInformation('unique_id', $uniqueTransactionToken);
            $this->getInfoInstance()->getOrder()/*->getQuote()*/->getPayment()->setAdditionalInformation('transaction_id', $transactionToken);
            $this->getInfoInstance()->getOrder()/*->getQuote()*/->getPayment()->setAdditionalInformation('redirect_url', $redirectUrl);
            $this->getInfoInstance()->getOrder()/*->getQuote()*/->getPayment()->setAdditionalInformation('cancel_url', $cancelUrl);
            // Making our order invisible for a customer, until it gets paid or canceled.
            //$stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
            $stateObject->setState(Mage_Sales_Model_Order::STATE_NEW);
            $stateObject->setStatus(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
            return $this;
        // if not, display error and cancel order
        } elseif ($initialize->unique_id && $initialize->error) {
            $error = $initialize->error;
            $errMsg = $error->technical_message;
            Mage::helper('bithypercharge')->logger($error->status_code . ': ' . $errMsg);            
            $order->addStatusToHistory($order->getStatus(), $error->status_code . ': ' .$errMsg);
            Mage::throwException(Mage::helper('bithypercharge')->__($errMsg));
        // if no error message, display a generic error message
        } else {
            Mage::throwException(Mage::helper('bithypercharge')->__('Payment initialization error.'));
        }
    }
     
    /**
     * Returns the termination $xml or null on failure and updates the order data
     * @param array $post The $_POST string received in the notification
     * @return mixed 
     */
    public function hyperResponse($post) {        
        // prepare hypercharge gateway channels for calling
        $hypercharge_channels = $this->getConfigChannels();
        
        $timestamp = date('Y-m-d H:i:s', time() + 10800);
        Mage::helper('bithypercharge')->logger("\n" . str_repeat('*', 80) . "\n" . $timestamp . ' POST notification received');
        Mage::helper('bithypercharge')->logger("\n" . ' Post data: ' . print_r($post, true));

        // Check for existence of data
        if(!$hypercharge_channels || !$post || !is_array($post))
            return;
        if (!($post['payment_status'] && ($post['payment_status'] == 'error')))
            if(!array_key_exists('signature', $post) 
                || !array_key_exists('payment_transaction_channel_token', $post)
                || !array_key_exists('payment_transaction_unique_id', $post)
                || !array_key_exists('payment_transaction_id', $post)
                || !array_key_exists('payment_transaction_transaction_type', $post)
                || !array_key_exists('payment_unique_id', $post)
                || !array_key_exists('notification_type', $post))
                return;
        
        //Get the information from the POST variables
        $signature = $post['signature'];
        $trx_channel = $post['payment_transaction_channel_token'];
        $trx_id = $post['payment_transaction_unique_id'];
        $pay_trx_id = $post['payment_transaction_id'];
		$order_id = substr($pay_trx_id, 0, strpos($pay_trx_id, '-'));
        $trx_type = $post['payment_transaction_transaction_type'];
        $pay_id = $post['payment_unique_id'];
        $notification_type = $post['notification_type'];

        Mage::log(var_export($post, true), null, 'bbcc.log');
        $xml = $this->getTrxEndXml($pay_id);
        
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
            //$order_id = $order->getRealOrderId();
        } catch(Exception $e){
            Mage::helper('bithypercharge')->logger("\n" . $timestamp 
                    . ' Transaction could not be found in database - ' 
                    . $pay_trx_id . '. Error: ' . $e->getMessage());
            return $xml;
        }
        $paymentMethod = get_class($order->getPayment()->getMethodInstance());
        if ($paymentMethod)
            $modelPayment = Mage::getModel($paymentMethod);
        else 
            $modelPayment = $this;
        
        //Check if error or timeout
        if ($post['payment_status'] && ($post['payment_status'] == 'error')) {
            // cancel order
            $order->getPayment()
                ->setPreparedMessage('Payment authorized.')
                ->setAdditionalInformation(
                    'Transaction Status', 
                    'error')
                ->setIsTransactionClosed(0);
            $order->registerCancellation('Payment error.', false);
            $order->save();
            return $xml;
        }
        
        // check if test mode
        $test = $modelPayment->getConfigData('test');
        // set API call mode
        if ($test) {
            $mode = Hypercharge\Config::ENV_SANDBOX;
        } else {
            $mode = Hypercharge\Config::ENV_LIVE;
        }
        
        //Get configuration values here
        $channel = null;
        $allChannels = $this->getAllConfigChannels();
        foreach($allChannels as $ch)
            if($ch['channel'] == $trx_channel) {
                $channel = $ch;
                break;
            }
        //Check the signature of the request - optional
        if(!$channel) { // channel is bogus
            Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Invalid channel');
            return;
        }
        $pass = false;
        if(function_exists('hash'))// check if hash family is supported e.g. php > 5.1.2
            if(hash('sha512', $pay_id . $channel['pass']) == $signature)
                $pass = true;
            
        // set gateway data
        Hypercharge\Config::set(
                $channel['login']
                ,$channel['pass']
                ,$mode
            );
        
        if(!$pass && function_exists('hash')) { // the signature doesn't match 
            if(!$mode)
                Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Invalid signature. Signature: ' 
                    . $signature . ' Payment id: ' . $pay_id . ' Password: ' 
                    . $channel['pass']);            
            return $xml;
        }
                
        //Do a mobile reconcile        
        $response = $this->getReconcile($pay_id);
        //check if debit_sale and if status is 'pending_async'
        if ($trx_type == 'debit_sale' && $response->status == 'pending_async') {
            // pending_async is equivalent to approved
            //$response->status = 'approved';
        }
        
        if(!$response || ($response->status && $response->status == 'error')) {// Reconcile request failed
            Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Reconcile request failed');            
            return $xml;
        }        
        
        //Get the transaction type
        $transaction = null;
        if($response->payment_transaction)
            //we have a single transaction in response
            $transaction = $response->payment_transaction;
        else
            foreach($response->payment_transaction as $p)
                if($p['transaction_id'] == $pay_trx_id){
                    $transaction = $p;
                    break;
                }
        if(!$transaction && !($trx_type == 'debit_sale')){
            Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Transaction type could not be determined ');
            return $xml;
        }
        
        $is_sale = $transaction->transaction_type == 'sale' || $trx_type == 'debit_sale'; 
        $is_authorize = $transaction->transaction_type == 'authorize' || $transaction->transaction_type == 'authorize3d'; 
        
        // Set status as completed		
        if(in_array($response->status, array('approved', 'chargeback_reversed')) && $is_sale)
            $status = Mage_Sales_Model_Order::STATE_COMPLETE;

        // Set status as cancelled		
        if(in_array($response->status, array('chargebacked', 'voided')))
            $status = Mage_Sales_Model_Order::STATE_CANCELED;

        // Set status as pending
        if(in_array($response->status, array('pending', 'pending_async', 'pre_arbitrated')) || ($is_authorize && $response->status == 'approved'))
            $status = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT; 
        
        if ($transaction && $transaction->transaction_type)
            $transactionType = $transaction->transaction_type;
        else 
            $transactionType = $trx_type; 
        
        if ($transaction && $transaction->unique_id)
            $transactionId = $transaction->unique_id;
        else 
            $transactionId = $pay_id; 
        
        if ($transaction && $transaction->amount)
            $transactionAmount = $transaction->amount;
        else 
            $transactionAmount = $response->amount;

        //get wire_ref_id
        $wire_reference_id = "";
        if($transaction && $transaction->wire_reference_id) {
            $wire_reference_id = $transaction->wire_reference_id;
        }


        // Set some extra info
        $order->getPayment()
            ->setAdditionalInformation('Last Transaction ID', $transactionId)
            ->setAdditionalInformation('Transaction Type', $transactionType)
            ->setAdditionalInformation('Transaction Status', $response->status);

        if ($wire_reference_id) {
            $order->getPayment()->setAdditionalInformation('Wire Reference ID', $wire_reference_id);
        }

        try {
            switch($response->status) {
                case 'approved':
                case 'chargeback_reversed':
                    if ($is_sale) {
                        $order->getPayment()
                            ->setPreparedMessage('Payment authorized.')
                            ->setTransactionId($transactionId)
                            ->setAdditionalInformation('Last Transaction ID', $transactionId)
                            ->setAdditionalInformation('Transaction Type', $transactionType)
                            ->setAdditionalInformation('Transaction Status', $response->status)
                            ->setIsTransactionClosed(0)
                            ->registerAuthorizationNotification($transactionAmount / 100)
                            ->setIsTransactionApproved(true);
                        if (!$order->getEmailSent())
                            $order->sendNewOrderEmail();
                        $order->save();
                        $order->getPayment()->capture();
                        break;
                    }
                    $order->getPayment()
                        ->setTransactionId($transactionId)
                        ->setPreparedMessage('Transaction successful.')
                        ->setIsTransactionClosed(0)
                        ->registerCaptureNotification($transactionAmount / 100);
                    if (!$order->getEmailSent())
                        $order->sendNewOrderEmail();
                    $order->save();
                    break;
                case 'declined':
                    $order->registerCancellation('Payment was declined.', false)->save();
                    break;
                case 'chargebacked':
                    break;
                case 'pre_arbitrated':
                case 'refunded':
                    $order->getPayment()
                        ->setPreparedMessage('Payment was refunded.')
                        ->setTransactionId($transactionId)
                        ->setIsTransactionClosed($response->status == 'refunded' ? 1 : 0)
                        ->registerRefundNotification(-1 * ($transactionAmount / 100));
                    $order->save();
                    break;
                case 'voided':
                    $order->getPayment()
                        ->setPreparedMessage('Transaction voided.')
                        ->registerVoidNotification();
                    $order->save();
                    break;
                case 'pending':
                case 'pending_async':
                    $order->getPayment()
                        ->setPreparedMessage('Payment not complete yet.')
                        ->setTransactionId($transactionId)
                        ->setAdditionalInformation('Last Transaction ID', $transactionId)
                        ->setAdditionalInformation('Transaction Type', $transactionType)
                        ->setAdditionalInformation('Transaction Status', $response->status)
                        ->setIsTransactionClosed(0)
                        ->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_UPDATE, false);
                    if (!$order->getEmailSent()) {
                        $order->sendNewOrderEmail();
                    }
                    $order->save();
                    break;
            }
        } catch(Exception $e) {
            if(!$mode)
                Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Could not update order status: ' . $e->getMessage());            
            return $xml;
        }
        
        Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Order status updated to ' . $status . ' - transaction type: ' . $transactionType);        
        Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Transaction successfully finished');
        return $xml;
    }
    
    /**
     * Reconcile a transaction to get information about status
     * @param array $params
     * @return mixed 
     */
    public function getReconcile($pay_id) {        
        return Hypercharge\Payment::find($pay_id);        
    }
    
    function runCapture($pay_id) {        
        return Hypercharge\Payment::capture($pay_id);        
    }
    
    
    /**
     * Returns the xml for the gateway to stop sending notifications for a trx
     * @param int The unique ID of the transaction
     * @return string
     */
     public function getTrxEndXml($pay_id) {
        return '<?xml version="1.0" encoding="utf-8"?>' 
            . $this->paramsXML(array('unique_id' => $pay_id), 
            'notification_echo');
     }
     
     /**
     * Returns an xml based on an associative array
     * @param array $params
     * @param string $root
     * @param object $xml
     * @return string 
     */
    function paramsXML($params, $root = 'payment_transaction', $xml = null) {
        if(!is_array($params) || !$params || !is_string($root))
            return;
        
        if($xml === null) {
            $xml = new GlobalExperts_Hypercharge_Model_Api_XmlDoc();
            if(!$xml->loadString('<?xml version="1.0" encoding="utf-8"?>'
                . "<$root />")) {
                $this->setError($xml->getError());
                return;
            }
            $xml = $xml->document;
        }
        
        foreach($params as $key => $value)
            if(!is_array($value)) {
                $oldXml = $xml;
                $xml = $xml->addChild($key);
                $xml->setData(utf8_encode($value));
                $xml = $oldXml;
                unset($oldXml);
            } elseif($root == 'pay_payment' && $key == 'transaction_types') {
                // Add the root first
                $topXml = $xml;
                $xml = $xml->addChild($key);
                
                // Allow array notation for transaction types
                foreach($value as $v) {
                    $oldXml = $xml;
                    $xml = $xml->addChild('transaction_type');
                    $xml->setData(utf8_encode($v));
                    $xml = $oldXml;
                    unset($oldXml);                
                }
                $xml = $topXml;
                unset($topXml);
            } else
                self::paramsXML($value, $root, $xml->addChild($key));            
        return $xml->toString();       
    }
        
    /**
     * Return the values from the channels config
     * @return array 
     */
    public function getConfigChannels() {
        $path = 'payment/hypercharge_mobile/channels';        
        $channels = explode("\n", Mage::getStoreConfig($path));
        if(count($channels) < 1)
            return;
        
        $hypercharge_channels = array();
        foreach($channels as $ch) {
            $pieces = explode('|', $ch);
            if(count($pieces) != 4)
                continue;
            $hypercharge_channels[trim($pieces[0])] = array(
                'login' => trim($pieces[1]),
                'pass' => trim($pieces[2]),
                'channel' => trim($pieces[3])
            );
        }
        unset($pieces, $channels);
        
        return $hypercharge_channels;
    }

    /**
     * Return the values from the channels config
     * @return array 
     */
    public function getAllConfigChannels() {
        $path = 'payment/hypercharge_mobile/channels';        
        $channels = explode("\n", Mage::getStoreConfig($path));
        if(count($channels) < 1)
            return;
        
        $hypercharge_channels = array();
        foreach($channels as $ch) {
            $pieces = explode('|', $ch);
            if(count($pieces) != 4)
                continue;
            $hypercharge_channels[] = array(
                'login' => trim($pieces[1]),
                'pass' => trim($pieces[2]),
                'channel' => trim($pieces[3]),
                'currency' => trim($pieces[0])
            );
        }
        unset($pieces, $channels);
        
        return $hypercharge_channels;
    }
    
}
