<?php
/**
 * Класс общего доступа к информации в базе данных.
 *
 * Класс берёт на себя функции поиска информации в базе. По результатам поиска он возвращает
 * экземпляр класса ww1_records_set, хранящего наёденную информацию и отвечающего за её вывод
 * на экран.
 *
 * @see ww1_records_set
 *
 * @copyright    Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Direct execution forbidden for this script
if (!defined('GB_VERSION') || count(get_included_files()) == 1) die('<b>ERROR:</b> Direct execution forbidden!');


if (!defined('GB_DEBUG_SQL_PROF')) define('GB_DEBUG_SQL_PROF', false);

/*
CREATE VIEW `?_v_persons` AS SELECT
	 `p`.`id`				AS `id`,
	 `p`.`surname`			AS `surname`,
	 `p`.`name`				AS `name`,
	 `p`.`rank`				AS `rank`,
	 `p`.`religion_id`		AS `religion_id`,
	`rl`.`religion`			AS `religion`,
	 `p`.`marital_id`		AS `marital_id`,
	`mr`.`marital`			AS `marital`,
	`rg`.`region`			AS `region`,
	`rg`.`region_idx`		AS `region_idx`,
	 `p`.`place`			AS `place`,
	 `p`.`reason_id`		AS `reason_id`,
	`rs`.`reason`			AS `reason`,
	 `p`.`date`				AS `date`,
	 `p`.`date_from`		AS `date_from`,
	 `p`.`date_to`			AS `date_to`,
	 `p`.`source_id`		AS `source_id`,
	`sc`.`source`			AS `source`,
	`sc`.`source_type_id`	AS `source_type_id`,
	`sc`.`source_number`	AS `source_nr`,
	 `p`.`source_pg`		AS `source_pg`,
	`sc`.`source_url`		AS `source_url`,
	`sc`.`source_pg_corr`	AS `source_pg_corr`,
	TRIM(LEADING CHAR(10) FROM CONCAT_WS(CHAR(10),TRIM(`p`.`comments`),TRIM(`sc`.`comments`)))	AS `comments`
FROM `?_persons` AS `p`
	JOIN `?_dic_regions`   AS `rg` ON (`p`.`region_id`   = `rg`.`id` AND `rg`.`locale` = 'ru')
	JOIN `?_dic_sources`   AS `sc` ON (`p`.`source_id`   = `sc`.`id` AND `sc`.`locale` = 'ru')
	JOIN `?_dic_reasons`   AS `rs` ON (`p`.`reason_id`   = `rs`.`id` AND `rs`.`locale` = 'ru')
	JOIN `?_dic_religions` AS `rl` ON (`p`.`religion_id` = `rl`.`id` AND `rl`.`locale` = 'ru')
	JOIN `?_dic_maritals`  AS `mr` ON (`p`.`marital_id`  = `mr`.`id` AND `mr`.`locale` = 'ru')
WHERE (1 = 1)
*/

if (!defined('MIN_DATE')) define('MIN_DATE', '1914-07-28');
if (!defined('MAX_DATE')) define('MAX_DATE', '1918-11-11');

/********************************************************************************
 * Абстрактный класс работы с базой данных
 */
define('Q_SIMPLE', 'Q_SIMPLE');    // Простой режим поиска
define('Q_EXTENDED', 'Q_EXTENDED');    // Расширенный режим поиска
//
abstract class ww1_database
{
    protected $query_mode;    // Режим поиска
    public $query;            // Набор условий поиска
    protected $page;            // Текущая страница результатов
    public $have_query;    // Признак наличия данных для запроса
    public $records_cnt;    // Общее число записей в базе

    /**
     * Создание экземпляра класса.
     *
     * @param string $qmode
     */
    function __construct($qmode = Q_SIMPLE)
    {
        $this->query_mode = $qmode;
        $this->query = array();
        $this->have_query = false;
        $this->records_cnt = 0;

        $this->page = get_request_attr('pg', 1);
        if ($this->page < 1) $this->page = 1;
    }

