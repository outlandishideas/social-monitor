-- --------------------------------------------------------

--
-- Table structure for table `hashtags`
--

CREATE TABLE `hashtags` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `hashtag` varchar(100) NOT NULL UNIQUE,
  `is_relevant` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- --------------------------------------------------------

--
-- Table structure for table `domains_hashtags`
--

CREATE TABLE `posts_hashtags` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `post` int(11) NOT NULL,
  `hashtag` int(11) NOT NULL REFERENCES hashtags(`id`),
  `post_type` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
