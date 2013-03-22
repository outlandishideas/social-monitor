ALTER TABLE  `twitter_tweets` ADD  `tweet_id` BIGINT NOT NULL AFTER  `id` ,
ADD INDEX (  `tweet_id` );
UPDATE twitter_tweets SET tweet_id = id;
ALTER TABLE  `twitter_tweets` ADD  `parent_type` ENUM(  'list',  'search' ) NOT NULL AFTER  `tweet_id` ,
ADD  `parent_id` INT NOT NULL AFTER  `parent_type` ,
ADD INDEX (`parent_type`),
ADD INDEX (`parent_id`);
UPDATE twitter_lists list INNER JOIN twitter_list_tweets j ON list.id = j.twitter_list_id INNER JOIN twitter_tweets tweet ON j.twitter_tweet_id = tweet.tweet_id SET tweet.parent_type =  'list',
tweet.parent_id = list.id;
UPDATE twitter_searches search INNER JOIN twitter_search_tweets j ON search.id = j.twitter_search_id INNER JOIN twitter_tweets tweet ON j.twitter_tweet_id = tweet.tweet_id SET tweet.parent_type =  'search',
tweet.parent_id = search.id;
ALTER TABLE  `twitter_tweets` ADD  `bucket_half_hour` DATETIME NULL DEFAULT NULL ,
ADD  `bucket_4_hours` DATETIME NULL DEFAULT NULL ,
ADD  `bucket_12_hours` DATETIME NULL DEFAULT NULL ,
ADD  `bucket_day` DATETIME NULL DEFAULT NULL;
update twitter_tweets set bucket_day = date(created_at), 
bucket_half_hour = FROM_UNIXTIME(UNIX_TIMESTAMP(created_at) - MOD(UNIX_TIMESTAMP(created_at),1800)),
bucket_4_hours = FROM_UNIXTIME(UNIX_TIMESTAMP(created_at) - MOD(UNIX_TIMESTAMP(created_at),14400)),
bucket_12_hours = FROM_UNIXTIME(UNIX_TIMESTAMP(created_at) - MOD(UNIX_TIMESTAMP(created_at),43200));

update twitter_tweets set bucket_4_hours = date_add(bucket_4_hours, interval -1 hour), bucket_12_hours = date_add(bucket_12_hours, interval -1 hour) where date(created_at) >= '2012-03-26';

update twitter_tweets set bucket_4_hours = date_add(bucket_4_hours, interval 4 hour) where created_at > date_add(bucket_4_hours, interval 4 hour);
update twitter_tweets set bucket_12_hours = date_add(bucket_12_hours, interval 12 hour) where created_at > date_add(bucket_12_hours, interval 12 hour);

ALTER TABLE  `twitter_tweets` DROP  `id`;
ALTER TABLE  `twitter_tweets` ADD  `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;
ALTER TABLE  `twitter_tweets` CHANGE  `created_at`  `created_time` DATETIME NOT NULL;

ALTER TABLE  `twitter_tweets` ADD INDEX (  `bucket_half_hour` );
ALTER TABLE  `twitter_tweets` ADD INDEX (  `bucket_4_hours` );
ALTER TABLE  `twitter_tweets` ADD INDEX (  `bucket_12_hours` );
ALTER TABLE  `twitter_tweets` ADD INDEX (  `bucket_day` );
ALTER TABLE  `twitter_tweets` ADD UNIQUE  `unique_tweet_index` ( `parent_type` ,  `parent_id` , `tweet_id` );



ALTER TABLE  `facebook_stream` ADD  `bucket_half_hour` DATETIME NULL DEFAULT NULL ,
ADD  `bucket_4_hours` DATETIME NULL DEFAULT NULL ,
ADD  `bucket_12_hours` DATETIME NULL DEFAULT NULL ,
ADD  `bucket_day` DATETIME NULL DEFAULT NULL;
ALTER TABLE  `facebook_stream` ADD INDEX (  `bucket_half_hour` );
ALTER TABLE  `facebook_stream` ADD INDEX (  `bucket_4_hours` );
ALTER TABLE  `facebook_stream` ADD INDEX (  `bucket_12_hours` );
ALTER TABLE  `facebook_stream` ADD INDEX (  `bucket_day` );
UPDATE facebook_stream SET bucket_day = DATE( created_time ) ,
bucket_half_hour = FROM_UNIXTIME( UNIX_TIMESTAMP( created_time ) - MOD( UNIX_TIMESTAMP( created_time ) , 1800 ) ) ,
bucket_4_hours = FROM_UNIXTIME( UNIX_TIMESTAMP( created_time ) - MOD( UNIX_TIMESTAMP( created_time ) , 14400 ) ) ,
bucket_12_hours = FROM_UNIXTIME( UNIX_TIMESTAMP( created_time ) - MOD( UNIX_TIMESTAMP( created_time ) , 43200 ) ) ;

update facebook_stream set bucket_4_hours = date_add(bucket_4_hours, interval -1 hour), bucket_12_hours = date_add(bucket_12_hours, interval -1 hour) where date(created_time) >= '2012-03-26';

update facebook_stream set bucket_4_hours = date_add(bucket_4_hours, interval 4 hour) where created_time > date_add(bucket_4_hours, interval 4 hour);
update facebook_stream set bucket_12_hours = date_add(bucket_12_hours, interval 12 hour) where created_time > date_add(bucket_12_hours, interval 12 hour);

DROP TABLE  `twitter_search_tweets`;
DROP TABLE  `twitter_list_tweets`;