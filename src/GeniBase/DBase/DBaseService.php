<?php
namespace GeniBase\DBase;

use Gedcomx\Agent\Agent;
use Pimple\Container;

/**
 *
 * @author Limych
 */
class DBaseService extends Container
{

    /**
     * @var Agent
     */
    protected $agent;

    /**
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this['app'] = $app;
    }

    /**
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDb()
    {
        return $this['app']['db'];
    }

    /**
     *
     * @param string $table
     * @return string
     */
    public function getTableName($table)
    {
        $prefix = isset($this['app']['dbs.options']['default']['prefix'])
                ? $this['app']['dbs.options']['default']['prefix'] : '';
        return $prefix.$table;
    }

    /**
     *
     * @param string $table
     * @param string $id
     * @return number|false
     */
    public function getLidForId($table, $id)
    {
        if (false !== $result = $this['app']['db']->fetchColumn("SELECT _id FROM $table WHERE id = ?", [$id])) {
            return (int) $result;
        }
        return $result;
    }

    /**
     *
     * @param string $table
     * @param number $lid
     * @return string|false
     */
    public function getIdForLid($table, $lid)
    {
        return $this['app']['db']->fetchColumn("SELECT id FROM $table WHERE _id = ?", [(int) $lid]);
    }

    /**
     *
     * @param string $uri
     * @return NULL|string|false
     * @deprecated
     */
    public function getIdFromReference($uri)
    {
        if (empty($uri)) {
            return null;
        }

        if (preg_match('/\#([\w\-]+)/', $uri, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     *
     * @param Agent $agent
     */
    public function setAgent(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     *
     * @return \Gedcomx\Agent\Agent
     */
    public function getAgent()
    {
        return $this->agent;
    }
}
