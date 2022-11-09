<?php

namespace JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep;

use Isotope\CheckoutStep\ShippingAddress as IsotopeShippingAddress;
use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Isotope;
use Isotope\Model\Address;
use Isotope\Model\Address as AddressModel;
use Isotope\Model\Shipping;
use Isotope\Module\Checkout;
use Krabo\IsotopePackagingSlipBundle\Model\PackagingSlipModel;
use Krabo\IsotopePackagingSlipDHLBundle\Model\Address\DHLParcelShop;
use NotificationCenter\Model\Gateway;

class ShippingAddress extends IsotopeShippingAddress implements IsotopeCheckoutStep {

  /**
   * @var \Krabo\IsotopePackagingSlipDHLBundle\Model\Address\DHLParcelShop
   */
  protected DHLParcelShop $dhlParcelShopShipping;

  /**
   * Load data container and create template
   *
   * @param Checkout $objModule
   */
  public function __construct(Checkout $objModule) {
    parent::__construct($objModule);
    $this->dhlParcelShopShipping = new DHLParcelShop();
  }

  /**
   * Return short name of current class (e.g. for CSS)
   *
   * @return string
   */
  public function getStepClass() {
    // To keep the CSS intact. We can remove this line after Sietse has updated
    // the CSS.
    return 'jvhshippingaddress';
  }


  /**
   * Generate the checkout step
   *
   * @return string
   */
  public function generate() {
    if ($this->isSkippable()) {
      Isotope::getCart()->setShippingAddress(Isotope::getCart()
        ->getBillingAddress());
      return '';
    }

    $blnValidate = \Input::post('FORM_SUBMIT') === $this->objModule->getFormId();

    $this->Template->headline = $GLOBALS['TL_LANG']['MSC']['checkout_shipping_address'];
    $this->Template->message = $GLOBALS['TL_LANG']['MSC']['shipping_address_message'];
    $this->Template->class = $this->getStepClass();
    $this->Template->tableless = isset($this->objModule->tableless) ? $this->objModule->tableless : TRUE;
    $this->Template->options = $this->generateOptions($blnValidate);

    $strBuffer = $this->Template->parse();
    $strBuffer .= '<script type="text/javascript">(function(jQuery) { Isotope.toggleAddressFields(this, \'' . $this->getStepClass() . '_new\'); })(window.jQuery);</script>';
    return $strBuffer;
  }

  public function review() {
    $shippingMethod = Isotope::getCart()->getShippingMethod();
    if ($shippingMethod && ($shippingMethod->type === 'pickup_shop' || $shippingMethod->type === 'combine_order' || $shippingMethod->type === 'combine_packaging_slip')) {
      return [
        'shipping_address' => [
          'headline' => $GLOBALS['TL_LANG']['MSC']['shipping_address'],
          'info' => Isotope::getCart()
            ->getDraftOrder()
            ->getShippingMethod()
            ->checkoutReview(),
          'note' => Isotope::getCart()
            ->getDraftOrder()
            ->getShippingMethod()
            ->getNote(),
          'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('shipping_address'),
        ],
      ];
    }

    $objAddress = Isotope::getCart()->getDraftOrder()->getShippingAddress();

    if ($objAddress->id == Isotope::getCart()
        ->getDraftOrder()
        ->getBillingAddress()->id) {
      return FALSE;
    }

    return [
      'shipping_address' => [
        'headline' => $GLOBALS['TL_LANG']['MSC']['shipping_address'],
        'info' => $objAddress->generate(Isotope::getConfig()
          ->getShippingFieldsConfig()),
        'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('shipping_address'),
      ],
    ];
  }

