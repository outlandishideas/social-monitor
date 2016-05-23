
--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password_hash` varchar(200) NOT NULL,
  `twitter` varchar(20) DEFAULT NULL,
  `last_sign_in` datetime DEFAULT NULL,
  `last_failed_login` datetime DEFAULT NULL,
  `last_campaign_id` int(11) DEFAULT NULL,
  `user_level` int(1) NOT NULL DEFAULT '1',
  `failed_logins` int(1) NOT NULL DEFAULT '0',
  `token_id` int(11) DEFAULT NULL,
  `reset_key` varchar(20) DEFAULT NULL,
  `confirm_email_key` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `unique_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

