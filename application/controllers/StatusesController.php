<?php

use mikehaertl\wkhtmlto\Pdf;
use Outlandish\SocialMonitor\Report\ReportablePresence;
use Outlandish\SocialMonitor\Report\ReportGenerator;
use Outlandish\SocialMonitor\TableIndex\Header\ActionsPerDay;
use Outlandish\SocialMonitor\TableIndex\Header\Branding;
use Outlandish\SocialMonitor\TableIndex\Header\CurrentAudience;
use Outlandish\SocialMonitor\TableIndex\Header\Handle;
use Outlandish\SocialMonitor\TableIndex\Header\Header;
use Outlandish\SocialMonitor\TableIndex\Header\Options;
use Outlandish\SocialMonitor\TableIndex\Header\ResponseTime;
use Outlandish\SocialMonitor\TableIndex\Header\SignOff;
use Outlandish\SocialMonitor\TableIndex\Header\TargetAudience;
use Outlandish\SocialMonitor\TableIndex\Header\TotalRank;
use Outlandish\SocialMonitor\TableIndex\Header\TotalScore;
use Outlandish\SocialMonitor\TableIndex\TableIndex;

class StatusesController extends GraphingController
{
    protected static $publicActions = array();
    protected $providers = array();

    public function init()
    {
        parent::init();
        foreach (Enum_PresenceType::enumValues() as $type) {
            $this->providers[] = $type->getProvider();
        }
    }

    /**
     * Statuses main page
     * @user-level user
     */
    public function indexAction()
    {
        Model_PresenceFactory::setDatabase(Zend_Registry::get('db')->getConnection());
        $presences = Model_PresenceFactory::getPresences();

        $this->view->title = 'Statuses';
        $this->view->presences = $presences;
        $this->view->sortCol = Handle::getName();
        $this->view->queryOptions = [
            ['name' => 'type', 'label' => 'Social Media', 'options' => Enum_PresenceType::enumValues()],
            ['name' => 'country', 'label' => 'Country', 'options' => Model_Country::fetchAll()],
            ['name' => 'region', 'label' => 'Region', 'options' => Model_Region::fetchAll()],
            ['name' => 'sbu', 'label' => 'SBU', 'options' => Model_Group::fetchAll()],
            ['name' => 'sort', 'label' => 'Sort', 'options' =>
                [
                    ['value' => 'date', 'title' => 'Date'],
                    ['value' => 'engagement', 'title' => 'Engagement']
                ]
            ]
        ];
    }

