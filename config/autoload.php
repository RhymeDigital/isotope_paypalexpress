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


/**
 * Register PSR-0 namespace
 */
NamespaceClassLoader::add('Rhyme', 'system/modules/isotope_paypalexpress/library');


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
    //Checkout step
    //'iso_checkout_payment_method'   => 'system/modules/isotope_paypalexpress/templates/checkout',
    
    //PayPal Express
    'paypal_express_js_default'      	=> 'system/modules/isotope_paypalexpress/templates/paypalexpress',
    
    //Payment
    'payment_paypalexpress'      		=> 'system/modules/isotope_paypalexpress/templates/payment',
    
    //Mootools
    //'moo_togglepayment'             => 'system/modules/isotope_paypalexpress/templates/mootools',
    
));
