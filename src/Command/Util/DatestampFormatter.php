<?php

namespace Outlandish\SocialMonitor\Command\Output;

use Symfony\Component\Console\Formatter\OutputFormatter;

class DatestampFormatter extends OutputFormatter
{
    protected $format;

    public function __construct($format)
    {
        parent::__construct(true);

        $this->format = $format;
    }

    public function format($message)
    {
        if ($message) {
            $message = date($this->format) . ' ' . $message;
        }
        return parent::format($message);
    }


}