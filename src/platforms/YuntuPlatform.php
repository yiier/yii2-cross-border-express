<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/19 10:54 AM
 * description:
 */

namespace yiier\crossBorderExpress\platforms;

use Exception;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;


class YuntuPlatform extends Platform
{
    const HOST = 'http://oms.api.yunexpress.com';

    /**
     * @var string
     */
    private $host;

    /**
     * @return \GuzzleHttp\Client
     */
    public function getClient()
    {
        $headers = [
            'Content-Type' => 'application/json; charset=utf8',
            'Authorization' => 'Basic ' . $this->buildToken(),
            'Accept-Language' => 'en-us',
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
     * @param string $countryCode
     * @return Transport[]|array
     * @throws Exception
     */
    public function getTransportsByCountryCode(string $countryCode): array
    {
        $api = '/api/Common/GetCountry';
        $query = [];
        if (!empty($countryCode)) {
            $query = [
                'query' => ['countryCode' => $countryCode],
            ];
        }
        $response = $this->client->get($this->host . $api, $query);
        $result = $this->parseResult($response->getBody());

        $transport = new Transport();
        $transports = [];
        foreach ($result as $value) {
            $_transport = clone $transport;
            $_transport->countryCode = $countryCode;
            $_transport->code = $value['CountryCode'];
            $_transport->cnName = $value['CName'];
            $_transport->enName = $value['EName'];
            $_transport->data = json_encode($value, JSON_UNESCAPED_UNICODE);
            $transports[] = $_transport;
        }
        return $transports;
    }

    /**
     * Create platform Order
     * @param Order $order
     * @return OrderResult
     * @throws ExpressException
     * @throws Exception
     */
    public function createOrder(Order $order): OrderResult
    {
        $orderResult = new OrderResult();
        $api = '/api/WayBill/CreateOrder';
        $waybill = $this->formatOrder($order);
        $body = ['body' => json_encode([$waybill])];

        $response = $this->client->post($this->host . $api, $body);
        $result = $this->parseResult($response->getBody());

        if (!empty($result) && $result[0]['Success'] === 1) {
            $orderResult->expressAgentNumber = $result[0]['AgentNumber'];
            $orderResult->expressNumber = $result[0]['WayBillNumber'];
            $orderResult->expressTrackingNumber = $result[0]['TrackingNumber'];
        } else {
            throw new ExpressException('订单提交返回失败', (array)$result);
        }
        $orderResult->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderResult;
    }


    /**
     * Get print url
     * @param string $orderNumber
     * @param array $params
     * @return string
     * @throws ExpressException
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string
    {
        $url = $this->host . "/api/Label/Print";
        $data = [$orderNumber];
        $body = ['body' => json_encode($data)];

        $response = $this->client->post($url, $body);

        $result = $this->parseResult($response->getBody());

        try {
            return $result[0]['Url'];
        } catch (Exception $e) {
            throw new ExpressException('获取打印地址失败', (array)$result);
        }
    }

    /**
     * Get platform order fee
     * @param string $orderNumber
     * @return OrderFee
     * @throws Exception
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        $api = '/api/Freight/GetShippingFeeDetail';
        $query = [
            'query' => ['wayBillNumber' => $orderNumber],
        ];

        $response = $this->client->get($this->host . $api, $query);

        $result = $this->parseResult($response->getBody());

        $orderFee = new OrderFee();
        $orderFee->customerOrderNumber = $result['CustomerOrderNumber'];
        $orderFee->orderNumber = $result['WayBillNumber'];
        $orderFee->chargeWeight = $result['GrossWeight'];
        $orderFee->freight = $result['Freight'];
        $orderFee->fuelCosts = $result['FuelSurcharge'];
        $orderFee->registrationFee = $result['RegistrationFee'];
        $orderFee->processingFee = $result['ProcessingFee'];
        $orderFee->otherFee = $result['OtherFee'];
        $orderFee->totalFee = $result['TotalFee'];
        $orderFee->country = $result['CountryName'];
        $orderFee->transportName = $result['ShippingMethodName'];
        $orderFee->datetime = $result['OccurrenceTime'];
        $orderFee->data = json_encode($result, JSON_UNESCAPED_UNICODE);
        return $orderFee;
    }

    /**
     * Get platform all order fee
     * 云途暂未提供此方法
     * @param array $query
     * @return OrderFee[]
     */
    public function getOrderAllFee(array $query = []): array
    {
        return [];
    }

    /**
     * @param Order $orderClass
     * @return array
     */
    protected function formatOrder(Order $orderClass)
    {
        $goods = [];

        foreach ($orderClass->goods as $key => $value) {
            $goods[$key] = [
                'EName' => $value->description,
                'CName' => $value->cnDescription,
                'Quantity' => $value->quantity,
                'UnitPrice' => $value->worth,
                'UnitWeight' => $value->weight,
                'HSCode' => $value->hsCode,
                'SKU' => $value->sku,
                'CurrencyCode' => "USD"
            ];
        }


        return [
            'CustomerOrderNumber' => $orderClass->customerOrderNo,
            'ShippingMethodCode' => $orderClass->transportCode,
            'PackageCount' => $orderClass->package->quantity,
            'Weight' => $orderClass->package->weight,
            'Receiver' => [
                'CountryCode' => $orderClass->recipient->countryCode,
                'FirstName' => $orderClass->recipient->name,
//                'LastName' => $orderClass->recipient->name,
                'Company' => $orderClass->recipient->company,
                'Street' => $orderClass->recipient->address,
                'City' => $orderClass->recipient->city,
                'State' => $orderClass->recipient->state,
                'Zip' => $orderClass->recipient->zip,
                'Phone' => $orderClass->recipient->phone,
                'HouseNumber' => $orderClass->recipient->doorplate,
                'Email' => $orderClass->recipient->email,
            ],
            'Sender' => [
                'CountryCode' => $orderClass->shipper->countryCode,
                'FirstName' => $orderClass->shipper->name,
//                'LastName' => $orderClass->shipper->name,
                'Company' => $orderClass->shipper->company,
                'Street' => $orderClass->shipper->address,
                'City' => $orderClass->shipper->city,
                'State' => $orderClass->shipper->state,
                'Zip' => $orderClass->shipper->zip,
                'Phone' => $orderClass->shipper->phone,
            ],
            'ReturnOption' => $orderClass->isReturn ? 1 : 0,
            'InsuranceOption' => $orderClass->evaluate ? 1 : 0,
            'Coverage' => $orderClass->evaluate,
            'Parcels' => $goods,
        ];
    }


    /**
     * @return string
     */
    protected function buildToken()
    {
        return base64_encode($this->config->get('account') . '&' . $this->config->get('secret'));
    }


    /**
     * Parse result
     *
     * @param string $result
     * @return array
     * @throws Exception
     */
    protected function parseResult($result): array
    {
        $arr = json_decode($result, true);
        if (empty($arr) || !isset($arr['Code'])) {
            throw new Exception('Invalid response: ' . $result, 400);
        }
        if (!in_array($arr['Code'], ['0000', '5001'])) {
            if (!is_numeric($arr['Code'])) {
                $arr['Code'] = '1001';
            }
            $message = sprintf("%s;%s;%s", $arr['Message'], $arr["RequestId"], $arr["TimeStamp"]);
            if (!empty($arr['Item'][0]['Remark'])) {
                $message = $arr['Item'][0]['Remark'];
            }
            throw new ExpressException($message, $arr['Code']);
        }

        return $arr['Item'];
    }
}
