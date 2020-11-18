<?php


namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use PHPUnit\Util\Xml;
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

    /* 顺丰接口配置 */
    protected $accesscode = '';             //商户号码
    protected $checkword = '';             //商户密匙

    const ENDPOINT = "http://kts-api-uat.trackmeeasy.com/ruserver/webservice/sfexpressService?wsdl";

    /**
     * @var array $xmlArray
     */
    private $xmlArray = [
        '@attributes' => [
            'service' => '',
            'lang' => 'zh_CN'
        ],
        'Head' => "",
        'Body' => []
    ];

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        //$config = [
        //    "timeout" => 60.0,
        //    "platforms" => [
        //        \yiier\crossBorderExpress\platforms\PlatformsName::SFEXPRESS_PLATFORM => [
        //            "accesscode" => "KT7551249912",
        //            "username" => "erptest",
        //            "checkword" => "78BE1BCAAED1EE08D344F894FBB296D3",
        //        ]
        //    ]
        //];

        $this->endpoint = $this->endpoint ?: self::ENDPOINT;
        $client = new nusoap_client($this->endpoint, true);
        $client->soap_defencoding = 'UTF-8';
        $client->decode_utf8 = false;

        $this->accesscode = $this->config->get("accesscode");
        $this->checkword = $this->config->get("checkword");
        $this->xmlArray["@attributes"]["service"] = "OrderService";
        $this->xmlArray['Head'] = $this->config->get("username");
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
        $orderData = $this->formatOrder($order);

        foreach ($orderData["Order"] as $key => $datum) {
            $this->xmlArray["Body"]["Order"]["@attributes"][$key] = $datum;
        }

        foreach ($orderData["Cargo"] as $key => $datum) {
            $this->xmlArray["Body"]["Order"]["Cargo"]["@attributes"][$key] = $datum;
        }

        $xml = $this->arrayToXml($this->xmlArray, "Request");

        $parameter = ['xml' => $xml, 'verifyCode' => $this->sign($xml)];
        $result = $this->client->call('sfKtsService', $parameter);
        $res = $this->parseXml($result, "OrderResponse");
        $orderResult->expressTrackingNumber = $res["mailno"];
        $orderResult->expressAgentNumber = $res["agent_mailno"];
        $orderResult->expressNumber = $res["direction_code"];
        $orderResult->data = json_encode($res, JSON_UNESCAPED_UNICODE);

        return $orderResult;
    }

    /**
     * @param string $result
     * @param $name
     * @return array
     * @throws ExpressException
     */
    private function parseXml(string $result, $name): array
    {
        $xmlResult = @simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS);
        $res = self::xmlToArray($xmlResult);

        $success = false;
        $error = "";
        $body = [];
        foreach ($res as $key => $node) {
            switch ($key) {
                case 'head':
                    $success = 'OK' == strtoupper($node['head']);
                    break;
                case 'body':
                    $ret['data'] = $node[strtolower($name)] ?? '';
                    break;
                case 'error':
                    $error = sprintf("code: %s; msg: %s", $node["code"], $node["error"]);
                    break;
            }
        }
        if(!$success){
            throw new ExpressException($error);
        }
        return $body;
    }

    /**
     * @inheritDoc
     */
    public function getPrintUrl(string $orderNumber): string
    {
        // TODO: Implement getPrintUrl() method.
        // https://github.com/duugr/express/blob/ebff856b658aae75cc681f9d425ab2dadba487ea/src/Sf.php
        // https://github.com/171869092/live/blob/c148b41cea36bc81217a494e23100fee873d105f/laravel/app/Service/ShunFeng/ExpressWebService.php
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
        $cargo = [];

        foreach ($order->goods as $good) {
            $cargo = [
                "name" => $good->description,
                "count" => $good->quantity,
                "unit" => "piece",
                "weight" => $good->weight,
                "amount" => $good->worth,
                "cargo_desc" => $good->enMaterial,
                "currency" => "USD",
                "cname" => $good->cnDescription,
                "hscode" => $good->hsCode
            ];
        }

        return [
            "Order" => [
                "orderid" => $order->customerOrderNo,
                "platform_order_id" => $order->customerOrderNo,
                "platform_code" => "ICOLO",
                "erp_code" => "0000",
                "express_type" => $order->transportCode,
                "j_company" => $order->shipper->company,
                "j_contact" => $order->shipper->name,
                "j_tel" => $order->shipper->phone,
                "j_mobile" => $order->shipper->phone,
                "j_province" => $order->shipper->state,
                "j_city" => $order->shipper->city,
                "j_address" => $order->shipper->address,
                "d_company" => $order->recipient->company,
                "d_contact" => $order->recipient->name,
                "d_tel" => $order->recipient->phone,
                "d_mobile" => $order->recipient->phone,
                "d_province" => $order->recipient->state,
                "d_city" => $order->recipient->city,
                "d_address" => $order->recipient->address,
                "parcel_quantity" => $order->package->quantity,
                "pay_method" => "1",
                "declared_value" => $order->package->quantity ?: 1 * $order->package->declareWorth,
                "declared_value_currency" => "USD",
                "j_country" => $order->shipper->countryCode,
                "j_county" => $order->shipper->address,
                "j_post_code" => $order->shipper->zip,
                "d_country" => $order->recipient->countryCode,
                "d_county" => $order->recipient->address,
                "d_post_code" => $order->recipient->zip,
                "cargo_total_weight" => $order->package->weight,
                "operate_flag" => "1",
                "isBat" => $order->withBattery,
            ],
            "Cargo" => $cargo
        ];
    }

    public static function toObj($xml, $notObj = true)
    {
        $obj = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
        return json_decode(json_encode($obj), $notObj);
    }

    /**
     * 转换XML属性为数组
     *
     * @param \SimpleXMLElement $xml
     *
     * @param array $collection
     * @return array
     */
    public static function xmlToArray(\SimpleXMLElement $xml, $collection = [])
    {
        $attributes = $xml->attributes();
        $nodes = $xml->children();
        if ($attributes->count() > 0) {
            if ($xml->__toString()) {
                $collection[strtolower($xml->getName())] = $xml->__toString();
            }
            foreach ($attributes as $attrName => $attrValue) {
                $collection[strtolower($attrName)] = strval($attrValue);
            }
        }

        if (0 === $xml->count()) {
            $collection[strtolower($xml->getName())] = $xml->__toString();
        }

        if (0 === $nodes->count()) {
            return $collection;
        } else {
            foreach ($nodes as $nodeName => $nodeValue) {
                if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
                    $collection[strtolower($nodeName)] = self::xmlToArray($nodeValue);
                    continue;
                }

                $collection[strtolower($nodeName)][] = self::xmlToArray($nodeValue);
            }
        }

        return $collection;
    }


    /**
     * @param $var
     * @param string $type
     * @param string $tag
     * @return string
     */
    public static function arrayToXml($var, $type = 'root', $tag = '')
    {
        $ret = '';
        if (!is_int($type)) {
            if ($tag) {
                return self::arrayToXml([$tag => $var], 0, $type);
            } else {
                $tag .= $type;
                $type = 0;
            }
        }
        $level = $type;
        $indent = str_repeat("\t", $level);
        if (!is_array($var)) {
            $ret .= $indent . '<' . $tag;
            $var = strval($var);
            if ('' == $var) {
                $ret .= ' />';
            } elseif (!preg_match('/[^0-9a-zA-Z@\._:\/-]/', $var)) {
                $ret .= '>' . $var . '</' . $tag . '>';
            } else {
                $ret .= "><![CDATA[{$var}]]></{$tag}>";
            }
            $ret .= "\n";
        } elseif (!(is_array($var) && count($var) && (array_keys($var) !== range(
                        0,
                        sizeof($var) - 1))) && !empty($var)) {
            foreach ($var as $tmp) {
                $ret .= self::arrayToXml($tmp, $level, $tag);
            }

        } else {
            $ret .= $indent . '<' . $tag;
            if (0 == $level) {
                $ret .= '';
            }

            if (isset($var['@attributes'])) {
                foreach ($var['@attributes'] as $k => $v) {
                    if (!is_array($v)) {
                        $ret .= sprintf(' %s="%s"', $k, $v);
                    }
                }
                unset($var['@attributes']);
            }
            $ret .= ">\n";
            foreach ($var as $key => $val) {
                $ret .= self::arrayToXml($val, $level + 1, $key);
            }
            $ret .= "{$indent}</{$tag}>\n";
        }

        return $ret;
    }
}
