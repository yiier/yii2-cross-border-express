<?php


namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class CourierButler extends Platform
{
    private $host = "http://lfn.rtb56.com";
    private $body = [];

    /**
     * @return Client|nusoap_client
     */
    public function getClient()
    {
        $client = new \GuzzleHttp\Client([
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);

        $this->host = $this->config->get("host") ?: $this->host;
        $this->body = [
            "appToken" => $this->config->get('app_token'),
            "appKey" => $this->config->get('app_key'),
        ];

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
     * @inheritDoc
     */
    public function createOrder(Order $order): OrderResult
    {
        $this->body["serviceMethod"] = "createorder";
        $this->body["paramsJson"] = json_encode($this->formatOrder($order), true);
        try {
            $result = $this->client->post($this->host . "/webservice/PublicService.asmx/ServiceInterfaceUTF8", [
                'body' => $this->body,
            ])->getBody();
            $resData = $this->parseResult($result);
            $orderResult = new OrderResult();
            $orderResult->data = $result;
            $orderResult->expressNumber = !empty($resData["refrence_no"]) ? $resData["refrence_no"] : "";
            $orderResult->expressTrackingNumber = !empty($resData["shipping_method_no"]) ? $resData["shipping_method_no"] : "";
            return $orderResult;
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("创建包裹失败: %s", $exception->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getPrintUrl(string $orderNumber): string
    {
        $this->body["serviceMethod"] = "getnewlabel";
        $this->body["paramsJson"] = json_encode([
            "lable_file_type" => "1",
            "lable_paper_type" => "1",
            "lable_content_type" => "1",
            "additional_info" => [
                "lable_print_invoiceinfo" => "N",
                "lable_print_buyerid" => "N",
                "lable_print_datetime" => "Y",
                "customsdeclaration_print_actualweight" => "N",
            ],
            "listorder" => [
                [
                    "reference_no" => $orderNumber,
                ]
            ],
        ], true);
        try {
            $result = $this->client->post($this->host . "/webservice/PublicService.asmx/ServiceInterfaceUTF8", [
                'body' => $this->body,
            ])->getBody();
            $res = $this->parseResult($result);
            return $res[0]["lable_file"];
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("获取打印地址失败: %s", $exception->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        $this->body["serviceMethod"] = "getbusinessfee";
        $this->body["paramsJson"] = json_encode([
            "reference_no" => "1",
        ], true);
        try {
            $result = $this->client->post($this->host . "/webservice/PublicService.asmx/ServiceInterfaceUTF8", [
                'body' => $this->body,
            ])->getBody();
            $res = $this->parseResult($result);
            $order = $res[0];
            $orderFee = new OrderFee();

//            $orderFee->orderNumber = !empty($order["trackingNo"]) ? $order["trackingNo"] : "";
//            $orderFee->chargeWeight = $order["balanceWeight"] > 0 ? $order["balanceWeight"] * 1000 : 0;
//            $orderFee->freight = !empty($order["transportFee"]) ? $order["transportFee"] * 100 : 0;
//            $orderFee->totalFee = !empty($order["totalFee"]) ? $order["totalFee"] * 100 : 0;
//            $orderFee->otherFee = !empty($order["otherFee"]) ? $order["otherFee"] * 100 : 0;
//            $orderFee->customerOrderNumber = !empty($order["orderNo"]) ? $order["orderNo"] : "";
//            $orderFee->country = CountryCodes::getEnName($order["destinationCountryCode"]);
//            $orderFee->transportCode = !empty($order["transportWayCode"]) ? $order["transportWayCode"] : "";
//            $orderFee->transportName = !empty($order["transportWayName"]) ? $order["transportWayName"] : "";
//            $orderFee->datetime = !empty($order["createTime"]) ? $order["createTime"] : ""; //2019-03-02T18:26:25
//            $orderFee->data = json_encode($result["order"], JSON_UNESCAPED_UNICODE);

            return $orderFee;

        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("获取打印地址失败: %s", $exception->getMessage()));
        }
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
        $res = json_decode($result, true);
        if ($res["success"] == 1) {
            return $res["data"];
        } else {
            throw new ExpressException($res["cnmessage"]);
        }
    }

    /**
     * 格式化所需要的数据
     *
     * @param Order $orderClass
     * @return array
     */
    protected function formatOrder(Order $orderClass): array
    {
        $invoice = [];
        $cargovolume = [];
        $invoice[] = [
            "sku" => "",
            "invoice_enname" => "",
            "invoice_cnname" => "",
            "invoice_quantity" => "",
            "unit_code" => "",
            "invoice_unitcharge" => "",
            "hs_code" => "",
            "invoice_note" => "",
            "invoice_url" => "",
            "invoice_info" => "",
            "invoice_material" => "",
            "invoice_spec" => "",
            "invoice_use" => "",
            "invoice_brand" => "",
            "posttax_num" => "",
        ];

        $cargovolume[] = [
            "child_number" => "",
            "involume_length" => "",
            "involume_width" => "",
            "involume_height" => "",
            "involume_grossweight" => "",
        ];

        $extraService[] = [
            "extra_servicecode" => "",
            "extra_servicevalue" => "",
            "extra_servicenote" => "",
        ];

        return [
            'reference_no' => '',
            'shipping_method' => '',
            'shipping_method_no' => '',
            'order_weight' => '',
            'order_pieces' => '',
            'cargotype' => '',
            'mail_cargo_type' => '',
            'buyer_id' => '',
            'order_info' => '',
            'platform_id' => '',
            'custom_hawbcode' => '',
            'shipper' => [
                "shipper_name" => "",
                "shipper_company" => "",
                "shipper_countrycode" => "",
                "shipper_province" => "",
                "shipper_city" => "",
                "shipper_district" => "",
                "shipper_street" => "",
                "shipper_postcode" => "",
                "shipper_areacode" => "",
                "shipper_telephone" => "",
                "shipper_mobile" => "",
                "shipper_email" => "",
                "shipper_fax" => "",
            ],
            "consignee" => [
                "consignee_name" => "",
                "consignee_company" => "",
                "consignee_countrycode" => "",
                "consignee_province" => "",
                "consignee_city" => "",
                "consignee_district" => "",
                "consignee_street" => "",
                "consignee_postcode" => "",
                "consignee_doorplate" => "",
                "consignee_areacode" => "",
                "consignee_telephone" => "",
                "consignee_mobile" => "",
                "consignee_email" => "",
                "consignee_fax" => "",
                "consignee_certificatetype" => "",
                "consignee_certificatecode" => "",
                "consignee_credentials_period" => "",
                "consignee_tariff" => "",
            ],
            "invoice" => $invoice,
            "cargovolume" => $cargovolume,
            "extra_service" => $extraService,
        ];
    }
}
