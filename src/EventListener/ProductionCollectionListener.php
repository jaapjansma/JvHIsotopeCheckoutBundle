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
use Isotope\Model\Address;
use Isotope\Model\Product;
use Isotope\Model\ProductCollection;
use Isotope\Model\ProductCollection\Cart;
use Isotope\Model\ProductCollectionSurcharge;
use Isotope\Module\Checkout;

class ProductionCollectionListener {

  public function addCollectionToTemplate(Template $objTemplate, array $arrItems, IsotopeProductCollection $objCollection, array $arrConfig) {
    if ($objCollection instanceof Cart) {
      foreach ($objCollection->getItems() as $objItem) {
        $product = $objItem->getProduct();
        if ($product instanceof Product) {
          if (!$product->isAvailableInFrontend()) {
            Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['checkout_jvh_product_notavailable'], $product->getName()));
          }
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

  public function preCheckout(ProductCollection $order, Checkout $checkoutModule) {
    // Deze hook zorgt ervoor dat niet alle shipping addressen aan de tl member worden toegevoegd.
    // Dit dient alleen te gebeuren als het een nieuw shipping address is (of te wel geen ophalen in winkel, geen pickup of niet gecombineerd met bestaande bestelling.
    // Store address in address book
    if ($order->iso_addToAddressbook && $order->member > 0 && !$order->isLocked()) {
      $order->iso_addToAddressbook = false;
      $canSkip = deserialize($order->iso_checkout_skippable, true);
      $objBillingAddress  = $order->getBillingAddress();
      $objShippingAddress = $order->getShippingAddress();
      if (null !== $objBillingAddress
        && $objBillingAddress->ptable != \MemberModel::getTable()
        && !\in_array('billing_address', $canSkip, true)
      ) {
        $objAddress         = clone $objBillingAddress;
        $objAddress->pid    = $order->member;
        $objAddress->tstamp = time();
        $objAddress->ptable = \MemberModel::getTable();
        $objAddress->store_id = $order->store_id;
        $objAddress->save();

        $this->updateDefaultAddress($objAddress);
      }

      if (null !== $objBillingAddress
        && null !== $objShippingAddress
        && $objBillingAddress->id != $objShippingAddress->id
        && $objShippingAddress->ptable != \MemberModel::getTable()
        && !\in_array('shipping_address', $canSkip, true)
        && empty($objShippingAddress->sendcloud_servicepoint_id)
        && empty($objShippingAddress->dhl_servicepoint_id)
        && empty($order->combined_order_id)
        && ($order->getShippingMethod() === NULL || $order->getShippingMethod()->type != 'pickup_shop')
      ) {
        $objAddress         = clone $objShippingAddress;
        $objAddress->pid    = $order->member;
        $objAddress->tstamp = time();
        $objAddress->ptable = \MemberModel::getTable();
        $objAddress->store_id = $order->store_id;
        $objAddress->save();

        $this->updateDefaultAddress($objAddress);
      }
    }
  }

  /**
   * Mark existing addresses as not default if the new address is default
   *
   * @param Address $objAddress
   */
  protected function updateDefaultAddress(Address $objAddress)
  {
    $arrSet = array();

    if ($objAddress->isDefaultBilling) {
      $arrSet['isDefaultBilling'] = '';
    }

    if ($objAddress->isDefaultShipping) {
      $arrSet['isDefaultShipping'] = '';
    }

    if (\count($arrSet) > 0) {
      \Database::getInstance()
        ->prepare('UPDATE tl_iso_address %s WHERE pid=? AND ptable=? AND id!=?')
        ->set($arrSet)
        ->execute($objAddress->pid, \MemberModel::getTable(), $objAddress->id)
      ;
    }
  }

}