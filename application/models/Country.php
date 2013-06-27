<?php

class Model_Country extends Model_Campaign {

    const ICON_TYPE = 'icon-globe';

	protected function fetch($clause = null, $args = array()) {
		if ($clause) {
			$clause .= ' AND ';
		}
		$clause .= ' is_country = 1';
		return parent::fetch($clause, $args);
	}

    public function countryInfo(){
        return array(
            'audience' => (object)array(
                'title' => 'Audience',
                'value' => number_format($this->audience),
            ),
            'pages' => (object)array(
                'title' => 'Facebook Pages',
                'value' => count($this->getFacebookPages()),
            ),
            'handles' => (object)array(
                'title' => 'Twitter Accounts',
                'value' => count($this->getTwitterAccounts()),
            ),
            'notes' => (object)array(
                'title' => 'Notes',
                'value' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum lacus eros, vulputate non mollis sed, egestas ut enim. Sed hendrerit dui nibh. Morbi bibendum feugiat nulla a tempus. Nam mattis egestas nisl a pulvinar. Curabitur non libero quis dolor ultricies tincidunt suscipit condimentum justo.' //$this->notes
            )
        );
    }

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

	public function getTargetAudience() {
		return $this->audience;
	}

	public static function getNameFromCode($code){
        $countries = static::countryCodes();
        if(array_key_exists($code, $countries)){
            return $countries[$code];
        } else {
            return null;
        }
    }

    public static function mapDataFactory(){

        $endDate = new DateTime();
        $startDate = clone $endDate;
        $startDate->modify('-30 days');

        $badges = Model_Badge::ALL_BADGES_TITLE();

        $data = self::getBadgeData($startDate, $endDate);

        //get list of presences
        $campaignIds = array();
        array_map(function($a) use (&$campaignIds) {
            if(!isset($campaignIds[$a->campaign_id])) $campaignIds[$a->campaign_id] = 0;
            $campaignIds[$a->campaign_id]++;
        }, $data);

        //set up an object for each country
        $campaigns = array();
        foreach(array_keys($campaignIds) as $id){
            $country = Model_Country::fetchById($id);
            $row = (object)array(
                'country' => $country->country,
                'name' => $country->display_name,
                'id'=>intval($id),
                'targetAudience' => $country->getTargetAudience(),
                'presenceCount' => count($country->presences),
                'presences' => array()
            );

            //foreach badge, add a place to put scores to the country object
            foreach ($badges as $badge=>$m) {

                $row->$badge = array();

            }
            $campaigns[$id] = $row;
        }

        //now that we have country objects set up, go though the data and assign it to the appropriate object
        foreach($data as $row){
            //ignore the "week" range data for th moment
            //todo include week data in the data that we send out as json
            if($row->type != 'week'){

                //calculate the number of days since this row of data was created
                $rowDate = new DateTime($row->datetime);
                $rowDiff = $rowDate->diff($endDate);
                $days = $rowDiff->days;
                //turn it around so that the most recent data is the has the highest score
                //this is because jquery slider has a value going 0-30 (left to right) and we want time to go in reverse
                $days = 30-$days;

                //each metric property in a country object is an array of days (0-30)
                //if the day doesn't already exist, create it with a 0 value
                if(!isset($campaigns[$row->campaign_id]->reach[$days])) $campaigns[$row->campaign_id]->reach[$days] = 0;
                if(!isset($campaigns[$row->campaign_id]->engagement[$days])) $campaigns[$row->campaign_id]->engagement[$days] = 0;
                if(!isset($campaigns[$row->campaign_id]->quality[$days])) $campaigns[$row->campaign_id]->quality[$days] = 0;

                //add the badge values for this row to the appropriate country model
                //multiple values per country, because countries have more than one presence
                $campaigns[$row->campaign_id]->reach[$days] += $row->reach;
                $campaigns[$row->campaign_id]->engagement[$days] += $row->engagement;
                $campaigns[$row->campaign_id]->quality[$days] += $row->quality;

            }
        }

        //calculate the total scores for each day for each country object
        foreach($campaigns as $campaign){

            //setup the total score
            $campaign->total = array();

            //go though each day in each badge in each country and convert the score into an score/label object for geochart
            foreach($badges as $badge => $metrics){

                foreach($campaign->$badge as $v => $value){
                    $value /= $campaign->presenceCount;
                    $object = (object)array('score'=>$value, 'label'=>round($value).'%');
                    $campaign->{$badge}[$v] = $object;
                    if(!isset($campaign->total[$v])) $campaign->total[$v] = 0;
                    $campaign->total[$v] += $value;
                }

            }

            foreach($campaign->total as $v => $value){
                $value /= count($badges);
                $object = (object)array('score'=>$value, 'label'=>round($value).'%');
                $campaign->total[$v] = $object;
            }

        }
        return $campaigns;

    }

    public static function countryCodes() {
        return array(
            'AF' => 'Afghanistan',
            'AX' => 'Åland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia, Plurinational state of',
            'BQ' => 'Bonaire, Sint Eustatius and Saba',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, the Democratic Republic of the',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Côte d\'Ivoire',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CW' => 'Curaçao',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic of',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KP' => 'Korea, Democratic People\'s Republic of',
            'KR' => 'Korea, Republic of',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia, the Former Yugoslav Republic of',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States of',
            'MD' => 'Moldova, Republic of',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestine, State of',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Réunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthélemy',
            'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin (French Part)',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome and Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SX' => 'Sint Maarten (Dutch Part)',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia and the South Sandwich Islands',
            'SS' => 'South Sudan',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard and Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan, Province of China',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania, United Republic of',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Minor Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela, Bolivarian Republic of',
            'VN' => 'Vietnam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        );
    }
}
