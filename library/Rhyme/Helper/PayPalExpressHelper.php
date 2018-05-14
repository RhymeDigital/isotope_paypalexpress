<?php

/**
 * Copyright (C) 2018 Rhyme Digital, LLC.
 * 
 * @link		http://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

namespace Rhyme\Helper;

use Isotope\Isotope;
use Isotope\Model\Payment as PaymentModel;
use Isotope\Model\ProductCollection\Order as OrderModel;

use Rhyme\Model\Payment\PayPalExpress as PayPalExpressModel;


/**
 * Class PayPalExpressHelper
 * @package Rhyme\Helper
 */
class PayPalExpressHelper extends \Controller 
{
	
	/**
	 * Set up a "payment" for PayPal Express
	 * @return string
	 */
	public static function createPayment()
	{
		// Get the page ID and load it for Isotope cart - Todo: more security here?
		global $objPage;
		$objPage = \PageModel::findPublishedByIdOrAlias(\Input::get('page_id'));
		if ($objPage === null)
		{
			// Todo: handle errors better
			$strErrMsg = 'Could not load page with ID "'.\Input::get('page_id').'".';
			log_message($strErrMsg, 'debugPaypalExpress.log');
			\System::log($strErrMsg, __METHOD__, TL_ERROR);
			
			return '';
		}
		
		$objPage->current()->loadDetails();
		
		$objOrder = Isotope::getCart();
		if ($objOrder === null)
		{
			// Todo: handle errors better
			$strErrMsg = 'Could not load cart.';
			log_message($strErrMsg, 'debugPaypalExpress.log');
			\System::log($strErrMsg, __METHOD__, TL_ERROR);
			
			return '';
		}
		
		$strCartId = $objOrder->id;
		
		// Make sure we have the right payment module - Todo: more security here?
		$objPaymentModule = PaymentModel::findByPk(\Input::get('payment_mod'));
		if ($objPaymentModule === null || !($objPaymentModule instanceof PayPalExpressModel) || !$objPaymentModule->isAvailable()) 
		{
			// Todo: handle errors better
			$strErrMsg = 'Could not load payment method  with ID "'.\Input::get('payment_mod').'"';
			log_message($strErrMsg, 'debugPaypalExpress.log');
			\System::log($strErrMsg, __METHOD__, TL_ERROR);
			
			return '';
		}
		
		// Check to see if we can grab the order instead
		$objCheck = OrderModel::findByPk(intval($objOrder->cart_id));
		if ($objCheck !== null)
		{
			$objOrder = $objCheck;
		}
		
        $arrPaymentData = deserialize($objOrder->payment_data, true);
        
        $objBillingAddress = $objOrder->getBillingAddress();
        $objShippingAddress = $objOrder->getShippingAddress();

        $arrBillingSubdivision = explode('-', $objBillingAddress->subdivision);
        $arrShippingSubdivision = explode('-', $objShippingAddress->subdivision);
        
        $strCardType = '';

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
        $objTransaction->amount->total = strval($objOrder->total);
        $objTransaction->amount->currency = 'USD';
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
		            $strDesc .= html_entity_decode($value['label'].': '.strval($value).' | ');
	            }
            }
            
            $objLineItem->description = $strDesc;
            $arrItems[] = $objLineItem;
        }
        
        $objTransaction->item_list->items = $arrItems;
        
        // Add transaction to array
        $objData->transactions = array($objTransaction);
        
        // cURL headers
        $arrHeaders = array
        (
	        'Content-Type: application/json',
	        'Authorization: Basic ' . base64_encode('AfTcgVWo6h065Br3zWQ5iavHoUN_yFI6EhFSFkhIpGEmNGZ7t1YedZsDDLHFZzkqhBsLdsG3tqEsU16N:EFFehvA0Q3QOxvX8hH1_YjDNtUUC1_Afbg_8clBS2RSvqDT065X9wbYfAWds1vuNQPbib5X5OsMVZIU_'),
        );

        // cURL request
        $curl_request = curl_init('https://api' . ($objPaymentModule->debug ? '.sandbox.' : '.') . 'paypal.com/v1/payments/payment');
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
			
	        return '';
        }
        
        $objResponse = json_decode($response);
        if (!is_object($objResponse) || !$objResponse->id)
        {
			// Todo: handle errors better
			$strErrMsg = 'Could not get payment from PayPal';
			log_message($strErrMsg, 'debugPaypalExpress.log');
			\System::log($strErrMsg, __METHOD__, TL_ERROR);
			
	        return '';
        }
        
		return $response;
	}


    /**
     * Execute a "payment" for PayPal Express
     */
    public static function executePayment()
    {

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