<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/19 10:55 AM
 * description:
 */

namespace yiier\intExpress\contracts;

use GuzzleHttp\Client;
use yiier\intExpress\exceptions\ExpressException;

interface PlatformInterface
{
    /**
     * Get platform name.
     * @return string
     */
    public function getName();


    /**
     * Get platform client.
     * @return string|Client
     */
    public function getClient();


    /**
     * Get platform Transports By Country Code
     * @param string $countryCode
     * @return Transport[]|array
     */
    public function getTransportsByCountryCode(string $countryCode);


    /**
     * Create platform Order
     * @param Order $order
     * @return OrderResult
     * @throws ExpressException
     */
    public function createOrder(Order $order): OrderResult;


    /**
     * Get platform print url
     * @param string $orderNumber
     * @return string
     */
    public function getPrintUrl(string $orderNumber);
}