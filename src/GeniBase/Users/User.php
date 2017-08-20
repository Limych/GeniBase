<?php
namespace GeniBase\Users;

use Gedcomx\Links\HypermediaEnabledData;
use GeniBase\Common\GeniBaseClass;

/**
 *
 * @author Limych
 *
 */
class User extends HypermediaEnabledData
{

    protected $agentId;

    protected $preferredLanguage;

    protected $email;

    protected $displayName;

    /**
     * Constructs a User from a (parsed) JSON hash
     *
     * @param mixed $opt
     *            Either an array (JSON) or an XMLReader.
     *
     * @throws \Exception
     */
    public function __construct($opt = null)
    {
        if (is_array($opt)) {
            $this->initFromArray($opt);
        } elseif ($opt instanceof \XMLReader) {
            $success = true;
            while ($success && $opt->nodeType != \XMLReader::ELEMENT) {
                $success = $opt->read();
            }
            if ($opt->nodeType != \XMLReader::ELEMENT) {
                throw new \Exception("Unable to read XML: no start element found.");
            }

            $this->initFromReader($opt);
        }
    }

    /**
     *
     * @return string
     */
    public function getAgentId()
    {
        return $this->agentId;
    }

    /**
     *
     * @param string $agentId
     */
    public function setAgentId($agentId)
    {
        $this->agentId = $agentId;
    }

    /**
     *
     * @return string
     */
    public function getPreferredLanguage()
    {
        return $this->preferredLanguage;
    }

    /**
     *
     * @param string $preferredLanguage
     */
    public function setPreferredLanguage($preferredLanguage)
    {
        $this->preferredLanguage = $preferredLanguage;
    }

    /**
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     *
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     *
     * @return string $displayName
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     *
     * @param string $displayName
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
    }

    /**
     * Initializes this User from an associative array
     *
     * @param array $opt
     */
    public function initFromArray(array $opt)
    {
        if (isset($opt['displayName'])) {
            $this->displayName = $opt['displayName'];
            unset($opt['displayName']);
        }
        if (isset($opt['email'])) {
            $this->email = $opt['email'];
            unset($opt['email']);
        }
        if (isset($opt['agentId'])) {
            $this->agentId = $opt['agentId'];
            unset($opt['agentId']);
        }
    }

    /**
     * Returns the associative array for this User
     *
     * @return array
     */
    public function toArray()
    {
        $res = parent::toArray();

        if ($this->agentId) {
            $res['agentId'] = $this->agentId;
        }
        if ($this->displayName) {
            $res['displayName'] = $this->displayName;
        }
        if ($this->email) {
            $res['email'] = $this->email;
        }

        return $res;
    }

    /**
     * Sets a known child element of User from an XML reader.
     *
     * @param \XMLReader $xml
     *            The reader.
     *
     * @return bool Whether a child element was set.
     */
    protected function setKnownChildElement(\XMLReader $xml)
    {
        $happened = parent::setKnownChildElement($xml);
        if ($happened) {
            return true;
        }

        if (($xml->localName == 'displayName') && ($xml->namespaceURI == GeniBaseClass::GENIBASE_NS)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->displayName = $child;
            $happened = true;
        } elseif (($xml->localName == 'email') && ($xml->namespaceURI == GeniBaseClass::GENIBASE_NS)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->email = $child;
            $happened = true;
        } elseif (($xml->localName == 'agentId') && ($xml->namespaceURI == GeniBaseClass::GENIBASE_NS)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->agentId = $child;
            $happened = true;
        } elseif (($xml->localName == 'preferredLanguage') && ($xml->namespaceURI == GeniBaseClass::GENIBASE_NS)) {
            $child = '';
            while ($xml->read() && $xml->hasValue) {
                $child = $child . $xml->value;
            }
            $this->preferredLanguage = $child;
            $happened = true;
        }

        return $happened;
    }

    /**
     * Sets a known attribute of User from an XML reader.
     *
     * @param \XMLReader $xml
     *            The reader.
     *
     * @return bool Whether an attribute was set.
     */
    protected function setKnownAttribute(\XMLReader $xml)
    {
        if (parent::setKnownAttribute($xml)) {
            return true;
        }

        return false;
    }

    /**
     * Writes this User to an XML writer.
     *
     * @param \XMLWriter $writer
     *            The XML writer.
     * @param bool $includeNamespaces
     *            Whether to write out the namespaces in the element.
     */
    public function toXml(\XMLWriter $writer, $includeNamespaces = true)
    {
        $writer->startElementNS('gb', 'user', null);
        if ($includeNamespaces) {
            $this->writeXmlNamespaces($writer);
        }
        $this->writeXmlContents($writer);
        $writer->endElement();
    }

    /**
     * Writes the namespaces for this GeniBaseClass to an XML writer.
     * The startElement is expected to be already provided.
     *
     * @param \XMLWriter $writer The XML writer.
     */
    protected function writeXmlNamespaces(\XMLWriter $writer)
    {
        $writer->writeAttributeNs('xmlns', 'gx', null, 'http://gedcomx.org/v1/');
        $writer->writeAttributeNs('xmlns', 'gb', null, GeniBaseClass::GENIBASE_NS);
    }

    /**
     * Writes the contents of this User to an XML writer.
     * The startElement is expected to be already provided.
     *
     * @param \XMLWriter $writer
     *            The XML writer.
     */
    protected function writeXmlContents(\XMLWriter $writer)
    {
        if ($this->agentId) {
            $writer->writeAttributeNs('gb', 'agent', null, $this->agentId);
        }

        parent::writeXmlContents($writer);

        if ($this->displayName) {
            $writer->startElementNs('gb', 'displayName', null);
            $writer->text($this->displayName);
            $writer->endElement();
        }
        if ($this->email) {
            $writer->startElementNs('gb', 'email', null);
            $writer->text($this->email);
            $writer->endElement();
        }
        if ($this->preferredLanguage) {
            $writer->startElementNs('gb', 'preferredLanguage', null);
            $writer->text($this->preferredLanguage);
            $writer->endElement();
        }
    }
}
