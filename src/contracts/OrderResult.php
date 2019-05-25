<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/25 8:16 AM
 * description:
 */

namespace yiier\crossBorderExpress\contracts;


class OrderResult
{
    /**
     * 物流运单号/转单号
     * @var string
     */
    public $expressNumber;

    /**
     * 物流跟踪号
     * @var string
     */
    public $expressTrackingNumber;

    /**
     * 物流商单号/代理单号
     * @var string
     */
    public $expressAgentNumber;

    /**
     * 接口返回数据的 json 格式，非必填
     * @var string
     */
    public $data;
}