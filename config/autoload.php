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


/**
 * Register PSR-0 namespace
 */
NamespaceClassLoader::add('Rhyme', 'system/modules/isotope_paypalexpress/library');


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
    //PayPal Express
    'paypal_express_js_default'      	=> 'system/modules/isotope_paypalexpress/templates/paypalexpress',
    
    //Payment
    'payment_paypalexpress'      		=> 'system/modules/isotope_paypalexpress/templates/payment',
    
));
