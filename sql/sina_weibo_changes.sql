-- 2014-10-06
ALTER TABLE  `presences` CHANGE  `type`  `type` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;

-- 2014-10-07
CREATE TABLE `sina_weibo_posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remote_id` varchar(20) COLLATE utf8_bin NOT NULL,
  `text` decimal(10,0) NOT NULL,
  `presence_id` int(11) DEFAULT NULL COMMENT 'NULL when not posted by a known presence',
  `remote_user_id` varchar(20) COLLATE utf8_bin NOT NULL,
  `created_at` datetime NOT NULL,
  `picture_url` varchar(128) COLLATE utf8_bin DEFAULT NULL COMMENT 'NULL when no picture attached',
  `posted_by_presence` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'has this post been posted by the presence itself',
  `included_retweet` varchar(20) COLLATE utf8_bin DEFAULT NULL COMMENT 'remote_id of included retweet. NULL when no included retweet with this post',
  `repost_count` int(11) unsigned NOT NULL DEFAULT '0',
  `comment_count` int(11) unsigned NOT NULL DEFAULT '0',
  `attitude_count` int(11) unsigned NOT NULL DEFAULT '0' COMMENT 'number of likes',
  PRIMARY KEY (`id`),
  UNIQUE KEY `remote_id` (`remote_id`),
  KEY `presence_id` (`presence_id`),
  KEY `included_retweet` (`included_retweet`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

ALTER TABLE  `status_links` CHANGE  `type`  `type` VARCHAR( 20 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;