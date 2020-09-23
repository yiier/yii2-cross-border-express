<?php


namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
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
     * @var string $endpoint
     */
    protected $endpoint;

    /**
     * @var array $options
     */
    private $options = [
        "trace" => true,
        "connection_timeout" => 3000,
        "encoding" => "utf-8"
    ];

    /**
     * @return Client
     */
    public function getClient()
    {
        $this->endpoint = $this->config->get("wsdl") ?: $this->webService;
        $this->appKey = $this->config->get("appKey");
        $this->appToken = $this->config->get("appToken");
//        return new \SoapClient($this->wsdl, $this->options);
        $headers = [
            'Content-Type' => 'application/xml; charset=utf8',
            'Accept' => 'application/xml',
        ];

        return new \GuzzleHttp\Client([
            'headers' => $headers,
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);
    }

    /**
     * @param string $countryCode
     * @return array|void|Transport[]
     * @throws ExpressException
     */
    public function getTransportsByCountryCode(string $countryCode)
    {
        $req = $this->getRequestParams(
            'getCountry', []
        );
        $rs = $this->client->post($this->endpoint, [
            "body" => $req,
        ]);
        $result = $this->parseResponse($rs->getBody());
        $transports = [];

        foreach ($result["data"] as $value) {
            if (strtoupper($value["CountryCode"]) != strtoupper($countryCode)) {
                continue;
            }
            $t = new Transport();
            $t->countryCode = $value["CountryCode"];
            $t->cnName = $value["CName"];
            $t->enName = $value["EName"];
            $transports[] = $t;
        }

        return $transports;
    }

    /**
     * @param Order $order
     * @return OrderResult
     * @throws ExpressException
     */
    public function createOrder(Order $order): OrderResult
    {
        $orderResult = new OrderResult();
        $req = $this->getRequestParams(
            'createOrder',
            $this->formatOrder($order)
        );

        $rs = $this->client->post($this->endpoint, [
            "body" => $req,
        ]);
        $result = $this->parseResponse($rs->getBody());

        $trackRes = $this->getTrackNumber($result["reference_no"]);
        $orderResult->expressNumber = $trackRes["WayBillNumber"];
        $orderResult->expressTrackingNumber = $trackRes["TrackingNumber"];
        $orderResult->expressAgentNumber = $result["agent_number"];

        $orderResult->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderResult;
    }

    /**
     * @param string $orderNumber
     * @return string
     * @throws ExpressException
     */
    public function getPrintUrl(string $orderNumber): string
    {
        $req = $this->getRequestParams(
            'getLabelUrl',
            [
                "reference_no" => $orderNumber,
                "label_type" => "2",
            ]
        );
        $rs = $this->client->post($this->endpoint, [
            "body" => $req,
        ]);

        $result = $this->parseResponse($rs->getBody());
        return $result["url"];
    }

    /**
     * @param string $orderNumber
     * @return OrderFee
     * @throws ExpressException
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        $req = $this->getRequestParams(
            'getReceivingExpense',
            [
                "reference_no" => $orderNumber,
            ]
        );
        $rs = $this->client->post($this->endpoint, [
            "body" => $req,
        ]);
        $result = $this->parseResponse($rs->getBody());
        $response = new OrderFee();
        $data = $result["data"];
        if (empty($data)) {
            return $response;
        }

        $response->country = $data["CountryCode"];
        $response->totalFee = $data["TotalFee"];
        $response->otherFee = $data["OtherFee"];
        $response->processingFee = $data["HandlingFee"];
        $response->registrationFee = $data["Register"];
        $response->freight = $data["Freight"];
        $response->chargeWeight = $data["SettleWeight"];
        $response->fuelCosts = $data["FuelCharge"];
        $response->customerOrderNumber = $data["CustomerOrderNumber"];
        $response->orderNumber = $data["TrackingNumber"]; // WaybillNumber
        $response->data = json_encode($data, JSON_UNESCAPED_UNICODE);

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function getOrderAllFee(array $query = []): array
    {
        return [];
    }

    /**
     * @param Order $orderClass
     * @return array
     */
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
     * @param string $referenceNo
     * @return array
     * @throws ExpressException
     */
    private function getTrackNumber(string $referenceNo): array
    {
        $req = $this->getRequestParams(
            'getTrackNumber',
            [
                "reference_no" => [$referenceNo],
            ]
        );
        $rs = $this->client->post($this->endpoint, [
            "body" => $req,
        ]);
        $result = $this->parseResponse($rs->getBody());
        if (empty($result["data"])) {
            return [];
        }
        return $result["data"][0];
    }

    /**
     * @param string $service
     * @param array $paramsJson
     * @return string
     */
    protected function getRequestParams(string $service, array $paramsJson): string
    {
        $template = <<<EOF
<?xml version="1.0" encoding="utf-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://www.example.org/Ec/">
  <SOAP-ENV:Body>
    <ns1:callService>
      <paramsJson>%s</paramsJson>
      <appToken>%s</appToken>
      <appKey>%s</appKey>
      <service>%s</service>
    </ns1:callService>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
EOF;
        return sprintf($template, json_encode($paramsJson), $this->appToken, $this->appKey, $service);
    }

    /**
     * @param string $resp
     * @return array
     * @throws ExpressException
     */
    protected function parseResponse(string $resp): array
    {
        $p = xml_parser_create();
        xml_parse_into_struct($p, $resp, $values, $tags);
        xml_parser_free($p);

        if (count($values) < 4) {
            throw new ExpressException("XML解析出错");
        }
        $val = $values[3]["value"];
        $res = json_decode($val, true);
        if (!$res) {
            throw new ExpressException("返回数据解析失败");
        }

        if (strtoupper($res["ask"]) != "SUCCESS") {
            $msg = $res["message"];
            if (!empty($res["Error"])) {
                $msg .= sprintf(" err: %s, code: %s ", $res["Error"]["errMessage"], $res["Error"]["errCode"]);
            }
            throw new ExpressException(sprintf(" err: %s, code: %d ", $msg, $res["err_code"]));
        }

        return $res;
    }
}
