CREATE TABLE `youtube_video_stream` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `presence_id` int(11) NOT NULL,
  `video_id` varchar(16) NOT NULL,
  `title` varchar(128) NOT NULL,
  `description` text NOT NULL,
  `created_time` date NOT NULL,
  `permalink` varchar(64) NOT NULL,
  `views` int(11) NOT NULL DEFAULT '0',
  `likes` int(11) NOT NULL DEFAULT '0',
  `dislikes` int(11) NOT NULL DEFAULT '0',
  `comments` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `video_id_idx` (`video_id`)
) ENGINE=InnoDB AUTO_INCREMENT=599 DEFAULT CHARSET=latin1

CREATE TABLE `youtube_video_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `video_id` int(11) NOT NULL,
  `datetime` date NOT NULL,
  `type` varchar(16) NOT NULL,
  `value` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4785 DEFAULT CHARSET=latin1

CREATE TABLE `youtube_comment_stream` (
  `id` varchar(200) NOT NULL,
  `presence_id` int(11) NOT NULL,
  `video_id` varchar(200) DEFAULT NULL,
  `message` varchar(1000) NOT NULL,
  `in_response_to` varchar(200) DEFAULT NULL,
  `posted_by_owner` tinyint(4) NOT NULL DEFAULT '0',
  `author_channel_id` varchar(200) DEFAULT NULL,
  `number_of_replies` int(11) DEFAULT NULL,
  `likes` int(11) NOT NULL DEFAULT '0',
  `rating` varchar(200) DEFAULT NULL,
  `created_time` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1