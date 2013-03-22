ALTER TABLE `twitter_searches` DROP `created_at`, DROP `tweets_per_hour`;
ALTER TABLE  `twitter_searches` ADD `tweets_last_24_hours` INT NULL , ADD `tweets_this_month` INT NULL;

ALTER TABLE `twitter_lists` DROP `created_at`, DROP `tweets_per_hour`;
ALTER TABLE `twitter_lists` ADD `tweets_last_24_hours` INT NULL, ADD `tweets_this_month` INT NULL;

ALTER TABLE  `facebook_pages` ADD `posts_last_24_hours` INT NULL, ADD `posts_this_month` INT NULL;

ALTER TABLE  `campaigns` ADD  `timezone` VARCHAR( 30 ) NOT NULL DEFAULT  'Europe/London';

ALTER TABLE  `campaign_networks` ADD  `trim_limit` INT NOT NULL DEFAULT  '2';

CREATE TABLE IF NOT EXISTS `options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE  `campaigns` CHANGE  `should_analyse`  `analysis_quota` INT NOT NULL DEFAULT  '0';

ALTER TABLE `twitter_users` ADD user_last_updated DATETIME NULL DEFAULT NULL;

ALTER TABLE `users` ADD UNIQUE  `unique_email` (  `email` );
ALTER TABLE  `users` ADD  `reset_key` VARCHAR( 20 ) NULL;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_level` smallint(6) NOT NULL,
  `permission` varchar(50) NOT NULL,
  KEY `user_level` (`user_level`),
  UNIQUE KEY `unique_key` (`user_level`, `permission`)
) ENGINE=MYISAM DEFAULT CHARSET=utf8;

INSERT INTO `user_permissions` (`user_level`, `permission`) VALUES
(1, 'compare_campaign'),
(1, 'compare_facebook_page'),
(1, 'compare_twitter_list'),
(1, 'compare_twitter_network'),
(1, 'compare_twitter_search'),
(1, 'compare_twitter_trend'),
(1, 'compare_user'),
(1, 'edit_self'),
(1, 'fetch'),
(1, 'list_facebook_page'),
(1, 'list_twitter_list'),
(1, 'list_twitter_network'),
(1, 'list_twitter_search'),
(1, 'list_twitter_trend'),
(1, 'list_user'),
(1, 'select_campaign'),
(1, 'twitter_callback'),
(1, 'twitter_deauth'),
(1, 'twitter_oauth'),
(1, 'view_campaign'),
(1, 'view_facebook_page'),
(1, 'view_twitter_list'),
(1, 'view_twitter_network'),
(1, 'view_twitter_search'),
(1, 'view_twitter_trend'),
(1, 'view_user'),
(5, 'compare_campaign'),
(5, 'compare_facebook_page'),
(5, 'compare_twitter_list'),
(5, 'compare_twitter_network'),
(5, 'compare_twitter_search'),
(5, 'compare_twitter_trend'),
(5, 'compare_user'),
(5, 'create_campaign'),
(5, 'create_facebook_page'),
(5, 'create_twitter_list'),
(5, 'create_twitter_network'),
(5, 'create_twitter_search'),
(5, 'create_twitter_trend'),
(5, 'edit_campaign'),
(5, 'edit_facebook_page'),
(5, 'edit_self'),
(5, 'edit_twitter_list'),
(5, 'edit_twitter_network'),
(5, 'edit_twitter_search'),
(5, 'edit_twitter_trend'),
(5, 'fetch'),
(5, 'list_campaign'),
(5, 'list_facebook_page'),
(5, 'list_twitter_list'),
(5, 'list_twitter_network'),
(5, 'list_twitter_search'),
(5, 'list_twitter_trend'),
(5, 'list_user'),
(5, 'manage_twitter_network'),
(5, 'select_campaign'),
(5, 'twitter_callback'),
(5, 'twitter_deauth'),
(5, 'twitter_oauth'),
(5, 'update_facebook_page'),
(5, 'view_campaign'),
(5, 'view_facebook_page'),
(5, 'view_twitter_list'),
(5, 'view_twitter_network'),
(5, 'view_twitter_search'),
(5, 'view_twitter_trend'),
(5, 'view_user'),
(10, 'campaign_stats'),
(10, 'change_user_level'),
(10, 'compare_campaign'),
(10, 'compare_facebook_page'),
(10, 'compare_twitter_list'),
(10, 'compare_twitter_network'),
(10, 'compare_twitter_search'),
(10, 'compare_twitter_trend'),
(10, 'compare_user'),
(10, 'create_campaign'),
(10, 'create_facebook_page'),
(10, 'create_region'),
(10, 'create_twitter_list'),
(10, 'create_twitter_network'),
(10, 'create_twitter_search'),
(10, 'create_twitter_trend'),
(10, 'create_user'),
(10, 'delete_campaign'),
(10, 'delete_facebook_page'),
(10, 'delete_region'),
(10, 'delete_twitter_list'),
(10, 'delete_twitter_network'),
(10, 'delete_twitter_search'),
(10, 'delete_twitter_trend'),
(10, 'delete_user'),
(10, 'edit_campaign'),
(10, 'edit_facebook_page'),
(10, 'edit_region'),
(10, 'edit_self'),
(10, 'edit_twitter_list'),
(10, 'edit_twitter_network'),
(10, 'edit_twitter_search'),
(10, 'edit_twitter_trend'),
(10, 'edit_user'),
(10, 'fetch'),
(10, 'list_campaign'),
(10, 'list_facebook_page'),
(10, 'list_region'),
(10, 'list_twitter_list'),
(10, 'list_twitter_network'),
(10, 'list_twitter_search'),
(10, 'list_twitter_trend'),
(10, 'list_user'),
(10, 'manage_twitter_network'),
(10, 'manage_user'),
(10, 'select_campaign'),
(10, 'twitter_callback'),
(10, 'twitter_deauth'),
(10, 'twitter_oauth'),
(10, 'update_facebook_page'),
(10, 'view_campaign'),
(10, 'view_facebook_page'),
(10, 'view_region'),
(10, 'view_twitter_list'),
(10, 'view_twitter_network'),
(10, 'view_twitter_search'),
(10, 'view_twitter_trend'),
(10, 'view_user');

ALTER TABLE  `twitter_searches` ADD  `geo_args_json` VARCHAR( 512 ) NULL AFTER  `query`;

CREATE TABLE IF NOT EXISTS `regions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `args_json` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--2012-08-22

ALTER TABLE  `twitter_searches` DROP INDEX  `campaign_search_query` ,
ADD INDEX  `campaign_search_query` (  `campaign_id` );

--2013-02-26

CREATE TABLE IF NOT EXISTS `job_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;

ALTER TABLE twitter_tokens ADD rate_limits TEXT, DROP hourly_limit, DROP remaining_hits, DROP reset_time;

CREATE TABLE IF NOT EXISTS `twitter_user_relationships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `twitter_user_id` bigint(20) NOT NULL,
  `type` enum('friends','followers') NOT NULL,
  `last_updated` datetime DEFAULT NULL,
  `related_ids` mediumtext,
  `partial_update` mediumtext,
  PRIMARY KEY (`id`),
  UNIQUE KEY `twitter_user_id` (`twitter_user_id`,`type`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- only run these after migrating the data from twitter_follows to twitter_user_relationships
ALTER TABLE twitter_users DROP friends_last_updated, DROP followers_last_updated;
DROP TABLE twitter_follows;