    /**
     * AJAX function for fetching the statuses
     */
    public function listAction()
    {
        Zend_Session::writeClose(); //release session on long running actions

        $dateRange = $this->getRequestDateRange();
        if (!$dateRange) {
            $this->apiError('Missing date range');
        }

        /** @var Model_Presence $presence */
        $format = $this->_request->getParam('format');
        $types = null;
        $presences = array();

        /** If id is set, we only want statuses for this presence */
        if ($this->_request->getParam('id')) {
            $presences = [Model_PresenceFactory::getPresenceById($this->_request->getParam('id'))];
        } else {
            /** Otherwise we build an array of presence Ids */

            $countryParamString = $this->_request->getParam('country');
            $regionParamString = $this->_request->getParam('region');
            $sbuParamString = $this->_request->getParam('sbu');

            /** Update $presences to include all presences in specified countries */
            if (isset($countryParamString)) {
                $countryParams = explode(',', $countryParamString);
            } else {
                $countries = Model_Country::fetchAll();
                $countryParams = array_map(function ($c) {
                    return $c->id;
                }, $countries);

            }
            foreach ($countryParams as $cid) {
                if ($cid) {
                    $countryPresences = Model_PresenceFactory::getPresencesByCampaign($cid);
                    $presences = array_merge($presences, $countryPresences);
                }
            }

            /** Add presences in specified regions */
            if (isset($regionParamString)) {
                $regionParams = explode(',', $regionParamString);
            } else {
                $regions = Model_Region::fetchAll();
                $regionParams = array_map(function ($c) {
                    return $c->id;
                }, $regions);
            }
            foreach ($regionParams as $rid) {
                $countries = Model_Country::getCountriesByRegion($rid);
                foreach ($countries as $c) {
                    $countryPresences = Model_PresenceFactory::getPresencesByCampaign($c->id);
                    $presences = array_merge($presences, $countryPresences);
                }
            }

            /** Add presences in SBUs */
            if (isset($sbuParamString)) {
                $sbuParams = explode(',', $sbuParamString);
            } else {
                $sbus = Model_Group::fetchAll();
                $sbuParams = array_map(function ($c) {
                    return $c->id;
                }, $sbus);
            }
            foreach ($sbuParams as $sid) {
                if ($sid) {
                    $sbuPresences = Model_PresenceFactory::getPresencesByCampaign($sid);
                    $presences = array_merge($presences, $sbuPresences);
                }
            }

            /** Filter presences by type */
            if ($this->_request->getParam('type')) {
                $typeParamString = $this->_request->getParam('type');
                $types = array();
                if ($typeParamString) {
                    $typeParams = explode(',', $typeParamString);
                    foreach ($typeParams as $type) {
                        $types[] = Enum_PresenceType::get($type);
                    }
                }
            }
        }

        $limit = $this->getRequestLimit();

        $streams = $this->getStatusStream(
            $presences,
            $types,
            $dateRange[0],
            $dateRange[1],
            $this->getRequestSearchQuery(),
            $this->getRequestOrdering(),
            $limit,
            $this->getRequestOffset()
        );

        $tableData = array();
        $count = 0;
        foreach ($streams as $data) {
            $type = $data->type;
            $stream = $data->stream;

            if (!$stream) {
                continue;
            }

            // aren't these going to be the same?
            $count = $count + $data->total;

            switch ($type) {
                case Enum_PresenceType::TWITTER:
                    foreach ($stream as $status) {
                        $tweet = (object)$status;
                        $tableData[] = array(
                            'id' => $tweet->id,
                            'message' => $format == 'csv' ? $tweet->text_expanded : $tweet->html_tweet,
                            'date' => Model_Base::shortDate($tweet->created_time),
                            'isodate' => $tweet->created_time,
                            'links' => $tweet->links,
                            'twitter_url' => $tweet->permalink,
                            'presence_id' => $tweet->presence_id,
                            'engagement' => [
                                'shares' => $tweet->retweet_count,
                                'comparable' => $tweet->retweet_count
                            ],
                            'icon' => Enum_PresenceType::TWITTER()->getSign()
                        );
                    }
                    break;
                case Enum_PresenceType::FACEBOOK:
                    foreach ($stream as $status) {
                        $post = (object)$status;
                        if ($post->message) {
                            if ($post->first_response) {
                                $response = $post->first_response->message;
                                $responseDate = $post->first_response->created_time;
                            } else {
                                $response = null;
                                $responseDate = gmdate('Y-m-d H:i:s');
                            }

                            $timeDiff = strtotime($responseDate) - strtotime($post->created_time);
                            $components = array();
                            $timeDiff /= 60;

                            $elements = array(
                                'minute' => 60,
                                'hour' => 24,
                                'day' => 100000
                            );
                            foreach ($elements as $label => $size) {
                                $val = $timeDiff % $size;
                                $timeDiff /= $size;
                                if ($val) {
                                    array_unshift($components, $val . ' ' . $label . ($val == 1 ? '' : 's'));
                                }
                            }

                            $tableData[] = array(
                                'id' => $post->id,
//						'actor_type' => $post->actor->type,
                                'actor_name' => $post->actor->name,
//						'pic_url' => $post->actor->pic_url,
                                'facebook_url' => $post->permalink,
                                'profile_url' => $post->actor->profile_url,
                                'message' => $post->message,
                                'links' => $post->links,
                                'date' => Model_Base::shortDate($post->created_time),
                                'isodate' => $post->created_time,
                                'needs_response' => $post->needs_response,
                                'first_response' => array(
                                    'message' => $response,
                                    'date' => Model_Base::shortDate($responseDate),
                                    'date_diff' => implode(', ', $components),
                                ),
                                'presence_id' => $post->presence_id,
                                'engagement' => [
                                    'shares' => $post->share_count,
                                    'likes' => $post->likes,
                                    'comments' => $post->comments,
                                    'comparable' => (($post->likes + $post->comments * 4 + $post->share_count * 7) / 12)
                                ],
                                'icon' => Enum_PresenceType::FACEBOOK()->getSign()
                            );
                        }
                    }
                    break;
                case Enum_PresenceType::SINA_WEIBO:
                    foreach ($stream as $status) {
                        $post = (object)$status;
                        $tableData[] = array(
                            'id' => $post->id,
                            'url' => Provider_SinaWeibo::BASEURL . $post->remote_user_id . '/' . Provider_SinaWeibo::getMidForPostId($post->remote_id),
                            'message' => $post->text,
                            'links' => $post->links,
                            'date' => Model_Base::shortDate($post->created_at),
                            'presence_id' => $post->presence_id,
                            'isodate' => $post->created_at,
                            'engagement' => [
                                'shares' => $post->repost_count,
                                'likes' => $post->attitude_count,
                                'comments' => $post->comment_count,
                                'comparable' => (($post->attitude_count + $post->comment_count * 4 + $post->repost_count * 7) / 12)
                            ],
                            'icon' => Enum_PresenceType::SINA_WEIBO()->getSign()
                        );
                    }
                    break;
                case Enum_PresenceType::INSTAGRAM:
                    foreach ($stream as $status) {
                        $post = (object)$status;
                        $tableData[] = array(
                            'id' => $post->id,
                            'url' => $post->permalink,
                            'message' => $post->message . ' <img src="' . $post->image_url . '">',
                            'links' => array(),
                            'date' => Model_Base::shortDate($post->created_time),
                            'isodate' => $post->created_time,
                            'presence_id' => $post->presence_id,
                            'engagement' => [
                                'comments' => $post->comments,
                                'likes' => $post->likes,
                                'comparable' => (($post->likes + $post->comments * 4) / 5)
                            ],
                            'icon' => Enum_PresenceType::INSTAGRAM()->getSign()
                        );
                    }
                    break;
                case Enum_PresenceType::YOUTUBE:
                    foreach ($stream as $status) {
                        $post = (object)$status;
                        $tableData[] = array(
                            'id' => $post->id,
                            'url' => '', //messages don't have a direct link
                            'message' => $post->message,
                            'links' => array(),
                            'date' => Model_Base::shortDate($post->created_time),
                            'isodate' => $post->created_time,
                            'presence_id' => $post->presence_id,
                            'engagement' => [
                                'comments' => $post->number_of_replies,
                                'likes' => $post->likes,
                                'comparable' => (($post->likes + $post->number_of_replies * 4) / 5)
                            ],
                            'icon' => Enum_PresenceType::YOUTUBE()->getSign()
                        );
                    }
                    break;
                case Enum_PresenceType::LINKEDIN:
                    foreach ($stream as $status) {
                        $post = (object)$status;
                        $tableData[] = array(
                            'id' => $post->id,
                            'url' => '', //messages don't have a direct link
                            'message' => $post->message,
                            'links' => array(),
                            'date' => Model_Base::shortDate($post->created_time),
                            'isodate' => $post->created_time,
                            'presence_id' => $post->presence_id,
                            'engagement' => [
                                'comments' => $post->comments,
                                'likes' => $post->likes,
                                'comparable' => (($post->likes + $post->comments * 4) / 5)
                            ],
                            'icon' => Enum_PresenceType::LINKEDIN()->getSign()
                        );
                    }
                    break;
                default:
                    break;
            }
        }

        //return CSV or JSON?
        if ($this->_request->getParam('format') == 'csv') {
            $type = $presence ? $presence->type : 'all-presence';
            $this->returnCsv($tableData, $type . 's.csv');
        } else {
            // sort according to request param
            if($this->_request->getParam('sort') === 'engagement') {
                usort($tableData, function ($a, $b) {
                    $aAfterB = $a['engagement']['comparable'] > $b['engagement']['comparable'];
                    return $aAfterB ? -1 : 1;
                });
            } else {
                usort($tableData, function ($a, $b) {
                    $aAfterB = $a['isodate'] > $b['isodate'];
                    return $aAfterB ? -1 : 1;
                });
            }
            $displayRows = array_slice($tableData, 0, $limit);
            $apiResult = array(
                'sEcho' => $this->_request->getParam('sEcho'),
                'iTotalRecords' => $count, // total rows before filtering
                'iTotalDisplayRecords' => $count, // total rows after filtering. these are the same as we don't filter
                'aaData' => $displayRows
            );
            $this->apiSuccess($apiResult);
        }

    }

