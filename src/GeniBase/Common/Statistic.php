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
namespace GeniBase\Common;

/**
 *
 * @author Limych
 */
class Statistic extends GeniBaseClass
{
    protected $statistic = array();

    /**
     * Merges given data with current object
     *
     * @param GeniBaseClass $data
     */
    public function embed(GeniBaseClass $data)
    {
        if ($data instanceof Statistic) {
            $this->statistic = array_merge($this->statistic, $data->toArray());
        }
    }

    /**
     * Initializes this class from an associative array
     *
     * @param array $o
     */
    public function initFromArray(array $o)
    {
        $this->statistic = $o;
    }

    /**
     * Returns the associative array for this class
     *
     * @return array
     */
    public function toArray()
    {
        return $this->statistic;
    }

    protected $lastElement;

    /**
     * Sets a known child element of GeniBaseClass from an XML reader.
     *
     * @param  \XMLReader $xml The reader.
     * @return bool Whether a child element was set.
     */
    protected function setKnownChildElement(\XMLReader $xml)
    {
        if (true === $happened = parent::setKnownChildElement($xml)) {
            return true;
        } elseif (empty($xml->namespaceURI)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->statistic[$xml->localName] = $child;
            $this->lastElement = $xml->localName;
            $happened = true;
        }

        return $happened;
    }

    /**
     * Sets a known attribute of GeniBaseClass from an XML reader.
     *
     * @param  \XMLReader $xml The reader.
     * @return bool Whether an attribute was set.
     */
    protected function setKnownAttribute(\XMLReader $xml)
    {
        if (true === $happened = parent::setKnownChildElement($xml)) {
            return true;
        } elseif (($xml->localName == 'modified') && empty($xml->namespaceURI)) {
            $this->statistic[$this->lastElement . '_modified'] = $xml->value;
            $happened = true;
        }

        return $happened;
    }

    /**
     * Writes this GeniBaseClass to an XML writer.
     *
     * @param \XMLWriter $writer            The XML writer.
     * @param bool       $includeNamespaces Whether to write out the namespaces in the element.
     */
    public function toXml(\XMLWriter $writer, $includeNamespaces = true)
    {
        $writer->startElementNS('gb', 'statistic', self::GENIBASE_NS);
        $this->writeXmlContents($writer);
        $writer->endElement();
    }

    /**
     * Writes the contents of this GeniBaseClass to an XML writer.
     * The startElement is expected to be already provided.
     *
     * @param \XMLWriter $writer The XML writer.
     */
    public function writeXmlContents(\XMLWriter $writer)
    {
        parent::writeXmlContents($writer);

        foreach ($this->statistic as $k => $v) {
            if (! preg_match('/_modified$/', $k)) {
                $writer->startElement($k);
                if (! empty($this->statistic[$k . '_modified'])) {
                    $date = new \DateTime($this->statistic[$k . '_modified']);
                    $writer->writeAttribute('modified', $date->format(\DateTime::W3C));
                }
                $writer->text($v);
                $writer->endElement();
            }
        }
    }
}
