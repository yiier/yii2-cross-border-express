<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Date: 2020/4/3
 * Time: 22:18
 * File: EtowerTest.php
 */

namespace platforms;

//require("../../src/Config.php");
//require("../../src/Express.php");
//require("../../src/CountryCodes.php");
//require("../../src/Factory.php");
//require("../../src/contracts/Order.php");
//require("../../src/contracts/Goods.php");
//require("../../src/platforms/PlatformsName.php");
//require("../../src/platforms/EtowerPlatform.php");
//require("../../src/platforms/Platform.php");

require_once("../../vendor/autoload.php");

use PHPUnit\Framework\TestCase;
use yiier\crossBorderExpress\contracts\Goods;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\Package;
use yiier\crossBorderExpress\contracts\Recipient;
use yiier\crossBorderExpress\contracts\Shipper;
use yiier\crossBorderExpress\Express;
use yiier\crossBorderExpress\platforms\PlatformsName;

class EtowerPlatformTest extends TestCase
{
    private $config = [
        "timeout" => 60.0,
        "platforms" => [
            PlatformsName::ETOWER_PLATFORM => [
                "host" => "http://qa.etowertech.com",
                "token" => "test5AdbzO5OEeOpvgAVXUFE0A",
                "key" => "79db9e5OEeOpvgAVXUFWSD"
            ]
        ]
    ];

    public function testGetTransportsByCountryCode()
    {

    }

    public function testGetPrintUrl()
    {

    }

    public function testGetOrderAllFee()
    {

    }

    public function testGetOrderFee()
    {

    }

    public function testCreateOrder()
    {
//        $order = new Order();
//        $order->customerOrderNo = "";
//        $order->transportCode = "";
//        $order->goods = [];
//        $order->evaluate = ""; // 报税
//        $order->taxesNumber = '91440300MA5DKRQT36'; // 税号
//        $order->isRemoteConfirm = 1; // 是否同意收偏远费
//        $order->isReturn = 0; // 是否退件
//        $order->withBattery = 0; // 是否带电池

        $expressOrder = new Order();
        $expressOrder->customerOrderNo = 'xxx';
        $expressOrder->transportCode = 'xxx';

        $goods = new Goods();
        $goods->description = 'xxx';
        $goods->cnDescription = 'xxx';
        $goods->quantity = 'xxx';
        $goods->weight = 'xxx';
        $goods->hsCode = 'xxx';
        $goods->enMaterial = 'cotton';
        $goods->cnMaterial = '棉';
        $goods->worth = 1; // 1美元;
        $goods->sku = 'xxx'; // 云途某些渠道需要

        $expressOrder->goods = [$goods];
        $expressOrder->evaluate = 1; // 1美元
        $expressOrder->taxesNumber = 'xxx'; // 税号
        $expressOrder->isRemoteConfirm = 1; // 是否同意收偏远费
        $expressOrder->isReturn = 1; // 是否退件
        $expressOrder->withBattery = 0; // 是否带电池

        $package = new Package();
        $package->description = 'xxxx';
        $package->quantity = 1;
        $package->weight = 'xxx';
        $package->declareWorth = 1; // 1美元
        $expressOrder->package = $package;


        $recipient = new Recipient();
        $recipient->countryCode = 'xx';
        $recipient->name = 'xx';
        $recipient->address = 'xx';
        $recipient->city = 'xx';
        $recipient->state = 'xx';
        $recipient->zip = 'xx';
        $recipient->phone = 'xx';
        $expressOrder->recipient = $recipient;

        $shipper = new Shipper();
        $shipper->countryCode = 'CN';
        $shipper->name = 'xxx';
        $shipper->company = 'xxx';
        $shipper->address = 'xxx';
        $shipper->city = 'xxx';
        $shipper->state = 'xxx';
        $shipper->zip = 'xx';
        $shipper->phone = 'xxx';
        $expressOrder->shipper = $shipper;

        $express = new Express($this->config, PlatformsName::ETOWER_PLATFORM);
        $orderResult = $express->createOrder($expressOrder);

        var_dump($orderResult);
    }

    public function testGetClient()
    {

    }
}
