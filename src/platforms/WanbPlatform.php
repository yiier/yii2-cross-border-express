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
     * @var string $shippingMethod
     */
    private $shippingMethod = "3HPA";

    /**
     * @var string $warehouseCode
     */
    private $warehouseCode = "SZ";

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

        $this->shippingMethod = $this->config->get("shipping_method");
        $this->warehouseCode = $this->config->get("warehouse_code");

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
        $result = $this->client->post($this->host . "/api/parcels", [
            'body' => json_encode($parameter, true)
        ])->getBody();
        var_dump($this->parseResult($result));
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
     * @param string $result
     * @return array
     * @throws ExpressException
     */
    protected function parseResult(string $result): array
    {
        $arr = json_decode($result, true);
        if (empty($arr) || !isset($arr['Succeeded'])) {
            throw new ExpressException('Invalid response: ' . $result, 400);
        }
        if ($arr["Succeeded"] != true) {
            $message = json_encode($arr, true);
            throw new ExpressException($message);
        }
        return $arr;
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
        $items = [];
        foreach ($orderClass->goods as $good) {
            $declareItems[] = [
                'name' => $good->description,
                'cnName' => $good->cnDescription,
                'pieces' => $good->quantity,
                'netWeight' => $good->weight,
                'unitPrice' => $good->worth,
                'customsNo' => $good->hsCode,
            ];
            $items[] = [
                "GoodsId" => "",
                "GoodsTitle" => $good->description,
                "DeclaredNameEn" => $good->description,
                "DeclaredNameCn" => "$good->cnDescription",
                "DeclaredValue" => [
                    "Code" => "USD",
                    "Value" => $good->worth
                ],
                "WeightInKg" => $good->weight,
                "Quantity" => $good->quantity,
                "HSCode" => $good->hsCode,
                "CaseCode" => "",
                "SalesUrl" => "",
                "IsSensitive" => false,
                "Brand" => "",
                "Model" => "",
                "MaterialCn" => $good->cnMaterial,
                "MaterialEn" => $good->enMaterial,
                "UsageCn" => "",
                "UsageEn" => "",
            ];
        }

        return [
            'ReferenceId' => $orderClass->customerOrderNo,
            'ShippingAddress' => [
                "Company" => $orderClass->recipient->company,
                "Street1" => $orderClass->recipient->address,
                "Street2" => $orderClass->recipient->doorplate,
                "City" => $orderClass->recipient->city,
                "Province" => $orderClass->recipient->state,
                "CountryCode" => $orderClass->recipient->countryCode,
                "Postcode" => $orderClass->recipient->zip,
                "Contacter" => $orderClass->recipient->name,
                "Tel" => $orderClass->recipient->phone,
                "Email" => $orderClass->recipient->email,
            ],
            'WeightInKg' => $orderClass->package->weight,
            'ItemDetails' => $items,
            'TotalValue' => [
                "Code" => "USD",
                "Value" => $orderClass->package->declareWorth,
            ],
            'TotalVolume' => [
                'Height' => $orderClass->package->height,
                'Length' => $orderClass->package->length,
                'Width' => $orderClass->package->weight,
                'Unit' => "CM",
            ],
            'WithBatteryType' => $orderClass->withBattery == 1 ? "WithBattery" : "NOBattery", // NOBattery,WithBattery,Battery
            'Notes' => $orderClass->package->description,
            'WarehouseCode' => $this->warehouseCode,
            'ShippingMethod' => $this->shippingMethod,
            'ItemType' => 'SPX',
            'TradeType' => 'B2C',
            'IsMPS' => false,
            'AllowRemoteArea' => true,
            'AutoConfirm' => true,
            'ShipperInfo' => $shipper,
        ];
    }
}
