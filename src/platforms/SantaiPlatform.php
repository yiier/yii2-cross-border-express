<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/19 10:54 AM
 * description:
 */

namespace yiier\crossBorderExpress\platforms;

use nusoap_client;
use yii\helpers\ArrayHelper;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\CountryCodes;
use yiier\crossBorderExpress\exceptions\ExpressException;

class SantaiPlatform extends Platform
{

    const ENDPOINT = 'http://www.sendfromchina.com/ishipsvc/web-service?wsdl';

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @return nusoap_client
     */
    public function getClient()
    {
        $this->endpoint = $this->endpoint ?: self::ENDPOINT;
        $client = new nusoap_client($this->endpoint, true);
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
                $orderResult->expressAgentNumber = self::dataGet($result, 'trackingNumberUsps');
                $orderResult->expressNumber = $result['orderCode'];
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
     * Get platform print url
     * @param string $orderNumber
     * @return string
     */
    public function getPrintUrl(string $orderNumber): string
    {
        $host = 'http://www.sendfromchina.com/api/label';
        $printType = 1;
        $fileType = 'pdf';

        return "{$host}?orderCodeList={$orderNumber}&printType={$printType}&print_type={$fileType}";
    }


    /**
     * Get platform order fee
     * @param string $orderNumber
     * @return OrderFee
     * @throws \Exception
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        $this->endpoint = 'http://www.sendfromchina.com/ishipsvc/finance-lists-service?wsdl';
        $client = $this->getClient();
        $query = [
            'code' => ltrim($orderNumber, 'SFC3'),
            'startDate' => date('Y-m-d', strtotime("-2 years")),
            'endDate' => date('Y-m-d'),
        ];
        $parameter = array_merge($this->getAuthParams(), ['orderFeeDetailRequestInfo' => $query]);
        $result = $client->call('orderFeeDetail', $parameter);
        if (isset($result['status']) && $result['status'] === "1" && isset($result['lists'])) {
            $orderFee = new OrderFee();
            $orderFee->chargeWeight = ArrayHelper::getValue($result, 'lists.weight', 0);
            $orderFee->orderNumber = $result['lists']['AeCode'];
            $orderFee->freight = $result['lists']['shipping_cost'];
            $orderFee->fuelCosts = $result['lists']['addons'];
            $orderFee->registrationFee = $result['lists']['reg_fee'];
            $orderFee->processingFee = $result['lists']['according_handling'];
            $orderFee->otherFee = $result['lists']['rests_fee'];
            $orderFee->totalFee = $result['lists']['total_shipping_cost'];
            $orderFee->customerOrderNumber = $result['lists']['customer_order_code'];
            $orderFee->country = $result['lists']['cn_name'];
            $orderFee->transportCode = $result['lists']['ship_type_code'];
            $orderFee->datetime = date('c', strtotime($result['lists']['deduct_refund_time']));
            $orderFee->data = json_encode($result, JSON_UNESCAPED_UNICODE);
            return $orderFee;
        }
        throw new ExpressException('获取订单费用失败', (array)$result);
    }


    /**
     * Get platform all order fee
     * @param array $query
     * @return OrderFee[]
     * @throws ExpressException
     */
    public function getOrderAllFee(array $query = []): array
    {
        $this->endpoint = 'http://www.sfcservice.com/ishipsvc/web-service?wsdl';
        $client = $this->getClient();
        $defaultQuery = [
            'startime' => date('Y-m-d H:i:s', strtotime("-3 months")),
            'endtime' => date('Y-m-d H:i:s'),
            'page' => 1,
        ];
        $parameter = array_merge($this->getAuthParams(), array_merge($defaultQuery, $query));
        $result = $client->call('getFeeByTime', $parameter);
        if (isset($result['ask']) && $result['ask'] === "Success" && isset($result['data'])) {
            $data = (array)$result['data'];
            $orderFee = new OrderFee();
            $items = [];
            unset($result['data']);
            foreach ($data as $key => $datum) {
                $_orderFee = clone $orderFee;
                $_orderFee->chargeWeight = $datum['feeWeight'];
                $_orderFee->orderNumber = $datum['orderCode'];
                $_orderFee->freight = $datum['baseFee'];
                $_orderFee->fuelCosts = 0;
                $_orderFee->registrationFee = $datum['regFee'];
                $_orderFee->processingFee = $datum['dealFee'];
                $_orderFee->otherFee = isset($datum['otherFees']) ?
                    array_sum(ArrayHelper::getColumn($datum['otherFees'], 'total_fee')) : 0;
                $_orderFee->totalFee = $datum['totalFee'];
                $_orderFee->customerOrderNumber = $datum['customerOrderNo'];
                $_orderFee->country = '';
                $_orderFee->transportCode = $datum['shipTypeCode'];
                $_orderFee->datetime = date('c', strtotime($datum['chargebackTime']));
                $_orderFee->data = json_encode($result + ['data' => $datum], JSON_UNESCAPED_UNICODE);
                $items[$key] = $_orderFee;
            }
            return $items;
        }
        throw new ExpressException('获取订单费用失败', (array)$result);
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

        $specialTransportCodes = [
            'HKDHL',
            'HKDHL1',
            'CNUPS',
            'SZUPS',
            'HKUPS',
            'SGDHL',
            'EUTLP',
            'CNFEDEX',
            'HKFEDEX',
            'CNS FEDEX',
            'HKSFEDEX',
            'EUEXP3'
        ];

        if (in_array($orderClass->transportCode, $specialTransportCodes)) {
            $order['pieceNumber'] = $orderClass->pieceNumber ?: 1;
        }

        return array_merge($order, $shipper, $recipient, $package);
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
