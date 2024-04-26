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
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Isotope\CheckoutStep\CheckoutStep;
use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Shipping;
use Isotope\Module\Checkout;
use Isotope\Template;
use JvH\IsotopeCheckoutBundle\Validator;
use Krabo\IsotopePackagingSlipBundle\Helper\IsotopeHelper;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel;
use Krabo\IsotopePackagingSlipDHLBundle\DHL\EndPoints\ServicePoints;
use Mvdnbrk\DhlParcel\Exceptions\DhlParcelException;
use Mvdnbrk\DhlParcel\Resources\ServicePoint as ServicePointResource;
use Symfony\Component\Cache\CacheItem;

class DhlPickup extends CheckoutStep implements IsotopeCheckoutStep {

    public const DHL_PARCEL_SHOP_SHIPPING_METHOD_ID = 158;

    /**
     * Return true if the checkout step is available
     * @return  bool
     */
    public function isAvailable()
    {
      $isAvailable = false;
      $dhlPickUpMethod = Shipping::findByPk(self::DHL_PARCEL_SHOP_SHIPPING_METHOD_ID);
      if ($dhlPickUpMethod->enabled) {
        $isAvailable = Isotope::getCart()->requiresShipping();
      }
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
          if (!$shippingMethod || ($shippingMethod->getId() != self::DHL_PARCEL_SHOP_SHIPPING_METHOD_ID) ) {
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
        $strError = '';
        $this->initializeModules();

        $dhlPickUpMethod = Shipping::findByPk(self::DHL_PARCEL_SHOP_SHIPPING_METHOD_ID);
        $objShipper = NULL;
        if ($dhlPickUpMethod->shipper_id) {
            $objShipper = IsotopePackagingSlipShipperModel::findByPk($dhlPickUpMethod->shipper_id);
        }

        $allowShippingDateChange = false;
        if ($objShipper && $objShipper->customer_can_provide_shipping_date) {
            $allowShippingDateChange = TRUE;
        }

        /** @var Widget $objWidget */
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

        $earliestShippingDateStringValue = '';
        $objShipper = null;
        if ($dhlPickUpMethod->shipping_id) {
          $objShipper = IsotopePackagingSlipShipperModel::findByPk($dhlPickUpMethod->shipper_id);
        }
        $earliestShippingDateTimeStamp = IsotopeHelper::getScheduledShippingDate(Isotope::getCart(), $objShipper);
        if (empty(Isotope::getCart()->scheduled_shipping_date) || date('Ymd', Isotope::getCart()->scheduled_shipping_date) < date('Ymd', $earliestShippingDateTimeStamp)) {
            $earliestShippingDateStringValue = date('d-m-Y', $earliestShippingDateTimeStamp);
        } elseif (Isotope::getCart()->scheduled_shipping_date) {
            $earliestShippingDateStringValue = date('d-m-Y', Isotope::getCart()->scheduled_shipping_date);
        }

        $objShippingDateWidget = new $GLOBALS['TL_FFL']['text'](
            [
                'id' => $this->getStepClass() . '_shipping_date',
                'name' => $this->getStepClass() . '_shipping_date',
                'label' => $GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date'][0],
                'description' => $GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date'][1],
                'customTpl' => 'form_jvhshippingdatefield',
                'value' => $earliestShippingDateStringValue,
            ]
        );

        if (Input::post('FORM_SUBMIT') == $this->objModule->getFormId()) {
            $objWidget->validate();
            $this->blnError = $objWidget->hasErrors();
            if (!$objWidget->hasErrors()) {
                if ($allowShippingDateChange) {
                    $earliestShippingDateTimeStamp = IsotopeHelper::getScheduledShippingDate(Isotope::getCart(), $objShipper);
                    $earliestShippingDate = date('d-m-Y', $earliestShippingDateTimeStamp);
                    $objShippingDateWidget->validate();
                    if (!$objShippingDateWidget->hasErrors()) {
                        if (!empty($objShippingDateWidget->value)) {
                            try {
                                $scheduledShippingDate = new \DateTime($objShippingDateWidget->value);
                                $scheduledShippingDate->setTime(23, 59);
                                if (!Validator::isDate($objShippingDateWidget->value)) {
                                    $objShippingDateWidget->addError(sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_error'], $earliestShippingDate));
                                }
                                elseif ($scheduledShippingDate->getTimestamp() < $earliestShippingDateTimeStamp) {
                                    $objShippingDateWidget->addError(sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_error'], $earliestShippingDate));
                                }
                            } catch (\Exception $e) {
                                $objShippingDateWidget->addError(sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_error'], $earliestShippingDate));
                            }
                        }
                        else {
                            $objShippingDateWidget->addError(sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_error'], $earliestShippingDate));
                        }
                    }
                    if ($objShippingDateWidget->hasErrors()) {
                        $this->blnError = TRUE;
                    }
                    else {
                        Isotope::getCart()->scheduled_shipping_date = $scheduledShippingDate->getTimestamp();
                    }
                }
                else {
                    Isotope::getCart()->scheduled_shipping_date = '';
                }
                if (!$this->blnError) {
                    $objAddress = Address::createForProductCollection(Isotope::getCart(), Isotope::getConfig()->getShippingFields(), false, false);
                    $billingAddress = Isotope::getCart()->getBillingAddress();
                    $objAddress->salutation = $billingAddress->salutation;
                    $objAddress->firstname = $billingAddress->firstname;
                    $objAddress->lastname = $billingAddress->lastname;
                    $objAddress->email = $billingAddress->email;
                    $objAddress->phone = $billingAddress->phone;
                    $objAddress->company = $GLOBALS['TL_LANG']['MSC']['shipping_dhl_pickup'] . ' ' . \Input::post('dhlpickup_servicepoint_name');
                    $objAddress->street_1 = \Input::post('dhlpickup_servicepoint_street');
                    $objAddress->housenumber = \Input::post('dhlpickup_servicepoint_housenumber');
                    $objAddress->postal = \Input::post('dhlpickup_servicepoint_postal');
                    $objAddress->city = \Input::post('dhlpickup_servicepoint_city');
                    $objAddress->country = 'nl';
                    $objAddress->dhl_servicepoint_id = $objWidget->value;
                    $objAddress->save();
                    Isotope::getCart()->setShippingAddress($objAddress);
                    Isotope::getCart()->setShippingMethod($dhlPickUpMethod);
                }
            } else {
              $strError = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_dhl_pickup_required'];
            }
        }

        $router = \System::getContainer()->get('router');
        $objTemplate                  = new Template('iso_checkout_jvh_dhl_pickup');
        $objTemplate->headline        = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_dhl_pickup'];
        $objTemplate->message         = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_dhl_pickup_message'];
        $objTemplate->errors = $strError;
        $objTemplate->selectParcelShopUrl = $router->generate('isotopepackagingslipdhl_selectparcelshop');
        $shippingAddress = Isotope::getCart()->getShippingAddress();
        if ($shippingAddress && $shippingAddress->dhl_servicepoint_id) {
            $objTemplate->selectParcelShopUrl = $router->generate('isotopepackagingslipdhl_selectparcelshop', [
                'selectedServicepointId' => $shippingAddress->dhl_servicepoint_id,
            ]);
            $servicePointId = $shippingAddress->dhl_servicepoint_id;
            $objTemplate->dhl_servicepoint_id = $shippingAddress->dhl_servicepoint_id;
            /** @var \Krabo\IsotopePackagingSlipDHLBundle\Factory\DHLConnectionFactoryInterface $dhlConnection */
            $dhlConnection = System::getContainer()->get('krabo.isotope-packaging-slip-dhl.factory');
            /** @var \Symfony\Contracts\Cache\CacheInterface $cache */
            $cache = System::getContainer()->get('cache.system');
            $cacheKey = 'isotopepackagingslipdhl_parcelshop_'.$servicePointId;
            $cachedServicePoint = $cache->get($cacheKey, function() use ($servicePointId, $dhlConnection) {
                $servicepointApi = new ServicePoints($dhlConnection->getClient());
                try {
                    $servicePoint = $servicepointApi->getById($servicePointId);
                } catch (DhlParcelException $ex) {
                    $servicePoint = null;
                }
                $item = new CacheItem();
                $item->set($servicePoint->toArray());
                return $item;
            });
            $servicePoint = $cachedServicePoint->get();
            if ($servicePoint) {
                $objTemplate->dhlpickup_servicepoint_name = $servicePoint['name'];
                $objTemplate->dhlpickup_servicepoint_street = $servicePoint['street'];
                $objTemplate->dhlpickup_servicepoint_housenumber = $servicePoint['number'];
                $objTemplate->dhlpickup_servicepoint_postal = $servicePoint['postal_code'];
                $objTemplate->dhlpickup_servicepoint_city = $servicePoint['city'];
                $objTemplate->dhlpickup_info = $servicePoint['name'] . '<br>' . $servicePoint['street'] . ' ' . $servicePoint['number'].  '<br>' . $servicePoint['postal_code'] . ' ' . $servicePoint['city'];
            }
        }
        $objTemplate->shippingDate = '';
        if ($allowShippingDateChange) {
            $objTemplate->shippingDate = $objShippingDateWidget->parse();
        }

        return $objTemplate->parse();
    }

    /**
     * Get review information about this step
     * @return  array
     */
    public function review()
    {
        $note = Isotope::getCart()->getDraftOrder()->getShippingMethod()->getNote();
        if (Isotope::getCart()->getDraftOrder()->scheduled_shipping_date) {
            $scheduledShippingDate = date('d-m-Y', Isotope::getCart()->scheduled_shipping_date);
            $note .= '<br>' . sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_checkout_review_note'], $scheduledShippingDate);
        }
        $objAddress = Isotope::getCart()->getDraftOrder()->getShippingAddress();
        if ($objAddress && !empty($objAddress->dhl_servicepoint_id)) {
            return [
                'shipping_address' => [
                    'headline' => $GLOBALS['TL_LANG']['MSC']['shipping_address'],
                    'info' => $objAddress->generate(Isotope::getConfig()->getShippingFieldsConfig()),
                    'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('jvh_dhl_pickup'),
                ],
                'shipping_method' => [
                    'headline' => $GLOBALS['TL_LANG']['MSC']['shipping_method'],
                    'info' => Isotope::getCart()
                        ->getDraftOrder()
                        ->getShippingMethod()
                        ->checkoutReview(),
                    'note' => $note,
                    'edit' => '',
                ],
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