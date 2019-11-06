<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/19 10:54 AM
 * description:
 */

namespace yiier\crossBorderExpress\platforms;

use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;


class YuntuPlatform extends Platform
{
    const HOST = 'http://api.yunexpress.com/LMS.API/api';

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
            'Authorization' => ' basic ' . $this->buildToken(),
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
     * @throws \Exception
     */
    public function getTransportsByCountryCode(string $countryCode): array
    {
        $api = '/lms/Get';
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
            $_transport->code = $value['Code'];
            $_transport->cnName = $value['FullName'];
            $_transport->enName = $value['EnglishName'];
            $_transport->ifTracking = $value['HaveTrackingNum'] ? 1 : 0;
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
     * @throws \Exception
     */
    public function createOrder(Order $order): OrderResult
    {
        $orderResult = new OrderResult();
        $api = '/WayBill/BatchAdd';
        $waybill[] = $this->formatOrder($order);
        $body = ['body' => json_encode($waybill)];

        $response = $this->client->post($this->host . $api, $body);
        $result = $this->parseResult($response->getBody());

        if (!empty($result) && $result[0]['Status']) {
            $orderResult->expressAgentNumber = $result[0]['AgentNumber'];
            $orderResult->expressNumber = $result[0]['WayBillNumber'];
            $orderResult->expressTrackingNumber = $result[0]['TrackingNumber'];
        } else {
            throw new ExpressException('订单提交返回失败', (array)$orderResult);
        }
        $orderResult->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderResult;
    }


    /**
     * Get print url
     * @param string $orderNumber
     * @return string
     * @throws \Exception
     */
    public function getPrintUrl(string $orderNumber): string
    {
        $this->host = 'http://api.yunexpress.com/LMS.API.Lable/Api';
        $api = '/PrintUrl';
        $data = [$orderNumber];
        $body = ['body' => json_encode($data)];

        $response = $this->client->post($this->host . $api, $body);

        $result = $this->parseResult($response->getBody());

        try {
            return $result[0]['Url'];
        } catch (\Exception $e) {
            throw new ExpressException('获取打印地址失败', (array)$result);
        }
    }

    /**
     * Get platform order fee
     * @param string $orderNumber
     * @return OrderFee
     * @throws \Exception
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        $api = '/WayBill/GetShippingFeeDetail';
        $query = [
            'query' => ['wayBillNumber' => $orderNumber],
        ];

        $response = $this->client->get($this->host . $api, $query);

        $result = $this->parseResult($response->getBody());

        $orderFee = new OrderFee();
        $orderFee->customerOrderNumber = $result['CustomerOrderNumber'];
        $orderFee->orderNumber = $result['WayBillNumber'];
        $orderFee->chargeWeight = $result['ChargeWeight'];
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
        $shipper = [];
        $goods = [];
        if ($orderClass->shipper) {
            $shipper = [
                'CountryCode' => $orderClass->shipper->countryCode,
                'SenderFirstName' => $orderClass->shipper->name,
                'SenderAddress' => $orderClass->shipper->address,
                'SenderCity' => $orderClass->shipper->city,
                'SenderState' => $orderClass->shipper->state,
                'SenderZip' => $orderClass->shipper->zip,
                'SenderPhone' => $orderClass->shipper->phone,
                'SenderCompany' => $orderClass->shipper->company,
            ];
        }

        $recipient = [
            'CountryCode' => $orderClass->recipient->countryCode,
            'ShippingFirstName' => $orderClass->recipient->name,
            'ShippingState' => $orderClass->recipient->state,
            'ShippingCity' => $orderClass->recipient->city,
            'ShippingAddress' => $orderClass->recipient->address,
            'ShippingZip' => $orderClass->recipient->zip,
            'ShippingPhone' => $orderClass->recipient->phone,
            'ShippingCompany' => $orderClass->recipient->company,
        ];


        $package = [
            'PackageNumber' => $orderClass->package->quantity,
            'Weight' => $orderClass->package->weight,
            'SourceCode' => 'API',
        ];

        foreach ($orderClass->goods as $key => $value) {
            $goods[$key] = [
                'ApplicationName' => $value->description,
                'PickingName' => $value->cnDescription,
                'Qty' => $value->quantity,
                'UnitPrice' => $value->worth,
                'UnitWeight' => $value->weight,
                'HSCode' => $value->hsCode,
                'SKU' => $value->sku,
            ];
        }

        $order = [
            'OrderNumber' => $orderClass->customerOrderNo,
            'ShippingMethodCode' => $orderClass->transportCode,
            'ApplicationInfos' => $goods,
            'InsuranceType' => $orderClass->evaluate ? 1 : 0,
            'InsureAmount' => $orderClass->evaluate,
            'IsReturn' => $orderClass->isReturn,
        ];

        return array_merge($order, ['SenderInfo' => $shipper], ['ShippingInfo' => $recipient], $package);
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
     * @throws \Exception
     */
    protected function parseResult($result)
    {
        $arr = json_decode($result, true);
        if (empty($arr) || !isset($arr['ResultCode'])) {
            throw new \Exception('Invalid response: ' . $result, 400);
        }
        if (!in_array($arr['ResultCode'], ['0000', '5001'])) {
            if (!is_numeric($arr['ResultCode'])) {
                $arr['ResultCode'] = '1001';
            }
            $message = $arr['ResultDesc'];
            if (!empty($arr['Item'][0]['Feedback'])) {
                $message = $arr['Item'][0]['Feedback'];
            }
            throw new ExpressException($message, $arr['ResultCode']);
        }

        return $arr['Item'];
    }
}
