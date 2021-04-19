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
                "host" => "http://47.96.220.163:802",
                "userId" => "100000",
                "token" => "D6140AA383FD8515B09028C586493DDB",
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
            $orderResult = $express->getPrintUrl("YH100001468");

            var_dump($orderResult);
        } catch (\Exception $e) {
            $this->expectException($e->getMessage());
        }
    }
}
