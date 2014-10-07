<?php

class NewModel_PresenceType extends NewModel_Enum
{
	const TWITTER 		= 'twitter';
	const FACEBOOK 	= 'facebook';
	const SINA_WEIBO 	= 'sina_weibo';

	public function getProvider(PDO $db) {
		switch ($this->value) {
			case self::SINA_WEIBO:
				return new NewModel_SinaWeiboProvider($db);
				break;
			case self::FACEBOOK:
				return new NewModel_FacebookProvider($db);
				break;
			case self::TWITTER:
				return new NewModel_TwitterProvider($db);
				break;
			default:
				throw new \LogicException("Not implemented yet.");
		}
	}
}