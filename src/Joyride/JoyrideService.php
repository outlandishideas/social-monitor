<?php
/**
 * Created by PhpStorm.
 * User: Matthew
 * Date: 05/04/2016
 * Time: 16:23
 */

namespace Outlandish\SocialMonitor\Joyride;


class JoyrideService
{
	private $id;

	public function __construct($id, array $steps)
	{
		$this->steps = $steps;
		$this->id = $id;
	}

	public function renderHtml()
	{
		//if no steps return an empty string
		if (empty($this->steps)) {
			return "";
		}

		$html = "<ol id=\"{$this->id}\" class=\"joyride-list\" data-joyride>";

		foreach ($this->steps as $index => $step) {
			$html .= $this->addStep($index, $step);
		}

		$html .= "</ol>";

		return $html;
	}

	private function addStep($index, $step)
	{
		$html = $this->addListItem($index, $step);
		$html .= $this->addTitle($step);
		$html .= $this->addContent($step);
		$html .= $this->addListItem($index, $step, true);

		return $html;
	}

	private function addListItem($index, $step, $end = false)
	{
		if ($end) {
			return '</li>';
		}

		$options = [];
		$nextButton = 'Next';

		if ($index == 0) {
			$options[] = 'prev_button: false';
		}

		if ($index == (count($this->steps)-1)) {
			$nextButton = 'End';
		}

		$id = $step[0] !== null ? "data-id=\"{$step[0]}\"" : "";

		return "<li {$id} data-button=\"{$nextButton}\" data-prev-text=\"Prev\" data-options=\"{$this->dataOptions($options)}\">";
	}

	private function addTitle($step)
	{
		$title = $step[1];
		if (!$title) {
			return "";
		}
		return "<h4>{$title}</h4>";
	}

	private function addContent($step)
	{
		$content = $step[2];
		if (!$content) {
			return "";
		}

		return "<p>{$content}</p>";
	}

	private function dataOptions($options = [], $override = false)
	{
		$default = $override ? [] : ['tip_location:top', 'tip_animation:fade'];
		return implode(";", array_merge($default, $options));
	}
}