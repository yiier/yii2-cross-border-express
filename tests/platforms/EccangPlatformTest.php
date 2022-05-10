<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2020/9/21
 * Time: 22:37
 * File: EccangPlatformTest.php
 */

namespace platforms;

use PHPUnit_Framework_TestCase;
use yiier\crossBorderExpress\Express;
use yiier\crossBorderExpress\platforms\PlatformsName;

class EccangPlatformTest extends  PHPUnit_Framework_TestCase
{
    private $config = [
        "timeout" => 60.0,
        "platforms" => [
            \yiier\crossBorderExpress\platforms\PlatformsName::ECCANG_PLATFORM => [
                "appKey" => "",
                "appToken" => "",
            ]
        ]
    ];

    public function testGetTransportsByCountryCode()
    {

    }

    public function testGetPrintUrl()
    {

    }

    public function testGetOrderFee()
    {

    }

    public function testGetOrderAllFee()
    {

    }

    public function testCreateOrder()
    {
        $express = new Express($this->config, PlatformsName::ECCANG_PLATFORM);
        try {
            $orderResult = $express->createOrder($this->getExpressOrder());

            var_dump($orderResult);
        } catch (\Exception $e) {
            $this->expectException($e->getMessage());
        }
    }

    public function testGetClient()
    {

    }
}
