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

class GlobalExperts_Hypercharge_Model_Debit extends GlobalExperts_Hypercharge_Model_Mobile {

    // unique internal payment method identifier
    // @var string [a-z0-9_]
    protected $_code = 'hypercharge_mobile_debit';
    // Define payment block form
    protected $_formBlockType = 'bithypercharge/form_debit';
    // Info block type
    protected $_infoBlockType = 'bithypercharge/info_debit';
    // Can capture funds online?
    protected $_canCapture = true;
    // Can refund online?
    protected $_canRefund = true;
    // Can void transactions online?
    protected $_canVoid = true;
    // Can use this payment method in administration panel?
    protected $_canUseInternal = false;
    // Can show this payment method as an option on checkout payment page?
    protected $_canUseCheckout = true;
    // Is this payment method suitable for multi-shipping checkout?
    protected $_canUseForMultishipping = false;
    // Can save credit card information for future processing?
    protected $_canSaveCc = false;

    protected $_jsonTransactionType = "";
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
     
    /**
     * refund the amount with transaction id
     *
     * @param string $payment Varien_Object object
     * @return Mage_Paygate_Model_Authorizenet
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount) {
        if ($payment->getRefundTransactionId() && $amount > 0) {
            $payment->setAnetTransType(self::REQUEST_TYPE_CREDIT)
                    ->setIsTransactionClosed(0); // open transaction for refunding
                       
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
            Mage::helper('bithypercharge')->logger("\n" . str_repeat("*", 80) . "\n Refunding transaction started");
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
            $refund = Hypercharge\Payment::refund($uniqueId);
                       
            // check if void is approved
            if ($refund->unique_id && $refund->status == 'refunded' && !$refund->error) {
                Mage::helper('bithypercharge')->logger("Refund transaction approved");                 
                $payment->setTransactionId($refund->unique_id)
                        ->setTransactionType($refund->transaction_type)                        
                        ->setIsTransactionClosed(1) // refund initiated by merchant
                        ->setStatus(self::STATUS_SUCCESS);
                return $this;                
            // if not, display error and cancel order
            } elseif ($refund->unique_id && $refund->error) {
                $error = $refund->error;
                $errMsg = $error->technical_message;
                Mage::helper('bithypercharge')->logger($error->status_code . ': ' . $errMsg);
                Mage::throwException(Mage::helper('bithypercharge')->__($errMsg));
            }            
        }
        // if no error message, display a generic error message
        Mage::throwException(Mage::helper('bithypercharge')->__('Error in refunding the payment.'));
    }         
    
}
