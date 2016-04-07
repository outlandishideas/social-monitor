<?php

namespace Outlandish\SocialMonitor\Helper;
class Verification
{
    public static function pluck($key, $data) {
        return array_reduce($data, function($result, $array) use($key){
            isset($array[$key]) &&
            $result[] = $array[$key];

            return $result;
        }, array());
    }
}
