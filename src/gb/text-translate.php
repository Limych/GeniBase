<?php
/**
 * These functions are needed to translate pronounce or scripting
 * of words from one language to another.
 * 
 * @since	2.2.3
 *
 * @package	GeniBase
 * @subpackage i18n
 *
 * @copyright	Copyright © 2015, Andrey Khrolenok (andrey@khrolenok.ru)
 */

// Direct execution forbidden for this script
if( !defined('GB_VERSION') || count(get_included_files()) == 1)	die('<b>ERROR:</b> Direct execution forbidden!');



define('GB_TEXT_TRANSLIT',		1);
define('GB_TEXT_TRANSCRIPT',	2);

function gb_text_translate($text, $from_locale, $to_locale, $mode = null){
	global $gb_trans;

	$text = trim($text);
	if(empty($text))
		return $text;

	if( $mode === null )
		$mode = GB_TEXT_TRANSLIT;

	if(!is_array($gb_trans) || !is_array($gb_trans[$mode]))
		return false;
	if(!is_array($gb_trans[$mode][$from_locale])){
		$from_locale = strtok($from_locale, '_');
		if(!is_array($gb_trans[$mode][$from_locale]))
			return false;
	}
	if(!is_array($gb_trans[$mode][$from_locale][$to_locale])){
		$to_locale = strtok($to_locale, '_');
		if(!is_array($gb_trans[$mode][$from_locale][$to_locale]))
			return false;
	}
	
	// If needed split text to single words and translate it separately
	if(preg_match('/\W/uS', $text)){
		$words = preg_split('/(\W+)/uS', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
		for($i = 0; $i < count($words); $i += 2)
			$words[$i] = gb_text_translate($words[$i], $to_locale);
		return implode($words);
	}
	
	$old_enc = mb_internal_encoding();
	mb_internal_encoding('UTF-8');
	$tr = $gb_trans[$mode][$from_locale];

	// Detect word's case
	$case = 0;	// abcd
	$t = mb_substr($text, 0, 1);
	if($t != mb_strtolower($t)){
		$case = 1;	// Abcd
		$t = mb_substr($text, 1);
		if($t != mb_strtolower($t))	$case = 2;	// ABCD
	}

	// Translate word
	$text = mb_strtolower($text);
	if(isset($tr[$to_locale.'-special']) && !empty($tr[$to_locale.'-special']))
		$text = preg_replace(array_keys($tr[$to_locale.'-special']), array_values($tr[$to_locale.'-special']), $text);
	if(isset($tr[$to_locale]) && !empty($tr[$to_locale]))
		$text = strtr($text, $tr[$to_locale]);

	// Restore initial word's case
	switch($case){
		case 1:	// Abcd
			$text = mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
			break;
		case 2:	// ABCD
			$text = mb_strtoupper($text);
			break;
	}

	mb_internal_encoding($old_enc);
	return $text;
}

function gb_text_translate_add($from_locale, $to_locale, $mode, $tr, $tr_special = null){
	global $gb_trans;

	if( !is_array($gb_trans) )
		$gb_trans = array();
	if( !is_array($gb_trans[$mode]) )
		$gb_trans[$mode] = array();
	if( !is_array($gb_trans[$mode][$from_locale]) )
		$gb_trans[$mode][$from_locale] = array();

	if( !empty($tr_special) )
		$gb_trans[$mode][$from_locale][$to_locale.'-special'] = $tr_special;

	$gb_trans[$mode][$from_locale][$to_locale] = $tr;
}

function gb_text_translate_init(){
	// Русско-английская транскрипция (произношение) и транслитерация (написание)
	$tr_special = array(
			'/\bе/uS' => 'ye',		'/ий\b/uS' => 'iy',	'/ой\b/uS' => 'oy',
			'/ее\b/uS' => 'eye',	'/ое\b/uS' => 'oye',	'/ая\b/uS' => 'aya',
			'/яя\b/uS' => 'yaya',	'/ия\b/uS' => 'iya',	'/ие\b/uS' => 'iye',
			'/ые\b/uS' => 'yye',
			'/\bсергей\b/uS' => 'sergey',		'/\bюрий\b/uS' => 'yuri',
	);
	$tr = array(
			'а' => 'a',			'ай' => 'ai',		'б' => 'b',			'в' => 'v',
			'г' => 'g',			'д' => 'd',			'е' => 'e',			'ё' => 'yo',
			'ей' => 'ei',		'ёй' => 'yoi',		'ж' => 'zh',		'жё' => 'zho',
			'же' => 'zhe',		'жёй' => 'zhoi',	'з' => 'z',			'и' => 'i',
			'ий' => 'ii',		'й' => 'y',			'к' => 'k',			'л' => 'l',
			'м' => 'm',			'н' => 'n',			'о' => 'o',			'ой' => 'oi',
			'п' => 'p',			'р' => 'r',			'с' => 's',			'т' => 't',
			'у' => 'u',			'уй' => 'ui',		'ф' => 'f',			'х' => 'kh',
			'ц' => 'ts',		'ч' => 'ch',		'чё' => 'cho',		'че' => 'che',
			'чёй' => 'choi',	'ш' => 'sh',		'шё' => 'sho',		'ше' => 'she',
			'шёй' => 'shoi',	'щ' => 'shch',		'щё' => 'shcho',	'ще' => 'shche',
			'щёй' => 'shchoi',	'ъ' => '',			'ъе' => 'ye',		'ъё' => 'yo',
			'ъи' => 'yi',		'ъо' => 'yo',		'ъо' => 'yo',		'ъю' => 'yu',
			'ъя' => 'ya',		'ы' => 'y',			'ый' => 'yi',		'ь' => '',
			'ье' => 'ye',		'ьё' => 'yo',		'ьи' => 'yi',		'ьо' => 'yo',
			'ьо' => 'yo',		'ью' => 'yu',		'ья' => 'ya',		'э' => 'e',
			'эй' => 'ei',		'ю' => 'yu',		'юй' => 'yui',		'я' => 'ya',
			'яй' => 'yai',
	);
	gb_text_translate_add('ru', 'en', GB_TEXT_TRANSLIT, $tr, $tr_special);
	gb_text_translate_add('ru', 'en', GB_TEXT_TRANSCRIPT, $tr, $tr_special);
	
	// Русско-немецкая транскрипция (произношение)
	$tr = array(
			'а' => 'a',		'б' => 'b',		'в' => 'w',	'г' => 'g',	'д' => 'd',		'е' => 'je',
			'ё' => 'jo',	'ж' => 'sh',	'з' => 's',	'и' => 'i',	'й' => 'j',		'к' => 'k',
			'л' => 'l',		'м' => 'm',		'н' => 'n',	'о' => 'o',	'п' => 'p',		'р' => 'r',
			'с' => 's',		'т' => 't',		'у' => 'u',	'ф' => 'f',	'х' => 'ch',	'ц' => 'z',
			'ч' => 'tsch',	'ш' => 'sch',	'щ' => 'schtsch',	'ь' => '\'',	'ы' => 'y',
			'ъ' => '\'',	'э' => 'e',		'ю' => 'yu',	'я' => 'ja',
	);
	gb_text_translate_add('ru', 'ge', GB_TEXT_TRANSCRIPT, $tr);

	// Русско-немецкая транслитерация (написание)
	$tr_special = array(
			'/ой/uS'	=> 'äu',		'/кк/uS'	=> 'ck',		'/кр/uS'	=> 'chr',
			'/ай/uS'	=> 'ei',		'/кв/uS'	=> 'qu',		'/шп/uS'	=> 'sp',
			'/шт/uS'	=> 'st',
	);
	$tr = array(
			'а' => 'a',		'б' => 'b',		'в' => 'v',	'г' => 'g',	'д' => 'd',		'е' => 'ä',
			'ё' => 'ö',		'ж' => 'zh',	'з' => 's',	'и' => 'i',	'й' => 'j',		'к' => 'k',
			'л' => 'l',		'м' => 'm',		'н' => 'n',	'о' => 'o',	'п' => 'p',		'р' => 'r',
			'с' => 's',		'т' => 't',		'у' => 'u',	'ф' => 'f',	'х' => 'ch',	'ц' => 'z',
			'ч' => 'ĉ',		'ш' => 'ŝ',		'щ' => 'ŝĉ',	'ь' => '\'',	'ы' => 'y',
			'ъ' => '\'',	'э' => 'ä',		'ю' => 'ü',	'я' => 'ja',
			'готфрид' => 'gottfried',		'вильгельм' => 'wilhelm',	'иоган' => 'johann',
			'август' => 'august',			'готлиб' => 'gottlieb',		'людвиг' => 'ludwig',
	);
	gb_text_translate_add('ru', 'ge', GB_TEXT_TRANSLIT, $tr);

	// Русско-польская транскрипция (произношение)
	$tr_special = array(
			'/(?<=\b|[аоэиуыеёюяъь])е/uS' => 'jo',	'/(?<=[жцчшщ])е/uS' => 'o',
			'/(?<=\b|[аоэиуыеёюяъь])ё/uS' => 'je',	'/(?<=[жцчшщ])ё/uS' => 'e',
			'/(?<=[ь])и/uS' => 'ji',				'/(?<=[жцш])и/uS' => 'y',
			'/л(?=[иья])/uS' => 'l',
			'/(?<=\b|[аоэиуыеёюяъь])ю/uS' => 'ju',
			'/(?<=\b|[аоэиуыеёюяъь])я/uS' => 'ja',
	);
	$tr = array(
			'а' => 'a',		'б' => 'b',		'в' => 'w',		'г' => 'g',		'д' => 'd',
			'е' => 'ie',	'ё' => 'io',	'ж' => 'ż',		'з' => 'z',		'и' => 'i',
			'й' => 'j',		'к' => 'k',		'л' => 'ł',		'ле' => 'lo',	'лё' => 'le',
			'лю' => 'lu',	'ля' => 'la',	'м' => 'm',		'н' => 'n',		'о' => 'o',
			'п' => 'p',		'р' => 'r',		'с' => 's',		'т' => 't',		'у' => 'u',
			'ф' => 'f',		'х' => 'ch',	'ц' => 'c',		'ч' => 'cz',	'ш' => 'sz',
			'щ' => 'szcz',	'ъ' => '',		'ы' => 'y',		'ь' => '\'',	'э' => 'e',
			'ю' => 'iu',	'я' => 'ia',
	);
	gb_text_translate_add('ru', 'pl', GB_TEXT_TRANSCRIPT, $tr);

	// Русско-польская транслитерация (написание)
	$tr_special = array(
			'/ский\b/uS' => 'ski',		'/цкий\b/uS' => 'cki',		'/ий\b/uS' => 'i',
			'/ый\b/uS' => 'y',			'/ой\b/uS' => 'oj',			'/ской\b/uS' => 'skoj',
			'/цкой\b/uS' => 'ckoj',		'/инский\b/uS' => 'inski',	'/инская\b/uS' => 'inska',
			'/ская\b/uS' => 'ska',		'/цкая\b/uS' => 'cka',
			'/цкая\b/uS' => 'cka',
	);
	$tr = array(
			'а' => 'a',	'б' => 'b',	'в' => 'v',	'г' => 'g',	'д' => 'd',	'е' => 'e',	'ё' => 'ё',
			'ж' => 'ž',	'з' => 'z',	'и' => 'i',	'й' => 'j',	'к' => 'k',	'л' => 'l',	'м' => 'm',
			'н' => 'n',	'о' => 'o',	'п' => 'p',	'р' => 'r',	'с' => 's',	'т' => 't',	'у' => 'u',
			'ф' => 'f',	'х' => 'h',	'ц' => 'c',	'ч' => 'č',	'ш' => 'š',	'щ' => 'ŝ',	'ъ' => '″',
			'ы' => 'y',	'ь' => '′',	'э' => 'è',	'ю' => 'û',	'я' => 'â',
	);
	gb_text_translate_add('ru', 'pl', GB_TEXT_TRANSLIT, $tr);

	// Русско-финская транскрипция (произношение) и транслитерация (написание)
	$tr_special = array(
			'/(?<=\b|[аоэиуыеёюяъь])е/uS' => 'je',		'/(?<=[жчшщ])ё/uS' => 'o',
			'/(?<=ь)и/uS' => 'ji',
			'/ий\b/uS' => 'i',		'/(?<=\b|[и])й\B/uS' => 'j',
	);
	$tr = array(
			'а' => 'a',		'б' => 'b',		'в' => 'v',		'г' => 'g',		'д' => 'd',
			'е' => 'e',		'ё' => 'jo',	'ж' => 'ž',		'з' => 'z',		'и' => 'i',
			'й' => 'i',		'к' => 'k',		'л' => 'l',		'м' => 'm',		'н' => 'n',
			'о' => 'o',		'п' => 'p',		'р' => 'r',		'с' => 's',		'т' => 't',
			'у' => 'u',		'ф' => 'f',		'х' => 'h',		'ц' => 'ts',	'ч' => 'tš',
			'ш' => 'š',		'щ' => 'štš',	'ъ' => '',		'ы' => 'y',		'ь' => '',
			'э' => 'e',		'ю' => 'ju',	'я' => 'ja',
			'вейна' => 'wayne',		'вайне' => 'wayne',
	);
	gb_text_translate_add('ru', 'fi', GB_TEXT_TRANSCRIPT, $tr, $tr_special);
	gb_text_translate_add('ru', 'fi', GB_TEXT_TRANSLIT, $tr, $tr_special);

	// Польско-русская транскрипция (произношение) и транслитерация (написание)
	// https://ru.wikipedia.org/wiki/%D0%9F%D0%BE%D0%BB%D1%8C%D1%81%D0%BA%D0%BE-%D1%80%D1%83%D1%81%D1%81%D0%BA%D0%B0%D1%8F_%D0%BF%D1%80%D0%B0%D0%BA%D1%82%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B0%D1%8F_%D1%82%D1%80%D0%B0%D0%BD%D1%81%D0%BA%D1%80%D0%B8%D0%BF%D1%86%D0%B8%D1%8F
	$tr_special = array(
			'/\be/uS' => 'э',
			'/\bja/uS' => 'я',
			'/\bje/uS' => 'е',
			'/\bjo/uS' => 'йо',
			'/\bjó/uS' => 'ю',
			'/\bju/uS' => 'ю',
			'//uS' => '',
			'//uS' => '',
			'//uS' => '',
	);
	$tr = array(
			'ąb' => 'омб',
			'ąp' => 'омп',
			'ą' => 'он',
			'b' => 'б',
			'c' => 'ц',
			'ć' => 'ць',
			'ch' => 'х',
			'cz' => 'ч',
			'd' => 'д',
			'dż' => 'дж',
			'dz' => 'дз',
			'e' => 'е',
			'ęb' => 'емб',
			'ęp' => 'емп',
			'ę' => 'ен',
			'f' => 'ф',
			'g' => 'г',
			'h' => 'х',
			'ia' => 'я',
			'ie' => 'е',
			'io' => 'ё',
			'ió' => 'ю',
			'iu' => 'ю',
			'k' => 'к',
			'ł' => 'л',
			'm' => 'м',
			'n' => 'н',
			'ń' => 'нь',
			'p' => 'п',
			'r' => 'р',
			's' => 'с',
			'sz' => 'ш',
			'szcz' => 'щ',
			't' => 'т',
			'w' => 'в',
			'ż' => 'ж',
			'z' => 'з',
			'' => '',
	);
	gb_text_translate_add('pl', 'ru', GB_TEXT_TRANSCRIPT, $tr, $tr_special);
	gb_text_translate_add('pl', 'ru', GB_TEXT_TRANSLIT, $tr, $tr_special);
}

gb_text_translate_init();
