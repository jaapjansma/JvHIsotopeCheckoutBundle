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

namespace JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep\Shipping;

use Contao\Date;
use Contao\FrontendUser;
use Contao\System;
use Contao\Widget;
use Haste\Generator\RowClass;
use Isotope\Isotope;
use Isotope\Model\Address as AddressModel;
use Isotope\Model\Shipping;
use Isotope\Module\Checkout;
use Isotope\Template;

class Ship extends ShippingSubStep {

    /**
     * @var array
     */
    private array $options = [];

    /**
     * @var array
     */
    private $arrWidgets;

    /**
     * @var string
     */
    private string $html;

    public function __construct(Checkout $objModule) {
        parent::__construct($objModule);
        $this->loadOptions();
    }


    /**
     * @param bool $blnIsSubmitted
     * @return string
     */
    public function generate(bool $blnIsSubmitted = false): string
    {
        if (empty($this->html)) {
            $selectedValue = '-1';
            if ($option = $this->getSelectedOption()) {
                $selectedValue = $option['value'];
            }
            $objWidget = new $GLOBALS['TL_FFL']['radio'](
                [
                    'id' => $this->getStepClass(),
                    'name' => $this->getStepClass(),
                    'mandatory' => $this->isSelected,
                    'options' => $this->options,
                    'value' => $selectedValue,
                    'onclick'     => "Isotope.toggleAddressFields(this, '" . $this->getStepClass() . "_new');",
                    'storeValues' => true,
                    'tableless' => isset($this->objModule->tableless) ? $this->objModule->tableless : true,
                ]
            );

            if ($blnIsSubmitted) {
                $objWidget->validate();
                $this->blnError = $objWidget->hasErrors();
                if (!$this->blnError) {
                    $varValue = (string) $objWidget->value;
                    if ($varValue === '-1') {
                        Isotope::getCart()->setShippingAddress(Isotope::getCart()->getBillingAddress());
                        Isotope::getCart()->setShippingMethod(null);
                    } elseif ($varValue === '0') {
                        $objAddress = $this->getDefaultAddress();
                        $arrAddress = $this->validateFields();
                        if (!$this->blnError) {
                            foreach ($arrAddress as $field => $value) {
                                $objAddress->$field = $value;
                            }
                            $objAddress->save();
                            Isotope::getCart()->setShippingAddress($objAddress);
                            Isotope::getCart()->setShippingMethod(null);
                        }
                    } else {
                        $objAddress = AddressModel::findByPk($varValue);
                        Isotope::getCart()->setShippingAddress($objAddress);
                        Isotope::getCart()->setShippingMethod(null);
                    }
                }
            }

            $objTemplate = new Template('iso_checkout_jvh_shipping_ship');
            $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shipping_ship'];
            $objTemplate->message = $GLOBALS['TL_LANG']['MSC']['checkout_jvh_shipping_ship_message'];
            $objTemplate->options = $objWidget->parse();
            $objTemplate->tableless = isset($this->objModule->tableless) ? $this->objModule->tableless : true;
            $objTemplate->class = $this->getStepClass();

            $fields  = '';
            $arrWidgets = $this->getWidgets();

            RowClass::withKey('rowClass')->addCount('row_')->addFirstLast('row_')->addEvenOdd('row_')->applyTo($arrWidgets);

            foreach ($arrWidgets as $objWidget) {
                $fields .= $objWidget->parse();
            }

            $objTemplate->fields = $fields;
            $this->html = $objTemplate->parse();
        }
        return $this->html;
    }

    private function loadOptions() {
        if (empty($this->options)) {
            $arrFields = Isotope::getConfig()->getShippingFieldsConfig();
            $this->options[] = [
                'value' => '-1',
                'label' => Isotope::getCart()->requiresPayment() ? $GLOBALS['TL_LANG']['MSC']['useBillingAddress'] : $GLOBALS['TL_LANG']['MSC']['useCustomerAddress'],
                'default' => '1',
            ];

            if (FE_USER_LOGGED_IN === true) {

                /** @var AddressModel[] $arrAddresses */
                $arrAddresses = $this->getAddresses();
                $arrCountries = Isotope::getConfig()->getShippingCountries();

                if (0 !== \count($arrAddresses) && 0 !== \count($arrCountries)) {
                    foreach ($arrAddresses as $objAddress) {
                        if (!\in_array($objAddress->country, $arrCountries, true)) {
                            continue;
                        }
                        $this->options[] = [
                            'value' => $objAddress->id,
                            'label' => $objAddress->generate($arrFields),
                        ];
                    }
                }
            }

            $this->options[] = [
                'value' => '0',
                'label' => $GLOBALS['TL_LANG']['MSC']['differentShippingAddress'],
            ];
        }
    }

    /**
     * Returns the review information (shown on the checkout confirmation page)
     *
     * @param array $review
     * @return array
     */
    public function review(array $review): array
    {
        if ($this->getSelectedOption()) {
            $objAddress = Isotope::getCart()->getDraftOrder()->getShippingAddress();
            $review['jvh_shipping']['info'] = $objAddress->generate(Isotope::getConfig()->getShippingFieldsConfig());
        }
        return $review;
    }

    protected function getSelectedOption():? array
    {
        if (Isotope::getCart()->getShippingMethod() && Isotope::getCart()->getShippingMethod()->getId()) {
            $shippingMethod = Shipping::findByPk(Isotope::getCart()->getShippingMethod()->getId());
            if ($shippingMethod->type == 'pickup_shop' || $shippingMethod->type == 'combine_packaging_slip') {
                return null;
            }
        }
        if (Isotope::getCart()->getShippingAddress() && Isotope::getCart()->getShippingAddress()->id > 0) {
            $this->loadOptions();
            foreach ($this->options as $option) {
                if ($option['value'] == Isotope::getCart()->getShippingAddress()->id) {
                    return $option;
                }
            }
        }
        return null;
    }

