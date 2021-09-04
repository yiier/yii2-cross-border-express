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
use GuzzleHttp\Exception\ClientException;
use SimpleXMLElement;
use yiier\AliyunOSS\OSS;
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
    private $endpoint = "http://online.yw56.com.cn";

    /**
     * @return Client|\nusoap_client
     */
    public function getClient()
    {
        $this->endpoint = $this->config->get('host') ?: $this->endpoint;
        $this->token = $this->config->get('token') ?: $this->token;
        $this->userId = $this->config->get('userId') ?: $this->userId;

        $headers = [
            'Content-Type' => 'text/xml',
            'Authorization' => 'Basic ' . $this->token,
            'Accept' => 'application/xml',
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
     * @param Order $order
     * @return OrderResult
     * @throws ExpressException
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
                "NationalId" => $order->taxesNumber
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
                "Weight" => $order->goods[0]->weight * 1000,
                "DeclaredValue" => $order->goods[0]->worth,
                "DeclaredCurrency" => "USD",
                "HsCode" => $order->goods[0]->hsCode
            ]
        ];
        $xml = new SimpleXMLElement('<ExpressType/>');
        $this->arrayToXml($data, $xml);

        $response = $this->client->post(sprintf("%s/service/users/%d/expresses", $this->endpoint, $this->userId), [
            "body" => $xml->asXML()
        ]);

        return $this->parseResult($response->getBody());
    }

    /**
     * @param string $orderNumber
     * @param array $params
     * @return string
     * @throws ExpressException
     * @throws \OSS\Core\OssException
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string
    {
        $cli = new \GuzzleHttp\Client([
            'headers' => ["Authorization" => "Basic " . $this->token],
        ]);

        try {
            $fileName = sprintf("%s.pdf", $orderNumber);
            $filePath = "/tmp/" . $fileName;

            $cli->get(sprintf("%s/service/users/%d/expresses/%s/%sLABEL",
                $this->endpoint, $this->userId, $orderNumber, "A6L"), [
                "save_to" => $filePath
            ]);

            return $this->getPrintFile($fileName, $filePath);
        } catch (ClientException $exception) {
            if ($exception->hasResponse() && !empty($exception->getResponse()->getBody())) {
                if ($msg = $this->parseResponseError($exception->getResponse()->getBody())) {
                    throw new ExpressException($msg);
                }
            }
            throw new ExpressException($exception->getMessage());
        }
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
        $xml = new SimpleXMLElement($result);
        if ($xml->CallSuccess == "false") {
            throw new ExpressException(sprintf("%s:%s", $xml->Response->ReasonMessage, $xml->Response->Reason));
        }
        $orderResult = new OrderResult();
        $orderResult->data = json_decode($xml, TRUE);

        $orderResult->expressNumber = !empty($xml->CreatedExpress->Epcode) ? $xml->CreatedExpress->Epcode : "";
//        $orderResult->expressTrackingNumber = !empty($xml->CreatedExpress->YanwenNumber) ? $xml->CreatedExpress->YanwenNumber : "";
        return $orderResult;
    }

    /**
     * @param string $result
     * @return string
     */
    private function parseResponseError(string $result): string
    {
        $xml = new SimpleXMLElement($result);
        if (!empty($xml->ReasonMessage)) {
            return $xml->ReasonMessage;
        }
        return "";
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

    /**
     * @param string $fileName
     * @param string $filePath
     * @return string
     * @throws \OSS\Core\OssException
     */
    protected function getPrintFile(string $fileName, string $filePath): string
    {
        // PDF传到阿里云oss
        $oss = new OSS([
            "accessKeyId" => $this->config->get("ossAccessKeyId"),
            "bucket" => $this->config->get("ossBucket"),
            "accessKeySecret" => $this->config->get("ossAccessKeySecret"),
            "lanDomain" => $this->config->get("ossLanDomain"),
            "wanDomain" => $this->config->get("ossWanDomain"),
            "isInternal" => false,
        ]);

        $storagePath = 'storage/express/yw56/';
        if ($oss->has($storagePath . $fileName)) {
            return sprintf("http://%s.%s/%s", $this->config->get("oss_bucket"), $this->config->get("oss_yw_domain"), $storagePath . $fileName);
        }

        if (!$oss->has($storagePath)) {
            $oss->createDir($storagePath);
        }

        if ($res = $oss->upload($storagePath . $fileName, $filePath)) {
            unlink($filePath);
            return sprintf("http://%s/%s", $res["oss-requestheaders"]["Host"], $storagePath . $fileName);
        }
        unlink($filePath);
        return "";
    }
}
