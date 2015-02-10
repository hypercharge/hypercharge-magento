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

class GlobalExperts_Hypercharge_Block_Info_Wpf extends Mage_Payment_Block_Info
{
    /**
     * Checkout progress information block flag
     *
     * @var bool
     */
    protected $_isCheckoutProgressBlockFlag = true;
        
    protected function _prepareSpecificInformation($transport = null) {
        $info = parent::_prepareSpecificInformation($transport);
        $payment = $this->getInfo();
        $transport = new Varien_Object();
        $paymentInfo = $payment->getAdditionalInformation();
        if ($this->htmlEscape($payment->getAdditionalInformation('Last Transaction ID'))) {
            $transport->addData(array(
                Mage::helper('bithypercharge')->__('Last Transaction ID') => $this->htmlEscape($payment->getAdditionalInformation('Last Transaction ID'))
            ));      
        }
        if ($this->htmlEscape($payment->getAdditionalInformation('Wire Reference ID approved'))) {
            $transport->addData(array(
                Mage::helper('bithypercharge')->__('Wire Reference ID') => $this->htmlEscape($payment->getAdditionalInformation('Wire Reference ID approved'))
            ));      
        } elseif ($this->htmlEscape($payment->getAdditionalInformation('Wire Reference ID pending'))) {
            $transport->addData(array(
                Mage::helper('bithypercharge')->__('Wire Reference ID - please use this to make a deposit') => $this->htmlEscape($payment->getAdditionalInformation('Wire Reference ID pending'))
            ));      
        }
        if ($this->htmlEscape($payment->getAdditionalInformation('Transaction Status'))) {
            $transport->addData(array(
                Mage::helper('bithypercharge')->__('Transaction Status') => $this->htmlEscape($payment->getAdditionalInformation('Transaction Status'))
            ));      
        }

        if ($this->htmlEscape($payment->getAdditionalInformation('Wire Reference ID'))) {
            $transport->addData(array(
                Mage::helper('bithypercharge')->__('Wire Reference ID') => $this->htmlEscape($payment->getAdditionalInformation('Wire Reference ID'))
            ));
        }
        if ($this->htmlEscape($payment->getAdditionalInformation('Verwendungszweck'))) {
            $transport->addData(array(
                Mage::helper('bithypercharge')->__('Verwendungszweck') => $this->htmlEscape($payment->getAdditionalInformation('Verwendungszweck'))
            ));
        }
        if (Mage::getDesign()->getArea() == 'adminhtml' && $this->htmlEscape($payment->getAdditionalInformation('Channel Token'))) {
            $transport->addData(array(
                Mage::helper('bithypercharge')->__('Channel Token') => $this->htmlEscape($payment->getAdditionalInformation('Channel Token'))
            ));
        }
        return $transport;
    }
}
