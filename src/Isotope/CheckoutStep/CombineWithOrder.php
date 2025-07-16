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
use Isotope\Module\Checkout;
use Isotope\Template;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopePackagingSlipBundle\Model\Shipping\CombinePackagingSlip;

class CombineWithOrder extends CheckoutStep implements IsotopeCheckoutStep {

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
        if ($available) {
            $shippingMethod = Isotope::getCart()->getShippingMethod();
            if (!$shippingMethod instanceof CombinePackagingSlip) {
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
                $combineWithOrder = new \JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\CombineOrder();
                $shippingMethod = $combineWithOrder->getShippingMethod();
                $this->blnError = true;
                if ($shippingMethod) {
                    $packagingSlipModel = IsotopePackagingSlipModel::findOneBy('id', substr($objWidget->value, 15));
                    Isotope::getCart()->combined_packaging_slip_id = '';
                    Isotope::getCart()->disableFreeProducts = false;
                    Isotope::getCart()->setShippingAddress(null);
                    Isotope::getCart()->setShippingMethod(null);
                    if ($packagingSlipModel) {
                        $this->blnError = false;
                        Isotope::getCart()->combined_packaging_slip_id = $packagingSlipModel->document_number;
                        Isotope::getCart()->disableFreeProducts = true;
                        Isotope::getCart()->setShippingMethod($shippingMethod);
                        $objAddress = Address::createForProductCollection(Isotope::getCart(), Isotope::getConfig()->getShippingFields(), false, false);
                        foreach(Isotope::getConfig()->getShippingFields() as $field) {
                            if (isset($packagingSlipModel->$field)) {
                                $objAddress->$field = $packagingSlipModel->$field;
                            }
                        }
                        Isotope::getCart()->setShippingAddress($objAddress);
                    }
                }
            }
        }

        $objTemplate                  = new Template('iso_checkout_combine_with_order');
        $objTemplate->headline        = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_combine_order'];
        $objTemplate->message         = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_combine_order_message'];
        $objTemplate->options         = $objWidget->parse();

        return $objTemplate->parse();
    }

    /**
     * Get review information about this step
     * @return  array
     */
    public function review()
    {
        $selectedOption = $this->getSelectedOption();
        if ($selectedOption) {
            $note = '';
            foreach($this->options as $option) {
                if ($option['value'] == $selectedOption) {
                    $note = $option['label'];
                }
            }
            return [
                'jvh_combine_order' => [
                    'headline' => $GLOBALS['TL_LANG']['MSC']['checkout_jvh_combine_order'],
                    'info' => Isotope::getCart()
                        ->getDraftOrder()
                        ->getShippingMethod()
                        ->checkoutReview(),
                    'note' => $note,
                    'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('jvh_combine_order'),
                ],
            ];
        }
        return [];
    }

    private function initializeModules() {
        if (empty($this->options)) {
            $combine = new \JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping\CombineOrder();
            $this->options = $combine->getOptions();
        }
    }

    private function getSelectedOption(): string {
        $this->initializeModules();
        $shippingMethod = Isotope::getCart()->getShippingMethod();
        if (Isotope::getCart()->combined_packaging_slip_id && $shippingMethod instanceof CombinePackagingSlip) {
            foreach ($this->options as $option) {
                $packagingSlipModel = IsotopePackagingSlipModel::findOneBy('id', substr($option['value'], 15));
                if ($packagingSlipModel && $packagingSlipModel->getDocumentNumber() == Isotope::getCart()->combined_packaging_slip_id) {
                    return $option['value'];
                }
            }
        }
        return '';
    }


}