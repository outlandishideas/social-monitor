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
    
    public static function getType($sqlType){
        return self::$sqlTypeMappings[$sqlType];
    }
    
    public static function isStringType($sqlType){
        $mappedType = self::$sqlTypeMappings[$sqlType];
        return ($mappedType === 'string');
    }

    public static function isNumericType($sqlType){
        $mappedType = self::$sqlTypeMappings[$sqlType];
        return ($mappedType === 'integer' || $mappedType === 'double');
    }

    public static function isValidNumber($candidate){
        return is_numeric($candidate) || !$candidate;
    }

    public static function truthyOrZero($value){
       if($value || $value === 0 || $value === '0'){
           return true;
       }
        return false;
    }

    public static function pluck($key, $data) {
        return array_reduce($data, function($result, $array) use($key){
            isset($array[$key]) &&
            $result[] = $array[$key];

            return $result;
        }, array());
    }
}
