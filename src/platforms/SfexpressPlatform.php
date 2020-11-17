<?php


namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class SfexpressPlatform extends Platform
{
    /**
     * @var string
     */
    private $endpoint = "";

    const ENDPOINT = "http://kts-api-uat.trackmeeasy.com/ruserver/webservice/sfexpressService?wsdl";

    /**
     * @inheritDoc
     */
    public function getClient()
    {

        $this->endpoint = $this->endpoint ?: self::ENDPOINT;
        $client = new nusoap_client($this->endpoint, true);
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;

        $this->endpoint = $this->config->get('host') ?: self::ENDPOINT;

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
        $parameter = ['order' => $this->formatOrder($order)];
        $result = $this->client->call('sfKtsService', $parameter);
        if (isset($result['orderActionStatus'])) {
            if ($result[''] == 'Y') {

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

    /**
     * @param string $input
     * @return string
     */
    private function sign(string $input): string
    {
        // 生成 MD5
        $md5 = md5($input . $this->config->get("checkword"), true);
        // Base64 编码
        return base64_encode($md5);
    }

    /**
     * @param Order $order
     * @return array
     */
    private function formatOrder(Order $order): array
    {
        return [
            "orderid" => "",
            "platform_order_id" => "",
            "platform_code" => "",
            "erp_code" => "",
            "express_type" => "",
            "j_company" => "",
            "j_contact" => "",
            "j_tel" => "",
            "j_mobile" => "",
            "j_province" => "",
            "j_city" => "",
            "j_address" => "",
            "d_company" => "",
            "d_contact" => "",
            "d_tel" => "",
            "d_mobile" => "",
            "d_province" => "",
            "d_city" => "",
            "d_address" => "",
            "parcel_quantity" => "",
            "pay_method" => "",
            "declared_value" => "",
            "declared_value_currency" => "",
            "j_country" => "",
            "j_county" => "",
            "j_post_code" => "",
            "d_country" => "",
            "d_county" => "",
            "d_post_code" => "",
            "cargo_total_weight" => "",
            "operate_flag" => "1",
            "isBat" => "",
            "cargo" => [
                "name" => "",
                "count" => "",
                "unit" => "",
                "weight" => "",
                "amount" => "",
                "cargo_desc" => "",
                "currency" => "",
                "cname" => "",
                "hscode" => "",
                "order_url" => "",
            ],
            "extra" => [
                "d_email" => "",
                "order_website" => "",
            ]
        ];
    }
}
