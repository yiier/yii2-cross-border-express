<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/23 9:01 PM
 * description:
 */

namespace yiier\crossBorderExpress\contracts;

/**
 * 包裹内的物品
 * Class Goods
 * @package yiier\crossBorderExpress\contracts
 */
class Goods
{
    /**
     * 物品描述，必填
     * @var string
     */
    public $description;

    /**
     * 物品中文描述，必填
     * @var float
     */
    public $cnDescription;

    /**
     * 物品数量，必填
     * @var integer
     */
    public $quantity;

    /**
     * 物品价值，单位美元，必填
     * @var float
     */
    public $worth;

    /**
     * 物品报关编码，必填
     * @var string
     */
    public $hsCode;

    /**
     * 物品英文材质，三态必填
     * @var string
     */
    public $enMaterial;

    /**
     * 物品中文材质，三态必填
     * @var string
     */
    public $cnMaterial;

    /**
     * 物品重量，非必填
     * @var float
     */
    public $weight;

}