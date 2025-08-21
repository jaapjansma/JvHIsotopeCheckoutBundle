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

use Contao\Database;
use Isotope\Model\Address;
use Isotope\Module\AddressBook;

class AddressBookListener {

  public static function updateAddressData(Address $objAddress, array $arrOldAddress, AddressBook $module) {
    $db = Database::getInstance();
    if ($objAddress->isDefaultBilling) {
      $db->prepare("UPDATE `tl_iso_address` SET `isDefaultBilling` = '' WHERE `tl_iso_address`.`isDefaultBilling` = '1' AND `pid` = ? AND `ptable` = 'tl_member' AND `id` != ?")->execute([$objAddress->pid, $objAddress->id]);
    }
    if ($objAddress->isDefaultShipping) {
      $db->prepare("UPDATE `tl_iso_address` SET `isDefaultShipping` = '' WHERE `tl_iso_address`.`isDefaultShipping` = '1' AND `pid` = ? AND `ptable` = 'tl_member' AND `id` != ?")->execute([$objAddress->pid, $objAddress->id]);
    }
  }

}