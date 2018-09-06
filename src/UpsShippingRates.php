<?php
/**
 * UPS Shipping Rates plugin for Craft CMS 3.x
 *
 * Adds UPS shipping methods and rates to Craft Commerce.
 *
 * @link      https://github.com/surprisehighway
 * @copyright Copyright (c) 2018 Surprise Highway
 */

namespace surprisehighway\upsshippingrates;

use surprisehighway\upsshippingrates\CommerceUpsRates\ShippingMethod as ShippingMethod;
use surprisehighway\upsshippingrates\services\Rates as RatesService;
use surprisehighway\upsshippingrates\services\ShippingMethods as ShippingMethodsService;
use surprisehighway\upsshippingrates\models\Settings as UpsSettings;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\events\ModelEvent;

use yii\base\Event;

use craft\commerce\services\ShippingMethods;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;


/**
 * Class UpsShippingRates
 *
 * @author    Surprise Highway
 * @package   UpsShippingRates
 * @since     2.0.0-beta
 *
 * @property  RatesService $rates
 * @property  ShippingMethodsService $shippingMethods
 */
class UpsShippingRates extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * @var UpsShippingRates
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '2.0.0-beta.4';

    /**
     * @inheritdoc
     */
    public $hasCpSettings = true;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ($event->plugin === $this) {
                }
            }
        );

        Craft::info(
            Craft::t(
                'upsshippingrates',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );

        // Encrypt UPS Password on Settings save
        Event::on(UpsShippingRates::class, UpsShippingRates::EVENT_BEFORE_SAVE_SETTINGS, function(ModelEvent $event) {
            $plugin = $event->sender;
            $settings = $plugin->getSettings();
            $settings->upsPassword = base64_encode(\Craft::$app->security->encryptByKey($settings->upsPassword));
        });

        // Handle requests for Shipping Methods
        Event::on(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, function(RegisterAvailableShippingMethodsEvent $event) {

            if ( $this->getSettings()->apiKey !== '' && $this->getSettings()->apiKey !== null )
            {
                // Don't bother returning all shipping methods when we are in the context of an order,
                // only those that match.
                if ($event->order)
                {
                    $upsServices = $this->settings->upsServices;

                    // get shipping rates.
                    $rates = UpsShippingRates::getInstance()->rates->getRates($event->order);

                    $shippingMethods = [];

                    if (Craft::$app->config->general->devMode)
                    {
                        Craft::info('Rates for Order #'.$event->order->id." (Order Number: ".$event->order->number, __CLASS__);
                    }

                    foreach ($rates as $rate)
                    {
                        $serviceCode = $rate->Service->getCode();
                        $monetaryValue = $rate->TotalCharges->MonetaryValue;

                        if (Craft::$app->config->general->devMode)
                        {
                            Craft::info('Rate: '.$monetaryValue, __CLASS__);
                        }

                        // If we have specified the service in our config, create a new shipping method.
                        if ( isset( $upsServices[$serviceCode] ) )
                        {
                            $shippingMethods[] = new ShippingMethod(
                                'UPS',
                                [
                                    'handle' => $upsServices[$serviceCode]['handle'],
                                    'name' => $upsServices[$serviceCode]['name']
                                ],
                                $rate,
                                $event->order
                            );
                        }
                    }

                    $event->shippingMethods = array_merge($event->shippingMethods, $shippingMethods);
                }

                // Return the display shipping methods.
                // These never match the order and are only used for display purposes in the CP.
                $event->shippingMethods = array_merge($event->shippingMethods, 
                                UpsShippingRates::getInstance()->shippingMethods->getAllShippingMethods());
            }
        });
    }

    // Protected Methods
    // =========================================================================

    protected function createSettingsModel()
    {
        return new UpsSettings();
    }

    protected function settingsHtml() : string
    {
        $settings = $this->getSettings();

        // Decrypt UPS Password before password is rendered to settings template
        $settings->upsPassword = \Craft::$app->security->decryptByKey(base64_decode($settings->upsPassword));

        return \Craft::$app->getView()->renderTemplate('upsshippingrates/settings', [
            'settings' => $settings
        ]);
    }
}
