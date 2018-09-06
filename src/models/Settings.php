<?php

namespace surprisehighway\upsshippingrates\models;

use craft\base\Model;

class Settings extends Model
{
    public $apiKey;
    public $testApiKey;
    public $upsUsername;
    public $upsPassword;
    public $showNegotiatedRates;
    public $markup;
    public $upsServices;
    public $fromAddress;
    public $modifyPrice;

    public function rules()
    {
        return [
            [['apiKey', 'testApiKey'], 'string'],
            [['upsUsername', 'upsPassword'], 'required'],
        ];
    }
}