    /**
     * Генерация html-формы поиска.
     */
    abstract function search_form();

    /**
     * Осуществление поиска и генерация класса результатов поиска.
     */
    abstract function do_search();
}


/********************************************************************************
 * Класс работы с базой данных нижних чинов
 */
class ww1_database_solders extends ww1_database
{
    /**
     * If TRUE, the surname was automatically expanded with search keys.
     * @var boolean
     */
    var $surname_ext = FALSE;

    /**
     * If TRUE, the name was automatically expanded with search keys.
     * @var boolean
     */
    var $name_ext = FALSE;

    /**
     * List of fields for simple search mode.
     * @var array
     */
    private $simple_fields = array('surname', 'name', 'place');

    /**
     * List of fields for extended search mode.
     * @var array
     */
    private $extended_fields = array('surname', 'name', 'rank', 'religion', 'marital', 'region',
        'place', 'reason', 'date_from', 'date_to', 'source_type', 'source_nr', 'source_pg',
        'id');

    /**
     * List of fields with numeric values.
     * @var array
     */
    private $numeric_fields = array('id', 'religion', 'marital', 'reason', 'source_type',
        'source_nr', 'source_pg');

    /**
     * List of fields with IDs.
     * @var array
     */
    private $ids_fields = array(/*don't add 'id',*/
        'religion', 'marital', 'reason', 'source_type');

    /**
     * List of fields that have dictionaries.
     * @var array
     */
    private $dictionary_fields = array('rank', 'religion', 'marital', 'reason', 'source_type');


    /**
     * Создание экземпляра класса.
     *
     * @param string $qmode
     */
    function __construct($qmode = Q_SIMPLE)
    {
        parent::__construct($qmode);
        $args = gb_parse_args($_REQUEST);

        if ($qmode == Q_SIMPLE) {
            // Простой режим поиска ******************************************
            foreach ($this->simple_fields as $key) {
                $this->query[$key] = isset($args[$key]) ? trim((string)$args[$key]) : '';
                if (is_translit($this->query[$key]))
                    $this->query[$key] = translit2rus($this->query[$key]);
                $this->have_query |= !empty($this->query[$key]);
            }
            $this->name_ext = TRUE;
            $this->surname_ext = TRUE;

            $args = $this->query;

        } else {
            // Расширенный режим поиска **************************************
            foreach ($this->extended_fields as $key) {
                $this->query[$key] = isset($args[$key]) ? $args[$key] : '';
                if (is_string($this->query[$key]))
                    $this->query[$key] = trim($this->query[$key]);
                if (is_translit($this->query[$key]))
                    $this->query[$key] = translit2rus($this->query[$key]);
                if (in_array($key, $this->dictionary_fields) && !is_array($this->query[$key]))
                    $this->query[$key] = array();
                $this->have_query |= !empty($this->query[$key]);
            }
            $this->name_ext = isset($args['name_ext']) ? (bool)$args['name_ext'] : TRUE;
            $this->surname_ext = isset($args['surname_ext']) ? (bool)$args['surname_ext'] : TRUE;

            $args = $this->query;
            $args['name_ext'] = $this->name_ext;
            $args['surname_ext'] = $this->surname_ext;
        }

        // Make optimized request URI for current page
        list($protocol, $base, $query, $frag) = parse_query($_SERVER['REQUEST_URI']);
        $rq = build_query(array_filter($args));
        $rq = trim($rq, '?');
        $rq = $protocol . $base . $rq . $frag;
        $rq = rtrim($rq, '?');
        $_SERVER['REQUEST_URI'] = $rq;

        // Считаем, сколько всего записей в базе
        $this->records_cnt = gbdb()->get_cell('SELECT COUNT(*) FROM ?_persons');
// 		$this->records_cnt = gbdb()->get_cell('SELECT COUNT(*) FROM ?_persons AS p WHERE p.surname = "*" ' .
// 				'OR p.surname LIKE "(%" OR EXISTS ( SELECT 1 FROM ?_idx_search_keys AS i ' .
// 					'WHERE i.person_id = p.id )');
    }

