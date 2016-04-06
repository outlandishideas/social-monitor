<?php

namespace Outlandish\SocialMonitor\Models;

class MultiSelectFilter
{
	public $id;
	public $name;
	public $label;
	public $multiple = true;
	public $enabled = true;
	public $showFilters = null;
	public $options = array();
	public $selectAllText;
	public $allSelectedText;
	public $countSelectedText;
	public $noMatchesFoundText;
	public $placeholderText;

	public function __construct($id, $name, $label = '&nbsp;')
	{
		$this->id = $id;
		$this->name = $name;
		$this->label = $label;
	}

	public function addOption($title, $value, $selected = false)
	{
		$this->options[] = [
			'title' => $title,
			'value' => $value,
			'selected' => $selected
		];
	}

}