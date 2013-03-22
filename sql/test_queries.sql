SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS DISTINCT stream.*, actor.type AS actor_type, actor.name AS actor_name 
				FROM (SELECT SQL_NO_CACHE * FROM facebook_stream WHERE facebook_page_id IN (3)) AS stream
				LEFT OUTER JOIN facebook_post_topics AS topic ON stream.id = topic.facebook_stream_id
				LEFT OUTER JOIN facebook_actors AS actor ON stream.actor_id = actor.id WHERE 
				(created_time >= '2012-03-01') 
				AND (created_time < '2012-03-26') 
				AND ((MATCH(message) AGAINST ('good'))) 
				ORDER BY stream.created_time asc
				
SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS DISTINCT stream.*, actor.type AS actor_type, actor.name AS actor_name 
				FROM facebook_stream AS stream
				LEFT OUTER JOIN facebook_post_topics AS topic ON stream.id = topic.facebook_stream_id
				LEFT OUTER JOIN facebook_actors AS actor ON stream.actor_id = actor.id WHERE 
				(created_time >= '2012-03-01') 
				AND (created_time < '2012-03-26') 
				AND facebook_page_id IN (3)
				AND ((MATCH(message) AGAINST ('good'))) 
				ORDER BY stream.created_time asc

--twitter basic				
SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS DISTINCT tweet.*, user.id AS user_id, user.name AS user_name, screen_name, profile_image_url
				FROM (SELECT SQL_NO_CACHE * FROM twitter_search_tweets WHERE twitter_search_id IN (9)) AS j
				INNER JOIN twitter_tweets AS tweet ON tweet.id = j.twitter_tweet_id
				LEFT OUTER JOIN twitter_users AS user ON tweet.twitter_user_id = user.id
				LEFT OUTER JOIN twitter_tweet_topics AS topic ON tweet.id = topic.twitter_tweet_id 
				WHERE (created_at >= '2012-03-01') 
				AND (created_at < '2012-03-27') 
				ORDER BY tweet.created_at desc LIMIT 50

--with topic
SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS DISTINCT tweet.*, user.id AS user_id, user.name AS user_name, screen_name, profile_image_url
				FROM (SELECT SQL_NO_CACHE * FROM twitter_search_tweets WHERE twitter_search_id IN (9)) AS j
				INNER JOIN twitter_tweets AS tweet ON tweet.id = j.twitter_tweet_id
				LEFT OUTER JOIN twitter_users AS user ON tweet.twitter_user_id = user.id
				LEFT OUTER JOIN twitter_tweet_topics AS topic ON tweet.id = topic.twitter_tweet_id 
				WHERE (created_at >= '2012-03-01') AND (created_at < '2012-03-27') 
				AND ((normalised_topic='BBC')) ORDER BY tweet.created_at desc LIMIT 50
				
--rearranged topic
SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS DISTINCT tweet.*, user.id AS user_id, user.name AS user_name, screen_name, profile_image_url
				FROM twitter_search_tweets AS j
				INNER JOIN twitter_tweets AS tweet ON tweet.id = j.twitter_tweet_id
				LEFT OUTER JOIN twitter_users AS user ON tweet.twitter_user_id = user.id
				LEFT OUTER JOIN twitter_tweet_topics AS topic ON tweet.id = topic.twitter_tweet_id 
				WHERE (created_at >= '2012-03-01') AND (created_at < '2012-03-27') 
				AND twitter_search_id IN (9) 
				AND ((normalised_topic='BBC')) ORDER BY tweet.created_at desc LIMIT 50

