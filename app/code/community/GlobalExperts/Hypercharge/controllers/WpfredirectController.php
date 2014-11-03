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

class GlobalExperts_Hypercharge_WpfredirectController extends Mage_Core_Controller_Front_Action {

    protected $order;

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    protected function _expireAjax() {
        if (!Mage::getSingleton('checkout/session')->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Session Expired');
            exit;
        }
    }

    /**
     * Customer Redirection to HyperCharge Gateway
     */

    public function redirectAction() {
        $session = $this->getCheckout();
        $session->setHyperchargeQuoteId($session->getQuoteId());
        $session->setHyperchargeRealOrderId($session->getLastRealOrderId());
        $model = Mage::getModel('bithypercharge/checkout');
        $url = $model->getRedirectUrl();

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        $order->addStatusToHistory($order->getStatus(), Mage::helper('bithypercharge')->__('Customer was redirected to Hypercharge.'));
        $order->save();

        if ($url)
            $this->getResponse()->setRedirect($url, 302);
        else
            $this->_forward('failure', 'redirect');
        $session->unsQuoteId();
    }

    /**
     * Success action after initialize
     */

    public function successAction() {
        $session = Mage::getSingleton('checkout/session');
        $session->unsHyperchargeRealOrderId();
        $session->setQuoteId($session->getHyperchargeQuoteId(true));
        Mage::getSingleton('core/session')->unsHyperRedirectUrl();
        Mage::getSingleton('core/session')->unsHyperReviewRedirect();
        Mage::getSingleton('checkout/session')->getQuote()
                ->setIsActive(false)->save();
        echo '<script type="text/javascript">window.top.location.href = "' . Mage::getUrl('checkout/onepage/success') . '"; </script>';
    }

    /**
     * Failure action after initialize
     */

    public function failureAction() {
        Mage::getSingleton('core/session')->unsHyperRedirectUrl();
        Mage::getSingleton('core/session')->unsHyperReviewRedirect();
		$session = Mage::getSingleton('checkout/session');
		if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->setState(Mage_Sales_Model_Order::STATE_NEW, true)->save();
            }
        }
        echo '<script type="text/javascript">window.top.location.href = "' . Mage::getUrl('checkout/onepage/failure') . '"; </script>';
    }

    /**
     * Cancel action after initialize
     */

    public function cancelAction() {
        Mage::getSingleton('core/session')->unsHyperRedirectUrl();
        Mage::getSingleton('core/session')->unsHyperReviewRedirect();
		$session = Mage::getSingleton('checkout/session');
		if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->setState(Mage_Sales_Model_Order::STATE_NEW, true)->save();
            }
        }
        echo '<script type="text/javascript">window.top.location.href = "' . Mage::getUrl('checkout/onepage/failure') . '"; </script>';
    }

    /**
     * Load the iFrame for HyperCharge depending on the payment method set
     */

    public function hyperchargeAction() {
        $this->loadLayout();
        $this->getLayout()->getBlock('root')->setTemplate('page/1column.phtml');
        $block = $this->getLayout()->createBlock('bithypercharge/iframe')->setTemplate('hypercharge/wpf/iframe.phtml');
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

}