  /**
   * Get available address options
   *
   * @param array $arrFields
   *
   * @return array
   */
  protected function getAddressOptions($arrFields = NULL) {
    $billingAddress = Isotope::getCart()->getBillingAddress();
    $blnValidate = \Input::post('FORM_SUBMIT') === $this->objModule->getFormId();
    $objGatewayModel = Gateway::findOneBy('type', 'sendcloud');

    $arrOptions = [];

    $combinedPackagingSlipShippingMethod = Shipping::findOneBy('type', 'combine_packaging_slip');
    $ordersToCombine = $combinedPackagingSlipShippingMethod->getOptionsForCombinedPackagingSlips($arrFields);

    if (count($ordersToCombine)) {
      $arrOptions[] = [
        'group' => TRUE,
        'label' => $GLOBALS['TL_LANG']['MSC']['checkout_shipping_address_legends']['orders'],
        'value' => '',
        'class' => 'combine_with_order',
      ];
      $arrOptions = array_merge($arrOptions, $ordersToCombine);
    }

    $arrOptions[] = [
      'group' => TRUE,
      'label' => $GLOBALS['TL_LANG']['MSC']['checkout_shipping_address_legends']['address_book'],
      'value' => '',
      'class' => 'address_book',
    ];
    $defaultSet = FALSE;
    foreach ($arrOptions as $arrOption) {
      if (!empty($arrOption['default'])) {
        $defaultSet = $arrOption['value'];
      }
    }
    $dhlParcelShopShippingOptions = $this->dhlParcelShopShipping->getOptionsForDHLParcelShop($arrFields, $blnValidate);
    if (count($dhlParcelShopShippingOptions)) {
      foreach ($dhlParcelShopShippingOptions as $index => $arrOption) {
        if ($defaultSet && !empty($arrOption['default'])) {
          $dhlParcelShopShippingOptions[$index]['default'] = '0';
        }
        elseif (!empty($arrOption['default'])) {
          $defaultSet = TRUE;
        }
      }
    }

    $arrOptions = array_merge($arrOptions, parent::getAddressOptions($arrFields));
    foreach ($arrOptions as $index => $arrOption) {
      if ($arrOption['value'] == '-1') {
        // Dit is het factuuradres. Toon ook het factuuradres.
        $arrOptions[$index]['label'] = '<div class="use_billing_address_header">' . $arrOption['label'] . '</div>' . Isotope::getCart()
            ->getBillingAddress()
            ->generate($arrFields);
      }
      if ($defaultSet && $arrOption['value'] != $defaultSet) {
        $arrOptions[$index]['default'] = '0';
      }
    }
    $otherAddressOption = array_pop($arrOptions);
    $otherAddressOption['label'] = '<span class="other_address_button">' . $GLOBALS['TL_LANG']['MSC']['differentShippingAddress'] . '</span>';
    $otherAddressOption['label'] .= '<div class="other_address_form" id="' . $this->getStepClass() . '_new">' . $this->generateFields($blnValidate) . '</div>';
    $arrOptions[] = [
      'group' => TRUE,
      'label' => $GLOBALS['TL_LANG']['MSC']['checkout_shipping_address_legends']['new_address'],
      'value' => '',
      'class' => 'new_address',
    ];
    $arrOptions[] = $otherAddressOption;
    $arrOptions[] = [
      'group' => TRUE,
      'label' => $GLOBALS['TL_LANG']['MSC']['checkout_shipping_address_legends']['pickup'],
      'value' => '',
      'class' => 'pickup',
    ];

    if (count($dhlParcelShopShippingOptions)) {
      $arrOptions = array_merge($arrOptions, $dhlParcelShopShippingOptions);
    }

    // Retrieve pick up shops
    $pickupShops = $this->getPickupShops();
    if (count($pickupShops)) {
      $arrOptions[] = [
        'group' => TRUE,
        'label' => $GLOBALS['TL_LANG']['MSC']['checkout_shipping_address_legends']['pickup_shop'],
        'value' => '',
        'class' => 'pickup_shop',
      ];
      $arrOptions = array_merge($arrOptions, $pickupShops);
    }

    return $arrOptions;
  }

  /**
   * Get field configuration for this address type
   *
   * @return array
   */
  protected function getAddressFields() {
    $fields = parent::getAddressFields();
    foreach ($fields as $index => $field) {
      if ($field['value'] == 'dhl_servicepoint_id') {
        unset($fields[$index]);
      }
    }
    return $fields;
  }

