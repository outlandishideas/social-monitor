<?php

namespace Outlandish\SocialMonitor\Models;

use Carbon\Carbon;

class AccessToken
{
    /** @var string */
    private $token;
    /** @var Carbon */
    private $expires;

    public function __construct($token, $expires)
    {
        $this->token = $token;
        $this->setExpires($expires);
    }

    protected function setExpires($expires)
    {
        if ($expires instanceof Carbon) {
            $this->expires = $expires;
        } else {
            $dateTime = new Carbon();
            $dateTime->setTimestamp($expires);
            $this->expires = $dateTime;
        }
    }

    public function expiresSoon()
    {
        try {
            return Carbon::now()->diff($this->expires)->days < 7;
        } catch (\Exception $e) {
            return true;
        }
    }

    public function getExpires()
    {
        return $this->expires;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function isExpired()
    {
        return (new Carbon()) > $this->expires;
    }
    
    public function __toString()
    {
        return $this->token;
    }

}