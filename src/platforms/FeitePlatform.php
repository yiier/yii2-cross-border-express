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

class FeitePlatform extends Platform
{
    const HOST = 'http://exorderwebapi.flytcloud.com';

    const CACHE_KEY_FEITE_ACCESS_TOKEN = 'yiier.crossBorderExpress.feite.token';

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
        ];

        $client = new \GuzzleHttp\Client([
            'headers' => $headers,
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);

        $this->host = $this->config->get('host') ?: self::HOST;

        return $client;
    }

    /**
     *  不能根据国家获取渠道
     * @param string $countryCode
     * @return Transport[]|array
     * @throws \Exception
     */
    public function getTransportsByCountryCode(string $countryCode): array
    {
        $api = '/BaseInfo/GetPostTypes';
        $response = $this->client->get($this->host . $api);
        $result = $response->getBody();
        $data = json_decode($result, true);
        $transport = new Transport();
        $transports = [];
        foreach ($data as $value) {
            $_transport = clone $transport;
            $_transport->code = $value['code'];
            $_transport->cnName = $value['posttypeName'];
            $_transport->enName = '';
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
        $api = '/api/OrderSyn/ErpUploadOrder';
        // 只支持上传一个订单
        $bodyData = $this->getBaseParams() + ['OrderList' => [$this->formatOrder($order)]];
        $body = ['body' => json_encode($bodyData)];
        $response = $this->client->post($this->host . $api, $body);
        $result = $this->parseResult($response->getBody(), 'ErpSuccessOrders');

        if (!empty($result)) {
            $orderResult->expressAgentNumber = '';
            $orderResult->expressNumber = $result[0]['OrderId'];
            $orderResult->expressTrackingNumber = $result[0]['TraceId'];
        } else {
            throw new ExpressException('订单提交返回失败', (array)$result);
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
        $api = 'http://exapi.flytcloud.com/api/label/LabelProvider/GetLabelBatchExt';
        $data = [
            'OrderIdlst' => [$orderNumber]
        ];
        $body = [
            'headers' => [
                'token' => $this->getAccessToken()
            ],
            'body' => json_encode($data)
        ];

        $response = $this->client->post($api, $body);

        $result = json_decode($response->getBody(), true);

        try {
            $b64 = $result['Data']['Label'];

            $bin = base64_decode($b64, true);
            header('Content-Type: application/pdf');
            exit($bin);
        } catch (\Exception $e) {
            throw new ExpressException('获取打印地址数据失败', (array)$result);
        }
    }

    /**
     * Get platform order fee
     * @param string $orderNumber 订单跟踪号
     * @return OrderFee
     * @throws \Exception
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        $api = '/api/OrderSyn/ErpGetGoodsReceivingInformation';
        $body = ['body' => json_encode($this->getBaseParams() + ['TrackingNumber' => $orderNumber])];

        $response = $this->client->post($this->host . $api, $body);

        $result = $this->parseResult($response->getBody());

        $orderFee = new OrderFee();
        $orderFee->customerOrderNumber = '';
        $orderFee->orderNumber = '';
        $orderFee->chargeWeight = $result['Weight'];
        $orderFee->freight = $result['Freight'];
        $orderFee->fuelCosts = 0;
        $orderFee->registrationFee = 0;
        $orderFee->processingFee = 0;
        $orderFee->otherFee = 0;
        $orderFee->totalFee = $result['Freight'];
        $orderFee->country = '';
        $orderFee->transportName = '';
        $orderFee->datetime = date('c');
        $orderFee->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderFee;
    }

    /**
     * Get platform all order fee
     * 暂未提供此方法
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
        $orderDetailList = [];
        $haikwanDetail = [];
        foreach ($orderClass->goods as $key => $value) {
            $orderDetailList[$key] = [
                'ItemName' => $value->description,
                'Quantities' => $value->quantity,
                'Price' => $value->worth,
                'SKU' => $value->sku,
            ];

            $haikwanDetail[$key] = [
                'HwCode' => $value->hsCode,
                'ItemCnName' => $value->cnDescription,
                'ItemEnName' => $value->description,
                'Quantities' => $value->quantity,
                'UnitPrice' => $value->worth,
                'Weight' => $value->weight,
                'ProducingArea' => 'CN',
                'CCode' => 'USD',
                'BtId' => $orderClass->withBattery ?: null,
            ];

        }

        $order = [
            'ApiOrderId' => $orderClass->customerOrderNo,
            'Address1' => $orderClass->recipient->address,
            'City' => $orderClass->recipient->city,
            'CiId' => $orderClass->recipient->countryCode,
            'County' => $orderClass->recipient->state,
            'Email' => $orderClass->recipient->email,
            'Phone' => $orderClass->recipient->phone,
            'ReceiverName' => $orderClass->recipient->name,
            'Zip' => $orderClass->recipient->zip,
            'PtId' => $orderClass->transportCode,
            'SalesPlatformFlag' => 0,
            'PackType' => 3,
            'Remark' => $orderClass->customerOrderNo,
            'SyncPlatformFlag' => $this->config->get('syncPlatformId')
        ];

        return array_merge($order, ['OrderDetailList' => $orderDetailList], ['HaikwanDetialList' => $haikwanDetail]);
    }


    protected function getBaseParams()
    {
        return [
            'Token' => $this->config->get('token'),
            'UAccount' => $this->config->get('accountId'),
            'Password' => strtoupper(md5($this->config->get('password'))),
        ];
    }

    /**
     * @return mixed
     * @throws ExpressException
     */
    protected function getAccessToken()
    {
        $cache = \Yii::$app->cache;
        if ($accessToken = $cache->get(self::CACHE_KEY_FEITE_ACCESS_TOKEN)) {
            return $accessToken;
        }

        $api = 'http://exapi.flytcloud.com/api/auth/Authorization/GetAccessToken';
        $data = [
            'grant_type' => 'password',
            'username' => $this->config->get('printUsername'),
            'password' => md5($this->config->get('printPassword')),
        ];
        $body = ['body' => json_encode($data)];

        $response = $this->client->post($api, $body);

        $arr = json_decode($response->getBody(), true);
        $cache = \Yii::$app->cache;

        if (!empty($arr['access_token'])) {
            $duration = $arr['expires_in'] - 3600;
            $cache->set(self::CACHE_KEY_FEITE_ACCESS_TOKEN, $arr['access_token'], $duration);

            return $arr['access_token'];
        }

        throw new ExpressException('获取 AccessToken 失败', (array)$arr);
    }


    /**
     * Parse result
     *
     * @param string $result
     * @param $key
     * @return array
     * @throws \Exception
     */
    protected function parseResult($result, $key = '')
    {
        $arr = json_decode($result, true);
        if (empty($arr)) {
            throw new \Exception('Invalid response: ' . $result, 400);
        }
        if (!$arr['ErrorCode'] && $arr['Success']) {
            return $key ? $arr[$key] : $arr;
        }
        $message = isset($arr['Remark']) ? $arr['Remark'] : $result;
        $code = isset($arr['ErrorCode']) ? $arr['ErrorCode'] : 0;
        throw new ExpressException($message, $code);
    }
}
