<?php

/**
 * Copyright (C) 2018 Rhyme Digital, LLC.
 * 
 * @link		http://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

// Set the script name
define('TL_SCRIPT', 'create_payment.php');

// Initialize the system
define('TL_MODE', 'FE');
require __DIR__ . '/../../../initialize.php';

$objModel = \Rhyme\Helper\PayPalExpressHelper::getPaymentModule();
echo $objModel !== null ? $objModel->createPayment() : '{}';