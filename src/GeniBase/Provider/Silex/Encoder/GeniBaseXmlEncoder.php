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

use Gedcomx\Gedcomx;
use Gedcomx\Util\XmlMapper;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

/**
 *
 *
 * @package GeniBase
 * @subpackage Silex
 * @author Andrey Khrolenok <andrey@khrolenok.ru>
 */
class GeniBaseXmlEncoder extends XmlEncoder
{

    use GeniBaseEncoderTrait;
    use GeniBaseDecoderTrait;

    protected $options;

    public function __construct($options = array())
    {
        $this->options = array_merge(array(
            'xml_decode_options' => LIBXML_NONET | LIBXML_NOBLANKS,
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
            Gedcomx::XML_MEDIA_TYPE,
        ));
    }

    protected function resolveContext($context)
    {
        $context = array_merge($this->options, $context);
        return $context;
    }

    /**
     * Encodes PHP data to a XML.
     *
     * @param mixed  $resource    Data to encode
     * @param string $format  Must be set to GeniBaseXmlEncoder::FORMAT
     * @param array  $context An optional set of options for the XML encoder; see below
     *
     * The $context array is a simple key=>value array, with the following supported keys:
     *
     * pretty_print: boolean
     *
     * @return string
     */
    public function encode($resource, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        $xml = new \XMLWriter();
        $xml->openMemory();
        if (! empty($context['pretty_print'])) {
            $xml->setIndent(true);
            $xml->setIndentString('    ');
        }
        $xml->startDocument('1.0', 'UTF-8');
        $resource->toXml($xml);
        $xml->endDocument();

        return $xml->outputMemory(true);
    }

    /**
     * Decodes data.
     *
     * @param string $json    The XML string to decode
     * @param string $format  Must be set to GeniBaseXmlEncoder::FORMAT
     * @param array  $context An optional set of options for the XML decoder
     *
     * @return mixed
     */
    public function decode($incoming, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        $resources = null;

        $reader = new \XMLReader();
        $reader->xml($incoming);
        $reader->read();
        do {
            if ($reader->nodeType == \XMLReader::ELEMENT && XmlMapper::isKnownType($reader->name)) {
                $class = XmlMapper::getClassName($reader->name);
                $resources[] = new $class($reader);
            }
        } while ($reader->read());

        return $resources;
    }
}