--topic and fulltext
SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS DISTINCT tweet.*, user.id AS user_id, user.name AS user_name, screen_name, profile_image_url
				FROM (SELECT SQL_NO_CACHE * FROM twitter_search_tweets WHERE twitter_search_id IN (9)) AS j
				INNER JOIN twitter_tweets AS tweet ON tweet.id = j.twitter_tweet_id
				LEFT OUTER JOIN twitter_users AS user ON tweet.twitter_user_id = user.id
				LEFT OUTER JOIN twitter_tweet_topics AS topic ON tweet.id = topic.twitter_tweet_id 
				WHERE (created_at >= '2012-03-01') AND (created_at < '2012-03-27') 
				AND ((normalised_topic='BBC') OR (MATCH(text_expanded) AGAINST ('good'))) ORDER BY tweet.created_at desc LIMIT 50				

--rearranged ft
SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS DISTINCT tweet.*, user.id AS user_id, user.name AS user_name, screen_name, profile_image_url
				FROM twitter_search_tweets AS j
				INNER JOIN twitter_tweets AS tweet ON tweet.id = j.twitter_tweet_id
				LEFT OUTER JOIN twitter_users AS user ON tweet.twitter_user_id = user.id
				LEFT OUTER JOIN twitter_tweet_topics AS topic ON tweet.id = topic.twitter_tweet_id 
				WHERE (created_at >= '2012-03-01') AND (created_at < '2012-03-27') 
				AND twitter_search_id IN (9)
				AND ((normalised_topic='BBC') OR (MATCH(text_expanded) AGAINST ('good'))) ORDER BY tweet.created_at desc LIMIT 50				
				
SELECT SQL_NO_CACHE SQL_CALC_FOUND_ROWS DISTINCT tweet.*, user.id AS user_id, user.name AS user_name, screen_name, profile_image_url
				FROM twitter_search_tweets AS j
				INNER JOIN twitter_tweets AS tweet ON tweet.id = j.twitter_tweet_id
				LEFT OUTER JOIN twitter_users AS user ON tweet.twitter_user_id = user.id
				LEFT OUTER JOIN twitter_tweet_topics AS topic ON tweet.id = topic.twitter_tweet_id 
				WHERE (created_at >= '2012-03-01') AND (created_at < '2012-03-27') 
				AND ((normalised_topic='BBC' AND twitter_search_id=9) OR (twitter_search_id=9 AND MATCH(text_expanded) AGAINST ('good'))) ORDER BY tweet.created_at desc LIMIT 50				
				
--tweet mentions
SELECT SQL_NO_CACHE COUNT(*) as mentions, average_sentiment AS polarity,
				from_unixtime(50325*floor(unix_timestamp(tweets.created_at)/50325)) AS date
				FROM (SELECT * FROM twitter_search_tweets WHERE twitter_search_id IN (9)) AS joiner
				INNER JOIN twitter_tweets AS tweets ON tweets.id = joiner.twitter_tweet_id
				WHERE tweets.created_at BETWEEN '2012-03-01' AND '2012-03-26'
				GROUP BY date

--rearranhged mentions
SELECT SQL_NO_CACHE COUNT(*) as mentions, average_sentiment AS polarity,
				from_unixtime(50325*floor(unix_timestamp(tweets.created_at)/50325)) AS date
				FROM twitter_search_tweets AS joiner
				INNER JOIN twitter_tweets AS tweets ON tweets.id = joiner.twitter_tweet_id
				WHERE tweets.created_at BETWEEN '2012-03-01' AND '2012-03-26'
				AND twitter_search_id IN (9)
				GROUP BY date
				
--mentions and topics
SELECT SQL_NO_CACHE COUNT(*) AS mentions, AVG(polarity) AS polarity,
				from_unixtime(50325*floor(unix_timestamp(tweets.created_at)/50325)) AS date
				FROM (SELECT * FROM twitter_search_tweets WHERE twitter_search_id IN (9)) AS joiner
				INNER JOIN twitter_tweets AS tweets ON tweets.id = joiner.twitter_tweet_id
				INNER JOIN twitter_tweet_topics AS topics ON topics.twitter_tweet_id = tweets.id
				WHERE topics.normalised_topic = 'BBC'
				AND tweets.created_at BETWEEN '2012-03-01' AND '2012-03-26'
				GROUP BY date