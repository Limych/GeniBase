#!/usr/bin/php -q 
<?php
/**
 * Tell GeniBase we are doing the CRON task.
 * @var bool
 */
define('DOING_CRON', true);

require_once('gb/gb.php');	// Общие функции системы

/**
 * Формализация и публикация записей
 */

if(empty($_SERVER['PHP_SELF']) || (basename($_SERVER['PHP_SELF']) == basename(__FILE__)))	@header('Content-Type: text/plain; charset=utf-8');

publish_cron(true);
db_update();
