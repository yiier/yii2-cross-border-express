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

use yiier\crossBorderExpress\platforms\EccangPlatform;
use PHPUnit\Framework\TestCase;

class EccangPlatformTest extends TestCase
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

    }

    public function testGetClient()
    {

    }
}
