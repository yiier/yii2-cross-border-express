<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/9/7 9:31 下午
 * description:
 */

namespace yiier\crossBorderExpress;

use InvalidArgumentException;

class Factory
{
    protected $config;

    /**
     * Factory constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
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
        $class = static::formatClassName($this->config['provider']);
        if (!class_exists($class)) {
            throw new InvalidArgumentException('Class is not exist!');
        }
        return call_user_func([new $class($this->config), $name], ...$arguments);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    public static function formatClassName($name)
    {
        $name = \ucfirst(\str_replace(['-', '_', ''], '', $name));
        return __NAMESPACE__ . "\\platforms\\{$name}Platform";
    }
}