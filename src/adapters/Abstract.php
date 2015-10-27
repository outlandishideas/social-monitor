<?php

namespace Outlandish\SocialMonitor\Adapters;

abstract class Adapter_Abstract {

    /**
     * @param $handle
     * @return array
     */
    abstract public function getMetadata($handle);

    abstract public function getStatuses($handle);

}