SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

CREATE TABLE IF NOT EXISTS `activity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `activity_type_id` int(10) unsigned NOT NULL DEFAULT '0',
  `type_id` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `time` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_index` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `articles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `time` bigint(20) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `contents` mediumtext COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `order` int(10) unsigned NOT NULL,
  `active` int(1) NOT NULL DEFAULT '1',
  `visibility` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `active` int(1) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `message` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type_id_object_id_index` (`type_id`,`object_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `comment_summary` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `count` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `all_unique` (`type_id`,`object_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_verify` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `hash` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `all_unique` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `message` varchar(500) COLLATE utf8_unicode_ci NOT NULL,
  `time` bigint(20) unsigned NOT NULL,
  `time_end` bigint(20) unsigned NOT NULL,
  `active` int(1) NOT NULL DEFAULT '1',
  `visibility` int(10) unsigned NOT NULL,
  `location_latitude` double NOT NULL,
  `location_longitude` double NOT NULL,
  `location_title` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_attendants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_summary` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `going` int(10) unsigned NOT NULL,
  `not_going` int(10) unsigned NOT NULL,
  `maybe` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `feed` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `active` int(1) NOT NULL,
  `visibility` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `feed_type` int(10) unsigned NOT NULL,
  `feed_status_id` int(10) unsigned NOT NULL,
  `feed_image_id` int(10) unsigned NOT NULL,
  `feed_link_id` int(10) unsigned NOT NULL,
  `feed_extra_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created` (`created`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `feed_aggregations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `feed_type` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `created` int(20) unsigned NOT NULL,
  `post_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `feed_extra` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `data` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `feed_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `image` char(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `feed_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` char(100) NOT NULL,
  `description` char(200) NOT NULL,
  `host` char(100) NOT NULL,
  `url` char(250) NOT NULL,
  `image` char(250) NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `feed_read` (
  `user_id` int(10) unsigned NOT NULL,
  `post_id` int(10) unsigned NOT NULL,
  `status` int(1) unsigned NOT NULL,
  UNIQUE KEY `user_id` (`user_id`,`post_id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `feed_read_summary` (
  `user_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `read_count` int(10) unsigned NOT NULL,
  UNIQUE KEY `user_id` (`user_id`,`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `feed_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message` varchar(500) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gallery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `title` char(250) COLLATE utf8_unicode_ci NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `time` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `active` int(1) NOT NULL DEFAULT '1',
  `visibility` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `gallery_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gallery_id` int(10) unsigned NOT NULL,
  `image` char(250) COLLATE utf8_unicode_ci NOT NULL,
  `message` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
  `time` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `gallery_id` (`gallery_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `groups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` char(250) COLLATE utf8_unicode_ci DEFAULT NULL,
  `link` char(250) COLLATE utf8_unicode_ci NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `image` char(250) COLLATE utf8_unicode_ci NOT NULL,
  `privacy` int(10) unsigned NOT NULL,
  `active` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_admins` (
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) NOT NULL,
  `location_country` int(10) unsigned NOT NULL,
  `location_region` int(10) unsigned NOT NULL,
  `location_city` int(10) unsigned NOT NULL,
  `location_latitude` double NOT NULL,
  `location_longitude` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `all_unique` (`group_id`),
  KEY `location_latitude_index` (`location_latitude`),
  KEY `location_longitude_index` (`location_longitude`),
  KEY `location_country_index` (`location_country`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_members` (
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `message` varchar(250) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `group_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `issued_user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `all_unique` (`group_id`,`user_id`,`type`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `group_summary` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL,
  `members_count` int(10) unsigned NOT NULL,
  `posts_count` int(10) unsigned NOT NULL,
  `posts_visible` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_id` (`group_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `group_tags` (
  `group_id` int(10) unsigned NOT NULL,
  `tag_id` int(10) unsigned NOT NULL,
  KEY `group_id` (`group_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `location_cities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` char(2) COLLATE utf8_unicode_ci NOT NULL,
  `region_code` char(20) COLLATE utf8_unicode_ci NOT NULL,
  `geoname_id` int(10) unsigned NOT NULL,
  `name` char(200) COLLATE utf8_unicode_ci NOT NULL,
  `latitude` double NOT NULL,
  `longitude` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `geoname_id` (`geoname_id`),
  KEY `latitude` (`latitude`),
  KEY `longitude` (`longitude`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `location_countries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `country_code` char(2) COLLATE utf8_unicode_ci NOT NULL,
  `name` char(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `country_code` (`country_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `location_regions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `region_code` char(20) COLLATE utf8_unicode_ci NOT NULL,
  `country_code` char(2) COLLATE utf8_unicode_ci NOT NULL,
  `name` char(200) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `region_code` (`region_code`,`country_code`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `message` varchar(500) NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `conversation_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `message_conversations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `last_user_id` int(10) unsigned NOT NULL,
  `last_message_id` int(10) unsigned NOT NULL,
  `updated` int(10) unsigned NOT NULL,
  `user_count` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `message_conversation_users` (
  `conversation_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` int(20) unsigned NOT NULL,
  `last_read_message_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `conversation_id` (`conversation_id`,`user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `moderation_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` char(250) COLLATE utf8_unicode_ci NOT NULL,
  `password` binary(64) NOT NULL,
  `ip` char(45) COLLATE utf8_unicode_ci NOT NULL,
  `name` char(210) COLLATE utf8_unicode_ci NOT NULL,
  `session` binary(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `session` (`session`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `group_id` int(10) unsigned NOT NULL,
  `status` int(1) unsigned NOT NULL DEFAULT '0',
  `notification_type_id` int(10) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `time` bigint(20) unsigned NOT NULL,
  `from_user_count` int(10) unsigned NOT NULL,
  `from_user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_index` (`user_id`),
  KEY `time` (`time`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_summary` (
  `user_id` int(10) unsigned NOT NULL,
  `notification_count` int(10) unsigned NOT NULL,
  `read_count` int(10) unsigned NOT NULL,
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `notification_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_id` (`notification_id`,`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `session` binary(20) NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_index` (`session`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `subscription_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `session` binary(15) NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_index` (`session`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` char(100) COLLATE utf8_unicode_ci NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` char(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_name` char(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `birthday` char(8) COLLATE utf8_unicode_ci NOT NULL,
  `email` char(250) COLLATE utf8_unicode_ci NOT NULL,
  `password` binary(64) NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `active` int(1) NOT NULL,
  `gender` int(2) NOT NULL DEFAULT '1',
  `image` char(250) COLLATE utf8_unicode_ci NOT NULL,
  `verified` int(1) NOT NULL DEFAULT '0',
  `ip` char(45) COLLATE utf8_unicode_ci NOT NULL,
  `name` char(210) COLLATE utf8_unicode_ci NOT NULL,
  `privacy` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_follow` (
  `user_id` int(10) unsigned NOT NULL,
  `follow_user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  KEY `follow_user_id_index` (`follow_user_id`),
  KEY `user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `location_country` int(10) unsigned NOT NULL,
  `location_region` int(10) unsigned NOT NULL,
  `location_city` int(10) unsigned NOT NULL,
  `location_latitude` double NOT NULL,
  `location_longitude` double NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `all_unique` (`user_id`),
  KEY `location_latitude_index` (`location_latitude`),
  KEY `location_longitude_index` (`location_longitude`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reported_user_id` int(10) unsigned NOT NULL,
  `message` varchar(250) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `type_id` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `user_summary` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `followers` int(10) unsigned NOT NULL,
  `following` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `voting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `created` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `voting_type` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `all_unique` (`type_id`,`object_id`,`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `voting_summary` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int(10) unsigned NOT NULL,
  `object_id` int(10) unsigned NOT NULL,
  `like` int(10) unsigned NOT NULL,
  `dislike` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `all_unique` (`type_id`,`object_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
