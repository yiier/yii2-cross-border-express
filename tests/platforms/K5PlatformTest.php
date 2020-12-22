<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2020/7/16
 * Time: 00:05
 * File: WanbPlatformTest.php
 */

namespace platforms;


require_once("../../vendor/autoload.php");
require_once("../PHPUnit_Framework_TestCase.php");
use yiier\crossBorderExpress\test;
use yiier\crossBorderExpress\contracts\Goods;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Package;
use yiier\crossBorderExpress\contracts\Recipient;
use yiier\crossBorderExpress\contracts\Shipper;
use yiier\crossBorderExpress\Express;
use PHPUnit\Framework\TestCase;
use yiier\crossBorderExpress\platforms\PlatformsName;

class K5PlatformTest extends \PHPUnit_Framework_TestCase
{

    private $config = [
        "timeout" => 60.0,
        "platforms" => [
            \yiier\crossBorderExpress\platforms\PlatformsName::K5_PLATFORM => [
                "host" => "http://xt.jiehang.net",
                "clientid" => "KLXX008",
                "token" => "IPLLEx18PbUSd8Fygejo",
                //"warehouse_code" => "SZ"
            ]
        ]
    ];


    public function testGetOrderFee()
    {

    }

    public function testGetTransportsByCountryCode()
    {

    }

    public function testGetPrintUrl()
    {
        $express = new Express($this->config, PlatformsName::K5_PLATFORM);
        $res = $express->getPrintUrl("EYT0122169080SZ");
        var_dump($res);

    }

    public function testCreateOrder()
    {
        $express = new Express($this->config, PlatformsName::K5_PLATFORM);
        try {
			//var_dump($express->searchStartChannel());exit;
            $orderResult = $express->createOrder($this->getExpressOrder());

             var_dump($orderResult->expressNumber);

        } catch (\Exception $e) {
            $this->expectException($e->getMessage());
        }
    }

    public function testGetOrderAllFee()
    {

    }
}
