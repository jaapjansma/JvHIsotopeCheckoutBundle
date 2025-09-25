<?php

namespace JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep;

use Contao\Database;
use Contao\System;
use Haste\Generator\RowClass;
use Isotope\Isotope;
use Isotope\Model\Address as AddressModel;
use Isotope\Module\Checkout;

class BillingAddress extends \Isotope\CheckoutStep\BillingAddress {

  /**
   * Load data container and create template
   *
   * @param Checkout $objModule
   */
  public function __construct(Checkout $objModule) {
    parent::__construct($objModule);
    $this->Template->pro6pp_apikey = System::getContainer()
      ->getParameter('jvh.isotope-checkout-bundle.pro6pp_apikey');
  }

  /**
   * Get addresses for the current member
   *
   * @return AddressModel[]
   */
  protected function getAddresses() {
    $objAddresses = AddressModel::findBy(
      ['pid=?', 'ptable=?'],
      [\FrontendUser::getInstance()->id, 'tl_member'],
      [
        'order' => 'isDefaultBilling DESC, isDefaultShipping DESC',
      ]
    );
    $allAddresses = NULL === $objAddresses ? [] : $objAddresses->getModels();
    $filteredAddresses = array_filter($allAddresses, function ($address, $idx) {
      if (!empty($address->sendcloud_servicepoint_id) || !empty($address->dhl_servicepoint_id)) {
        return FALSE;
      }
      return TRUE;
    }, ARRAY_FILTER_USE_BOTH);
    return $filteredAddresses;
  }

  /**
   * @inheritdoc
   */
  protected function getAddressCountries() {
    return array_keys(\System::getCountries());
  }

  public function review() {
    $draftOrder = Isotope::getCart()->getDraftOrder();
    $blnRequiresPayment = $draftOrder->requiresPayment();
    $blnRequiresShipping = $draftOrder->requiresShipping();
    $objBillingAddress = $draftOrder->getBillingAddress();

    $canEdit = !$this->isSkippable();
    $strHeadline = $GLOBALS['TL_LANG']['MSC']['billing_address'];

    if (!$blnRequiresPayment && !$blnRequiresShipping) {
      $strHeadline = $GLOBALS['TL_LANG']['MSC']['customer_address'];
    }

    return [
      'billing_address' => [
        'headline' => $strHeadline,
        'info' => $objBillingAddress->generate(Isotope::getConfig()
          ->getBillingFieldsConfig()),
        'edit' => $canEdit ? Checkout::generateUrlForStep('billing_address') : '',
        'note' => '',
      ],
    ];
  }

  /**
   * Generate address options and return it as HTML string
   *
   * @param bool $blnValidate
   *
   * @return string
   */
  protected function generateOptions($blnValidate = false) {
    if (!$blnValidate) {
      $cart = Isotope::getCart();
      $billingAddress = $cart->getBillingAddress();
      if (empty($billingAddress->id)) {
        $addresses = $this->getAddresses();
        if (count($addresses) > 0) {
          $cart->setBillingAddress(reset($addresses));
          $cart->billing_address_id = $billingAddress->id;
        }
      }
    }
    return parent::generateOptions($blnValidate);
  }

  protected function setAddress(AddressModel $objAddress): void {
    $arrShopCountries = Isotope::getConfig()->getBillingCountries();
    if (!\in_array($objAddress->country, $arrShopCountries, TRUE)) {
      if (Isotope::getCart()->config_id == 2) {
        Isotope::getCart()->config_id = 3;
        Isotope::getCart()->save();
      }
      elseif (Isotope::getCart()->config_id == 3) {
        Isotope::getCart()->config_id = 2;
        Isotope::getCart()->save();
      }
    }
    parent::setAddress($objAddress);
    Isotope::getCart()->save();
  }

  /**
   * @inheritdoc
   */
  protected function getAddress()
  {
    $address = Isotope::getCart()->getBillingAddress();

    if ((null === $address || empty($address->id)) && FE_USER_LOGGED_IN === true) {
      $allAddresses = $this->getAddresses();
      if (count($allAddresses) > 0) {
        $address = $allAddresses[0];
      }
    }

    if (null !== $address && Isotope::getCart()->billing_address_id != $address->id) {
      Isotope::getCart()->setBillingAddress($address);
    }

    return $address;
  }

  protected function getWidgets() {
    $return = parent::getWidgets();
    unset($return['isDefaultShipping']);
    $arrOptions = $this->getAddressOptions();
    if (!count($arrOptions)) {
      unset($return['isDefaultBilling']);
    }
    return $return;
  }

  /**
   * Validate input and return address data
   *
   * @param bool $blnValidate
   *
   * @return array
   */
  protected function validateFields($blnValidate)
  {
    static $arrOptions = null;
    if ($arrOptions === null) {
      $arrOptions = $this->getAddressOptions();
    }
    $arrAddress = parent::validateFields($blnValidate);
    if (!count($arrOptions)) {
      $arrAddress['isDefaultBilling'] = TRUE;
      $arrAddress['isDefaultShipping'] = TRUE;
    }
    return $arrAddress;
  }

  protected function getAddressForOption($varValue, $blnValidate)
  {
    if ($varValue == '0') {
      $objAddress = $this->getDefaultAddress();
      $arrAddress = $this->validateFields($blnValidate);

      if ($blnValidate) {
        foreach ($arrAddress as $field => $value) {
          $objAddress->$field = $value;
        }

        if (Isotope::getCart()->member) {
          $objAddress->pid = Isotope::getCart()->member;
          $objAddress->ptable = 'tl_member';
        }

        $objAddress->save();
      }

      return $objAddress;
    }

    $arrAddresses = $this->getAddresses();

    foreach ($arrAddresses as $objAddress) {
      if ($objAddress->id == $varValue) {
        return $objAddress;
      }
    }

    return null;
  }


}
