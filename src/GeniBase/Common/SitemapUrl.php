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

use Carbon\Carbon;

/**
 *
 * @author Limych
 */
class SitemapUrl extends GeniBaseClass
{
    /**
     * Change frequency values
     */
    const CHANGEFREQ_ALWAYS = 'always';
    const CHANGEFREQ_HOURLY = 'hourly';
    const CHANGEFREQ_DAILY = 'daily';
    const CHANGEFREQ_WEEKLY = 'weekly';
    const CHANGEFREQ_MONTHLY = 'monthly';
    const CHANGEFREQ_YEARLY = 'yearly';
    const CHANGEFREQ_NEVER = 'never';

    const PRIORITY_LOWEST = 0.0;
    const PRIORITY_HIGHEST = 1.0;
    const PRIORITY_DEFAULT = 0.5;

    /**
     * URL of the page.
     *
     * @var string
     */
    protected $url;

    /**
     * The date of last modification of the page.
     *
     * @var \DateTime
     */
    protected $lastmod;

    /**
     * How frequently the page is likely to change.
     *
     * Valid values are only: always, hourly, daily, weekly, monthly, yearly, never
     *
     * @var string
     */
    protected $changefreq;

    /**
     * The priority of this URL relative to other URLs on site.
     *
     * Valid values range from 0.0 to 1.0.
     *
     * @var float
     */
    protected $priority;

    /**
     * Get the URL of the page.
     *
     * @return string The URL of the page
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the URL of the page.
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        if (isset($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $this->url = $url;
        }
    }

    /**
     * Get the date of last modification of the page.
     *
     * @return \DateTime The date of last modification of the page
     */
    public function getLastmod()
    {
        return $this->lastmod;
    }

    /**
     * Get the date of last modification of the page.
     *
     * @param \DateTime|integer|string $lastmod The date of last modification of the page
     */
    public function setLastmod($lastmod)
    {
        if (! isset($lastmod)) {
            return;
        }

        $this->lastmod = $lastmod;
        if (! $this->lastmod instanceof \DateTime) {
            $this->lastmod = is_int($this->lastmod)
                ? Carbon::createFromTimestampUTC($this->lastmod)
                : Carbon::parse($this->lastmod);
        }
    }

    /**
     * @return string The $changefreq value
     */
    public function getChangefreq()
    {
        return $this->changefreq;
    }

    /**
     * @param string $changefreq
     *      Valid values are only: always, hourly, daily, weekly, monthly, yearly, never
     */
    public function setChangefreq($changefreq)
    {
        if (! isset($changefreq)) {
            return;
        }

        $changefreq = strtolower($changefreq);
        if (in_array($changefreq, array(
            self::CHANGEFREQ_ALWAYS,
            self::CHANGEFREQ_HOURLY,
            self::CHANGEFREQ_DAILY,
            self::CHANGEFREQ_WEEKLY,
            self::CHANGEFREQ_MONTHLY,
            self::CHANGEFREQ_YEARLY,
            self::CHANGEFREQ_NEVER,
        ))) {
            $this->changefreq = $changefreq;
        }
    }

    /**
     * @return float The $priority value
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param float $priority Valid values range from 0.0 to 1.0.
     */
    public function setPriority($priority)
    {
        if (! isset($priority)) {
            return;
        }

        $priority = floatval($priority);
        if (self::PRIORITY_LOWEST <= $priority && $priority <= self::PRIORITY_HIGHEST) {
            $this->priority = $priority;
        }
    }

    /**
     * Initializes this class from an associative array
     *
     * @param array $o
     */
    public function initFromArray(array $o)
    {
        $this->url = null;
        if (isset($o['url'])) {
            $this->setUrl($o['url']);
        }

        $this->lastmod = null;
        if (isset($o['lastmod'])) {
            $this->setLastmod($o['lastmod']);
        }

        $this->changefreq = null;
        if (isset($o['changefreq'])) {
            $this->setChangefreq($o['changefreq']);
        }

        $this->priority = null;
        if (isset($o['priority'])) {
            $this->setPriority($o['priority']);
        }
    }

    /**
     * Returns the associative array for this class
     *
     * @return array
     */
    public function toArray()
    {
        $urlinfo = array();
        if (isset($this->url)) {
            $urlinfo['url'] = $this->url;
        }
        if (isset($this->lastmod)) {
            $urlinfo['lastmod'] = $this->lastmod->getTimestamp();
        }
        if (isset($this->changefreq)) {
            $urlinfo['changefreq'] = $this->changefreq;
        }
        if (isset($this->priority)) {
            $urlinfo['priority'] = $this->priority;
        }

        return $urlinfo;
    }

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
        } elseif (($xml->localName == 'loc') && ($xml->namespaceURI === Sitemap::SITEMAP_NS)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->url = $child;
            $happened = true;
        } elseif (($xml->localName == 'lastmod') && ($xml->namespaceURI === Sitemap::SITEMAP_NS)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->lastmod = Carbon::parse($child);
            $happened = true;
        } elseif (($xml->localName == 'changefreq') && ($xml->namespaceURI === Sitemap::SITEMAP_NS)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->changefreq = $child;
            $happened = true;
        } elseif (($xml->localName == 'priority') && ($xml->namespaceURI === Sitemap::SITEMAP_NS)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->priority = floatval($child);
            $happened = true;
        }

        return $happened;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Common\GeniBaseClass::toXml()
     */
    public function toXml(\XMLWriter $writer, $includeNamespaces = true)
    {
        $writer->startElement('url');
        if ($includeNamespaces) {
            $this->writeXmlNamespaces($writer);
        }
        $this->writeXmlContents($writer);
        $writer->endElement();
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Common\GeniBaseClass::writeXmlNamespaces()
     */
    protected function writeXmlNamespaces(\XMLWriter $writer)
    {
        $writer->writeAttributeNs('xmlns', '', null, Sitemap::SITEMAP_NS);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Common\GeniBaseClass::writeXmlContents()
     */
    public function writeXmlContents(\XMLWriter $writer)
    {
        parent::writeXmlContents($writer);

        if ($this->url) {
            $writer->startElement('loc');
            $writer->text($this->url);
            $writer->endElement();
        }
        if ($this->lastmod) {
            $writer->startElement('lastmod');
            $writer->text($this->lastmod->format(\DateTime::W3C));
            $writer->endElement();
        }
        if ($this->changefreq) {
            $writer->startElement('changefreq');
            $writer->text($this->changefreq);
            $writer->endElement();
        }
        if ($this->priority) {
            $writer->startElement('priority');
            $writer->text(sprintf('%3.1f', $this->priority));
            $writer->endElement();
        }
    }
}
