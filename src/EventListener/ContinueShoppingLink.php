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

use Contao\ContentModule;
use Contao\Module;
use Contao\ModuleModel;
use Contao\Template;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Module\ProductList;
use JvH\JvHPuzzelDbBundle\Frontend\AbstractModule;

class ContinueShoppingLink {

  public function getFrontendModule($objRow, $strBuffer, $objModule) {
    if ($objModule instanceof ProductList) {
      $session = \Contao\System::getContainer()->get('session');
      $session->set('jvh_checkout_continue_shopping', \Environment::get('uri'));
    }
    return $strBuffer;
  }

  public function getContentElement($objRow, $strBuffer, $objElement) {
    if ($objElement instanceof ContentModule) {
      $objModel = ModuleModel::findByPk($objRow->module);
      $strClass = Module::findClass($objModel->type);
      if (is_a($strClass, AbstractModule::class, true) || is_a($strClass, ProductList::class, true)) {
        $session = \Contao\System::getContainer()->get('session');
        $session->set('jvh_checkout_continue_shopping', \Environment::get('uri'));
      }
    }
    return $strBuffer;
  }

  public function addCollectionToTemplate(Template $objTemplate, array $arrItems, IsotopeProductCollection $objCollection, array $arrConfig) {
    $session = \Contao\System::getContainer()->get('session');
    $referer = $session->get('jvh_checkout_continue_shopping', '/');
    $objTemplate->jvhContinueShoppingUrl = $referer;
  }

}