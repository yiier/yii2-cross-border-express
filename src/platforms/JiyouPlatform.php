<?php
/**
 * author     : icowan <solacowa@gmail.com>
 * createTime : 2020/7/04 8:32 PM
 * description:
 */

namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\CountryCodes;
use yiier\crossBorderExpress\exceptions\ExpressException;

class JiyouPlatform extends Platform
{

    /**
     * default host
     */
    const HOST = '';

    /**
     * @var string $userToken
     */
    private $userToken = "";

    /**
     * 打印样式
     * 1 地址标签打印
     * 11 报关单
     * 2 地址标签+配货信息
     * 3 地址标签+报关单
     * 12 特殊100mm×100mm地址标签+配货信息+报关单
     * @var int $printSelect 打印物流样式
     */
    private $printSelect = 3;

    /**
     * 纸张尺寸，
     * 1 表示80.5mm × 90mm
     * 2 表示105mm × 210mm
     * 3 表示A4
     * 7 表示100mm × 150mm
     * 4 表示102mm × 76mm
     * 5 表示110mm × 85mm
     * 6 表示100mm × 100mm
     * @var int $pageSizeCode
     */
    private $pageSizeCode = 7;

    /**
     * @var string $host
     */
    private $host;


    /**
     * @var nusoap_client $client
     */
    public $client;

    /**
     * @inheritDoc
     * @throws ExpressException
     */
    public function getClient()
    {
        $this->host = $this->config->get("host") ? $this->config->get("host") : self::HOST;
        $host = $this->host . "/xms/services/order?wsdl";
        $this->userToken = $this->config->get("user_token");
        if ($this->userToken == "") {
            throw new ExpressException("userToken不能为空");
        }

        $this->client = new nusoap_client($host, true);
        $this->client->soap_defencoding = 'UTF-8';
        $this->client->decode_utf8 = false;

        return $this->client;
    }

    /**
     * @inheritDoc
     * @return Transport[]|array
     */
    public function getTransportsByCountryCode(string $countryCode): array
    {
        return [];
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

        // createOrder, createAndAuditOrder
        $result = $this->client->call('createAndAuditOrder', [
            'createOrderRequest' => $parameter,
            "userToken" => $this->userToken,
        ]);
        if (isset($result["success"])) {
            if (strtoupper($result["success"]) == "TRUE") {
                $trackingNo = "";
                if (!empty($result["trackingNo"])) {
                    $trackingNo = $result['trackingNo'];
                } else if (!empty($result["id"])) {
                    try {
                        $trackingNo = $this->auditOrder($result['id']);
                    } catch (ExpressException $e) {
                    }
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
     * 预报订单
     * @param int $orderId
     * @return string
     * @throws ExpressException
     */
    protected function auditOrder(int $orderId): string
    {
        $result = $this->client->call('auditOrder', [
            'orderId' => $orderId,
            "userToken" => $this->userToken,
        ]);

        var_dump($result);
        if (isset($result["success"])) {
            if (strtoupper($result["success"]) == "TRUE") {
                return $result["trackingNo"];
            }
            $error = $result["error"];
            $msg = !empty($error['errorCode']) ? $error["errorCode"] . ":" : "";
            $msg .= !empty($error['errorInfo']) ? $error["errorInfo"] . ":" : "";
            $msg .= !empty($error['solution']) ? $error["solution"] : "";
            throw new ExpressException($msg);
        } else {
            throw new ExpressException('订单预报返回失败' . json_encode($result, true), (array)$result);
        }
    }

    /**
     * @inheritDoc
     */
    public function getPrintUrl(string $orderNumber): string
    {
        $orderKey = "trackingNo"; // 跟踪单号（trackingNo）,订单编号（orderId）,客户单号（orderNo）

        return sprintf("%s/xms/client/order_online!printPdf.action?userToken=%s&%s=%s&printSelect=%d&pageSizeCode=%d&downloadPdf=0",
            $this->host, $this->userToken, $orderKey, $orderNumber, $this->printSelect, $this->pageSizeCode);
    }

    /**
     * @inheritDoc
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        $orderFee = new OrderFee();

        $result = $this->client->call('lookupOrder', [
            "userToken" => $this->userToken,
            "lookupOrderRequest" => [
                "trackingNo" => $orderNumber,
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
        $orderFee->country = CountryCodes::getEnName($order["destinationCountryCode"]);
        $orderFee->transportCode = !empty($order["transportWayCode"]) ? $order["transportWayCode"] : "";
        $orderFee->transportName = !empty($order["transportWayName"]) ? $order["transportWayName"] : "";
        $orderFee->datetime = !empty($order["createTime"]) ? $order["createTime"] : ""; //2019-03-02T18:26:25
        $orderFee->data = json_encode($result["order"], JSON_UNESCAPED_UNICODE);

        return $orderFee;
    }

    /**
     * @inheritDoc
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
        $shipper = [];
        if ($orderClass->shipper) {
            // 发件人
            $shipper = [
                "shipperCompanyName" => $orderClass->shipper->company,
                "shipperName" => $orderClass->shipper->name,
                "shipperAddress" => $orderClass->shipper->address,
                "shipperTelephone" => $orderClass->shipper->phone,
                "shipperMobile" => $orderClass->shipper->phone,
                "shipperPostcode" => $orderClass->shipper->zip,
                "shipperCity" => $orderClass->shipper->city,
                "shipperProvince" => $orderClass->shipper->state,
            ];
        }

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

        $order = [
            'consigneeCompanyName' => $orderClass->recipient->company,
            'consigneeName' => $orderClass->recipient->name,
            'street' => $orderClass->recipient->address,
            'city' => $orderClass->recipient->city,
            'province' => $orderClass->recipient->state,
            'consigneePostcode' => $orderClass->recipient->zip,
            'consigneeTelephone' => $orderClass->recipient->phone,
            'consigneeMobile' => $orderClass->recipient->phone,
//            'orderNo' => $orderClass->customerOrderNo,
            'trackingNo' => $orderClass->customerOrderNo,
            'transportWayCode' => $orderClass->transportCode, // 运输方式代码。通过接口getTransportWayList可查询到所有运输方式。
            'cargoCode' => 'W',
            'originCountryCode' => $orderClass->shipper->countryCode,
            'destinationCountryCode' => $orderClass->recipient->countryCode,
            'pieces' => $orderClass->package->quantity,
            'length' => $orderClass->package->length,
            'width' => $orderClass->package->height,
            'height' => $orderClass->package->height,
            'weight' => $orderClass->package->weight,
            'insured' => $orderClass->evaluate > 0 ? "Y" : 'N',
            'goodsCategory' => 'O',
            'declareItems' => $declareItems
        ];

        return array_merge($order, $shipper);
    }
}
