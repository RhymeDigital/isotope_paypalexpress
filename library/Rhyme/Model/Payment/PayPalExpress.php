<?php

/**
 * Paypal Express for Iotope eCommerce
 *
 * Copyright (C) 2009-2018 Rhyme.Digital
 *
 * @package    IsotopePayPalExpress
 * @link       http://rhyme.digital
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

namespace Rhyme\Model\Payment;

use Haste\Http\Response\Response;
use Isotope\Interfaces\IsotopePayment;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Isotope;
use Isotope\Model\Payment;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection\Order;
use Isotope\Model\Payment\Postsale;

use Isotope\Model\Payment as PaymentModel;
use Isotope\Model\ProductCollection\Order as OrderModel;


/**
 * Isotope Payment Module
 *
 * @copyright  Rhyme Digital 2018
 * @author     Blair Winans <blair@rhyme.digital>
 * @author     Adam Fisher <adam@rhyme.digital>
 * @package    IsotopePayPalExpress
 */
class PayPalExpress extends Payment implements IsotopePayment
{

    /**
     * Initialize the object
     * @param \Database\Result
     */
    public function __construct(\Database\Result $objResult = null)
    {
	    $GLOBALS['TL_BODY']['iso_paypal_express_vendor_js'] = '<script src="https://www.paypalobjects.com/api/checkout.js"></script>';
	    
        parent::__construct($objResult);
    }
    

    /**
     * Process payment on checkout confirmation page.
     * @param   IsotopeProductCollection    $objOrder   The order being places
     * @param   \Module                     $objModule  The checkout module instance
     * @return  mixed
     */
    public function processPayment(IsotopeProductCollection $objOrder, \Module $objModule)
    {
        if (!$objOrder->payment_data)
        {
            return false;
        }

        $objPaymentData = json_decode(deserialize($objOrder->payment_data));
        if (!$objPaymentData || $objPaymentData->state != 'approved')
        {
            return false;
        }

        $objOrder->checkout();
        $objOrder->updateOrderStatus($this->new_order_status);

        return true;
    }


    /**
     * Return the PayPal form.
     *
     * @param \Module $objCheckoutModule
     * @param IsotopeCheckoutStep $objPaymentStep
     * @return string
     */
    public function paymentForm(\Module $objCheckoutModule, IsotopeCheckoutStep $objPaymentStep)
    {
        global $objPage;

        // Build JS template
        $objTemplate = new \Isotope\Template($this->paypalJsTpl ?: 'paypal_express_js_default');
        $objTemplate->setData($this->row());

        $objTemplate->current_page_id = $objPage->id;
        $objTemplate->create_url = 'http'.(\Environment::get('ssl') ? 's' : '').'://'.\Environment::get('host').'/system/modules/isotope_paypalexpress/scripts/create_payment.php?payment_mod='.$this->id.'&page_id='.$objPage->id;
        $objTemplate->execute_url = 'http'.(\Environment::get('ssl') ? 's' : '').'://'.\Environment::get('host').'/system/modules/isotope_paypalexpress/scripts/execute_payment.php?payment_mod='.$this->id.'&page_id='.$objPage->id;

        $GLOBALS['TL_BODY']['paypal_express_js'.$this->id] = $objTemplate->parse();
        
        // Build payment template
        $objTemplate = new \Isotope\Template($this->customTpl ?: 'payment_paypalexpress');
        $objTemplate->setData($this->row());

        $objTemplate->current_page_id = $objPage->id;

        return $objTemplate->parse();
    }


    /**
     * Return the checkout review information.
     *
     * Use this to return custom checkout information about this payment module.
     * Example: parial information about the used credit card.
     *
     * @return string
     */
    public function checkoutReview()
    {
        // Todo: add review stuff
	    return '';
    }


    /**
     * Return a list of valid credit card types for this payment module
     *
     * @return array
     * @deprecated Deprecated since 2.2, to be removed in 3.0. Create your own DCA field instead.
     */
    public static function getAllowedCCTypes()
    {
        return array('mc', 'visa', 'amex', 'discover', 'jcb', 'diners','maestro','solo');
    }


