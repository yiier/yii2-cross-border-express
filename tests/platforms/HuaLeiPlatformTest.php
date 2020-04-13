<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2020/4/7
 * Time: 15:06
 * File: HuaLeiPlatformTest.php
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
use yiier\crossBorderExpress\platforms\HualeiPlatform;
use PHPUnit\Framework\TestCase;
use yiier\crossBorderExpress\platforms\PlatformsName;

class HuaLeiPlatformTest extends TestCase
{
    private $config = [
        "timeout" => 60.0,
        "platforms" => [
            PlatformsName::HUALEI_PLATFORM => [
                "host" => "",
                "print_host" => "",
                "username" => "",
                "password" => "",
                "customer_id" => "", // 如果有这customer_id和customer_user_id ，可以不填username,password
                "customer_user_id" => ""
            ]
        ]
    ];

    public function testCreateOrder()
    {
        $t = new \DateTime();

        $expressOrder = new Order();
        $expressOrder->customerOrderNo = $t->format("YmdHis");
        $expressOrder->transportCode = "1981";//sprintf("CN%s", $t->format("YmdHis"));

        $goods = new Goods();
        $goods->description = 'shoes';
        $goods->cnDescription = '包含中文字符';
        $goods->quantity = 1;
        $goods->weight = 0.776;
        $goods->hsCode = 'TT11';
        $goods->enMaterial = 'cotton';
        $goods->cnMaterial = '棉';
        $goods->worth = 50; // 1美元;
        $goods->sku = '椅子'; // 云途某些渠道需要

        $expressOrder->goods = [$goods];
        $expressOrder->evaluate = 1; // 1美元
        $expressOrder->taxesNumber = ''; // 税号
        $expressOrder->isRemoteConfirm = 1; // 是否同意收偏远费
        $expressOrder->isReturn = 1; // 是否退件
        $expressOrder->withBattery = 0; // 是否带电池

        $package = new Package();
        $package->description = 'xxxx';
        $package->quantity = 1;
        $package->weight = 0.766;
        $package->declareWorth = 1; // 1美元
        $expressOrder->package = $package;


        $recipient = new Recipient();
        $recipient->countryCode = 'MY';
        $recipient->name = 'Bob';
        $recipient->address = '200 Bumborah Point Rd';
        $recipient->city = 'PORT BOTANY';
        $recipient->state = 'NSW';
        $recipient->zip = '43000';
        $recipient->email = 'hello@gmail.com';
        $recipient->phone = '17090110293';
        $expressOrder->recipient = $recipient;

        $shipper = new Shipper();
        $shipper->countryCode = 'CN';
        $shipper->name = '哈哈';
        $shipper->company = '超级翁一';
        $shipper->address = '北京市';
        $shipper->city = '北京';
        $shipper->state = "北京";
        $shipper->zip = '100022';
        $shipper->phone = '17091022322';
        $expressOrder->shipper = $shipper;

        $express = new Express($this->config, PlatformsName::HUALEI_PLATFORM);
        try {
            /** @var OrderResult $orderResult */
            $orderResult = $express->createOrder($expressOrder);
        } catch (\Exception $e) {
            echo sprintf("error: %s\n", $e->getMessage());
        }

        echo sprintf("expressTrackingNumber: %s\nexpressAgentNumber: %s\nexpressNumber: %s\n",
            $orderResult->expressTrackingNumber,
            $orderResult->expressAgentNumber,
            $orderResult->expressNumber);

        $this->assertIsObject($orderResult);
        $this->assertIsString($orderResult->expressTrackingNumber);
    }

    public function testGetOrderAllFee()
    {

    }

    public function testGetOrderFee()
    {
        $orderNumber = "DAYIN224468"; // 订单跟踪号
    }

    public function testGetTransportsByCountryCode()
    {

    }

    public function testGetPrintUrl()
    {
        $orderNumber = "283932"; // 订单号
        $express = new Express($this->config, PlatformsName::HUALEI_PLATFORM);
        $res = $express->getPrintUrl($orderNumber);
        echo $res;
        $this->assertNotNull($res);
    }
}
