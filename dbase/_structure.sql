-- phpMyAdmin SQL Dump
-- version 
-- http://www.phpmyadmin.net
--
-- Время создания: Июл 07 2014 г., 17:09
-- Версия сервера: 5.5.28
-- Версия PHP: 5.4.4-14+deb7u11

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `u62106_1914`
--

-- --------------------------------------------------------

--
-- Структура таблицы `dic_marital`
--

CREATE TABLE IF NOT EXISTS `dic_marital` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `marital` varchar(50) NOT NULL COMMENT '',
  `marital_cnt` int(10) unsigned NOT NULL COMMENT '',
  `update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Временная отметка последнего обновления записи',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=6 ;

-- --------------------------------------------------------

--
-- Структура таблицы `dic_reason`
--

CREATE TABLE IF NOT EXISTS `dic_reason` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `reason` varchar(70) NOT NULL COMMENT '',
  `reason_cnt` int(10) unsigned NOT NULL COMMENT '',
  `update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Временная отметка последнего обновления записи',
  PRIMARY KEY (`id`),
  KEY `reason` (`reason`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=48 ;

-- --------------------------------------------------------

--
-- Структура таблицы `dic_region`
--

CREATE TABLE IF NOT EXISTS `dic_region` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `parent_id` int(10) unsigned NOT NULL COMMENT '',
  `title` varchar(150) NOT NULL COMMENT '',
  `region` varchar(250) NOT NULL COMMENT '',
  `region_ids` varchar(150) NOT NULL COMMENT '',
  `region_comment` varchar(250) NOT NULL COMMENT '',
  `region_cnt` int(10) unsigned NOT NULL COMMENT 'Число записей в этом регионе',
  `update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Временная отметка последнего обновления записи',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  FULLTEXT KEY `region` (`region`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=665 ;

-- --------------------------------------------------------

--
-- Структура таблицы `dic_religion`
--

CREATE TABLE IF NOT EXISTS `dic_religion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `religion` varchar(200) NOT NULL COMMENT '',
  `religion_cnt` int(10) unsigned NOT NULL COMMENT '',
  `update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Временная отметка последнего обновления записи',
  PRIMARY KEY (`id`),
  KEY `religion` (`religion`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=20 ;

-- --------------------------------------------------------

--
-- Структура таблицы `dic_source`
--

CREATE TABLE IF NOT EXISTS `dic_source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `source` varchar(250) NOT NULL COMMENT '',
  `source_url` varchar(250) NOT NULL COMMENT '',
  `pg_correction` int(11) NOT NULL COMMENT '',
  `update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Временная отметка последнего обновления записи',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=823 ;

-- --------------------------------------------------------

--
-- Структура таблицы `persons`
--

CREATE TABLE IF NOT EXISTS `persons` (
  `id` int(10) unsigned NOT NULL COMMENT 'ID записи',
  `surname` varchar(70) NOT NULL COMMENT 'Фамилия',
  `surname_key` varchar(50) NOT NULL COMMENT 'Вычисляемый ключ фамилии для фонетического поиска',
  `name` varchar(70) NOT NULL COMMENT 'Имя-отчество',
  `rank` varchar(200) NOT NULL COMMENT 'Воинское звание',
  `religion_id` int(10) unsigned NOT NULL COMMENT 'ID вероисповедания',
  `marital_id` int(10) unsigned NOT NULL COMMENT 'ID семейного положения',
  `region_id` int(10) unsigned NOT NULL COMMENT 'ID географического региона, откуда родом человек',
  `place` varchar(200) NOT NULL COMMENT '',
  `reason_id` int(10) unsigned NOT NULL COMMENT '',
  `date` varchar(100) NOT NULL COMMENT '',
  `date_from` date NOT NULL COMMENT '',
  `date_to` date NOT NULL COMMENT '',
  `source_id` int(10) unsigned NOT NULL COMMENT '',
  `list_nr` int(10) unsigned NOT NULL COMMENT '',
  `list_pg` int(10) unsigned NOT NULL COMMENT '',
  `comments` text NOT NULL COMMENT '',
  `update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Временная отметка последнего обновления записи',
  PRIMARY KEY (`id`),
  KEY `surname_key` (`surname_key`),
  KEY `marital_id` (`marital_id`),
  KEY `list_nr` (`list_nr`),
  KEY `date_from` (`date_from`),
  KEY `date_to` (`date_to`),
  KEY `rank` (`rank`),
  KEY `reason_id` (`reason_id`),
  FULLTEXT KEY `surname` (`surname`),
  FULLTEXT KEY `name` (`name`),
  FULLTEXT KEY `place` (`place`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Структура таблицы `persons_raw`
--

CREATE TABLE IF NOT EXISTS `persons_raw` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID записи',
  `status` set('Draft','Published','Cant publish') NOT NULL DEFAULT 'Draft',
  `surname` varchar(70) NOT NULL COMMENT '',
  `name` varchar(70) NOT NULL COMMENT '',
  `rank` varchar(200) NOT NULL COMMENT '',
  `religion` varchar(150) NOT NULL COMMENT '',
  `marital` varchar(100) NOT NULL COMMENT '',
  `region_id` int(11) unsigned NOT NULL COMMENT '',
  `uyezd` varchar(200) NOT NULL COMMENT '',
  `place` varchar(200) NOT NULL COMMENT '',
  `reason` varchar(100) NOT NULL COMMENT '',
  `date` varchar(100) NOT NULL COMMENT '',
  `list_nr` int(10) unsigned NOT NULL COMMENT '',
  `list_pg` int(10) unsigned NOT NULL COMMENT '',
  `update_datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Временная отметка последнего обновления записи',
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=249848 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
