<?php

namespace Outlandish\SocialMonitor\Models;

class MultiSelectFilter
{
	public $id;
	public $name;
	public $label = '&nbsp;';
	public $translationSuffix;
	public $multiple = true;
	public $enabled = true;
	public $showFilters = null;
	public $options = array();
	public $selectAllText;
	public $allSelectedText;
	public $countSelectedText;
	public $noMatchesFoundText;
	public $placeholderText;

	public function __construct($id, $name, $translationSuffix)
	{
		$this->id = $id;
		$this->name = $name;
		$this->translationSuffix = $translationSuffix;
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