<?php
/**
 * Created by PhpStorm.
 * User: LatteCake
 * Email: solacowa@gmail.com
 * Date: 2020/7/15
 * Time: 00:02
 * File: WanbPlatform.php
 */

namespace yiier\crossBorderExpress\platforms;


use GuzzleHttp\Client;
use nusoap_client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class WanbPlatform extends Platform
{

    /**
     * default host
     */
    const HOST = 'http://api-sbx.wanbexpress.com';

    /**
     * @var string
     */
    private $host = '';

    /**
     * @inheritDoc
     */
    public function getClient()
    {
        $nounce = hash('sha512', strtoupper($this->makeRandomString()));;
        $headers = [
            'Content-Type' => 'application/json; charset=utf8',
            'Authorization' => sprintf("Hc-OweDeveloper %s;%s;%s",
                $this->config->get("account_no"),
                $this->config->get("token"),
                $nounce
            )
        ];

        $client = new \GuzzleHttp\Client([
            'headers' => $headers,
            'timeout' => method_exists($this, 'getTimeout') ? $this->getTimeout() : 5.0,
        ]);

        $this->host = $this->config->get("host") ? $this->config->get("host") : self::HOST;

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
        $orderResult = new OrderResult();
        $parameter = $this->formatOrder($order);

        $body = $this->client->get($this->host . "/api/whoami")->getBody();
        var_dump($body->getContents());
        die;
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
     * @param int $bits
     * @return string
     */
    private function makeRandomString($bits = 256): string
    {
        $bytes = ceil($bits / 8);
        $return = '';
        for ($i = 0; $i < $bytes; $i++) {
            $return .= chr(mt_Rand(0, 255));
        }
        return $return;
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
                'CountryCode' => $orderClass->shipper->countryCode,
                'Province' => $orderClass->shipper->state,
                'City' => $orderClass->shipper->city,
                'Postcode' => $orderClass->shipper->zip,
                'Name' => $orderClass->shipper->name,
                'Address' => $orderClass->shipper->address,
                'ContactInfo' => $orderClass->shipper->phone,
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

        $items = [];
        $items[] = [
            "GoodsId" => "GoodsId",
            "GoodsTitle" => "GoodsTitle",
            "DeclaredNameEn" => "Test",
            "DeclaredNameCn" => "品名测试",
            "DeclaredValue" => [
                "Code" => ["USD",
                    "Value" => 5.0
                ]
            ],
            "WeightInKg" => 0.6,
            "Quantity" => 2,
            "HSCode" => "",
            "CaseCode" => "",
            "SalesUrl" => "http://www.amazon.co.uk/gp/product/B00FEDIPQ4",
            "IsSensitive" => false,
            "Brand" => "",
            "Model" => "",
            "MaterialCn" => "",
            "MaterialEn" => "",
            "UsageCn" => "",
            "UsageEn" => "",
        ];

        $order = [
            'ReferenceId' => '',
            'ShippingAddress' => [
                "Company" => "Company",
                "Street1" => "Street1",
                "Street2" => "Street1",
                "Street3" => null,
                "City" => "City",
                "Province" => "",
                "Country" => "",
                "CountryCode" => "GB",
                "Postcode" => "NW1 6XE",
                "Contacter" => "Jon Snow",
                "Tel" => "134567890",
                "Email" => "",
                "TaxId" => ""
            ],
            'WeightInKg' => 0,
            'ItemDetails' => $items,
            'TotalValue' => [
                'Code' => '',
                'Value' => 0,
            ],
            'TotalVolume' => [
                'Height' => $orderClass->package->height,
                'Length' => $orderClass->package->length,
                'Width' => $orderClass->package->weight,
                'Unit' => "CM",
            ],
            'WithBatteryType' => '', // NOBattery,WithBattery,Battery
            'Notes' => '',
            'BatchNo' => '',
            'WarehouseCode' => '',
            'TrackingNumber' => '',
            'ShippingMethod' => '',
            'ItemType' => 'SPX',
            'TradeType' => 'B2C',
            'IsMPS' => false,
            //'MPSType' => 'Normal', // Normal, FBA
            //'Cases' => $declareItems,
            'AllowRemoteArea' => true,
            'AutoConfirm' => true,
            'ShipperInfo' => $shipper,
        ];

        return array_merge($order, $shipper);
    }
}
