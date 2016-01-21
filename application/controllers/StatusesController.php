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

    public function init() {
        parent::init();
        foreach(Enum_PresenceType::enumValues() as $type) {
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

        /** @var TableIndex $indexTable */
        $indexTable = $this->getContainer()->get('table.presence-index');
        $rows = $this->getTableIndex('presence-index', $indexTable, $presences);

        $this->view->title = 'Statuses';
        $this->view->presences = $presences;
        $this->view->rows = $rows;
        $this->view->tableHeaders = $indexTable->getHeaders();
        $this->view->sortCol = Handle::getName();
    }

    /**
     * Views a specific status
     */
    public function viewAction()
    {
        $presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
        $this->validateData($presence);

        $this->view->presence = $presence;
        $this->view->badgePartial = $this->badgeDetails($presence);
        $this->view->chartOptions = $this->chartOptions($presence);
        $allPresences = array();
        foreach (Model_PresenceFactory::getPresences() as $p) {
            $group = $p->getType()->getTitle();
            if (!isset($allPresences[$group])) {
                $allPresences[$group] = array();
            }
            $allPresences[$group][] = $p;
        }
        $this->view->allPresences = $allPresences;
    }

    /**
     * AJAX function for fetching the statuses
     */
    public function listAction() {
        Zend_Session::writeClose(); //release session on long running actions

        $dateRange = $this->getRequestDateRange();
        if (!$dateRange) {
            $this->apiError('Missing date range');
        }

        /** @var Model_Presence $presence */
        $presence = Model_PresenceFactory::getPresenceById($this->_request->getParam('id'));
        $format = $this->_request->getParam('format');
        $type = null;
        if($this->_request->getParam('type')) {
            $type = Enum_PresenceType::get($this->_request->getParam('type'));
        }

        $streams = $this->getStatusStream(
            $presence,
            $type,
            $dateRange[0],
            $dateRange[1],
            $this->getRequestSearchQuery(),
            $this->getRequestOrdering(),
            $this->getRequestLimit(),
            $this->getRequestOffset()
        );

        $tableData = array();
        $count = 0;
        $display = 0;
        foreach($streams as $data) {
            $type = $data->type;
            $stream = $data->stream;

            // aren't these going to be the same?
            $count = $count + $data->total;
            $display = $display + count($stream);

            switch($type) {
                case Enum_PresenceType::TWITTER:
                    foreach($stream as $status) {
                        $tweet = (object)$status;
                        $tableData[] = array(
                            'id' => $tweet->id,
                            'message' => $format == 'csv' ? $tweet->text_expanded : $tweet->html_tweet,
                            'date' => Model_Base::localeDate($tweet->created_time),
                            'links' => $tweet->links,
                            'twitter_url' => $tweet->permalink
                        );
                    }
                    break;
                case Enum_PresenceType::FACEBOOK:
                    foreach($stream as $status) {
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
                                'date' => Model_Base::localeDate($post->created_time),
                                'needs_response' => $post->needs_response,
                                'first_response' => array(
                                    'message' => $response,
                                    'date' => Model_Base::localeDate($responseDate),
                                    'date_diff' => implode(', ', $components),
                                )
                            );
                        }
                    }
                    break;
                case Enum_PresenceType::SINA_WEIBO:
                    foreach($stream as $status) {
                        $post = (object)$status;
                        $tableData[] = array(
                            'id' => $post->id,
                            'url' => Provider_SinaWeibo::BASEURL . $post->remote_user_id . '/' . Provider_SinaWeibo::getMidForPostId($post->remote_id),
                            'message' => $post->text,
                            'links' => $post->links,
                            'date' => Model_Base::localeDate($post->created_at)
                        );
                    }
                    break;
                case Enum_PresenceType::INSTAGRAM:
                    foreach($stream as $status) {
                        $post = (object)$status;
                        $tableData[] = array(
                            'id' => $post->id,
                            'url' => $post->permalink,
                            'message' => $post->message . ' <img src="' . $post->image_url . '">',
                            'links' => array(),
                            'date' => Model_Base::localeDate($post->created_time)
                        );
                    }
                    break;
                case Enum_PresenceType::YOUTUBE:
                    foreach($stream as $status) {
                        $post = (object)$status;
                        $tableData[] = array(
                            'id' => $post->id,
                            'url' => '', //messages don't have a direct link
                            'message' => $post->message,
                            'links' => array(),
                            'date' => Model_Base::localeDate($post->created_time)
                        );
                    }
                    break;
                case Enum_PresenceType::LINKEDIN:
                    foreach($stream as $status) {
                        $post = (object)$status;
                        $tableData[] = array(
                            'id' => $post->id,
                            'url' => '', //messages don't have a direct link
                            'message' => $post->message,
                            'links' => array(),
                            'date' => Model_Base::localeDate($post->created_time)
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
            $this->returnCsv($tableData, $type.'s.csv');
        } else {
            $apiResult = array(
                'sEcho' => $this->_request->getParam('sEcho'),
                'iTotalRecords' => $count,
                'iTotalDisplayRecords' => $display,
                'aaData' => $tableData
            );
            $this->apiSuccess($apiResult);
        }

    }

    private function getStatusStream(Model_Presence $presence = null, Enum_PresenceType $type = null, \DateTime $start, \DateTime $end, $search = null,
                                     $order = null, $limit = null, $offset = null)
    {
        if($presence) {
            $data = $presence->getStatusStream($start,$end,$search,$order,$limit,$offset);
            $data->type = $presence->getType();
            return [$data];
        }

        $statuses = array();
        /** @var Provider_Abstract $provider */
        foreach($this->providers as $provider) {
            if(!$type || $type === $provider->getType()) {
                $data = $provider->getStatusStream(null, $start, $end, $search, $order, $limit, $offset);
                $data->type = $provider->getType();
                $statuses[] = $data;
            }
        }

        return $statuses;
    }
    /**
     * @return Header[]
     */
    protected function tableIndexHeaders() {
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