    /**
     * Генерация html-формы поиска.
     */
    function search_form()
    {
        // Выводим предупреждение, что поиск производится только на русском
        if (get_locale() !== 'ru') {
            print '<p class="nb">' . __('<strong>Please note:</strong> We have translated the interface in English, but we can not translate the whole database. Unfortunately, the search for information you still need to do in Russian.', WW1_TXTDOM) . "</p>\n";
        }

        print "<div class='fields'>";
        if ($this->query_mode == Q_SIMPLE) {
            // Простой режим поиска ******************************************

            // Выводим html-поля
            $fields = array(
                'surname' => _x('Surname', 'Field name', WW1_TXTDOM),
                'name' => _x('Other names', 'Field name', WW1_TXTDOM),
                'place' => _x('Place of residence', 'Field name', WW1_TXTDOM),
            );
            foreach ($fields as $key => $val) {
                print "<div class='field'>" .
                    "<label for='q_$key'>$val:</label>" .
                    "<div><input type='text' id='q_$key' name='$key' value='" . esc_attr($this->query[$key]) . "' /></div>" .
                    "</div>\n";
            }

        } else {
            // Расширенный режим поиска **************************************
            $dics = array();

            // Получаем список всех вариантов значений воиских званий
            $dics['rank'] = gbdb()->get_column('SELECT DISTINCT rank, rank FROM ?_persons WHERE rank != ""' .
                ' ORDER BY rank', array(), TRUE);

            // Получаем список всех вариантов значений вероисповеданий
            $dics['religion'] = gbdb()->get_column('SELECT id, religion FROM ?_dic_religions' .
                ' WHERE religion_cnt != 0 ORDER BY religion', array(), TRUE);

            // Получаем список всех вариантов значений семейных положений
            $dics['marital'] = gbdb()->get_column('SELECT id, marital FROM ?_dic_maritals' .
                ' WHERE marital_cnt != 0 ORDER BY marital', array(), TRUE);

            // Получаем список всех вариантов значений событий
            $dics['reason'] = gbdb()->get_column('SELECT id, reason FROM ?_dic_reasons WHERE reason_cnt != 0' .
                ' ORDER BY reason', array(), TRUE);

            // Получаем список всех вариантов значений типов источников
            $dics['source_type'] = gbdb()->get_column('SELECT id, source_type FROM ?_dic_source_types WHERE source_type_cnt != 0' .
                ' ORDER BY source_type', array(), TRUE);

            // Выводим html-поля
            static $fields;
            if (!is_array($fields)) {
                $fields = array(
                    'surname' => _x('Surname', 'Field name', WW1_TXTDOM),
                    'name' => _x('Other names', 'Field name', WW1_TXTDOM),
                    'rank' => _x('Military rank', 'Field name', WW1_TXTDOM),
                    'religion' => _x('Religion', 'Field name', WW1_TXTDOM),
                    'marital' => _x('Marital status', 'Field name', WW1_TXTDOM),
                    'region' => _x('Province, Uezd, Volost', 'Field name', WW1_TXTDOM),
                    'place' => _x('Volost/Place', 'Field name', WW1_TXTDOM),
                    'reason' => _x('Event', 'Field name', WW1_TXTDOM),
                    'date' => _x('Event date', 'Field name', WW1_TXTDOM),
                    'source_type' => _x('Source type', 'Field name', WW1_TXTDOM),
                    'source_nr' => _x('Source number', 'Field name', WW1_TXTDOM),
                    'source_pg' => _x('Source page', 'Field name', WW1_TXTDOM),
                    'id' => _x('Record ID', 'Field name', WW1_TXTDOM),
                );
            }
            foreach ($fields as $key => $val) {
                switch ($key) {
                    case 'surname':
                        // Текстовые поля
                        print "<div class='fieldset'>" .
                            "<label for='q_$key'>$val:</label>" .
                            "<div>" .
                            "<div class='field'><input type='text' id='q_$key' name='$key' value='" . esc_attr($this->query[$key]) . "' /></div>" .
                            "<div class='field'><span class='checkbox'><input type='checkbox' id='q_surname_ext' name='surname_ext' value='1'" . (!$this->surname_ext ? "" : " checked='checked'") . " /><label for='q_surname_ext'></label></span> <label for='q_surname_ext'>" . __('also search for similar surnames', WW1_TXTDOM) . "</label></div>" .
                            "</div>" .
                            "</div>\n";
                        break;

                    case 'name':
                        // Текстовые поля
                        print "<div class='fieldset'>" .
                            "<label for='q_$key'>$val:</label>" .
                            "<div>" .
                            "<div class='field'><input type='text' id='q_$key' name='$key' value='" . esc_attr($this->query[$key]) . "' /></div>" .
                            "<div class='field'><span class='checkbox'><input type='checkbox' id='q_name_ext' name='name_ext' value='1'" . (!$this->surname_ext ? "" : " checked='checked'") . " /><label for='q_name_ext'></label></span> <label for='q_name_ext'>" . __('also search for abbreviations of names', WW1_TXTDOM) . "</label></div>" .
                            "</div>" .
                            "</div>\n";
                        break;

                    case 'date':
                        // Поля дат
                        print "<div class='field'>" .
                            "<label for='q_$key'>$val:</label>" .
                            "<div><nobr>" . _x('from', 'Date from … to …', WW1_TXTDOM) . " <input type='date' id='q_$key' name='date_from' value='" . esc_attr($this->query['date_from']) . "' min='" . MIN_DATE . "' max='" . MAX_DATE . "' /></nobr> " .
                            "<nobr>" . _x('to', 'Date from … to …', WW1_TXTDOM) . " <input type='date' name='date_to' value='" . esc_attr($this->query['date_to']) . "' min='" . MIN_DATE . "' max='" . MAX_DATE . "' /></nobr></div>" .
                            "</div>\n";
                        break;

                    default:
                        if (in_array($key, $this->dictionary_fields)) {        // Списковые поля
                            print "<div class='field'>" .
                                "<label for='q_$key'>$val:</label>" .
                                "<div><select id='q_$key' name='${key}[]' multiple='multiple' size='5'>";
                            foreach ($dics[$key] as $k => $v)
                                print "<option value='" . esc_attr($k) . "'" . (is_array($this->query[$key]) && in_array($k, $this->query[$key]) ? " selected='selected'" : '') . ">" . esc_html($v) . "</option>\n";
                            print "</select></div></div>\n";

                        } else {    // Текстовые поля
                            print "<div class='field'>" .
                                "<label for='q_$key'>$val:</label>" .
                                "<div><input type='text' id='q_$key' name='$key' value='" . esc_attr($this->query[$key]) . "' /></div>" .
                                "</div>\n";
                        }
                        break;
                }    // switch
            } // foreach $fields
        }
        print "</div>\n";
    }    // function


