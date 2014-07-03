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
while($row = $result->fetch_array(MYSQL_ASSOC)){
	$drafts[] = $row;
}
$result->free();

// Нормирование данных
foreach($drafts as $row){
if(defined('P_DEBUG'))	print "\n\n======================================\n";
if(defined('P_DEBUG'))	var_export($row);
	$pub = prepublish($row, $have_trouble, $date_norm);
if(defined('P_DEBUG'))	var_export($have_trouble);
if(defined('P_DEBUG'))	var_export($pub);

	// Заносим данные в основную таблицу и обновляем статус в таблице «сырых» данных
	if(!$have_trouble){
		db_query('REPLACE INTO persons (' . implode(', ', array_keys($pub)) . ') VALUES ("' . implode('", "', array_values($pub)) . '")');
	}
	db_query('UPDATE persons_raw SET status = "' . ($have_trouble ? 'Cant publish' : 'Published') . '" WHERE id = ' . $row['id']);
}

if(empty($_SERVER['PHP_SELF']) || (basename($_SERVER['PHP_SELF']) == basename(__FILE__)))	db_close();
?>