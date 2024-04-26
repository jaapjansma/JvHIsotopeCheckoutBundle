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
use Contao\System;
use Isotope\Isotope;
use Isotope\Model\Address as AddressModel;
use Isotope\Module\Checkout;
use Krabo\IsotopePackagingSlipBundle\Model\Shipping\CombinePackagingSlip;
use Krabo\IsotopePackagingSlipDHLBundle\Model\Shipping\DHL;

class ShippingAddress extends \Isotope\CheckoutStep\ShippingAddress {

    /**
     * Load data container and create template
     *
     * @param Checkout $objModule
     */
    public function __construct(Checkout $objModule) {
        parent::__construct($objModule);
        $this->Template->pro6pp_apikey = System::getContainer()
            ->getParameter('jvh.isotope-checkout-bundle.pro6pp_apikey');
    }

    /**
     * Returns true if the current cart has shipping
     *
     * @inheritdoc
     */
    public function isAvailable()
    {
        $isAvailable = parent::isAvailable();
        if ($isAvailable) {
            $currentStep = \Haste\Input\Input::getAutoItem('step');
            if ($currentStep == 'billing_address' || $currentStep == 'jvh_shipping' || $currentStep == 'jvh_combine_order') {
                $isAvailable = false;
            } elseif ($currentStep == 'jvh_shipping_to' && Input::post('FORM_SUBMIT') != $this->objModule->getFormId()) {
                $isAvailable = false;
            }
        }
        if ($isAvailable) {
            $shippingMethod = Isotope::getCart()->getShippingMethod();
            $shippingAddress = Isotope::getCart()->getShippingAddress();
            if ($shippingAddress && !empty($shippingAddress->sendcloud_servicepoint_id) || !empty($shippingAddress->dhl_servicepoint_id)) {
                $isAvailable = false;
            } elseif ($shippingMethod instanceof CombinePackagingSlip || $shippingMethod instanceof Pickup || ($shippingMethod instanceof DHL && $shippingMethod->getId() == DhlPickup::DHL_PARCEL_SHOP_SHIPPING_METHOD_ID)) {
                $isAvailable = false;
            }
        }
        return $isAvailable;
    }

    /**
     * @inheritdoc
     */
    public function review()
    {
        $objAddress = Isotope::getCart()->getDraftOrder()->getShippingAddress();

        return array('shipping_address' => array
        (
            'headline' => $GLOBALS['TL_LANG']['MSC']['shipping_address'],
            'info'     => $objAddress->generate(Isotope::getConfig()->getShippingFieldsConfig()),
            'edit'     => $this->isSkippable() ? '' : Checkout::generateUrlForStep('shipping_address'),
        ));
    }

    /**
     * Get addresses for the current member
     *
     * @return \Isotope\Model\Address[]
     */
    protected function getAddresses()
    {
        $adresses = parent::getAddresses();
        return array_filter($adresses, function($address) {
            return empty($address->dhl_servicepoint_id);
        });
    }

    /**
     * Get available address options
     *
     * @param array $arrFields
     *
     * @return array
     */
    protected function getAddressOptions($arrFields = null)
    {
        $arrOptions = parent::getAddressOptions(Isotope::getConfig()->getShippingFieldsConfig());
        foreach ($arrOptions as $index => $arrOption) {
            if ($arrOption['value'] == '-1') {
                // Dit is het factuuradres. Toon ook het factuuradres.
                $arrOptions[$index]['label'] = '<div class="use_billing_address_header">' . $arrOption['label'] . '</div>' . Isotope::getCart()
                        ->getBillingAddress()
                        ->generate($arrFields);
            }
        }
        return $arrOptions;
    }

  protected function setAddress(AddressModel $objAddress): void {
    $arrShopCountries = Isotope::getConfig()->getBillingCountries();
    if (!\in_array($objAddress->country, $arrShopCountries, TRUE)) {
      if (Isotope::getCart()->config_id == 2) {
        Isotope::getCart()->config_id = 3;
        Isotope::getCart()->save();
      }
      elseif (Isotope::getCart()->config_id == 3) {
        Isotope::getCart()->config_id = 2;
        Isotope::getCart()->save();
      }
    }
    parent::setAddress($objAddress);
  }


}