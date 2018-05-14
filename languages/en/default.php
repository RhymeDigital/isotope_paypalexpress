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
 * Payment modules
 */
$GLOBALS['TL_LANG']['MODEL']['tl_iso_payment.paypalexpress'] = array('PayPal Express', 'The PayPal Express module is a full service credit card gateway, a more robust solution for most e-commerce sites.');

/**
 * Error messages
 */
$GLOBALS['TL_LANG']['ERR']['cc_num']						= 'Please provide a valid credit card number.';
$GLOBALS['TL_LANG']['ERR']['cc_type']						= 'Please select a credit card type.';
$GLOBALS['TL_LANG']['ERR']['cc_exp']						= 'Please provide a credit card expiration date in the mm/yy format.';
$GLOBALS['TL_LANG']['ERR']['cc_ccv']						= 'Please provide a card code verification number (3 or 4 digits found on the front or back of the card).';
$GLOBALS['TL_LANG']['ERR']['cc_match']						= 'Your credit card number does not match the selected credit card type.';
