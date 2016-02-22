<?php

namespace Outlandish\SocialMonitor\Engagement;


class EngagementScore
{
    private $name;
    private $type;
    private $score;

    public function __construct($name, $type, $score)
    {
        $this->name = $name;
        $this->type = $type;
        $this->score = $score;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getScore()
    {
        return $this->score;
    }
}