    /**
     * Get addresses for the current member
     *
     * @return AddressModel[]
     */
    protected function getAddresses()
    {
        $objAddresses = AddressModel::findForMember(
            FrontendUser::getInstance()->id,
            array(
                'order' => 'isDefaultBilling DESC, isDefaultShipping DESC'
            )
        );

        return null === $objAddresses ? array() : $objAddresses->getModels();
    }

    /**
     * Get default address for this collection and address type
     *
     * @return AddressModel
     */
    protected function getDefaultAddress()
    {
        $objAddress = AddressModel::findDefaultShippingForProductCollection(Isotope::getCart()->id);

        if (null === $objAddress) {
            $objAddress = AddressModel::createForProductCollection(
                Isotope::getCart(),
                Isotope::getConfig()->getShippingFields(),
                false,
                true
            );
        }

        return $objAddress;
    }

    /**
     * Validate input and return address data
     *
     * @return array
     */
    protected function validateFields()
    {
        $arrAddress = array();
        $arrWidgets = $this->getWidgets();

        foreach ($arrWidgets as $strName => $objWidget) {
            $objWidget->validate();
            $varValue = (string) $objWidget->value;

            // Convert date formats into timestamps
            if ('' !== $varValue && \in_array(($objWidget->dca_config['eval']['rgxp'] ?? null), array('date', 'time', 'datim'), true)) {
                try {
                    $objDate = new Date($varValue, $GLOBALS['TL_CONFIG'][$objWidget->dca_config['eval']['rgxp'] . 'Format']);
                    $varValue = $objDate->tstamp;
                } catch (\OutOfBoundsException $e) {
                    $objWidget->addError(
                        sprintf(
                            $GLOBALS['TL_LANG']['ERR'][$objWidget->dca_config['eval']['rgxp']],
                            $GLOBALS['TL_CONFIG'][$objWidget->dca_config['eval']['rgxp'] . 'Format']
                        )
                    );
                }
            }

            // Do not submit if there are errors
            if ($objWidget->hasErrors()) {
                $this->blnError = true;
            } // Store current value
            elseif ($objWidget->submitInput()) {
                $arrAddress[$strName] = $varValue;
            }
        }

        return $arrAddress;
    }

    /**
     * Get widget objects for address fields
     * @return  Widget[]
     */
    protected function getWidgets()
    {
        if (null === $this->arrWidgets) {
            $this->arrWidgets = array();
            $objAddress       = $this->getDefaultAddress();
            $arrFields        = $this->mergeFieldsWithDca(Isotope::getConfig()->getShippingFieldsConfig());

            // !HOOK: modify address fields in checkout process
            if (isset($GLOBALS['ISO_HOOKS']['modifyAddressFields'])
                && \is_array($GLOBALS['ISO_HOOKS']['modifyAddressFields'])
            ) {
                foreach ($GLOBALS['ISO_HOOKS']['modifyAddressFields'] as $callback) {
                    $arrFields = System::importStatic($callback[0])->{$callback[1]}($arrFields, $objAddress, $this->getStepClass());
                }
            }

            foreach ($arrFields as $field) {

                if (!\is_array($field['dca'])
                    || !($field['enabled'] ?? null)
                    || !($field['dca']['eval']['feEditable'] ?? null)
                    || (($field['dca']['eval']['membersOnly'] ?? null) && FE_USER_LOGGED_IN !== true)
                ) {
                    continue;
                }

                // Continue if the class is not defined
                if (!\array_key_exists($field['dca']['inputType'], $GLOBALS['TL_FFL'])
                    || !class_exists($GLOBALS['TL_FFL'][$field['dca']['inputType']])
                ) {
                    continue;
                }

                /** @var Widget $strClass */
                $strClass = $GLOBALS['TL_FFL'][$field['dca']['inputType']];

                if ('country' === $field['value']) {
                    // Special field "country"
                    $arrCountries = Isotope::getConfig()->getShippingCountries();
                    $field['dca']['reference'] = $field['dca']['options'];
                    $field['dca']['options'] = array_values(array_intersect(array_keys($field['dca']['options']), $arrCountries));
                } elseif (!empty($field['dca']['eval']['conditionField'])) {
                    // Special field type "conditionalselect"
                    $field['dca']['eval']['conditionField'] = $this->getStepClass() . '_' . $field['dca']['eval']['conditionField'];
                }

                $objWidget = new $strClass(
                    $strClass::getAttributesFromDca(
                        $field['dca'],
                        $this->getStepClass() . '_' . $field['value'],
                        $objAddress->{$field['value']}
                    )
                );

                $objWidget->mandatory   = $field['mandatory'] ? true : false;
                $objWidget->required    = $objWidget->mandatory;
                $objWidget->tableless   = isset($this->objModule->tableless) ? $this->objModule->tableless : true;
                $objWidget->storeValues = true;
                $objWidget->dca_config  = $field['dca'];

                $this->arrWidgets[$field['value']] = $objWidget;
            }
        }

        return $this->arrWidgets;
    }

    /**
     * Append DCA configuration to fields so it can be changed in hook.
     *
     * @param array $fieldConfig
     *
     * @return array
     */
    private function mergeFieldsWithDca(array $fieldConfig)
    {
        $fields = [];

        foreach ($fieldConfig as $field) {
            // Do not use reference, otherwise the billing address fields would affect shipping address fields
            $dca = $GLOBALS['TL_DCA'][AddressModel::getTable()]['fields'][$field['value']];

            if (\is_array($dca)) {
                $field['dca'] = $dca;
            }

            $fields[$field['value']] = $field;
        }

        return $fields;
    }


}