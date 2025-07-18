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

namespace JvH\IsotopeCheckoutBundle\Backend\Callback;

use Isotope\Interfaces\IsotopeAttributeWithOptions;
use Isotope\Interfaces\IsotopeProduct;
use Isotope\Isotope;
use Isotope\Model\Attribute;
use Isotope\Model\AttributeOption;
use Isotope\Model\Product;

class OrderDetails {

  public function getAttributesFromDca($arrData, $objDca) {
    if (TL_MODE == 'BE'
      && $arrData['strTable'] == Product::getTable()
      && ($arrData['optionsSource'] ?? '') != ''
      && 'foreignKey' !== $arrData['optionsSource']
    ) {
      /** @var IsotopeAttributeWithOptions|Attribute $objAttribute */
      $objAttribute = Attribute::findByFieldName($arrData['strField']);

      if (null !== $objAttribute && $objAttribute instanceof IsotopeAttributeWithOptions && $objAttribute->optionsSource == IsotopeAttributeWithOptions::SOURCE_TABLE) {
        $objProduct = NULL;
        if ($objDca instanceof IsotopeProduct) {
          $objProduct = $objDca;
        }
        $objOptions = $objAttribute->getOptionsFromManager();

        if (null === $objOptions) {
          $arrOptions = array();
        } elseif ($objAttribute->isCustomerDefined()) {
          $arrOptions = [];
          foreach ($objOptions->getModels() as $objModel) {
            $originalLabel = $objModel->label;
            if ($objModel instanceof AttributeOption) {
              $objModel->label = $this->getLabel($objModel, $objProduct);
            }
            $arrOptions[] = $objModel->getAsArray($objProduct, FALSE);
            if ($objModel instanceof AttributeOption) {
              $objModel->label = $originalLabel;
            }
          }
        } else {
          $arrOptions = $objOptions->getArrayForBackendWidget();
        }

        $arrData['options'] = $arrOptions;

        if (!empty($arrData['options'])) {
          if ($arrData['includeBlankOption']) {
            array_unshift($arrData['options'], array('value'=>'', 'label'=>($arrData['blankOptionLabel'] ?: '-')));
          }

          if (null !== ($arrData['default'] ?? null)) {
            $arrDefault = array_filter(
              $arrData['options'],
              function (&$option) {
                return (bool) $option['default'];
              }
            );

            if (!empty($arrDefault)) {
              array_walk(
                $arrDefault,
                function (&$value) {
                  $value = $value['value'];
                }
              );

              $arrData['value'] = ($objAttribute->multiple ? $arrDefault : $arrDefault[0]);
            }
          }
        }
      }
    }

    return $arrData;
  }

  private function getLabel(AttributeOption $attributeOption, IsotopeProduct $objProduct = null)
  {
    $strLabel    = $attributeOption->label;
    $priceFormat = $GLOBALS['TL_LANG']['MSC']['attributePriceLabel'];

    /** @var Attribute $objAttribute */
    $objAttribute = null;

    switch ($attributeOption->ptable) {
      case 'tl_iso_product':
        $objAttribute = Attribute::findByFieldName($attributeOption->field_name);
        break;

      case 'tl_iso_attribute':
        $objAttribute = Attribute::findByPk($attributeOption->pid);
        break;
    }

    if (null === $objAttribute || $attributeOption->price == '' || $objAttribute->isVariantOption()) {
      return $strLabel;
    }

    if (null === $objProduct && $attributeOption->isPercentage()) {
      return sprintf($priceFormat, $strLabel, $attributeOption->price);
    }

    $strPrice = Isotope::formatPriceWithCurrency(Isotope::calculatePrice($attributeOption->price, $attributeOption, 'price'), false);

    if ($attributeOption->isFromPrice($objProduct)) {
      $strPrice = sprintf($GLOBALS['TL_LANG']['MSC']['priceRangeLabel'], $strPrice);
    }

    return sprintf($priceFormat, $strLabel, $strPrice);
  }

}