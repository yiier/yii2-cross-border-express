<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/23 8:52 PM
 * description:
 */

namespace yiier\crossBorderExpress\contracts;

/**
 * 包裹
 * Class Package
 * @package yiier\crossBorderExpress\contracts
 */
class Package
{
    /**
     * 包裹内物品描述，必填
     * @var string
     */
    public $description;

    /**
     * 包裹内物品数量，必填
     * @var integer
     */
    public $quantity;

    /**
     * 包裹内物品申报价格，单位美元，必填
     * @var float
     */
    public $declareWorth;

    /**
     * 包裹内物品重量，非必填
     * @var float
     */
    public $weight;

    /**
     * 包裹内物品长度，非必填
     * @var float
     */
    public $length;

    /**
     * 包裹内物品申宽度，非必填
     * @var float
     */
    public $width;

    /**
     * 包裹内物品高度，非必填
     * @var float
     */
    public $height;
}