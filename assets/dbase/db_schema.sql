/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

CREATE TABLE IF NOT EXISTS `gb_agents` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of record',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `emails_uris` text COMMENT 'The emails that belong to this person or organization',
  `homepage_uri` varchar(255) DEFAULT NULL COMMENT 'The homepage',
  `openid_uri` varchar(255) DEFAULT NULL COMMENT 'The OpenID of the person or organization',
  `phones_uris` text COMMENT 'The phones that belong to this person or organization',
  `person_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to the person that describes this agent',
  `addresses_json` text COMMENT 'The addresses that belong to this person or organization',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_events` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of record',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'The type of the name',
  `date_original` text COMMENT 'The original value of the date as supplied by the contributor.',
  `date_formal` varchar(70) DEFAULT NULL COMMENT 'The standardized formal value of the date, formatted according to the GEDCOM X Date Format specification.',
  `date__from_day` mediumint(9) DEFAULT NULL COMMENT 'The start date of period (for quick search)',
  `date__to_day` mediumint(9) DEFAULT NULL COMMENT 'The end date of period (for quick search)',
  `place_description_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to the place of applicability of this fact',
  `place_description_uri` varchar(255) DEFAULT NULL COMMENT 'Reference to a description of the place being referenced',
  `place_original` text COMMENT 'The original value as supplied by the user',
  `confidence_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_event_roles` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of record',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `_event_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to event record',
  `type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Enumerated value identifying the participant''s role',
  `person_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to the event participant',
  `details` text COMMENT 'Details about the role of participant in the event',
  `confidence_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `id` (`id`),
  KEY `_event_id` (`_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_facts` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of record',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `_person_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to person record',
  `primary` bit(1) DEFAULT NULL COMMENT 'Whether this fact is the primary fact of the record from which the subject was extracted',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'The type of the name',
  `date_original` text COMMENT 'The original value of the date as supplied by the contributor.',
  `date_formal` varchar(70) DEFAULT NULL COMMENT 'The standardized formal value of the date, formatted according to the GEDCOM X Date Format specification.',
  `date__from_day` mediumint(9) DEFAULT NULL COMMENT 'The start date of period (for quick search)',
  `date__to_day` mediumint(9) DEFAULT NULL COMMENT 'The end date of period (for quick search)',
  `place_description_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to the place of applicability of this fact',
  `place_description_uri` varchar(255) DEFAULT NULL COMMENT 'Reference to a description of the place being referenced',
  `place_original` text COMMENT 'The place description as supplied by the user',
  `value` text COMMENT 'The value as supplied by the user',
  `confidence_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `id` (`id`),
  KEY `_person_id` (`_person_id`),
  KEY `type_id` (`type_id`),
  KEY `place_id` (`place_description_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_genders` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of gender',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `_person_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to person record',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to gender type',
  `confidence_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `_person_id` (`_person_id`),
  UNIQUE KEY `id` (`id`),
  KEY `type_id` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_identifiers` (
  `_ref_id` varchar(255) NOT NULL COMMENT 'Public ID of parent record',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'The type of the identifier',
  `value` varchar(255) NOT NULL COMMENT 'The value (URI) of identifier',
  PRIMARY KEY (`_ref_id`,`type_id`,`value`),
  KEY `type_id` (`type_id`),
  KEY `value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_languages` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  PRIMARY KEY (`_id`),
  KEY `lang` (`lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_names` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of record',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `_person_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to person record',
  `type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'The type of the name',
  `preferred` bit(1) DEFAULT NULL COMMENT 'Whether the conclusion is preferred above other conclusions of the same type',
  `date_original` text COMMENT 'The original value of the date as supplied by the contributor.',
  `date_formal` varchar(70) DEFAULT NULL COMMENT 'The standardized formal value of the date, formatted according to the GEDCOM X Date Format specification.',
  `date__from_day` mediumint(9) DEFAULT NULL COMMENT 'The start date of period (for quick search)',
  `date__to_day` mediumint(9) DEFAULT NULL COMMENT 'The end date of period (for quick search)',
  `confidence_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `id` (`id`),
  KEY `_person_id` (`_person_id`),
  KEY `type_id` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_name_forms` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of name form',
  `_name_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to name record',
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `full_text` text COMMENT 'The full text of the name form',
  PRIMARY KEY (`_id`),
  KEY `lang_id` (`lang_id`),
  KEY `_name_id` (`_name_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_name_parts` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of name part',
  `_name_form_id` bigint(20) unsigned NOT NULL COMMENT 'A reference to name form record',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'ID типа части имени',
  `value` text NOT NULL COMMENT 'Значение части имени',
  PRIMARY KEY (`_id`),
  KEY `_name_form_id` (`_name_form_id`),
  KEY `type_id` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_persons` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of person',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `private` bit(1) DEFAULT NULL COMMENT 'A flag that this record for limited distribution or display',
  `living` bit(1) DEFAULT NULL COMMENT 'A flag that this person is living now',
  `confidence_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_places` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of place description',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to the type of the place as it is applicable to this description',
  `spatialDescription_uri` varchar(255) DEFAULT NULL COMMENT 'Reference to a geospatial description of this place',
  `spatialDescription_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to a geospatial description of this place',
  `jurisdiction_uri` varchar(255) DEFAULT NULL COMMENT 'Reference to a description of the jurisdiction of this place',
  `jurisdiction_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to a description of the jurisdiction of this place',
  `_calculatedGeo` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Label that the geographical coordinates were calculated automatically',
  `latitude` double DEFAULT NULL COMMENT 'Angular distance, in degrees, north or south of the Equator',
  `longitude` double DEFAULT NULL COMMENT 'Angular distance, in degrees, east or west of the Prime Meridian',
  `_zoom` tinyint(4) NOT NULL DEFAULT '11' COMMENT 'Map zoom level',
  `temporalDescription_original` text COMMENT 'The original value of the date as supplied by the contributor.',
  `temporalDescription_formal` varchar(70) DEFAULT NULL COMMENT 'The standardized formal value of the date, formatted according to the GEDCOM X Date Format specification.',
  `temporalDescription__from_day` mediumint(9) DEFAULT NULL COMMENT 'The start date of period (for quick search)',
  `temporalDescription__to_day` mediumint(9) DEFAULT NULL COMMENT 'The end date of period (for quick search)',
  `confidence_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `id` (`id`),
  KEY `jurisdiction_id` (`jurisdiction_id`),
  KEY `latitude` (`latitude`),
  KEY `longitude` (`longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_sources` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID источника',
  `id` varchar(255) NOT NULL COMMENT 'Public ID of record',
  `about` varchar(255) DEFAULT NULL COMMENT 'The URI (if applicable) of the actual source',
  `mediaType_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Hint about the media (MIME) type of the resource being described',
  `mediator_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to the entity that mediates access to the described source',
  `repository_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to an agent describing the repository in which the source is found',
  `resourceType_id` bigint(20) unsigned NOT NULL COMMENT 'ID типа источника',
  `sortKey` varchar(255) DEFAULT NULL COMMENT 'A sort key to be used in determining the position of this source relative to other sources in the same collection',
  `rights_json` text COMMENT 'The rights for this source',
  `created` datetime DEFAULT NULL COMMENT 'The date the source was created',
  `modified` datetime DEFAULT NULL COMMENT 'The date the source was last modified',
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `id` (`id`),
  KEY `type` (`resourceType_id`),
  KEY `sortKey` (`sortKey`),
  KEY `created` (`created`),
  KEY `modified` (`modified`),
  KEY `mediator_id` (`mediator_id`),
  KEY `repository_id` (`repository_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_source_references` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of record',
  `_source_id` bigint(20) unsigned NOT NULL COMMENT 'ID of parent record',
  `is_componentOf` bit(1) NOT NULL DEFAULT b'0',
  `description_uri` varchar(255) NOT NULL DEFAULT '',
  `description_id` bigint(20) unsigned DEFAULT NULL,
  `att_contributor_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `_source_id` (`_source_id`,`is_componentOf`,`description_uri`),
  KEY `description_id` (`description_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_text_values` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `_group` bigint(20) unsigned NOT NULL,
  `_ref_id` bigint(20) unsigned NOT NULL,
  `lang_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to locale tag',
  `value` varchar(2048) NOT NULL,
  PRIMARY KEY (`_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_types` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of data type',
  `uri` varchar(255) NOT NULL COMMENT 'URI of the data type',
  PRIMARY KEY (`_id`),
  UNIQUE KEY `uris` (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DELIMITER //
CREATE DEFINER=`root`@`%` PROCEDURE `geobox`(
	IN src_lat DECIMAL(9,6), IN src_lon DECIMAL(9,6), IN dist DECIMAL(6,2),
	OUT lat_top DECIMAL(9,6), OUT lon_lft DECIMAL(9,6),
	OUT lat_bot DECIMAL(9,6), OUT lon_rgt DECIMAL(9,6)
)
    DETERMINISTIC
    COMMENT 'Calculate coordinates of search area around geolocation point'
BEGIN
	SET lat_top := src_lat + (dist / 69);
	SET lon_lft := src_lon - (dist / ABS(COS(RADIANS(src_lat)) * 69));
	SET lat_bot := src_lat - (dist / 69);
	SET lon_rgt := src_lon + (dist / ABS(COS(RADIANS(src_lat)) * 69));
END//
DELIMITER ;

DELIMITER //
CREATE DEFINER=`root`@`%` PROCEDURE `geobox_pt`(
	IN pt POINT, IN dist DECIMAL(6,2),
	OUT top_lft POINT, OUT bot_rgt POINT
)
    DETERMINISTIC
    COMMENT 'Calculate coordinates of search area around geolocation point (POINT datatype version)'
BEGIN
	CALL geobox(X(pt), Y(pt), dist, @lat_top, @lon_lft, @lat_bot, @lon_rgt);
	SET top_lft := POINT(@lat_top, @lon_lft);
	SET bot_rgt := POINT(@lat_bot, @lon_rgt);
END//
DELIMITER ;

DELIMITER //
CREATE DEFINER=`root`@`%` FUNCTION `geodist`(
	src_lat DECIMAL(9,6), src_lon DECIMAL(9,6),
	dst_lat DECIMAL(9,6), dst_lon DECIMAL(9,6)
) RETURNS decimal(6,2)
    DETERMINISTIC
    COMMENT 'Calculate distance between two geolocation points'
BEGIN
	SET @dist := 6371 * 2 * ASIN(SQRT(
		POWER(SIN((src_lat - ABS(dst_lat)) * PI()/180 / 2), 2) +
		COS(src_lat * PI()/180) *
		COS(ABS(dst_lat) * PI()/180) *
		POWER(SIN((src_lon - dst_lon) * PI()/180 / 2), 2)
	));
	RETURN @dist;
END//
DELIMITER ;

DELIMITER //
CREATE DEFINER=`root`@`%` FUNCTION `geodist_pt`(src POINT, dst POINT) RETURNS decimal(6,2)
    DETERMINISTIC
    COMMENT 'Calculate distance between two geolocation points (POINT datatype version)'
BEGIN
	RETURN geodist(X(src), Y(src), X(dst), Y(dst));
END//
DELIMITER ;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
