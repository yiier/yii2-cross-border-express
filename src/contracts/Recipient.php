<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/23 6:42 PM
 * description:
 */

namespace yiier\intExpress\contracts;


/**
 * 收件人
 * Class Shipper
 * @package yiier\express\contracts
 */
class Recipient
{
    /**
     * 收件人所在国家，填写国际通用标准2位简码，可通过国家查询服务查询，必填
     * @var string
     */
    public $countryCode;

    /**
     * 收件人公司名称，非必填
     * @var string
     */
    public $company;

    /**
     * 收件人姓名，必填
     * @var string
     */
    public $name;

    /**
     * 收件人详情地址，必填
     * @var string
     */
    public $address;

    /**
     * 收件人所在城市，必填
     * @var string
     */
    public $city;

    /**
     * 收件人省/州，必填
     * @var string
     */
    public $state;

    /**
     * 收件人邮编，必填
     * @var string
     */
    public $zip;

    /**
     * 收件人电话，必填
     * @var string
     */
    public $phone;

    /**
     * 收件人邮箱，非必填
     * @var string
     */
    public $email;
}