<?php

namespace surprisehighway\upsshippingrates\CommerceUpsRates;

// use Craft;
// use craft\commerce\base\Model;
use craft\commerce\base\ShippingMethodInterface as ShippingMethodInterface;
// use craft\commerce\Plugin;

use surprisehighway\upsshippingrates\CommerceUpsRates\ShippingRule;
use craft\commerce\records\ShippingMethod as ShippingMethodRecord;
use craft\validators\UniqueValidator;


class ShippingMethod implements ShippingMethodInterface
{
	private $_rate;
	private $_handle;
	private $_name;
	private $_carrier;
	private $_service;
	private $_order;
	public function __construct($carrier, $service, $rate = null, $order = null)
	{
		$this->_rate = $rate;
		$this->_carrier = $carrier;
		$this->_service = $service;
		$this->_order = $order;
		$this->_handle = $service['handle'];
		$this->_name = $carrier." - ".$service['name'];
	}
	/**
	 * Returns the type of Shipping Method.
	 * The core shipping methods have type: `Custom`. This is shown in the control panel only.
	 *
	 * @return string
	 */
	public function getType(): string
	{
		return "UPS";
	}
	/**
	 * Returns the ID of this Shipping Method, if it is managed by Craft Commerce.
	 *
	 * @return int|null The shipping method ID, or null if it is not managed by Craft Commerce
	 */
	public function getId()
	{
		return null;
	}
	/**
	 * Returns the unique handle of this Shipping Method.
	 *
	 * @return string
	 */
	public function getHandle(): string
	{
		return $this->_handle;
	}
	/**
	 * Returns the control panel URL to manage this method and it's rules.
	 * An empty string will result in no link.
	 *
	 * @return string
	 */
	public function getCpEditUrl(): string
	{
		return "";
	}
	/**
	 * Returns an array of rules that meet the `ShippingRules` interface.
	 *
	 * @return \Commerce\Interfaces\ShippingRules[] The array of ShippingRules
	 */
    public function getShippingRules(): array
	{
		return [new ShippingRule($this->_carrier, $this->_service, $this->_rate, $this->_order)];
	}
	/**
	 * Returns the name of this Shipping Method as displayed to the customer and in the control panel.
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->_name;
	}
	/**
	 * Is this shipping method enabled for listing and selection by customers.
	 *
	 * @return bool
	 */
	public function getIsEnabled(): bool
	{
		return true;
	}
}