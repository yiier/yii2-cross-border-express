<?php


namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class FopPlatform extends Platform
{

    /**
     * @var string
     */
    private $host = "http://open.sandbox.4px.com";

    /**
     * @var string
     */
    private $appKey = "";

    /**
     * @var string
     */
    private $appSecret = "";

    /**
     * @var string
     */
    private $accessToken = "";

    /**
     * @return Client|nusoap_client
     */
    public function getClient()
    {
        $this->host = $this->config->get('host') ?: $this->host;
        $this->appKey = $this->config->get('appKey') ?: $this->appKey;
        $this->appSecret = $this->config->get('appSecret') ?: $this->appSecret;
        $this->accessToken = $this->config->get('accessToken') ?: $this->accessToken;

        $headers = [
            'Content-Type' => 'application/json',
        ];

        return new \GuzzleHttp\Client([
            'headers' => $headers,
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);
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
        $this->client->post($this->host."/");
    }

    /**
     * @inheritDoc
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string
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
}
