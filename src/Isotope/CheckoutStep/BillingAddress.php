<?php

namespace JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep;

use Contao\System;
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
    $objShippingAddress = $draftOrder->getShippingAddress();

    $canEdit = !$this->isSkippable();
    $strHeadline = $GLOBALS['TL_LANG']['MSC']['billing_address'];

    if ($blnRequiresPayment && $blnRequiresShipping && $objBillingAddress->id == $objShippingAddress->id) {
      $strHeadline = $GLOBALS['TL_LANG']['MSC']['billing_shipping_address'];
      $canEdit = $canEdit || !$this->objModule->canSkipStep('shipping_address');
    }
    elseif ($blnRequiresShipping && $objBillingAddress->id == $objShippingAddress->id) {
      $strHeadline = $GLOBALS['TL_LANG']['MSC']['shipping_address'];
      $canEdit = $canEdit || !$this->objModule->canSkipStep('shipping_address');
    }
    elseif (!$blnRequiresPayment && !$blnRequiresShipping) {
      $strHeadline = $GLOBALS['TL_LANG']['MSC']['customer_address'];
    }

    return [
      'billing_address' => [
        'headline' => $strHeadline,
        'info' => $objBillingAddress->generate(Isotope::getConfig()
          ->getBillingFieldsConfig()),
        'edit' => $canEdit ? Checkout::generateUrlForStep('billing_address') : '',
      ],
    ];
  }

}
