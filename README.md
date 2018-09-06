# UPS Shipping Rates plugin for Craft CMS 3.x & Craft Commerce 2.x

Adds UPS shipping methods and live rates to Craft Commerce 2.

![Screenshot](resources/screenshot.png)

This plugin is in beta and bugs may be present. Please document any issues you encounter at our [Github Issues](https://github.com/surprisehighway/craft-upsshippingrates/issues) page.

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later and Craft Commerce 2.x.

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require https://github.com/surprisehighway/craft-upsshippingrates/

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for UPS Shipping Rates.

## UPS Shipping Rates Overview

The UPS Shipping Rates plugin provides UPS shipping methods to Craft Commerce. UPS shipping methods can be enabled individually and will be displayed, along with any other configured shipping option, at checkout.

UPS shipping rates are calculated live, via the UPS Rating API, for both U.S. Domestic and International addresses using the supplied customer address.

UPS Freight services are not currently supported.

## Configuring UPS Shipping Rates

In order to access the UPS rating service, you must have the following:
- An active UPS account username and password.
- A valid UPS API Access Key with “Production” access to “Rating - Package”

Visit [https://www.ups.com/upsdeveloperkit](https://www.ups.com/upsdeveloperkit) to setup your account and generate an access key.

Once you have obtained the above:

1. Copy config.php from the `upsshippingrates` directory to your craft/config folder and rename it to `upsshippingrates.php`
2. Specify a valid orgin address within the `fromAddress` array
3. Comment out any shipping methods within the `upsServices` array that you do not want to offer to customers
4. -OPTIONAL- Order pricing can be modified within 'modifyPrice'
5. Navigate to the settings page `/settings/plugins/upsshippingrates`
6. Supply your Production Access Key, Account Name, and Password
7. -OPTIONAL- Enter a test key if available
8. -OPTIONAL- Add a percentage based markup for shipping rates, 0-100
9. Save your settings

UPS shipping rates will now be calculated for products that have dimensions and weights specified.

## Using UPS Shipping Rates

After successfully configuring the plugin your shipping methods will appear as shipping methods during checkout.

![Shipping Methods](resources/shipping-methods.png)


**A weight value is required for product and variants entries to calcuate shipping costs.**

![Weight input](resources/weight-input.png)

The UPS Shipping Rates plugin will automatically convert from the "Weight Unit" specified in Craft Commerce's settings at `/commerce/settings/general`
. Width, Length, and Height dimensions are not required; however, large shipments should included these values to generate an accurate shipping rate.

If the a UPS service is available for the customer's shipping address, a live rate will be returned during the checkout process.

## UPS Shipping Rates Roadmap

Some things to do, and ideas for potential features:

* Release it

Brought to you by [Surprise Highway](https://github.com/surprisehighway)
