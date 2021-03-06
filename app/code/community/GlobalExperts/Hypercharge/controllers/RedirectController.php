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

class GlobalExperts_Hypercharge_RedirectController extends Mage_Core_Controller_Front_Action
{ 
    protected $order;

    protected function _expireAjax() {
        if(!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            exit;
        }
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }


    /**
     * Customer Redirection to HyperCharge Gateway
     */

    public function redirectAction() {
        $session = $this->getCheckout();
        $session->setHyperchargeQuoteId($session->getQuoteId());
        $session->setHyperchargeRealOrderId($session->getLastRealOrderId());

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        $order->addStatusToHistory($order->getStatus(), 
            Mage::helper('wpf')->__('Customer was redirected to Hypercharge.'));
        $order->save();

        die('redirect');
    }

    /**
     * Success action after initialize
     */

    public function successAction() {
        $session = Mage::getSingleton('checkout/session');
        $session->unsHyperchargeRealOrderId();
        $session->setQuoteId($session->getHyperchargeQuoteId(true));
        Mage::getSingleton('checkout/session')->getQuote()
            ->setIsActive(false)->save();        
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * Failure action after initialize
     */

    public function failureAction() {
        $order = Mage::getModel("sales/order")->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
        if ($order && $order->getPayment()->getMethod() == 'hypercharge_mobile_purchase_on_account_gtd') {
            Mage::getSingleton('checkout/session')->setErrorMessage("<br/>Diese Zahlung konnte nicht durchgeführt werden. Dies kann unterschiedliche Gründe haben, wie etwa fehlerhafte Eingabedaten, eine unbekannte Adresse, oder ein vorübergehendes technisches Problem.<br/>Bitte überprüfen Sie die angegebenen Daten, oder wählen Sie ein anderes Zahlungsmittel.<br/><br/>");
        }

        $this->_redirect('checkout/onepage/failure');
    }

    /**
     * Cancel action after initialize
     */

    public function cancelAction() {


        Mage::getSingleton('core/session')->unsHyperRedirectUrl();
        Mage::getSingleton('core/session')->unsHyperReviewRedirect();
        $this->_redirect('checkout/onepage/failure');
    }

    /**
     * Load the iFrame for HyperCharge depending on the payment method set
     */

    public function hyperchargeAction() {
        $this->loadLayout();
        $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
        $block =  $this->getLayout()->createBlock('wpf/iframe')->setTemplate('hypercharge/wpf/iframe.phtml');
        $this->getLayout()->getBlock('content')->append($block);        
        $this->renderLayout();
    }
    
}
