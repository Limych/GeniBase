<?php
/**
 * Функции автоматической формализации данных.
 * 
 * @copyright	Copyright © 2014–2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Запрещено непосредственное исполнение этого скрипта
if(count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



/**
 * Функции формализации и публикации записей
 */



/**
 * Функция нормирования дат
 */
function prepublish_date(&$raw, &$date_norm){
	if(empty($raw['date'])){
		$raw['date_from']	= '1914-08-01';
		$raw['date_to']		= '1918-11-11';
		return;
	}

	// Автоматическая корректировка «машинных» форматов дат в привычные для людей
	$raw['date'] = strtr($raw['date'], array(
		'-Jan-'	=> ' янв.',		'-Feb-'	=> ' фев.',		'-Mar-'	=> ' мар.',		'-Apr-'	=> ' апр.',		'-May-'	=> ' мая ',
		'-Jun-'	=> ' июн.',		'-Jul-'	=> ' июл.',		'-Aug-'	=> ' авг.',		'-Sep-'	=> ' сен.',		'-Oct-'	=> ' окт.',
		'-Nov-'	=> ' ноя.',		'-Dec-'	=> ' дек.',
	));
	//
	static $month_names_norm = array(
		1	=> ' янв.',		2	=> ' фев.',		3	=> ' мар.',		4	=> ' апр.',		5	=> ' мая ', 	6	=> ' июн.',
		7	=> ' июл.',		8	=> ' авг.',		9	=> ' сен.',		10	=> ' окт.',		11	=> ' ноя.',		12	=> ' дек.',
	);
	// yyyy-mm-dd
	$raw['date'] = preg_replace_callback('/^(\d{4})-(\d{2})-(\d{2})$/uS', function($matches) use ($month_names_norm) {
		if(!isset($month_names_norm[intval($matches[2])]))
			return $matches[0];
		return $matches[3] . $month_names_norm[intval($matches[2])] . $matches[1];
	}, $raw['date']);
	// mm/dd/yyyy
	$raw['date'] = preg_replace_callback('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/uS', function($matches) use ($month_names_norm) {
		if(!isset($month_names_norm[intval($matches[1])]))
			return $matches[0];
		return $matches[2] . $month_names_norm[intval($matches[1])] . $matches[3];
	}, $raw['date']);
	// dd.mm.yyyy
	$raw['date'] = preg_replace_callback('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/uS', function($matches) use ($month_names_norm) {
		if(!isset($month_names_norm[intval($matches[2])]))
			return $matches[0];
		return $matches[1] . $month_names_norm[intval($matches[2])] . $matches[3];
	}, $raw['date']);

	$date_norm = $raw['date'];

	// Переводим все буквы в строчные, обрезаем концевые пробелы и корректируем русские буквы
	$date_norm = fix_russian(trim(mb_strtolower($date_norm)));
	// Убираем префикс «с »
	$date_norm = preg_replace('/^съ?\s*/uS', '', $date_norm);
	// Заменяем частички « по » и « на » на дефис
	$date_norm = preg_replace('/(?<=[\d\W])\s*(?:по|на)\s*(?=[\d\W])/uS', '-', $date_norm);
	// Заменяем частички « и » и « или » на запятую
	$date_norm = preg_replace('/(?<=[\d\W])\s*и(ли)?\s*(?=[\d\W])/uS', ',', $date_norm);
	// Убираем окончание «г[ода][.]»
	$date_norm = preg_replace('/\s*г(?:ода?)?\.?\s*$/uS', '', $date_norm);
	// Заменяем на точки пробелы рядом со словами, остальные — убираем
	$date_norm = preg_replace('/(?<=[А-Яа-я])\s+|\s+(?=[А-Яа-я])/uS', '.', $date_norm);
	$date_norm = preg_replace('/\s+/uS', '', $date_norm);
	// Сокращаем несколько точек или дефисов в один
	$date_norm = preg_replace('/([\.\-])\\1*/uS', '\\1', $date_norm);

	// Шаблоны для поиска дат
	static	$month_names = array(
		'январь'	=> '01',		'января'	=> '01',		'янв'	=> '01',
		'февраль'	=> '02',		'февраля'	=> '02',		'февр'	=> '02',		'фев'	=> '02',		'фвр'	=> '02',
		'март'		=> '03',		'марта'		=> '03',		'мрт'	=> '03',		'мар'	=> '03',
		'апрель'	=> '04',		'апреля'	=> '04',		'апр'	=> '04',
		'май'		=> '05',		'мая'		=> '05',
		'июнь'		=> '06',		'июня'		=> '06',		'июн'	=> '06',
		'июль'		=> '07',		'июля'		=> '07',		'июл'	=> '07',
		'август'	=> '08',		'августа'	=> '08',		'ав'	=> '08',		'авг'	=> '08',
		'сентябрь'	=> '09',		'сентября'	=> '09',		'сен'	=> '09',		'снт'	=> '09',		'сент'	=> '09',
		'октябрь'	=> '10',		'октября'	=> '10',		'окт'	=> '10',
		'ноябрь'	=> '11',		'ноября'	=> '11',		'нбр'	=> '11',		'ноя'	=> '11',		'нояб'	=> '11',
		'декабрь'	=> '12',		'декабря'	=> '12',		'дек'	=> '12',		'дкб'	=> '12',
	);
	static	$reg_months = '';
	static	$reg_date_left	= '';
	static	$reg_date_right	= '';
	if(empty($reg_months)){
		$reg_months = '(?:' . implode('|', array_keys($month_names)) . ')';
		// дд[.мм[.[гг]гг]] или дд[.ммм[.[гг]гг]]
		$reg_date_left	= "(\d\d?)(?:\.(?:($reg_months|\d\d?)(?:\.((?:\d\d)?\d\d)?)?)?)?";
		// [[дд.]мм.]гггг или [[дд.]ммм.]гггг
		$reg_date_right	= "(?:(?:(?:(\d\d?)?\.)?($reg_months|\d\d?))?\.)?(\d\d\d\d)";
		// [дд.]ммм.
		$reg_date_right_short	= "(?:(\d\d?)?\.)?($reg_months)\.?()";
	}

	// Запись «1914/15»
	if($date_norm == '1914/15'){
		$matches = array('', '01', '08', '1914', '31', '12', '1915');

	// Простая запись дд.мм.гггг или мм.гггг или гггг
	}elseif(preg_match("/^$reg_date_left$/uS", $date_norm, $matches)
	|| preg_match("/^$reg_date_right$/uS", $date_norm, $matches)){
		if(empty($matches[3]))	$matches[3] = ($matches[2] >= 8 ? 1914 : 1915);
		$matches[4] = $matches[1];
		$matches[5] = $matches[2];
		$matches[6] = $matches[3];

	// Периодическая запись дд-дд.мм.гггг или дд.мм-дд.мм.гггг или дд.мм.гггг-дд.мм.гггг
	}elseif(preg_match("/^$reg_date_left-$reg_date_left$/uS", $date_norm, $matches)
	|| preg_match("/^$reg_date_left-$reg_date_right$/uS", $date_norm, $matches)
	|| preg_match("/^$reg_date_right-$reg_date_right$/uS", $date_norm, $matches)){
		if(empty($matches[6]))	$matches[6] = ($matches[5] >= 8 ? 1914 : 1915);
		if(empty($matches[2]))	$matches[2] = $matches[5];
		if(empty($matches[3]))	$matches[3] = $matches[6];

	// Периодическая запись дд.ммм-дд.ммм.гггг
	}elseif(preg_match("/^$reg_date_right_short-$reg_date_right$/uS", $date_norm, $matches)){
		if(empty($matches[6]))	$matches[6] = ($matches[5] >= 8 ? 1914 : 1915);
		if(empty($matches[2]))	$matches[2] = $matches[5];
		if(empty($matches[3]))	$matches[3] = $matches[6];

	// Списочная запись дд,дд,дд.мм.гггг
	}elseif(preg_match("/^(\d\d?),(?:\d\d?,)*$reg_date_left$/uS", $date_norm, $matches)
	|| preg_match("/^(\d\d?),(?:\d\d?,)*$reg_date_right$/uS", $date_norm, $matches)){
		if(empty($matches[4]))	$matches[4] = ($matches[3] >= 8 ? 1914 : 1915);
		array_splice($matches, 2, 0, array($matches[3], $matches[4]));
	}

	// Нормализуем данные и формируем новые поля
	if(!empty($matches)){
		static $last_days = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

		if($matches[3] < 100)	$matches[3] += 1900;
		if(empty($matches[2]))	$matches[2] = ($matches[3] == 1914 ? '08' : '01');
		elseif(!is_numeric($matches[2]))	$matches[2] = $month_names[$matches[2]];
		if(empty($matches[1]))	$matches[1] = '01';
		$tmp = date_create(implode('-', array($matches[3], $matches[2], $matches[1])));
		if($tmp)
			$raw['date_from'] = $tmp->format('Y-m-d');
		//
		if($matches[6] < 100)	$matches[6] += 1900;
		if(empty($matches[5]))	$matches[5] = ($matches[5] == 1918 ? '11' : '12');
		elseif(!is_numeric($matches[5]))	$matches[5] = $month_names[$matches[5]];
		if(empty($matches[4]))	$matches[4] = (($matches[6] == 1918) && ($matches[5] == 11) ? '11' : $last_days[intval($matches[5])-1]);
		$tmp = date_create(implode('-', array($matches[6], $matches[5], $matches[4])));
		if($tmp)
			$raw['date_to'] = $tmp->format('Y-m-d');
		
		if(($raw['date_from'] < '1914-07-01') || ($raw['date_from'] > '1920-12-31')
		|| ($raw['date_to'] < '1914-07-01') || ($raw['date_to'] > '1920-12-31')){
			unset($raw['date_from']);
			unset($raw['date_to']);
		}
	}
// var_export($matches);
}



