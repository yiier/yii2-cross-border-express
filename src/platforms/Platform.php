<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/19 11:02 AM
 * description:
 */

namespace yiier\crossBorderExpress\platforms;

use yiier\crossBorderExpress\Config;
use yiier\crossBorderExpress\contracts\PlatformInterface;

abstract class Platform implements PlatformInterface
{
    const DEFAULT_TIMEOUT = 5.0;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var float
     */
    protected $timeout;

    /**
     * @var string
     */
    protected $client;

    /**
     * Gateway constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $this->client = $this->getClient();
    }

    /**
     * Return timeout.
     *
     * @return int|mixed
     */
    public function getTimeout()
    {
        return $this->timeout ?: $this->config->get('timeout', self::DEFAULT_TIMEOUT);
    }

    /**
     * Set timeout.
     *
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = floatval($timeout);
        return $this;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Config $config
     *
     * @return $this
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return \strtolower(str_replace([__NAMESPACE__ . '\\', 'Platform'], '', \get_class($this)));
    }

    public static function dataGet($array, $key, $default = null)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        return $default;
    }
}