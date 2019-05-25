<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/19 10:54 AM
 * description:
 */

namespace yiier\intExpress\platforms;

use nusoap_client;
use yiier\intExpress\contracts\Order;
use yiier\intExpress\contracts\OrderResult;
use yiier\intExpress\contracts\Transport;
use yiier\intExpress\CountryCodes;
use yiier\intExpress\exceptions\ExpressException;


class SantaiPlatform extends Platform
{

    const ENDPOINT_TEMPLATE = 'http://www.sendfromchina.com/ishipsvc/web-service?wsdl';

    /**
     * @return nusoap_client
     */
    public function getClient()
    {
        $client = new nusoap_client(self::ENDPOINT_TEMPLATE, 'wsdl');
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;
        return $client;
    }

    /**
     * @param string $countryCode
     * @return Transport[]|array
     */
    public function getTransportsByCountryCode(string $countryCode): array
    {
        $ratesRequestInfo = ['country' => CountryCodes::getEnName($countryCode), 'weight' => 0.0011];
        $parameter = array_merge($this->getAuthParams(), ['ratesRequestInfo' => $ratesRequestInfo]);
        $result = $this->client->call('getRates', $parameter);
        if (empty($result['rates'])) {
            return [];
        }
        $transport = new Transport();
        $transports = [];
        foreach ($result['rates'] as $value) {
            $_transport = clone $transport;
            $_transport->countryCode = $countryCode;
            $_transport->code = $value['!shiptypecode'];
            $_transport->cnName = $value['!shiptypecnname'];
            $_transport->enName = $value['!shiptypename'];
            $_transport->ifTracking = $value['!iftracking'] === 'YES';
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
     */
    public function createOrder(Order $order): OrderResult
    {
        $orderResult = new OrderResult();
        $parameter = array_merge($this->getAuthParams(), ['addOrderRequestInfo' => $this->formatOrder($order)]);
        $result = $this->client->call('addOrder', $parameter);
        if (isset($result['orderActionStatus'])) {
            if ($result['orderActionStatus'] == 'Y') {
                $orderResult->expressTrackingNumber = $result['trackingNumber'];
                $orderResult->expressNumber = $result['orderCode'];
                $orderResult->expressAgentNumber = self::dataGet($result, 'trackingNumberUsps');
            } else {
                throw new ExpressException($result['note']);
            }
        } else {
            throw new ExpressException('订单提交返回失败', (array)$orderResult);
        }
        $orderResult->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderResult;
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
                'shipperName' => $orderClass->shipper->name,
                'shipperEmail' => $orderClass->shipper->email,
                'shipperAddress' => $orderClass->shipper->address,
                'shipperZipCode' => $orderClass->shipper->zip,
                'shipperPhone' => $orderClass->shipper->phone,
                'shipperCompanyName' => $orderClass->shipper->company,
            ];
        }

        $recipient = [
            'recipientCountry' => CountryCodes::getEnName($orderClass->recipient->countryCode),
            'recipientName' => $orderClass->recipient->name,
            'recipientEmail' => $orderClass->recipient->email,
            'recipientState' => $orderClass->recipient->state,
            'recipientCity' => $orderClass->recipient->city,
            'recipientAddress' => $orderClass->recipient->address,
            'recipientZipCode' => $orderClass->recipient->zip,
            'recipientPhone' => $orderClass->recipient->phone,
            'recipientOrganization' => $orderClass->recipient->company,
        ];


        $package = [
            'goodsDescription' => $orderClass->package->description,
            'goodsQuantity' => $orderClass->package->quantity,
            'goodsDeclareWorth' => $orderClass->package->declareWorth,
            'goodsWeight' => $orderClass->package->weight,
            'goodsLength' => $orderClass->package->length,
            'goodsWidth' => $orderClass->package->width,
            'goodsHeight' => $orderClass->package->height,
        ];

        foreach ($orderClass->goods as $key => $value) {
            $goods[$key] = [
                'detailDescription' => $value->description,
                'detailDescriptionCN' => $value->cnDescription,
                'detailQuantity' => $value->quantity,
                'detailWorth' => $value->worth,
                'detailWeight' => $value->weight,
                'hsCode' => $value->hsCode,
                'enMaterial' => $value->enMaterial,
                'cnMaterial' => $value->cnMaterial,
            ];
        }

        $order = [
            'customerOrderNo' => $orderClass->customerOrderNo,
            // 发货地址类型，1 为用户系统默认地址，2 为用户传送的地址信息
            'shipperAddressType' => $orderClass->shipper ? 2 : 1,
            'shippingMethod' => $orderClass->transportCode,
            'goodsDetails' => $goods,
            // 提交订单 confirmed，订单预提交状态 preprocess，提交且交寄订单 sumbmitted
            'orderStatus' => 'sumbmitted',
            'evaluate' => $orderClass->evaluate,
            'taxesNumber' => $orderClass->taxesNumber,
            'isRemoteConfirm' => $orderClass->isRemoteConfirm,
            'isReturn' => $orderClass->isReturn,
            'withBattery' => $orderClass->withBattery,
        ];

        return array_merge($order, $shipper, $recipient, $package);
    }

    /**
     * Get platform print url
     * @param string $orderNumber
     * @return string
     */
    public function getPrintUrl(string $orderNumber)
    {
        $host = 'http://www.sendfromchina.com/api/label';
        $printType = 1;
        $fileType = 'pdf';
        // http://www.sendfromchina.com/api/label?orderCodeList=QCFF01204060012& printType=1 &print_type=html&printSize=3&printSort=1

        return "{$host}?orderCodeList={$orderNumber}&printType={$printType}&print_type={$fileType}";
    }

    /**
     * @return array
     */
    protected function getAuthParams()
    {
        return [
            'HeaderRequest' => [
                'appKey' => $this->config['appKey'],
                'token' => $this->config['token'],
                'userId' => $this->config['userId'],
            ],
        ];
    }

}