<?php

namespace Outlandish\SocialMonitor\Adapter;

use DateTime;
use Exception_InstagramNotFound;
use Outlandish\SocialMonitor\InstagramApp;
use Outlandish\SocialMonitor\Models\InstagramStatus;
use Outlandish\SocialMonitor\Models\PresenceMetadata;

class InstagramAdapter extends AbstractAdapter
{
	/** @var InstagramApp */
	protected $instagram;

    public function __construct(InstagramApp $instagram)
    {
        $this->instagram = $instagram;
    }

    public function getMetadata($handle)
    {
        $user = null;
        /** @var object $users */
        /** @var object $user */
        $users = $this->instagram->searchUser($handle, 10);
        if ($users && count($users->data) === 1) {
            $user = $users->data[0];
        } else if ($users && count($users->data) > 1) {
            foreach($users->data as $u) {
                if($u->username===$handle) {
                    $user = $u;
                    break;
                }
            }
            if(!$user) {
                throw new \Exception_InstagramApi('Multiple users found for instagram name ' . $handle, 404);
            }
        } else {
            throw new Exception_InstagramNotFound('Instagram user not found ' . $handle, 404);
        }
        $inflated = $this->instagram->getUser($user->id)->data;
        if (!$inflated) {
            throw new Exception_InstagramNotFound('Instagram user not found ' . $handle, 404);
        }
        $metadata = new PresenceMetadata();
        $metadata->uid = $inflated->id;
        $metadata->image_url = $inflated->profile_picture;
        $metadata->page_url = 'https://instagram.com/' . $handle;
        $metadata->name = $handle;
        $metadata->popularity = $inflated->counts->followed_by;

        return $metadata;
    }

    public function getStatuses($pageUID, $since, $handle = null)
    {
        $posts = array();
        $media = null;
        if(!$since) {
            $now = new DateTime();
            $fromDate = $now->modify('-2 months');
            $media = $this->instagram->getUserMediaFromDate($pageUID, $fromDate)->data;
        } else {
            $media = $this->instagram->getUserMediaFromId($pageUID, $since)->data;
        }
        foreach ($media as $m) {
            $status = new InstagramStatus();
            $status->id = $m->id;
            $status->message = $m->caption ? $m->caption->text : '';
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
}