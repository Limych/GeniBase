/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

CREATE TABLE IF NOT EXISTS `gb_agents` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `emails_uris` text COMMENT 'The emails that belong to this person or organization',
  `homepage_uri` varchar(255) DEFAULT NULL COMMENT 'The homepage',
  `openid_uri` varchar(255) DEFAULT NULL COMMENT 'The OpenID of the person or organization',
  `phones_uris` text COMMENT 'The phones that belong to this person or organization',
  `person_id` varchar(36) DEFAULT NULL COMMENT 'Reference to the person that describes this agent',
  `addresses_json` text COMMENT 'The addresses that belong to this person or organization',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_events` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'The type of the name',
  `date_original` text COMMENT 'The original value of the date as supplied by the contributor.',
  `date_formal` varchar(70) DEFAULT NULL COMMENT 'The standardized formal value of the date, formatted according to the GEDCOM X Date Format specification.',
  `date_eday_from` mediumint(9) DEFAULT NULL COMMENT 'The start date of period (for quick search)',
  `date_eday_to` mediumint(9) DEFAULT NULL COMMENT 'The end date of period (for quick search)',
  `place_description_id` varchar(36) DEFAULT NULL COMMENT 'Reference to the place of applicability of this fact',
  `place_description_uri` varchar(255) DEFAULT NULL COMMENT 'Reference to a description of the place being referenced',
  `place_original` text COMMENT 'The original value as supplied by the user',
  `confidence_type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  KEY `type_id` (`type_id`),
  KEY `place_description_id` (`place_description_id`),
  KEY `confidence_type_id` (`confidence_type_id`),
  KEY `lang` (`lang`),
  KEY `date_eday_from` (`date_eday_from`),
  KEY `date_eday_to` (`date_eday_to`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_event_roles` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `event_id` varchar(36) NOT NULL COMMENT 'Reference to event record',
  `type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Enumerated value identifying the participant''s role',
  `person_id` varchar(36) DEFAULT NULL COMMENT 'Reference to the event participant',
  `details` text COMMENT 'Details about the role of participant in the event',
  `confidence_type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `type_id` (`type_id`),
  KEY `person_id` (`person_id`,`type_id`),
  KEY `confidence_type_id` (`confidence_type_id`),
  KEY `lang` (`lang`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_facts` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `person_id` varchar(36) NOT NULL COMMENT 'Reference to person record',
  `primary` bit(1) DEFAULT NULL COMMENT 'Whether this fact is the primary fact of the record from which the subject was extracted',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'The type of the name',
  `date_original` text COMMENT 'The original value of the date as supplied by the contributor.',
  `date_formal` varchar(70) DEFAULT NULL COMMENT 'The standardized formal value of the date, formatted according to the GEDCOM X Date Format specification.',
  `date_eday_from` mediumint(9) DEFAULT NULL COMMENT 'The start date of period (for quick search)',
  `date_eday_to` mediumint(9) DEFAULT NULL COMMENT 'The end date of period (for quick search)',
  `place_description_id` varchar(36) DEFAULT NULL COMMENT 'Reference to the place of applicability of this fact',
  `place_description_uri` varchar(255) DEFAULT NULL COMMENT 'Reference to a description of the place being referenced',
  `place_original` text COMMENT 'The place description as supplied by the user',
  `value` text COMMENT 'The value as supplied by the user',
  `confidence_type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  KEY `type_id` (`type_id`),
  KEY `place_id` (`place_description_id`),
  KEY `person_id` (`person_id`),
  KEY `confidence_type_id` (`confidence_type_id`),
  KEY `lang` (`lang`),
  KEY `date_eday_from` (`date_eday_from`),
  KEY `date_eday_to` (`date_eday_to`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_genders` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `person_id` varchar(36) NOT NULL COMMENT 'Reference to person record',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'Reference to gender type',
  `confidence_type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_uuid` (`person_id`),
  KEY `type_id` (`type_id`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_identifiers` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of parent record',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'The type of the identifier',
  `value` varchar(255) NOT NULL COMMENT 'The value (URI) of identifier',
  PRIMARY KEY (`id`,`type_id`,`value`),
  KEY `value` (`value`),
  KEY `type_id` (`type_id`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_names` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `person_id` varchar(36) NOT NULL COMMENT 'Reference to person record',
  `type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'The type of the name',
  `preferred` bit(1) DEFAULT NULL COMMENT 'Whether the conclusion is preferred above other conclusions of the same type',
  `date_original` text COMMENT 'The original value of the date as supplied by the contributor.',
  `date_formal` varchar(70) DEFAULT NULL COMMENT 'The standardized formal value of the date, formatted according to the GEDCOM X Date Format specification.',
  `date_eday_from` mediumint(9) DEFAULT NULL COMMENT 'The start date of period (for quick search)',
  `date_eday_to` mediumint(9) DEFAULT NULL COMMENT 'The end date of period (for quick search)',
  `confidence_type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  KEY `type_id` (`type_id`),
  KEY `person_uuid` (`person_id`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_name_forms` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of record',
  `name_id` varchar(36) NOT NULL COMMENT 'Reference to name record',
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `full_text` text COMMENT 'The full text of the name form',
  PRIMARY KEY (`_id`),
  KEY `name_id` (`name_id`,`lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

CREATE TABLE IF NOT EXISTS `gb_name_parts` (
  `_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of record',
  `_name_form_id` bigint(20) unsigned NOT NULL COMMENT 'A reference to name form record',
  `type_id` bigint(20) unsigned NOT NULL COMMENT 'ID типа части имени',
  `value` varchar(250) NOT NULL COMMENT 'Значение части имени',
  PRIMARY KEY (`_id`),
  KEY `type_id` (`type_id`,`value`),
  KEY `_name_form_id` (`_name_form_id`,`type_id`,`value`),
  KEY `value` (`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_persons` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `private` bit(1) DEFAULT NULL COMMENT 'A flag that this record for limited distribution or display',
  `living` bit(1) DEFAULT NULL COMMENT 'A flag that this person is living now',
  `confidence_type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_places` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to the type of the place as it is applicable to this description',
  `spatialDescription_uri` varchar(255) DEFAULT NULL COMMENT 'Reference to a geospatial description of this place',
  `spatialDescription_id` varchar(36) DEFAULT NULL COMMENT 'Reference to a geospatial description of this place',
  `jurisdiction_uri` varchar(255) DEFAULT NULL COMMENT 'Reference to a description of the jurisdiction of this place',
  `jurisdiction_id` varchar(36) DEFAULT NULL COMMENT 'Reference to a description of the jurisdiction of this place',
  `geo_calculated` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Label that the geographical coordinates were calculated automatically',
  `latitude` double DEFAULT NULL COMMENT 'Angular distance, in degrees, north or south of the Equator',
  `longitude` double DEFAULT NULL COMMENT 'Angular distance, in degrees, east or west of the Prime Meridian',
  `geo_lat_min` double DEFAULT NULL COMMENT 'Angular distance, in degrees, north or south of the Equator',
  `geo_lon_min` double DEFAULT NULL COMMENT 'Angular distance, in degrees, east or west of the Prime Meridian',
  `geo_lat_max` double DEFAULT NULL COMMENT 'Angular distance, in degrees, north or south of the Equator',
  `geo_lon_max` double DEFAULT NULL COMMENT 'Angular distance, in degrees, east or west of the Prime Meridian',
  `temporalDescription_original` text COMMENT 'The original value of the date as supplied by the contributor.',
  `temporalDescription_formal` varchar(70) DEFAULT NULL COMMENT 'The standardized formal value of the date, formatted according to the GEDCOM X Date Format specification.',
  `temporalDescription_eday_from` mediumint(9) DEFAULT NULL COMMENT 'The start date of period (for quick search)',
  `temporalDescription_eday_to` mediumint(9) DEFAULT NULL COMMENT 'The end date of period (for quick search)',
  `confidence_type_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Reference to confidence type',
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  KEY `latitude` (`latitude`),
  KEY `longitude` (`longitude`),
  KEY `jurisdiction_uuid` (`jurisdiction_id`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_sources` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `about` varchar(255) DEFAULT NULL COMMENT 'The URI (if applicable) of the actual source',
  `mediaType_id` bigint(20) unsigned DEFAULT NULL COMMENT 'Hint about the media (MIME) type of the resource being described',
  `mediator_id` varchar(36) DEFAULT NULL COMMENT 'Reference to the entity that mediates access to the described source',
  `repository_id` varchar(36) DEFAULT NULL COMMENT 'Reference to an agent describing the repository in which the source is found',
  `resourceType_id` bigint(20) unsigned NOT NULL COMMENT 'ID типа источника',
  `sortKey` varchar(255) DEFAULT NULL COMMENT 'A sort key to be used in determining the position of this source relative to other sources in the same collection',
  `rights_json` text COMMENT 'The rights for this source',
  `created` datetime DEFAULT NULL COMMENT 'The date the source was created',
  `modified` datetime DEFAULT NULL COMMENT 'The date the source was last modified',
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  KEY `type` (`resourceType_id`),
  KEY `sortKey` (`sortKey`),
  KEY `created` (`created`),
  KEY `modified` (`modified`),
  KEY `mediator_id` (`mediator_id`),
  KEY `repository_id` (`repository_id`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_source_references` (
  `id` varchar(36) NOT NULL COMMENT 'UUID of record',
  `parent_id` varchar(36) NOT NULL COMMENT 'ID of parent record',
  `is_componentOf` bit(1) NOT NULL DEFAULT b'0',
  `description_uri` varchar(255) NOT NULL DEFAULT '',
  `description_id` varchar(36) DEFAULT NULL,
  `att_contributor` varchar(36) NOT NULL COMMENT 'UUID of the contributor of the attributed data',
  `att_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'The modified timestamp for the attributed data',
  `att_changeMessage` text COMMENT 'The "change message" for the attributed data provided by the contributor',
  PRIMARY KEY (`id`),
  UNIQUE KEY `parent_id` (`parent_id`,`is_componentOf`,`description_uri`),
  KEY `description_id` (`description_id`),
  KEY `att_contributor` (`att_contributor`),
  KEY `att_modified` (`att_modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_text_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of text value',
  `parent_id` varchar(36) NOT NULL COMMENT 'Reference to parent record',
  `group_type_id` bigint(20) unsigned NOT NULL,
  `lang` varchar(50) NOT NULL COMMENT 'IETF BCP 47 locale tag',
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_type_id` (`group_type_id`,`lang`,`value`(255)),
  KEY `parent_uuid` (`parent_id`,`group_type_id`,`lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gb_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Internal ID of data type',
  `uri` varchar(255) NOT NULL COMMENT 'URI of the data type',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uris` (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
