<?php

use yiier\crossBorderExpress\contracts\Goods;
use yiier\crossBorderExpress\contracts\Package;

/**
 * Created by PhpStorm.
 * User: LatteCake
 * Date: 2020/4/3
 * Time: 22:18
 * File: ${FILE_NAME}
 */
class PHPUnit_Framework_TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @return \yiier\crossBorderExpress\contracts\Order
     * @throws Exception
     */
    public function getExpressOrder(): \yiier\crossBorderExpress\contracts\Order
    {
        $t = new \DateTime();

        $expressOrder = new \yiier\crossBorderExpress\contracts\Order();
        $expressOrder->customerOrderNo = $t->format("YmdHis");
        $expressOrder->transportCode = "HKPOSTTH";//sprintf("CN%s", $t->format("YmdHis"));

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
