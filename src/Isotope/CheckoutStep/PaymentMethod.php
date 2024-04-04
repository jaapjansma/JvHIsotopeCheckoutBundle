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

use Contao\Database;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;
use Isotope\CheckoutStep\CheckoutStep;
use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Isotope;
use Isotope\Model\Payment;
use Isotope\Module\Checkout;
use Isotope\Template;

/**
 * PaymentMethod checkout step lets the user choose a payment method
 */
class PaymentMethod extends CheckoutStep implements IsotopeCheckoutStep
{
    /**
     * Payment modules
     * @var array
     */
    private $modules;

    /**
     * Payment options
     * @var array
     */
    private $options;

    /**
     * Returns true if the current cart has payment
     *
     * @inheritdoc
     */
    public function isAvailable()
    {
        $available = Isotope::getCart()->requiresPayment();

        if (!$available) {
            Isotope::getCart()->setPaymentMethod(null);
        }

        return $available;
    }

    /**
     * @inheritdoc
     */
    public function isSkippable()
    {
        if (!$this->objModule->canSkipStep('payment_method')) {
            return false;
        }

        $this->initializeModules();

        return 1 === \count($this->options);
    }

    /**
     * @inheritdoc
     */
    public function generate()
    {
        $this->initializeModules();

        if (empty($this->modules)) {
            $this->blnError = true;

            System::log('No payment methods available for cart ID ' . Isotope::getCart()->id, __METHOD__, TL_ERROR);

            /** @var Template|\stdClass $objTemplate */
            $objTemplate           = new Template('mod_message');
            $objTemplate->class    = 'payment_method';
            $objTemplate->hl       = 'h2';
            $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['payment_method'];
            $objTemplate->type     = 'error';
            $objTemplate->message  = $GLOBALS['TL_LANG']['MSC']['noPaymentModules'];

            return $objTemplate->parse();
        }

        $strClass  = $GLOBALS['TL_FFL']['radio'];

        /** @var Widget $objWidget */
        $objWidget = new $strClass(array(
            'id'            => $this->getStepClass(),
            'name'          => $this->getStepClass(),
            'mandatory'     => true,
            'options'       => $this->options,
            'value'         => Isotope::getCart()->payment_id,
            'storeValues'   => true,
            'tableless'     => true,
        ));

        // If there is only one payment method, mark it as selected by default
        if (\count($this->modules) == 1) {
            $objModule        = reset($this->modules);
            $objWidget->value = $objModule->id;
            Isotope::getCart()->setPaymentMethod($objModule);
        }

        if (Input::post('FORM_SUBMIT') == $this->objModule->getFormId()) {
            $objWidget->validate();

            if (!$objWidget->hasErrors()) {
                Isotope::getCart()->setPaymentMethod($this->modules[$objWidget->value]);
            }
        }

        /** @var Template|\stdClass $objTemplate */
        $objTemplate = new Template('iso_checkout_payment_method');

        if (!Isotope::getCart()->hasPayment() || !isset($this->modules[Isotope::getCart()->payment_id])) {
            $this->blnError = true;
        }

        $objTemplate->headline       = $GLOBALS['TL_LANG']['MSC']['payment_method'];
        $objTemplate->message        = $GLOBALS['TL_LANG']['MSC']['payment_method_message'];
        $objTemplate->options        = $objWidget->parse();
        $objTemplate->paymentMethods = $this->modules;

        return $objTemplate->parse();
    }

    /**
     * Return review information for last page of checkout
     * @return  array
     */
    public function review()
    {
        return array(
            'payment_method' => array(
                'headline' => $GLOBALS['TL_LANG']['MSC']['payment_method'],
                'info'     => Isotope::getCart()->getDraftOrder()->getPaymentMethod()->checkoutReview(),
                'note'     => Isotope::getCart()->getDraftOrder()->getPaymentMethod()->getNote(),
                'edit'     => $this->isSkippable() ? '' : Checkout::generateUrlForStep(Checkout::STEP_PAYMENT),
            ),
        );
    }

    /**
     * Initialize modules and options
     */
    private function initializeModules()
    {
        if (null !== $this->modules && null !== $this->options) {
            return;
        }

        $this->modules = array();
        $this->options = array();

        $arrIds = StringUtil::deserialize($this->objModule->iso_payment_modules);

        if (!empty($arrIds) && \is_array($arrIds)) {
            $arrColumns = array('id IN (' . implode(',', $arrIds) . ')');

            if (BE_USER_LOGGED_IN !== true) {
                $arrColumns[] = "enabled='1'";
            }

            /** @var Payment[] $objModules */
            $objModules = Payment::findBy($arrColumns, null, array('order' => Database::getInstance()->findInSet('id', $arrIds)));

            if (null !== $objModules) {
                foreach ($objModules as $objModule) {

                    if (!$objModule->isAvailable()) {
                        continue;
                    }

                    $strLabel = $objModule->getLabel();
                    $fltPrice = $objModule->getPrice();

                    if ($fltPrice != 0) {
                        if ($objModule->isPercentage()) {
                            $strLabel .= ' (' . $objModule->getPercentageLabel() . ')';
                        }

                        $strLabel .= ': ' . Isotope::formatPriceWithCurrency($fltPrice);
                    }
                    $strLabel = '<span class="payment-method-label">' . $strLabel . '</span>';

                    if ($note = $objModule->getNote()) {
                        $strLabel .= '<span class="note">' . $note . '</span>';
                    } else {
                        $strLabel .= '<span class="note">notititie</span>';
                    }

                    $this->options[] = array(
                        'value'     => $objModule->id,
                        'label'     => $strLabel,
                    );

                    $this->modules[$objModule->id] = $objModule;
                }
            }
        }
    }
}