  /**
   * Get address object for a selected option
   *
   * @param mixed $varValue
   * @param bool $blnValidate
   *
   * @return AddressModel
   */
  protected function getAddressForOption($varValue, $blnValidate) {
    if ($blnValidate) {
      Isotope::getCart()->setShippingMethod(NULL);
      Isotope::getCart()->combined_order_id = '';
      Isotope::getCart()->save();
    }

    $combineShippingMethod = Shipping::findOneBy('type', 'combine_packaging_slip');
    $objAddress = $combineShippingMethod->getAddressForOption($varValue, $blnValidate);
    if ($objAddress) {
      return $objAddress;
    }
    $objAddress = $this->dhlParcelShopShipping->getAddressForOption($varValue, $blnValidate);
    if ($objAddress) {
      return $objAddress;
    }
    if (stripos($varValue, 'pickup_shop_') === 0) {
      $pickup_shop_id = substr($varValue, 12);
      $objAddress = Isotope::getCart()->getBillingAddress();
      $pickupShopShippingMethod = Shipping::findOneById($pickup_shop_id);
      if ($pickupShopShippingMethod) {
        Isotope::getCart()->setShippingMethod($pickupShopShippingMethod);
        if ($blnValidate) {
          Isotope::getCart()->save();
        }
        return $objAddress;
      }
    }
    if ($varValue === '-1') {
      return Isotope::getCart()->getBillingAddress();
    }
    elseif ($varValue === '0') {
      $objAddress = $this->getDefaultAddress();
      $arrAddress = $this->validateFields($blnValidate);

      if ($blnValidate) {
        foreach ($arrAddress as $field => $value) {
          $objAddress->$field = $value;
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
    $servicePointAddresses = $this->getServicePointAddresses();
    foreach ($servicePointAddresses as $objAddress) {
      if ($objAddress->id == $varValue) {
        return $objAddress;
      }
    }

    return NULL;
  }

  /**
   * Get addresses for the current member
   *
   * @return AddressModel
   */
  protected function getAddresses() {
    $objAddresses = AddressModel::findBy(
      ['pid=?', 'ptable=?'],
      [\FrontendUser::getInstance()->id, 'tl_member'],
      [
        'order' => 'isDefaultBilling DESC, isDefaultShipping DESC',
      ]
    );
    $addresses = NULL === $objAddresses ? [] : $objAddresses->getModels();
    $billingAddress = Isotope::getCart()->getBillingAddress();

    foreach ($addresses as $idx => $address) {
      if (!empty($address->dhl_servicepoint_id)) {
        unset($addresses[$idx]);
      }
      if ($billingAddress && $billingAddress->id == $address->id) {
        unset($addresses[$idx]);
      }
    }
    return $addresses;
  }

  /**
   * @inheritdoc
   */
  protected function getAddressCountries() {
    return array_keys(\System::getCountries());
  }

  /**
   * Get addresses for the current member
   *
   * @return AddressModel
   */
  protected function getServicePointAddresses() {
    $objAddresses = AddressModel::findBy(
      ['pid=?', 'ptable=?'],
      [\FrontendUser::getInstance()->id, 'tl_member'],
      [
        'order' => 'isDefaultBilling DESC, isDefaultShipping DESC',
      ]
    );
    /** @var AddressModel $arrAddresses */
    $addresses = NULL === $objAddresses ? [] : $objAddresses->getModels();

    foreach ($addresses as $idx => $address) {

      if (empty($address->dhl_servicepoint_id)) {
        unset($addresses[$idx]);
      }
    }

    return $addresses;
  }

  protected function getPickupShops() {
    $return = [];
    $arrColumns[] = "type = 'pickup_shop'";
    if (TRUE !== BE_USER_LOGGED_IN) {
      $arrColumns[] = "enabled='1'";
    }
    $selectedShippingMethod = Isotope::getCart()->getShippingMethod();

    /** @var Shipping[] $objModules */
    $objModules = Shipping::findBy($arrColumns, NULL);
    if (NULL !== $objModules) {
      foreach ($objModules as $objModule) {

        if (!$objModule->isAvailable()) {
          continue;
        }

        $strLabel = $objModule->getLabel();
        $fltPrice = $objModule->getPrice();

        if ($fltPrice != 0) {
          if ($objModule->isPercentage()) {
            $strLabel .= ' (' . $objModule->getPercentageLabel() . ')';
          }

          $strLabel .= ': ' . Isotope::formatPriceWithCurrency($fltPrice);
        }

        if ($note = $objModule->getNote()) {
          $strLabel .= '<span class="note">' . $note . '</span>';
        }

        $default = '0';
        if ($selectedShippingMethod && $selectedShippingMethod->getId() == $objModule->id) {
          $default = '1';
        }

        $return['pickup_shop_' . $objModule->id] = [
          'value' => 'pickup_shop_' . $objModule->id,
          'label' => $strLabel,
          'default' => $default,
        ];

      }
    }
    return $return;
  }

  /**
   * @inheritdoc
   */
  protected function setAddress(AddressModel $objAddress) {
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
    Isotope::getCart()->setShippingAddress($objAddress);
  }


}