    /**
     * Set up a "payment" for PayPal Express
     * @return string
     */
    public function createPayment()
    {
        $objOrder = Isotope::getCart();
        if ($objOrder === null)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not load cart.';
            log_message($strErrMsg, 'debugPaypalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        $strCartId = $objOrder->id;

        // Check to see if we can grab the order instead
        $objCheck = OrderModel::findByPk(intval($objOrder->cart_id));
        if ($objCheck !== null)
        {
            $objOrder = $objCheck;
            $strCartId = $objOrder->id;
        }

        $objData = static::buildPayPalPaymentObject($objOrder, $strCartId);

        // !HOOK: alter data before sending
        if (isset($GLOBALS['ISO_HOOKS']['paypalExpressCreatePayment']) && is_array($GLOBALS['ISO_HOOKS']['paypalExpressCreatePayment'])) {
            foreach ($GLOBALS['ISO_HOOKS']['paypalExpressCreatePayment'] as $callback) {
                $objCallback = \System::importStatic($callback[0]);
                $objCallback->{$callback[1]}($objData, $objOrder, $this);
            }
        }

        // cURL headers
        $arrHeaders = array
        (
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->paypalClientId.':'.$this->paypalSecret),
        );

        // cURL request
        $curl_request = curl_init('https://api.' . ($this->debug ? 'sandbox.' : '') . 'paypal.com/v1/payments/payment');
        curl_setopt($curl_request, CURLOPT_POSTFIELDS, json_encode($objData));
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, $arrHeaders);
        curl_setopt($curl_request, CURLOPT_HEADER, 0);
        curl_setopt($curl_request, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl_request, CURLOPT_SSLVERSION, 1);
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl_request);
        curl_close($curl_request);

        if (!$response)
        {
            // Todo: handle errors better
            $strErrMsg = 'Empty response from PayPal';
            log_message($strErrMsg, 'debugPaypalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        $objResponse = json_decode($response);
        if (!is_object($objResponse) || !$objResponse->id)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not get payment from PayPal';
            log_message($strErrMsg, 'debugPaypalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        // Save the PayPal payment info
        $objOrder->paypal_payment_id = $objResponse->id;
        $objOrder->save();

        return $response;
    }


    /**
     * Execute a "payment" for PayPal Express
     */
    public function executePayment()
    {
        $objOrder = Isotope::getCart();
        if ($objOrder === null)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not load cart.';
            log_message($strErrMsg, 'debugPaypalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        $objOrder->setPaymentMethod($this);
        $strCartId = $objOrder->id;

        // Check to see if we can grab the order instead
        $objCheck = OrderModel::findByPk(intval($objOrder->cart_id));
        if ($objCheck !== null)
        {
            $objOrder = $objCheck;
            $strCartId = $objOrder->id;
            $objOrder->setPaymentMethod($this);
        }

        $objPayment = static::buildPayPalPaymentObject($objOrder, $strCartId);

        $objData = new \stdClass();
        $objData->payer_id = $_POST['payerID'];
        $objPayment->transactions[0]->description = substr(strval($objPayment->transactions[0]->description), 0, 127);
        $objData->transactions = $objPayment->transactions;

        // !HOOK: alter data before sending
        if (isset($GLOBALS['ISO_HOOKS']['paypalExpressExecutePayment']) && is_array($GLOBALS['ISO_HOOKS']['paypalExpressExecutePayment'])) {
            foreach ($GLOBALS['ISO_HOOKS']['paypalExpressExecutePayment'] as $callback) {
                $objCallback = \System::importStatic($callback[0]);
                $objCallback->{$callback[1]}($objData, $objOrder, $this);
            }
        }

        // cURL headers
        $arrHeaders = array
        (
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($this->paypalClientId.':'.$this->paypalSecret),
        );

        // cURL request
        $curl_request = curl_init('https://api.' . ($this->debug ? 'sandbox.' : '') . 'paypal.com/v1/payments/payment/'.$_POST['paymentID'].'/execute');
        curl_setopt($curl_request, CURLOPT_POSTFIELDS, json_encode($objData));
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, $arrHeaders);
        curl_setopt($curl_request, CURLOPT_HEADER, 0);
        curl_setopt($curl_request, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl_request, CURLOPT_SSLVERSION, 1);
        curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl_request);
        curl_close($curl_request);

        if (!$response)
        {
            // Todo: handle errors better
            $strErrMsg = 'Empty response from PayPal';
            log_message($strErrMsg, 'debugPaypalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        $objResponse = json_decode($response);
        if (!is_object($objResponse) || !$objResponse->id)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not get payment from PayPal';
            log_message($strErrMsg, 'debugPaypalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }
        elseif ($objResponse->state != 'approved')
        {
            // Todo: handle errors better
            $strErrMsg = 'PayPal payment was not approved.';
            log_message($strErrMsg, 'debugPaypalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        $objOrder->payment_data = serialize($response);
        $objOrder->save();

        return $response;
    }


    /**
     * Build the PayPal payment object
     * @param $objOrder
     * @param string $strCartId
     * @return \stdClass
     */
    protected function buildPayPalPaymentObject($objOrder, $strCartId='')
    {
        $strCartId = $strCartId ?: ($objOrder->cart_id ?: $objOrder->id);

        $objBillingAddress = $objOrder->getBillingAddress();
        $objShippingAddress = $objOrder->getShippingAddress();

        $arrBillingSubdivision = explode('-', $objBillingAddress->subdivision);
        $arrShippingSubdivision = explode('-', $objShippingAddress->subdivision);

        $strComments = 'CARTID:' . $strCartId;
        $strComments .= ', BILLING ADDRESS:'. $objBillingAddress->firstname .' '. $objBillingAddress->lastname;
        $strComments .= ' '. $objBillingAddress->street_1 . ' ' . $objBillingAddress->street_2 . ' ' . $objBillingAddress->street_3;
        $strComments .= ' '. $objBillingAddress->city . ',  ' . $arrBillingSubdivision[1] . ' ' . $objBillingAddress->postal;
        $strComments .= ' '. $arrBillingSubdivision[0];
        $strComments .= ' EMAIL: '. strtoupper($objBillingAddress->email);
        $strComments .= ' PHONE: '. strtoupper($objBillingAddress->phone);

        $strComments .= ', SHIPPING ADDRESS:'. $objShippingAddress->firstname .' '. $objShippingAddress->lastname;
        $strComments .= ' '. $objShippingAddress->street_1 . ' ' . $objShippingAddress->street_2 . ' ' . $objShippingAddress->street_3;
        $strComments .= ' '. $objShippingAddress->city . ',  ' . $arrShippingSubdivision[1] . ' ' . $objShippingAddress->postal;
        $strComments .= ' '. $arrShippingSubdivision[0];

        $strComments .= ' PRODUCTS: ';

        foreach ($objOrder->getItems() as $objProduct)
        {
            $strComments .=  $objProduct->name .', ' . $objProduct->sku . '|';
        }

        // cURL data as object (for JSON)
        $objData = new \stdClass();
        $objData->intent = 'sale';

        // Redirect URLs
        $strUrlStart = 'http'.(\Environment::get('ssl') ? 's' : '').'://'.\Environment::get('host').'/system/modules/isotope_paypalexpress/scripts/';
        $objData->redirect_urls = new \stdClass();
        $objData->redirect_urls->return_url = $strUrlStart.'return_url.php';
        $objData->redirect_urls->cancel_url = $strUrlStart.'cancel_url.php';

        // Payer
        $objData->payer = new \stdClass();
        $objData->payer->payment_method = 'paypal';

        // Transaction
        $objTransaction = new \stdClass();
        $objTransaction->description = $strComments;
        $objTransaction->invoice_number = $objOrder->id; // Todo: generate document_number now?
        $objTransaction->custom = ''; // Todo: add custom note? (max length is 256)

        // Amount
        $objTransaction->amount = new \stdClass();
        $objTransaction->amount->total = strval($objOrder->getTotal());
        $objTransaction->amount->currency = Isotope::getConfig()->currency;
        $objTransaction->amount->details = new \stdClass();
        $objTransaction->amount->details->subtotal = strval($objOrder->subtotal);
        // Todo: add shipping, tax, shipping_discount, etc.

        // Item List
        $arrItems = array();
        $objTransaction->item_list = new \stdClass();

        foreach ($objOrder->getItems() as $objItem)
        {
            $objLineItem = new \stdClass();
            $objLineItem->quantity = $objItem->quantity;
            $objLineItem->name = html_entity_decode($objItem->name);
            $objLineItem->price = $objItem->price;
            $objLineItem->currency = Isotope::getConfig()->currency;
            $objLineItem->tax = $objItem->tax_id ? '1': '0';

            $strDesc = '';
            $arrOptions = $objItem->getConfiguration();

            if (!empty($arrOptions))
            {
                foreach ($arrOptions as $key=>$option)
                {
                    // Todo: Taken from the collection template, but we need to test this...
                    $strDesc .= html_entity_decode($option['label'].': '.strval($option).' | ');
                }
            }

            $objLineItem->description = $strDesc;
            $arrItems[] = $objLineItem;
        }

        $objTransaction->item_list->items = $arrItems;

        // Add transaction to array
        $objData->transactions = array($objTransaction);

        return $objData;
    }


    /**
     * Use output buffer to var dump to a string
     *
     * @param	string
     * @return	string
     */
    public static function varDumpToString($var)
    {
        ob_start();
        var_dump($var);
        $result = ob_get_clean();
        return $result;
    }

}