    /**
     * Осуществление поиска и генерация класса результатов поиска.
     */
    function do_search()
    {
        if ($this->query_mode == Q_SIMPLE) {
            // Простой режим поиска
            $fields = $this->simple_fields;
            $this->name_ext = $this->surname_ext = true;
        } else {
            // Расширенный режим поиска
            $fields = $this->extended_fields;
        }

        // Формируем основной поисковый запрос в БД
        $from = $where = $order = array();
        $from[] = gbdb()->prepare_query('?_v_persons AS p');
        $q_fused_match = 0;
        foreach ($fields as $key) {
            $val = $this->query[$key];
            if (!is_array($val))
                $val = fix_russian($val);
            if (empty($val)) continue;

            $is_regex = is_string($val) && preg_match('/[?*]/uSs', $val);
            if ($key == 'date_from') {
                // Дата с
                $where[] = 'p.`date_to` >= STR_TO_DATE(' . gbdb()->data_escape($val) . ', "%Y-%m-%d")';

            } elseif ($key == 'date_to') {
                // Дата по
                $where[] = 'p.`date_from` <= STR_TO_DATE(' . gbdb()->data_escape($val) . ', "%Y-%m-%d")';

            } elseif (in_array($key, $this->numeric_fields)) {
                // Числовые данные
                if (in_array($key, $this->ids_fields)) $key .= '_id';    // Проверка на поля с ID
                if (!is_array($val))
                    $val = preg_split('/\D+/uS', trim($val));
                $val = implode(', ', array_map('intval', $val));
                if (false === strchr($val, ','))
                    $where[] = "p.`$key` = $val";    // Одиночное значение
                else
                    $where[] = "p.`$key` IN ($val)";    // Множественное значение

            } else {
                // Текстовые данные…
                if (is_array($val)) {
                    // … в виде массива строк
                    $where[] = "p.`$key` IN (" .
                        implode(', ', array_map(array(gbdb(), 'data_escape'), $val)) . ')';

                } else {
                    // … в виде строки
                    if ($key == 'region' || $key == 'place') {
                        // Удаляем слова типа «губерния», «уезд» и т.п.
                        global $region_short;
                        $val = strtr($val, array_fill_keys(array_merge(array_keys($region_short), array_values($region_short)), ''));
                    }

                    $is_regex = preg_match('/[?*]/uS', $val);
                    $val_a = preg_split('/[^\w\?\*]+/uS', mb_strtoupper($val), -1, PREG_SPLIT_NO_EMPTY);
                    $val_a = simplify_query($val_a);
                    switch ($key) {
                        case 'surname':
                            $from_q = gbdb()->prepare_query('( SELECT k.person_id, MIN(k.surname_key_type)' .
                                ' AS ktype FROM ?_idx_search_keys AS k WHERE ');
                            $q_fused_match = '2*(p.surname LIKE "%?%") +4*(p.surname LIKE "%*%")' .
                                ' +8*(p.surname = "*")';
                            if ($is_regex || !$this->surname_ext) {
                                $data = gbdb()->data_escape(GB_DBase::make_condition($val_a), TRUE);
                                $data2 = gbdb()->data_escape($val_a, TRUE);
                                $from_q .= '(k.surname_key_type = ' . GB_MK_SURNAME . ' AND (k.surname_key LIKE ' .
                                    implode(' OR k.surname_key LIKE ', $data) .
                                    ')) OR (k.surname_mask != "" AND ' .
                                    implode(' LIKE k.surname_mask OR ', $data2) .
                                    ' LIKE k.surname_mask) GROUP BY k.person_id ) AS isk';
                            } else {
                                $data = make_metakeys(array(
                                    GB_MK_SURNAME => $val_a,
                                ));
                                $metakeys = array();
                                foreach ($data as $mks)
                                    $metakeys = array_merge($metakeys, $mks);
                                $data2 = gbdb()->data_escape($val_a, TRUE);
                                $from_q .= '(k.surname_key_type >= ' . GB_MK_SURNAME .
                                    ' AND k.surname_key_type < ' . (GB_MK_SURNAME + 100) .
                                    ' AND ' . gbdb()->prepare_query('k.surname_key IN (?keys)',
                                        array('keys' => $metakeys)) .
                                    ') OR (k.surname_mask != "" AND ' .
                                    implode(' LIKE k.surname_mask OR ', $data2) .
                                    ' LIKE k.surname_mask) GROUP BY k.person_id ) AS isk';
                                $q_fused_match = '(p.surname NOT LIKE ' .
                                    implode(' OR p.surname NOT LIKE ',
                                        gbdb()->data_escape(array_map(function ($text) {
                                            return "%$text%";
                                        }, $val_a), TRUE)) . ') +' . $q_fused_match;
                            }
                            $from[] = $from_q;
                            $where[] = 'p.id = isk.person_id';
                            $order[] = $q_fused_match;
                            $order[] = 'isk.ktype';
                            break;

                        case 'name':
                            $tmp = $is_regex || !$this->name_ext ? GB_DBase::make_regex($val_a)
                                : expand_names($val);
                            $where[] = 'UPPER(p.name) RLIKE ' . implode(' AND UPPER(p.name) RLIKE ',
                                    gbdb()->data_escape($tmp, TRUE));
                            break;

                        case 'region':
                            $data = gbdb()->data_escape(GB_DBase::make_regex($val_a), TRUE);
                            $where[] = 'p.region_idx RLIKE ' .
                                implode(' AND p.region_idx RLIKE ', $data);
                            break;

                        case 'place':
                            $data = gbdb()->data_escape(GB_DBase::make_regex($val_a), TRUE);
                            if ($this->query_mode == Q_SIMPLE) {
                                $where[] = implode(' AND ', array_map(function ($v) {
                                    return "(p.region_idx RLIKE $v OR UPPER(p.place) RLIKE $v)";
                                }, $data));
                            } else {
                                $where[] = 'UPPER(p.place) RLIKE ' .
                                    implode(' AND UPPER(p.place) RLIKE ', $data);
                            }
                            break;
                    } // switch
                } // if
            } // if
        } // foreach

        $order[] = 'p.surname, p.name, p.region, p.place, p.`rank`, p.source, p.source_pg';

        if (empty($from) || empty($where))
            return new ww1_solders_set($this->page);

        $from = ' FROM ' . implode(', ', $from);
        $where = ' WHERE ' . implode(' AND ', $where);
        $order = empty($order) ? '' : (' ORDER BY ' . implode(', ', $order));
        $query = 'SELECT' . (!GB_DEBUG_SQL_PROF ? '' : ' SQL_NO_CACHE') . ' p.id' .
            $from . $where . $order;

        if (GB_DEBUG_SQL_PROF)
            gbdb()->query('SET PROFILING=1');

        $ids = false;
        // TODO: Добавить получение результатов поиска из кэша
        if (false === $ids) {
            // Получаем результаты поиска
            $ids = gbdb()->get_column($query);
// 			if( $ids)	$ids = array_map('intval', $ids);	// TODO: Out of memory crash

            // TODO: Добавить сохранение результатов поиска в кэш
        }

        $data = $ids_part = array();
        if (!empty($ids))
            $ids_part = array_slice($ids, ($this->page - 1) * Q_LIMIT, Q_LIMIT);
        if (!empty($ids_part)) {
            // Получаем текущую порцию результатов для вывода в таблицу
            $result = gbdb()->get_table('SELECT p.*, ' . $q_fused_match .
                ' AS fused_match FROM ?_v_persons AS p WHERE p.id IN (?ids)',
                array('ids' => $ids_part), 'id');
            //
            // Дополняем данные новыми (неформализуемыми) полями
            if (!empty($result)) {
                $add_fields = gbdb()->get_table('SELECT id, military_unit, place_of_event, estate_or_title,' .
                    ' additional_info, birthdate FROM ?_persons_raw WHERE id IN (?ids)',
                    array('ids' => $ids_part), 'id');

                foreach ($ids_part as $id) {
                    if (is_array($add_fields) && !empty($add_fields[$id]))
                        $result[$id] = array_merge($result[$id], array_filter($add_fields[$id]));
                    $data[$id] = $result[$id];
                }
            }
        }
        $report = new ww1_solders_set($this->page, $data, count($ids));

        if (GB_DEBUG_SQL_PROF) {
            print("\n<!-- SQL-Profile:\n");
            $total = 0;
            $result = gbdb()->get_column('SHOW PROFILE', array(), TRUE);
            foreach ($result as $key => $val) {
                printf("%20s: %7.3f sec\n", $key, $val);
                if ($key == 'Table lock')
                    continue;
                $total += $val;
            }
            printf("-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-\n%20s: %7.3f sec\n -->\n", 'Total', $total);
            gbdb()->query('SET PROFILING=0');
        }

        return $report;
    }
}
