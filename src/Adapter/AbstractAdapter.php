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
     * Return all statuses posted by the presence with 3rd party ID $pageUID.
     * Also, additionally include statuses mentioning $handle.
     *
     * Since is either a DateTime or a status id, depending on implementation
     *
     * @param $pageUID
     * @param string $handle
     * @param mixed $since
     * @return Status[]
     */
    abstract public function getStatuses($pageUID,$since,$handle = null);
}