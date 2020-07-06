<?php

namespace platforms;


require_once("../../vendor/autoload.php");

include_once "../PHPUnit_Framework_TestCase.php";

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
        $express = new Express($this->config, PlatformsName::JIYOU_PLATFORM);
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
        $express = new Express($this->config, PlatformsName::JIYOU_PLATFORM);
        $res = $express->getPrintUrl("883209123");
        echo $res;
        $this->assertNotNull($res);

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
}
