<?php

namespace Outlandish\SocialMonitor;

class InstagramApp extends \MetzWeb\Instagram\Instagram {

    public function getUserMediaFromId($id,$minId) {
        return $this->_makeCall('users/' . $id . '/media/recent', ($id === 'self'), array('min_id' => $minId));
    }

}