<?php

class Model_Country extends Model_Campaign {

	const ICON_TYPE = 'icon-flag';

	public static $campaignType = '1';

	public function fromArray($data) {
		if (array_key_exists('audience', $data)) {
			$data['audience'] = str_replace(',', '', $data['audience']);
		}
		parent::fromArray($data);
	}

	public static function fetchByCountryCode($code) {
		if (!is_scalar($code)) {
			return null;
		}
		return self::fetchBy('country', $code);
	}

	public function getName() {
		return self::getNameFromCode($this->country);
	}

    /**
     * @return Model_Region|null
     */
    public function getRegion() {
        if (!$this->parent) {
            return null;
        }
        return Model_Region::fetchById($this->parent);
    }

	public static function getNameFromCode($code){
		$countries = static::countryCodes();
		if(array_key_exists($code, $countries)){
			return $countries[$code];
		} else {
			return null;
		}
	}

	public function getCountryCode()
	{
		return strtoupper($this->country);
	}

    /**
     * calculates the digital population from the country population and internet penetration in that country (penetration presented as a percentage)
     * @return int
     */
    public function getDigitalPopulation() {

        if($this->penetration)
        {
            return ( $this->population / 100 ) * $this->penetration;
        }
        else
        {
            return null;
        }

    }

    /**
     * turns country target audience into a percentage of country's digital pop
     * @return float
     */
    public function getDigitalPopulationHealth()
    {
        if($this->getDigitalPopulation())
        {
            return ( $this->audience / $this->getDigitalPopulation()) * 100;
        }
        else
        {
            return null;
        }
    }

	public function isSmallCountry()
	{
		return in_array($this->getCountryCode(), self::smallCountryCodes());
	}

    public static function smallCountryCodes(){
        return array('SG', 'AL', 'HK');
    }

	protected static $countryNames = null;
	public static function countryCodes() {
		if (!self::$countryNames) {
			$countryCodes = array(
				'AF', 'AX', 'AL', 'DZ', 'AS', 'AD', 'AO', 'AI', 'AQ', 'AG', 'AR', 'AM', 'AW', 'AU', 'AT', 'AZ', 'BS',
				'BH', 'BD', 'BB', 'BY', 'BE', 'BZ', 'BJ', 'BM', 'BT', 'BO', 'BQ', 'BA', 'BW', 'BV', 'BR', 'IO', 'BN',
				'BG', 'BF', 'BI', 'KH', 'CM', 'CA', 'CV', 'KY', 'CF', 'TD', 'CL', 'CN', 'CX', 'CC', 'CO', 'KM', 'CG',
				'CD', 'CK', 'CR', 'CI', 'HR', 'CU', 'CW', 'CY', 'CZ', 'DK', 'DJ', 'DM', 'DO', 'EC', 'EG', 'SV', 'GQ',
				'ER', 'EE', 'ET', 'FK', 'FO', 'FJ', 'FI', 'FR', 'GF', 'PF', 'TF', 'GA', 'GM', 'GE', 'DE', 'GH', 'GI',
				'GR', 'GL', 'GD', 'GP', 'GU', 'GT', 'GG', 'GN', 'GW', 'GY', 'HT', 'HM', 'VA', 'HN', 'HK', 'HU', 'IS',
				'IN', 'ID', 'IR', 'IQ', 'IE', 'IM', 'IL', 'IT', 'JM', 'JP', 'JE', 'JO', 'KZ', 'KE', 'KI', 'KP', 'KR',
				'XK', 'KW', 'KG', 'LA', 'LV', 'LB', 'LS', 'LR', 'LY', 'LI', 'LT', 'LU', 'MO', 'MK', 'MG', 'MW', 'MY',
				'MV', 'ML', 'MT', 'MH', 'MQ', 'MR', 'MU', 'YT', 'MX', 'FM', 'MD', 'MC', 'MN', 'ME', 'MS', 'MA', 'MZ',
				'MM', 'NA', 'NR', 'NP', 'NL', 'NC', 'NZ', 'NI', 'NE', 'NG', 'NU', 'NF', 'MP', 'NO', 'OM', 'PK', 'PW',
				'PS', 'PA', 'PG', 'PY', 'PE', 'PH', 'PN', 'PL', 'PT', 'PR', 'QA', 'RE', 'RO', 'RU', 'RW', 'BL', 'SH',
				'KN', 'LC', 'MF', 'PM', 'VC', 'WS', 'SM', 'ST', 'SA', 'SN', 'RS', 'SC', 'SL', 'SG', 'SX', 'SK', 'SI',
				'SB', 'SO', 'ZA', 'GS', 'SS', 'ES', 'LK', 'SD', 'SR', 'SJ', 'SZ', 'SE', 'CH', 'SY', 'TW', 'TJ', 'TZ',
				'TH', 'TL', 'TG', 'TK', 'TO', 'TT', 'TN', 'TR', 'TM', 'TC', 'TV', 'UG', 'UA', 'AE', 'GB', 'US', 'UM',
				'UY', 'UZ', 'VU', 'VE', 'VN', 'VG', 'VI', 'WF', 'EH', 'YE', 'ZM', 'ZW'
			);

			self::$countryNames = array();
			$translate = Zend_Registry::get('symfony_translate');
			foreach ($countryCodes as $code) {
				self::$countryNames[$code] = $translate->trans('country.' . $code);
			}
			asort(self::$countryNames);
		}
		return self::$countryNames;
	}

	public static function getCountriesByRegion($id) {
		return static::fetchAll('parent = ?', array($id));
	}
}
