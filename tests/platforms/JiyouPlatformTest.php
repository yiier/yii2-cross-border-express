<?php

namespace platforms;

require_once("../../vendor/autoload.php");

use PHPUnit\Framework\TestCase;
use yiier\crossBorderExpress\contracts\Goods;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Package;
use yiier\crossBorderExpress\Express;
use yiier\crossBorderExpress\platforms\PlatformsName;

class JiyouPlatformTest extends TestCase
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
        $orderNumber = "283932"; // 订单号
        $express = new Express($this->config, PlatformsName::JIYOU_PLATFORM);
        $res = $express->getPrintUrl($orderNumber);
        echo $res;
        $this->assertNotNull($res);
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

    /**
     * @return \yiier\crossBorderExpress\contracts\Order
     * @throws \Exception
     */
    protected function getExpressOrder(): \yiier\crossBorderExpress\contracts\Order
    {
        $t = new \DateTime();

        $expressOrder = new \yiier\crossBorderExpress\contracts\Order();
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


        $recipient = new \yiier\crossBorderExpress\contracts\Recipient();
        $recipient->countryCode = 'MY';
        $recipient->name = 'Bob';
        $recipient->address = '200 Bumborah Point Rd';
        $recipient->city = 'PORT BOTANY';
        $recipient->state = 'NSW';
        $recipient->zip = '43000';
        $recipient->email = 'hello@gmail.com';
        $recipient->phone = '17090110293';
        $expressOrder->recipient = $recipient;

        $shipper = new \yiier\crossBorderExpress\contracts\Shipper();
        $shipper->countryCode = 'CN';
        $shipper->name = '哈哈';
        $shipper->company = '超级翁一';
        $shipper->address = '北京市';
        $shipper->city = '北京';
        $shipper->state = "北京";
        $shipper->zip = '100022';
        $shipper->phone = '17091022322';
        $expressOrder->shipper = $shipper;

        return $expressOrder;
    }
}
