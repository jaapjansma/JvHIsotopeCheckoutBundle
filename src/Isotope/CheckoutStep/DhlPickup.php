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

class DhlPickup extends CheckoutStep implements IsotopeCheckoutStep {

    public const DHL_PARCEL_SHOP_SHIPPING_METHOD_ID = 158;

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
            if (!$shippingMethod || ($shippingMethod->getId() != self::DHL_PARCEL_SHOP_SHIPPING_METHOD_ID) ) {
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

        $objWidget = new $GLOBALS['TL_FFL']['hidden'](
            [
                'id'          => 'pickup_servicepoint_id',
                'name'        => 'pickup_servicepoint_id',
                'mandatory'   => true,
                'value'       => $this->getSelectedOption(),
                'storeValues' => true,
                'tableless'   => true,
            ]
        );

        if (Input::post('FORM_SUBMIT') == $this->objModule->getFormId()) {
            $objWidget->validate();
            $this->blnError = $objWidget->hasErrors();
            if (!$objWidget->hasErrors()) {
                $objAddress = Address::createForProductCollection(Isotope::getCart(), Isotope::getConfig()->getShippingFields(), false, false);
                $billingAddress = Isotope::getCart()->getBillingAddress();
                $objAddress->salutation = $billingAddress->salutation;
                $objAddress->firstname = $billingAddress->firstname;
                $objAddress->lastname = $billingAddress->lastname;
                $objAddress->email = $billingAddress->email;
                $objAddress->phone = $billingAddress->phone;
                $objAddress->company = $GLOBALS['TL_LANG']['MSC']['shipping_dhl_pickup'].' '. \Input::post('dhlpickup_servicepoint_name');
                $objAddress->street_1 = \Input::post('dhlpickup_servicepoint_street');
                $objAddress->housenumber = \Input::post('dhlpickup_servicepoint_housenumber');
                $objAddress->postal = \Input::post('dhlpickup_servicepoint_postal');
                $objAddress->city = \Input::post('dhlpickup_servicepoint_city');
                $objAddress->country = 'nl';
                $objAddress->dhl_servicepoint_id = $objWidget->value;
                $objAddress->save();
                Isotope::getCart()->setShippingAddress($objAddress);
                $dhlPickUpMethod = Shipping::findByPk(DhlPickup::DHL_PARCEL_SHOP_SHIPPING_METHOD_ID);
                Isotope::getCart()->setShippingMethod($dhlPickUpMethod);
            }
        }

        $router = \System::getContainer()->get('router');
        $objTemplate                  = new Template('iso_checkout_jvh_dhl_pickup');
        $objTemplate->headline        = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_dhl_pickup'];
        $objTemplate->message         = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_dhl_pickup_message'];
        $objTemplate->selectParcelShopUrl = $router->generate('isotopepackagingslipdhl_selectparcelshop');
        $shippingAddress = Isotope::getCart()->getShippingAddress();
        if ($shippingAddress && $shippingAddress->dhl_servicepoint_id) {
            $objTemplate->dhl_servicepoint_id = $shippingAddress->dhl_servicepoint_id;
        }

        return $objTemplate->parse();
    }

    /**
     * Get review information about this step
     * @return  array
     */
    public function review()
    {
        $objAddress = Isotope::getCart()->getDraftOrder()->getShippingAddress();
        if ($objAddress && !empty($objAddress->dhl_servicepoint_id)) {
            return [
                'shipping_address' => [
                    'headline' => $GLOBALS['TL_LANG']['MSC']['checkout_jvh_dhl_pickup'],
                    'info' => $objAddress->generate(Isotope::getConfig()->getShippingFieldsConfig()),
                    'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('jvh_dhl_pickup'),
                ]
            ];
        }
        return [];
    }

    private function initializeModules() {
    }

    private function getSelectedOption(): string {
        $shippingAddress = Isotope::getCart()->getShippingAddress();
        if (!$shippingAddress && (!empty($shippingAddress->dhl_servicepoint_id))) {
            return $shippingAddress->dhl_servicepoint_id;
        }
        return '';
    }


}