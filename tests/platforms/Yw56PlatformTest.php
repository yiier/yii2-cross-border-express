<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2021/4/16
 * Time: 下午9:58
 * File: Yw56PlatformTest.php
 */

namespace platforms;
require_once("../../vendor/autoload.php");
require_once("../PHPUnit_Framework_TestCase.php");

use yiier\crossBorderExpress\Express;
use yiier\crossBorderExpress\platforms\PlatformsName;

class Yw56PlatformTest extends \PHPUnit_Framework_TestCase
{

    private $config = [
        "timeout" => 60.0,
        "platforms" => [
            \yiier\crossBorderExpress\platforms\PlatformsName::YW56_PLATFORM => [
                "host" => "http://online.yw56.com.cn",
                "userId" => "30060893",
                "token" => "C50886D1314181E59BFB888955B1E13F",
                "ossBucket" => "",
                "ossAccessKeyId" => "",
                "ossAccessKeySecret" => "",
                "ossLanDomain" => "",
                "ossWanDomain" => "",
            ]
        ]
    ];

    public function testCreateOrder()
    {
        $express = new Express($this->config, PlatformsName::YW56_PLATFORM);
        try {
            $orderResult = $express->createOrder($this->getExpressOrder());

            var_dump($orderResult);
        } catch (\Exception $e) {
            $this->expectException($e->getMessage());
        }
    }

    public function testGetPrintUrl()
    {
        $express = new Express($this->config, PlatformsName::YW56_PLATFORM);
        try {
            $orderResult = $express->getPrintUrl("YBLYcgq6000128361926");

            var_dump($orderResult);
        } catch (\Exception $e) {
            $this->expectException($e->getMessage());
        }
    }
}
