<?php


namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\CountryCodes;
use yiier\crossBorderExpress\exceptions\ExpressException;

class HuliantongPlatform extends Platform
{
    /**
     * @var string
     */
    private $endpoint = "";

    /**
     * @var string
     */
    private $userToken = "";

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        $this->endpoint = $this->config->get("host") ?: $this->endpoint;
        $client = new nusoap_client($this->endpoint . "/xms/services/order?wsdl", true);
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;
        $this->userToken = $this->config->get("userToken");

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
        $parameter = $this->formatOrder($order);
        $result = $this->client->call('createAndAuditOrder', [
            'createOrderRequest' => $parameter,
            "userToken" => $this->userToken,
        ]);
        if (isset($result["success"])) {
            if (strtoupper($result["success"]) == "TRUE") {
                $trackingNo = "";
                if (!empty($result["trackingNo"])) {
                    $trackingNo = $result['trackingNo'];
                }
                if (!empty($result["id"])) {
                    $orderResult->expressNumber = $result['id'];
                }
                $orderResult->expressTrackingNumber = $trackingNo;
            } else {
                $error = $result["error"];
                $msg = !empty($error['errorCode']) ? $error["errorCode"] . ":" : "";
                $msg .= !empty($error['errorInfo']) ? $error["errorInfo"] . ":" : "";
                $msg .= !empty($error['solution']) ? $error["solution"] : "";
                throw new ExpressException($msg);
            }
        } else {
            throw new ExpressException('订单提交返回失败' . json_encode($result, true), (array)$result);
        }
        $orderResult->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderResult;
    }

    /**
     * @param string $orderNumber
     * @param array $params
     * @return string
     * @throws ExpressException
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string
    {
        $orderKey = "oid"; // 跟踪单号（trackingNo）,订单编号（orderId）,客户单号（orderNo）
        // 纸张尺寸，
        //“1”表示80.5mm × 90mm
        //“2”表示105mm × 210mm
        //“3”表示A4
        //“7”表示100mm × 150mm
        //“4”表示102mm × 76mm
        //“5”表示110mm × 85mm
        //“6”表示100mm × 100mm
        //选择打印样式
        //1 地址标签打印
        //11 报关单
        //2 地址标签+配货信息
        //3 地址标签+报关单
        //12 特殊100mm×100mm地址标签+配货信息+报关单
//        return sprintf("%s/xms/client/order_online!printPdf.action?userToken=%s&%s=%s&printSelect=%d&pageSizeCode=%d&showCnoBarcode=%d",
//            $this->endpoint, $this->userToken, $orderKey, $orderNumber, 1, 3, 0);
        $result = $this->client->call("printOrder", [
            'printOrderRequest' => [
                "oid" => $orderNumber,
                "printSelect" => 3,
                "pageSizeCode" => 6,
                "showCnoBarcode" => 0,
                "downloadPdf" => 0,
                "showRecycleTags" => 0
            ],
            "userToken" => $this->userToken,
        ]);

        if (empty($result)) {
            throw new ExpressException("网经错误");
        }
        if (isset($result["success"]) && strtoupper($result["success"]) == "TRUE") {
            return $result["url"];
        }
        throw new ExpressException(sprintf("%s: %s : %s", $result["errorCode"], $result["errorInfo"], $result["solution"]));
    }

    /**
     * @param string $orderNumber
     * @return OrderFee
     * @throws ExpressException
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {

        $orderFee = new OrderFee();

        $key = "orderId";

        $result = $this->client->call('lookupOrder', [
            "userToken" => $this->userToken,
            "lookupOrderRequest" => [
                $key => $orderNumber,
            ]
        ]);
        if (empty($result["success"])) {
            throw new ExpressException('订单提交返回失败', (array)$result);
        }
        if (strtoupper($result["success"]) != "TRUE") {
            $error = $result["error"];
            $msg = "orderId:{$orderNumber}:";
            $msg .= !empty($error['errorCode']) ? $error["errorCode"] . ":" : "";
            $msg .= !empty($error['errorInfo']) ? $error["errorInfo"] . ":" : "";
            $msg .= !empty($error['solution']) ? $error["solution"] : "";
            throw new ExpressException($msg);
        }

        if (empty($result["order"])) {
            return $orderFee;
        }

        $order = $result["order"];

        $orderFee->orderNumber = !empty($order["trackingNo"]) ? $order["trackingNo"] : "";
        $orderFee->chargeWeight = $order["balanceWeight"] > 0 ? $order["balanceWeight"] * 1000 : 0; // realWeight 实际重（kg）。 realVolWeight 体积重（kg）。balanceWeight 结算重（kg）。
        $orderFee->freight = !empty($order["transportFee"]) ? $order["transportFee"] * 100 : 0;
        $orderFee->totalFee = !empty($order["totalFee"]) ? $order["totalFee"] * 100 : 0;
        $orderFee->otherFee = !empty($order["otherFee"]) ? $order["otherFee"] * 100 : 0;
        $orderFee->customerOrderNumber = !empty($order["orderNo"]) ? $order["orderNo"] : "";
        $orderFee->country = $order["currency"];
        $orderFee->transportCode = !empty($order["transportWayCode"]) ? $order["transportWayCode"] : "";
        $orderFee->transportName = !empty($order["transportWayName"]) ? $order["transportWayName"] : "";
        $orderFee->datetime = !empty($order["createTime"]) ? $order["createTime"] : ""; //2019-03-02T18:26:25
        $orderFee->data = json_encode($result["order"], JSON_UNESCAPED_UNICODE);

        return $orderFee;
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
     * 格式化所需要的数据
     *
     * @param Order $orderClass
     * @return array
     */
    protected function formatOrder(Order $orderClass): array
    {
        // 收件人
        $declareItems = [];
        foreach ($orderClass->goods as $good) {
            $declareItems[] = [
                'name' => $good->description,
                'cnName' => $good->cnDescription,
                'pieces' => $good->quantity,
                'netWeight' => $good->weight,
                'unitPrice' => $good->worth,
                'customsNo' => $good->hsCode,
            ];
        }

        return [
            'orderNo' => $orderClass->customerOrderNo,
            'transportWayCode' => $orderClass->transportCode,
            'cargoCode' => 'W',
            'originCountryCode' => $orderClass->shipper->countryCode,
            'destinationCountryCode' => $orderClass->recipient->countryCode,
            'pieces' => $orderClass->package->quantity,
            'length' => $orderClass->package->length,
            'width' => $orderClass->package->height,
            'height' => $orderClass->package->height,
            'weight' => $orderClass->package->weight,
            "shipperCompanyName" => $orderClass->shipper->company,
            "shipperName" => $orderClass->shipper->name,
            "shipperAddress" => $orderClass->shipper->address,
            "shipperTelephone" => $orderClass->shipper->phone,
            "shipperMobile" => $orderClass->shipper->phone,
            "shipperPostcode" => $orderClass->shipper->zip,
            "shipperCity" => $orderClass->shipper->city,
            "shipperProvince" => $orderClass->shipper->state,
            "shipperStreet" => $orderClass->shipper->address,
            "shipperStreetNo" => "",
            'consigneeCompanyName' => $orderClass->recipient->company,
            'consigneeName' => $orderClass->recipient->name,
            'consigneeStreetNo' => "",
            'street' => $orderClass->recipient->address,
            'city' => $orderClass->recipient->city,
            'province' => $orderClass->recipient->state,
            'consigneePostcode' => $orderClass->recipient->zip,
            'consigneeTelephone' => $orderClass->recipient->phone,
            'consigneeMobile' => $orderClass->recipient->phone,
            'trackingNo' => $orderClass->customerOrderNo,
            'insured' => $orderClass->evaluate > 0 ? "Y" : 'N',
            'goodsCategory' => 'O',
            'goodsDescription' => $orderClass->package->description,
            'declareItems' => $declareItems
        ];
    }
}
