<?php
require_once('functions.php');	// Общие функции системы

/**
 * Скрипт формализации и публикации записей
 */
 
// define('P_DEBUG', 1);	// Признак режима отладки

define('P_LIMIT', 21);	// Лимит числа единовременно публикуемых записей



if(empty($_SERVER['PHP_SELF']) || (basename($_SERVER['PHP_SELF']) == basename(__FILE__)))	header('Content-Type: text/plain; charset=utf-8');

// Делаем выборку записей для публикации
$drafts = array();
$result = db_query('SELECT * FROM persons_raw WHERE status = "Draft" ORDER BY rank, reason LIMIT ' . P_LIMIT);
while($row = mysql_fetch_array($result, MYSQL_ASSOC)){
	$drafts[] = $row;
}
mysql_free_result($result);

// Нормирование данных
foreach($drafts as $row){
if(defined('P_DEBUG'))	print "\n\n======================================\n";
if(defined('P_DEBUG'))	var_export($row);
	$pub = prepublish($row, $have_trouble);
if(defined('P_DEBUG'))	var_export($have_trouble);
if(defined('P_DEBUG'))	var_export($pub);

	// Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
	if(!$have_trouble){
		db_query('REPLACE INTO persons (' . implode(', ', array_keys($pub)) . ') VALUES ("' . implode('", "', array_values($pub)) . '")');
	}
	db_query('UPDATE persons_raw SET status = "' . ($have_trouble ? 'Cant publish' : 'Published') . '" WHERE id = ' . $row['id']);
}

if(empty($_SERVER['PHP_SELF']) || (basename($_SERVER['PHP_SELF']) == basename(__FILE__)))	db_close();



/**
 * Функция нормирования исходных данных
 */
