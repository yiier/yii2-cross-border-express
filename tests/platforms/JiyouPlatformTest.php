<?php

namespace platforms;

use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\Express;
use yiier\crossBorderExpress\platforms\PlatformsName;

class JiyouPlatformTest extends \PHPUnit_Framework_TestCase
{
    private $config = [
        "timeout" => 60.0,
        "platforms" => [
            PlatformsName::JIYOU_PLATFORM => [
                "host" => "",
                "user_token" => "",
            ]
        ]
    ];

    public function testGetOrderFee()
    {
        $express = new Express($this->config, PlatformsName::HUALEI_PLATFORM);
        try {
            /** @var OrderResult $orderResult */
            $orderResult = $express->createOrder($this->getExpressOrder());
        } catch (\Exception $e) {
            echo sprintf("error: %s\n", $e->getMessage());
        }

        var_dump($orderResult);
    }

    public function testGetPrintUrl()
    {

    }

    public function testGetClient()
    {

    }

    public function testCreateOrder()
    {

    }

    public function testGetTransportsByCountryCode()
    {

    }

    public function testGetOrderAllFee()
    {

    }
}
