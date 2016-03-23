<?php

abstract class Metric_Factory {

    protected static $metrics = array();

    protected static function getClassName($name)
    {
        $names = array(
            Metric_Popularity::getName() => "Metric_Popularity",
            Metric_ActionsPerDay::getName() => "Metric_ActionsPerDay",
            Metric_Branding::getName() => "Metric_Branding",
            Metric_SignOff::getName() => "Metric_SignOff",
            Metric_PopularityTime::getName() => "Metric_PopularityTime",
            Metric_Relevance::getName() => "Metric_Relevance",
            Metric_FBEngagement::getName() => "Metric_FBEngagement",
            Metric_Klout::getName() => "Metric_Klout",
            Metric_LikesPerPost::getName() => "Metric_LikesPerPost",
            Metric_LikesPerView::getName() => "Metric_LikesPerView",
            Metric_ResponseTime::getName() => "Metric_ResponseTime",
            Metric_ResponseRatio::getName() => "Metric_ResponseRatio",
            Metric_SinaWeiboEngagement::getName() => "Metric_SinaWeiboEngagement",
            Metric_InstagramEngagement::getName() => "Metric_InstagramEngagement",
            Metric_YoutubeEngagement::getName() => "Metric_YoutubeEngagement",
            Metric_LinkedinEngagement::getName() => "Metric_LinkedinEngagement"
        );

        if(!array_key_exists($name, $names)){
            throw new \LogicException("$name is not implemented yet.");
        }

        return $names[$name];
    }

    /**
     * @param $name
     * @return Metric_Abstract
     */
    public static function getMetric($name)
    {
        if(!array_key_exists($name, self::$metrics)){
            $class = self::getClassName($name);
            self::$metrics[$name] = new $class();
        }
        return self::$metrics[$name];
    }

}