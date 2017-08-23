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
class Sitemap extends GeniBaseClass
{
    const SITEMAP_NS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    protected $sitemap = array();

    /**
     * Add new page URL to sitemap.
     *
     * @param string $url URL of the page.
     * @param \DateTime|integer|string $lastmod The date of last modification of the page.
     * @param string $changefreq How frequently the page is likely to change.
     *      Valid values are only: always, hourly, daily, weekly, monthly, yearly, never
     * @param float $priority The priority of this URL relative to other URLs on site.
     *      Valid values range from 0.0 to 1.0.
     */
    public function addUrl($url, $lastmod = null, $changefreq = null, $priority = null)
    {
        $child = new SitemapUrl(array(
            'url' => $url,
            'lastmod' => $lastmod,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ));
        if ($child->getUrl()) {
            $this->sitemap[$child->getUrl()] = $child;
        }
    }

    /**
     * Merges given data with current object
     *
     * @param GeniBaseClass $data
     */
    public function embed(GeniBaseClass $data)
    {
        if ($data instanceof self) {
            $this->sitemap = array_merge($this->sitemap, $data->sitemap);
        }
    }

    /**
     * Initializes this class from an associative array
     *
     * @param array $o
     */
    public function initFromArray(array $o)
    {
        $this->sitemap = array();
        foreach ($o as $urlinfo) {
            $child = new SitemapUrl($urlinfo);
            if ($child->getUrl()) {
                $this->sitemap[$child->getUrl()] = $child;
            }
        }
    }

    /**
     * Returns the associative array for this class
     *
     * @return array
     */
    public function toArray()
    {
        $ar = parent::toArray();

        foreach ($this->sitemap as $url) {
            /** @var SitemapUrl $url */
            $ar[] = $url->toArray();
        }

        return $ar;
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Common\GeniBaseClass::setKnownChildElement()
     */
    protected function setKnownChildElement(\XMLReader $xml)
    {
        if (true === $happened = parent::setKnownChildElement($xml)) {
            return true;
        } elseif (($xml->localName == 'url') && ($xml->namespaceURI === self::SITEMAP_NS)) {
            $child = new SitemapUrl($xml);
            if ($child->getUrl()) {
                $this->sitemap[$child->getUrl()] = $child;
            }
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
        $writer->startElement('urlset');
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
        $writer->writeAttribute('xmlns', Sitemap::SITEMAP_NS);
    }

    /**
     * {@inheritDoc}
     * @see \GeniBase\Common\GeniBaseClass::writeXmlContents()
     */
    public function writeXmlContents(\XMLWriter $writer)
    {
        parent::writeXmlContents($writer);

        foreach ($this->sitemap as $url) {
            $writer->startElement('url');
            /** @var SitemapUrl $url */
            $url->writeXmlContents($writer);
            $writer->endElement();
        }
    }
}
