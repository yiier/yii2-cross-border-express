<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/23 6:42 PM
 * description:
 */

namespace yiier\intExpress\contracts;


/**
 * 发件人
 * Class Shipper
 * @package yiier\express\contracts
 */
class Shipper
{
    /**
     * 发件人所在国家，填写国际通用标准2位简码，可通过国家查询服务查询，非必填
     * @var string
     */
    public $countryCode;

    /**
     * 发件人姓名，必填
     * @var string
     */
    public $name;

    /**
     * 发件人公司名称，非必填
     * @var string
     */
    public $company;

    /**
     * 发件人详情地址，非必填
     * @var string
     */
    public $address;

    /**
     * 发件人所在城市，非必填
     * @var string
     */
    public $city;

    /**
     * 发件人省/州，非必填
     * @var string
     */
    public $state;

    /**
     * 发件人邮编，非必填
     * @var string
     */
    public $zip;

    /**
     * 发件人电话，非必填
     * @var string
     */
    public $phone;

    /**
     * 发件人邮箱，非必填
     * @var string
     */
    public $email;
}