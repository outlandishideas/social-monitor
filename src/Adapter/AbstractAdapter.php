<?php

namespace Outlandish\SocialMonitor\Adapter;

use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\Models\PresenceMetadata;

abstract class AbstractAdapter {

    /**
     *
     * Here we take a handle, as we might not yet know the UID
     *
     * @param $handle
     * @return PresenceMetadata
     */
    abstract public function getMetadata($handle);

    /**
     *
     * Here we take a UID i.e. the 3rd party id of a presence
     *
     * @param $pageUID
     * @param DateTime $since
     * @return Status[]
     */
    abstract public function getStatuses($pageUID,$since);

    /**
     * @param array $pageUIDs
     * @return mixed
     */
    abstract public function getResponses($pageUIDs);

}