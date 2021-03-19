<?php
/**
 * 
 * User: andy 
 * Email: uuus007@gmail.com
 * Date: 2020/12/17
 * Time: 00:02
 * File: K5Platform.php
 */

namespace yiier\crossBorderExpress\platforms;

use GuzzleHttp\Client;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\OrderFee;
use yiier\crossBorderExpress\contracts\OrderResult;
use yiier\crossBorderExpress\contracts\Transport;
use yiier\crossBorderExpress\exceptions\ExpressException;

class K5Platform extends Platform
{

    /**
     * default host
     */
    const HOST = 'http://k5.kingtrans.cn';

    /**
     * @var string
     */
    private $host = '';

    /**
     * 订单类型 1：快件订单 2：快递制单-非实时返回单号3：仓储订单
     * 4：快递制单-实时返回单号(等待时间较长)。
     * 选择 4 后，后续如需调用其他方法，例如调用删除订单，其他方法的OrderType 请选择 2
     * @var int
     */
    private $OrderType = '1';
    

     /**
      * 支付方式 [ PP:预付,CC:到付, TP:第三方 ]
      * @var string
      */
    private $FeePayType = 'PP';
    
    /**
     * @inheritDoc
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
        $this->host = $this->config->get("host");
        if ($this->host == "") {
            throw new ExpressException("物流代理商host不能为空");
        }

        if ($this->config->get("clientid") == "") {
            throw new ExpressException("clientid不能为空");
        }

        if ($this->config->get("token") == "") {
            throw new ExpressException("token不能为空");
        }
        if ($this->config->get("orderType") != "") {
            $this->OrderType = $this->config->get("orderType");
        }
        if ($this->config->get("feePayType") != "") {
            $this->FeePayType = $this->config->get("feePayType");
        }
        return $client;
    }

    /**
     * @param string $countryCode
     * @return array|void|Transport[]
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

        $parameter = $this->formatOrder($order);
        try {
            $result = $this->client->post($this->host . "/PostInterfaceService?method=createOrder", [
                'body' => json_encode($parameter, true)
            ])->getBody();

            return $this->parseResult($result);
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("创建快件订单失败: %s", $exception->getMessage()));
        }
    }

    /**
     * @inheritDoc
     * @throws \OSS\Core\OssException
     */
    public function getPrintUrl(string $orderNumber, array $params = []): string
    {
        
        return $this->getPrintFile($orderNumber,$params['channelCode']);
    }

    /**
     * @param string $orderNumber
     * @return string
     * @throws \OSS\Core\OssException
     */
    protected function getPrintFile(string $orderNumber,$channelCode): string
    {

        $PrintPaper = $this->searchPrintPaper($channelCode);


        $params = [
            'OrderType'=>$this->OrderType,
            'Verify'=>$this->getVerifyData(),
            'CorpBillidDatas'=>[['CorpBillid'=>$orderNumber]],
            'OrderType'=>$this->OrderType,
            'PrintPaper'=>$PrintPaper,
            'PrintContent'=>1
        ];
        
        try {
            $result = $this->client->post($this->host . "/PostInterfaceService?method=printOrderLabel", [
                'body' => json_encode($params, true)
            ])->getBody();
            $orderInvoice = json_decode($result,true);

            if($orderInvoice['statusCode'] === 'success') {
                return $orderInvoice['url'];
            }else{
                throw new ExpressException(sprintf("创建包裹失败: %s", $orderInvoice['message']));
            }
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("创建包裹失败: %s", $exception->getMessage()));
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
     * 查询渠道纸张代码
     *
     * @param [type] $channelCode
     * @return void
     */
    public function searchPrintPaper($channelCode)
    {
        if(!$channelCode){
            throw new ExpressException("打印面单失败: 渠道码错误-".$channelCode);  
        }
        $params = [
            'Verify'=>$this->getVerifyData(),
            'ChannelCode'=>$channelCode
        ];
        
        try {
            $result = $this->client->post($this->host . "/PostInterfaceService?method=searchPrintPaper", [
                'body' => json_encode($params, true)
            ])->getBody();
            $PrintPaper = json_decode($result,true);
            if($PrintPaper['statusCode'] === 'success') {
                return $PrintPaper['returnDatas'][0]['paperCode'];
            }else{
                throw new ExpressException(sprintf("打印面单失败: %s", $PrintPaper['message']));
            }
            //echo '<pre>';var_dump($PrintPaper);exit;
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("打印面单失败: %s", $exception->getMessage()));
        }

        return "";
    }

    /**
     * 验证信息
     *
     * @return array
     */
    private function getVerifyData(): array
    {
        return [
            'Clientid'=>$this->config->get("clientid"),
            'Token'=>$this->config->get("token"),
        ];

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
        $orderResult->expressAgentNumber = !empty($resData["customerNumber"]) ? $resData["customerNumber"] : "";
        $orderResult->expressNumber = !empty($resData["corpBillid"]) ? $resData["corpBillid"] : "";
        $orderResult->expressTrackingNumber = !empty($resData["trackNumber"]) ? $resData["trackNumber"] : $this->getTracingNumber($resData["corpBillid"]);
        return $orderResult;
    }

