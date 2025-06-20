<?php
/**
 * Copyright (C) 2022  Jaap Jansma (jaap.jansma@civicoop.org)
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

use JvH\IsotopeCheckoutBundle\EventListener\ProductionCollectionListener;

$GLOBALS['ISO_HOOKS']['updateDraftOrder'][] = [ProductionCollectionListener::class, 'updateDraftOrder'];
$GLOBALS['ISO_HOOKS']['findSurchargesForCollection'][] = [ProductionCollectionListener::class, 'findSurchargesForCollection'];
$GLOBALS['ISO_HOOKS']['addCollectionToTemplate'][] = [ProductionCollectionListener::class, 'addCollectionToTemplate'];

unset($GLOBALS['ISO_CHECKOUTSTEP']['address']);
unset($GLOBALS['ISO_CHECKOUTSTEP']['shipping']);
unset($GLOBALS['ISO_CHECKOUTSTEP']['review']);
$jvhCheckoutSteps['billing_address'][] = 'JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\BillingAddress';
$jvhCheckoutSteps['jvh_shipping'][] = 'JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping';
$jvhCheckoutSteps['jvh_shop'][] = 'JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shop';
$jvhCheckoutSteps['jvh_combine_order'][] = 'JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\CombineWithOrder';
$jvhCheckoutSteps['jvh_shipping_to'][] = 'JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\ShippingTo';
$jvhCheckoutSteps['jvh_dhl_pickup'][] = 'JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\DhlPickup';
$jvhCheckoutSteps['shipping_address'][] = 'JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\ShippingAddress';
$jvhCheckoutSteps['jvh_shipping_method'][] = 'JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\ShippingMethod';
$GLOBALS['ISO_CHECKOUTSTEP'] = array_merge($jvhCheckoutSteps, $GLOBALS['ISO_CHECKOUTSTEP']);
$GLOBALS['ISO_CHECKOUTSTEP']['payment'] = ['JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\PaymentMethod'];
$GLOBALS['ISO_CHECKOUTSTEP']['jvh_review'] = ['JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\OrderInfo', 'Isotope\CheckoutStep\OrderProducts'];