/**
 * Функция нормирования исходных данных
 */
function prepublish($raw, &$have_trouble, &$date_norm){
	static	$str_fields = array('surname', 'name', 'rank', 'religion', 'marital', 'uyezd', 'reason');

	foreach($str_fields as $key){
		// Убираем концевые пробелы и сокращаем множественные пробелы
		$raw[$key] = trim(preg_replace('/\s\s+/uS', ' ', $raw[$key]));

		// Конвертируем старо-русские буквы в современные
		$raw[$key] = fix_russian($raw[$key]);

		// Правим регистр букв в текстах
		if($key == 'surname'){
			// Первые буквы каждого слова в верхний регистр
			$raw[$key] = preg_replace_callback('/\b\w+\b/uS', function ($matches){
				return mb_ucfirst(mb_strtolower($matches[0]));
			}, $raw[$key]);
		}elseif($key == 'name'){
			// Первые буквы каждого слова в верхний регистр
			$raw[$key] = preg_replace_callback('/\b\w+(?:-\w+)\b/uS', function ($matches){
				return mb_ucfirst($matches[0]);
			}, $raw[$key]);
		}else {
			// Первую букву в верхний регистр
			$raw[$key] = mb_ucfirst($raw[$key]);
		}
	}

	// Расшифровываем вероисповедания
	/** @var	int[]	Array of correspondences between contractions of religion names and their IDs in the database. */
	static	$religion_conts = array();
	//
	// Fetch religion names and reductions from dbase
	if (empty($religion_conts)) {
		$religion_conts[''] = 18;	// Special ID for "(not set)"

		$result = gbdb()->get_table('SELECT `id`, `religion`, `contractions` FROM `dic_religion`
				WHERE `religion` NOT LIKE "(%"');
		foreach ($result as $row){
			$tmp = array_merge((array) mb_strtolower($row['religion']),
					preg_split('/\W+/uS', mb_strtolower($row['contractions']), -1, PREG_SPLIT_NO_EMPTY));
			foreach ($tmp as $key)
				$religion_conts[$key] = $row['id'];
		}
	}
 	//
	$tmp = trim(preg_replace('/\W+/uS', '-', mb_strtolower($raw['religion'])), '-');
// if(defined('P_DEBUG'))	var_export($tmp);
	if(isset($religion_conts[$tmp]))
		$raw['religion_id'] = $religion_conts[$tmp];

	// Расшифровываем семейные положения
	static $maritals = array(
		''		=> 4,
		// Женатые
		'ж'	=> 1,
		'жен'	=> 1,
		'женат'	=> 1,
		// Холостые
		'х'	=> 2,
		'хол'	=> 2,
		'холост'	=> 2,
		// Вдовые
		'вд'	=> 3,
		'вдв'	=> 3,
		'вдов'	=> 3,
		'вдовец'	=> 3,
	);
	$tmp = trim(preg_replace('/\W+/uS', ' ', mb_strtolower($raw['marital'])));
// if(defined('P_DEBUG'))	var_export($tmp);
	if(isset($maritals[$tmp]))
		$raw['marital_id'] = $maritals[$tmp];

	// Формализуем события
	/** @var	int[]	Array of correspondences between contractions of events names and their IDs in the database. */
	static	$reason_conts = array();
	//
	// Fetch events/reasons names and reductions from dbase
	if (empty($reason_conts)) {
		$reason_conts[''] = 1;	// Special ID for "(not set)"

		$result = gbdb()->get_table('SELECT `reason_id`, `reason_raw` FROM `dic_reason2reason` r2r, `dic_reason` r
				WHERE r2r.reason_id = r.id and r.`reason` NOT LIKE "(%"');
		foreach ($result as $row)
			$reason_conts[trim(mb_strtolower($row['reason_raw']))] = $row['reason_id'];
	}
 	//
	$tmp = trim(mb_strtolower($raw['reason']));
	// if(defined('P_DEBUG'))	var_export($tmp);
	if(isset($reason_conts[$tmp]))
		$raw['reason_id'] = $reason_conts[$tmp];
		
	// Расшифровываем источники
	if((empty($raw['source_id']))||($raw['source_id']==0)){
		if(empty($raw['list_nr'])){
			$raw['source_id'] = 0;
		}else {
			$res = gbdb()->get_cell('SELECT id FROM dic_source WHERE source LIKE :title',
					array('title' => "Именной список №$raw[list_nr] %"));
			if($res)
				$raw['source_id'] = $res;
			else {
				$tmp = "Именной список №$raw[list_nr] убитым, раненым и без вести пропавшим " . ($raw['list_nr'] < 974 ? "нижним чинам." : "солдатам.");
				$raw['source_id'] = gbdb()->set_row('dic_source',
						array('source' => $tmp), FALSE, GB_DBase::MODE_INSERT);
			}
		}
	}
	
	// Уточняем региональную привязку
	if(!empty($raw['uyezd'])){
		$res = gbdb()->get_cell('SELECT id FROM dic_region WHERE parent_id = :parent_id AND title LIKE :title',
				array(
					'parent_id'	=> $raw['region_id'],
					'title'		=> $raw['uyezd'] . ' %',
				));
		if($res)
			$raw['region_id'] = $res;
		else {
			$raw['region_id'] = gbdb()->set_row('dic_region', array(
				'parent_id'	=> $raw['region_id'],
				'title'		=> $raw['uyezd'] . ' ',
			), FALSE, GB_DBase::MODE_INSERT);
		}
	}

	// Расшифровываем даты
	prepublish_date($raw, $date_norm);

	// Собираем данные для занесения в основную таблицу
if(defined('P_DEBUG'))	var_export($raw);
	return prepublish_make_data($raw, $have_trouble);
} // function prepublish



/**
 * Функция подготовки данных для занесения в систему.
 * 
 * @param	mixed[]	$raw_norm		Исходные данные для нормализации.
 * @param	bool	$have_trouble	Признак невозможности автоматически формализовать данные.
 * @return	mixed[]		Формализованная версия данных.
 */
function prepublish_make_data($raw_norm, &$have_trouble){
	$have_trouble = false;
	$pub = array();
	foreach(explode(' ', 'id surname name rank religion_id marital_id region_id place reason_id date date_from date_to source_id list_nr list_pg') as $key){
		if(!isset($raw_norm[$key])
		|| ((empty($raw_norm[$key]) || absint($raw_norm[$key]) != $raw_norm[$key]) && $key != 'source_id' && preg_match('/(^|_)id$/uS', $key))
		|| ($key == 'date_from' && ($raw_norm['date_from'] < '1914-07-01' || $raw_norm['date_from'] > '1920-12-31'))
		|| ($key == 'date_to' && ($raw_norm['date_to'] < '1914-07-01' || $raw_norm['date_to'] > '1920-12-31')))
			$have_trouble = true;
		else
			$pub[$key] = $raw_norm[$key];
	}
	return $pub;
}

?>