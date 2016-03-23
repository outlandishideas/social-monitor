<?php


class Zend_View_Helper_TrafficLight extends Zend_View_Helper_Abstract
{
	public function trafficLight() {
		return $this;
	}

	public function __construct($view = null) {
		$this->view = $view;
	}

	/**
	 * Converts the value for the given metric into a color hex string
	 * @param $value
	 * @param $metric
	 * @return null|string
	 */
	public function color($value, $metric) {
        if($value == 0 && $metric == 'response_time'){

            $color = "#000";

        } else {

            $metric = $this->view->trafficMetrics[$metric];
            $color = null;



            foreach ($metric->range as $i=>$v) {
                $color = $metric->colors[$i];
                if ($value < $metric->range[$i]) {
                    break;
                }
            }

            if (isset($i) && $i > 0 && $i < count($metric->range)) {
                $start = $metric->range[$i-1];
                $end = $metric->range[$i];
                $fraction = ($value - $start)/($end - $start);
                if ($fraction < 1) {
                    $color = '#';
                    for ($j=0; $j<3; $j++) {
                        $start = $metric->colorsRgb[$i-1][$j];
                        $end = $metric->colorsRgb[$i][$j];
                        $part = round($start + $fraction*($end - $start));
                        $part = dechex($part);
                        if (strlen($part) < 2) {
                            $part = '0' . $part;
                        }
                        $color .= $part;
                    }
                    $color = strtoupper($color);
                }
            }
        }

		return $color;
	}

	public function label($value, $metric) {
        if($value == 0 && $metric == 'response_time'){

            $label = "N/A";

        } else {

            $label = round($value * 100)/100;
            switch ($metric) {
                case Metric_PopularityTime::NAME:
                    if ($value == 0) {
                        $label = 'Target already reached';
                    } else {
                        $tmp = floor($value);
                        $fraction = $value - $tmp;
                        $months = $tmp%12;
                        $years = ($tmp - $months)/12;
                        $months = round(($months + $fraction)*10)/10;
                        $components = array();
                        if ($years != 0) {
                            $components[] = $years . ' year' . ($years == 1 ? '' : 's');
                        }
                        if ($months != 0) {
                            $components[] = $months . ' month' . ($months == 1 ? '' : 's');
                        }
                        $label = implode(', ', $components);
                    }
                    break;
                case Metric_Popularity::NAME:
                    $label .= '%';
                    break;
                case 'digital_population':
                    $label .= '%';
                    break;
                case Metric_ResponseTime::NAME:
                    $label .= ' hours';
                    break;
            }
        }
		return $label;
	}
}