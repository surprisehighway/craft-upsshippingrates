<?php
/**
 * UPS Shipping Rates plugin for Craft CMS 3.x
 *
 * Adds UPS shipping methods and rates to Craft Commerce.
 *
 * @link      https://github.com/surprisehighway
 * @copyright Copyright (c) 2018 Surprise Highway
 */

namespace surprisehighway\upsshippingrates\services;

use surprisehighway\upsshippingrates\UpsShippingRates;
use surprisehighway\upsshippingrates\CommerceUpsRates\ShippingMethod as ShippingMethod;

use Craft;
use craft\base\Component;

/**
 * @author    Surprise Highway
 * @package   UpsShippingRates
 * @since     2.0.0-beta
 */
class ShippingMethods extends Component
{
    private $_allShippingMethods;

    // Public Methods
    // =========================================================================

    public function getAllShippingMethods()
    {
        if (!isset($this->_allShippingMethods))
        {
            $upsServicesConfig = UpsShippingRates::getInstance()->settings['upsServices'];

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
