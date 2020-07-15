<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2020/7/15
 * Time: 00:02
 * File: WanbPlatform.php
 */

namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class WanbPlatform extends Platform
{

    /**
     * default host
     */
    const HOST = 'http://api-sbx.wanbexpress.com';

    /**
     * @var string
     */
    private $host = '';

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        $nounce = hash('sha512', strtoupper($this->makeRandomString()));;
        $headers = [
            'Content-Type' => 'application/json; charset=utf8',
            'Authorization' => sprintf("Hc-OweDeveloper %s;%s;%s",
                $this->config->get("account_no"),
                $this->config->get("token"),
                $nounce
            )
        ];

        var_dump($headers);

        $client = new \GuzzleHttp\Client([
            'headers' => $headers,
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);

        $this->host = $this->config->get("host") ? $this->config->get("host") : self::HOST;

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
        var_dump($this->client->getHeaders());die;
        $body = $this->client->get($this->host . "/api/whoami")->getBody();
        var_dump($body);die;
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
     * @param int $bits
     * @return string
     */
    private function makeRandomString($bits = 256): string
    {
        $bytes = ceil($bits / 8);
        $return = '';
        for ($i = 0; $i < $bytes; $i++) {
            $return .= chr(mt_Rand(0, 255));
        }
        return $return;
    }
}
