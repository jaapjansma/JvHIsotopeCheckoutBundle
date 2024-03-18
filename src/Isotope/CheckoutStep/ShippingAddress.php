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
use Contao\System;
use Isotope\Isotope;
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


}