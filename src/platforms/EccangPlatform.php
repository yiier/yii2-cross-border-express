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
        $this->endpoint = $this->config->get("wsdl") ?: $this->wsdl;
        $client = new nusoap_client($this->endpoint, false);
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
     * @throws \SoapFault
     */
    public function createOrder(Order $order): OrderResult
    {
        $orderResult = new OrderResult();
        $req = $this->getRequestParams(
            'createOrder',
            $this->formatOrder($order)
        );
        var_dump($req);
        $options = array(
            "trace" => true,
            "connection_timeout" => 1000,
            "encoding" => "utf-8"
        );
        $client = new \SoapClient ($this->wsdl, $options);
        $rs = $client->callService($req);

        $result = $this->parseResponse($rs->response);

        $orderResult->expressTrackingNumber = $result['shipping_method_no'];
//        $orderResult->expressAgentNumber = self::dataGet($result, 'trackingNumberUsps');
        $orderResult->expressNumber = $result['reference_no'];

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

    /**
     * @param string $resp
     * @return array
     * @throws ExpressException
     */
    protected function parseResponse(string $resp): array
    {
        $res = json_decode($resp, true);
        if (!$res) {
            throw new ExpressException("返回数据解析失败");
        }

        if (strtoupper($res["ask"]) != "SUCCESS") {
            throw new ExpressException(sprintf("err: %s, code: %d", $res["message"], $res["err_code"]));
        }

        return $res;
    }
}
