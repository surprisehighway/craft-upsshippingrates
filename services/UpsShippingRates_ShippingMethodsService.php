<?php

namespace Craft;

use CommerceUpsRates\ShippingMethod;

/**
 * UPS Shipping Methods
 *
 * @author    Rob Knecht
 * @copyright Copyright (c) 2017 Surprise Highway
 * @link      https://github.com/surprisehighway
 * @package   UpsShippingRates
 * @since     0.0.1
 */

class UpsShippingRates_ShippingMethodsService extends BaseApplicationComponent
{
	private $_allShippingMethods;

	public function getAllShippingMethods()
	{
		if (!isset($this->_allShippingMethods))
		{

			$upsServicesConfig = craft()->config->get('upsServices', 'upsshippingrates');

			$shippingMethods = [];

			$upsServices = isset($upsServicesConfig) ? $upsServicesConfig : [];

			foreach ($upsServices as $service)
			{
				$shippingMethods[] = new ShippingMethod(
					'UPS',
					[
						'handle' => $service['handle'],
						'name' => $service['name']
					]
				);
			}

			$this->_allShippingMethods = $shippingMethods;
		}

		return $this->_allShippingMethods;
	}
}