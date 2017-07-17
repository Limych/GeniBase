<?php
namespace GeniBase\Common;

/**
 *
 * @author Limych
 *
 */
class GeniBaseClass
{
    const GENIBASE_NS = 'http://genibase/v1/';
    const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    /**
     * Constructs a GeniBaseClass from a (parsed) JSON hash
     *
     * @param mixed $o Either an array (JSON), an XMLReader or
     *          another instance of GeniBaseClass or a subclass.
     *
     * @throws \Exception
     */
    public function __construct($o = null)
    {
        if ($o instanceof self) {
            $o = $o->toArray();
        }

        if (is_array($o)) {
            $this->initFromArray($o);

        } elseif ($o instanceof \XMLReader) {
            $success = true;
            while ($success && $o->nodeType != \XMLReader::ELEMENT) {
                $success = $o->read();
            }
            if ($o->nodeType != \XMLReader::ELEMENT) {
                throw new \Exception("Unable to read XML: no start element found.");
            }

            $this->initFromReader($o);
        }
    }

    /**
     * Merges given data with current object
     *
     * @param GeniBaseClass $data
     */
    public function embed(GeniBaseClass $data)
    {}  // Empty

    /**
     * Initializes this GeniBaseClass from an associative array
     *
     * @param array $o
     */
    public function initFromArray(array $o)
    {}  // Empty

    /**
     * Returns the associative array for this GeniBaseClass
     *
     * @return array
     */
    public function toArray()
    {
        return [];
    }

    /**
     * Returns the JSON string for this GeniBaseClass
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Initializes this GeniBaseClass from an XML reader.
     *
     * @param \XMLReader $xml The reader to use to initialize this object.
     */
    public function initFromReader(\XMLReader $xml)
    {
        $empty = $xml->isEmptyElement;

        if ($xml->hasAttributes) {
            $moreAttributes = $xml->moveToFirstAttribute();
            while ($moreAttributes) {
                if (!$this->setKnownAttribute($xml)) {
                    //skip unknown attributes...
                }
                $moreAttributes = $xml->moveToNextAttribute();
            }
        }

        if (!$empty) {
            $xml->read();
            while ($xml->nodeType != \XMLReader::END_ELEMENT) {
                if ($xml->nodeType != \XMLReader::ELEMENT) {
                    //no-op: skip any insignificant whitespace, comments, etc.
                }
                else if (!$this->setKnownChildElement($xml)) {
                    $n = $xml->localName;
                    $ns = $xml->namespaceURI;
                    $elementIsEmpty = $xml->isEmptyElement;
                    $dom = new \DOMDocument();
                    $nodeFactory = $dom;
                    $dom->formatOutput = true;

                    $e = $nodeFactory->createElementNS($xml->namespaceURI, $xml->localName);
                    $dom->appendChild($e);
                    if ($xml->hasAttributes) {
                        $moreAttributes = $xml->moveToFirstAttribute();
                        while ($moreAttributes) {
                            $e->setAttributeNS($ns, $xml->localName, $xml->value);
                            $moreAttributes = $xml->moveToNextAttribute();
                        }
                    }
                    $dom = $e;
                    if (!$elementIsEmpty) {
                        //create any child elements...
                        while ($xml->read() && $xml->nodeType != \XMLReader::END_ELEMENT && $xml->localName != $n) {
                            if ($xml->nodeType == \XMLReader::ELEMENT) {
                                $e = $nodeFactory->createElementNS($xml->namespaceURI, $xml->localName);
                                $dom->appendChild($e);
                                if ($xml->hasAttributes) {
                                    $moreAttributes = $xml->moveToFirstAttribute();
                                    while ($moreAttributes) {
                                        $e->setAttributeNS($xml->namespaceURI, $xml->localName, $xml->value);
                                        $moreAttributes = $xml->moveToNextAttribute();
                                    }
                                }
                            } else if ($xml->nodeType == \XMLReader::TEXT) {
                                $dom->textContent = $xml->value;
                            } else if ($xml->nodeType == \XMLReader::END_ELEMENT) {
                                $dom = $dom->parentNode;
                            }
                        }
                    }
                    array_push($this->extensionElements, $dom);
                }
                $xml->read(); //advance the reader.
            }
        }
    }

    /**
     * Sets a known child element of GeniBaseClass from an XML reader.
     *
     * @param \XMLReader $xml The reader.
     * @return bool Whether a child element was set.
     */
    protected function setKnownChildElement(\XMLReader $xml) {
        return false;
    }

    /**
     * Sets a known attribute of GeniBaseClass from an XML reader.
     *
     * @param \XMLReader $xml The reader.
     * @return bool Whether an attribute was set.
     */
    protected function setKnownAttribute(\XMLReader $xml) {
        return false;
    }

    /**
     * Writes this GeniBaseClass to an XML writer.
     *
     * @param \XMLWriter $writer The XML writer.
     * @param bool $includeNamespaces Whether to write out the namespaces in the element.
     */
    public function toXml(\XMLWriter $writer, $includeNamespaces = true)
    {
        $writer->startElement('xml');
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
    {}  // Empty

}