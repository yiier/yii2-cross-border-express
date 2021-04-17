<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2021/4/16
 * Time: 下午9:32
 * File: Yw56Platform.php
 */

namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use SimpleXMLElement;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class Yw56Platform extends Platform
{
    /**
     * @var int 客户号
     */
    private $userId = 100000;

    /**
     * @var string API TOKEN
     */
    private $token = "D6140AA383FD8515B09028C586493DDB";

    /**
     * @var string 端点
     */
    private $endpoint = "http://online.yw56.com.cn/service";

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        $headers = [
            'Content-Type' => 'text/xml; charset=utf8',
            'Authorization' => 'Basic ' . $this->token,
            'Accept' => 'application/xml',
        ];

        $client = new \GuzzleHttp\Client([
            'headers' => $headers,
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);

        $this->endpoint = $this->config->get('host') ?: $this->endpoint;
        $this->token = $this->config->get('token') ?: $this->token;
        $this->userId = $this->config->get('userId') ?: $this->userId;

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
        $date = new \DateTime();
        $data = [
//            "Epcode" => $order->customerOrderNo,
            "Userid" => $this->userId,
            "Channel" => $order->transportCode,
            "UserOrderNumber" => $order->customerOrderNo,
            "SendDate" => $date->format(DATE_ISO8601),
            "MRP" => $order->package->declareWorth,
            "Receiver" => [
                "Userid" => $this->userId,
                "Name" => $order->recipient->name,
                "Phone" => $order->recipient->phone,
                "Email" => $order->recipient->email,
                "Company" => $order->recipient->company,
                "Country" => $order->recipient->countryCode,
                "Postcode" => $order->recipient->zip,
                "State" => $order->recipient->state,
                "City" => $order->recipient->city,
                "Address1" => $order->recipient->address,
                "Address2" => $order->recipient->doorplate,
            ],
            "Sender" => [
                "TaxNumber" => $order->taxesNumber
            ],
            "Memo" => $order->package->description,
            "Quantity" => $order->package->quantity,
            "GoodsName" => [
                "Userid" => $this->userId,
                "NameCh" => $order->goods[0]->cnDescription,
                "NameEn" => $order->goods[0]->description,
                "Weight" => $order->goods[0]->weight,
                "DeclaredValue" => $order->goods[0]->worth,
                "DeclaredCurrency" => "USD",
                "HsCode" => $order->goods[0]->hsCode
            ]
        ];
        $xml = new SimpleXMLElement('<ExpressType/>');
        $this->arrayToXml($data, $xml);
        $response = $this->getClient()->post(sprintf("%s/service/users/%d/expresses", $this->endpoint, $this->userId), [
            "body" => $xml->asXML()
        ]);
        print_r($response->getHeaders());
        $result = $response->getBody();
        print_r($result);die;
        return $this->parseResult();
    }

    /**
     * @inheritDoc
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string
    {
        // {SERVICEENDPOINT}/USERS/{USERID}/EXPRESSES/{EPCODE}/{LABELSIZE}LABEL
        $response = $this->getClient()->get(sprintf("%s/service/users/%d/expresses/%s/%sLABEL", $this->endpoint, $this->userId, $orderNumber, "A4L"));
        print_r($response->getHeaders());
        $result = $response->getBody();
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
     * @param string $result
     * @return OrderResult
     * @throws ExpressException
     */
    protected function parseResult(string $result): OrderResult
    {
        $orderResult = new OrderResult();
//        $orderResult->data = $result;
//        $orderResult->expressNumber = !empty($resData["ProcessCode"]) ? $resData["ProcessCode"] : "";
//        $orderResult->expressTrackingNumber = !empty($resData["TrackingNumber"]) ? $resData["TrackingNumber"] : $this->getTracingNumber($resData["ProcessCode"]);
        return $orderResult;
    }

    /**
     * Convert an array to XML
     * @param array $array
     * @param SimpleXMLElement $xml
     */
    private function arrayToXml($array, &$xml)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if (is_int($key)) {
                    $key = "e";
                }
                $label = $xml->addChild($key);
                $this->arrayToXml($value, $label);
            } else {
                $xml->addChild($key, $value);
            }
        }
    }
}
