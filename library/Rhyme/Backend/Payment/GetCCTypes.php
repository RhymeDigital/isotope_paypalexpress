<?php

/**
 * Isotope eCommerce for Contao Open Source CMS
 *
 * Copyright (C) 2009-2014 terminal42 gmbh & Isotope eCommerce Workgroup
 *
 * @package    Isotope
 * @link       http://isotopeecommerce.org
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */

namespace Rhyme\Backend\Payment;

use Isotope\Model\Payment;


class GetCCTypes extends \Backend
{

    /**
     * Get allowed CC types and return them as array
     *
     * @param \DataContainer $dc
     *
     * @return array
     */
    public function run(\DataContainer $dc)
    {
        $arrCCTypes = array();

        /** @type Payment $objPayment */
        if (($objPayment = Payment::findByPk($dc->id)) !== null) {

            try {
                foreach ($objPayment->getAllowedCCTypes() as $type) {
                    $arrCCTypes[$type] = $GLOBALS['TL_LANG']['CCT'][$type];
                }

                return $arrCCTypes;

            } catch (\Exception $e) {
            }
        }

        return $arrCCTypes;
    }

}
