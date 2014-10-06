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
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}
}