<?php

namespace Outlandish\SocialMonitor\Exception;

class InvalidPropertyException extends SocialMonitorException
{
    protected $message;
    protected $property;

    public function __construct($property, $message, Exception $previous = null)
    {
        $this->message = $message;
        $this->property = $property;
        parent::__construct($message, 0, $previous);
    }

    public function getProperty()
    {
        return $this->property;
    }
}