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
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['paypalexpress'] = str_replace(array(',paypal_account;'), array(',paypalClientId,paypalSecret;{template_legend},customTpl,paypalJsTpl,paypalPaymentPrompt;'), $GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['paypal']);


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['allowed_cc_types'] = array
(
    'label'                 => &$GLOBALS['TL_LANG']['tl_iso_payment']['allowed_cc_types'],
    'exclude'               => true,
    'filter'                => true,
    'inputType'             => 'checkbox',
    'options_callback'      => array('Rhyme\Backend\Payment\GetCCTypes', 'run'),
    'eval'                  => array('multiple'=>true, 'tl_class'=>'clr'),
    'sql'                   => "text NULL",
);

$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['customTpl'] = $GLOBALS['TL_DCA']['tl_iso_payment']['fields']['customTpl'] ?: array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_iso_payment']['customTpl'],
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => array('Rhyme\Backend\Payment\Callbacks', 'getCustomTemplates'),
    'eval'                    => array('tl_class'=>'w50', 'includeBlankOption'=>true),
    'sql'                     => "varchar(128) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paypalJsTpl'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_iso_payment']['paypalJsTpl'],
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => array('Rhyme\Backend\Payment\Callbacks', 'getPayPalExpressJsTemplates'),
    'eval'                    => array('tl_class'=>'w50', 'includeBlankOption'=>true),
    'sql'                     => "varchar(128) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paypalClientId'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_iso_payment']['paypalClientId'],
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => array('tl_class'=>'w50', 'mandatory'=>true),
    'sql'                     => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paypalSecret'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_iso_payment']['paypalSecret'],
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => array('tl_class'=>'w50', 'mandatory'=>true),
    'sql'                     => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['paypalPaymentPrompt'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_iso_payment']['paypalPaymentPrompt'],
    'exclude'                 => true,
    'search'                  => true,
    'inputType'               => 'textarea',
    'eval'                    => array('mandatory'=>false, 'rte'=>'tinyMCE', 'tl_class'=>'clr'),
    'sql'                     => "text NULL",
);