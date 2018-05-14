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
     * @param array
     */
    public function __construct(\Database\Result $objResult = null)
    {
	    global $objPage;
	    \Session::getInstance()->set('LAST_PAGE_VISITED', $objPage->id); // Fix for accessing cart when not on a Contao page (in PayPalExpressHelper)
	    
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
        //$objOrder->checkout();
        //$objOrder->updateOrderStatus($this->new_order_status);

        return true;
    }



    /**
     * Return the PayPal form.
     *
     * @access public
     * @param Module - The checkout Module
     * @return string
     */
    public function paymentForm(\Module $objCheckoutModule, IsotopeCheckoutStep $objPaymentStep)
    {
        // Build JS template
        $objTemplate = new \Isotope\Template('paypal_express_js_default'); // Todo: make this configurable
        $objTemplate->setData($this->row());
        $GLOBALS['TL_BODY']['paypal_express_js'.$this->id] = $objTemplate->parse();
        
        // Build payment template
        $objTemplate = new \Isotope\Template('payment_paypalexpress');
        $objTemplate->setData($this->row());
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

}
