<?php

/**
 * PayPal Express for Isotope eCommerce
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
use Isotope\Model\ProductCollection as ProductCollectionModel;


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
        // See if the payment was approved
        if (!$objOrder->payment_data)
        {
            return false;
        }

        $arrPaymentData = deserialize($objOrder->payment_data, true);
        $objPaymentData = json_decode($arrPaymentData[0]);

        if (!$objPaymentData || $objPaymentData->state != 'approved')
        {
            return false;
        }

        $objOrder->checkout();
        $objOrder->updateOrderStatus($this->new_order_status);

        return true;
    }


    /**
     * Return a html form for checkout or false
     * @param   IsotopeProductCollection    $objOrder   The order being places
     * @param   \Module                     $objModule  The checkout module instance
     * @return  bool
     */
    public function checkoutForm(IsotopeProductCollection $objOrder, \Module $objModule)
    {
        $objCart = Isotope::getCart();
        if ($objCart === null)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not load cart.';
            log_message($strErrMsg, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);
        }

        // See if the payment was approved
        if ($objCart->payment_data)
        {
            $arrPaymentData = deserialize($objCart->payment_data, true);
            $objPaymentData = json_decode($arrPaymentData[0]);
            if ($objPaymentData && $objPaymentData->state == 'approved')
            {
                // Make sure the order has the correct payment data
                $objOrder->payment_data = $objCart->payment_data;
                $objOrder->save();

                return false;
            }
        }

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
     * Set up a "payment" for PayPal Express
     * @return string
     */
    public function createPayment()
    {
        $objCart = Isotope::getCart();
        if ($objCart === null)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not load cart.';
            log_message($strErrMsg, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        // Get the order
        $objOrder = $objCart->getDraftOrder();

        // Build the PayPal payment object
        $objData = static::buildPayPalPaymentObject($objOrder, $objOrder->id);

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
            log_message($strErrMsg, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        $objResponse = json_decode($response);
        if (!is_object($objResponse) || !$objResponse->id)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not get payment from PayPal';
            log_message($strErrMsg, 'debugPayPalExpress.log');
            log_message($response, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        // Save the PayPal payment info
        $objOrder->paypal_payment_id = $objResponse->id;
        $objOrder->save();
        $objCart->paypal_payment_id = $objResponse->id;
        $objCart->save();

        return $response;
    }


    /**
     * Execute a "payment" for PayPal Express
     */
    public function executePayment()
    {
        $objCart = Isotope::getCart();
        if ($objCart === null)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not load cart.';
            log_message($strErrMsg, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        // Get the order
        $objOrder = $objCart->getDraftOrder();

        // Get the PayPal payment object
        $objPayment = static::buildPayPalPaymentObject($objOrder, $objOrder->id);

        // Build the PayPal payment "execute" object
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
            log_message($strErrMsg, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        $objResponse = json_decode($response);
        if (!is_object($objResponse) || !$objResponse->id)
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not get payment from PayPal';
            log_message($strErrMsg, 'debugPayPalExpress.log');
            log_message($response, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }
        elseif ($objResponse->state != 'approved')
        {
            // Todo: handle errors better
            $strErrMsg = 'PayPal payment was not approved.';
            log_message($strErrMsg, 'debugPayPalExpress.log');
            log_message($response, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return '{}';
        }

        // Save the PayPal info
        $objOrder->payment_data = serialize(array($response));
        $objOrder->save();
        $objCart->payment_data = serialize(array($response));
        $objCart->save();

        return $response;
    }


    /**
     * Build the PayPal payment object
     * @param IsotopeProductCollection $objOrder
     * @param string $strOrderId
     * @return \stdClass
     */
    protected function buildPayPalPaymentObject(IsotopeProductCollection $objOrder, $strOrderId='')
    {
        global $objPage;

        $strOrderId = $strOrderId ?: $objOrder->id;

        $objBillingAddress = $objOrder->getBillingAddress();
        $objShippingAddress = $objOrder->getShippingAddress();

        $arrBillingSubdivision = explode('-', $objBillingAddress->subdivision);
        $arrShippingSubdivision = explode('-', $objShippingAddress->subdivision);

        $strComments = 'ORDERID:' . $strOrderId;
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
        $objData->redirect_urls->return_url = $strUrlStart.'return_payment.php?payment_mod='.$this->id.'&page_id='.$objPage->id;
        $objData->redirect_urls->cancel_url = $strUrlStart.'cancel_payment.php?payment_mod='.$this->id.'&page_id='.$objPage->id;

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

        // Add surcharges too
        foreach ($objOrder->getSurcharges() as $objSurcharge)
        {
            if (!$objSurcharge->addToTotal)
            {
                continue;
            }

            // Todo: add more surcharge types (hook maybe?)
            switch ($objSurcharge->type)
            {
                case 'shipping':
                    $objTransaction->amount->details->shipping = strval($objSurcharge->total_price);
                    break;

                case 'tax':
                    $objTransaction->amount->details->tax = strval($objSurcharge->total_price);
                    break;
            }
        }

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

        // !HOOK: alter data if needed
        if (isset($GLOBALS['ISO_HOOKS']['paypalExpressBuildPayment']) && is_array($GLOBALS['ISO_HOOKS']['paypalExpressBuildPayment'])) {
            foreach ($GLOBALS['ISO_HOOKS']['paypalExpressBuildPayment'] as $callback) {
                $objCallback = \System::importStatic($callback[0]);
                $objCallback->{$callback[1]}($objData, $objOrder, $this);
            }
        }

        return $objData;
    }


    /**
     * Cancel a "payment" for PayPal Express
     */
    public function cancelPayment()
    {

    }


    /**
     * Return a "payment" for PayPal Express
     */
    public function returnPayment()
    {

    }


    /**
     * Return the PayPal form (used for XCheckout)
     *
     * @param \Module $objCheckoutModule
     * @param IsotopeCheckoutStep $objPaymentStep
     * @return string
     */
    public function paymentForm(\Module $objCheckoutModule, IsotopeCheckoutStep $objPaymentStep)
    {
        return '';
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
