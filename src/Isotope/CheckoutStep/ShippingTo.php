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
use Isotope\Model\Address;
use Isotope\Model\Shipping;
use Isotope\Module\Checkout;
use Isotope\Template;
use JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\CombineOrder;
use JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\ShippingSubStep;
use JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\Shop;
use Krabo\IsotopePackagingSlipBundle\Model\Shipping\CombinePackagingSlip;
use Krabo\IsotopePackagingSlipDHLBundle\Model\Shipping\DHLParcelShop;


class ShippingTo extends CheckoutStep implements IsotopeCheckoutStep {

    /**
     * Shipping options.
     * @var array
     */
    private $options;

    /**
     * Return true if the checkout step is available
     * @return  bool
     */
    public function isAvailable()
    {
        $isAvailable = Isotope::getCart()->requiresShipping();
        if ($isAvailable) {
            $currentStep = \Haste\Input\Input::getAutoItem('step');
            if ($currentStep == 'billing_address' || $currentStep == 'jvh_combine_order') {
                $isAvailable = false;
            } elseif ($currentStep == 'jvh_shipping' && Input::post('FORM_SUBMIT') != $this->objModule->getFormId()) {
                $isAvailable = false;
            }
        }

        if ($isAvailable) {
            $shippingMethod = Isotope::getCart()->getShippingMethod();
            if ($shippingMethod instanceof Pickup || $shippingMethod instanceof \AppBundle\Model\Shipping\CombineOrder || $shippingMethod instanceof CombinePackagingSlip) {
                $isAvailable = false;
            }
        }

        return $isAvailable;
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
                $varValue = $objWidget->value;
                if ($varValue == 'pickup' && $dhlPickUpMethod = DHLParcelShop::getParcelShopShippingMethod()) {
                    $shippingAddress = Isotope::getCart()->getShippingAddress();
                    if (!$shippingAddress || (!$shippingAddress->dhl_servicepoint_id)) {
                      Isotope::getCart()->setShippingAddress(null);
                    }
                    Isotope::getCart()->setShippingMethod($dhlPickUpMethod);
                } else {
                    Isotope::getCart()->setShippingAddress(null);
                    Isotope::getCart()->setShippingMethod(null);
                }
            }
        }

        $objTemplate                  = new Template('iso_checkout_jvh_shipping_to');
        $objTemplate->headline        = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shipping_to'];
        $objTemplate->message         = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shipping_to_message'];
        $objTemplate->options         = $objWidget->parse();

        return $objTemplate->parse();
    }

    /**
     * Get review information about this step
     * @return  array
     */
    public function review()
    {
        return [];
    }

    private function initializeModules() {
        if (empty($this->options)) {
            $this->options[] = [
                'value' => 'home',
                'label' => $GLOBALS['TL_LANG']['MSC']['jvh_shipping_options']['home'][0]
            ];
            $dhlPickUpMethod = DHLParcelShop::getParcelShopShippingMethod();
            if ($dhlPickUpMethod && $dhlPickUpMethod->enabled && $dhlPickUpMethod->isAvailable()) {
              $this->options[] = [
                'value' => 'pickup',
                'label' => $GLOBALS['TL_LANG']['MSC']['jvh_shipping_options']['pickup'][0]
              ];
            }
        }
        return $this->options;
    }

    private function getSelectedOption(): string {
        $this->initializeModules();
        $shippingMethod = Isotope::getCart()->getShippingMethod();
        $shippingAddress = Isotope::getCart()->getShippingAddress();
        if ($shippingMethod && $shippingMethod instanceof DHLParcelShop) {
            return 'pickup';
        } elseif ($shippingAddress) {
            return 'home';
        }
        return '';
    }


}