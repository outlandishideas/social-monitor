<?php

use mikehaertl\wkhtmlto\Pdf;
use Outlandish\SocialMonitor\Models\Status;
use Outlandish\SocialMonitor\PresenceType\PresenceType;
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
        foreach (PresenceType::getAll() as $type) {
            $this->providers[] = $type->getProvider();
        }
    }

    /**
     * Statuses main page
     * @user-level user
     */
    public function indexAction()
    {
        $presences = Model_PresenceFactory::getPresences();

		$engagementBadge = $this->getContainer()->get('badge.engagement');

        $this->view->presences = $presences;
        $this->view->sortCol = Handle::NAME;
        $this->view->queryOptions = [
            [
				'name' => 'type',
				'label' => $this->translator->trans('route.statuses.index.filter.social-media'),
				'options' => PresenceType::getAll()
			],
            [
				'name' => 'country',
				'label' => $this->translator->trans('route.statuses.index.filter.countries'),
				'options' => Model_Country::fetchAll()
			],
            [
				'name' => 'region',
				'label' => $this->translator->trans('route.statuses.index.filter.regions'),
				'options' => Model_Region::fetchAll()
			],
            [
				'name' => 'sbu',
				'label' => $this->translator->trans('route.statuses.index.filter.groups'),
				'options' => Model_Group::fetchAll()
			],
            [
				'name' => 'sort',
				'label' => $this->translator->trans('route.statuses.index.sort-by'),
				'options' => [
					['value' => 'date', 'title' => $this->translator->trans('route.statuses.index.sort-by.date')],
					['value' => 'engagement', 'title' => $engagementBadge->getTitle()]
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
            $this->apiError($this->translator->trans('Error.missing-date-range'));
        }

        $types = null;

        /** If id is set, we only want statuses for this presence */
        if ($this->_request->getParam('id')) {
            $presences = [Model_PresenceFactory::getPresenceById($this->_request->getParam('id'))];
        } else {
            /** Otherwise we build an array of presence Ids */

            $countryParamString = $this->_request->getParam('country');
            $regionParamString = $this->_request->getParam('region');
            $groupParamString = $this->_request->getParam('sbu');
            $typeParamString = $this->_request->getParam('type');

            /** Update $presences to include all presences in specified countries */
            if (isset($countryParamString)) {
                $countryIds = explode(',', $countryParamString);
            } else {
                $countries = Model_Country::fetchAll();
                $countryIds = array_map(function ($c) {
                    return $c->id;
                }, $countries);

            }

            /** Add presences in specified regions */
            if (isset($regionParamString)) {
                $regionIds = explode(',', $regionParamString);
            } else {
                $regions = Model_Region::fetchAll();
                $regionIds = array_map(function ($c) {
                    return $c->id;
                }, $regions);
            }
			$regionCountryIds = array();
            foreach ($regionIds as $rid) {
                if ($rid) {
                    $countries = Model_Country::getCountriesByRegion($rid);
                    foreach ($countries as $c) {
						$regionCountryIds[] = $c->id;
                    }
                }
            }

            /** Add presences in groups */
            if (isset($groupParamString)) {
                $groupIds = explode(',', $groupParamString);
            } else {
                $groups = Model_Group::fetchAll();
                $groupIds = array_map(function ($c) {
                    return $c->id;
                }, $groups);
            }

			$campaignIds = array_filter(array_unique(array_merge($countryIds, $groupIds, $regionCountryIds)));
			$presences = Model_PresenceFactory::getPresencesByCampaigns($campaignIds);

            /** Filter presences by type */
            if (isset($typeParamString)) {
                $types = array();
                if ($typeParamString) {
                    $typeParams = explode(',', $typeParamString);
                    foreach ($typeParams as $type) {
                        $types[] = PresenceType::get($type);
                    }
                }
            }
        }

		$search = $this->getRequestSearchQuery();
		$sort = $this->_request->getParam('sort');
		if(!$sort) {
			$sort = 'date';
		}
		$order = [ $sort => 'desc' ];
        $limit = $this->getRequestLimit();
        $offset = $this->getRequestOffset();

        $streams = $this->getStatusStream(
            $presences,
            $types,
            $dateRange[0],
            $dateRange[1],
            $search,
            $order,
            $limit,
            $offset
        );

        $tableData = array();
        $count = 0;
        foreach ($streams as $data) {
            if ($data->stream) {
				$count += $data->total;
				$tableData = array_merge($data->stream, $tableData);
			}
        }

        //return CSV or JSON?
		$format = $this->_request->getParam('format');
        if ($format == 'csv') {
            $type = 'all-presence';//$presence ? $presence->type : ; //todo: what was $presence?
            $this->returnCsv($tableData, $type . 's.csv');
        } else {
            // sort according to request param
            if($sort === 'engagement') {
                usort($tableData, function ($a, $b) {
                    $aAfterB = $a['engagement']['comparable'] > $b['engagement']['comparable'];
                    return $aAfterB ? -1 : 1;
                });
            } else {
                usort($tableData, function ($a, $b) {
                    $aAfterB = $a['created_time'] > $b['created_time'];
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

	/**
	 * @param Model_Presence[] $presences
	 * @param PresenceType[] $types
	 * @param DateTime $start
	 * @param DateTime $end
	 * @param string $search
	 * @param object[] $order
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
    private function getStatusStream($presences, $types = null, \DateTime $start, \DateTime $end, $search = null,
                                     $order = null, $limit = null, $offset = null)
    {
        $statuses = array();
        if($presences && count($presences)) {
			/** @var Provider_Abstract $provider */
			foreach ($this->providers as $provider) {
				$providerType = $provider->getType();
				if (is_array($types) && count($types) && !in_array($providerType, $types)) {
					continue;
				}

				// filter presences by the current type first
				$currentPresences = array();
				foreach ($presences as $presence) {
					if ($presence->getType() == $providerType) {
						$currentPresences[] = $presence;
					}
				}
				if ($currentPresences) {
					$data = $provider->getStatusStreamMulti($currentPresences, $start, $end, $search, $order, $limit, $offset);
					$data->type = $providerType;
					$statuses[] = $data;
				}
			}
		}

        return $statuses;
    }

}
