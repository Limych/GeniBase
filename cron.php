#!/usr/bin/php -q 
<?php
require_once('functions.php');	// Общие функции системы

/**
 * Формализация и публикация записей
 */

if(empty($_SERVER['PHP_SELF']) || (basename($_SERVER['PHP_SELF']) == basename(__FILE__)))	header('Content-Type: text/plain; charset=utf-8');

publish_cron(true);

db_close();
?>