    private function getStatusStream($presences, $types = null, \DateTime $start, \DateTime $end, $search = null,
                                     $order = null, $limit = null, $offset = null)
    {
        $statuses = array();
        /** @var Provider_Abstract $provider */
        foreach ($this->providers as $provider) {
            if ($types && count($types) && in_array($provider->getType(), $types)) {
                $data = $provider->getStatusStreamMulti($presences, $start, $end, $search, $order, $limit, $offset);
                $data->type = $provider->getType();
                $statuses[] = $data;
            }
        }

        return $statuses;
    }

    // creates an array of ordering arguments (propName=>direction) from the datatables request args
    protected function getRequestOrdering()
    {
        $sort = $this->_request->getParam('sort');
        if(!$sort) {
            $sort = 'date';
        }
        return [ $sort => 'desc' ];
    }

    /**
     * @return Header[]
     */
    protected function tableIndexHeaders()
    {
        return array(
//            Header_Compare::getInstance(),//todo: reinstate when compare functionality is restored
            Handle::getInstance(),
            SignOff::getInstance(),
            Branding::getInstance(),
            TotalRank::getInstance(),
            TotalScore::getInstance(),
            CurrentAudience::getInstance(),
            TargetAudience::getInstance(),
            ActionsPerDay::getInstance(),
            ResponseTime::getInstance(),
            Options::getInstance()
        );
    }

}
