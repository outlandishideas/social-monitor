<?php

namespace Outlandish\SocialMonitor\Helper;
class Verification
{

    public static $sqlTypeMappings = [
        'int' => 'integer',
        'tinyint' => 'integer',
        'smallint' => 'integer',
        'bigint' => 'integer',
        'float' => 'double',
        'varchar' => 'string',
        'text' => 'string',
        'mediumtext' => 'string',
        'datetime' => 'date',
        'date' => 'date',
        'timestamp' => 'date',
        'mediumBlob' => 'blob'
    ];

    public static function verifyType($sqlType, $value){
        $type = self::$sqlTypeMappings[$sqlType];
       
        if($type === 'integer' || $type === 'double'){
            return is_numeric($value);
        }
        
        return true;
    }



    public static function pluck($key, $data) {
        return array_reduce($data, function($result, $array) use($key){
            isset($array[$key]) &&
            $result[] = $array[$key];

            return $result;
        }, array());
    }
}
