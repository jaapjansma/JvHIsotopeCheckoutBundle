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

use AppBundle\Model\Shipping\Pickup;
use Isotope\Isotope;
use Isotope\Model\Shipping;

class Shop {

    /**
     * @var array
     */
    private array $options = [];

    /**
     * @var array
     */
    private array $modules = [];

    private function loadOptions(): void
    {
        if (empty($this->options)) {
            $this->options = [];
            $arrColumns[] = "type = 'pickup_shop'";
            $selectedShippingMethod = Isotope::getCart()->getShippingMethod();

            /** @var Shipping[] $objModules */
            $objModules = Shipping::findBy($arrColumns, NULL);
            if (NULL !== $objModules) {
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

                    if ($note = $objModule->getNote()) {
                        $strLabel .= '<span class="note">' . $note . '</span>';
                    }

                    $default = '0';
                    if ($selectedShippingMethod && $selectedShippingMethod->getId() == $objModule->id) {
                        $default = '1';
                    }

                    $this->options[$objModule->id] = [
                        'value' => $objModule->id,
                        'label' => $strLabel,
                        'default' => $default,
                    ];
                    $this->modules[$objModule->id] = $objModule;
                }
            }
        }
    }

    public function getOptions(): array {
        $this->loadOptions();
        return $this->options;
    }

    public function getFirstShopShippingMethod():? Pickup {
        $this->loadOptions();
        $firstShippingMethod = reset($this->modules);
        if ($firstShippingMethod) {
            return $firstShippingMethod;
        }
        return null;
    }

    public function getShippingMethodForOption(int $id):? Pickup {
        $this->loadOptions();
        if (isset($this->modules[$id])) {
            return $this->modules[$id];
        }
        return null;
    }

    public function isAvailable(): bool {
        $this->loadOptions();
        return (bool) count($this->options);
    }

    public function isSelected(): bool {
        $shippingMethod = Isotope::getCart()->getShippingMethod();
        if ($shippingMethod && $shippingMethod instanceof Pickup) {
            return true;
        }
        return false;
    }

}