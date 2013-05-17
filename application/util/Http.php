<?php


class Util_Http {

	static $options = array(
		CURLOPT_RETURNTRANSFER => true,     // return web page
		CURLOPT_HEADER         => true,     // return headers
		CURLOPT_FOLLOWLOCATION => false,    // follow redirects
		CURLOPT_ENCODING       => "",       // handle all encodings
		CURLOPT_USERAGENT      => "spider", // who am i
//		CURLOPT_NOBODY         => true,     // don't return body
		CURLOPT_AUTOREFERER    => true,     // set referrer on redirect
		CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
		CURLOPT_TIMEOUT        => 120,      // timeout on response
		CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
	);

	/**
	 * Follows redirects to resolve the given URL to its eventual location
	 * @param $url
	 * @param int $iteration
	 * @throws RuntimeException
	 * @return string
	 */
	public static function resolveUrl($url, $iteration = 0) {
		if ($iteration > 10) {
			throw new RuntimeException('Too many redirects');
		}

		// don't bother checking https urls. Assume they are ok
		if (strpos($url, 'https://') === false) {
			$ch = curl_init($url);
			curl_setopt_array( $ch, self::$options );
			curl_exec($ch);
			$header = curl_getinfo($ch);
			curl_close($ch);

			switch ($header['http_code']) {
				case 301: // moved permanently
				case 302: // found (temporarily at different location)
				case 303: // see other
				case 305: // use proxy
				case 307: // temporary redirect
					$redirectUrl = $header['redirect_url'];
					if ($redirectUrl == $url) {
						throw new RuntimeException('Infinite redirect loop');
					}
					$url = self::resolveUrl($redirectUrl, $iteration+1);
					break;
				case 200:
					break;
				default:
					throw new RuntimeException('URL not found');
			}
		}

		return $url;
	}

}