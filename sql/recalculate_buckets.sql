--hacked with one hour time difference for BST


SET time_zone = '+0:00';
--UPDATE facebook_stream
UPDATE twitter_tweets

SET
bucket_half_hour = from_unixtime(unix_timestamp(created_time)-(unix_timestamp(created_time)+3600)%1800),
bucket_4_hours = from_unixtime(unix_timestamp(created_time)-(unix_timestamp(created_time)+3600)%14400),
bucket_12_hours = from_unixtime(unix_timestamp(created_time)-(unix_timestamp(created_time)+3600)%43200),
bucket_day = from_unixtime(unix_timestamp(created_time)-(unix_timestamp(created_time)+3600)%86400);