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

class GlobalExperts_Hypercharge_NotificationController extends Mage_Core_Controller_Front_Action {

    /**
     * After a payment is initialize HyperCharge Gateway calls the notification URL in order to do a reconcile action
     */

    public function responseAction() {
        Mage::log("POST Mobile" . var_export($this->getRequest()->getPost(), true), null, "hypercharge-reconcile-post.log");
        $this->getResponse()
                ->setHeader('Content-type', 'text/xml; charset=utf8')
                ->setBody($this->getLayout()
                    ->createBlock('bithypercharge/response')
                    ->toHtml());   
    }
    
}
