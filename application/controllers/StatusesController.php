<?php

use mikehaertl\wkhtmlto\Pdf;
use Outlandish\SocialMonitor\Models\MultiSelectFilter;
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

		$presenceTypeFilter = new MultiSelectFilter('status-filter-presence-type', 'presence-type', 'presence-type');
		foreach (PresenceType::getAll() as $presenceType) {
			$presenceTypeFilter->addOption($presenceType->getTitle(), $presenceType->getValue());
		}

		$campaignTypeFilter = new MultiSelectFilter('status-filter-campaign-type', 'campaign-type', 'campaign-type');
		$campaignTypeFilter->multiple = false;
		$campaignTypeFilter->showFilters = [
			'all' => 'status-filter-all-presences',
			'country' => 'status-filter-country',
			'region' => 'status-filter-region',
			'group' => 'status-filter-group'
		];
		$campaignTypeFilter->addOption($this->translator->trans('route.statuses.index.filter.campaign-type.all'), 'all', true);
		$campaignTypeFilter->addOption($this->translator->trans('route.statuses.index.filter.campaign-type.country'), 'country');
		$campaignTypeFilter->addOption($this->translator->trans('route.statuses.index.filter.campaign-type.region'), 'region');
		$campaignTypeFilter->addOption($this->translator->trans('route.statuses.index.filter.campaign-type.group'), 'group');

		// a pseudo-filter to show when 'all presences' has been selected
		$allPresencesFilter = new MultiSelectFilter('status-filter-all-presences', 'campaign-ids', 'all-presences');
		$allPresencesFilter->multiple = false;
		$allPresencesFilter->enabled = false;
		$allPresencesFilter->addOption($this->translator->trans('route.statuses.index.filter.campaign-type.all'), 'all', true);

		$countryFilter = new MultiSelectFilter('status-filter-country', 'campaign-ids', 'countries');
		foreach (Model_Country::fetchAll() as $country) {
			/** @var $country Model_Country */
			$countryFilter->addOption($country->getName(), $country->id);
		}

		$regionFilter = new MultiSelectFilter('status-filter-region', 'campaign-ids', 'regions');
		foreach (Model_Region::fetchAll() as $region) {
			/** @var $region Model_Region */
			$regionFilter->addOption($region->getName(), $region->id);
		}

		$groupFilter = new MultiSelectFilter('status-filter-group', 'campaign-ids', 'groups');
		foreach (Model_Group::fetchAll() as $group) {
			/** @var $group Model_Group */
			$groupFilter->addOption($group->getName(), $group->id);
		}

		$sortFilter = new MultiSelectFilter('status-filter-sort', 'sort', 'sort-by');
		$sortFilter->multiple = false;
		$sortFilter->addOption($this->translator->trans('route.statuses.index.filter.sort-by.date'), 'date', true);
		$sortFilter->addOption($engagementBadge->getTitle(), 'engagement');

		/** @var MultiSelectFilter[] $queryFilters */
        $queryFilters = [
			$presenceTypeFilter,
			$campaignTypeFilter,
			$allPresencesFilter,
			$countryFilter,
			$regionFilter,
			$groupFilter,
			$sortFilter
		];

		// set the labels using specific ones if present, but falling back on defaults
		$textMap = [
			'selectAllText' => 'route.statuses.index.multi-select.select-all',
			'allSelectedText' => 'route.statuses.index.multi-select.all-selected',
			'countSelectedText' => 'route.statuses.index.multi-select.count-selected',
			'noMatchesFoundText' => 'route.statuses.index.multi-select.no-matches',
			'placeholderText' => 'route.statuses.index.multi-select.placeholder'
		];
		foreach ($queryFilters as $queryFilter) {
			$labelKey = 'route.statuses.index.filter.' . $queryFilter->translationSuffix . '.label';
			$label = $this->translator->trans($labelKey);
			if ($label != $labelKey) {
				$queryFilter->label = $label;
			}
			foreach ($textMap as $property => $transKey) {
				$specificKey = $transKey . '.' . $queryFilter->translationSuffix;
				$text = $this->translator->trans($specificKey);
				if ($text == $specificKey) {
					$text = $this->translator->trans($transKey . '.default');
				}
				$queryFilter->$property = $text;
			}
		}

		$this->view->queryOptions = $queryFilters;
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

        $types = PresenceType::getAll();
		$presences = array();

        /** If id is set, we only want statuses for this presence */
		$presenceId = $this->_request->getParam('id');
        if ($presenceId) {
            $presences = [Model_PresenceFactory::getPresenceById($presenceId)];
        } else {
            /** Otherwise we build an array of presence Ids */

			$campaignType = $this->_request->getParam('campaign-type');
			$campaignIdsString = $this->_request->getParam('campaign-ids');
			$campaigns = array();

			if ($campaignType == 'all') {
				$presences = Model_PresenceFactory::getPresences();
			} else {
				$campaignIds = array_filter(explode(',', $campaignIdsString));

				switch ($campaignType) {
					case 'region':
						if ($campaignIdsString == 'all') {
							$regions = Model_Region::fetchAll();
							$regionIds = array_map(function ($c) {
								return $c->id;
							}, $regions);
						} else {
							$regionIds = $campaignIds;
						}
						foreach ($regionIds as $rid) {
							$countries = Model_Country::getCountriesByRegion($rid);
							$campaigns = array_merge($campaigns, $countries);
						}
						break;
					case 'country':
						if ($campaignIdsString == 'all') {
							$campaigns = Model_Country::fetchAll();
						}
						break;
					case 'group':
						if ($campaignIdsString == 'all') {
							$campaigns = Model_Group::fetchAll();
						}
						break;
				}

				if ($campaigns) {
					$campaignIds = array_map(function (Model_Campaign $campaign) {
						return $campaign->id;
					}, $campaigns);
				}

				if ($campaignIds) {
					$presences = Model_PresenceFactory::getPresencesByCampaigns($campaignIds);
				}
			}

            /** Filter presences by type */
			$typeParamString = $this->_request->getParam('presence-type');
            if ($typeParamString != 'all') {
				$typeNames = array_filter(explode(',', $typeParamString));
                $types = array();
				foreach ($typeNames as $type) {
					$types[] = PresenceType::get($type);
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
		if ($types === null) {
			$types = PresenceType::getAll();
		}

        $statuses = array();
        if($presences && count($presences)) {
			/** @var Provider_Abstract $provider */
			foreach ($this->providers as $provider) {
				$providerType = $provider->getType();
				if (!in_array($providerType, $types)) {
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
