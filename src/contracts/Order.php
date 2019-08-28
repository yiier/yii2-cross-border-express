<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/23 6:36 PM
 * description:
 */

namespace yiier\crossBorderExpress\contracts;


class Order
{
    /**
     * 客户的订单标识
     * @var string
     */
    public $customerOrderNo;

    /**
     * 运输方式代码，必填
     * @var string
     */
    public $transportCode;

    /**
     * 投保价值，必填
     * @var float
     */
    public $evaluate;

    /**
     * 税号
     * @var string
     */
    public $taxesNumber;

    /**
     * 是否同意收偏远费 0 不同意，1 同意
     * @var integer
     */
    public $isRemoteConfirm;

    /**
     * 是否退件 0 否，1 是，默认0，部分支持退件
     * @var integer
     */
    public $isReturn;

    /**
     * 是否带电池，1 是，默认 0
     * @var integer
     */
    public $withBattery;

    /**
     * 包裹物品
     * @var Goods[]
     */
    public $goods;

    /**
     * 包裹物品
     * @var Package
     */
    public $package;

    /**
     * 发件人
     * @var Shipper
     */
    public $shipper;

    /**
     * 收件人
     * @var Recipient
     */
    public $recipient;

    /**
     * 每票的件数, 三态有这些方式 HKDHL,HKDHL1,CNUPS,SZUPS,HKUPS, SGDHL,EUTLP,CNFEDEX,HKFEDEX,CNS FEDEX,HKSFEDEX,EUEXP3 必填。默认是 1
     * @var integer
     */
    public $pieceNumber;

}