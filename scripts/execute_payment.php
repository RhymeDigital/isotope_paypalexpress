<?php

/**
 * Copyright (C) 2018 Rhyme Digital, LLC.
 * 
 * @link		http://rhyme.digital
 * @license		http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */

// Set the script name
define('TL_SCRIPT', 'execute_payment.php');

// Initialize the system
define('TL_MODE', 'FE');
require __DIR__ . '/../../../initialize.php';

echo \Rhyme\Helper\PayPalExpressHelper::executePayment();