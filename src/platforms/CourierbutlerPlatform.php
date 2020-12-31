<?php


namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class CourierbutlerPlatform extends Platform
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
     * @param string $refrence_no 客户参考号
     * @return string
     * @throws ExpressException
     */
    public function getTracingNumber(string $refrence_no): string
    {


        $this->body["serviceMethod"] = "gettrackingnumber";
        $this->body["paramsJson"] = json_encode([
            'reference_no'=>$refrence_no
        ], true);
        try {
            $result = $this->client->post($this->host . "/webservice/PublicService.asmx/ServiceInterfaceUTF8", [
                'form_params' => $this->body,
            ])->getBody();
            $data = $this->parseResult($result);
            //var_dump($data);exit;
            return $data['shipping_method_no']?$data['shipping_method_no']:'';
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("获取转单号失败: %s", $exception->getMessage()));
        }

        return "";
    }




    /**
     * @param string $countryCode
     * @return array|Transport[]
     */
    public function getTransportsByCountryCode(string $countryCode)
    {
        return [];
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
                'form_params' => $this->body,
            ])->getBody();
            $resData = $this->parseResult($result);
            $orderResult = new OrderResult();
            $orderResult->data = json_encode($resData, true);
            $orderResult->expressNumber = !empty($resData["refrence_no"]) ? $resData["refrence_no"] : "";
            $orderResult->expressTrackingNumber = !empty($resData["shipping_method_no"]) ? $resData["shipping_method_no"] : "";;
            $orderResult->expressAgentNumber = !empty($resData["shipping_method_no"]) ? $resData["shipping_method_no"] : "";
            return $orderResult;
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("创建包裹失败: %s", $exception->getMessage()));
        }
    }

    /**
     * @param string $orderNumber
     * @return string
     * @throws ExpressException
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string
    {
        $this->body["serviceMethod"] = "getnewlabel";
        $this->body["paramsJson"] = json_encode([
            "configInfo" => [
                "lable_file_type" => "1",
                "lable_paper_type" => "1",
                "lable_content_type" => "1",
                "additional_info" => [
                    "lable_print_invoiceinfo" => "N",
                    "lable_print_buyerid" => "N",
                    "lable_print_datetime" => "Y",
                    "customsdeclaration_print_actualweight" => "N",
                ]
            ],
            "listorder" => [
                [
                    "reference_no" => $orderNumber,
                ]
            ],
        ], true);
        try {
            $result = $this->client->post($this->host . "/webservice/PublicService.asmx/ServiceInterfaceUTF8", [
                'form_params' => $this->body,
            ])->getBody();
            $res = $this->parseResult($result);
            return $res[0]["lable_file"];
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("获取打印地址失败: %s", $exception->getMessage()));
        }
    }

    /**
     * @param string $orderNumber
     * @return OrderFee
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        return new OrderFee();
    }

    /**
     * @param array $query
     * @return array
     */
    public function getOrderAllFee(array $query = []): array
    {
        return [];
    }

    /**
     * @param string $result
     * @return array
     * @throws ExpressException
     */
    protected function parseResult(string $result)
    {
        $res = json_decode($result, true);
        if(!is_array($res)) {
            throw new ExpressException('接口返回数据异常');
        }
        if (isset($res["success"]) && $res["success"] == 1) {
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
        foreach ($orderClass->goods as $good) {
            $invoice[] = [
                "sku" => $good->sku,
                "invoice_enname" => $good->description,
                "invoice_cnname" => $good->cnDescription,
                "invoice_quantity" => $good->quantity,
                "unit_code" => "PCE",
                "invoice_unitcharge" => $good->worth,
                "hs_code" => $good->hsCode,
                "invoice_material" => $good->enMaterial,
            ];
        }

        return [
            'reference_no' => $orderClass->customerOrderNo,
            'shipping_method' => $orderClass->transportCode,
            'order_weight' => $orderClass->package->weight,
            'order_pieces' => $orderClass->package->quantity,
            'cargotype' => "W",
            'mail_cargo_type' => '2',
            'order_info' => $orderClass->package->description,
            'shipper' => [
                "shipper_name" => $orderClass->shipper->name,
                "shipper_company" => $orderClass->shipper->company,
                "shipper_countrycode" => $orderClass->shipper->countryCode,
                "shipper_province" => $orderClass->shipper->state,
                "shipper_city" => $orderClass->shipper->city,
                "shipper_district" => $orderClass->shipper->address,
                "shipper_street" => $orderClass->shipper->address,
                "shipper_postcode" => $orderClass->shipper->zip,
                "shipper_telephone" => $orderClass->shipper->phone,
                "shipper_mobile" => $orderClass->shipper->phone,
                "shipper_email" => $orderClass->shipper->email,
            ],
            "consignee" => [
                "consignee_name" => $orderClass->recipient->name,
                "consignee_company" => $orderClass->recipient->company,
                "consignee_countrycode" => $orderClass->recipient->countryCode,
                "consignee_province" => $orderClass->recipient->state,
                "consignee_city" => $orderClass->recipient->city,
                "consignee_district" => $orderClass->recipient->address,
                "consignee_street" => $orderClass->recipient->address,
                "consignee_postcode" => $orderClass->recipient->zip,
                "consignee_doorplate" => $orderClass->recipient->doorplate,
                "consignee_areacode" => "",
                "consignee_telephone" => $orderClass->recipient->phone,
                "consignee_mobile" => $orderClass->recipient->phone,
                "consignee_email" => $orderClass->recipient->email,
            ],
            "invoice" => $invoice
        ];
    }
}
