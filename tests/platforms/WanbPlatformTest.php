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

use yiier\crossBorderExpress\contracts\Goods;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Package;
use yiier\crossBorderExpress\contracts\Recipient;
use yiier\crossBorderExpress\contracts\Shipper;
use yiier\crossBorderExpress\Express;
use PHPUnit\Framework\TestCase;
use yiier\crossBorderExpress\platforms\PlatformsName;

class WanbPlatformTest extends \PHPUnit_Framework_TestCase
{

    private $config = [
        "timeout" => 60.0,
        "platforms" => [
            \yiier\crossBorderExpress\platforms\PlatformsName::WANB_PLATFORM => [
                "host" => "http://api-sbx.wanbexpress.com",
                "account_no" => "TEST",
                "token" => "DK49AjVbKj23bdk",
                "shipping_method" => "3HPA",
                "warehouse_code" => "SZ"
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

    }

    public function testCreateOrder()
    {
        $express = new Express($this->config, PlatformsName::WANB_PLATFORM);
        try {
            $orderResult = $express->createOrder($this->getExpressOrder());

            var_dump($orderResult);
        } catch (\Exception $e) {
            $this->expectException($e->getMessage());
        }
    }

    public function testGetOrderAllFee()
    {

    }
}
