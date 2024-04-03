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

use Isotope\CheckoutStep\CheckoutStep;
use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Template;

/**
 * OrderInfo checkout steps shows a summary of all other checkout steps (e.g. addresses, payment and shipping method).
 */
class OrderInfo extends CheckoutStep implements IsotopeCheckoutStep
{
    /**
     * @inheritdoc
     */
    public function isAvailable()
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function generate()
    {
        /** @var Template|\stdClass $objTemplate */
        $objTemplate            = new Template('iso_checkout_jvh_order_info');
        $objTemplate->headline  = $GLOBALS['TL_LANG']['MSC']['order_review'];
        $objTemplate->message   = $GLOBALS['TL_LANG']['MSC']['order_review_message'];
        $objTemplate->info      = $this->objModule->getCheckoutInfo();
        $objTemplate->edit_info = $GLOBALS['TL_LANG']['MSC']['changeCheckoutInfo'];

        return $objTemplate->parse();
    }

    /**
     * @inheritdoc
     */
    public function review()
    {
        return '';
    }
}