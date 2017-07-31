<?php

namespace Craft;
use Ups\Rate;
use CommerceUpsRates\ShippingMethod;

require __DIR__.'/vendor/autoload.php';

/**
 * UPS Shipping Rates plugin for Craft Commerce
 *
 * Calculates UPS shipping rates using UPS Rating API
 *
 * @author    Rob Knecht
 * @copyright Copyright (c) 2017 Rob Knecht
 * @link      https://github.com/rmknecht
 * @package   UpsShippingRates
 * @since     0.0.1
 */

class UpsShippingRatesPlugin extends BasePlugin
{
    /**
     * Called after the plugin class is instantiated; do any one-time initialization here such as hooks and events:
     *
     * craft()->on('entries.saveEntry', function(Event $event) {
     *    // ...
     * });
     *
     * or loading any third party Composer packages via:
     *
     * require_once __DIR__ . '/vendor/autoload.php';
     *
     * @return mixed
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Returns the user-facing name.
     *
     * @return mixed
     */
    public function getName()
    {
         return Craft::t('UPS Shipping Rates');
    }

    /**
     * Plugins can have descriptions of themselves displayed on the Plugins page by adding a getDescription() method
     * on the primary plugin class:
     *
     * @return mixed
     */
    public function getDescription()
    {
        return Craft::t('Adds UPS shipping methods and rates to Craft Commerce.');
    }

    /**
     * Plugins can have links to their documentation on the Plugins page by adding a getDocumentationUrl() method on
     * the primary plugin class:
     *
     * @return string
     */
    public function getDocumentationUrl()
    {
        return 'https://github.com/rmknecht/upsshippingrates/blob/master/README.md';
    }

    /**
     * Plugins can now take part in Craft’s update notifications, and display release notes on the Updates page, by
     * providing a JSON feed that describes new releases, and adding a getReleaseFeedUrl() method on the primary
     * plugin class.
     *
     * @return string
     */
    public function getReleaseFeedUrl()
    {
        return 'https://raw.githubusercontent.com/rmknecht/upsshippingrates/master/releases.json';
    }

    /**
     * Returns the version number.
     *
     * @return string
     */
    public function getVersion()
    {
        return '0.0.1';
    }

    /**
     * As of Craft 2.5, Craft no longer takes the whole site down every time a plugin’s version number changes, in
     * case there are any new migrations that need to be run. Instead plugins must explicitly tell Craft that they
     * have new migrations by returning a new (higher) schema version number with a getSchemaVersion() method on
     * their primary plugin class:
     *
     * @return string
     */
    public function getSchemaVersion()
    {
        return '0.0.1';
    }

    /**
     * Returns the developer’s name.
     *
     * @return string
     */
    public function getDeveloper()
    {
        return 'Rob Knecht';
    }

    /**
     * Returns the developer’s website URL.
     *
     * @return string
     */
    public function getDeveloperUrl()
    {
        return 'https://github.com/rmknecht';
    }

    /**
     * Returns whether the plugin should get its own tab in the CP header.
     *
     * @return bool
     */
    public function hasCpSection()
    {
        return false;
    }

    /**
     * Called right before your plugin’s row gets stored in the plugins database table, and tables have been created
     * for it based on its records.
     */
    public function onBeforeInstall()
    {
    }

    /**
     * Called right after your plugin’s row has been stored in the plugins database table, and tables have been
     * created for it based on its records.
     */
    public function onAfterInstall()
    {
    }

    /**
     * Called right before your plugin’s record-based tables have been deleted, and its row in the plugins table
     * has been deleted.
     */
    public function onBeforeUninstall()
    {
    }

    /**
     * Called right after your plugin’s record-based tables have been deleted, and its row in the plugins table
     * has been deleted.
     */
    public function onAfterUninstall()
    {
    }

    /**
     * Defines the attributes that model your plugin’s available settings.
     *
     * @return array
     */
    protected function defineSettings()
    {
        return [
            'apiKey' => [
                AttributeType::String,
                'label'     => 'Production UPS Access Key',
                'default'   => '',
                'required'  => true
            ],

            'testApiKey' => [
                AttributeType::String,
                'label'     => 'Test UPS Access Key',
                'default'   => '',
                'required'  => false
            ],


            'upsUsername' => [
                AttributeType::String,
                'label'     => 'UPS Account Username',
                'default'   => '',
                'required'  => true
            ],


            'upsPassword' => [
                AttributeType::String,
                'label'     => 'UPS Account Password',
                'required'  => true
            ],

            'markup' => [
                AttributeType::Number,
                'label'     => 'Mark-up Percentage',
                'default'   => '0'
            ]
        ];
    }

    /**
     * Returns the HTML that displays your plugin’s settings.
     *
     * @return mixed
     */
    public function getSettingsHtml()
    {
        $settings = $this->getSettings();

        $settings->upsPassword = craft()->security->decrypt(base64_decode($settings->upsPassword));

        return craft()->templates->render('upsshippingrates/UpsShippingRates_Settings', array(
            'settings' => $settings
        ));
    }

    /**
     * If you need to do any processing on your settings’ post data before they’re saved to the database, you can
     * do it with the prepSettings() method:
     *
     * @param mixed $settings  The Widget's settings
     *
     * @return mixed
     */
    public function prepSettings($settings)
    {
        $settings['upsPassword'] = base64_encode(craft()->security->encrypt($settings['upsPassword']));

        return $settings;
    }


    /**
     * Returns the shipping methods available for the current order,
     * or just list the base shipping accounts.
     *
     * @param Commerce_OrderModel|null $order
     *
     * @return array
     */
    public function commerce_registerShippingMethods($order = null)
    {
        if ( $this->settings['apiKey'] !== '' && $this->settings['apiKey'] !== null )
        {
            // Don't bother returning all shipping methods when we are in the context of an order,
            // only those that match.
            if ($order)
            {
                $upsServices = craft()->config->get('upsServices', 'upsshippingrates');

                // get shipping rates.
                $rates = craft()->upsShippingRates_rates->getRates($order);

                $shippingMethods = [];

                if (craft()->config->get('devMode'))
                {
                    $this::log('Rates for Order #'.$order->id." (Order Number: ".$order->number);
                }

                foreach ($rates as $rate)
                {
                    $serviceCode = $rate->Service->getCode();
                    $monetaryValue = $rate->TotalCharges->MonetaryValue;

                    if (craft()->config->get('devMode'))
                    {
                        $this::log('Rate: '.$monetaryValue);
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
                            $order
                        );
                    }
                }

                return $shippingMethods;
            }

            // Return the display shipping methods.
            // These never match the order and are only used for display purposes in the CP.
            return craft()->upsShippingRates_shippingMethods->getAllShippingMethods();
        }
    }
}