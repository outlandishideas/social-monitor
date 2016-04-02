<?php

use Outlandish\SocialMonitor\Cache\ObjectCacheManager;
use Outlandish\SocialMonitor\PresenceType\PresenceType;
use Outlandish\SocialMonitor\TableIndex\TableIndex;

abstract class CampaignController extends GraphingController
{
    public function managePresencesList()
    {
        $presences = array();
        foreach(PresenceType::getAll() as $type) {
            $presences[] = array(
                'type' => $type->getValue(),
                'title' => $type->getTitle(),
                'presences' => Model_PresenceFactory::getPresencesByType($type),
                'sign' => $type->getSign()
            );
        }
        return $presences;
    }

	protected function invalidateTableCache()
	{
		$objectCacheManager = $this->getContainer()->get('object-cache-manager');
		$table = $this->getIndexTable($objectCacheManager);
		$objectCacheManager->invalidateObjectCache($table->getIndexName());
	}

	/**
	 * @param ObjectCacheManager $objectCacheManager
	 * @return TableIndex
	 */
	abstract function getIndexTable($objectCacheManager);
}

