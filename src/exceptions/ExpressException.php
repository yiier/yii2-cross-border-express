<?php
/**
 * author     : forecho <caizhenghai@gmail.com>
 * createTime : 2019/5/25 11:24 AM
 * description:
 */

namespace yiier\crossBorderExpress\exceptions;

class ExpressException extends \Exception
{
    /**
     * @var array the error info provided by a PDO exception. This is the same as returned
     * by [PDO::errorInfo](https://secure.php.net/manual/en/pdo.errorinfo.php).
     */
    public $errorInfo = [];


    /**
     * Constructor.
     * @param string $message PDO error message
     * @param array $errorInfo PDO error info
     * @param int $code PDO error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($message, $errorInfo = [], $code = 0, \Exception $previous = null)
    {
        $this->errorInfo = $errorInfo;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Express Exception';
    }

    /**
     * @return string readable representation of exception
     */
    public function __toString()
    {
        return parent::__toString() . PHP_EOL
            . 'Additional Information:' . PHP_EOL . print_r($this->errorInfo, true);
    }
}