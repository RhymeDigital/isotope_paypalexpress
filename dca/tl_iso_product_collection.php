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
 * Fields
 */
$GLOBALS['TL_DCA']['tl_iso_product_collection']['fields']['ipn_track_id'] = array
(
    'sql'   => "varchar(255) NOT NULL default ''"
);