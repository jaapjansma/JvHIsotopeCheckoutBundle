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

use Contao\Input;
use Isotope\CheckoutStep\CheckoutStep;
use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Isotope;
use Isotope\Module\Checkout;
use Isotope\Template;
use JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\CombineOrder;
use JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\Ship;
use JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\ShippingSubStep;
use JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\Shop;
use Krabo\IsotopePackagingSlipDHLBundle\Model\Shipping\DHL;
use Krabo\IsotopePackagingSlipDHLBundle\Model\Shipping\DHLParcelShop;


class Shipping extends CheckoutStep implements IsotopeCheckoutStep {

    /** @var Shop */
    private $shop;

    /**
     * @var CombineOrder
     */
    private $combinedOrder;

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
        $available = Isotope::getCart()->requiresShipping();

        if (!$available) {
            Isotope::getCart()->setShippingMethod(null);
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
                $varValue = $objWidget->value;
                switch($varValue) {
                    case 'shop':
                        $this->selectShop();
                        break;
                    case 'combine':
                        $this->selectCombinedOrder();
                        break;
                    case 'ship':
                        $this->selectShip();
                        break;
                }
            }
        }

        $objTemplate                  = new Template('iso_checkout_jvh_shipping');
        $objTemplate->headline        = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shipping'];
        $objTemplate->message         = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shipping_message'];
        $objTemplate->options         = $objWidget->parse();

        return $objTemplate->parse();
    }

    /**
     * Get review information about this step
     * @return  array
     */
    public function review()
    {
        switch($this->getSelectedOption()) {
            case 'shop':
                return [
                    'jvh_shipping' => [
                        'headline' => $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shipping'],
                        'info' => $GLOBALS['TL_LANG']['MSC']['jvh_shipping_options']['shop'][0],
                        'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('jvh_shipping'),
                    ],
                ];
                break;
            case 'combine':
                return [
                    'jvh_shipping' => [
                        'headline' => $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shipping'],
                        'info' => $GLOBALS['TL_LANG']['MSC']['jvh_shipping_options']['combine'][0],
                        'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('jvh_shipping'),
                    ],
                ];
                break;
        }
        return [];
    }

    private function selectShop() {
        $firstShopShippingMethod = $this->shop->getFirstShopShippingMethod();
        $this->blnError = true;
        if (!empty($firstShopShippingMethod)) {
            $this->blnError = false;
            Isotope::getCart()->combined_packaging_slip_id = '';
            Isotope::getCart()->setShippingMethod($firstShopShippingMethod);
            Isotope::getCart()->setShippingAddress(null);
        }
    }

    private function selectShip() {
        Isotope::getCart()->combined_packaging_slip_id = '';
        $shippingMethod = Isotope::getCart();
        if (!$shippingMethod || (!$shippingMethod instanceof DHL && !$shippingMethod instanceof DHLParcelShop)) {
          //Isotope::getCart()->setShippingMethod(null);
          //Isotope::getCart()->setShippingAddress(null);
        }
    }

    private function selectCombinedOrder() {
        $shippingMethod = $this->combinedOrder->getShippingMethod();
        $this->blnError = true;
        if (!empty($shippingMethod)) {
            $this->blnError = false;
            Isotope::getCart()->combined_packaging_slip_id = '';
            Isotope::getCart()->setShippingMethod($shippingMethod);
            Isotope::getCart()->setShippingAddress(null);
        }
    }

    private function initializeModules() {
        if (empty($this->options)) {
            $this->shop = new Shop();
            $this->combinedOrder = new CombineOrder();

            if ($this->combinedOrder->isAvailable()) {
                $this->options[] = [
                    'value' => 'combine',
                    'label' => $GLOBALS['TL_LANG']['MSC']['jvh_shipping_options']['combine'][0]
                ];
            }
            $this->options[] = [
                'value' => 'ship',
                'label' => $GLOBALS['TL_LANG']['MSC']['jvh_shipping_options']['ship'][0]
            ];
            if ($this->shop->isAvailable()) {
                $this->options[] = [
                    'value' => 'shop',
                    'label' => $GLOBALS['TL_LANG']['MSC']['jvh_shipping_options']['shop'][0],
                    'default' => $this->shop->isSelected(),
                ];
            }
        }
        return $this->options;
    }

    private function getSelectedOption(): string {
        $this->initializeModules();
        if ($this->shop && $this->shop->isAvailable() && $this->shop->isSelected()) {
            return 'shop';
        } elseif ($this->combinedOrder && $this->combinedOrder->isAvailable() && $this->combinedOrder->isSelected()) {
            return 'combine';
        } elseif (Isotope::getCart()->getBillingAddress()) {
            return 'ship';
        }
        return '';
    }


}