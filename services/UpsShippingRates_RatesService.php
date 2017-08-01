<?php

namespace Craft;

use Ups\Rate;
use PhpUnitsOfMeasure\PhysicalQuantity\Length;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass;

/**
 * UPS Shipping Rates
 *
 * @author    Rob Knecht
 * @copyright Copyright (c) 2017 Surprise Highway
 * @link      https://github.com/surprisehighway
 * @package   UpsShippingRates
 * @since     0.0.1
 */

class UpsShippingRates_RatesService extends BaseApplicationComponent
{

	private $_shipmentsBySignature;

	public function init()
	{
		$this->_shipmentsBySignature = [];
	}

	/**
	* @param $order object
	* @return $rates array
	*/
	public function getRates(Commerce_OrderModel $order)
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
	private function _getShipment(Commerce_OrderModel $order)
	{
		$signature = $this->_getSignature($order);

		// Do we already have it on this request?
		if (isset($this->_shipmentsBySignature[$signature]) && $this->_shipmentsBySignature[$signature] != false)
		{
			return $this->_shipmentsBySignature[$signature];
		}

		$cacheKey = 'upsshippingrates-shipment-'.$signature;

		// Is it in the cache? if not, get it from the api.
		$shipment = craft()->cache->get($cacheKey);
		if (!$shipment)
		{
			$shipment = $this->_createShipment($order);
			$this->_shipmentsBySignature[$signature] = craft()->cache->set($cacheKey, $shipment);
		}

		$this->_shipmentsBySignature[$signature] = $shipment;

		return $this->_shipmentsBySignature[$signature];
	}

	// Creates a new UPS shipment
	private function _createShipment($order)
	{
		$upsSettings = craft()->plugins->getPlugin('upsshippingrates')->getSettings();

		// Default to the required production key if no test key is specified.
		$testKey = $upsSettings->testApiKey == '' ?  $upsSettings->apiKey : $upsSettings->testApiKey;

		$upsKey = craft()->config->get('devMode') ? $testKey : $upsSettings->apiKey;

		$rate = new \Ups\Rate(
			$upsKey,
			$upsSettings->upsUsername,
			craft()->security->decrypt(base64_decode($upsSettings->upsPassword)) // encrypted password
		);

		$shipment = new \Ups\Entity\Shipment();

		// From address
		$from_address_params = craft()->config->get('fromAddress', 'upsshippingrates');

		/** @var Commerce_AddressModel $shippingAddress */
		$shippingAddress = $order->shippingAddress;

		if (!$shippingAddress)
		{
			return false;
		}

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

		// Origin Address
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

		// Packaging
		$settings = craft()->plugins->getPlugin('commerce')->getSettings();

		// TODO: Move this to box packing algorithm
		$length = new Length($order->getTotalLength(), $settings->dimensionUnits);
		$width = new Length($order->getTotalWidth(), $settings->dimensionUnits);
		$height = new Length($order->getTotalHeight(), $settings->dimensionUnits);
		$weight = new Mass($order->getTotalWeight(), $settings->weightUnits);

		$parcel_params = [
			"length"	=> $length->toUnit('inch') ?: 0.1,
			"width"		=> $width->toUnit('inch') ?: 0.1,
			"height"	=> $height->toUnit('inch') ?: 0.1,
			"weight"	=> $weight->toUnit('lbs')
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

		return $rate->shopRates($shipment);
	}

	// Returns a hash dervied from our order's properties.
	private function _getSignature(Commerce_OrderModel $order)
	{
		$totalQty = $order->getTotalQty();
		$totalWeight = $order->getTotalWeight();
		$totalWidth = $order->getTotalWidth();
		$totalHeight = $order->getTotalHeight();
		$totalLength = $order->getTotalLength();
		$shippingAddress = Commerce_AddressRecord::model()->findById($order->shippingAddressId);
		$updated = "";
		if ($shippingAddress)
		{
			$updated = DateTimeHelper::toIso8601($shippingAddress->dateUpdated);
		}

		return md5($totalQty.$totalWeight.$totalWidth.$totalHeight.$totalLength.$updated);
	}
}