function prepublish($row, &$have_trouble){
	static	$str_fields = array('surname', 'name', 'rank', 'religion', 'marital', 'uyezd', 'reason');

	foreach($str_fields as $key){
		// Убираем концевые пробелы и сокращаем множественные пробелы
		$row[$key] = trim(preg_replace('/\s\s+/uS', ' ', $row[$key]));

		// Конвертируем старо-русские буквы в современные
		$row[$key] = strtr($row[$key], array(
			'ѣ'	=> 'Е',
			'Ѣ'	=> 'е',
			'Ѵ'	=> 'И',
			'ѵ'	=> 'и',
			'І'	=> 'И',
			'і'	=> 'и',
			'Ѳ'	=> 'Ф',
			'ѳ'	=> 'ф',
			"\u1029"	=> 'З',
			"\u1109"	=> 'з',
			"S"	=> 'З',
			"s"	=> 'з',
		));

		// Правим регистр букв в текстах
		if(($key == 'surname') || ($key == 'name')){
			// Первые буквы каждого слова в верхний регистр
			$row[$key] = preg_replace_callback('/\b\w+\b/uS', function ($matches){
				return mb_ucfirst($matches[0]);
			}, $row[$key]);
		}else{
			// Первую букву в верхний регистр
			$row[$key] = mb_ucfirst($row[$key]);
		}
	}
	
	// Расшифровываем сокращения имён
	$names = array(
		'Ив.'		=> 'Иван',			'Вас.'		=> 'Василий',		'Мих.'		=> 'Михаил',
		'Григ.'		=> 'Григорий',		'Никол.'	=> 'Николай',		'Степ.'		=> 'Степан',
		'Андр.'		=> 'Андрей',		'Пав.'		=> 'Павел',			'Дм.'		=> 'Дмитрий',
		'Сем.'		=> 'Семён',			'Як.'		=> 'Яков',			'Афан.'		=> 'Афанасий',
		'Фед.'		=> 'Фёдор',			'Серг.'		=> 'Сергей',		'Васил.'	=> 'Василий',
		'Дмитр.'	=> 'Дмитрий',		'Гавр.'		=> 'Гавриил',		'Макс.'		=> 'Максим',
		'Конст.'	=> 'Константин',	'Дан.'		=> 'Даниил',		'Троф.'		=> 'Трофим',
		'Георг.'	=> 'Георгий',		'Гр.'		=> 'Григорий',		'Никиф.'	=> 'Никифор',
		'Тимоф.'	=> 'Тимофей',		'Тим.'		=> 'Тимофей',		'Емел.'		=> 'Емельян',
		'Матв.'		=> 'Матвей',		'Владим.'	=> 'Владимир',		'Ант.'		=> 'Антон',
		'Анд.'		=> 'Андрей',		'Филип.'	=> 'Филипп',		'Григор.'	=> 'Григорий',
		'Куз.'		=> 'Кузьма',		'Игн.'		=> 'Игнатий',		'Стеф.'		=> 'Стефан',
		'Прок.'		=> 'Прокопий',		'Ром.'		=> 'Роман',			'Ник.'		=> 'Николай',
		'Зах.'		=> 'Захарий',		'Иос.'		=> 'Иосиф',			'Леонт.'	=> 'Леонтий',
		'Герас.'	=> 'Герасим',		'Иллар.'	=> 'Илларион',		'Кирил.'	=> 'Кирилл',
		'Митроф.'	=> 'Митрофан',		'Кондр.'	=> 'Кондратий',		'Моис.'		=> 'Моисей',
		'Лавр.'		=> 'Лаврентий',		'Констант.'	=> 'Константин',	'Тих.'		=> 'Тихон',
		'Пантел.'	=> 'Пантелеймон',	'Евдок.'	=> 'Евдоким',		'Еф.'		=> 'Ефим',
		'Харит.'	=> 'Харитон',		'Игнат.'	=> 'Игнатий',		'Прокоф.'	=> 'Прокопий',
		'Терент.'	=> 'Терентий',		'Спирид.'	=> 'Спиридон',		'Дав.'		=> 'Давыд Давид',
		'Исид.'		=> 'Исидор',		'Викт.'		=> 'Виктор',		'Ег.'		=> '',
		'Прох.'		=> 'Прохор',		'Аф.'		=> 'Афанасий',		'Митр.'		=> 'Митрофан',
		'Артем.'	=> 'Артемий',		'Федор.'	=> '',				'Станисл.'	=> 'Станислав',
		'Порф.'		=> 'Порфирий',		'Стан.'		=> 'Станислав',		'Пант.'		=> 'Пантелеймон',
		'Евстаф.'	=> 'Евстафий',		'Спир.'		=> 'Спиридон',		'Абр.'		=> 'Абрам',
		'Ефр.'		=> '',				'Прокоп.'	=> 'Прокопий',		'Род.'		=> 'Родион',
		'Федос.'	=> '',				'Фил.'		=> 'Филипп',		'Плат.'		=> 'Платон',
		'Захар.'	=> 'Захарий',		'Триф.'		=> 'Трифон',		'Ал-др.'	=> 'Александр',
		'Афанас.'	=> 'Афанасий',		'Влад.'		=> 'Владимир',		'Елис.'		=> 'Елисей',
		'Порфир.'	=> 'Порфирий',		'Онис.'		=> '',				'Савел.'	=> 'Савелий',
		'Евтих.'	=> '',
		'Арс.'	=> '',
		'Корн.'	=> '',
		'Феодос.'	=> '',
		'Алекс.'	=> '',
		'Пет.'	=> '',
		'Арсен.'	=> '',
		'Дороф.'	=> '',
		'Mиx.'	=> '',
		'Тер.'	=> '',
		'Ден.'	=> '',
		'Полик.'	=> '',
		'Март.'	=> '',
		'Анис.'	=> '',
		'Петр.'	=> '',
		'Никл.'	=> '',
		'Бор.'	=> '',
		'Нест.'	=> '',
		'Арх.'	=> '',
		'Мак.'	=> '',
		'Терен.'	=> '',
		'Фридр.'	=> '',
		'Горд.'	=> '',
		'Онуфр.'	=> '',
		'Владисл.'	=> '',
		'Харл.'	=> '',
		'Кир.'	=> '',
		'Ерем.'	=> '',
		'Анан.'	=> '',
		'Никит.'	=> '',
		'Евген.'	=> '',
		'Викент.'	=> '',
		'Зинов.'	=> '',
		'Феод.'	=> '',
		'Ероф.'	=> '',
		'Лаврент.'	=> '',
		'Дмит.'	=> '',
		'Янк.'	=> '',
		'Генр.'	=> '',
		'Феокт.'	=> '',
		'Харламп.'	=> '',
		'Мефод.'	=> '',
		'Лаз.'	=> '',
		'Филим.'	=> '',
		'Тар.'	=> '',
		'Авр.'	=> '',
		'Иван.'	=> '',
		'Макар.'	=> '',
		'Дем.'	=> '',
		'Дионис.'	=> '',
		'Гер.'	=> '',
		'Леон.'	=> '',
		'Христ.'	=> '',
		'Болесл.'	=> '',
		'Ерм.'	=> '',
		'Пантелейм.'	=> '',
		'Антон.'	=> '',
		'Купр.'	=> '',
		'Казим.'	=> '',
		'Авкс.'	=> '',
		'Наз.'	=> '',
		'Иосиф.'	=> '',
		'Констан.'	=> '',
		'Демент.'	=> '',
		'Менд.'	=> '',
	);

	// Расшифровываем вероисповедания
	$religions = array(
		''		=> 0,
		// Православное
		'прав'	=> 1,
		'правосл'	=> 1,
		// Иудейское
		'иуд'	=> 2,
		'иудей'	=> 2,
		// Старообрядческое
		'стар'	=> 3,
		// Магометанское
		'маг'	=> 4,
		'магом'	=> 4,
		'магомет'	=> 4,
		'магометанин'	=> 4,
		// Евангелическое
		'еванг'	=> 5,
		// Католическое
		'кат'	=> 6,
		'католик'	=> 6,
		// Армянско-греческое
		'ар гр'	=> 7,
		'ар григор'	=> 7,
		// Римско-католическое
		'р кат'	=> 8,
		'р катол'	=> 8,
		// Лютеранское
		'лют'	=> 9,
		'лютер'	=> 9,
		// Баптистское
		'бабт'	=> 10,
		'бапт'	=> 10,
		// Молоканское
		'мол'	=> 11,
		'молок'	=> 11,
		'молоканин'	=> 11,
		// Сектантское
		'сект'	=> 12,
		'сектант'	=> 12,
		// Реформаторское
		'реф'	=> 13,
		// Языческое
		'язычник'	=> 14,
		// Евангелическо-лютеранское
		'ев-лют'	=> 15,
	);
	$tmp = trim(preg_replace('/[.-]+/uS', ' ', mb_strtolower($row['religion'])));
// if(defined('P_DEBUG'))	var_export($tmp);
	$row['religion_id'] = $religions[$tmp];

	// Расшифровываем семейные положения
	$maritals = array(
		''		=> 0,
		// Женатые
		'жен'	=> 1,
		'женат'	=> 1,
		// Холостые
		'хол'	=> 2,
		'холост'	=> 2,
		// Вдовые
		'вдов'	=> 3,
	);
	$tmp = trim(preg_replace('/[.-]+/uS', ' ', mb_strtolower($row['marital'])));
// if(defined('P_DEBUG'))	var_export($tmp);
	$row['marital_id'] = $maritals[$tmp];

	// Расшифровываем источники
	if(empty($row['list_nr'])){
		$row['source_id'] = 0;
	}else{
		$result = db_query('SELECT id FROM dic_source WHERE source LIKE "Именной список №' . $row['list_nr'] . ' %"');
		$res = mysql_fetch_array($result, MYSQL_NUM);
		mysql_free_result($result);
		if($res)
			$row['source_id'] = $res[0];
		else{
			db_query('INSERT INTO dic_source (source) VALUES ("Именной список №' . $row['list_nr'] . ' убитым, раненым и без вести пропавшим нижним чинам.")');
			$row['source_id'] = mysql_insert_id();
		}
	}
	
	// Уточняем региональную привязку
	if(!empty($row['uyezd'])){
		$result = db_query('SELECT id FROM dic_region WHERE parent_id = ' . $row['region_id'] . ' AND title LIKE "' . mysql_escape_string($row['uyezd']) . ' %"');
		$res = mysql_fetch_array($result, MYSQL_NUM);
		mysql_free_result($result);
		if($res)
			$row['region_id'] = $res[0];
		else{
			db_query('INSERT INTO dic_region (parent_id, title) VALUES (' . $row['region_id'] . ', "' . mysql_escape_string($row['uyezd']) . ' ")');
			$row['region_id'] = mysql_insert_id();
		}
	}

	// Расшифровываем даты
	if(empty($row['date'])){
		$row['date_from']	= '1914-07-28';
		$row['date_to']		= '1918-11-11';
	}else{
		// Убираем все пробелы
		$row['date'] = preg_replace('/\s+/uS', '', $row['date']);

		// Переводим текстовые названия месяцев в числовые
		$date_fixes = array(
			'/\bянв\b/uS'	=> '01',
			'/\bфев\b/uS'	=> '02',
			'/\bмрт\b/uS'	=> '03',
			'/\bмар\b/uS'	=> '03',
			'/\bапр\b/uS'	=> '04',
			'/\bмая\b/uS'	=> '05',
			'/\bиюн\b/uS'	=> '06',
			'/\bиюл\b/uS'	=> '07',
			'/\bавг\b/uS'	=> '08',
			'/\bсен\b/uS'	=> '09',
			'/\bокт\b/uS'	=> '10',
			'/\bнбр\b/uS'	=> '11',
			'/\bноя\b/uS'	=> '11',
			'/\bдек\b/uS'	=> '12',
		);
		$row['date'] = preg_replace(array_keys($date_fixes), array_values($date_fixes), $row['date']);

		// Простая запись дд.мм.гггг или мм.гггг или гггг
		if(preg_match('/^(?:(?:(\d\d?)\.)?(\d\d?)\.)?((?:\d\d)?\d\d)?$/uS', $row['date'], $matches)){
			if(empty($matches[3]))	$matches[3] = ($matches[2] >= 7 ? 1914 : 1915);
			$matches[4] = $matches[1];
			$matches[5] = $matches[2];
			$matches[6] = $matches[3];

		// Периодическая запись дд-дд.мм.гггг или дд.мм-дд.мм.гггг или дд.мм.гггг-дд.мм.гггг
		}elseif(preg_match('/^(\d\d?)\.?(?:(\d\d?)\.?((?:\d\d)?\d\d)?)?-(\d\d?)\.(\d\d?)\.((?:\d\d)?\d\d)$/uS', $row['date'], $matches)){
			if(empty($matches[6]))	$matches[6] = ($matches[5] >= 7 ? 1914 : 1915);
			if(empty($matches[2]))	$matches[2] = $matches[5];
			if(empty($matches[3]))	$matches[3] = $matches[6];

		// Списочная запись дд,дд,дд.мм.гггг
		}elseif(preg_match('/^(\d\d?),(?:\d\d?,)*(\d\d?)\.(\d\d?)\.((?:\d\d)?\d\d)$/uS', $row['date'], $matches)){
			if(empty($matches[4]))	$matches[4] = ($matches[3] >= 7 ? 1914 : 1915);
			array_splice($matches, 2, 0, array($matches[3], $matches[4]));
		}

		// Нормализуем данные и формируем новые поля
		if(!empty($matches)){
			static $last_days = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

			if($matches[3] < 100)	$matches[3] += 1900;
			if(empty($matches[2]))	$matches[2] = ($matches[3] == 1914 ? '07' : '01');
			if(empty($matches[1]))	$matches[1] = (($matches[3] == 1914) && ($matches[2] == 7) ? '28' : '01');;
			$row['date_from']	= implode('-', array($matches[3], $matches[2], $matches[1]));
			if($matches[6] < 100)	$matches[6] += 1900;
			if(empty($matches[5]))	$matches[5] = ($matches[5] == 1918 ? '11' : '12');
			if(empty($matches[4]))	$matches[4] = (($matches[6] == 1918) && ($matches[5] == 11) ? '11' : $last_days[intval($matches[5])-1]);;
			$row['date_to']	= implode('-', array($matches[6], $matches[5], $matches[4]));
		}
	}

	// Собираем данные для занесения в основную таблицу
if(defined('P_DEBUG'))	var_export($row);
	$have_trouble = false;
	$pub = array();
	foreach(explode(' ', 'id surname name rank religion_id marital_id region_id place reason source_id date date_from date_to source_id list_nr list_pg') as $key){
		if(isset($row[$key]))	
			$pub[$key] = $row[$key];
		else
			$have_trouble = true;
	}
	return $pub;
}

?>