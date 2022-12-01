<?php

namespace JvH\IsotopeCheckoutBundle\Isotope\CheckoutStep;

use Contao\Date;
use Contao\Input;
use Isotope\CheckoutStep\CheckoutStep;
use Isotope\Interfaces\IsotopeCheckoutStep;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Isotope;
use Isotope\Model\Shipping;
use Isotope\Module\Checkout;
use Isotope\Template;
use JvH\CadeauBonnenBundle\Model\Shipping\Cadeaubon;
use JvH\IsotopeCheckoutBundle\Validator;
use Krabo\IsotopePackagingSlipBundle\Helper\IsotopeHelper;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipModel;
use Krabo\IsotopePackagingSlipBundle\Model\IsotopePackagingSlipShipperModel;

class ShippingMethod extends CheckoutStep implements IsotopeCheckoutStep {

  public const DHL_PARCEL_SHOP_SHIPPING_METHOD_ID = 158;

  /**
   * Shipping modules.
   *
   * @var array
   */
  private $modules;

  /**
   * Shipping options.
   *
   * @var array
   */
  private $options;

  /**
   * @inheritdoc
   */
  public function generate() {
    $this->initializeModules();

    $shippingMethodAllowsShippingDateChange = [];
    $combineShippingMethod = Shipping::findOneBy('type', 'combine_packaging_slip');
    $combined_order_id = Isotope::getCart()->combined_order_id;
    $shippingAddress = Isotope::getCart()->getShippingAddress();
    if (!empty($shippingAddress->sendcloud_servicepoint_id) || !empty($shippingAddress->dhl_servicepoint_id)) {
      $dhlPickupPointShippingMethod = Shipping::findOneById(static::DHL_PARCEL_SHOP_SHIPPING_METHOD_ID);
      if ($dhlPickupPointShippingMethod) {
        $shippingMethodAllowsShippingDateChange[] = $dhlPickupPointShippingMethod->id;
        Isotope::getCart()->setShippingMethod($dhlPickupPointShippingMethod);
      }
    }
    if ($combineShippingMethod->skipShippingMethodSelection()) {
      $shippingMethodAllowsShippingDateChange[] = $combineShippingMethod->id;
    }
    elseif (!empty($combined_order_id)) {
      $shippingMethodAllowsShippingDateChange[] = $combineShippingMethod->id;
    }

    if (empty($this->modules)) {
      $this->blnError = TRUE;

      \System::log('No shipping methods available for cart ID ' . Isotope::getCart()->id, __METHOD__, TL_ERROR);

      /** @var Template|\stdClass $objTemplate */
      $objTemplate = new Template('mod_message');
      $objTemplate->class = 'shipping_method';
      $objTemplate->hl = 'h2';
      $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['shipping_method'];
      $objTemplate->type = 'error';
      $objTemplate->message = $GLOBALS['TL_LANG']['MSC']['noShippingModules'];

      return $objTemplate->parse();
    }

    /** @var \Widget $objWidget */
    $objWidget = new $GLOBALS['TL_FFL']['radio'](
      [
        'id' => $this->getStepClass(),
        'name' => $this->getStepClass(),
        'mandatory' => TRUE,
        'options' => $this->options,
        'value' => Isotope::getCart()->shipping_id,
        'storeValues' => TRUE,
        'tableless' => TRUE,
        'onchange' => "
                  $('#ctrl_" . $this->getStepClass() . "_shipping_date').parent().hide();
                  $('#ctrl_" . $this->getStepClass() . "_shipping_date').val('');
                  if ($(this).parent().find('label').find('.scheduled_shipping_date_able').length) {                  
                    var selectedValue = $(this).parent().find('label').find('.scheduled_shipping_date_able').data('earliestShippingDate'); 
                    $('#ctrl_" . $this->getStepClass() . "_shipping_date').val(selectedValue);
                    $('#ctrl_" . $this->getStepClass() . "_shipping_date').parent().show();
                  }",
      ]
    );

    $earliestShippingDateStringValue = '';
    if (Isotope::getCart()->scheduled_shipping_date) {
      $earliestShippingDateStringValue = date('d-m-Y', Isotope::getCart()->scheduled_shipping_date);
    }
    $objShippingDateWidget = new $GLOBALS['TL_FFL']['text'](
      [
        'id' => $this->getStepClass() . '_shipping_date',
        'name' => $this->getStepClass() . '_shipping_date',
        'label' => $GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date'][0],
        'description' => $GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date'][1],
        'customTpl' => 'form_jvhshippingdatefield',
        'value' => $earliestShippingDateStringValue,
      ]
    );

    // If there is only one shipping method, mark it as selected by default
    if (\count($this->modules) === 1) {
      $objModule = reset($this->modules);
      $objWidget->value = $objModule->id;
      Isotope::getCart()->setShippingMethod($objModule);
    }

    if (\Input::post('FORM_SUBMIT') == $this->objModule->getFormId()) {
      $objWidget->validate();

      if (!$objWidget->hasErrors()) {
        $objShipper = NULL;
        if ($this->modules[$objWidget->value]->shipper_id) {
          $objShipper = IsotopePackagingSlipShipperModel::findByPk($this->modules[$objWidget->value]->shipper_id);
        }

        $allowShippingDateChange = false;
        if ($objShipper && $objShipper->customer_can_provide_shipping_date) {
          $allowShippingDateChange = TRUE;
        } elseif (in_array($objModule->id, $shippingMethodAllowsShippingDateChange)) {
          $allowShippingDateChange = TRUE;
        }

        if ($allowShippingDateChange) {
          $earliestShippingDateTimeStamp = IsotopeHelper::getScheduledShippingDate(Isotope::getCart(), $objShipper);
          $earliestShippingDate = date('d-m-Y', $earliestShippingDateTimeStamp);
          $objShippingDateWidget->validate();
          if (!$objShippingDateWidget->hasErrors()) {
            if (!empty($objShippingDateWidget->value)) {
              try {
                $scheduledShippingDate = new \DateTime($objShippingDateWidget->value);
                $scheduledShippingDate->setTime(23, 59);
                if (!Validator::isDate($objShippingDateWidget->value)) {
                  $objShippingDateWidget->addError(sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_error'], $earliestShippingDate));
                }
                elseif ($scheduledShippingDate->getTimestamp() < $earliestShippingDateTimeStamp) {
                  $objShippingDateWidget->addError(sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_error'], $earliestShippingDate));
                }
              } catch (\Exception $e) {
                $objShippingDateWidget->addError(sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_error'], $earliestShippingDate));
              }
            }
            else {
              $objShippingDateWidget->addError(sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_error'], $earliestShippingDate));
            }
          }
          if ($objShippingDateWidget->hasErrors()) {
            $this->blnError = TRUE;
          }
          else {
            Isotope::getCart()->scheduled_shipping_date = $scheduledShippingDate->getTimestamp();
          }
        }
        else {
          Isotope::getCart()->scheduled_shipping_date = '';
        }
        if (!$this->blnError) {
          Isotope::getCart()
            ->setShippingMethod($this->modules[$objWidget->value]);
        }
      }
    }

    if (!Isotope::getCart()
        ->hasShipping() || !isset($this->modules[Isotope::getCart()->shipping_id])) {
      $this->blnError = TRUE;
    }

    /** @var Template|\stdClass $objTemplate */
    $objTemplate = new Template('iso_checkout_shipping_method');
    $objTemplate->headline = $GLOBALS['TL_LANG']['MSC']['shipping_method'];
    $objTemplate->message = $GLOBALS['TL_LANG']['MSC']['shipping_method_message'];
    $objTemplate->options = $objWidget->parse();
    $objTemplate->shippingDate = $objShippingDateWidget->parse();
    $objTemplate->shippingMethods = $this->modules;

    return $objTemplate->parse();
  }

  /**
   * @inheritdoc
   */
  public function review() {
    $note = Isotope::getCart()->getDraftOrder()->getShippingMethod()->getNote();
    if (Isotope::getCart()->getDraftOrder()->scheduled_shipping_date) {
      $scheduledShippingDate = date('d-m-Y', Isotope::getCart()->scheduled_shipping_date);
      $note .= '<br>' . sprintf($GLOBALS['TL_LANG']['MSC']['scheduled_shipping_date_checkout_review_note'], $scheduledShippingDate);
    }
    return [
      'shipping_method' => [
        'headline' => $GLOBALS['TL_LANG']['MSC']['shipping_method'],
        'info' => Isotope::getCart()
          ->getDraftOrder()
          ->getShippingMethod()
          ->checkoutReview(),
        'note' => $note,
        'edit' => $this->isSkippable() ? '' : Checkout::generateUrlForStep('shipping'),
      ],
    ];
  }

  /**
   * @inheritdoc
   */
  public function getNotificationTokens(IsotopeProductCollection $objCollection) {
    return [];
  }

  /**
   * Initialize modules and options
   */
  private function initializeModules() {
    $allowedShippingMethods = NULL;
    $shippingMethodAllowsShippingDateChange = [];
    $combineShippingMethod = Shipping::findOneBy('type', 'combine_packaging_slip');
    $combined_order_id = Isotope::getCart()->combined_order_id;
    $shippingAddress = Isotope::getCart()->getShippingAddress();
    if (!empty($shippingAddress->sendcloud_servicepoint_id) || !empty($shippingAddress->dhl_servicepoint_id)) {
      $dhlPickupPointShippingMethod = Shipping::findOneById(static::DHL_PARCEL_SHOP_SHIPPING_METHOD_ID);
      if ($dhlPickupPointShippingMethod) {
        $allowedShippingMethods[] = $dhlPickupPointShippingMethod->id;
        $shippingMethodAllowsShippingDateChange[] = $dhlPickupPointShippingMethod->id;
        Isotope::getCart()->setShippingMethod($dhlPickupPointShippingMethod);
      }
    }
    if ($combineShippingMethod->skipShippingMethodSelection()) {
      $allowedShippingMethods[] = $combineShippingMethod->id;
      $shippingMethodAllowsShippingDateChange[] = $combineShippingMethod->id;
    }
    elseif (!empty($combined_order_id)) {
      $allowedShippingMethods[] = $combineShippingMethod->id;
      $shippingMethodAllowsShippingDateChange[] = $combineShippingMethod->id;
    }

    if (NULL !== $this->modules && NULL !== $this->options) {
      return;
    }

    $this->modules = [];
    $this->options = [];

    $arrIds = deserialize($this->objModule->iso_shipping_modules);

    if (!empty($arrIds) && \is_array($arrIds)) {
      if (is_array($allowedShippingMethods)) {
        $arrIds = array_merge($arrIds, $allowedShippingMethods);
      }
      $arrIds = array_filter($arrIds, function ($id) use ($allowedShippingMethods, $combineShippingMethod) {
        if (is_array($allowedShippingMethods) && !in_array($id, $allowedShippingMethods)) {
          return FALSE;
        } elseif (!is_array($allowedShippingMethods) && $id == $combineShippingMethod->id) {
          return FALSE;
        }
        return TRUE;
      });
      $arrColumns = ['id IN (' . implode(',', $arrIds) . ')'];

      if (TRUE !== BE_USER_LOGGED_IN) {
        $arrColumns[] = "enabled='1'";
      }

      /** @var Shipping[] $objModules */
      $objModules = Shipping::findBy(
        $arrColumns, NULL, [
          'order' => \Database::getInstance()
            ->findInSet('id', $arrIds),
        ]
      );

      if (NULL !== $objModules) {
        $cadeaubonShippingMethodIds = [];
        foreach ($objModules as $objModule) {

          if (!$objModule->isAvailable()) {
            continue;
          }
          if ($objModule instanceof Cadeaubon) {
            $cadeaubonShippingMethodIds[] = $objModule->id;
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

          $objShipper = NULL;
          if ($objModule->shipper_id) {
            $objShipper = IsotopePackagingSlipShipperModel::findByPk($objModule->shipper_id);
          }
          $allowShippingDateChange = false;
          if ($objShipper && $objShipper->customer_can_provide_shipping_date) {
            $allowShippingDateChange = TRUE;
          } elseif (in_array($objModule->id, $shippingMethodAllowsShippingDateChange)) {
            $allowShippingDateChange = TRUE;
          }
          if ($allowShippingDateChange) {
            $earliestShippingDate = date('d-m-Y', IsotopeHelper::getScheduledShippingDate(Isotope::getCart(), $objShipper));
            $defaultShippingDate = $earliestShippingDate;
            if (Isotope::getCart()->combined_packaging_slip_id) {
              $packagingSlip = IsotopePackagingSlipModel::findOneBy('document_number', Isotope::getCart()->combined_packaging_slip_id);
              $defaultShippingDate = date('d-m-Y', $packagingSlip->scheduled_shipping_date);
            }
            $strLabel .= '<span class="scheduled_shipping_date_able" data-earliest-shipping-date="' . $defaultShippingDate . '">&nbsp;</span>';
          }

          $this->options[] = [
            'value' => $objModule->id,
            'label' => $strLabel,
          ];

          $this->modules[$objModule->id] = $objModule;
        }
        if (!empty($cadeaubonShippingMethodIds)) {
          $this->options = array_filter($this->options, function ($v, $k) use ($cadeaubonShippingMethodIds) {
            return in_array($v['value'], $cadeaubonShippingMethodIds);
          }, ARRAY_FILTER_USE_BOTH);
          $this->modules = array_filter($this->modules, function ($v, $k) use ($cadeaubonShippingMethodIds) {
            return in_array($k, $cadeaubonShippingMethodIds);
          }, ARRAY_FILTER_USE_BOTH);
        }
      }
    }
  }

  public function isAvailable() {
    $isAvailable = Isotope::getCart()->requiresShipping();
    if (!$isAvailable) {
      Isotope::getCart()->setShippingMethod(NULL);
    }
    return $isAvailable;
  }
}
