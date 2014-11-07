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

class GlobalExperts_Hypercharge_Model_Mobilepurchaseaccountgtd extends GlobalExperts_Hypercharge_Model_Mobile {

    protected $_code = 'hypercharge_mobile_purchase_on_account_gtd';
    protected $_formBlockType = 'bithypercharge/form_purchaseonaccountgtd';
    protected $_infoBlockType = 'bithypercharge/info_debit';
    protected $_canCapture = true;
    protected $_canRefund = false;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;
    protected $_jsonTransactionType = "gtd_purchase_on_account";

    /**
     * Validate payment method information object
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function validate() {
        // check if billing address is the same as shipping address
        $paymentInfo = $this->getInfoInstance();
        // get billing and shipping addresses
        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            $billing = $paymentInfo->getOrder()->getBillingAddress();
            $shipping = $paymentInfo->getOrder()->getShippingAddress();
        } else {
            $billing = $paymentInfo->getQuote()->getBillingAddress();
            $shipping = $paymentInfo->getQuote()->getShippingAddress();
        }
        // check addresses
        if ($shipping && !Mage::helper('bithypercharge')->checkAddresses($billing, $shipping)) {
            Mage::throwException(Mage::helper('bithypercharge')->__('For this payment method the shipping address must be the same as billing address.'));
        }
        parent::validate();
    }
    
    /**
     * Payment redirect
     * 
     * @return string
     */
    public function getOrderPlaceRedirectUrl() {
        $infoUrl = Mage::registry('hyper_redirect_url');
        return $infoUrl . '#' . Mage::getBlockSingleton($this->_formBlockType)->getSubmitUrl();
    }
    
    /**
     * Send capture request to gateway
     *
     * @param Varien_Object $payment
     * @param decimal $amount
     * @return GlobalExperts_Hypercharge_Model_PaymentMethod
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount) {         
        // check transaction mode - Authorize and Capture; or Pre-Authorization, then Capture
        if (!$payment->getTransactionId()) {
            Mage::throwException(Mage::helper('bithypercharge')->__('Error in capturing the payment.'));
        }        
        // set transaction amount
        $payment->setAmount($amount);
        // get current order
        $order = $payment->getOrder();        
        $payment->registerCaptureNotification($amount);
        if (!$this->_canRefund) {
            $payment->setIsTransactionClosed(1); // close transaction after capture
        } else {
            $payment->setIsTransactionClosed(0);
        }
        $order->save();
        return $this;                
    }
    
    /**
     * Void the payment through gateway
     *
     * @param Varien_Object $payment
     * @return GlobalExperts_Hypercharge_Model_PaymentMethod
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment) {        
        if ($payment->getParentTransactionId()) {
            $payment->setAnetTransType(self::REQUEST_TYPE_VOID);
                        
            // prepare hypercharge gateway channels for calling
            $hypercharge_channels = $this->getConfigChannels();
            // check if test mode
            $test = $this->getConfigData('test');
            // set API call mode
            if ($test) {
                $mode = Hypercharge\Config::ENV_SANDBOX;
            } else {
                $mode = Hypercharge\Config::ENV_LIVE;
            }
            // start logging transaction
            Mage::helper('bithypercharge')->logger("\n" . str_repeat("*", 80) . "\n Void transaction started");
            // get current order
            $order = $payment->getOrder();
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
        
            // prepare refund data
            $uniqueId = $payment->getAdditionalInformation('unique_id');
            $void = Hypercharge\Payment::void($uniqueId);
                       
            // check if void is approved
            if ($void->unique_id && $void->status == 'voided' && !$void->error) {
                Mage::helper('bithypercharge')->logger("Void transaction approved"); 
                $payment->setLastTransId($void->unique_id)                        
                        ->setStatus(self::STATUS_SUCCESS );
                return $this;   
            // if not, display error and cancel order
            } elseif ($void->unique_id && $void->error) {
                $error = $void->error;            
                $errMsg = $error->technical_message;
                Mage::helper('bithypercharge')->logger($error->status_code . ': ' . $errMsg);
                $payment->setStatus(self::STATUS_ERROR);
                Mage::throwException(Mage::helper('bithypercharge')->__($errMsg));            
            }                    
        }
        // if no error message, display a generic error message
        $payment->setStatus(self::STATUS_ERROR);
        Mage::throwException(Mage::helper('bithypercharge')->__('Invalid transaction ID.'));
    }
    
    /**
     * Cancel the payment through gateway
     *
     * @param Varien_Object $payment
     * @return GlobalExperts_Hypercharge_Model_PaymentMethod
     * @throws Mage_Core_Exception
     */
    public function cancel(Varien_Object $payment) {
        $this->void($payment);        
        return $this;
    }
}
