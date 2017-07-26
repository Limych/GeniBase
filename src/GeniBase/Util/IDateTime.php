<?php
namespace GeniBase\Util;

use DateTime;

/**
 *
 * @author Limych
 *
 */
class IDateTime extends DateTime
{

    const SQL = 'Y-m-d H:i:s';

    protected $formatters = [];

    /**
     * Register new formatter for datetime.
     *
     * @param string $key Formatter key.
     * @param callable $callback Callback for formatter.
     */
    public function addFormatter($key, callable $callback)
    {
        $this->formatters[$key] = $callback;
    }

    /**
     * {@inheritDoc}
     * @see DateTime::format()
     */
    public function format($format)
    {
        $regex = '/(?<!\\\\)('.implode('|', array_map('preg_quote', array_keys($this->formatters))).')/u';
        $format = preg_split($regex, $format, null, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        for ($i = 0; $i < count($format); $i++) {
            if (! empty($format[$i])) {
                $result .= parent::format($format[$i]);
            }
            if (! empty($key = $format[++$i]) && ! empty($this->formatters[$key])) {
                $result .= call_user_func($this->formatters[$key], $this);
            }
        }

        return $result;
    }
}
