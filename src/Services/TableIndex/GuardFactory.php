<?php

namespace Outlandish\SocialMonitor\Services\TableIndex;


class GuardFactory
{
	public function createGuard(array $rules)
	{
		$rules = array_map(function($rule) {
			$id = array_key_exists('user_level', $rule) ? $rule['user_level'] : 1;
			$columns = array_key_exists('columns', $rule) ? $rule['columns'] : [];
			return new Rule($id, $columns);
		}, $rules);

		return new Guard($rules);
	}
}