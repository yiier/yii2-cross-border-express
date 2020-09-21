<?php


namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class EccangPlatform extends Platform
{
    /**
     * @var string $wsdl
     */
    private $wsdl = "http://120.79.51.80/default/svc/wsdl";

    /**
     * @var string $webService
     */
    private $webService = "http://120.79.51.80/default/svc/web-service";

    /**
     * @var string $appToken
     */
    private $appToken = "";

    /**
     * @var string $appKey
     */
    private $appKey = "";

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        $this->endpoint = $this->endpoint ?: $this->wsdl;
        $client = new nusoap_client($this->endpoint, true);
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;
        $this->appKey = $this->config->get("appKey");
        $this->appToken = $this->config->get("appToken");
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
     * @param Order $order
     * @return OrderResult
     * @throws ExpressException
     */
    public function createOrder(Order $order): OrderResult
    {
        $orderResult = new OrderResult();
        $result = $this->client->call(
            'createOrder',
            $this->getRequestParams('createOrder',
                $this->formatOrder($order)
            )
        );

        var_dump($result);

        if (isset($result['orderActionStatus'])) {
            if ($result['orderActionStatus'] == 'Y') {
                $orderResult->expressTrackingNumber = $result['trackingNumber'];
                $orderResult->expressAgentNumber = self::dataGet($result, 'trackingNumberUsps');
                $orderResult->expressNumber = $result['orderCode'];
            } else {
                throw new ExpressException($result['note']);
            }
        } else {
            throw new ExpressException('订单提交返回失败', (array)$result);
        }
        $orderResult->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderResult;
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

    protected function formatOrder(Order $orderClass): array
    {

        $consignee = [
            "consignee_street" => $orderClass->recipient->address,
            "consignee_name" => $orderClass->recipient->name,
            "consignee_telephone" => $orderClass->recipient->phone,
            "consignee_postcode" => $orderClass->recipient->zip,
            "consignee_province" => $orderClass->recipient->state,
            "consignee_city" => $orderClass->recipient->city
        ];

        $shipper = [
            "shipper_countrycode" => $orderClass->shipper->countryCode,
            "shipper_province" => $orderClass->shipper->state,
            "shipper_city" => $orderClass->shipper->city,
            "shipper_street" => $orderClass->shipper->address,
            "shipper_postcode" => $orderClass->shipper->zip,
            "shipper_name" => $orderClass->shipper->name,
            "shipper_telephone" => $orderClass->shipper->phone,
            "shipper_mobile" => $orderClass->shipper->phone
        ];

        $itemArr = [];
        foreach ($orderClass->goods as $good) {
            $itemArr[] = [
                "invoice_cnname" => $good->cnDescription,
                "invoice_enname" => $good->enMaterial,
                "invoice_weight" => $good->weight,
                "invoice_quantity" => $good->quantity,
                "invoice_unitcharge" => $good->worth,
                "hs_code" => $good->hsCode,
                "sku" => $good->sku
            ];
        }

        $volume[] = [
            "length" => $orderClass->package->length,
            "width" => $orderClass->package->width,
            "height" => $orderClass->package->height,
            "weight" => $orderClass->package->weight,
        ];

        return [
            "reference_no" => $orderClass->customerOrderNo,
            "shipping_method" => $orderClass->transportCode,
            "country_code" => $orderClass->shipper->countryCode,
            "order_weight" => $orderClass->package->weight,
            "order_pieces" => $orderClass->package->declareWorth,
            "is_return" => $orderClass->isReturn,
//            "reference_id" => "461983",
//            "shipment_id" => "1235646",
//            "POANumber" => "64613135",
            "Consignee" => $consignee,
            "Shipper" => $shipper,
            "ItemArr" => $itemArr,
            "Volume" => $volume,
        ];
    }

    /**
     * @param string $service
     * @param array $paramsJson
     * @return array
     */
    protected function getRequestParams(string $service, array $paramsJson): array
    {
        return [
            'service' => $service,
            'paramsJson' => json_encode($paramsJson),
            'appToken' => $this->appToken,
            'appKey' => $this->appKey
        ];
    }

    protected function parseBody() {

    }
}
