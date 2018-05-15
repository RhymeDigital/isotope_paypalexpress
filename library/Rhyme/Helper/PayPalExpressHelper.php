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
     * Get the payment module
     * @return \Model|PayPalExpressModel|null
     */
    public static function getPaymentModule()
    {
        if (!static::loadLastPage())
        {
            return null;
        }

        // Make sure we have the right payment module - Todo: more security here?
        $objPaymentModule = PaymentModel::findByPk(\Input::get('payment_mod'));
        if ($objPaymentModule === null || !($objPaymentModule instanceof PayPalExpressModel) || !$objPaymentModule->isAvailable())
        {
            // Todo: handle errors better
            $strErrMsg = 'Could not load payment method with ID "'.\Input::get('payment_mod').'"';
            log_message($strErrMsg, 'debugPayPalExpress.log');
            \System::log($strErrMsg, __METHOD__, TL_ERROR);

            return null;
        }

        return $objPaymentModule;
    }


    /**
     * Load the last page
     * @return boolean
     */
    protected static function loadLastPage()
    {
        global $objPage;

        $objPage = \PageModel::findPublishedByIdOrAlias(\Input::get('page_id'));
        if ($objPage !== null)
        {
            $objPage->current()->loadDetails();
            return true;
        }

        // Todo: handle errors better
        $strErrMsg = 'Could not load current page (ID "'.\Input::get('payment_mod').'"")';
        log_message($strErrMsg, 'debugPayPalExpress.log');
        \System::log($strErrMsg, __METHOD__, TL_ERROR);

        return false;
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