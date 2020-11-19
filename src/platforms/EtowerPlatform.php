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


use Exception;
use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

/**
 * TODO: 还有问题暂不可用
 *
 * Class EtowerPlatform
 * @package yiier\crossBorderExpress\platforms
 */
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
     * @return Client|nusoap_client
     */
    public function getClient()
    {
        $headers = [
            'Content-Type' => 'application/json; charset=utf8',
        ];

        $client = new \GuzzleHttp\Client([
            'headers' => $headers,
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);

        $this->host = $this->config->get('host') ?: self::HOST;

        return $client;
    }

    /**
     * @param string $countryCode
     * @return array|void|Transport[]
     */
    public function getTransportsByCountryCode(string $countryCode)
    {
        // TODO: Implement getTransportsByCountryCode() method.
    }

    /**
     * create order
     *
     * @param Order $order
     * @return OrderResult
     * @throws ExpressException
     * @throws Exception
     */
    public function createOrder(Order $order): OrderResult
    {
        $uri = "/services/shipper/orders";

        $waybill[] = $this->formatOrder($order);
        $headers = $this->buildClientHeader("POST", $uri);

        $body = [
            'body' => json_encode($waybill, true),
            "headers" => $headers,
        ];

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
     * @param string $orderNumber
     * @return string
     * @throws ExpressException
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string
    {
        $uri = "/services/shipper/labels";

        try {
            $headers = $this->buildClientHeader("POST", $uri);
        } catch (Exception $e) {
            throw new ExpressException($e->getMessage());
        }

        $data = [
            "orderIds" => [$orderNumber],
            "labelType" => 1,
            "packinglist" => false,
            "merged" => false,
            "labelFormat" => "JPG"
        ];

        $body = [
            'body' => json_encode($data, true),
            "headers" => $headers,
        ];

        $response = $this->client->post($this->host . $uri, $body);
        $result = $this->parseResult($response->getBody());

        try {
            // TODO: 返回图片的base64值
            return $result[0]['labelContent'];
        } catch (\Exception $e) {
            throw new ExpressException('获取打印地址失败', (array)$result);
        }
    }

    /**
     * @param string $orderNumber
     * @return OrderFee
     * @throws ExpressException
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        $uri = "/services/shipper/queryorders";
        try {
            $headers = $this->buildClientHeader("POST", $uri);
        } catch (Exception $e) {
            throw new ExpressException($e->getMessage());
        }

        $data = [
            $orderNumber
        ];

        $body = [
            'body' => json_encode($data, true),
            "headers" => $headers,
        ];


        $response = $this->client->post($this->host . $uri, $body);
        $res = $this->parseResult($response->getBody());

        $orderFee = new OrderFee();

        if (empty($res) || empty($res[0]) || empty($res[0]["data"])) {
            return $orderFee;
        }

        $result = $res[0]["data"]["order"];

        var_dump($res);

        $orderFee->customerOrderNumber = $result["trackingNo"];
        $orderFee->orderNumber = $result['referenceNo'];
//        $orderFee->chargeWeight = $result['weight'];
//        $orderFee->freight = $result['Freight'];
//        $orderFee->fuelCosts = $result['FuelSurcharge'];
//        $orderFee->registrationFee = $result['RegistrationFee'];
//        $orderFee->processingFee = $result['ProcessingFee'];
        $orderFee->otherFee = $result['invoiceValue'];
        $orderFee->totalFee = $result['TotalFee'];
        $orderFee->country = $result['country'];
//        $orderFee->transportName = $result['ShippingMethodName'];
//        $orderFee->datetime = $result['OccurrenceTime'];
        $orderFee->data = json_encode($result, JSON_UNESCAPED_UNICODE);
        return $orderFee;
    }


    /**
     * @param array $query
     * @return array
     * @throws ExpressException
     */
    public function getOrderAllFee(array $query = []): array
    {
        $uri = "/services/shipper/queryorders";
        try {
            $headers = $this->buildClientHeader("POST", $uri);
        } catch (Exception $e) {
            throw new ExpressException($e->getMessage());
        }

        $body = [
            'body' => json_encode($query, true),
            "headers" => $headers,
        ];

        $response = $this->client->post($this->host . $uri, $body);
        $res = $this->parseResult($response->getBody());

        // TODO: 没有

        return [];
    }


    /**
     * build client header
     *
     * @param string $method
     * @param $path
     * @return array
     * @throws Exception
     */
    private function buildClientHeader(string $method, $path): array
    {
        $t = new \DateTime('now');
        $t = $t->setTimezone(new \DateTimeZone("GMT+8"));

        $wallTechDate = $t->format(\DateTime::RFC1123);
        $auth = $method . "\n" . $wallTechDate . "\n" . $this->config->get("host") . $path;
        $hash = base64_encode(hash_hmac('sha1', $auth, $this->config->get("key"), true));
        return [
            'Content-Type' => 'application/json; charset=utf8',
            "X-WallTech-Date" => $wallTechDate,
            'Authorization' => sprintf("WallTech %s:%s",
                $this->config->get("token"),
                $hash
            )
        ];
    }


    /**
     * Parse result
     *
     * @param $result
     * @return array
     * @throws ExpressException
     * @throws Exception
     */
    protected function parseResult(string $result): array
    {
        $arr = json_decode($result, true);
        if (empty($arr) || !isset($arr['status'])) {
            throw new Exception('Invalid response: ' . $result, 400);
        }
        if (strtoupper($arr["status"]) != self::SUCCESS) {
            $message = $arr['errors'];
            if (!empty($arr['data']) && !empty($arr['data'][0]['errors'])) {
                $message = $arr['data'][0]['errors'];
            }
            throw new ExpressException($message, $arr['errors']);
        }

        return $arr['data'];
    }

    /**
     * 格式化etower所需要的数据
     *
     * @param Order $orderClass
     * @return array
     */
    protected function formatOrder(Order $orderClass)
    {
        $goods = [];
        foreach ($orderClass->goods as $key => $value) {
            $goods[$key] = [
                "itemNo" => "00001",
                "sku" => $value->sku,
                "description" => $value->description,
                "nativeDescription" => $value->cnDescription,
                "hsCode" => $value->hsCode,
                "originCountry" => "", // CHINA
                "unitValue" => $value->worth,
                "itemCount" => $value->quantity,
                "weight" => $value->weight,
                "productURL" => ""
            ];
        }

        return [
            'trackingNo' => $orderClass->transportCode,
            'consignmentId' => '',
            'referenceNo' => $orderClass->customerOrderNo,
            'addressLine1' => $orderClass->recipient->address,
            'addressLine2' => '',
            'addressLine3' => '',
            'city' => $orderClass->recipient->city,
            'country' => $orderClass->recipient->countryCode,
            'description' => $orderClass->package->description,
            'nativeDescription' => $orderClass->package->description,
            'email' => $orderClass->recipient->email,
            'facility' => "",
            'instruction' => "",
            'invoiceCurrency' => '',
            'batteryType' => $orderClass->withBattery,
            'invoiceValue' => 100,
            'phone' => $orderClass->recipient->phone,
            'platform' => "",
            'postcode' => $orderClass->recipient->zip,
            'recipientCompany' => $orderClass->recipient->company,
            'recipientName' => $orderClass->recipient->name,
            'serviceCode' => '',
            'serviceOption' => '',
            "sku" => "",
            "state" => "",
            "weightUnit" => "KG",
            "weight" => $orderClass->package->weight,
            "dimensionUnit" => "",
            "length" => $orderClass->package->length,
            "width" => $orderClass->package->width,
            "height" => $orderClass->package->height,
            "volume" => "",
            "shipperName" => $orderClass->shipper ? $orderClass->shipper->name : "",
            "shipperAddressLine1" => $orderClass->shipper ? $orderClass->shipper->address : "",
            "shipperAddressLine2" => "",
            "shipperAddressLine3" => "",
            "shipperCity" => $orderClass->shipper ? $orderClass->shipper->city : "",
            "shipperState" => $orderClass->shipper ? $orderClass->shipper->state : "",
            "shipperPostcode" => $orderClass->shipper ? $orderClass->shipper->zip : "",
            "shipperCountry" => $orderClass->shipper ? $orderClass->shipper->countryCode : "",
            "shipperPhone" => $orderClass->shipper ? $orderClass->shipper->phone : "",
            "recipientTaxId" => $orderClass->taxesNumber,
            "authorityToLeave" => "",
            "incoterm" => "",
            "lockerService" => "",
            "extendData" => [
                "nationalNumber" => "",
                "nationalIssueDate" => "11/11/2017",
                "cyrillicName" => "Bob",
                "imei" => "",
                "isImei" => true,
                "vendorid" => "64652016681",
                "gstexemptioncode" => "",
                "abnnumber" => "",
                "sortCode" => "",
                "coveramount" => 12
            ],
            "orderItems" => $goods
        ];
    }
}
