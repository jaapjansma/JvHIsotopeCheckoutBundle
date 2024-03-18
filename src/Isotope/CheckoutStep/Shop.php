<?php
/**
 * Copyright (C) 2024  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep;

use AppBundle\Model\Shipping\Pickup;
use Contao\Input;
use Isotope\CheckoutStep\CheckoutStep;
use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Isotope;
use Isotope\Module\Checkout;
use Isotope\Template;

class Shop extends CheckoutStep implements IsotopeCheckoutStep {

    /**
     * Shipping options.
     * @var array
     */
    private $options;

    /**
     * @var
     */
    private $shippingMethods;

    /**
     * Return true if the checkout step is available
     * @return  bool
     */
    public function isAvailable()
    {
        $available = Isotope::getCart()->requiresShipping();
        if (!$available) {
            Isotope::getCart()->setShippingMethod(null);
        } else {
            $shippingMethod = Isotope::getCart()->getShippingMethod();
            if (!$shippingMethod instanceof Pickup) {
                $available = false;
            }
        }

        return $available;
    }

    /**
     * Generate the checkout step
     * @return  string
     */
    public function generate()
    {
        $this->initializeModules();

        $objWidget = new $GLOBALS['TL_FFL']['radio'](
            [
                'id'          => $this->getStepClass(),
                'name'        => $this->getStepClass(),
                'mandatory'   => true,
                'options'     => $this->options,
                'value'       => $this->getSelectedOption(),
                'storeValues' => true,
                'tableless'   => true,
            ]
        );

        if (Input::post('FORM_SUBMIT') == $this->objModule->getFormId()) {
            $objWidget->validate();
            $this->blnError = $objWidget->hasErrors();
            if (!$objWidget->hasErrors()) {
                $shop = new \JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\Shop();
                $shippingMethod = $shop->getShippingMethodForOption((int) $objWidget->value);
                $this->blnError = true;
                if ($shippingMethod) {
                    $this->blnError = false;
                    Isotope::getCart()->setShippingMethod($shippingMethod);
                }
            }
        }

        $objTemplate                  = new Template('iso_checkout_shop');
        $objTemplate->headline        = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shop'];
        $objTemplate->message         = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shop_message'];
        $objTemplate->options         = $objWidget->parse();

        return $objTemplate->parse();
    }

    /**
     * Get review information about this step
     * @return  array
     */
    public function review()
    {
        $review = [];
        $shippingMethod = Isotope::getCart()->getShippingMethod();
        if ($shippingMethod instanceof Pickup) {
            return [
                'jvh_shop' => [
                    'headline' => $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shop'],
                    'info' => $shippingMethod->getLabel(),
                    'note' => $shippingMethod->getNote(),
                    'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('jvh_shop'),
                ],
            ];
        }
        return [];
    }

    private function initializeModules() {
        if (empty($this->options)) {
            $shop = new \JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\Shop();
            $this->options = $shop->getOptions();
        }
    }

    private function getSelectedOption(): string {
        $shippingMethod = Isotope::getCart()->getShippingMethod();
        if ($shippingMethod) {
            return $shippingMethod->getId();
        }
        return '';
    }


}