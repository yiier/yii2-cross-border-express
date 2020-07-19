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
use yiier\AliyunOSS\OSS;
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
        return [];
    }

    /**
     * @param Order $order
     * @return OrderResult
     * @throws ExpressException
     */
    public function createOrder(Order $order): OrderResult
    {
        $parameter = $this->formatOrder($order);
        try {
            $result = $this->client->post($this->host . "/api/parcels", [
                'body' => json_encode($parameter, true)
            ])->getBody();
            return $this->parseResult($result);
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("创建包裹失败: %s", $exception->getMessage()));
        }
    }

    /**
     * @inheritDoc
     * @throws \OSS\Core\OssException
     */
    public function getPrintUrl(string $orderNumber): string
    {
        return $this->getPrintFile($orderNumber);
    }

    /**
     * @param string $orderNumber
     * @return string
     * @throws \OSS\Core\OssException
     */
    protected function getPrintFile(string $orderNumber): string
    {
        $fileName = sprintf("%s.pdf", $orderNumber);
        $filePath = "./" . $fileName;
        $url = sprintf("%s/api/parcels/%s/label", $this->host, $orderNumber);
        $this->client->get($url, [
            "save_to" => $filePath
        ]);
        // PDF传到阿里云oss
        $oss = new OSS([
            "accessKeyId" => $this->config->get("oss_access_key_id"),
            "bucket" => $this->config->get("oss_bucket"),
            "accessKeySecret" => $this->config->get("oss_access_key_secret"),
            "lanDomain" => $this->config->get("oss_lan_domain"),
            "wanDomain" => $this->config->get("oss_wan_domain"),
            "isInternal" => false,
        ]);

        $storagePath = 'storage/express/';
        if ($oss->has($storagePath . $fileName)) {
            return sprintf("http://%s.%s/%s", $this->config->get("oss_bucket"), $this->config->get("oss_wan_domain"), $storagePath . $fileName);
        }

        if (!$oss->has($storagePath)) {
            $oss->createDir($storagePath);
        }

        if ($res = $oss->upload($storagePath . $fileName, $filePath)) {
            return sprintf("http://%s/%s", $res["oss-requestheaders"]["Host"], $storagePath . $fileName);
        }
        return "";
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
     * @inheritDoc
     */
    public function getOrderAllFee(array $query = []): array
    {
        return [];
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
     * @return OrderResult
     * @throws ExpressException
     */
    protected function parseResult(string $result): OrderResult
    {
        $resData = $this->parseExpress($result);

        $orderResult = new OrderResult();
        $orderResult->data = $result;
        $orderResult->expressNumber = !empty($resData["ProcessCode"]) ? $resData["ProcessCode"] : "";
        $orderResult->expressTrackingNumber = !empty($resData["TrackingNumber"]) ? $resData["TrackingNumber"] : $this->getTracingNumber($resData["ProcessCode"]);
        return $orderResult;
    }

    /**
     * @param string $processCode
     * @return string
     * @throws ExpressException
     */
    protected function getTracingNumber(string $processCode): string
    {
        $url = $this->host . sprintf("/api/parcels/%s/confirmation", $processCode);
        $result = $this->client->post($url)->getBody();
        try {
            $res = $this->parseExpress($result);
            if (!empty($res["TrackingNumber"])) {
                return $res["TrackingNumber"];
            }
        } catch (ExpressException $e) {
            throw new ExpressException(sprintf("确认交运行包裹失败 %s", $e->getMessage()));
        }

        $url = $this->host . sprintf("/api/parcels/%s", $processCode);
        try {
            $res = $this->parseExpress($this->client->get($url)->getBody());
            if (!empty($res["FinalTrackingNumber"])) {
                return $res["FinalTrackingNumber"];
            }
        } catch (ExpressException $e) {
            throw new ExpressException(sprintf("获取包裹失败 %s", $e->getMessage()));
        }

        return "";
    }

    /**
     * @param string $result
     * @return array
     * @throws ExpressException
     */
    protected function parseExpress(string $result): array
    {
        $arr = json_decode($result, true);
        if (empty($arr) || !isset($arr['Succeeded'])) {
            throw new ExpressException('Invalid response: ' . $result, 400);
        }
        if ($arr["Succeeded"] != true) {
            $message = json_encode($arr, true);
            throw new ExpressException($message);
        }
        return !empty($arr["Data"]) ? $arr["Data"] : [];
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
