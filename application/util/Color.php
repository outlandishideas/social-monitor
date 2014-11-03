<?php
/**
 * Created by PhpStorm.
 * User: outlander
 * Date: 17/10/2014
 * Time: 15:35
 */

class Util_Color {

    protected static function getColors()
    {
        return array(
            'grey' => '#d2d2d2',
            'red' => '#D06959',
            'green' => '#84af5b',
            'orange' => '#F1DC63',
            'yellow' => '#FFFF50'
        );
    }

    public static function getColor($color)
    {
        $colors = self::getColors();
        if(array_key_exists($color, $colors)){
            return $colors[$color];
        } else {
            return null;
        }
    }

    public static function getColorNames()
    {
        return array_keys(self::getColors());
    }

    public static function getBadgeColor($score)
    {
        $range = array(
            0 => 'grey',
            1 => 'red',
            20 => 'red',
            50 => 'yellow',
            80 => 'green',
            100 => 'green'
        );
        return self::getScoreColor($range, $score);
    }

    public static function getDigitalPopulationHealthColor($score)
    {
        $range = array(
            0 => 'red',
            20 => 'red',
            50 => 'yellow',
            80 => 'green',
            100 => 'green'
        );
        return self::getScoreColor($range, $score);
    }

    public static function getScoreColor($range, $score)
    {
        return Badge_Abstract::colorize($score);
        // $colorName = array_shift($range);
        // foreach($range as $number => $color){
        //     if($score >= $number) $colorName = $color;
        // }
        // return self::getColor($colorName);
    }

}