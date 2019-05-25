<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/23 4:01 PM
 * description:
 */

namespace yiier\crossBorderExpress\contracts;

/**
 * 运输方式
 * Class Transport
 * @package yiier\crossBorderExpress\contracts
 */
class Transport
{

    /**
     * 国际通用标准2位简码，必填
     * @var string
     */
    public $countryCode;

    /**
     * 货运方式代号，必填
     * @var string
     */
    public $code;

    /**
     * 中文名称，必填
     * @var string
     */
    public $cnName;

    /**
     * 英文名称，必填
     * @var string
     */
    public $enName;

    /**
     * 是否可以追踪，非必填
     * @var string
     */
    public $ifTracking;

    /**
     * 接口返回数据的 json 格式，非必填
     * @var string
     */
    public $data;

}