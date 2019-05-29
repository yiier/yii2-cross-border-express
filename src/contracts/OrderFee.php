<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/28 2:37 PM
 * description:
 */

namespace yiier\crossBorderExpress\contracts;

class OrderFee
{
    /**
     * 计费重量
     * @var float
     */
    public $chargeWeight;

    /**
     * 运费
     * @var float
     */
    public $freight;

    /**
     * 挂号费
     * @var float
     */
    public $registrationFee;

    /**
     * 处理费
     * @var float
     */
    public $processingFee;

    /**
     * 燃油费
     * @var float
     */
    public $fuelCosts;

    /**
     * 总费用
     * @var float
     */
    public $totalFee;

    /**
     * 其他费用
     * @var float
     */
    public $otherFee;

    /**
     * 客户订单号
     * @var string
     */
    public $customerOrderNumber;

    /**
     * 接口返回数据的 json 格式，非必填
     * @var string
     */
    public $data;
}
