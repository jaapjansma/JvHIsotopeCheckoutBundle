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

namespace JvH\IsotopeCheckoutBundle;

class Validator {

  /**
   * Valid date formats
   *
   * @param mixed $varValue The value to be validated
   *
   * @return boolean True if the value is a valid date format
   */
  public static function isDate($varValue)
  {
    return preg_match('~^(0[1-9]|[12][0-9]|3[01])\-(0[1-9]|1[0-2])\-([0-9]{4})$~i', $varValue);
  }

}