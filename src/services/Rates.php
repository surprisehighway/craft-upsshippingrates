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

use Craft;
use craft\base\Component;
use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;
use craft\commerce\records\Address as AddressRecord;
use craft\helpers\DateTimeHelper;
use Ups\Rate;
use PhpUnitsOfMeasure\PhysicalQuantity\Length;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass;


/**
 * @author    Surprise Highway
 * @package   UpsShippingRates
 * @since     2.0.0-beta
 */
class Rates extends Component
{
    private $_shipmentsBySignature;

    private $_totalWidth;
    private $_totalHeight;
    private $_totalLength;

    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->_shipmentsBySignature = [];
    }

    /**
    * @param $order object
    * @return $rates array
    */
    public function getRates(Order $order)
    {
        $shipment = $this->_getShipment($order);

        $rates = [];

        if ($shipment)
        {
            $shipment->RatedShipment;

            foreach ($shipment->RatedShipment as $rate)
            {
                $rates[] = $rate;
            }
        }

        return $rates;
    }

    // Looks up our shipment in the cache. If it doesn't exist call a new shipment quote.
    private function _getShipment(Order $order)
    {
        $signature = $this->_getSignature($order);

        // Do we already have it on this request?
        if (isset($this->_shipmentsBySignature[$signature]) && $this->_shipmentsBySignature[$signature] != false)
        {
            return $this->_shipmentsBySignature[$signature];
        }

        $cacheKey = 'upsshippingrates-shipment-'.$signature;

        // Is it in the cache? if not, get it from the api.
        $shipment = Craft::$app->getCache()->get($cacheKey);
        $shipment = '';
        if (!$shipment)
        {
            $shipment = $this->_createShipment($order);
            $this->_shipmentsBySignature[$signature] = Craft::$app->getCache()->set($cacheKey, $shipment);
        }

        $this->_shipmentsBySignature[$signature] = $shipment;

        return $this->_shipmentsBySignature[$signature];
    }

    /* Creates a new UPS shipment
    * @return Ups\Entity\RateResponse Object
    */
    private function _createShipment($order)
    {
        $upsSettings = UpsShippingRates::getInstance()->getSettings();

        // Default to the required production key if no test key is specified.
        $testKey = $upsSettings->testApiKey == '' ?  $upsSettings->apiKey : $upsSettings->testApiKey;

        $upsKey = Craft::$app->config->general->devMode ? $testKey : $upsSettings->apiKey;

        $rate = new \Ups\Rate(
            $upsKey,
            $upsSettings->upsUsername,
            \Craft::$app->security->decryptByKey(base64_decode($upsSettings->upsPassword)) // encrypted password
        );

        // Specify our Account Number
        $shipper = new \Ups\Entity\Shipper();
        $shipper->setShipperNumber($upsSettings->upsUsername);

        $shipment = new \Ups\Entity\Shipment();
        $shipment->setShipper($shipper);


        // From address
        $from_address_params = UpsShippingRates::getInstance()->settings['fromAddress'];

        /** @var Commerce_AddressModel $shippingAddress */
        $shippingAddress = $order->shippingAddress;

        if (!$shippingAddress)
        {
            return false;
        }


        // Origin Address
        $to_address_params = [
            "name"           => $shippingAddress->getFullName(),
            "street1"        => $shippingAddress->address1,
            "street2"        => $shippingAddress->address2,
            "city"           => $shippingAddress->city,
            "state"          => $shippingAddress->getState() ? $shippingAddress->getState()->abbreviation : $shippingAddress->getStateText(),
            "zip"            => $shippingAddress->zipCode,
            "country"        => $shippingAddress->getCountry()->iso,
            "phone"          => $shippingAddress->phone,
            "company"        => $shippingAddress->businessName,
            "residential"    => $shippingAddress->businessName ? true : false,
            "email"          => $order->email,
            "federal_tax_id" => $shippingAddress->businessTaxId
        ];

        $shipperAddress = $shipment->getShipper()->getAddress();

        $shipperAddress->setAddressLine1($from_address_params['street1']);
        $shipperAddress->setAddressLine2($from_address_params['street2']);
        $shipperAddress->setCity($from_address_params['city']);
        $shipperAddress->setStateProvinceCode($from_address_params['state']);
        $shipperAddress->setPostalCode($from_address_params['zip']);


        // Destination Address
        $shipTo = $shipment->getShipTo();
        $shipTo->setCompanyName($to_address_params['company']);

        $shipToAddress = $shipTo->getAddress();
        $shipToAddress->setAddressLine1($to_address_params['street1']);
        $shipToAddress->setAddressLine2($to_address_params['street2']);
        $shipToAddress->setCity($to_address_params['city']);
        $shipToAddress->setStateProvinceCode($to_address_params['state']);
        $shipToAddress->setPostalCode($to_address_params['zip']);
        $shipToAddress->setCountryCode($to_address_params['country']);


        // Delivery address is residential
        $shipToAddress->setResidentialAddressIndicator(true);


        // Packaging
        $settings = Commerce::getInstance()->getSettings();

        // TODO: Move this to box packing algorithm
        $length = new Length($this->_totalLength, $settings->dimensionUnits);
        $width = new Length($this->_totalWidth, $settings->dimensionUnits);
        $height = new Length($this->_totalHeight, $settings->dimensionUnits);
        $weight = new Mass($order->getTotalWeight(), $settings->weightUnits);

        $parcel_params = [
            "length"    => $length->toUnit('inch') ?: 0.1,
            "width"     => $width->toUnit('inch') ?: 0.1,
            "height"    => $height->toUnit('inch') ?: 0.1,
            "weight"    => $weight->toUnit('lbs')
        ];

        if ($parcel_params['weight'] == 0)
        {
            return false;
        }

        $package = new \Ups\Entity\Package();
        $package->getPackagingType()->setCode(\Ups\Entity\PackagingType::PT_PACKAGE);
        $package->getPackageWeight()->setWeight($parcel_params['weight']);

        $dimensions = new \Ups\Entity\Dimensions();
        $dimensions->setHeight($parcel_params['length']);
        $dimensions->setWidth($parcel_params['width']);
        $dimensions->setLength($parcel_params['height']);

        $unit = new \Ups\Entity\UnitOfMeasurement;
        $unit->setCode(\Ups\Entity\UnitOfMeasurement::UOM_IN);

        $dimensions->setUnitOfMeasurement($unit);
        $package->setDimensions($dimensions);

        $shipment->addPackage($package);

$shipment->setNumOfPiecesInShipment(1);

        // Include account's negotiates rates in the response.
        $shipment->showNegotiatedRates();

        $ratingMethodRequestIndicator = new \Ups\Entity\RateInformation;

        $rates = false;

        try {
            $rates = $rate->shopRates($shipment);
        } catch (\Exception $e) {
            UpsShippingRatesPlugin::log('Caught exception: '.  $e->getMessage(),  LogLevel::Error);
        }

        return $rates;
    }

    // Returns a hash dervied from our order's properties.
    private function _getSignature(Order $order)
    {
        $this->_totalWidth = $this->_totalHeight = $this->_totalLength = 0;
        $this->_setTotals($order);

        $totalQty = $order->getTotalQty();
        $totalWeight = $order->getTotalWeight();
        $totalWidth = $this->_totalWidth;
        $totalHeight = $this->_totalHeight;
        $totalLength = $this->_totalLength;
        $shippingAddress = AddressRecord::findOne([$order->shippingAddressId]);
        $updated = "";
        if ($shippingAddress)
        {
            $updated = DateTimeHelper::toIso8601($shippingAddress->dateUpdated);
        }

        return md5($totalQty.$totalWeight.$totalWidth.$totalHeight.$totalLength.$updated);
    }

    private function _setTotals(Order $order)
    {
        foreach ($order->getLineItems() as $item) {
            $this->_totalWidth += $item->width;
            $this->_totalHeight += $item->height;
            $this->_totalLength += $item->length;
        }
    }
}
