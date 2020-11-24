<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/23 12:26 PM
 * description:
 */

namespace yiier\crossBorderExpress;

use InvalidArgumentException;
use yiier\crossBorderExpress\contracts\Order;
use yiier\crossBorderExpress\contracts\PlatformInterface;

/**
 * Class Express
 * @method createOrder(Order $order): OrderResult
 * @method getPrintUrl(string $orderNumber, array $params = []): string
 * @method getTransportsByCountryCode(string $countryCode): array
 * @method getOrderFee(string $trackingNumber): OrderFee
 * @method getOrderAllFee(array $query = []): OrderFee[]
 * @package yiier\crossBorderExpress
 */
class Express
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var array
     */
    protected $platforms = [];

    /**
     * @var string
     */
    protected $platformName;

    /**
     * Constructor.
     *
     * @param array $config
     * @param string $platformName
     */
    public function __construct(array $config, string $platformName)
    {
        $this->config = new Config($config);
        $this->platformName = $platformName;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function make($name, $arguments)
    {
        $platform = $this->platform($this->platformName);
        return call_user_func([$platform, $name], ...$arguments);
    }

    /**
     * Create a platform.
     * @param string $name
     * @return PlatformInterface
     * @throws InvalidArgumentException
     */
    public function platform($name)
    {
        if (!isset($this->platforms[$name])) {
            $this->platforms[$name] = $this->createPlatform($name);
        }
        return $this->platforms[$name];
    }


    /**
     * Create a new driver instance.
     * @param string $name
     * @return PlatformInterface
     * @throws InvalidArgumentException
     */
    protected function createPlatform($name)
    {
        $className = $this->formatPlatformClassName($name);
        $platform = $this->makePlatform($className, $this->getPlatformSettings($name));

        if (!($platform instanceof PlatformInterface)) {
            throw new InvalidArgumentException(
                \sprintf('Platform "%s" must implement interface %s.', $name, PlatformInterface::class)
            );
        }
        return $platform;
    }

    /**
     * Format platform name.
     * @param string $name
     * @return string
     */
    protected function formatPlatformClassName($name)
    {
        if (\class_exists($name) && \in_array(PlatformInterface::class, \class_implements($name))) {
            return $name;
        }
        $name = \ucfirst(\str_replace(['-', '_', ''], '', $name));
        return __NAMESPACE__ . "\\platforms\\{$name}Platform";
    }


    /**
     * Make Platform instance.
     * @param string $platform
     * @param array $config
     * @return PlatformInterface
     * @throws InvalidArgumentException
     */
    protected function makePlatform($platform, $config)
    {
        if (!\class_exists($platform) || !\in_array(PlatformInterface::class, \class_implements($platform))) {
            throw new InvalidArgumentException(\sprintf('Class "%s" is a invalid express platform.', $platform));
        }
        return new $platform($config);
    }

    /**
     * @param string $platform
     * @return array
     */
    protected function getPlatformSettings(string $platform)
    {
        $globalSettings = [
            'timeout' => $this->config->get('timeout'),
        ];
        $settings = array_merge($globalSettings, $this->config->get("platforms.{$platform}", []));
        return $settings;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->make($name, $arguments);
    }
}
