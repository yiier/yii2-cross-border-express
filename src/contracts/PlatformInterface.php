<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/19 10:55 AM
 * description:
 */

namespace yiier\crossBorderExpress\contracts;

use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\exceptions\ExpressException;

interface PlatformInterface
{
    /**
     * Get platform name.
     * @return string
     */
    public function getName();


    /**
     * Get platform client.
     * @return nusoap_client|Client
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
     * @param array $params
     * @return string
     * @throws ExpressException
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string;

    /**
     * Get platform order fee
     * @param string $orderNumber
     * @return OrderFee
     * @throws ExpressException
     */
    public function getOrderFee(string $orderNumber): OrderFee;


    /**
     * Get platform all order fee
     * @param array $query
     * @return OrderFee[]
     */
    public function getOrderAllFee(array $query = []): array;
}
