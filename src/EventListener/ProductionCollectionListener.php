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

namespace JvH\IsotopeCheckoutBundle\EventListener;

use Contao\Template;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Message;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\ProductCollectionSurcharge;

class ProductionCollectionListener {

  public function addCollectionToTemplate(Template $objTemplate, array $arrItems, IsotopeProductCollection $objCollection, array $arrConfig) {
    foreach ($objCollection->getItems() as $objItem) {
      $product = $objItem->getProduct();
      if ($product instanceof Product) {
        if (!$product->isAvailableInFrontend()) {
          Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['checkout_jvh_product_notavailable'], $product->getName()));
        }
      }
    }
  }

  /**
   * Copy the combined packaging slip from the source to the new cart.
   *
   * @param \Isotope\Model\ProductCollection $objCollection
   * @param \Isotope\Model\ProductCollection $objSource
   * @param $arrItemIds
   *
   * @return void
   */
  public function updateDraftOrder(ProductCollection $objCollection, ProductCollection $objSource, $arrItemIds) {
    $objCollection->scheduled_shipping_date = $objSource->scheduled_shipping_date;
  }

  /**
   * Get shipping and payment surcharges for given collection
   *
   * @param IsotopeProductCollection $objCollection
   *
   * @return ProductCollectionSurcharge[]
   */
  public function findSurchargesForCollection(IsotopeProductCollection $objCollection)
  {
    if ($objCollection instanceof Cart) {
      $arrSurcharges = array();

      if (($objSurcharge = $objCollection->getShippingSurcharge()) !== null) {
        $arrSurcharges[] = $objSurcharge;
      }
      return $arrSurcharges;
    }
    return array();
  }

}