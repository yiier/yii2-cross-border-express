<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2020/4/3
 * Time: 22:05
 * File: Etower.php
 */

namespace yiier\crossBorderExpress\platforms;


use Cassandra\Date;
use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\Config;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class EtowerPlatform extends Platform
{

    /**
     * default host
     */
    const HOST = 'https://cn.etowertech.com';

    const SUCCESS = "SUCCESS";

    /**
     * @var string
     */
    private $host;

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        $t = new \DateTime('now');

        $str = "";
        $key = base64_encode(hash_hmac("sha1", $str, $this->config->get("key"), true));

        $headers = [
            'Content-Type' => 'application/json; charset=utf8',
            'Accept-Language' => 'en-us',
            'X-WallTech-Date' => $t->format(\DateTime::RFC1123),
            'Authorization' => sprintf("WallTech %s:%s",
                $this->config->get("token"),
                $key
            ),
            'Accept' => 'text/json',
        ];

        $client = new \GuzzleHttp\Client([
            'headers' => $headers,
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);

        $this->host = $this->config->get('host') ?: self::HOST;

        return $client;
    }

    /**
     * @inheritDoc
     */
    public function getTransportsByCountryCode(string $countryCode)
    {
        // TODO: Implement getTransportsByCountryCode() method.
    }

    /**
     * @inheritDoc
     */
    public function createOrder(Order $order): OrderResult
    {
        $uri = "/services/shipper/orders";

        $body = [];

        $response = $this->client->post($this->host . $uri, $body);
        $result = $this->parseResult($response->getBody());
        $orderResult = new OrderResult();
        if (!empty($result) && strtoupper($result[0]["status"]) == self::SUCCESS) {

            $orderResult->expressAgentNumber = $result[0]['referenceNo'];
            $orderResult->expressNumber = $result[0]['orderId'];
            $orderResult->expressTrackingNumber = $result[0]['trackingNo'];
        } else {
            throw new ExpressException('订单提交返回失败', (array)$result);
        }
        $orderResult->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderResult;
    }

    /**
     * @inheritDoc
     */
    public function getPrintUrl(string $orderNumber): string
    {
        // TODO: Implement getPrintUrl() method.
    }

    /**
     * @inheritDoc
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        // TODO: Implement getOrderFee() method.
    }

    /**
     * @inheritDoc
     */
    public function getOrderAllFee(array $query = []): array
    {
        // TODO: Implement getOrderAllFee() method.
    }

    /**
     * Parse result
     *
     * @param string $result
     * @return array
     * @throws \Exception
     */
    protected function parseResult($result)
    {
        $arr = json_decode($result, true);
        if (empty($arr) || !isset($arr['status'])) {
            throw new \Exception('Invalid response: ' . $result, 400);
        }
        if (strtoupper($arr["status"]) != self::SUCCESS) {
            $message = $arr['errors'];
            if (!empty($arr['data'][0]['errors'])) {
                $message = $arr['data'][0]['errors'];
            }
            throw new ExpressException($message, $arr['errors']);
        }

        return $arr['data'];
    }
}
