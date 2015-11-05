<?php

namespace Outlandish\SocialMonitor\Adapter;

use DateTime;
use Exception_InstagramNotFound;
use Outlandish\SocialMonitor\InstagramApp;
use Outlandish\SocialMonitor\Models\InstagramStatus;
use Outlandish\SocialMonitor\Models\PresenceMetadata;
use Outlandish\SocialMonitor\Models\Status;

class InstagramAdapter extends AbstractAdapter
{

    public function __construct(InstagramApp $instagram)
    {
        $this->instagram = $instagram;
    }

    public function getMetadata($handle)
    {
        $users = $this->instagram->searchUser($handle, 2);
        if (count($users->data) > 1) {
            throw new \Exception_InstagramApi('Multiple users found for instagram name ' . $handle, 404);
        } else if (count($users->data) === 0) {
            throw new Exception_InstagramNotFound('Instagram user not found ' . $handle, 404);
        }
        $user = $users->data[0];
        $inflated = $this->instagram->getUser($user->id)->data;
        if (!$inflated) {
            throw new Exception_InstagramNotFound('Instagram user not found ' . $handle, 404);
        }
        $metadata = new PresenceMetadata();
        $metadata->uid = $inflated->id;
        $metadata->image_url = $inflated->profile_picture;
        $metadata->page_url = 'https://instagram.com/jwdsouza/' . $handle;
        $metadata->name = $handle;
        $metadata->popularity = $inflated->counts->followed_by;

        return $metadata;
    }

    public function getStatuses($pageUID, $since, $handle)
    {
        $posts = array();
        $media = $this->instagram->getUserMediaFromId($pageUID, $since)->data;
        foreach ($media as $m) {
            $status = new InstagramStatus();
            $status->id = $m->id;
            $status->message = $m->caption->text;
            $status->created_time = $m->created_time;
            $status->posted_by_owner = true;
            $status->permalink = $m->link;
            $status->image_url = $m->images->standard_resolution->url;
            $status->likes = $m->likes->count;
            $status->comments = $m->comments->count;

            $posts[] = $status;
        }
        return $posts;
    }

    public function getResponses($postUIDs)
    {

    }
}