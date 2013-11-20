<?php

defined('_JEXEC') or die('Restricted access');

/**
 *
 * @author Brandon Sanchez
 * @version $Id: payu.php 04-2013 18:44:10Z $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2013 - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 */
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}
define('JURI_VMPAYU', JURI::base() . 'plugins/vmpayment/payu');

class plgVmPaymentPayu extends vmPSPlugin {

    // instance of class
    public static $_this = FALSE;

    function __construct(& $subject, $config) {

        //if (self::$_this)
        //   return self::$_this;
        parent::__construct($subject, $config);

        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = array('payu_encrypt_key' => array('', 'char'),
            'payu_user_id' => array('', 'char'),
            'payu_description' => array('', 'char'),
            'payu_account_id' => array('', 'char'),
            'debug' => array(0, 'int'),
            'min_amount' => array('', 'int'),
            'max_amount' => array('', 'int'),
            'payu_currency' => array('', 'char'),
            'payment_logos' => array('', 'char'),
            'tax_id' => array(0, 'int')
        );

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        //self::$_this = $this;
    }

    /**
     * @return string
     */
    public function getVmPluginCreateTableSQL() {

        return $this->createTableSQL('Payment Payu');
    }

    /**
     * @return array
     */
    function getTableSQLFields() {

        $SQLfields = array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL',
            'payment_currency' => 'smallint(1)',
            'email_currency' => 'smallint(1)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)',
            'fecha' => 'varchar(32) NOT NULL',
            'refventa' => 'varchar(32) UNIQUE KEY NOT NULL',
            'refpol' => 'varchar(32) NOT NULL',
            'estado_pol' => 'varchar(32) NOT NULL',
            'formapago' => 'varchar(32) NOT NULL',
            'banco' => 'varchar(32) NOT NULL',
            'codigo_respuesta_pol' => 'varchar(32) NOT NULL',
            'mensaje' => 'varchar(50) NOT NULL',
            'valor' => 'varchar(20) NOT NULL',
            'conf' => 'int(1) DEFAULT 0'
        );
        return $SQLfields;
    }

    function plgVmConfirmedOrder($cart, $order) {

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        $session = JFactory::getSession();
        $return_context = $session->getId();
        $this->_debug = $method->debug;

        //$this->logInfo ('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        if (!class_exists('VirtueMartModelCurrency')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
        }

        $address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

        if (!class_exists('TableVendors')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
        }
        $vendorModel = VmModel::getModel('Vendor');
        $vendorModel->setId(1);
        $vendor = $vendorModel->getVendor();
        $vendorModel->addImages($vendor, 1);
        $this->getPaymentCurrency($method);
        $email_currency = $payment->email_currency;
        $currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');

        $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
        $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, FALSE), 2);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
        if ($totalInPaymentCurrency <= 0) {
            vmInfo(JText::_('VMPAYMENT_PAYU_PAYMENT_AMOUNT_INCORRECT'));
            return FALSE;
        }
        $merchant_email = $order['details']['BT']->email;
        if (empty($merchant_email)) {
            vmInfo(JText::_('VMPAYMENT_PAYU_MERCHANT_EMAIL_NOT_SET'));
            return FALSE;
        }
        $quantity = 0;
        foreach ($cart->products as $key => $product) {
            $quantity = $quantity + $product->quantity;
        }

        $iva = floatval($order['details']['BT']->order_billTaxAmount);
        $baseDevolucionIva = $order['details']['BT']->order_subtotal;
        if ($iva == 0) {
            $baseDevolucionIva = 0;
        }

        $baseDevolucionIva = ($baseDevolucionIva != 0) ? number_format((floatval($baseDevolucionIva) - floatval($order['details']['BT']->order_discount)), '2', '.', '') : 0;
        /* echo "<pre>";
          echo $baseDevolucionIva;
          print_r($order['details']['BT']);die();die($order['details']['BT']->order_number); */

        $firma = md5($method->payu_encrypt_key
                . "~" . $method->payu_user_id
                . "~" . $order['details']['BT']->order_number
                . "~" . number_format(floatval($totalInPaymentCurrency), '2', '.', '')
                . "~" . $method->payu_currency);

        $post_variables = Array(
            'description' => $method->payu_description,
            'proof' => $method->debug,
            'merchantId' => $method->payu_user_id,
            'referenceCode' => $order['details']['BT']->order_number,
            'amount' => number_format(floatval($totalInPaymentCurrency), '2', '.', ''),
            'tax' => $iva,
            'taxReturnBase' => $baseDevolucionIva,
            'currency' => $method->payu_currency,
            'confirmationUrl' => JURI_VMPAYU . '/payu/' . 'confirmacion.php',
            'responseUrl' => JURI::base(),
            'buyerEmail' => $order['details']['BT']->email,
            'signature' => $firma
        );

        if ($method->payu_account_id != "") {
            $post_variables['accountId'] = $method->payu_account_id;
        }

        $gateway = "https://gateway.payulatam.com/ppp-web-gateway";
        if ($method->debug == "1" || $method->debug == 1) {
            $gateway = "https://stg.gatewaylap.pagosonline.net/ppp-web-gateway";
        }

        //var_dump($post_variables);die();
        // Prepare data that should be stored in the database
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues['refventa'] = $order['details']['BT']->order_number;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $method->payment_currency;
        $dbValues['email_currency'] = $email_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);

        //$url = "gateway.payu.net/apps/gateway/index.html";

        $html = '<html><head><title>Redirection</title></head><body><div style="margin: auto; text-align: center;">';
        $html .= '<form action="' . $gateway . '" method="post" name="vm_payu_form">';
        foreach ($post_variables as $name => $value) {
            $html .= '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
        }
        $html .= '<input type="submit" name="Submit"  value="' . JText::_('VMPAYMENT_PAYU_REDIRECT_MESSAGE') . '" />';
        $html .= '</form></div>';
        $html .= ' <script type="text/javascript">';
        $html .= ' document.vm_payu_form.submit();';
        $html .= ' </script></body></html>';

        // Vaciar carrito
        $cart = VirtueMartCart::getCart ();
        $cart->emptyCart ();
        // 	2 = don't delete the cart, don't send email and don't redirect
        $cart->_confirmDone = FALSE;
        $cart->_dataValidated = FALSE;
        $cart->setCartIntoSession();
        JRequest::setVar('html', $html);
    }

    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        $this->getPaymentCurrency($method);
        $paymentCurrencyId = $method->payment_currency;
    }

    function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }

        if (empty($payments[0]->email_currency)) {
            $vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
            $db = JFactory::getDBO();
            $q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
            $db->setQuery($q);
            $emailCurrencyId = $db->loadResult();
        } else {
            $emailCurrencyId = $payments[0]->email_currency;
        }
    }

    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {

        if (!$this->selectedThisByMethodId($payment_method_id)) {
            return NULL; // Another method was selected, do nothing
        }

        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $first = TRUE;
        foreach ($payments as $payment) {
            $html .= '<tr class="row1"><td>' . JText::_('VMPAYMENT_PAYU_DATE') . '</td><td align="left">' . $payment->created_on . '</td></tr>';
            // Now only the first entry has this data when creating the order
            if ($first) {
                $html .= $this->getHtmlRowBE('PAYU_PAYMENT_NAME', $payment->payment_name);
                // keep that test to have it backwards compatible. Old version was deleting that column  when receiving an IPN notification
                if ($payment->payment_order_total and $payment->payment_order_total != 0.00) {
                    $html .= $this->getHtmlRowBE('PAYU_PAYMENT_ORDER_TOTAL', $payment->payment_order_total . " " . shopFunctions::getCurrencyByID($payment->payment_currency, 'currency_code_3'));
                }
                if ($payment->email_currency and $payment->email_currency != 0) {
                    $html .= $this->getHtmlRowBE('PAYU_PAYMENT_EMAIL_CURRENCY', shopFunctions::getCurrencyByID($payment->email_currency, 'currency_code_3'));
                }
                $first = FALSE;
            }
        }
        $html .= '</table>' . "\n";
        return $html;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {

        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    protected function checkConditions($cart, $method, $cart_prices) {

        $this->convert($method);

        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR
                ($method->min_amount <= $amount AND ($method->max_amount == 0)));

        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        // probably did not gave his BT:ST address
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($amount_cond) {
                return TRUE;
            }
        }

        return FALSE;
    }

    function convert($method) {

        $method->min_amount = (float) $method->min_amount;
        $method->max_amount = (float) $method->max_amount;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {

        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {

        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {

        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id) {

        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {

        return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {

        return $this->setOnTablePluginParams($name, $id, $table);
    }

    function onAfterRender() {
        $mainframe = & JFactory::getApplication();

        if ($mainframe->isAdmin())
            return;

        $pol = JRequest::getVar('transactionState');

        $content = & JResponse::getBody();
        $qst = "?" . $_SERVER["QUERY_STRING"];

        $scripts = JURI::base() . "plugins/vmpayment/payu/payu/";
        //print_r($params[3]);die();

        $conf = JURI::base() . "plugins/vmpayment/payu/payu/respuesta.php";
        $conf = str_replace("\\", "/", $conf);


        if ($pol == "" || $pol == null)
            return true;

        $alert = '<a href="' . $conf . $qst . '" class="fancybox fancybox.iframe" id="pol_show_fancy" />';
        $showAlert = '<script type="text/javascript">
			$(document).ready(function() {
				$("#pol_show_fancy").fancybox({ easingIn: "swing", easingOut: "swing", speedIn: 500, speedOut: 300 });
				$("#pol_show_fancy").trigger("click");
			});
		</script>';

        $content = str_replace('</body>', "\n<link rel='stylesheet' type='text/css' href='".$scripts."fancybox/jquery.fancybox.css'/>" . "\n" . '</body>', $content);
        $content = str_replace('</body>', "\n<script type='text/javascript' src='http://code.jquery.com/jquery-latest.min.js'></script>" . "\n" . '</body>', $content);
        $content = str_replace('</body>', "\n<script type='text/javascript' src='".$scripts."fancybox/jquery.fancybox.pack.js'></script>" . "\n" . '</body>', $content);
        $content = str_replace('</body>', "\n" . $alert . "\n" . '</body>', $content);
        $content = str_replace('</body>', "\n" . $showAlert . "\n" . '</body>', $content);

        JResponse::setBody($content);
    }

}

// No closing tag