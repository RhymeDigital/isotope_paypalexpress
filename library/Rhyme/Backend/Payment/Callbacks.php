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


class Callbacks extends \Backend
{

    /**
     * Get custom templates
     *
     * @param \DataContainer $dc
     *
     * @return array
     */
    public function getCustomTemplates(\DataContainer $dc)
    {
        return $this->getTemplateGroup('payment_');
    }

    /**
     * Get custom templates
     *
     * @param \DataContainer $dc
     *
     * @return array
     */
    public function getPayPalExpressJsTemplates(\DataContainer $dc)
    {
        return $this->getTemplateGroup('paypal_express_js_');
    }

}
