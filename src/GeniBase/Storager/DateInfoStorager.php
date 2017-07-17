<?php
namespace GeniBase\Storager;

use Gedcomx\Common\ExtensibleData;
use Gedcomx\Conclusion\DateInfo;
use Gedcomx\Util\FormalDate;
use GeniBase\Util;
use GeniBase\DBase\GeniBaseInternalProperties;

class DateInfoStorager extends GeniBaseStorager
{

    const GC_PROBABILITY = 1; // of 10 000

    const TABLES_WITH_REF = 'events facts names places.temporalDescription_id';

    protected function getObject($o = null)
    {
        return new DateInfo($o);
    }

    /**
     *
     * @param mixed $entity
     * @param ExtensibleData $context
     * @param array|null $o
     * @return ExtensibleData|false
     */
    public function save($entity, ExtensibleData $context = null, $o = null)
    {
        if (! $entity instanceof DateInfo)
            $entity = $this->getObject($entity);

        $t_dates = $this->dbs->getTableName('dates');

        // Prepare data to save
        $ent = $entity->toArray();
        $data = Util::array_slice_keys($ent, 'original', 'formal');
        if (isset($data['formal'])) {
            $period = self::calcPeriodInDays($data['formal']);

            $data['_from_day']   = $period[0];
            $data['_to_day']     = $period[1];
        }

        // Save data
        $_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id');
        parent::save($entity, $context, $o);

        if (! empty($_id)) {
            $this->dbs->getDb()->update($t_dates, $data, [
                '_id' => $_id
            ]);

        } else {
            $this->dbs->getDb()->insert($t_dates, $data);
            $_id = (int) $this->dbs->getDb()->lastInsertId();
        }
        GeniBaseInternalProperties::setPropertyOf($entity, '_id', $_id);
        
        return $entity;
    }

    protected function loadRaw(ExtensibleData $entity, $context, $o)
    {
        $table = $this->dbs->getTableName('dates');

        $result = false;
        if (! empty($_id = (int) GeniBaseInternalProperties::getPropertyOf($entity, '_id'))) {
            $result = $this->dbs->getDb()->fetchAssoc(
                "SELECT * FROM $table WHERE _id = ?",
                [$_id]
            );

        } elseif (! empty($id = $entity->getId())) {
            $result = $this->dbs->getDb()->fetchAssoc(
                "SELECT * FROM $table WHERE id = ?",
                [$id]
            );
        }

        return $result;
    }

    protected function garbageCleaning()
    {
        static $tables;

        if (mt_rand(1, 10000) > self::GC_PROBABILITY)   return; // Skip cleaning now

        if (! isset($tables)) {
            // Initialization
            $tmp = array_map(
                function ($v) {
                    $v = preg_split('/[.:]+/', $v, null, PREG_SPLIT_NO_EMPTY);
                    if (empty($v[1]))   $v[1] = 'date_id';
                    $v[0] = $this->dbs->getTableName($v[0]);
                    return $v;
                },
                preg_split('/[\s,]+/', self::TABLES_WITH_REF, null, PREG_SPLIT_NO_EMPTY)
            );
            $tables = [];
            foreach ($tmp as $v) {
                $tables[$v[0]] = $v[1];
            }
            unset($tmp);
        }

        $t_dates = $this->dbs->getTableName('dates');

        $q  = "DELETE LOW_PRIORITY dt FROM $t_dates AS dt WHERE NOT EXISTS ( ";
        $cnt = 0;
        foreach ($tables as $t => $f) {
            if (1 != ++$cnt)
                $q  .= "UNION ";
            $q  .= "SELECT 1 FROM $t AS t$cnt WHERE t$cnt.$f = dt._id ";
        }
        $q .= ")";

        $this->dbs->getDb()->query($q);
    }

    /**
     *
     * @param mixed $date
     * @return NULL[]|number[]
     */
    protected static function calcPeriodInDays($date)
    {
        if (! $date instanceof FormalDate) {
            $tmp = $date;
            $date = new FormalDate();
            $date->parse($tmp);
            unset($tmp);
        }

        $start = $date->getStart();
        $end = $date->getEnd();

        if (! $date->getIsRange()) {
            $end = $start;
        }
        // TODO: Add logic for Reccuring and Durations

        $period = [
            self::calcDayOfEpoch($start),
            self::calcDayOfEpoch($end, true),
        ];

        if (($period[0] !== null) && ($period[1] !== null) && ($period[0] > $period[1])) {
            $period = [
                self::calcDayOfEpoch($end),
                self::calcDayOfEpoch($start, true),
            ];
        }

        return $period;
    }

    protected static function calcDayOfEpoch($date, $calcEndOfPeriod = false)
    {
        static $mdays = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        if (empty($date) || (! $date->isValid() && ($date->getYear() !== 0)))
            return null;

        if ($mdays[12] < 100) {
            // First time initialization

            // Calculate number of days from start of (non leap) year
            $sum = 0;
            for ($i = 0; $i <= 12; $i++) {
                $sum += $mdays[$i];
                $mdays[$i] = $sum;
            }
        }

        $y = $date->getYear();
        $sign = 1;
        if ($y < 1) {
            $sign = -1;
            $y = 1 - $y;
        }

        $isLeap = (($y % 100 != 0) && ($y % 4 == 0)) || ($y % 400 == 0);

        $my = intval(($y-1) / 1000);        // Millenium
        $cy = intval(($y-1) / 100) % 1000;  // Century into millenium
        $sy = ($y-1) % 100;                 // Single years into century
        $k = $ky = ($y-1) *365 + $my *243 + $cy *24 + intval($cy / 4) + intval($sy / 4) + 1; // First day of year

        if (null !== $m = $date->getMonth()) {
            $k += $mdays[$m - 1] + intval($isLeap && $m > 2);  // First day of month

            if (null !== $d = $date->getDay()) {
                $k += $d - 1;

            } elseif ($calcEndOfPeriod) {
                $k = $ky + $mdays[$m] + intval($isLeap && $m >= 2) - 1;  // Last day of month
            }

        } elseif ($calcEndOfPeriod) {
            $my = intval($y / 1000);        // Millenium
            $cy = intval($y / 100) % 1000;  // Century into millenium
            $sy = $y % 100;                 // Single years into century
            $k = $y *365 + $my *243 + $cy *24 + intval($cy / 4) + intval($sy / 4); // First day of year
        }

        return $sign * $k;
    }

}
