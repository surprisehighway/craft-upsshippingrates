<?php

/**
* Copy and place within your craft config directoy at /craft/config/upsshippingrates.php
* Be sure to rename the config file 'upsshippingrates.php'
*/

return [
	// The address you will be posting from.
	'fromAddress' => [
		"name"    => "John Doe",
		"street1" => "201 E Randolph St",
		"street2" => "",
		"city"    => "Chicago",
		"state"   => "IL",
		"zip"     => "60601",
		"phone"   => "123-456-789"
	],

	// Available UPS service levels.
	// Comment lines to disable services.
	'upsServices' => [
		/* International shipments originating in United States  */
		'11' => [ "handle" => "UPSStandard", 			"name" => 'Standard'],
		'07' => [ "handle" => "WorldwideExpress", 		"name" => 'Worldwide Express'],
		'08' => [ "handle" => "WorldwideExpedited", 	"name" => 'Worldwide Expedited'],
		'54' => [ "handle" => "WorldwideExpressPlus", 	"name" => 'Worldwide Express Plus'],
		'65' => [ "handle" => "UPSSaver",				"name" => 'Saver'],

		/* United States domestic shipments */
		'02' => [ "handle" => "2ndDayAir", 			"name" => 'Second Day Air'],
		'59' => [ "handle" => "2ndDayAirAM", 		"name" => 'Second Day Air A.M.'],
		'12' => [ "handle" => "3DaySelect", 		"name" => 'Three-Day Select'],
		'03' => [ "handle" => "Ground", 			"name" => 'Ground'],
		'01' => [ "handle" => "NextDayAir", 		"name" => 'Next Day Air'],
		'14' => [ "handle" => "NextDayAirEarlyAM", 	"name" => 'Next Day Air Early A.M.'],
		'13' => [ "handle" => "NextDayAirSaver", 	"name" => 'Next Day Air Saver'],
	],

	// This setting can change the price of any rate returned from UPS
	// The shipping method handle is the combination of carrier account id and service level id
	'modifyPrice'     => function ($shippingMethodHandle, $order, $price)
	{

		// Example of modifying the price based on order totalPrice
		/*
		if($order->totalPrice >= 100)
		{
			return $price + 10;
		}
		return $price + 5;
		*/

		return $price;
	}
];