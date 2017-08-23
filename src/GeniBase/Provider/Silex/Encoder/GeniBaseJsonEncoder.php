<?php
/**
 * GeniBase — the content management system for genealogical websites.
 *
 * @package GeniBase
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 * @copyright Copyright (C) 2014-2017 Andrey Khrolenok
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 * @link https://github.com/Limych/GeniBase
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */
namespace GeniBase\Provider\Silex\Encoder;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\NormalizationAwareInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Gedcomx\Gedcomx;
use Gedcomx\Util\JsonMapper;
use Gedcomx\Util\XmlMapper;

/**
 *
 *
 * @package GeniBase
 * @subpackage Silex
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class GeniBaseJsonEncoder extends JsonEncoder implements NormalizationAwareInterface
{

    use GeniBaseEncoderTrait;
    use GeniBaseDecoderTrait;

    protected $options;

    public function __construct($options = array())
    {
        $this->options = array_merge(array(
            'json_encode_options' => 0,
            'json_decode_options' => 0,
        ), $options);
    }

    /**
     * Whether the specified content type is a known content type and therefore
     * does not need to be written to the entry attributes.
     *
     * @param string $contentType The content type.
     * @return boolean Whether the content type is "known".
     */
    public function isKnownContentType($contentType)
    {
        return in_array($contentType, array(
            Gedcomx::JSON_MEDIA_TYPE,
        ));
    }

    protected function resolveContext($context)
    {
        $context = array_merge($this->options, $context);
        if (! empty($context['pretty_print'])) {
            $context['json_encode_options'] |= JSON_PRETTY_PRINT;
        }
        return $context;
    }

    /**
     * Encodes PHP data to a JSON string.
     *
     * @param mixed  $data    Data to encode
     * @param string $format  Must be set to GeniBaseJsonEncoder::FORMAT
     * @param array  $context An optional set of options for the JSON encoder; see below
     *
     * The $context array is a simple key=>value array, with the following supported keys:
     *
     * json_encode_options: integer
     *      Specifies additional options as per documentation for json_encode. Only supported with PHP 5.4.0 and higher
     *
     * pretty_print: boolean
     *
     * @return string
     *
     * @throws UnexpectedValueException
     *
     * @see http://php.net/json_encode json_encode
     */
    public function encode($data, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        $json = $data->toJson($context['json_encode_options']);

        if (false != $callback = $context['jsonp_callback']) {
            // Make JSON-P envelope
            $json = "$callback($json);";
        }

        return $json;
    }

    /**
     * Decodes data.
     *
     * @param string $json    The encoded JSON string to decode
     * @param string $format  Must be set to GeniBaseJsonEncoder::FORMAT
     * @param array  $context An optional set of options for the JSON decoder; see below
     *
     * The $context array is a simple key=>value array, with the following supported keys:
     *
     * json_decode_options: integer
     *      Specifies additional options as per documentation for json_decode. Only supported with PHP 5.4.0 and higher
     *
     * @return mixed
     *
     * @throws UnexpectedValueException
     *
     * @see http://php.net/json_decode json_decode
     */
    public function decode($json, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        $data = json_decode($json, true, null, $context['json_decode_options']);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new UnexpectedValueException(json_last_error_msg(), json_last_error());
        }

        $resources = null;

        foreach ($data as $key => $value){
            if (JsonMapper::isKnownType($key)) {
                $class = XmlMapper::getClassName($key);
                $resources[] = new $class($value);
            }
        }

        return $data;
    }
}