    /**
     * @param string $processCode
     * @return string
     * @throws ExpressException
     */
    protected function getTracingNumber(string $processCode): string
    {

        $params = [
            'OrderType'=>$this->OrderType,
            'Verify'=>$this->getVerifyData(),
            'CorpBillidDatas'=>[['CorpBillid'=>$processCode]]
        ];
        
        try {
            $result = $this->client->post($this->host . "/PostInterfaceService?method=searchOrderTracknumber", [
                'body' => json_encode($params, true)
            ])->getBody();
            $orderTrackNumber = json_decode($result,true);
            return $orderTrackNumber[0]['trackNumber']?$orderTrackNumber[0]['trackNumber']:'';
        } catch (ExpressException $exception) {
            throw new ExpressException(sprintf("创建包裹失败: %s", $exception->getMessage()));
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
        if (empty($arr) || !isset($arr['statusCode']) || $arr["statusCode"] !== 'success') {
            throw new ExpressException('Invalid response: ' . $result, 400);
        }
        
        if (isset($arr["returnDatas"][0]['statusCode']) && $arr["returnDatas"][0]['statusCode'] === 'error') {
            throw new ExpressException($arr["returnDatas"][0]['message']);
        }
       
        return isset($arr["returnDatas"][0]) ? $arr["returnDatas"][0] : [];
    }

    /**
     * 格式化所需要的数据
     *
     * @param Order $orderClass
     * @return array
     */
    protected function formatOrder(Order $orderClass): array
    {
        
        $items = [];
        foreach ($orderClass->goods as $good) {
            $items[] = [
                'Sku'=>is_null($good->sku)?'':$good->sku, // 产品 Sku (OrderType 为仓储订单必传)
                'Cnname'=>$good->cnDescription, //  产品中文名
                'Enname'=>$good->description, // 产品英文名
                'Price'=>$good->worth, // 单价
                'SingleWeight'=>$good->weight, // 单件重量
                'Num'=>$good->quantity, // 数量
            ];
        }

        return [
            'Verify' => $this->getVerifyData(),
            'OrderType'=>$this->OrderType,
            'OrderDatas'=>[[
                'CustomerNumber'=>$orderClass->customerOrderNo, // 客户订单号(可传入贵公司内部单号)
                'ChannelCode'=>$orderClass->transportCode, // 渠道代码可调用[searchStartChannel]方法获取
                'CountryCode'=>$orderClass->recipient->countryCode, // 国家二字代码
                'TotalWeight'=>$orderClass->package->weight, // 订单总重量
                'TotalValue'=>$orderClass->package->declareWorth, // 订单总申报价值
                'Number'=>$orderClass->package->quantity, // 件数
                'Recipient'=>[
                    'Name'=>is_null($orderClass->recipient->name)?'':$orderClass->recipient->name, // 名称
                    'Company'=>is_null($orderClass->recipient->company)?'':$orderClass->recipient->company,
                    'Addres1'=>is_null($orderClass->recipient->address)?'':$orderClass->recipient->address, // 地址1
                    'Addres2'=>is_null($orderClass->recipient->doorplate)?'':$orderClass->recipient->doorplate, // 地址2
                    'Tel'=> $orderClass->recipient->phone, // 电话
                    'Province'=>$orderClass->recipient->state, // 省州
                    'City'=>$orderClass->recipient->city, // 城市
                    'Post'=>$orderClass->recipient->zip, // 邮编
                    'Email'=>$orderClass->recipient->email
                ],
                'Sender'=>[
                    'Name'=>$orderClass->shipper->name, // 名称
                    'Company'=>is_null($orderClass->shipper->company)?'':$orderClass->shipper->company,
                    'Addres'=>is_null($orderClass->shipper->address)?'':$orderClass->shipper->address, // 地址
                    'Country'=> $orderClass->shipper->countryCode, // 国家
                    'Mobile'=> $orderClass->shipper->phone, // 电话
                    'Tel'=> $orderClass->shipper->phone, // 电话
                    'Province'=>$orderClass->shipper->state, // 省州
                    'City'=>$orderClass->shipper->city, // 城市
                    'Post'=>$orderClass->shipper->zip, // 邮编
                    'Email'=>$orderClass->shipper->email
                ],
               
                'OrderItems'=>$items, // 订单明细产品信息
    
                'FeePayData'=>[
                    'FeePayType'=>$this->FeePayType, // 支付方式[ PP:预付,CC:到付, TP:第三方]必传
                ],
                
    
            ]],
            
            


            

        
          
        ];
    }

    /**
     * 获取启用得入仓渠道
     * 
     * 
     */
    public function searchStartChannel()
    {
        
         try {
             $result = $this->client->post($this->host . "/PostInterfaceService?method=searchStartChannel", [
                 'body' => json_encode(['Verify' => $this->getVerifyData()], true)
             ])->getBody();
             return json_decode($result,true);
         } catch (ExpressException $exception) {
             throw new ExpressException(sprintf("创建包裹失败: %s", $exception->getMessage()));
         }
    }
}
