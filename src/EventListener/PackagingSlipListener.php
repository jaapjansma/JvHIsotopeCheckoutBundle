<?php
/**
 * Copyright (C) 2025  Jaap Jansma (jaap.jansma@civicoop.org)
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

use Krabo\IsotopeConditionalFreeProductBundle\Isotope\Model\ProductCollectionSurcharge\IsotopeConditionalFreeProductSurcharge;
use Krabo\IsotopeConditionalFreeProductBundle\Model\IsotopeConditionalFreeProduct;
use Krabo\IsotopePackagingSlipBundle\Event\PackagingSlipOrderEvent;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipProductCollectionModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PackagingSlipListener implements EventSubscriberInterface {
  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * The array keys are event names and the value can be:
   *
   *  * The method name to call (priority defaults to 0)
   *  * An array composed of the method name to call and the priority
   *  * An array of arrays composed of the method names to call and respective
   *    priorities, or 0 if unset
   *
   * For instance:
   *
   *  * ['eventName' => 'methodName']
   *  * ['eventName' => ['methodName', $priority]]
   *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
   *
   * The code must not depend on runtime state as it will only be called at compile time.
   * All logic depending on runtime state must be put into the individual methods handling the events.
   *
   * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
   */
  public static function getSubscribedEvents()
  {
    return ['krabo.isotope_packaging_slip.products_from_order' => 'productsFromOrder'];
  }


  public function productsFromOrder(PackagingSlipOrderEvent $event) {
    $packagingSlip = $event->getPackagingSlip();
    $order = $event->getOrder();
    if (isset($order->freeProducts)) {
      $freeProducts = $order->freeProducts;
      foreach ($order->getSurcharges() as $surcharge) {
        if ($surcharge instanceof IsotopeConditionalFreeProductSurcharge) {
          $checked = true;
          $qty = 1;
          if (isset($freeProducts[$surcharge->source_id]['checked'])) {
            $checked = (bool) $freeProducts[$surcharge->source_id]['checked'];
          }
          if (isset($freeProducts[$surcharge->source_id]['qty'])) {
            $qty = $freeProducts[$surcharge->source_id]['qty'];
          }
          if ($checked) {
            $objFreeProduct = IsotopeConditionalFreeProduct::findByPk($surcharge->source_id);
            if ($objFreeProduct) {
              $product = new IsotopePackagingSlipProductCollectionModel();
              $product->pid = $packagingSlip->id;
              $product->product_id = $objFreeProduct->product_id;
              $product->quantity = $qty;
              $product->document_number = $order->document_number;
              $product->value = 0;
              $event->products[] = $product;
            }
          }
        }
      }
    }
  }

}