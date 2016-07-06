<?php

namespace Outlandish\SocialMonitor\Services\TableIndex;

use Outlandish\SocialMonitor\Services\TableIndex\Interfaces\Rule;

/**
 * This class checks to see whether a user of a given level can view the defined table column
 *
 * This class is to be used when printing out the tables for the channel, country etc indexes. It
 * allows you to provide a blacklist of columns for a given user level that should not be shown
 * for that level.
 *
 * @package Outlandish\SocialMonitor\Services\TableIndex
 */
class Guard
{
	/**
	 * @var Interfaces\Rule[]
	 */
	private $rules;

	public function __construct(array $rules)
	{
		$this->rules = $rules;
	}

	public function userCanSee(\Model_User $user, Header $column)
	{
		return $this->findRuleForUser($user)->canSee($column);
	}

	/**
	 * @param \Model_User|null $user
	 * @return Rule
	 */
	private function findRuleForUser(\Model_User $user = null)
	{
		foreach ($this->rules as $rule) {
			if ($rule->isFor($user)) {
				return $rule;
			}
		}

		return new NullRule;
	}

}