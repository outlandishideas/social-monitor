<?php

class Model_PresenceType extends Model_Enum
{
	const TWITTER 		= 'twitter';
	const FACEBOOK 	= 'facebook';
	const SINA_WEIBO 	= 'sina_weibo';

	public function getProvider(PDO $db) {
		switch ($this->value) {
			case self::SINA_WEIBO:
				return new Model_SinaWeiboProvider($db);
				break;
			case self::FACEBOOK:
				return new Model_FacebookProvider($db);
				break;
			case self::TWITTER:
				return new Model_TwitterProvider($db);
				break;
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}
}