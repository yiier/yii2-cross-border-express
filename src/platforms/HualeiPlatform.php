<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2020/4/7
 * Time: 14:54
 * File: HuaLeiPlatform.php
 */

namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class HualeiPlatform extends Platform
{
    /**
     * default host
     */
    const HOST = 'http://www.sz56t.com:8082';

    const SUCCESS = "TRUE";

    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var string
     */
    private $customerUserId;

    /**
     * @return Client|nusoap_client
     * @throws ExpressException
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

        $this->host = $this->config->get("host") ? $this->config->get("host") : self::HOST;
        $this->customerId = $this->config->get("customer_id");
        $this->customerUserId = $this->config->get("customer_user_id");
        if ($this->customerUserId == "" || $this->customerId == "") {
            try {
                $this->auth($client);
            } catch (\Exception $exception) {
                throw new ExpressException($exception->getMessage());
            }
        }

        return $client;
    }

    /**
     * @param string $countryCode
     * @return array|Transport[]
     */
    public function getTransportsByCountryCode(string $countryCode): array
    {
        return null;
    }

    /**
     * create order
     *
     * @param Order $order
     * @return OrderResult
     * @throws ExpressException
     */
    public function createOrder(Order $order): OrderResult
    {
        $waybill = $this->formatOrder($order);
        $params = json_encode($waybill, true);

        $uri = $this->host . "/createOrderApi.htm?param=" . $params;
        $result = $this->parseResult($this->client->post($uri)->getBody());

        $orderResult = new OrderResult();
        if (!empty($result) && strtoupper($result["ack"]) == self::SUCCESS) {
            $orderResult->expressTrackingNumber = $result["tracking_number"];
            $orderResult->expressAgentNumber = $result["order_transfercode"];
//            $orderResult->expressNumber = $result["reference_number"];
            $orderResult->expressNumber = $result["order_id"];
            $orderResult->data = json_encode($result, true);
        } else {
            throw new ExpressException('订单提交返回失败', (array)$result);
        }
        $orderResult->data = json_encode($result, JSON_UNESCAPED_UNICODE);

        return $orderResult;
    }

    /**
     * get print url
     * @param string $orderNumber
     * @return string
     */
    public function getPrintUrl(string $orderNumber): string
    {
        return sprintf("%s/order/FastRpt/PDF_NEW.aspx?Format=A4_EMS_BGD.frx&PrintType=1&order_id=%s&Print=1",
            $this->config->get("print_host"), $orderNumber
        );
    }

    /**
     * @param string $orderNumber
     * @return OrderFee
     */
    public function getOrderFee(string $orderNumber): OrderFee
    {
        return null;
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
    protected function formatOrder(Order $orderClass)
    {
        $goods = [];
        foreach ($orderClass->goods as $key => $value) {
            $goods[$key] = [
                "invoice_amount" => $value->worth,
                "invoice_pcs" => $value->quantity,
                "invoice_title" => $value->description,
                "invoice_weight" => $value->weight,
                "item_id" => "",
                "item_transactionid" => "",
                "sku" => $value->sku,
                "sku_code" => "",
                "hs_code" => $value->hsCode
            ];
        }

        return [
            "buyerid" => $orderClass->customerOrderNo,
            "consignee_address" => $orderClass->recipient->address,
            "order_piece" => $orderClass->package->quantity,
            "consignee_city" => $orderClass->recipient->city,
            "consignee_mobile" => $orderClass->recipient->phone,
            "order_returnsign" => $orderClass->isReturn ? "Y" : "N",
            "consignee_name" => $orderClass->recipient->name,
            "trade_type" => "ZYXT",
            "consignee_postcode" => $orderClass->recipient->zip,
            "consignee_state" => $orderClass->recipient->state,
            "consignee_telephone" => $orderClass->recipient->phone,
            "country" => $orderClass->recipient->countryCode,
            "customer_id" => $this->customerId,
            "customer_userid" => $this->customerUserId,
            "orderInvoiceParam" => $goods,
            "order_customerinvoicecode" => $orderClass->customerOrderNo,
            "product_id" => $orderClass->transportCode,
            //"weight" => "总重，选填，如果sku上有单重可不填该项",
            "weight" => "",
            "product_imagepath" => "",
            "order_transactionurl" => "",
            "consignee_email" => $orderClass->recipient->email,
            "consignee_companyname" => $orderClass->recipient->company,
            "order_cargoamount" => $orderClass->package->declareWorth,
            "order_insurance" => $orderClass->evaluate,
            "consignee_taxno" => $orderClass->taxesNumber,
            "consignee_doorno" => "",
            "shipper_name" => $orderClass->shipper->name,
            "shipper_companyname" => $orderClass->shipper->company,
            "shipper_address1" => $orderClass->shipper->address,
            "shipper_city" => $orderClass->shipper->city,
            "shipper_state" => $orderClass->shipper->state,
            "shipper_postcode" => $orderClass->shipper->zip,
            "shipper_country" => $orderClass->shipper->countryCode,
            "shipper_telephone" => $orderClass->shipper->phone,
        ];
    }

    /**
     * @param string $result
     * @return array
     * @throws ExpressException
     */
    protected function parseResult(string $result): array
    {
        $arr = json_decode($result, true);
        if (empty($arr) || !isset($arr['ack'])) {
            throw new ExpressException('Invalid response: ' . $result, 400);
        }
        if (strtoupper($arr["ack"]) != self::SUCCESS) {
            $message = urldecode($arr['message']);
            throw new ExpressException($message);
        }
        return $arr;
    }

    /**
     * @param Client $client
     * @return void
     * @throws ExpressException
     */
    private function auth(Client $client)
    {
        $uri = $this->host . sprintf("/selectAuth.htm?username=%s&password=%s",
                $this->config->get("username"),
                $this->config->get("password")
            );
        $res = $client->post($uri);
        $response = json_decode(str_replace("'", "\"", $res->getBody()), true);
        if ($response && $response["ack"] == 'true') {
            $this->customerUserId = $response["customer_userid"];
            $this->customerId = $response["customer_id"];
        } else {
            throw new ExpressException("用户名或密码错误");
        }
    }
}
