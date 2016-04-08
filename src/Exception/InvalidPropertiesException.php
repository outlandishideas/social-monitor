<?php

namespace Outlandish\SocialMonitor\Exception;

class InvalidPropertiesException extends SocialMonitorException
{
    protected $message;
    protected $properties;

    public function __construct($message, $properties = array(), Exception $previous = null)
    {
        $this->message = $message;
        $this->properties = $properties;
        parent::__construct($message, 0, $previous);
    }

    public function getProperties()
    {
        return $this->properties;
    }

}