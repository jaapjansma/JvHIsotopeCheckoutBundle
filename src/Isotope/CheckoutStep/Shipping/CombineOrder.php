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

use Isotope\Isotope;
use Isotope\Model\Shipping;
use Krabo\IsotopePackagingSlipBundle\Model\Shipping\CombinePackagingSlip;

class CombineOrder {

    /**
     * @var array
     */
    private array $options = [];

    /**
     * @var CombinePackagingSlip
     */
    private $combinedPackagingSlipShippingMethod;

    private function loadOptions() {
        if (empty($this->combinedPackagingSlipShippingMethod)) {
            $this->combinedPackagingSlipShippingMethod = Shipping::findOneBy('type', 'combine_packaging_slip');
        }
        if (empty($this->options)) {
            $this->options = $this->combinedPackagingSlipShippingMethod->getOptionsForCombinedPackagingSlips();
        }
    }

    public function isAvailable(): bool {
        $this->loadOptions();
        return count($this->options) > 0;
    }
    public function getOptions(): array {
        $this->loadOptions();
        return $this->options;
    }

    public function isSelected(): bool {
        $shippingMethod = Isotope::getCart()->getShippingMethod();
        if (Isotope::getCart()->combined_packaging_slip_id && $shippingMethod instanceof CombinePackagingSlip) {
            return true;
        }
        return false;
    }

    public function getShippingMethod():? CombinePackagingSlip {
        $this->loadOptions();
        return $this->combinedPackagingSlipShippingMethod;
    }

}