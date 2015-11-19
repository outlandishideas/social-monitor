<?php

namespace Outlandish\SocialMonitor;

use DateTime;

class InstagramApp extends \MetzWeb\Instagram\Instagram {

    public function getUserMediaFromId($id,$minId) {
        return $this->_makeCall('users/' . $id . '/media/recent', ($id === 'self'), array('min_id' => $minId));
    }

    /**
     * @param $id
     * @param DateTime $minDate
     * @return mixed
     * @throws \Exception
     */
    public function getUserMediaFromDate($id,$minDate) {
        $millis = $minDate->getTimestamp();
        return $this->_makeCall('users/' . $id . '/media/recent', ($id === 'self'), array('min_timestamp' => $millis));
    }

}