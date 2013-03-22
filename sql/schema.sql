-- phpMyAdmin SQL Dump
-- version 3.2.4
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Oct 19, 2012 at 10:59 AM
-- Server version: 5.5.14
-- PHP Version: 5.3.3

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `tsr_socialdash`
--

-- --------------------------------------------------------

--
-- Table structure for table `campaigns`
--

CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `token_id` int(11) DEFAULT NULL,
  `analysis_quota` int(11) NOT NULL DEFAULT '0',
  `timezone` varchar(30) NOT NULL DEFAULT 'Europe/London',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `token` (`token_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=72 ;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_networks`
--

CREATE TABLE IF NOT EXISTS `campaign_networks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '1',
  `trim_limit` int(11) NOT NULL DEFAULT '2',
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_id` (`campaign_id`,`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=145 ;

-- --------------------------------------------------------

--
-- Table structure for table `campaign_network_users`
--

CREATE TABLE IF NOT EXISTS `campaign_network_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `network_id` int(11) NOT NULL,
  `twitter_user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `network_id` (`network_id`,`twitter_user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1382 ;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_actors`
--

CREATE TABLE IF NOT EXISTS `facebook_actors` (
  `id` varchar(20) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `pic_url` varchar(255) DEFAULT NULL,
  `profile_url` varchar(255) DEFAULT NULL,
  `type` varchar(10) NOT NULL,
  `last_fetched` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_comments`
--

CREATE TABLE IF NOT EXISTS `facebook_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` varchar(100) NOT NULL,
  `post_id` varchar(100) NOT NULL,
  `fromid` varchar(20) NOT NULL,
  `time` datetime NOT NULL,
  `text` varchar(500) NOT NULL,
  `likes` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `comment_id` (`comment_id`),
  KEY `post_id` (`post_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=339739 ;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_pages`
--

CREATE TABLE IF NOT EXISTS `facebook_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `page_id` bigint(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `username` varchar(200) NOT NULL,
  `pic_square` varchar(200) NOT NULL,
  `page_url` varchar(200) NOT NULL,
  `fan_count` int(11) NOT NULL,
  `last_fetched` datetime DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL,
  `should_analyse` tinyint(1) NOT NULL DEFAULT '1',
  `posts_last_24_hours` int(11) DEFAULT NULL,
  `posts_this_month` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `page_id` (`page_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=37 ;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_post_topics`
--

CREATE TABLE IF NOT EXISTS `facebook_post_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `facebook_stream_id` int(11) NOT NULL,
  `topic` varchar(100) NOT NULL,
  `normalised_topic` varchar(100) NOT NULL,
  `importance` int(11) NOT NULL,
  `polarity` float NOT NULL,
  PRIMARY KEY (`id`),
  KEY `topic` (`topic`,`normalised_topic`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=12448 ;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_stream`
--

CREATE TABLE IF NOT EXISTS `facebook_stream` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `facebook_page_id` int(11) NOT NULL,
  `post_id` varchar(100) NOT NULL,
  `message` varchar(1000) NOT NULL,
  `created_time` datetime NOT NULL,
  `actor_id` varchar(20) NOT NULL,
  `comments` int(11) NOT NULL,
  `likes` int(11) NOT NULL,
  `permalink` varchar(200) NOT NULL,
  `type` int(11) NOT NULL,
  `is_analysed` tinyint(1) NOT NULL DEFAULT '0',
  `average_sentiment` float DEFAULT NULL,
  `bucket_half_hour` datetime DEFAULT NULL,
  `bucket_4_hours` datetime DEFAULT NULL,
  `bucket_12_hours` datetime DEFAULT NULL,
  `bucket_day` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_id` (`post_id`),
  KEY `facebook_page_id` (`facebook_page_id`),
  KEY `is_analysed` (`is_analysed`),
  KEY `bucket_half_hour` (`bucket_half_hour`),
  KEY `bucket_4_hours` (`bucket_4_hours`),
  KEY `bucket_12_hours` (`bucket_12_hours`),
  KEY `bucket_day` (`bucket_day`),
  FULLTEXT KEY `message` (`message`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=50516 ;

-- --------------------------------------------------------

--
-- Table structure for table `options`
--

CREATE TABLE IF NOT EXISTS `options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `object_id` int(11) NOT NULL DEFAULT '0',
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`name`,`object_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=659355 ;

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE IF NOT EXISTS `regions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `args_json` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_follows`
--

CREATE TABLE IF NOT EXISTS `twitter_follows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `friend_id` bigint(20) NOT NULL,
  `follower_id` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5231223 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_lists`
--

CREATE TABLE IF NOT EXISTS `twitter_lists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `last_fetched` datetime DEFAULT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  `should_analyse` tinyint(1) NOT NULL DEFAULT '1',
  `tweets_last_24_hours` int(11) DEFAULT NULL,
  `tweets_this_month` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_list_name` (`campaign_id`,`name`),
  UNIQUE KEY `campaign_list_slug` (`campaign_id`,`slug`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=97 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_list_members`
--

CREATE TABLE IF NOT EXISTS `twitter_list_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `twitter_list_id` int(11) NOT NULL,
  `twitter_user_id` bigint(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_searches`
--

CREATE TABLE IF NOT EXISTS `twitter_searches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `query` varchar(200) NOT NULL,
  `geo_args_json` varchar(512) DEFAULT NULL,
  `last_fetched` datetime DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  `should_analyse` tinyint(1) NOT NULL DEFAULT '1',
  `tweets_last_24_hours` int(11) DEFAULT NULL,
  `tweets_this_month` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `campaign_search_query` (`campaign_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=144 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_tokens`
--

CREATE TABLE IF NOT EXISTS `twitter_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `twitter_user_id` bigint(20) NOT NULL,
  `oauth_token` varchar(200) NOT NULL,
  `oauth_token_secret` varchar(200) NOT NULL,
  `hourly_limit` int(11) NOT NULL,
  `remaining_hits` int(11) NOT NULL,
  `reset_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oauth_token` (`oauth_token`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=55 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_trend_regions`
--

CREATE TABLE IF NOT EXISTS `twitter_trend_regions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `woeid` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `last_fetched` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_woe` (`campaign_id`,`woeid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=23 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_trend_topics`
--

CREATE TABLE IF NOT EXISTS `twitter_trend_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `woeid` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `normalised_name` varchar(100) NOT NULL,
  `rank` int(11) NOT NULL,
  `as_of` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1262292 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_tweets`
--

CREATE TABLE IF NOT EXISTS `twitter_tweets` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `tweet_id` bigint(20) NOT NULL,
  `parent_type` enum('list','search') NOT NULL,
  `parent_id` int(11) NOT NULL,
  `text_expanded` varchar(255) NOT NULL,
  `twitter_user_id` bigint(20) NOT NULL,
  `created_time` datetime NOT NULL,
  `retweet_count` smallint(6) NOT NULL,
  `is_analysed` int(11) NOT NULL DEFAULT '0',
  `html_tweet` varchar(1024) DEFAULT NULL,
  `average_sentiment` float DEFAULT NULL,
  `bucket_half_hour` datetime DEFAULT NULL,
  `bucket_4_hours` datetime DEFAULT NULL,
  `bucket_12_hours` datetime DEFAULT NULL,
  `bucket_day` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tweet_index` (`parent_type`,`parent_id`,`tweet_id`),
  KEY `created_at` (`created_time`),
  KEY `tweet_id` (`tweet_id`),
  KEY `parent_type` (`parent_type`),
  KEY `parent_id` (`parent_id`),
  KEY `bucket_half_hour` (`bucket_half_hour`),
  KEY `bucket_4_hours` (`bucket_4_hours`),
  KEY `bucket_12_hours` (`bucket_12_hours`),
  KEY `bucket_day` (`bucket_day`),
  FULLTEXT KEY `text_expanded` (`text_expanded`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=26479778 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_tweet_topics`
--

CREATE TABLE IF NOT EXISTS `twitter_tweet_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `twitter_tweet_id` bigint(20) NOT NULL,
  `topic` varchar(100) NOT NULL,
  `normalised_topic` varchar(100) NOT NULL,
  `importance` int(11) NOT NULL,
  `polarity` float NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `twitter_tweet_id_topic` (`twitter_tweet_id`,`topic`),
  KEY `normalised_topic` (`normalised_topic`),
  KEY `twitter_tweet_id` (`twitter_tweet_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1714821 ;

-- --------------------------------------------------------

--
-- Table structure for table `twitter_users`
--

CREATE TABLE IF NOT EXISTS `twitter_users` (
  `id` bigint(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(200) DEFAULT NULL,
  `statuses_count` int(11) NOT NULL,
  `profile_image_url` varchar(200) NOT NULL,
  `followers_count` int(11) NOT NULL,
  `screen_name` varchar(20) NOT NULL,
  `url` varchar(200) DEFAULT NULL,
  `friends_count` int(11) NOT NULL,
  `location_id` varchar(200) NOT NULL,
  `peerindex` int(11) DEFAULT NULL,
  `peerindex_last_updated` datetime DEFAULT NULL,
  `klout` float DEFAULT NULL,
  `klout_last_updated` datetime DEFAULT NULL,
  `followers_last_updated` datetime DEFAULT NULL,
  `friends_last_updated` datetime DEFAULT NULL,
  `user_last_updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password_hash` varchar(200) NOT NULL,
  `twitter` varchar(20) NOT NULL,
  `last_sign_in` datetime DEFAULT NULL,
  `last_campaign_id` int(11) DEFAULT NULL,
  `user_level` int(1) NOT NULL DEFAULT '1',
  `token_id` int(11) DEFAULT NULL,
  `reset_key` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `unique_email` (`email`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=49 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `twitter`, `last_sign_in`, `last_campaign_id`, `user_level`, `token_id`, `reset_key`) VALUES
(1, 'admin', 'info@outlandishideas.co.uk', 'a0c59119e6ba0a1a7bcd0110e13ea937a34f036b', '', '2012-08-15 16:49:05', 6, 10, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_campaigns`
--

CREATE TABLE IF NOT EXISTS `user_campaigns` (
  `user_id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`campaign_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_level` smallint(6) NOT NULL,
  `permission` varchar(50) NOT NULL,
  UNIQUE KEY `unique_key` (`user_level`,`permission`),
  KEY `user_level` (`user_level`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
