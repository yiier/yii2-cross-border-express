<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Date: 2020/4/3
 * Time: 22:18
 * File: EtowerTest.php
 */

namespace platforms;

require_once("../../vendor/autoload.php");

use phpDocumentor\Reflection\DocBlock\Tags\Formatter;
use PHPUnit\Framework\TestCase;
use yiier\crossBorderExpress\contracts\Goods;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\Package;
use yiier\crossBorderExpress\contracts\Recipient;
use yiier\crossBorderExpress\contracts\Shipper;
use yiier\crossBorderExpress\exceptions\ExpressException;
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
//                "host" => "https://cn.etowertech.com",
//                "token" => "pclTwbivO-ppqeEQbKceeY",
//                "key" => "bAw_Qgqp7JA4QiOdsqnI4Q"
            ]
        ]
    ];

    public function testGetTransportsByCountryCode()
    {

    }

    public function testGetPrintUrl()
    {
        $express = new Express($this->config, PlatformsName::ETOWER_PLATFORM);

        $orderNumber = 'GV284853828GB';
        $express->getPrintUrl($orderNumber);
    }

    public function testGetOrderAllFee()
    {
        $express = new Express($this->config, PlatformsName::ETOWER_PLATFORM);

        $query = ['GV284853828GB'];
        $orderFee = $express->getOrderAllFee($query);
    }

    public function testGetOrderFee()
    {
        $express = new Express($this->config, PlatformsName::ETOWER_PLATFORM);

        $orderNumber = 'GV284853828GB';
        /** @var OrderFee $orderFee */
        $orderFee = $express->getOrderFee($orderNumber);

//        $this->assertIsString("Success", $orderFee->);
//        $this->assertIsString($orderResult, "Success");
        echo $orderFee->data;
    }

    public function testCreateOrder()
    {
        $t = new \DateTime();

        $expressOrder = new Order();
        $expressOrder->customerOrderNo = $t->format("YmdHis");
        $expressOrder->transportCode = sprintf("CN%s", $t->format("YmdHis"));

        $goods = new Goods();
        $goods->description = 'shoes';
        $goods->cnDescription = '包含中文字符';
        $goods->quantity = 1;
        $goods->weight = 0.776;
        $goods->hsCode = 'TT11';
        $goods->enMaterial = 'cotton';
        $goods->cnMaterial = '棉';
        $goods->worth = 50; // 1美元;
        $goods->sku = 'T1818ZS39*1-1'; // 云途某些渠道需要

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
        $recipient->countryCode = 'AU';
        $recipient->name = 'Bob';
        $recipient->address = '200 Bumborah Point Rd';
        $recipient->city = 'PORT BOTANY';
        $recipient->state = 'NSW';
        $recipient->zip = '2036';
        $recipient->phone = '';
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

        $express = new Express($this->config, PlatformsName::ETOWER_PLATFORM);
        try {
            $orderResult = $express->createOrder($expressOrder);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

//        $this->assertIsString($orderResult, "Success");
        var_dump($orderResult);
    }

    public function testGetClient()
    {

    }
}
