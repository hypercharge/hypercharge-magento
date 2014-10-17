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
class GlobalExperts_Hypercharge_Model_Checkout extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'wpf';
    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_formBlockType = 'bithypercharge/form_wpf';
    protected $_infoBlockType = 'bithypercharge/info_wpf';
    protected $_transactionType;
    protected $_order;

    public function capture(Varien_Object $payment, $amount) {
        $order = $payment->getOrder();
        $paymentMethod = get_class($order->getPayment()->getMethodInstance());
        if ($paymentMethod)
            $modelPayment = Mage::getModel($paymentMethod);
        else
            $modelPayment = $this;

        $gate = Mage::helper('bithypercharge/gateway');
        $hypercharge_channels = $this->getConfigChannels();
        $mode = !$modelPayment->getConfigData('mode');
        $ttl = $modelPayment->getConfigData('ttl');

        if (!$mode)
            Mage::helper('bithypercharge')->logger("\n" . str_repeat("*", 80)
                    . "\n Capture transaction started");

        if (!$hypercharge_channels) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Channels not configured");
            Mage::getSingleton('core/session')
                    ->addError('Channels not configured');
            $order->addStatusToHistory($order->getStatus(), 'Channels not configured');
            return $this;
        }

        $currency = $order->getBaseCurrencyCode();
        if (!array_key_exists($currency, $hypercharge_channels)) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Currency channel does not exist");
            Mage::getSingleton('core/session')
                    ->addError('Could not find currency channel in configuration');
            $order->addStatusToHistory($order->getStatus(), 'Could not find currency channel in configuration');
            return $this;
        }

        $gate->setUsername($hypercharge_channels[$currency]['login']);
        $gate->setPassword($hypercharge_channels[$currency]['pass']);
        $gate->setChannel($hypercharge_channels[$currency]['channel']);
        $gate->setMode('live');
        if (!$mode)
            $gate->setMode('test');

        $paymentData = array(
            'transaction_id' => uniqid(time()),
            'usage' => 'Capturing a previously authorized payment',
            'amount' => sprintf("%.02f", $order->getBaseGrandTotal()) * 100,
            'currency' => $currency,
            'remote_ip' => Mage::app()->getRequest()->getClientIp(false),
            'reference_id' => str_replace('-capture', '', $payment->getTransactionId())
        );
        if (!$response = $gate->capture($paymentData)) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Capture payment failed. Capture data sent:\n"
                        . print_r('<?xml version="1.0" encoding="utf-8"?>'
                                . $gate->paramsXML($paymentData), true));
            Mage::getSingleton('core/session')
                    ->addError('Could not capture the payment');
            $order->addStatusToHistory($order->getStatus(), 'Could not capture the payment');
            return $this;
        }

        if ($response['status'] == 'error')
            if ($response['mode'] == 'live') {
                Mage::getSingleton('core/session')
                        ->addError($response['message']);
                $order->addStatusToHistory($order->getStatus(), $response['message']);
                return $this;
            } else {
                Mage::helper('bithypercharge')->logger("\n Gateway returned error: "
                        . $response['technical_message']);
                Mage::helper('bithypercharge')->logger("\nCapture data sent:\n"
                        . print_r('<?xml version="1.0" encoding="utf-8"?>'
                                . $gate->paramsXML($paymentData), true));
                Mage::getSingleton('core/session')
                        ->addError('Gateway returned error: '
                                . $response['technical_message']);
                $order->addStatusToHistory($order->getStatus(), 'Gateway returned error: '
                        . $response['technical_message']);
                return $this;
            }
        // Everything is ok, wrap this up
        $payment->setPreparedMessage('Payment captured')
                ->setTransactionId($response['unique_id'])
                ->setIsTransactionClosed(0)
                ->setIsTransactionApproved(true);
        if (!$order->getEmailSent())
            $order->sendNewOrderEmail();
        $order->save();
        return $this;
    }

    public function void(Varien_Object $payment) {
        $order = $payment->getOrder();
        $paymentMethod = get_class($order->getPayment()->getMethodInstance());
        if ($paymentMethod)
            $modelPayment = Mage::getModel($paymentMethod);
        else
            $modelPayment = $this;

        $gate = Mage::helper('bithypercharge/gateway');
        $hypercharge_channels = $this->getConfigChannels();
        $mode = !$modelPayment->getConfigData('mode');
        $ttl = $modelPayment->getConfigData('ttl');

        if (!$mode)
            Mage::helper('bithypercharge')->logger("\n" . str_repeat("*", 80)
                    . "\n Void transaction started");

        if (!$hypercharge_channels) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Channels not configured");
            Mage::getSingleton('core/session')
                    ->addError('Channels not configured');
            $order->addStatusToHistory($order->getStatus(), 'Channels not configured');
            return $this;
        }

        $currency = $order->getBaseCurrencyCode();
        if (!array_key_exists($currency, $hypercharge_channels)) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Currency channel does not exist");
            Mage::getSingleton('core/session')
                    ->addError('Could not find currency channel in configuration');
            $order->addStatusToHistory($order->getStatus(), 'Could not find currency channel in configuration');
            return $this;
        }

        $gate->setUsername($hypercharge_channels[$currency]['login']);
        $gate->setPassword($hypercharge_channels[$currency]['pass']);
        $gate->setChannel($hypercharge_channels[$currency]['channel']);
        $gate->setMode('live');
        if (!$mode)
            $gate->setMode('test');

        $paymentData = array(
            'transaction_id' => uniqid(time()),
            'usage' => 'Voiding a previous payment',
            'remote_ip' => Mage::app()->getRequest()->getClientIp(false),
            'reference_id' => str_replace('-void', '', $payment->getTransactionId())
        );
        if (!$response = $gate->void($paymentData)) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Voiding payment failed. Voiding data sent:\n"
                        . print_r('<?xml version="1.0" encoding="utf-8"?>'
                                . $gate->paramsXML($paymentData), true));
            Mage::getSingleton('core/session')
                    ->addError('Could not void the payment');
            $order->addStatusToHistory($order->getStatus(), 'Could not void the payment');
            return $this;
        }

        if ($response['status'] == 'error')
            if ($response['mode'] == 'live') {
                Mage::getSingleton('core/session')
                        ->addError($response['message']);
                $order->addStatusToHistory($order->getStatus(), $response['message']);
                return $this;
            } else {
                Mage::helper('bithypercharge')->logger("\n Gateway returned error: "
                        . $response['technical_message']);
                Mage::getSingleton('core/session')
                        ->addError('Gateway returned error: '
                                . $response['technical_message']);
                $order->addStatusToHistory($order->getStatus(), 'Gateway returned error: '
                        . $response['technical_message']);
                return $this;
            }
        // Everything is ok, wrap this up
        $payment->setPreparedMessage('Transaction voided')
                ->registerVoidNotification();
        $order->save();
        return $this;
    }

    public function cancel(Varien_Object $payment) {
        $this->void($payment);
        $payment->getOrder()
                ->registerCancellation('Order cancelled', false)->save();
        return $this;
    }

    public function refund(Varien_Object $payment, $amount) {
        $order = $payment->getOrder();
        $paymentMethod = get_class($order->getPayment()->getMethodInstance());
        if ($paymentMethod)
            $modelPayment = Mage::getModel($paymentMethod);
        else
            $modelPayment = $this;

        $gate = Mage::helper('bithypercharge/gateway');
        $hypercharge_channels = $this->getConfigChannels();
        $mode = !$modelPayment->getConfigData('mode');
        $ttl = $modelPayment->getConfigData('ttl');

        if (!$mode)
            Mage::helper('bithypercharge')->logger("\n" . str_repeat("*", 80)
                    . "\n Refund transaction started");

        if (!$hypercharge_channels) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Channels not configured");
            Mage::getSingleton('core/session')
                    ->addError('Channels not configured');
            $order->addStatusToHistory($order->getStatus(), 'Channels not configured');
            return $this;
        }

        $currency = $order->getBaseCurrencyCode();
        if (!array_key_exists($currency, $hypercharge_channels)) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Currency channel does not exist");
            Mage::getSingleton('core/session')
                    ->addError('Could not find currency channel in configuration');
            $order->addStatusToHistory($order->getStatus(), 'Could not find currency channel in configuration');
            return $this;
        }

        $gate->setUsername($hypercharge_channels[$currency]['login']);
        $gate->setPassword($hypercharge_channels[$currency]['pass']);
        $gate->setChannel($hypercharge_channels[$currency]['channel']);
        $gate->setMode('live');
        if (!$mode)
            $gate->setMode('test');

        $paymentData = array(
            'transaction_id' => uniqid(time()),
            'usage' => 'Refunding a previous payment',
            'amount' => sprintf("%.02f", $order->getBaseGrandTotal()) * 100,
            'currency' => $currency,
            'remote_ip' => Mage::app()->getRequest()->getClientIp(false),
            'reference_id' => str_replace('-refund', '', $payment->getTransactionId())
        );
        if (!$response = $gate->refund($paymentData)) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n Refunding payment failed. Refund data sent:\n"
                        . print_r('<?xml version="1.0" encoding="utf-8"?>'
                                . $gate->paramsXML($paymentData), true));
            Mage::getSingleton('core/session')
                    ->addError('Could not refund the payment');
            $order->addStatusToHistory($order->getStatus(), 'Could not refund the payment');
            return $this;
        }

        if ($response['status'] == 'error')
            if ($response['mode'] == 'live') {
                Mage::getSingleton('core/session')
                        ->addError($response['message']);
                $order->addStatusToHistory($order->getStatus(), $response['message']);
                return $this;
            } else {
                Mage::helper('bithypercharge')->logger("\n Gateway returned error: "
                        . $response['technical_message']);
                Mage::getSingleton('core/session')
                        ->addError('Gateway returned error: '
                                . $response['technical_message']);
                $order->addStatusToHistory($order->getStatus(), 'Gateway returned error: '
                        . $response['technical_message']);
                return $this;
            }
        // Everything is ok, wrap this up
        $payment->setPreparedMessage('Payment was refunded')
                ->setTransactionId($response['unique_id'])
                ->setIsTransactionClosed(1)
                ->registerRefundNotification(
                        -1 * ($response['amount'] / 100));
        $order->save();
        return $this;
    }

    /**
     * Return the values from the channels config
     * @return array 
     */
    public function getConfigChannels() {
        $path = 'payment/hypercharge_mobile/channels';
        $channels = explode("\n", Mage::getStoreConfig($path));
        if (count($channels) < 1)
            return;

        $hypercharge_channels = array();
        foreach ($channels as $ch) {
            $pieces = explode('|', $ch);
            if (count($pieces) != 4)
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
        if (count($channels) < 1)
            return;

        $hypercharge_channels = array();
        foreach ($channels as $ch) {
            $pieces = explode('|', $ch);
            if (count($pieces) != 4)
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

    /**
     * Returns the redirect URL from a wpf creation action
     * @return string 
     */
    public function getRedirectUrl() {
        $order = $this->getQuote();
        $paymentMethod = get_class($order->getPayment()->getMethodInstance());

        $modelPayment = ($paymentMethod) ? Mage::getModel($paymentMethod) : $this;
        $hypercharge_channels = $this->getConfigChannels();

        // set API call mode
        $mode = !$modelPayment->getConfigData('mode');
        if ($mode) {
            $mode = Hypercharge\Config::ENV_LIVE;
        } else {
            $mode = Hypercharge\Config::ENV_SANDBOX;
        }

        $ttl = $modelPayment->getConfigData('ttl');

        if (!$hypercharge_channels) {
            Mage::throwException('Payment channels not configured correctly');
            return;
        }

        if (!$order) {
            Mage::throwException('Could not retrieve order information');
            return;
        }

        $currency = $order->getBaseCurrencyCode();
        if (!array_key_exists($currency, $hypercharge_channels)) {
            Mage::throwException(
                'The merchant doesn\'t accept payments for this currency');
            return;
        }


        Hypercharge\Config::set(
            $hypercharge_channels[$currency]['login']
            ,$hypercharge_channels[$currency]['pass']
            ,$mode
        );


        $currency = $order->getBaseCurrencyCode();
        if (!array_key_exists($currency, $hypercharge_channels)) {
            Mage::throwException(
                    'The merchant doesn\'t accept payments for this currency');
            return;
        }

        // is editable by user
        $editableByUser = (bool) $modelPayment->getConfigData('check_address');
        $amount = sprintf("%.02f", $order->getBaseGrandTotal()) * 100;
        $billing = $order->getBillingAddress();

        $paymentData = array(
            'transaction_id' => $order->getRealOrderId(),
            'usage' => 'Hypercharge Web Payment Form transaction',
            'description' => 'Order number ' . $order->getRealOrderId(),
            'amount' => (int) $amount,
            'currency' => $currency,
            'editable_by_user' => $editableByUser,
            'customer_email' => $order->getCustomerEmail(),
            'customer_phone' => $billing->getTelephone(),
            'notification_url' => Mage::getUrl('bit-hypercharge/wpfnotification/wpf', array('_secure' => true)),
            'return_success_url' => Mage::getUrl('bit-hypercharge/wpfredirect/success', array('_secure' => true)),
            'return_failure_url' => Mage::getUrl('bit-hypercharge/wpfredirect/failure', array('_secure' => true)),
            'return_cancel_url' => Mage::getUrl('bit-hypercharge/wpfredirect/cancel', array('_secure' => true)),
            'billing_address' => array(
                'first_name' => utf8_decode($billing->getFirstname()),
                'last_name' => utf8_decode($billing->getLastname()),
                'address1' => utf8_decode(trim(str_replace("\n", ' ', trim(implode(' ', $billing->getStreet()))))),
                'city' => utf8_decode($billing->getCity()),
                'country' => utf8_decode($billing->getCountryId()),
                'zip_code' => utf8_decode($billing->getData('postcode')),
            ),
            'shipping_address' => array(
                'first_name' => utf8_decode($billing->getFirstname()),
                'last_name' => utf8_decode($billing->getLastname()),
                'address1' => utf8_decode(trim(str_replace("\n", ' ', trim(implode(' ', $billing->getStreet()))))),
                'city' => utf8_decode($billing->getCity()),
                'country' => utf8_decode($billing->getCountryId()),
                'zip_code' => utf8_decode($billing->getData('postcode')),
            ),
        );

        $paymentData['transaction_types'] = array(
            'transaction_type' => $modelPayment->getTransactionType()
        );

        if (in_array($paymentData['billing_address']['country'], array('US', 'CA'))) {
            $paymentData['billing_address']['state'] = $billing->getRegionCode();
            $paymentData['shipping_address']['state'] = $billing->getRegionCode();
        }

        if (ctype_digit($ttl) && ($ttl * 60) >= 300 && $ttl <= 86400)
            $paymentData['ttl'] = $ttl;

        // Log some information        
        Mage::helper('bithypercharge')->logger("WPF initiated:\n"
                . "Mode:" . print_r($mode, true)
                . "\nRequest string:\n"
                . var_export($paymentData, true));



        // Instantiate payment method to log the response error
        $paymentInst = $order->getPayment()->getMethodInstance();

        try {
            $payment = Hypercharge\Payment::wpf($paymentData);

            if ($payment->status == 'error') {
                $order->addStatusToHistory($order->getStatus(), $payment->message);
                $order->save();
                Mage::getSingleton('core/session')->addError($mode ? $payment->message : $payment->technical_message);
                $paymentInst->setTransactionId($paymentData['transaction_id']);
                return;
            }

            // And some information aggregation before we leave

            if( $payment->shouldRedirect()) {
                // ok, WPF session created.
                Mage::helper('bithypercharge')->logger('Customer redirected to Hypercharge');
                $order->addStatusToHistory($order->getStatus(), Mage::helper('bithypercharge')->__('Customer was redirected to Hypercharge.'));
                $order->save();
                $paymentInst->setLastTransactionId($paymentData['transaction_id']);
                Mage::getSingleton('core/session')->setHyperRedirectUrl($payment->redirect_url);
                if ($modelPayment->getConfigData('use_iframe') == 1) {
                    return Mage::getUrl('bit-hypercharge/wpfredirect/hypercharge');
                } elseif ($modelPayment->getConfigData('use_iframe') == 2) {
                    Mage::getSingleton('core/session')->setHyperReviewRedirect(1);
                    return false;
                } else {
                    return $payment->redirect_url;
                }
            } elseif($payment->isPersistentInHypercharge()) {
                Mage::helper('bithypercharge')->logger($payment->message . " " . $payment->technical_message);
                // payment has been created in hypercharge but something went wrong.
                if ($payment->status == 'error') {
                    $order->addStatusToHistory($order->getStatus(), $payment->message);
                    $order->save();
                    Mage::getSingleton('core/session')->addError($mode ? $payment->message : $payment->technical_message);
                    $paymentInst->setTransactionId($paymentData['transaction_id']);
                    return;
                }

            } else {
                $order->addStatusToHistory($order->getStatus(), $this->__('Could not initiate WPF'));
                $order->save();
                Mage::getSingleton('core/session')->addError($this->__('Could not initiate WPF'));
                return;
            }
        } catch(Hypercharge\Errors\ValidationError $e) {
            // no payment created in hypercharge because of local pre-validation errors
            // show validation errors to customer
            // $e->errors is an Array of Hash, format: [ { "property": String , "message" : String }, ... ]
            $order->addStatusToHistory($order->getStatus(), "Oops! An error occured! " . implode("<br/>", $e->errors));
            $order->save();
            Mage::getSingleton('core/session')->addError($mode ? $payment->message : $payment->technical_message);
            $paymentInst->setTransactionId($paymentData['transaction_id']);
            return;

        } catch(Exception $e) {
            Mage::helper('bithypercharge')->logger('WPF ERROR: ' . var_export($e->getTraceAsString(), true));
            $order->addStatusToHistory($order->getStatus(), "Oops! An error occured! ");
            $order->save();
            Mage::getSingleton('core/session')->addError("Sorry for the inconvenience! An error occured!");
            $paymentInst->setTransactionId($paymentData['transaction_id']);
            return;
        }
    }

    /**
     * Returns the order object
     * @return Mage_Sales_Model_Order 
     */
    public function getQuote() {
        if (!$this->_order) {
            $orderIncrementId = Mage::getModel('checkout/session')
                    ->getLastRealOrderId();
            $this->_order = Mage::getModel('sales/order')
                    ->loadByIncrementId($orderIncrementId);
        }

        return $this->_order;
    }

    /**
     * Returns the url of the redirect controller
     * @return  string
     */
    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('bit-hypercharge/wpfredirect/redirect');
    }

    /**
     * Returns the termination $xml or null on failure and updates the order data
     * @param array $post The $_POST string received in the WPF notification
     * @return mixed 
     */
    public function wpfResponse($post) {
        $gate = Mage::helper('bithypercharge/gateway');
        $hypercharge_channels = $this->getConfigChannels();
        $timestamp = date('Y-m-d H:i:s', time() + 10800);
        Mage::helper('bithypercharge')->logger("\n" . str_repeat('*', 80) . "\n" . $timestamp
                . ' POST notification received');
        Mage::helper('bithypercharge')->logger("\n" . ' Post data: ' . print_r($post, true));

        // Check for existence of data
        if (!$hypercharge_channels || !$post || !is_array($post))
            return;
        Mage::log(var_export($post, true), null, "aabb.log");
        if (!($post['payment_status'] && ($post['payment_status'] == 'error' || $post['payment_status'] == 'timeout')))
            if (!array_key_exists('signature', $post) || !array_key_exists('payment_transaction_channel_token', $post) || !array_key_exists('payment_transaction_unique_id', $post) || !array_key_exists('payment_transaction_id', $post) || !array_key_exists('payment_transaction_transaction_type', $post) || !array_key_exists('payment_unique_id', $post) || !array_key_exists('notification_type', $post))
                return;

        //Get the information from the POST variables
        $signature = $post['signature'];
        $trx_channel = $post['payment_transaction_channel_token'];
        $trx_id = $post['payment_transaction_unique_id'];
        $wpf_trx_id = $post['payment_transaction_id'];
        $trx_type = $post['payment_transaction_transaction_type'];
        $wpf_id = $post['payment_unique_id'];
        $notification_type = $post['notification_type'];
        $wireId = null;
        if ($post['wire_reference_id']) {
            $wireId = $post['wire_reference_id'];
        }

        $xml = $gate->getTrxEndXml($wpf_id);
        $order_id = substr($wpf_trx_id, 0, strpos($wpf_trx_id, '-'));
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
        } catch (Exception $e) {
            Mage::helper('bithypercharge')->logger("\n" . $timestamp
                    . ' Transaction could not be found in database - '
                    . $wpf_trx_id . '. Error: ' . $e->getMessage());
            return $xml;
        }
        $paymentMethod = get_class($order->getPayment()->getMethodInstance());
        if ($paymentMethod)
            $modelPayment = Mage::getModel($paymentMethod);
        else
            $modelPayment = $this;

        $mode = !$modelPayment->getConfigData('mode');

        //Check if error or timeout
        if ($post['payment_status'] && ($post['payment_status'] == 'error' || $post['payment_status'] == 'timeout')) {
            // cancel order
            if ($post['payment_status'] == 'timeout') {
                $order->getPayment()
                        ->setAdditionalInformation(
                                'Transaction Status', 'timeout')
                        ->setIsTransactionClosed(0);
                $order->registerCancellation('Payment timeout.', false);
                $order->save();
            } else {
                $order->getPayment()
                        ->setAdditionalInformation(
                                'Transaction Status', 'error')
                        ->setIsTransactionClosed(0);
                $order->registerCancellation('Payment error.', false);
                $order->save();
            }
            return $xml;
        }

        //Get configuration values here
        $channel = null;
        $allChannels = $this->getAllConfigChannels();
        foreach ($allChannels as $ch)
            if ($ch['channel'] == $trx_channel) {
                $channel = $ch;
                break;
            }

        //Check the signature of the request - optional
        if (!$channel) {// channel is bogus
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Invalid channel');
            return;
        }

        $pass = false;
        if (function_exists('hash'))// check if hash family is supported e.g. php > 5.1.2
            if (hash('sha512', $wpf_id . $channel['pass']) == $signature)
                $pass = true;

        $gate->setUsername($channel['login']);
        $gate->setPassword($channel['pass']);
        $gate->setChannel($channel['channel']);
        $gate->setMode($mode ? 'live' : 'test');

        if (!$pass && function_exists('hash')) { // the signature doesn't match 
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Invalid signature. Signature: '
                        . $signature . ' WPF id: ' . $wpf_id . ' Password: '
                        . $channel['pass']);

            return $xml;
        }

        //Do a WPF reconcile
        $reconcile_params = array('unique_id' => $wpf_id);
        $response = $gate->wpf_reconcile($reconcile_params);

        if (!$response || (array_key_exists('status', $response) && $response['status'] == 'error')) {// Reconcile request failed
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Reconcile request failed');
            return $xml;
        }

        if (!$mode) {
            $respString = print_r($response, true);
            $postData = print_r($post, true);
            $reconcileXML = $gate->paramsXML($reconcile_params, 'wpf_reconcile');
            $delimiter = str_repeat('*', 80);
            $info = "\n$timestamp WPF notification received. Post data: \n
                $postData\n        
                Reconcile request sent:
                <?xml version=\"1.0\" encoding=\"utf-8\"?>\n  
                $reconcileXML\n\n
                Response received:\n
                $respString\n$delimiter";
            Mage::helper('bithypercharge')->logger($info);
        }

        if ($response['payment_transaction']['wire_reference_id']) {
            $wireId = $response['payment_transaction']['wire_reference_id'];
        }

        //Get the transaction type
        $transaction = null;
        if (!array_key_exists(0, $response['payment_transaction']))
        //we have a single transaction in response
            $transaction = $response['payment_transaction'];
        else
            foreach ($response['payment_transaction'] as $p)
                if ($p['transaction_id'] == $wpf_trx_id) {
                    $transaction = $p;
                    break;
                }
        if (!$transaction) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n" . $timestamp
                        . ' Transaction type could not be determined ');
            return $xml;
        }


        $is_authorize = $transaction['transaction_type'] == 'authorize' || $transaction['transaction_type'] == 'authorize3d';

        // Set status as completed		
        if (in_array($response['status'], array('approved', 'chargeback_reversed')) && !$is_authorize)
            $status = Mage_Sales_Model_Order::STATE_COMPLETE;

        // Set status as cancelled		
        if (in_array($response['status'], array('chargebacked', 'voided')))
            $status = Mage_Sales_Model_Order::STATE_CANCELED;

        // Set status as pending
        if (in_array($response['status'], array('pending', 'pending_async', 'pre_arbitrated')) || ($is_authorize && $response['status'] == 'approved'))
            $status = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;

        // Set some extra info
        $order->getPayment()
                ->setAdditionalInformation('Last Transaction ID', $transaction['unique_id'])
                ->setAdditionalInformation('Transaction Type', $transaction['transaction_type'])
                ->setAdditionalInformation('Transaction Status', $response['status']);
        /*if ($wireId) {
            $order->getPayment()->setAdditionalInformation('Wire Reference ID', $wireId);
        }*/
        try {
            switch ($response['status']) {
                case 'approved':
                case 'chargeback_reversed':
                    if ($is_authorize) {
                        $order->getPayment()
                                ->setPreparedMessage('Payment authorized.')
                                ->setTransactionId($transaction['unique_id'])
                                ->setAdditionalInformation('Last Transaction ID', $transaction['unique_id'])
                                ->setAdditionalInformation('Last Transaction Type', $transaction['transaction_type'])
                                ->setAdditionalInformation(
                                        'Last Transaction Status', $response['status'])
                                ->setIsTransactionClosed(0)
                                ->registerAuthorizationNotification(
                                        $transaction['amount'] / 100)
                                ->setIsTransactionApproved(true);
                        if (!$order->getEmailSent())
                            $order->sendNewOrderEmail();
                        $order->save();
                        break;
                    }
                    if ($wireId) {
                        $order->getPayment()->setAdditionalInformation('Wire Reference ID approved', $wireId);
                    }
                    $order->getPayment()
                            ->setTransactionId($transaction['unique_id'])
                            ->setPreparedMessage('Transaction successful.')
                            ->setIsTransactionClosed(0)
                            ->registerCaptureNotification(
                                    $transaction['amount'] / 100);
                    if (!$order->getEmailSent())
                        $order->sendNewOrderEmail();
                    $order->save();
                    $order->getPayment()->capture();
                    break;
                case 'declined':
                    $order->registerCancellation('Payment was declined.', false)
                            ->save();
                    break;
                case 'chargebacked':
                    break;
                case 'pre_arbitrated':
                case 'refunded':
                    $order->getPayment()
                            ->setPreparedMessage('Payment was refunded.')
                            ->setTransactionId($transaction['unique_id'])
                            ->setIsTransactionClosed(
                                    $response['status'] == 'refunded' ? 1 : 0)
                            ->registerRefundNotification(
                                    -1 * ($transaction['amount'] / 100));
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
                    if ($wireId) {
                        $order->getPayment()->setAdditionalInformation('Wire Reference ID pending', $wireId);
                    }
                    $order->getPayment()
                            ->setPreparedMessage('Payment not complete yet.')
                            ->setTransactionId($transaction['unique_id'])
                            ->setIsTransactionClosed(0)
                            ->registerPaymentReviewAction(
                                    Mage_Sales_Model_Order_Payment
                                    ::REVIEW_ACTION_UPDATE, false);
                    if (!$order->getEmailSent()) {
                        $order->sendNewOrderEmail();
                    }
                    $order->save();
                    break;
            }
        } catch (Exception $e) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Could not update order status: '
                        . $e->getMessage());
            return $xml;
        }

        if (!$mode)
            Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Order status updated to '
                    . $status . ' - transaction type: '
                    . $transaction['transaction_type']);

        //Output the XML so that the gateway knows that we are done
        if ($is_authorize && $status == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT || !$is_authorize && $status != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
            if (!$mode)
                Mage::helper('bithypercharge')->logger("\n" . $timestamp . ' Transaction successfully finished');
            return $xml;
        }
        else
            return;
    }

    public function getTransactionType() {
        if (empty($this->_transactionType)) {
            //do nothing
            return;
        }
        return $this->_transactionType;
    }

}