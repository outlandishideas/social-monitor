<?php
/**
 * PHP SDK for weibo.com (using OAuth2)
 *
 * @author Elmer Zhang <freeboy6716@gmail.com>
 */

defined('APP_ROOT_PATH')
    || define('APP_ROOT_PATH', realpath(__DIR__.'/../../'));

require_once APP_ROOT_PATH.'/lib/twitteroauth/OAuth.php';


/**
 * 新浪微博 OAuth 认证类(OAuth2) // sina weibo OAuth authorization class
 *
 * 授权机制说明请大家参考微博开放平台文档：{@link http://open.weibo.com/wiki/Oauth2} // for detail plese see the document.
 *
 * @package sae
 * @author Elmer Zhang
 * @version 1.0
 */
class SaeTOAuthV2 {
	/**
	 * @ignore
	 */
	public $client_id;
	/**
	 * @ignore
	 */
	public $client_secret;
	/**
	 * @ignore
	 */
	public $access_token;
	/**
	 * @ignore
	 */
	public $refresh_token;
	/**
	 * Contains the last HTTP status code returned.
	 *
	 * @ignore
	 */
	public $http_code;
	/**
	 * Contains the last API call.
	 *
	 * @ignore
	 */
	public $url;
	/**
	 * Set up the API root URL.
	 *
	 * @ignore
	 */
	public $host = "https://api.weibo.com/2/";
	/**
	 * Set timeout default.
	 *
	 * @ignore
	 */
	public $timeout = 30;
	/**
	 * Set connect timeout.
	 *
	 * @ignore
	 */
	public $connecttimeout = 30;
	/**
	 * Verify SSL Cert.
	 *
	 * @ignore
	 */
	public $ssl_verifypeer = FALSE;
	/**
	 * Respons format.
	 *
	 * @ignore
	 */
	public $format = 'json';
	/**
	 * Decode returned json data.
	 *
	 * @ignore
	 */
	public $decode_json = TRUE;
	/**
	 * Contains the last HTTP headers returned.
	 *
	 * @ignore
	 */
	public $http_info;
	/**
	 * Set the useragnet.
	 *
	 * @ignore
	 */
	public $useragent = 'Sae T OAuth2 v0.1';

	/**
	 * print the debug info
	 *
	 * @ignore
	 */
	public $debug = FALSE;

	/**
	 * boundary of multipart
	 * @ignore
	 */
	public static $boundary = '';

	/**
	 * Set API URLS
	 */
	/**
	 * @ignore
	 */
	function accessTokenURL()  { return 'https://api.weibo.com/oauth2/access_token'; }
	/**
	 * @ignore
	 */
	function authorizeURL()    { return 'https://api.weibo.com/oauth2/authorize'; }

	/**
	 * construct WeiboOAuth object
	 */
	function __construct($client_id, $client_secret, $access_token = NULL, $refresh_token = NULL) {
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->access_token = $access_token;
		$this->refresh_token = $refresh_token;
	}

	/**
	 * authorize接口
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/Oauth2/authorize Oauth2/authorize} // please see the API
	 *
	 * @param string $url 授权后的回调地址,站外应用需与回调地址一致,站内应用需要填写canvas page的地址 //The callback address after authorization. The application outside the website should be the same as the callback address. The application inside should fill canvas page address.
	 * @param string $response_type 支持的值包括 code 和token 默认值为code //support value: code and token, default:code
	 * @param string $state 用于保持请求和回调的状态。在回调时,会在Query Parameter中回传该参数 // used for keeping request and callback states In callback, it will return in the parameter Query Parameter.
	 * @param string $display 授权页面类型 可选范围:  //The authorization page type:
	 *  - default		默认授权页面		 //default page
	 *  - mobile		支持html5的手机		// the phone that supports html5
	 *  - popup			弹窗授权页		// popup authorization pages
	 *  - wap1.2		wap1.2页面		// wap1.2 page
	 *  - wap2.0		wap2.0页面		// wap 2.0 page
	 *  - js			js-sdk 专用 授权页面是弹窗，返回结果为js-sdk回掉函数		// only for js-sdk, popup authorization page, return the js-sdk callback function
	 *  - apponweibo	站内应用专用,站内应用不传display参数,并且response_type为token时,默认使用改display.授权后不会返回access_token，只是输出js刷新站内应用父框架 // only for application within the website, applications within the website don't send display parameter, and when response_type is token, by default, we use this display, it won't return access_token after authorization
	 * @return array
	 */
	function getAuthorizeURL( $url, $response_type = 'code', $state = NULL, $display = NULL ) {
		$params = array();
		$params['client_id'] = $this->client_id;
		$params['redirect_uri'] = $url;
		$params['response_type'] = $response_type;
		$params['state'] = $state;
		$params['display'] = $display;
		return $this->authorizeURL() . "?" . http_build_query($params);
	}

	/**
	 * access_token接口 //access_token interface
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/OAuth2/access_token OAuth2/access_token} //please see the API
	 *
	 * @param string $type 请求的类型,可以为:code, password, token // request type: code, password, token.
	 * @param array $keys 其他参数：// other parameters
	 *  - 当$type为code时： array('code'=>..., 'redirect_uri'=>...) //when it is code:...
	 *  - 当$type为password时： array('username'=>..., 'password'=>...) //when it is password:...
	 *  - 当$type为token时： array('refresh_token'=>...) //when it is token:...
	 * @return array
	 */
	function getAccessToken( $type = 'code', $keys ) {
		$params = array();
		$params['client_id'] = $this->client_id;
		$params['client_secret'] = $this->client_secret;
		if ( $type === 'token' ) {
			$params['grant_type'] = 'refresh_token';
			$params['refresh_token'] = $keys['refresh_token'];
		} elseif ( $type === 'code' ) {
			$params['grant_type'] = 'authorization_code';
			$params['code'] = $keys['code'];
			$params['redirect_uri'] = $keys['redirect_uri'];
		} elseif ( $type === 'password' ) {
			$params['grant_type'] = 'password';
			$params['username'] = $keys['username'];
			$params['password'] = $keys['password'];
		} else {
			throw new OAuthException("wrong auth type");
		}

		$response = $this->oAuthRequest($this->accessTokenURL(), 'POST', $params);
		var_dump($response);exit;
		$token = json_decode($response, true);
		if ( is_array($token) && !isset($token['error']) ) {
			$this->access_token = $token['access_token'];
			//$this->refresh_token = $token['refresh_token'];
		} else {
			throw new OAuthException("get access token failed." . $token['error']);
		}
		return $token;
	}

	/**
	 * 解析 signed_request //resolve signed_request
	 *
	 * @param string $signed_request 应用框架在加载iframe时会通过向Canvas URL post的参数signed_request // When it loads iframe, the parameter that Application framework will post through Canvas URL.
	 *
	 * @return array
	 */
	function parseSignedRequest($signed_request) {
		list($encoded_sig, $payload) = explode('.', $signed_request, 2);
		$sig = self::base64decode($encoded_sig) ;
		$data = json_decode(self::base64decode($payload), true);
		if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') return '-1';
		$expected_sig = hash_hmac('sha256', $payload, $this->client_secret, true);
		return ($sig !== $expected_sig)? '-2':$data;
	}

	/**
	 * @ignore
	 */
	function base64decode($str) {
		return base64_decode(strtr($str.str_repeat('=', (4 - strlen($str) % 4)), '-_', '+/'));
	}

	/**
	 * 读取jssdk授权信息，用于和jssdk的同步登录 //read jssdk authorization information, used for synchronous login
	 *
	 * @return array 成功返回array('access_token'=>'value', 'refresh_token'=>'value'); 失败返回false
	 */              // if succeed,return array.                                       //return false, if fail
	function getTokenFromJSSDK() {
		$key = "weibojs_" . $this->client_id;
		if ( isset($_COOKIE[$key]) && $cookie = $_COOKIE[$key] ) {
			parse_str($cookie, $token);
			if ( isset($token['access_token']) && isset($token['refresh_token']) ) {
				$this->access_token = $token['access_token'];
				$this->refresh_token = $token['refresh_token'];
				return $token;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * 从数组中读取access_token和refresh_token //read access_token and refresh_token from array
	 * 常用于从Session或Cookie中读取token，或通过Session/Cookie中是否存有token判断登录状态。// often used to read token from Session and Cookie, or to judge the login status by seeing whether Session/Cookie has token
	 *
	 * @param array $arr 存有access_token和secret_token的数组 //array that has access_token and secret_token
	 * @return array 成功返回array('access_token'=>'value', 'refresh_token'=>'value'); 失败返回false
	 */              //return array if succeed                                         return false if not
	function getTokenFromArray( $arr ) {
		if (isset($arr['access_token']) && $arr['access_token']) {
			$token = array();
			$this->access_token = $token['access_token'] = $arr['access_token'];
			if (isset($arr['refresh_token']) && $arr['refresh_token']) {
				$this->refresh_token = $token['refresh_token'] = $arr['refresh_token'];
			}

			return $token;
		} else {
			return false;
		}
	}

	/**
	 * GET wrappwer for oAuthRequest.
	 *
	 * @return mixed
	 */
	function get($url, $parameters = array()) {
		$response = $this->oAuthRequest($url, 'GET', $parameters);
		if ($this->format === 'json' && $this->decode_json) {
			return json_decode($response, true);
		}
		return $response;
	}

	/**
	 * POST wreapper for oAuthRequest.
	 *
	 * @return mixed
	 */
	function post($url, $parameters = array(), $multi = false) {
		$response = $this->oAuthRequest($url, 'POST', $parameters, $multi );
		if ($this->format === 'json' && $this->decode_json) {
			return json_decode($response, true);
		}
		return $response;
	}

	/**
	 * DELTE wrapper for oAuthReqeust.
	 *
	 * @return mixed
	 */
	function delete($url, $parameters = array()) {
		$response = $this->oAuthRequest($url, 'DELETE', $parameters);
		if ($this->format === 'json' && $this->decode_json) {
			return json_decode($response, true);
		}
		return $response;
	}

	/**
	 * Format and sign an OAuth / API request
	 *
	 * @return string
	 * @ignore
	 */
	function oAuthRequest($url, $method, $parameters, $multi = false) {

		if (strrpos($url, 'http://') !== 0 && strrpos($url, 'https://') !== 0) {
			$url = "{$this->host}{$url}.{$this->format}";
	}

	switch ($method) {
		case 'GET':
			$url = $url . '?' . http_build_query($parameters);
			return $this->http($url, 'GET');
		default:
			$headers = array();
			if (!$multi && (is_array($parameters) || is_object($parameters)) ) {
				$body = http_build_query($parameters);
			} else {
				$body = self::build_http_query_multi($parameters);
				$headers[] = "Content-Type: multipart/form-data; boundary=" . self::$boundary;
			}
			return $this->http($url, $method, $body, $headers);
	}
	}

	/**
	 * Make an HTTP request
	 *
	 * @return string API results
	 * @ignore
	 */
	function http($url, $method, $postfields = NULL, $headers = array()) {
		$this->http_info = array();
		$ci = curl_init();
		/* Curl settings */
		curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($ci, CURLOPT_USERAGENT, $this->useragent);
		curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
		curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ci, CURLOPT_ENCODING, "");
		curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
		curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
		curl_setopt($ci, CURLOPT_HEADER, FALSE);

		switch ($method) {
			case 'POST':
				curl_setopt($ci, CURLOPT_POST, TRUE);
				if (!empty($postfields)) {
					curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
					$this->postdata = $postfields;
				}
				break;
			case 'DELETE':
				curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
				if (!empty($postfields)) {
					$url = "{$url}?{$postfields}";
				}
		}

		if ( isset($this->access_token) && $this->access_token )
			$headers[] = "Authorization: OAuth2 ".$this->access_token;

		if ( !empty($this->remote_ip) ) {
			if ( defined('SAE_ACCESSKEY') ) {
				$headers[] = "SaeRemoteIP: " . $this->remote_ip;
			} else {
				$headers[] = "API-RemoteIP: " . $this->remote_ip;
			}
		} else {
			if ( !defined('SAE_ACCESSKEY') ) {
				$headers[] = "API-RemoteIP: " . $_SERVER['REMOTE_ADDR'];
			}
		}
		curl_setopt($ci, CURLOPT_URL, $url );
		curl_setopt($ci, CURLOPT_HTTPHEADER, $headers );
		curl_setopt($ci, CURLINFO_HEADER_OUT, TRUE );

		$response = curl_exec($ci);
		$this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
		$this->http_info = array_merge($this->http_info, curl_getinfo($ci));
		$this->url = $url;

		if ($this->debug) {
			echo "=====post data======\r\n";
			var_dump($postfields);

			echo "=====headers======\r\n";
			print_r($headers);

			echo '=====request info====='."\r\n";
			print_r( curl_getinfo($ci) );

			echo '=====response====='."\r\n";
			print_r( $response );
		}
		curl_close ($ci);
		return $response;
	}

	/**
	 * Get the header info to store.
	 *
	 * @return int
	 * @ignore
	 */
	function getHeader($ch, $header) {
		$i = strpos($header, ':');
		if (!empty($i)) {
			$key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
			$value = trim(substr($header, $i + 2));
			$this->http_header[$key] = $value;
		}
		return strlen($header);
	}

	/**
	 * @ignore
	 */
	public static function build_http_query_multi($params) {
		if (!$params) return '';

		uksort($params, 'strcmp');

		$pairs = array();

		self::$boundary = $boundary = uniqid('------------------');
		$MPboundary = '--'.$boundary;
		$endMPboundary = $MPboundary. '--';
		$multipartbody = '';

		foreach ($params as $parameter => $value) {

			if( in_array($parameter, array('pic', 'image')) && $value{0} == '@' ) {
				$url = ltrim( $value, '@' );
				$content = file_get_contents( $url );
				$array = explode( '?', basename( $url ) );
				$filename = $array[0];

				$multipartbody .= $MPboundary . "\r\n";
				$multipartbody .= 'Content-Disposition: form-data; name="' . $parameter . '"; filename="' . $filename . '"'. "\r\n";
				$multipartbody .= "Content-Type: image/unknown\r\n\r\n";
				$multipartbody .= $content. "\r\n";
			} else {
				$multipartbody .= $MPboundary . "\r\n";
				$multipartbody .= 'content-disposition: form-data; name="' . $parameter . "\"\r\n\r\n";
				$multipartbody .= $value."\r\n";
			}

		}

		$multipartbody .= $endMPboundary;
		return $multipartbody;
	}
}


/**
 * 新浪微博操作类V2       //Sina Weibo operating class
 *
 * 使用前需要先手工调用saetv2.ex.class.php <br /> // You need to manully call saetv2.ex.class.php before use.
 *
 * @package sae
 * @author Easy Chen, Elmer Zhang,Lazypeople
 * @version 1.0
 */
class SaeTClientV2
{
	/**
	 * 构造函数 //constructor function
	 *
	 * @access public
	 * @param mixed $akey 微博开放平台应用APP KEY //weibo open platform application APP KEY
	 * @param mixed $skey 微博开放平台应用APP SECRET // weibo open platform application APP SECRET
	 * @param mixed $access_token OAuth认证返回的token // token returned from the authorization
	 * @param mixed $refresh_token OAuth认证返回的token secret // token secret returned from authorization
	 * @return void
	 */
	function __construct( $akey, $skey, $access_token, $refresh_token = NULL)
	{
		$this->oauth = new SaeTOAuthV2( $akey, $skey, $access_token, $refresh_token );
	}

	/**
	 * 开启调试信息 // start to debug information
	 *
	 * 开启调试信息后，SDK会将每次请求微博API所发送的POST Data、Headers以及请求信息、返回内容输出出来。 // after starting to debug information, SDK will output the POST Data, Header and request information as well as return content generated by every request to weibo API
	 *
	 * @access public
	 * @param bool $enable 是否开启调试信息 //whether to enable the debug information or not
	 * @return void
	 */
	function set_debug( $enable )
	{
		$this->oauth->debug = $enable;
	}

	/**
	 * 设置用户IP // set user IP
	 *
	 * SDK默认将会通过$_SERVER['REMOTE_ADDR']获取用户IP，在请求微博API时将用户IP附加到Request Header中。但某些情况下$_SERVER['REMOTE_ADDR']取到的IP并非用户IP，而是一个固定的IP（例如使用SAE的Cron或TaskQueue服务时），此时就有可能会造成该固定IP达到微博API调用频率限额，导致API调用失败。此时可使用本方法设置用户IP，以避免此问题。 // By default, SDK will get user IP address through $_SERVER['REMOTE_ADDR'], it adds user IP to request header when it requests weibo API, but in some situations, the IP address the $_SERVER['REMOTE_ADDR' gets is not user IP but a fixed IP, now it is possible to make the fixed IP to peak at the weibo API call frequency, as a result, it leads to the failure of calling API. We can use this function to set user IP to avoid this problem.
	 *
	 * @access public
	 * @param string $ip 用户IP // user IP
	 * @return bool IP为非法IP字符串时，返回false，否则返回true // return false when it is illegal IP address
	 */
	function set_remote_ip( $ip )
	{
		if ( ip2long($ip) !== false ) {
			$this->oauth->remote_ip = $ip;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 获取最新的公共微博消息 //get the most recent public posts
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/public_timeline statuses/public_timeline} // please see the API
	 *
	 * @access public
	 * @param int $count 单页返回的记录条数，默认为50。// the number of records each page returns, default = 50
	 * @param int $page 返回结果的页码，默认为1。 // the page that returns the result, default = 1
	 * @param int $base_app 是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。// Whether to only get the information from current application. 0 is No(all data), 1 is Yes(only current application)
	 * @return array
	 */
	function public_timeline( $page = 1, $count = 50, $base_app = 0 )
	{
		$params = array();
		$params['count'] = intval($count);
		$params['page'] = intval($page);
		$params['base_app'] = intval($base_app);
		return $this->oauth->get('statuses/public_timeline', $params);//可能是接口的bug不能补全 //might be the bug of the interface cannot be fixed
	}

	/**
	 * 获取当前登录用户及其所关注用户的最新微博消息。// get the weibo posts of current user and the users current user is following
	 *
	 * 获取当前登录用户及其所关注用户的最新微博消息。和用户登录 http://weibo.com 后在“我的首页”中看到的内容相同。同friends_timeline() // This is the same as friends_timeline()
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/home_timeline statuses/home_timeline}
	 *
	 * @access public
	 * @param int $page 指定返回结果的页码。根据当前登录用户所关注的用户数及这些被关注用户发表的微博数，翻页功能最多能查看的总记录数会有所不同，通常最多能查看1000条左右。默认值1。可选。 // specify the page that returns the result. According to the number of users that current user is following and the number of posts these users post, the total records of the paging function will vary. Usually up to 1000 records. Default = 1. Optional.
	 * @param int $count 每次返回的记录数。缺省值50，最大值200。可选。//the number of records that is returned each time. Default = 50, MAX = 200
	 * @param int $since_id 若指定此参数，则只返回ID比since_id大的微博消息（即比since_id发表时间晚的微博消息）。可选。// If you specify this parameter, it will only return the posts that is posted later than since_id
	 * @param int $max_id 若指定此参数，则返回ID小于或等于max_id的微博消息。可选。 // If you specify this parameter, it will only return the posts that is posted earlier than max_id
	 * @param int $base_app 是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。// Whether to only get the information from current application. 0 is No(all data), 1 is Yes(only current application)
	 * @param int $feature 过滤类型ID，0：全部、1：原创、2：图片、3：视频、4：音乐，默认为0。 // filter type ID, 0:All, 1:Original, 2: Pictures, 3: Videos, 4: Music, default = 0
	 * @return array
	 */
	function home_timeline( $page = 1, $count = 50, $since_id = 0, $max_id = 0, $base_app = 0, $feature = 0 )
	{
		$params = array();
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['count'] = intval($count);
		$params['page'] = intval($page);
		$params['base_app'] = intval($base_app);
		$params['feature'] = intval($feature);

		return $this->oauth->get('statuses/home_timeline', $params);
	}

	/**
	 * 获取当前登录用户及其所关注用户的最新微博消息。 // This friends_timeline is the same as home_timeline()
	 *
	 * 获取当前登录用户及其所关注用户的最新微博消息。和用户登录 http://weibo.com 后在“我的首页”中看到的内容相同。同home_timeline()
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/friends_timeline statuses/friends_timeline}
	 *
	 * @access public
	 * @param int $page 指定返回结果的页码。根据当前登录用户所关注的用户数及这些被关注用户发表的微博数，翻页功能最多能查看的总记录数会有所不同，通常最多能查看1000条左右。默认值1。可选。
	 * @param int $count 每次返回的记录数。缺省值50，最大值200。可选。
	 * @param int $since_id 若指定此参数，则只返回ID比since_id大的微博消息（即比since_id发表时间晚的微博消息）。可选。
	 * @param int $max_id 若指定此参数，则返回ID小于或等于max_id的微博消息。可选。
	 * @param int $base_app 是否基于当前应用来获取数据。1为限制本应用微博，0为不做限制。默认为0。可选。
	 * @param int $feature 微博类型，0全部，1原创，2图片，3视频，4音乐. 返回指定类型的微博信息内容。转为为0。可选。
	 * @return array
	 */
	function friends_timeline( $page = 1, $count = 50, $since_id = 0, $max_id = 0, $base_app = 0, $feature = 0 )
	{
		return $this->home_timeline( $page, $count, $since_id, $max_id, $base_app, $feature);
	}

	/**
	 * 获取用户发布的微博信息列表 // get a list of weibo posts of the user.
	 *
	 * 返回用户的发布的最近n条信息，和用户微博页面返回内容是一致的。此接口也可以请求其他用户的最新发表微博. //get n posts the user postsrecently. This interface can only be used to request other users' latest weibo post.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/user_timeline statuses/user_timeline}
	 *
	 * @access public
	 * @param int $page 页码 //page number
	 * @param int $count 每次返回的最大记录数，最多返回200条，默认50。// max record number, MAX:200, default:50
	 * @param mixed $uid 指定用户UID或微博昵称 // specify user's UID or weibo nickname
	 * @param int $since_id 若指定此参数，则只返回ID比since_id大的微博消息（即比since_id发表时间晚的微博消息）。可选。// If you specify this parameter, it will only return the posts that is posted later than since_id
	 * @param int $max_id 若指定此参数，则返回ID小于或等于max_id的提到当前登录用户微博消息。可选。 // If you specify this parameter, it will only return the posts that is posted earlier than max_id
	 * @param int $base_app 是否基于当前应用来获取数据。1为限制本应用微博，0为不做限制。默认为0。// Whether to only get the information from current application. 0 is No(all data), 1 is Yes(only current application)
	 * @param int $feature 过滤类型ID，0：全部、1：原创、2：图片、3：视频、4：音乐，默认为0。 // filter type ID, 0:All, 1:Original, 2: Pictures, 3: Videos, 4: Music, default = 0
	 * @param int $trim_user 返回值中user信息开关，0：返回完整的user信息、1：user字段仅返回uid，默认为0。// Whether to return all information from user information. 0: return complete user information. 1: only return UID in user field
	 * @return array
	 */
	function user_timeline_by_id( $uid = NULL , $page = 1 , $count = 50 , $since_id = 0, $max_id = 0, $feature = 0, $trim_user = 0, $base_app = 0)
	{
		$params = array();
		$params['uid']=$uid;
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['base_app'] = intval($base_app);
		$params['feature'] = intval($feature);
		$params['count'] = intval($count);
		$params['page'] = intval($page);
		$params['trim_user'] = intval($trim_user);

		return $this->oauth->get( 'statuses/user_timeline', $params );
	}


	/**
	 * 获取用户发布的微博信息列表 // get a list of weibo posts of the user.
	 *
	 * 返回用户的发布的最近n条信息，和用户微博页面返回内容是一致的。此接口也可以请求其他用户的最新发表微博。//get n posts the user postsrecently. This interface can only be used to request other users' latest weibo post.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/user_timeline statuses/user_timeline}
	 *
	 * @access public
	 * @param string $screen_name 微博昵称，主要是用来区分用户UID跟微博昵称，当二者一样而产生歧义的时候，建议使用该参数 // weibo nickname, mainly used to distinguish user UID and user nickname. Suggest to use nickname when there is ambiguity.
	 * @param int $page 页码 //page number
	 * @param int $count 每次返回的最大记录数，最多返回200条，默认50。// max record number, MAX:200, default:50
	 * @param int $since_id 若指定此参数，则只返回ID比since_id大的微博消息（即比since_id发表时间晚的微博消息）。可选。// If you specify this parameter, it will only return the posts that is posted later than since_id
	 * @param int $max_id 若指定此参数，则返回ID小于或等于max_id的提到当前登录用户微博消息。可选。// If you specify this parameter, it will only return the posts that is posted earlier than max_id
	 * @param int $feature 过滤类型ID，0：全部、1：原创、2：图片、3：视频、4：音乐，默认为0。// filter type ID, 0:All, 1:Original, 2: Pictures, 3: Videos, 4: Music, default = 0
	 * @param int $trim_user 返回值中user信息开关，0：返回完整的user信息、1：user字段仅返回uid，默认为0。// Whether to return all information from user information. 0: return complete user information. 1: only return UID in user field
	 * @param int $base_app 是否基于当前应用来获取数据。1为限制本应用微博，0为不做限制。默认为0。// whether to fetch data based on current application. 1. limited to currrent application weibo, 0. no limit. Default = 0
	 * @return array
	 */
	function user_timeline_by_name( $screen_name = NULL , $page = 1 , $count = 50 , $since_id = 0, $max_id = 0, $feature = 0, $trim_user = 0, $base_app = 0 )
	{
		$params = array();
		$params['screen_name'] = $screen_name;
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['base_app'] = intval($base_app);
		$params['feature'] = intval($feature);
		$params['count'] = intval($count);
		$params['page'] = intval($page);
		$params['trim_user'] = intval($trim_user);

		return $this->oauth->get( 'statuses/user_timeline', $params );
	}



	/**
	 * 批量获取指定的一批用户的timeline // get the timelines from a batch of users
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/timeline_batch statuses/timeline_batch}
	 *
	 * @param string $screen_name  需要查询的用户昵称，用半角逗号分隔，一次最多20个 // user weibo nicknames that need to be searched, separated by commas, max number=20
	 * @param int    $count        单页返回的记录条数，默认为50。// the number of records that one page returns. Default = 50
	 * @param int    $page  返回结果的页码，默认为1。 // the page number that returns the result. Default = 1
	 * @param int    $base_app  是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。// Whether to only get the information from current application. 0 is No(all data), 1 is Yes(only current application)  Default = 0
	 * @param int    $feature   过滤类型ID，0：全部、1：原创、2：图片、3：视频、4：音乐，默认为0。 // filter type ID, 0:All, 1:Original, 2: Pictures, 3: Videos, 4: Music, default = 0
	 * @return array
	 */
	function timeline_batch_by_name( $screen_name, $page = 1, $count = 50, $feature = 0, $base_app = 0)
	{
		$params = array();
		if (is_array($screen_name) && !empty($screen_name)) {
			$params['screen_name'] = join(',', $screen_name);
		} else {
			$params['screen_name'] = $screen_name;
		}
		$params['count'] = intval($count);
		$params['page'] = intval($page);
		$params['base_app'] = intval($base_app);
		$params['feature'] = intval($feature);
		return $this->oauth->get('statuses/timeline_batch', $params);
	}

	/**
	 * 批量获取指定的一批用户的timeline  // get the timelines from a batch of users
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/timeline_batch statuses/timeline_batch}
	 *
	 * @param string $uids  需要查询的用户ID，用半角逗号分隔，一次最多20个。// user weibo IDs that need to be searched, separated by commas, max number=20
	 * @param int    $count        单页返回的记录条数，默认为50。 // the number of records that one page returns. Default = 50
	 * @param int    $page  返回结果的页码，默认为1。 // the page number that returns the result. Default = 1
	 * @param int    $base_app  是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。// Whether to only get the information from current application. 0 is No(all data), 1 is Yes(only current application)  Default = 0
	 * @param int    $feature   过滤类型ID，0：全部、1：原创、2：图片、3：视频、4：音乐，默认为0。// filter type ID, 0:All, 1:Original, 2: Pictures, 3: Videos, 4: Music, default = 0
	 * @return array
	 */
	function timeline_batch_by_id( $uids, $page = 1, $count = 50, $feature = 0, $base_app = 0)
	{
		$params = array();
		if (is_array($uids) && !empty($uids)) {
			foreach($uids as $k => $v) {
				$this->id_format($uids[$k]);
			}
			$params['uids'] = join(',', $uids);
		} else {
			$params['uids'] = $uids;
		}
		$params['count'] = intval($count);
		$params['page'] = intval($page);
		$params['base_app'] = intval($base_app);
		$params['feature'] = intval($feature);
		return $this->oauth->get('statuses/timeline_batch', $params);
	}


	/**
	 * 返回一条原创微博消息的最新n条转发微博消息。本接口无法对非原创微博进行查询。 // return the latest n forwarded weibo posts of one original weibo post. This interface cannot query non-original weibo post.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/repost_timeline statuses/repost_timeline}
	 *
	 * @access public
	 * @param int $sid 要获取转发微博列表的原创微博ID。//fetch the original weibo ID in the forwarded weibo list.
	 * @param int $page 返回结果的页码。 // return page number
	 * @param int $count 单页返回的最大记录数，最多返回200条，默认50。可选。// max record number, MAX:200, default:50 optional
	 * @param int $since_id 若指定此参数，则只返回ID比since_id大的记录（比since_id发表时间晚）。可选。// If you specify this parameter, it will only return the posts that is posted later than since_id. Optional
	 * @param int $max_id 若指定此参数，则返回ID小于或等于max_id的记录。可选。// If you specify this parameter, it will only return the posts that is posted earlier than max_id. Optional
	 * @param int $filter_by_author 作者筛选类型，0：全部、1：我关注的人、2：陌生人，默认为0。//The author type filter, 0:All, 1:People that I follow 2:strangers. default = 0
	 * @return array
	 */
	function repost_timeline( $sid, $page = 1, $count = 50, $since_id = 0, $max_id = 0, $filter_by_author = 0 )
	{
		$this->id_format($sid);

		$params = array();
		$params['id'] = $sid;
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['filter_by_author'] = intval($filter_by_author);

		return $this->request_with_pager( 'statuses/repost_timeline', $page, $count, $params );
	}

	/**
	 * 获取当前用户最新转发的n条微博消息 // return the latest n forwarded weibo posts by the user
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/repost_by_me statuses/repost_by_me}
	 *
	 * @access public
	 * @param int $page 返回结果的页码。 // return page number
	 * @param int $count  每次返回的最大记录数，最多返回200条，默认50。可选。 // max record number, MAX:200, default:50 optional
	 * @param int $since_id 若指定此参数，则只返回ID比since_id大的记录（比since_id发表时间晚）。可选。// If you specify this parameter, it will only return the posts that is posted later than since_id. Optional
	 * @param int $max_id  若指定此参数，则返回ID小于或等于max_id的记录。可选。// If you specify this parameter, it will only return the posts that is posted earlier than max_id. Optional
	 * @return array
	 */
	function repost_by_me( $page = 1, $count = 50, $since_id = 0, $max_id = 0 )
	{
		$params = array();
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}

		return $this->request_with_pager('statuses/repost_by_me', $page, $count, $params );
	}

	/**
	 * 获取@当前用户的微博列表 // fetch the weibo list that @ current user.
	 *
	 * 返回最新n条提到登录用户的微博消息（即包含@username的微博消息） // return these weibo posts that @ current user
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/mentions statuses/mentions}
	 *
	 * @access public
	 * @param int $page 返回结果的页序号。// return page number
	 * @param int $count 每次返回的最大记录数（即页面大小），不大于200，默认为50。// max record number, MAX:200, default:50
	 * @param int $since_id 若指定此参数，则只返回ID比since_id大的微博消息（即比since_id发表时间晚的微博消息）。可选。// If you specify this parameter, it will only return the posts that is posted later than since_id. Optional
	 * @param int $max_id 若指定此参数，则返回ID小于或等于max_id的提到当前登录用户微博消息。可选。// If you specify this parameter, it will only return the posts that is posted earlier than max_id. Optional
	 * @param int $filter_by_author 作者筛选类型，0：全部、1：我关注的人、2：陌生人，默认为0。//The author type filter, 0:All, 1:People that I follow 2:strangers. default = 0
	 * @param int $filter_by_source 来源筛选类型，0：全部、1：来自微博、2：来自微群，默认为0。 // The source filter, 0:All, 1:source is from weibo 2: source is from microgroup. default = 0
	 * @param int $filter_by_type 原创筛选类型，0：全部微博、1：原创的微博，默认为0。//weibo type: 0:all weibo, 1:original weibo default = 0
	 * @return array
	 */
	function mentions( $page = 1, $count = 50, $since_id = 0, $max_id = 0, $filter_by_author = 0, $filter_by_source = 0, $filter_by_type = 0 )
	{
		$params = array();
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['filter_by_author'] = $filter_by_author;
		$params['filter_by_source'] = $filter_by_source;
		$params['filter_by_type'] = $filter_by_type;

		return $this->request_with_pager( 'statuses/mentions', $page, $count, $params );
	}


	/**
	 * 根据ID获取单条微博信息内容 //get single post information by ID
	 *
	 * 获取单条ID的微博信息，作者信息将同时返回。// get single post information by ID as well as the author information
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/show statuses/show}
	 *
	 * @access public
	 * @param int $id 要获取已发表的微博ID, 如ID不存在返回空// fetch weibo posts ID. return null if ID does not exist
	 * @return array
	 */
	function show_status( $id )
	{
		$this->id_format($id);
		$params = array();
		$params['id'] = $id;
		return $this->oauth->get('statuses/show', $params);
	}

	/**
	 * 根据微博id号获取微博的信息 // fetch weibo information by weibo IDs
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/show_batch statuses/show_batch}
	 *
	 * @param string $ids 需要查询的微博ID，用半角逗号分隔，最多不超过50个。// the weibo IDs that need to be searched. Separated by commas, up to 50.
	 * @return array
	 */
    function show_batch( $ids )
	{
		$params=array();
		if (is_array($ids) && !empty($ids)) {
			foreach($ids as $k => $v) {
				$this->id_format($ids[$k]);
			}
			$params['ids'] = join(',', $ids);
		} else {
			$params['ids'] = $ids;
		}
		return $this->oauth->get('statuses/show_batch', $params);
	}

	/**
	 * 通过微博（评论、私信）ID获取其MID // fetch MID by weibo(comments,private messges) ID
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/querymid statuses/querymid}
	 *
	 * @param int|string $id  需要查询的微博（评论、私信）ID，批量模式下，用半角逗号分隔，最多不超过20个。//The IDs of the weibo(comments,private messages) that need to be searched, in a batch, separated by commas, up to 20
	 * @param int $type  获取类型，1：微博、2：评论、3：私信，默认为1。//fetch type: 1: weibo, 2: comment, 3: private message default = 1
	 * @param int $is_batch 是否使用批量模式，0：否、1：是，默认为0。//whether to enable batch. 0:No, 1:Yes default = 0
	 * @return array
	 */
	function querymid( $id, $type = 1, $is_batch = 0 )
	{
		$params = array();
		$params['id'] = $id;
		$params['type'] = intval($type);
		$params['is_batch'] = intval($is_batch);
		return $this->oauth->get( 'statuses/querymid',  $params);
	}

	/**
	 * 通过微博（评论、私信）MID获取其ID  //fetch ID by weibo(comments,private messges) MID
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/queryid statuses/queryid}
	 *
	 * @param int|string $mid  需要查询的微博（评论、私信）MID，批量模式下，用半角逗号分隔，最多不超过20个。//The MIDs of the weibo(comments,private messages) that need to be searched, in a batch, separated by commas, up to 20
	 * @param int $type  获取类型，1：微博、2：评论、3：私信，默认为1。//fetch type: 1: weibo, 2: comment, 3: private message default = 1
	 * @param int $is_batch 是否使用批量模式，0：否、1：是，默认为0。//whether to enable batch. 0:No, 1:Yes default = 0
	 * @param int $inbox  仅对私信有效，当MID类型为私信时用此参数，0：发件箱、1：收件箱，默认为0 。// only available for private messages, use this parameter when MID type is private message. 0: send box 1:inbox. default = 0
	 * @param int $isBase62 MID是否是base62编码，0：否、1：是，默认为0。//whether MID is base62 encoded. 0:No, 1:Yes. Default = 0
	 * @return array
	 */
	function queryid( $mid, $type = 1, $is_batch = 0, $inbox = 0, $isBase62 = 0)
	{
		$params = array();
		$params['mid'] = $mid;
		$params['type'] = intval($type);
		$params['is_batch'] = intval($is_batch);
		$params['inbox'] = intval($inbox);
		$params['isBase62'] = intval($isBase62);
		return $this->oauth->get('statuses/queryid', $params);
	}

	/**
	 * 按天返回热门微博转发榜的微博列表 //return the weibo post list according to hot forwarded weibo posts listings by a day.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/hot/repost_daily statuses/hot/repost_daily}
	 *
	 * @param int $count 返回的记录条数，最大不超过50，默认为20。//the number of records. Max=50. default = 20
	 * @param int $base_app 是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。//whether to get the data from current application. 0: No(all the data), 1:yes(current application.) default = 0
	 * @return array
	 */
	function repost_daily( $count = 20, $base_app = 0)
	{
		$params = array();
		$params['count'] = intval($count);
		$params['base_app'] = intval($base_app);
		return $this->oauth->get('statuses/hot/repost_daily',  $params);
	}

	/**
	 * 按周返回热门微博转发榜的微博列表  //return the weibo post list according to hot forwarded weibo posts listings by a week
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/hot/repost_weekly statuses/hot/repost_weekly}
	 *
	 * @param int $count 返回的记录条数，最大不超过50，默认为20。//the number of records. Max=50. default = 20
	 * @param int $base_app 是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。//whether to get the data from current application. 0: No(all the data), 1:yes(current application.) default = 0
	 * @return array
	 */
	function repost_weekly( $count = 20,  $base_app = 0)
	{
		$params = array();
		$params['count'] = intval($count);
		$params['base_app'] = intval($base_app);
		return $this->oauth->get( 'statuses/hot/repost_weekly',  $params);
	}

	/**
	 * 按天返回热门微博评论榜的微博列表 //return the weibo post list according to hot commented weibo posts listings by a day
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/hot/comments_daily statuses/hot/comments_daily}
	 *
	 * @param int $count 返回的记录条数，最大不超过50，默认为20。//the number of records. Max=50. default = 20
	 * @param int $base_app 是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。//whether to get the data from current application. 0: No(all the data), 1:yes(current application.) default = 0
	 * @return array
	 */
	function comments_daily( $count = 20,  $base_app = 0)
	{
		$params =  array();
		$params['count'] = intval($count);
		$params['base_app'] = intval($base_app);
		return $this->oauth->get( 'statuses/hot/comments_daily',  $params);
	}

	/**
	 * 按周返回热门微博评论榜的微博列表 //return the weibo post list according to hot commented weibo posts listings by a week
	 *
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/hot/comments_weekly statuses/hot/comments_weekly}
	 *
	 * @param int $count 返回的记录条数，最大不超过50，默认为20。//the number of records. Max=50. default = 20
	 * @param int $base_app 是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。//whether to get the data from current application. 0: No(all the data), 1:yes(current application.) default = 0
	 * @return array
	 */
	function comments_weekly( $count = 20, $base_app = 0)
	{
		$params =  array();
		$params['count'] = intval($count);
		$params['base_app'] = intval($base_app);
		return $this->oauth->get( 'statuses/hot/comments_weekly', $params);
	}


	/**
	 * 转发一条微博信息。//forward a post
	 *
	 * 可加评论。为防止重复，发布的信息与最新信息一样话，将会被忽略。// could add a comment. To avoid repeating, it will be ignored if the added information is the same as original information.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/repost statuses/repost}
	 *
	 * @access public
	 * @param int $sid 转发的微博ID // The forwarded weibo ID
	 * @param string $text 添加的评论信息。可选。//added comment information
	 * @param int $is_comment 是否在转发的同时发表评论，0：否、1：评论给当前微博、2：评论给原微博、3：都评论，默认为0。// whether to comment while forwarding. 0:No, 1:comment on current weibo post, 2: comment on original weibo post 3:both. Default = 0
	 * @return array
	 */
	function repost( $sid, $text = NULL, $is_comment = 0 )
	{
		$this->id_format($sid);

		$params = array();
		$params['id'] = $sid;
		$params['is_comment'] = $is_comment;
		if( $text ) $params['status'] = $text;

		return $this->oauth->post( 'statuses/repost', $params  );
	}

	/**
	 * 删除一条微博 //delete a weibo post
	 *
	 * 根据ID删除微博消息。注意：只能删除自己发布的信息。// delete weibo post according to ID. Warning: can only delete the information posted by the user itself.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/destroy statuses/destroy}
	 *
	 * @access public
	 * @param int $id 要删除的微博ID //the ID of the weibo post that is to be deleted.
	 * @return array
	 */
	function delete( $id )
	{
		return $this->destroy( $id );
	}

	/**
	 * 删除一条微博  //delete a weibo post
	 *
	 * 删除微博。注意：只能删除自己发布的信息。// delete weibo post. Warning: can only delete the information posted by the user itself.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/destroy statuses/destroy}
	 *
	 * @access public
	 * @param int $id 要删除的微博ID //the ID of the weibo post that is to be deleted.
	 * @return array
	 */
	function destroy( $id )
	{
		$this->id_format($id);
		$params = array();
		$params['id'] = $id;
		return $this->oauth->post( 'statuses/destroy',  $params );
	}


	/**
	 * 发表微博 // post weibo.
	 *
	 * 发布一条微博信息。// post one weibo text.
	 * <br />注意：lat和long参数需配合使用，用于标记发表微博消息时所在的地理位置，只有用户设置中geo_enabled=true时候地理位置信息才有效。
	 * <br />注意：为防止重复提交，当用户发布的微博消息与上次成功发布的微博消息内容一样时，将返回400错误，给出错误提示：“40025:Error: repeated weibo text!“。
	 //warning: parameter lat and long need to be used together, which is used for the geographical location, only work when user set the geo_enabled=true in the user settings.
	 //warning: to avoid repeating posting, if the weibo content is the same as last time, return 400 error. give a error notification:"40025:Error:repeated weibo text!"
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/update statuses/update}
	 *
	 * @access public
	 * @param string $status 要更新的微博信息。信息内容不超过140个汉字, 为空返回400错误。//the weibo that needs to be updated. less than 140 Chinese characters. Retuen 400 error if null.
	 * @param float $lat 纬度，发表当前微博所在的地理位置，有效范围 -90.0到+90.0, +表示北纬。可选。// latitude of the current location. range -90.0 to +90.0, + means North latitude. Optional.
	 * @param float $long 经度。有效范围-180.0到+180.0, +表示东经。可选。//longtitude of current location. range from -180.0 to +180.0. + means east longtitude. Optional
	 * @param mixed $annotations 可选参数。元数据，主要是为了方便第三方应用记录一些适合于自己使用的信息。每条微博可以包含一个或者多个元数据。请以json字串的形式提交，字串长度不超过512个字符，或者数组方式，要求json_encode后字串长度不超过512个字符。具体内容可以自定。例如：'[{"type2":123}, {"a":"b", "c":"d"}]'或array(array("type2"=>123), array("a"=>"b", "c"=>"d"))。//optional parameter, Metadata, it is useful for third-party application record some information. Each weibo contains one or more metadata. Please submitted in json format, less than 512 characters. or in the format of array, the string after json_encode is less than 512 characters, you can decide by yourself on the content.
	 * @return array
	 */
	function update( $status, $lat = NULL, $long = NULL, $annotations = NULL )
	{
		$params = array();
		$params['status'] = $status;
		if ($lat) {
			$params['lat'] = floatval($lat);
		}
		if ($long) {
			$params['long'] = floatval($long);
		}
		if (is_string($annotations)) {
			$params['annotations'] = $annotations;
		} elseif (is_array($annotations)) {
			$params['annotations'] = json_encode($annotations);
		}

		return $this->oauth->post( 'statuses/update', $params );
	}

	/**
	 * 发表图片微博 // post a weibo that has picture.
	 *
	 * 发表图片微博消息。目前上传图片大小限制为<5M。 // post a weibo that has picture, currently, the size limitation is 5M.
	 * <br />注意：lat和long参数需配合使用，用于标记发表微博消息时所在的地理位置，只有用户设置中geo_enabled=true时候地理位置信息才有效。//warning: parameter lat and long need to be used together, which is used for the geographical location, only work when user set the geo_enabled=true in the user settings.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/upload statuses/upload}
	 *
	 * @access public
	 * @param string $status 要更新的微博信息。信息内容不超过140个汉字, 为空返回400错误。//the weibo that needs to be updated. less than 140 Chinese characters. Retuen 400 error if null.
	 * @param string $pic_path 要发布的图片路径, 支持url。[只支持png/jpg/gif三种格式, 增加格式请修改get_image_mime方法] // the path of the picture that is to be uploaded. supporting url. (only support the format of png/jpg/gif. You need to change the get_image_mime function if you want to add a format. )
	 * @param float $lat 纬度，发表当前微博所在的地理位置，有效范围 -90.0到+90.0, +表示北纬。可选。 // latitude of the current location. range -90.0 to +90.0, + means North latitude. Optional.
	 * @param float $long 可选参数，经度。有效范围-180.0到+180.0, +表示东经。可选。//longtitude of current location. range from -180.0 to +180.0. + means east longtitude. Optional
	 * @return array
	 */
	function upload( $status, $pic_path, $lat = NULL, $long = NULL )
	{
		$params = array();
		$params['status'] = $status;
		$params['pic'] = '@'.$pic_path;
		if ($lat) {
			$params['lat'] = floatval($lat);
		}
		if ($long) {
			$params['long'] = floatval($long);
		}

		return $this->oauth->post( 'statuses/upload', $params, true );
	}


	/**
	 * 指定一个图片URL地址抓取后上传并同时发布一条新微博 // post a weibo as well as upload a picture from URL.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/statuses/upload_url_text statuses/upload_url_text}
	 *
	 * @param string $status  要发布的微博文本内容，内容不超过140个汉字。//The weibo text content. less than 140 Chinese characters.
	 * @param string $url    图片的URL地址，必须以http开头。// The URL address of the picture, must start with http.
	 * @return array
	 */
	function upload_url_text( $status,  $url )
	{
		$params = array();
		$params['status'] = $status;
		$params['url'] = $url;
		return $this->oauth->post( 'statuses/upload', $params, true );
	}


	/**
	 * 获取表情列表 // get a list of emotions.
	 *
	 * 返回新浪微博官方所有表情、魔法表情的相关信息。包括短语、表情类型、表情分类，是否热门等。// return all related information of all official emotions by sina weibo, including description of emotions, types, categories, popularity etc.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/emotions emotions}
	 *
	 * @access public
	 * @param string $type 表情类别。"face":普通表情，"ani"：魔法表情，"cartoon"：动漫表情。默认为"face"。可选。 optional.
	 * @param string $language 语言类别，"cnname"简体，"twname"繁体。默认为"cnname"。可选 //language type: cnname: simplified, twname:traditional. default = cnname. optional.
	 * @return array
	 */
	function emotions( $type = "face", $language = "cnname" )
	{
		$params = array();
		$params['type'] = $type;
		$params['language'] = $language;
		return $this->oauth->get( 'emotions', $params );
	}


	/**
	 * 根据微博ID返回某条微博的评论列表 // get the list of comments by certain weibo ID.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/comments/show comments/show}
	 *
	 * @param int $sid 需要查询的微博ID。// the weibo ID that is to be searched.
	 * @param int $page 返回结果的页码，默认为1。// return page number that has the result, default = 1
	 * @param int $count 单页返回的记录条数，默认为50。// the number of records each page returns, default = 50
	 * @param int $since_id 若指定此参数，则返回ID比since_id大的评论（即比since_id时间晚的评论），默认为0。// if you specify this parameter, return the private messages that are sent later than since_id. default = 0
	 * @param int $max_id  若指定此参数，则返回ID小于或等于max_id的评论，默认为0。// if you specify this parameter, return the private messages that are sent earlier than or equal to max_id. default = 0
	 * @param int $filter_by_author 作者筛选类型，0：全部、1：我关注的人、2：陌生人，默认为0。 // author filter type, 0: All, 1: the people I am following, 2:strangers. default = 0
	 * @return array
	 */
	function get_comments_by_sid( $sid, $page = 1, $count = 50, $since_id = 0, $max_id = 0, $filter_by_author = 0 )
	{
		$params = array();
		$this->id_format($sid);
		$params['id'] = $sid;
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['count'] = $count;
		$params['page'] = $page;
		$params['filter_by_author'] = $filter_by_author;
		return $this->oauth->get( 'comments/show',  $params );
	}


	/**
	 * 获取当前登录用户所发出的评论列表 // get the list of comments made by current users.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/comments/by_me comments/by_me}
	 *
	 * @param int $since_id 若指定此参数，则返回ID比since_id大的评论（即比since_id时间晚的评论），默认为0。// if you specify this parameter, return the private messages that are sent later than since_id. default = 0
	 * @param int $max_id 若指定此参数，则返回ID小于或等于max_id的评论，默认为0。// if you specify this parameter, return the private messages that are sent earlier than or equal to max_id. default = 0
	 * @param int $count  单页返回的记录条数，默认为50。// the number of records each page returns, default = 50
	 * @param int $page 返回结果的页码，默认为1。// return page number that has the result, default = 1
	 * @param int $filter_by_source 来源筛选类型，0：全部、1：来自微博的评论、2：来自微群的评论，默认为0。// source filter type, 0: All, 1: the comments from weibo posts, 2: the comments from weibo groups, default = 0
	 * @return array
	 */
	function comments_by_me( $page = 1 , $count = 50, $since_id = 0, $max_id = 0,  $filter_by_source = 0 )
	{
		$params = array();
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['count'] = $count;
		$params['page'] = $page;
		$params['filter_by_source'] = $filter_by_source;
		return $this->oauth->get( 'comments/by_me', $params );
	}

	/**
	 * 获取当前登录用户所接收到的评论列表 // the list of comments received by current user.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/comments/to_me comments/to_me}
	 *
	 * @param int $since_id 若指定此参数，则返回ID比since_id大的评论（即比since_id时间晚的评论），默认为0。// if you specify this parameter, return the private messages that are sent later than since_id. default = 0
	 * @param int $max_id  若指定此参数，则返回ID小于或等于max_id的评论，默认为0。// if you specify this parameter, return the private messages that are sent earlier than or equal to max_id. default = 0
	 * @param int $count 单页返回的记录条数，默认为50。// the number of records each page returns, default = 50
	 * @param int $page 返回结果的页码，默认为1。// return page number that has the result, default = 1
	 * @param int $filter_by_author 作者筛选类型，0：全部、1：我关注的人、2：陌生人，默认为0。// author filter type, 0: All, 1: the people I am following, 2:strangers. default = 0
	 * @param int $filter_by_source 来源筛选类型，0：全部、1：来自微博的评论、2：来自微群的评论，默认为0。// source filter type, 0: All, 1: the comments from weibo posts, 2: the comments from weibo groups, default = 0
	 * @return array
	 */
	function comments_to_me( $page = 1 , $count = 50, $since_id = 0, $max_id = 0, $filter_by_author = 0, $filter_by_source = 0)
	{
		$params = array();
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['count'] = $count;
		$params['page'] = $page;
		$params['filter_by_author'] = $filter_by_author;
		$params['filter_by_source'] = $filter_by_source;
		return $this->oauth->get( 'comments/to_me', $params );
	}

	/**
	 * 最新评论(按时间) // the latest comments(by time)
	 *
	 * 返回最新n条发送及收到的评论。//return the n latest comments that are both sent or received.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/comments/timeline comments/timeline}
	 *
	 * @access public
	 * @param int $page 页码 // page number.
	 * @param int $count 每次返回的最大记录数，最多返回200条，默认50。// the maximum number of records, max= 200, default = 50.
	 * @param int $since_id 若指定此参数，则只返回ID比since_id大的评论（比since_id发表时间晚）。可选。// if you specify this parameter, return the private messages that are sent later than since_id. Optional.
	 * @param int $max_id 若指定此参数，则返回ID小于或等于max_id的评论。可选。// if you specify this parameter, return the private messages that are sent earlier than or equal to max_id. Optional.
	 * @return array
	 */
	function comments_timeline( $page = 1, $count = 50, $since_id = 0, $max_id = 0 )
	{
		$params = array();
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}

		return $this->request_with_pager( 'comments/timeline', $page, $count, $params );
	}


	/**
	 * 获取最新的提到当前登录用户的评论，即@我的评论 //get the comments that mention current users.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/comments/mentions comments/mentions}
	 *
	 * @param int $since_id 若指定此参数，则返回ID比since_id大的评论（即比since_id时间晚的评论），默认为0。// if you specify this parameter, return the private messages that are sent later than since_id. default = 0
	 * @param int $max_id  若指定此参数，则返回ID小于或等于max_id的评论，默认为0。// if you specify this parameter, return the private messages that are sent earlier than or equal to max_id. default = 0
	 * @param int $count 单页返回的记录条数，默认为50。// the number of records each page returns, default = 50
	 * @param int $page 返回结果的页码，默认为1。// return page number that has the result, default = 1
	 * @param int $filter_by_author  作者筛选类型，0：全部、1：我关注的人、2：陌生人，默认为0。// author filter type, 0: All, 1: the people I am following, 2:strangers. default = 0
	 * @param int $filter_by_source 来源筛选类型，0：全部、1：来自微博的评论、2：来自微群的评论，默认为0。// source filter type, 0: All, 1: the comments from weibo posts, 2: the comments from weibo groups, default = 0
	 * @return array
	 */
	function comments_mentions( $page = 1, $count = 50, $since_id = 0, $max_id = 0, $filter_by_author = 0, $filter_by_source = 0)
	{
		$params = array();
		$params['since_id'] = $since_id;
		$params['max_id'] = $max_id;
		$params['count'] = $count;
		$params['page'] = $page;
		$params['filter_by_author'] = $filter_by_author;
		$params['filter_by_source'] = $filter_by_source;
		return $this->oauth->get( 'comments/mentions', $params );
	}


	/**
	 * 根据评论ID批量返回评论信息 // return comment information in a batch by comment IDs.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/comments/show_batch comments/show_batch}
	 *
	 * @param string $cids 需要查询的批量评论ID，用半角逗号分隔，最大50 // the comment IDs, separated by half-width commas, up to 50 IDs.
	 * @return array
	 */
	function comments_show_batch( $cids )
	{
		$params = array();
		if (is_array( $cids) && !empty( $cids)) {
			foreach($cids as $k => $v) {
				$this->id_format($cids[$k]);
			}
			$params['cids'] = join(',', $cids);
		} else {
			$params['cids'] = $cids;
		}
		return $this->oauth->get( 'comments/show_batch', $params );
	}


	/**
	 * 对一条微博进行评论 // To make a comment on a weibo post.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/comments/create comments/create}
	 *
	 * @param string $comment 评论内容，内容不超过140个汉字。// comment content. less than 140 characters.
	 * @param int $id 需要评论的微博ID。// the weibo ID that will be commented.
	 * @param int $comment_ori 当评论转发微博时，是否评论给原微博，0：否、1：是，默认为0。//whether to comment on the original post if it is a forwarded weibo post. 0:No, 1:Yes. Default = 0.
	 * @return array
	 */
	function send_comment( $id , $comment , $comment_ori = 0)
	{
		$params = array();
		$params['comment'] = $comment;
		$this->id_format($id);
		$params['id'] = $id;
		$params['comment_ori'] = $comment_ori;
		return $this->oauth->post( 'comments/create', $params );
	}

	/**
	 * 删除当前用户的微博评论信息。 // delete the comment information of current user.
	 *
	 * 注意：只能删除自己发布的评论，发部微博的用户不可以删除其他人的评论。//warning: can only delete the comments posted by myself, the user who posts the weibo cannot delete other people's comments.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/statuses/comment_destroy statuses/comment_destroy}
	 *
	 * @access public
	 * @param int $cid 要删除的评论id // the comment ID that is to be deleted.
	 * @return array
	 */
	function comment_destroy( $cid )
	{
		$params = array();
		$params['cid'] = $cid;
		return $this->oauth->post( 'comments/destroy', $params);
	}


	/**
	 * 根据评论ID批量删除评论 // delete comments by comment IDs in a batch.
	 *
	 * 注意：只能删除自己发布的评论，发部微博的用户不可以删除其他人的评论。//warning: can only delete the comments posted by myself, the user who posts the weibo cannot delete other people's comments.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/comments/destroy_batch comments/destroy_batch}
	 *
	 * @access public
	 * @param string $ids 需要删除的评论ID，用半角逗号隔开，最多20个。// the comment IDs that needs to be deleted. separated by commas, up to 20 IDs.
	 * @return array
	 */
	function comment_destroy_batch( $ids )
	{
		$params = array();
		if (is_array($ids) && !empty($ids)) {
			foreach($ids as $k => $v) {
				$this->id_format($ids[$k]);
			}
			$params['cids'] = join(',', $ids);
		} else {
			$params['cids'] = $ids;
		}
		return $this->oauth->post( 'comments/destroy_batch', $params);
	}


	/**
	 * 回复一条评论 // reply a comment.
	 *
	 * 为防止重复，发布的信息与最后一条评论/回复信息一样话，将会被忽略。//in order to avoid repeating, the reply will be ignored if the reply is same as the last comment.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/comments/reply comments/reply}
	 *
	 * @access public
	 * @param int $sid 微博id // weibo post ID
	 * @param string $text 评论内容。//comment content
	 * @param int $cid 评论id // comment ID
	 * @param int $without_mention 1：回复中不自动加入“回复@用户名”，0：回复中自动加入“回复@用户名”.默认为0. // 1. in the reply, not automatically add @the user that current user is replying. 0: in the reply, automatically add @ the user that current user is replying. default = 0
     * @param int $comment_ori	  当评论转发微博时，是否评论给原微博，0：否、1：是，默认为0。 //whether to comment on the original post if it is a forwarded weibo post. 0:No, 1:Yes. Default = 0.
	 * @return array
	 */
	function reply( $sid, $text, $cid, $without_mention = 0, $comment_ori = 0 )
	{
		$this->id_format( $sid );
		$this->id_format( $cid );
		$params = array();
		$params['id'] = $sid;
		$params['comment'] = $text;
		$params['cid'] = $cid;
		$params['without_mention'] = $without_mention;
		$params['comment_ori'] = $comment_ori;

		return $this->oauth->post( 'comments/reply', $params );

	}

	/**
	 * 根据用户UID或昵称获取用户资料  // get user information by user UID or user nickname.
	 *
	 * 按用户UID或昵称返回用户资料，同时也将返回用户的最新发布的微博。 // get user information by user UID or user nickname.  as well as get the latest weibo post by the user.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/users/show users/show}
	 *
	 * @access public
	 * @param int  $uid 用户UID。 // user UID
	 * @return array
	 */
	function show_user_by_id( $uid )
	{
		$params=array();
		if ( $uid !== NULL ) {
			$this->id_format($uid);
			$params['uid'] = $uid;
		}

		return $this->oauth->get('users/show', $params );
	}

	/**
	 * 根据用户UID或昵称获取用户资料 // get user information by user UID or user nickname.
	 *
	 * 按用户UID或昵称返回用户资料，同时也将返回用户的最新发布的微博。// get user information by user UID or user nickname.  as well as get the latest weibo post by the user.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/users/show users/show}
	 *
	 * @access public
	 * @param string  $screen_name 用户UID。//user UID
	 * @return array
	 */
	function show_user_by_name( $screen_name )
	{
		$params = array();
		$params['screen_name'] = $screen_name;

		return $this->oauth->get( 'users/show', $params );
	}

	/**
	 * 通过个性化域名获取用户资料以及用户最新的一条微博 // get the latest weibo post and user information by personal domain.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/users/domain_show users/domain_show}
	 *
	 * @access public
	 * @param mixed $domain 用户个性域名。例如：lazypeople，而不是http://weibo.com/lazypeople // user personal domain. for example: lazypeople, not http://...
	 * @return array
	 */
	function domain_show( $domain )
	{
		$params = array();
		$params['domain'] = $domain;
		return $this->oauth->get( 'users/domain_show', $params );
	}

	 /**
	 * 批量获取用户信息按uids  // get a batch of user information by UIDs
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/users/show_batch users/show_batch}
	 *
	 * @param string $uids 需要查询的用户ID，用半角逗号分隔，一次最多20个。 // the user ID that is to be searched. Separated by half-width commas, up to 20 nicknames once.
	 * @return array
	 */
	function users_show_batch_by_id( $uids )
	{
		$params = array();
		if (is_array( $uids ) && !empty( $uids )) {
			foreach( $uids as $k => $v ) {
				$this->id_format( $uids[$k] );
			}
			$params['uids'] = join(',', $uids);
		} else {
			$params['uids'] = $uids;
		}
		return $this->oauth->get( 'users/show_batch', $params );
	}

	/**
	 * 批量获取用户信息按screen_name // get a batch of user information by screen_name
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/users/show_batch users/show_batch}
	 *
	 * @param string  $screen_name 需要查询的用户昵称，用半角逗号分隔，一次最多20个。 // the nickname that is to be searched. Separated by half-width commas, up to 20 nicknames once.
	 * @return array
	 */
	function users_show_batch_by_name( $screen_name )
	{
		$params = array();
		if (is_array( $screen_name ) && !empty( $screen_name )) {
			$params['screen_name'] = join(',', $screen_name);
		} else {
			$params['screen_name'] = $screen_name;
		}
		return $this->oauth->get( 'users/show_batch', $params );
	}


	/**
	 * 获取用户的关注列表 // get the following list of current user.
	 *
	 * 如果没有提供cursor参数，将只返回最前面的5000个关注id // if cursor parameter is not provided, only return the first 5000 followers id.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/friends friendships/friends}
	 *
	 * @access public
	 * @param int $cursor 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。//return the cursor of the result, next page uses the next_cursor in the return value, previous page uses previous_cursor. default = 0
	 * @param int $count 单页返回的记录条数，默认为50，最大不超过200。 // the number of records each page returns, less than 200, default = 50.
	 * @param int $uid  要获取的用户的ID。 // the user ID that is to be searched.
	 * @return array
	 */
	function friends_by_id( $uid, $cursor = 0, $count = 50 )
	{
		$params = array();
		$params['cursor'] = $cursor;
		$params['count'] = $count;
		$params['uid'] = $uid;

		return $this->oauth->get( 'friendships/friends', $params );
	}


	/**
	 * 获取用户的关注列表 // get the following list of current user.
	 *
	 * 如果没有提供cursor参数，将只返回最前面的5000个关注id // if cursor parameter is not provided, only return the first 5000 followers id.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/friends friendships/friends}
	 *
	 * @access public
	 * @param int $cursor 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。//return the cursor of the result, next page uses the next_cursor in the return value, previous page uses previous_cursor. default = 0
	 * @param int $count 单页返回的记录条数，默认为50，最大不超过200。 // the number of records each page returns, less than 200, default = 50.
	 * @param string $screen_name  要获取的用户的 screen_name // the screen_name that is to be searched.
	 * @return array
	 */
	function friends_by_name( $screen_name, $cursor = 0, $count = 50 )
	{
		$params = array();
		$params['cursor'] = $cursor;
		$params['count'] = $count;
		$params['screen_name'] = $screen_name;
		return $this->oauth->get( 'friendships/friends', $params );
	}


	/**
	 * 获取两个用户之间的共同关注人列表 //get the list of friends that two users have in common.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/friends/in_common friendships/friends/in_common}
	 *
	 * @param int $uid  需要获取共同关注关系的用户UID // get the UIDs that needs to get the common friends
	 * @param int $suid  需要获取共同关注关系的用户UID，默认为当前登录用户。// get the UIDs that needs to get the common friends, default = current user.
	 * @param int $count  单页返回的记录条数，默认为50。// page number that returns the result, default = 50
	 * @param int $page  返回结果的页码，默认为1。// page number that returns the result, default = 1
	 * @return array
	 */
	function friends_in_common( $uid, $suid = NULL, $page = 1, $count = 50 )
	{
		$params = array();
		$params['uid'] = $uid;
		$params['suid'] = $suid;
		$params['count'] = $count;
		$params['page'] = $page;
		return $this->oauth->get( 'friendships/friends/in_common', $params  );
	}

	/**
	 * 获取用户的双向关注列表，即互粉列表 // get the list of current user's bilateral followers. (means I follow you and you follow me back.)
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/friends/bilateral friendships/friends/bilateral}
	 *
	 * @param int $uid  需要获取双向关注列表的用户UID。// get the UIDs that needs to get the bilateral followers.
	 * @param int $count  单页返回的记录条数，默认为50。// the number of records each page returns, default = 50
	 * @param int $page  返回结果的页码，默认为1。// page number that returns the result, default = 1
	 * @param int $sort  排序类型，0：按关注时间最近排序，默认为0。// sort type, 0: sort by the time you follow the user. default = 0
	 * @return array
	 **/
	function bilateral( $uid, $page = 1, $count = 50, $sort = 0 )
	{
		$params = array();
		$params['uid'] = $uid;
		$params['count'] = $count;
		$params['page'] = $page;
		$params['sort'] = $sort;
		return $this->oauth->get( 'friendships/friends/bilateral', $params  );
	}

	/**
	 * 获取用户的双向关注uid列表 // get the uid list of current user's bilateral followers. (means I follow you and you follow me back.)
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/friends/bilateral/ids friendships/friends/bilateral/ids}
	 *
	 * @param int $uid  需要获取双向关注列表的用户UID。// get the UIDs that needs to get the bilateral followers.
	 * @param int $count 单页返回的记录条数，默认为50。// the number of records each page returns, default = 50
	 * @param int $page  返回结果的页码，默认为1。// page number that returns the result, default = 1
	 * @param int $sort  排序类型，0：按关注时间最近排序，默认为0。// sort type, 0: sort by the time you follow the user. default = 0
	 * @return array
	 **/
	function bilateral_ids( $uid, $page = 1, $count = 50, $sort = 0)
	{
		$params = array();
		$params['uid'] = $uid;
		$params['count'] = $count;
		$params['page'] = $page;
		$params['sort'] = $sort;
		return $this->oauth->get( 'friendships/friends/bilateral/ids',  $params  );
	}

	/**
	 * 获取用户的关注列表uid  // get the uid list of the users that current user is following.
	 *
	 * 如果没有提供cursor参数，将只返回最前面的5000个关注id  // if cursor parameter is not provided, only return the first 5000 followers id.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/friends/ids friendships/friends/ids}
	 *
	 * @access public
	 * @param int $cursor 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。//return the cursor of the result, next page uses the next_cursor in the return value, previous page uses previous_cursor. default = 0
	 * @param int $count 每次返回的最大记录数（即页面大小），不大于5000, 默认返回500。// maximum number of records each return (page size), less than 5000, default = 500.
	 * @param int $uid 要获取的用户 UID，默认为当前用户  // the UID of the user that is to be searched, default = current user.
	 * @return array
	 */
	function friends_ids_by_id( $uid, $cursor = 0, $count = 500 )
	{
		$params = array();
		$this->id_format($uid);
		$params['uid'] = $uid;
		$params['cursor'] = $cursor;
		$params['count'] = $count;
		return $this->oauth->get( 'friendships/friends/ids', $params );
	}

	/**
	 * 获取用户的关注列表uid // get the uid list of the users that current user is following.
	 *
	 * 如果没有提供cursor参数，将只返回最前面的5000个关注id // if cursor parameter is not provided, only return the first 5000 followers id.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/friends/ids friendships/friends/ids}
	 *
	 * @access public
	 * @param int $cursor 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。//return the cursor of the result, next page uses the next_cursor in the return value, previous page uses previous_cursor. default = 0
	 * @param int $count 每次返回的最大记录数（即页面大小），不大于5000, 默认返回500。// maximum number of records each return (page size), less than 5000, default = 500.
	 * @param string $screen_name 要获取的用户的 screen_name，默认为当前用户 // the screen_name of the user that is to be searched, default = current user.
	 * @return array
	 */
	function friends_ids_by_name( $screen_name, $cursor = 0, $count = 500 )
	{
		$params = array();
		$params['cursor'] = $cursor;
		$params['count'] = $count;
		$params['screen_name'] = $screen_name;
		return $this->oauth->get( 'friendships/friends/ids', $params );
	}


	/**
	 * 批量获取当前登录用户的关注人的备注信息 // get the remark information of the users that current user is following.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/friends/remark_batch friendships/friends/remark_batch}
	 *
	 * @param string $uids  需要获取备注的用户UID，用半角逗号分隔，最多不超过50个。//the user UID that needs to get the remarks. Separated by commas, up to 50.
	 * @return array
	 **/
	function friends_remark_batch( $uids )
	{
		$params = array();
		if (is_array( $uids ) && !empty( $uids )) {
			foreach( $uids as $k => $v) {
				$this->id_format( $uids[$k] );
			}
			$params['uids'] = join(',', $uids);
		} else {
			$params['uids'] = $uids;
		}
		return $this->oauth->get( 'friendships/friends/remark_batch', $params  );
	}

	/**
	 * 获取用户的粉丝列表  // the list of active fans
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/followers friendships/followers}
	 *
	 * @param int $uid  需要查询的用户UID  the UID of the user that is to be searched.
	 * @param int $count 单页返回的记录条数，默认为50，最大不超过200。// the number of records that each page returns. default = 50, up to 200.
	 * @param int $cursor false 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。//return the cursor of the result, next page uses the next_cursor in the return value, previous page uses previous_cursor. default = 0
	 * @return array
	 **/
	function followers_by_id( $uid , $cursor = 0 , $count = 50)
	{
		$params = array();
		$this->id_format($uid);
		$params['uid'] = $uid;
		$params['count'] = $count;
		$params['cursor'] = $cursor;
		return $this->oauth->get( 'friendships/followers', $params  );
	}

	/**
	 * 获取用户的粉丝列表  // the list of active fans
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/followers friendships/followers}
	 *
	 * @param string $screen_name  需要查询的用户的昵称 // the nickname of the user that is to be searched.
	 * @param int  $count 单页返回的记录条数，默认为50，最大不超过200。// the number of records that each page returns. default = 50, up to 200.
	 * @param int  $cursor false 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。//return the cursor of the result, next page uses the next_cursor in the return value, previous page uses previous_cursor. default = 0
	 * @return array
	 **/
	function followers_by_name( $screen_name, $cursor = 0 , $count = 50 )
	{
		$params = array();
		$params['screen_name'] = $screen_name;
		$params['count'] = $count;
		$params['cursor'] = $cursor;
		return $this->oauth->get( 'friendships/followers', $params  );
	}

	/**
	 * 获取用户的粉丝列表uid  // the list of uids of active fans
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/followers friendships/followers}
	 *
	 * @param int $uid 需要查询的用户UID // the UID of the user that is to be searched.
	 * @param int $count 单页返回的记录条数，默认为50，最大不超过200。// the number of records that each page returns. default = 50, up to 200.
	 * @param int $cursor 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。//return the cursor of the result, next page uses the next_cursor in the return value, previous page uses previous_cursor. default = 0
	 * @return array
	 **/
	function followers_ids_by_id( $uid, $cursor = 0 , $count = 50 )
	{
		$params = array();
		$this->id_format($uid);
		$params['uid'] = $uid;
		$params['count'] = $count;
		$params['cursor'] = $cursor;
		return $this->oauth->get( 'friendships/followers/ids', $params  );
	}

	/**
	 * 获取用户的粉丝列表uid // the list of uids of active fans
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/followers friendships/followers}
	 *
	 * @param string $screen_name 需要查询的用户screen_name // the screen_name of the user that is to be searched.
	 * @param int $count 单页返回的记录条数，默认为50，最大不超过200。// the number of records that each page returns. default = 50, up to 200.
	 * @param int $cursor 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。//return the cursor of the result, next page uses the next_cursor in the return value, previous page uses previous_cursor. default = 0
	 * @return array
	 **/
	function followers_ids_by_name( $screen_name, $cursor = 0 , $count = 50 )
	{
		$params = array();
		$params['screen_name'] = $screen_name;
		$params['count'] = $count;
		$params['cursor'] = $cursor;
		return $this->oauth->get( 'friendships/followers/ids', $params  );
	}

	/**
	 * 获取优质粉丝 // get fans that are active.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/followers/active friendships/followers/active}
	 *
	 * @param int $uid 需要查询的用户UID。// user UID that is to be searched
	 * @param int $count 返回的记录条数，默认为20，最大不超过200。// the number of records. default = 20. up to 200
     * @return array
	 **/
	function followers_active( $uid,  $count = 20)
	{
		$param = array();
		$this->id_format($uid);
		$param['uid'] = $uid;
		$param['count'] = $count;
		return $this->oauth->get( 'friendships/followers/active', $param);
	}


	/**
	 * 获取当前登录用户的关注人中又关注了指定用户的用户列表 // get the list of users that current user is following and that follow specific users.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/friends_chain/followers friendships/friends_chain/followers}
	 *
	 * @param int $uid 指定的关注目标用户UID。// specific following target user UID
	 * @param int $count 单页返回的记录条数，默认为50。// the number of records that each page returns. default = 50
	 * @param int $page 返回结果的页码，默认为1。// return page number that has the result. default = 1
	 * @return array
	 **/
	function friends_chain_followers( $uid, $page = 1, $count = 50 )
	{
		$params = array();
		$this->id_format($uid);
		$params['uid'] = $uid;
		$params['count'] = $count;
		$params['page'] = $page;
		return $this->oauth->get( 'friendships/friends_chain/followers',  $params );
	}

	/**
	 * 返回两个用户关系的详细情况  // return the relationship between two users.
	 *
	 * 如果源用户或目的用户不存在，将返回http的400错误  // if source user or destination user does not exist, return ttp 400 error.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/show friendships/show}
	 *
	 * @access public
	 * @param mixed $target_id 目标用户UID   // destination user's UID
	 * @param mixed $source_id 源用户UID，可选，默认为当前的用户 // source user's UID. Optional, default = current user.
	 * @return array
	 */
	function is_followed_by_id( $target_id, $source_id = NULL )
	{
		$params = array();
		$this->id_format($target_id);
		$params['target_id'] = $target_id;

		if ( $source_id != NULL ) {
			$this->id_format($source_id);
			$params['source_id'] = $source_id;
		}

		return $this->oauth->get( 'friendships/show', $params );
	}

	/**
	 * 返回两个用户关系的详细情况 // return the relationship between two users.
	 *
	 * 如果源用户或目的用户不存在，将返回http的400错误 // if source user or destination user does not exist, return ttp 400 error.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/show friendships/show}
	 *
	 * @access public
	 * @param mixed $target_name 目标用户的微博昵称 // destination user's weibo nickname
	 * @param mixed $source_name 源用户的微博昵称，可选，默认为当前的用户 // source user's weibo nickname. Optional, default = current user.
	 * @return array
	 */
	function is_followed_by_name( $target_name, $source_name = NULL )
	{
		$params = array();
		$params['target_screen_name'] = $target_name;

		if ( $source_name != NULL ) {
			$params['source_screen_name'] = $source_name;
		}

		return $this->oauth->get( 'friendships/show', $params );
	}

	/**
	 * 关注一个用户。//follow one user.
	 *
	 * 成功则返回关注人的资料，目前最多关注2000人，失败则返回一条字符串的说明。如果已经关注了此人，则返回http 403的状态。关注不存在的ID将返回400。//If succeed, return the user's information. Currently, can follow up to 2000 users. If fail, return a description. if you have alrady followed this user, return http 403 status, If you follow the ID that does not exist, return 400.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/create friendships/create}
	 *
	 * @access public
	 * @param int $uid 要关注的用户UID  //the user's UID that to be followed.
	 * @return array
	 */
	function follow_by_id( $uid )
	{
		$params = array();
		$this->id_format($uid);
		$params['uid'] = $uid;
		return $this->oauth->post( 'friendships/create', $params );
	}

	/**
	 * 关注一个用户。//follow one user.
	 *
	 * 成功则返回关注人的资料，目前的最多关注2000人，失败则返回一条字符串的说明。如果已经关注了此人，则返回http 403的状态。关注不存在的ID将返回400。//If succeed, return the user's information. Currently, can follow up to 2000 users. If fail, return a description. if you have alrady followed this user, return http 403 status, If you follow the ID that does not exist, return 400.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/create friendships/create}
	 *
	 * @access public
	 * @param string $screen_name 要关注的用户昵称 //the user's nickname that to be followed.
	 * @return array
	 */
	function follow_by_name( $screen_name )
	{
		$params = array();
		$params['screen_name'] = $screen_name;
		return $this->oauth->post( 'friendships/create', $params);
	}


	/**
	 * 根据用户UID批量关注用户 // follow a batch of users according to their UIDs.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/friendships/create_batch friendships/create_batch}
	 *
	 * @param string $uids 要关注的用户UID，用半角逗号分隔，最多不超过20个。 // the UIDs that is to be followed. Separated by commas, up to 20.
	 * @return array
	 */
	function follow_create_batch( $uids )
	{
		$params = array();
		if (is_array($uids) && !empty($uids)) {
			foreach($uids as $k => $v) {
				$this->id_format($uids[$k]);
			}
			$params['uids'] = join(',', $uids);
		} else {
			$params['uids'] = $uids;
		}
		return $this->oauth->post( 'friendships/create_batch', $params);
	}

	/**
	 * 取消关注某用户 //Cancel following certain user.
	 *
	 * 取消关注某用户。成功则返回被取消关注人的资料，失败则返回一条字符串的说明。//Cancel following certain user. If succeed, return the detail of the user that is unfollowed by current user. If fail, return a description.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/destroy friendships/destroy}
	 *
	 * @access public
	 * @param int $uid 要取消关注的用户UID // the user UID that is to be unfollowed.
	 * @return array
	 */
	function unfollow_by_id( $uid )
	{
		$params = array();
		$this->id_format($uid);
		$params['uid'] = $uid;
		return $this->oauth->post( 'friendships/destroy', $params);
	}

	/**
	 * 取消关注某用户 //Cancel following certain user.
	 *
	 * 取消关注某用户。成功则返回被取消关注人的资料，失败则返回一条字符串的说明。//Cancel following certain user. If succeed, return the detail of the user that is unfollowed by current user. If fail, return a description.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/destroy friendships/destroy}
	 *
	 * @access public
	 * @param string $screen_name 要取消关注的用户昵称 // the user nickname that is to be unfollowed.
	 * @return array
	 */
	function unfollow_by_name( $screen_name )
	{
		$params = array();
		$params['screen_name'] = $screen_name;
		return $this->oauth->post( 'friendships/destroy', $params);
	}

	/**
	 * 更新当前登录用户所关注的某个好友的备注信息 // update the remark information of one friend that current user is following.
	 *
	 * 只能修改当前登录用户所关注的用户的备注信息。否则将给出400错误。// can only change the remark informtion if the users that current user is following. or it shows 400 error.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/friendships/remark/update friendships/remark/update}
	 *
	 * @access public
	 * @param int $uid 需要修改备注信息的用户ID。//the user ID whose remark information needs to be changed.
	 * @param string $remark 备注信息。//remark information.
	 * @return array
	 */
	function update_remark( $uid, $remark )
	{
		$params = array();
		$this->id_format($uid);
		$params['uid'] = $uid;
		$params['remark'] = $remark;
		return $this->oauth->post( 'friendships/remark/update', $params);
	}

	/**
	 * 获取当前用户最新私信列表 // get the latest private message list of current user.
	 *
	 * 返回用户的最新n条私信，并包含发送者和接受者的详细资料。 //return user's latest n private messages, including the full detail about sender and receiver.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/direct_messages direct_messages}
	 *
	 * @access public
	 * @param int $page 页码  // page number.
	 * @param int $count 每次返回的最大记录数，最多返回200条，默认50。// The maximum number of records. Max=200, default = 50
	 * @param int64 $since_id 返回ID比数值since_id大（比since_id时间晚的）的私信。可选。// if you specify this parameter, return the private messages that are sent later than since_id. Optional
	 * @param int64 $max_id 返回ID不大于max_id(时间不晚于max_id)的私信。可选。// if you specify this parameter, return the private messages that are sent earlier than or equal to max_id. Optional
	 * @return array
	 */
	function list_dm( $page = 1, $count = 50, $since_id = 0, $max_id = 0 )
	{
		$params = array();
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}

		return $this->request_with_pager( 'direct_messages', $page, $count, $params );
	}

	/**
	 * 获取当前用户发送的最新私信列表 // get the latest private messgae list that current user has sent
	 *
	 * 返回登录用户已发送最新50条私信。包括发送者和接受者的详细资料。//return the latest 50 private messages sent by current user, including the full detail about sender and receiver.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/direct_messages/sent direct_messages/sent}
	 *
	 * @access public
	 * @param int $page 页码 //page number.
	 * @param int $count 每次返回的最大记录数，最多返回200条，默认50。 // The maximum number of records. Max=200, default = 50
	 * @param int64 $since_id 返回ID比数值since_id大（比since_id时间晚的）的私信。可选。// if you specify this parameter, return the private messages that are sent later than since_id. Optional
	 * @param int64 $max_id 返回ID不大于max_id(时间不晚于max_id)的私信。可选。// if you specify this parameter, return the private messages that are sent earlier than or equal to max_id. Optional
	 * @return array
	 */
	function list_dm_sent( $page = 1, $count = 50, $since_id = 0, $max_id = 0 )
	{
		$params = array();
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}

		return $this->request_with_pager( 'direct_messages/sent', $page, $count, $params );
	}


	/**
	 * 获取与当前登录用户有私信往来的用户列表，与该用户往来的最新私信 // Return a list of users that have sent private messages with current user, as well as return the latest private message of each user.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/direct_messages/user_list direct_messages/user_list}
	 *
	 * @param int $count  单页返回的记录条数，默认为20。// return the number of records each page shows, default = 20
	 * @param int $cursor 返回结果的游标，下一页用返回值里的next_cursor，上一页用previous_cursor，默认为0。// return the cursor of the result. next page uses the next_cursor from the return value, previous page uses the previous_cursor.
	 * @return array
	 */
	function dm_user_list( $count = 20, $cursor = 0)
	{
		$params = array();
		$params['count'] = $count;
		$params['cursor'] = $cursor;
		return $this->oauth->get( 'direct_messages/user_list', $params );
	}

	/**
	 * 获取与指定用户的往来私信列表 //get the private message list with specific user.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/direct_messages/conversation direct_messages/conversation}
	 *
	 * @param int $uid 需要查询的用户的UID。//User ID
	 * @param int $since_id 若指定此参数，则返回ID比since_id大的私信（即比since_id时间晚的私信），默认为0。// if you specify this parameter, return the private messages that are sent later than since_id, default = 0
	 * @param int $max_id  若指定此参数，则返回ID小于或等于max_id的私信，默认为0。// if you specify this parameter, return the private messages that are sent earlier than or equal to max_id, default = 0
	 * @param int $count 单页返回的记录条数，默认为50。// return the number of records each page shows, default = 50
	 * @param int $page  返回结果的页码，默认为1。//return the page number that returns the result. default = 1
	 * @return array
	 */
	function dm_conversation( $uid, $page = 1, $count = 50, $since_id = 0, $max_id = 0)
	{
		$params = array();
		$this->id_format($uid);
		$params['uid'] = $uid;
		if ($since_id) {
			$this->id_format($since_id);
			$params['since_id'] = $since_id;
		}
		if ($max_id) {
			$this->id_format($max_id);
			$params['max_id'] = $max_id;
		}
		$params['count'] = $count;
		$params['page'] = $page;
		return $this->oauth->get( 'direct_messages/conversation', $params );
	}

	/**
	 * 根据私信ID批量获取私信内容 //fetch private message contents by private message IDs.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/direct_messages/show_batch direct_messages/show_batch}
	 *
	 * @param string  $dmids 需要查询的私信ID，用半角逗号分隔，一次最多50个 // the private message ID that needs to be searched, separated by commas, up to 50 IDs for each search.
	 * @return array
	 */
	function dm_show_batch( $dmids )
	{
		$params = array();
		if (is_array($dmids) && !empty($dmids)) {
			foreach($dmids as $k => $v) {
				$this->id_format($dmids[$k]);
			}
			$params['dmids'] = join(',', $dmids);
		} else {
			$params['dmids'] = $dmids;
		}
		return $this->oauth->get( 'direct_messages/show_batch',  $params );
	}

	/**
	 * 发送私信  //send a private message
	 *
	 * 发送一条私信。成功将返回完整的发送消息。 //send a private message, if succeed, return the whole sending message.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/direct_messages/new direct_messages/new}
	 *
	 * @access public
	 * @param int $uid 用户UID  //user UID
	 * @param string $text 要发生的消息内容，文本大小必须小于300个汉字。// the content, size <= 300 Chinese characters.
	 * @param int $id 需要发送的微博ID。//sender's weibo ID
	 * @return array
	 */
	function send_dm_by_id( $uid, $text, $id = NULL )
	{
		$params = array();
		$this->id_format( $uid );
		$params['text'] = $text;
		$params['uid'] = $uid;
		if ($id) {
			$this->id_format( $id );
			$params['id'] = $id;
		}
		return $this->oauth->post( 'direct_messages/new', $params );
	}

	/**
	 * 发送私信 //send a private message
	 *
	 * 发送一条私信。成功将返回完整的发送消息。//send a private message, if succeed, return the whole sending message.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/direct_messages/new direct_messages/new}
	 *
	 * @access public
	 * @param string $screen_name 用户昵称 //user nickname
	 * @param string $text 要发生的消息内容，文本大小必须小于300个汉字。// the content, size <= 300 Chinese characters.
	 * @param int $id 需要发送的微博ID。//sender's weibo ID
	 * @return array
	 */
	function send_dm_by_name( $screen_name, $text, $id = NULL )
	{
		$params = array();
		$params['text'] = $text;
		$params['screen_name'] = $screen_name;
		if ($id) {
			$this->id_format( $id );
			$params['id'] = $id;
		}
		return $this->oauth->post( 'direct_messages/new', $params);
	}

	/**
	 * 删除一条私信 //delete one private message
	 *
	 * 按ID删除私信。操作用户必须为私信的接收人。//delete private message according to ID. Only the receiver of the private message can do this.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/direct_messages/destroy direct_messages/destroy}
	 *
	 * @access public
	 * @param int $did 要删除的私信主键ID //private message key ID that is to be deleted.
	 * @return array
	 */
	function delete_dm( $did )
	{
		$this->id_format($did);
		$params = array();
		$params['id'] = $did;
		return $this->oauth->post('direct_messages/destroy', $params);
	}

	/**
	 * 批量删除私信 //delete private messages in a batch
	 *
	 * 批量删除当前登录用户的私信。出现异常时，返回400错误。//delete current users' private message. Return 400 error if there is an exception.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/direct_messages/destroy_batch direct_messages/destroy_batch}
	 *
	 * @access public
	 * @param mixed $dids 欲删除的一组私信ID，用半角逗号隔开，或者由一组评论ID组成的数组。最多20个。例如："4976494627, 4976262053"或array(4976494627,4976262053);//The private messges IDs that is to be deleted, separated by commas, or array made by an array of comments ID, up to 20 comments.
	 * @return array
	 */
	function delete_dms( $dids )
	{
		$params = array();
		if (is_array($dids) && !empty($dids)) {
			foreach($dids as $k => $v) {
				$this->id_format($dids[$k]);
			}
			$params['ids'] = join(',', $dids);
		} else {
			$params['ids'] = $dids;
		}

		return $this->oauth->post( 'direct_messages/destroy_batch', $params);
	}



	/**
	 * 获取用户基本信息 //fetch user basic information
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/basic account/profile/basic}
	 *
	 * @param int $uid  需要获取基本信息的用户UID，默认为当前登录用户。 //get user UID that needs to get basic information. Current user account by default.
	 * @return array
	 */
	function account_profile_basic( $uid = NULL  )
	{
		$params = array();
		if ($uid) {
			$this->id_format($uid);
			$params['uid'] = $uid;
		}
		return $this->oauth->get( 'account/profile/basic', $params );
	}

	/**
	 * 获取用户的教育信息 // get user's education information.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/education account/profile/education}
	 *
	 * @param int $uid  需要获取教育信息的用户UID，默认为当前登录用户。//get user UID that needs to get education information. Current user account by default.
	 * @return array
	 */
	function account_education( $uid = NULL )
	{
		$params = array();
		if ($uid) {
			$this->id_format($uid);
			$params['uid'] = $uid;
		}
		return $this->oauth->get( 'account/profile/education', $params );
	}

	/**
	 * 批量获取用户的教育信息  get users' education information in a batch.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/education_batch account/profile/education_batch}
	 *
	 * @param string $uids 需要获取教育信息的用户UID，用半角逗号分隔，最多不超过20。// get user UIDs that need to get education information, separated by commas, up to 20.
	 * @return array
	 */
	function account_education_batch( $uids  )
	{
		$params = array();
		if (is_array($uids) && !empty($uids)) {
			foreach($uids as $k => $v) {
				$this->id_format($uids[$k]);
			}
			$params['uids'] = join(',', $uids);
		} else {
			$params['uids'] = $uids;
		}

		return $this->oauth->get( 'account/profile/education_batch', $params );
	}


	/**
	 * 获取用户的职业信息 //get user's profession information.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/career account/profile/career}
	 *
	 * @param int $uid  需要获取教育信息的用户UID，默认为当前登录用户。//get user UID that needs to get education information. Current user account by default.
	 * @return array
	 */
	function account_career( $uid = NULL )
	{
		$params = array();
		if ($uid) {
			$this->id_format($uid);
			$params['uid'] = $uid;
		}
		return $this->oauth->get( 'account/profile/career', $params );
	}

	/**
	 * 批量获取用户的职业信息 // get users' profession information in a batch.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/career_batch account/profile/career_batch}
	 *
	 * @param string $uids 需要获取教育信息的用户UID，用半角逗号分隔，最多不超过20。// get user UIDs that need to get education information, separated by commas, up to 20.
	 * @return array
	 */
	function account_career_batch( $uids )
	{
		$params = array();
		if (is_array($uids) && !empty($uids)) {
			foreach($uids as $k => $v) {
				$this->id_format($uids[$k]);
			}
			$params['uids'] = join(',', $uids);
		} else {
			$params['uids'] = $uids;
		}

		return $this->oauth->get( 'account/profile/career_batch', $params );
	}

	/**
	 * 获取隐私信息设置情况 // fetch privacy information setting.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/get_privacy account/get_privacy}
	 *
	 * @access public
	 * @return array
	 */
	function get_privacy()
	{
		return $this->oauth->get('account/get_privacy');
	}

	/**
	 * 获取所有的学校列表 //fetch all school lists
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/school_list account/profile/school_list}
	 *
	 * @param array $query 搜索选项。格式：array('key0'=>'value0', 'key1'=>'value1', ....)。支持的key: //query options. supporting key:
	 *  - province	int		省份范围，省份ID。//province range, province ID
	 *  - city		int		城市范围，城市ID。// city range, city ID
	 *  - area		int		区域范围，区ID。//district range, district ID
	 *  - type		int		学校类型，1：大学、2：高中、3：中专技校、4：初中、5：小学，默认为1。//school type: 1:university 2.high school 3:middle technical school 4:middle school 5:primary school. Default = 1
	 *  - capital	string	学校首字母，默认为A。//first letter of the school, default = A
	 *  - keyword	string	学校名称关键字。//school keywords
	 *  - count		int		返回的记录条数，默认为10。// number of records, default = 10
	 * 参数keyword与capital二者必选其一，且只能选其一。按首字母capital查询时，必须提供province参数。// can only choose one between keyword and capital. if you query based on first letter, must provide province parameter.
	 * @access public
	 * @return array
	 */
	function school_list( $query )
	{
		$params = $query;

		return $this->oauth->get( 'account/profile/school_list', $params );
	}

	/**
	 * 获取当前登录用户的API访问频率限制情况 // fetch the information about API access frequency limitation about current user.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/rate_limit_status account/rate_limit_status}
	 *
	 * @access public
	 * @return array
	 */
	function rate_limit_status()
	{
		return $this->oauth->get( 'account/rate_limit_status' );
	}

	/**
	 * OAuth授权之后，获取授权用户的UID // after OAuth authorization, get the UID of the authorized user.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/get_uid account/get_uid}
	 *
	 * @access public
	 * @return array
	 */
	function get_uid()
	{
		return $this->oauth->get( 'account/get_uid' );
	}


	/**
	 * 更改用户资料 // change user data
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/basic_update account/profile/basic_update}
	 *
	 * @access public
	 * @param array $profile 要修改的资料。格式：array('key1'=>'value1', 'key2'=>'value2', .....)。
	 * 支持修改的项：//supporting change options
	 *  - screen_name		string	用户昵称，不可为空。// user nickname, required.
	 *  - gender	i		string	用户性别，m：男、f：女，不可为空。// gender. m:male .f:female. Required
	 *  - real_name			string	用户真实姓名。//real name
	 *  - real_name_visible	int		真实姓名可见范围，0：自己可见、1：关注人可见、2：所有人可见。// real name visibility. 0: myself, 1: people I follow, 2:everyone
	 *  - province	true	int		省份代码ID，不可为空。 //province code ID, required
	 *  - city	true		int		城市代码ID，不可为空。// city code ID, required
	 *  - birthday			string	用户生日，格式：yyyy-mm-dd。//birthday, format:...
	 *  - birthday_visible	int		生日可见范围，0：保密、1：只显示月日、2：只显示星座、3：所有人可见。//birthday visibility 0:secret 1:only show month and day, 2: only show constellation 3: everyone
	 *  - qq				string	用户QQ号码。//QQ number
	 *  - qq_visible		int		用户QQ可见范围，0：自己可见、1：关注人可见、2：所有人可见。//QQ visibility, 0: myself, 1:the people I follow, 2:everyone
	 *  - msn				string	用户MSN。// MSN
	 *  - msn_visible		int		用户MSN可见范围，0：自己可见、1：关注人可见、2：所有人可见。//MSN visibility, 0: myself, 1:the people I follow, 2:everyone
	 *  - url				string	用户博客地址。// blog address
	 *  - url_visible		int		用户博客地址可见范围，0：自己可见、1：关注人可见、2：所有人可见。//blog address visibility,  0: myself, 1:the people I follow, 2:everyone
	 *  - credentials_type	int		证件类型，1：身份证、2：学生证、3：军官证、4：护照。// credential type, 1: identity card, 2: student card, 3: police card, 4 passport
	 *  - credentials_num	string	证件号码。// credential number
	 *  - email				string	用户常用邮箱地址。//email address
	 *  - email_visible		int		用户常用邮箱地址可见范围，0：自己可见、1：关注人可见、2：所有人可见。//email address visibility. 0: myself, 1:the people I follow, 2:everyone
	 *  - lang				string	语言版本，zh_cn：简体中文、zh_tw：繁体中文。//language type, zh_cn:simplified Chinese, zh_tw: traditional Chinese
	 *  - description		string	用户描述，最长不超过70个汉字。//user description, less than 70 Chinese characters.
	 * 填写birthday参数时，做如下约定：// when you fill birthday parameters, follow following rules:
	 *  - 只填年份时，采用1986-00-00格式；// only fill year of birth, format: ...
	 *  - 只填月份时，采用0000-08-00格式；// only fill month of birth, format: ...
	 *  - 只填某日时，采用0000-00-28格式。// only fill day of birth, format:...
	 * @return array
	 */
	function update_profile( $profile )
	{
		return $this->oauth->post( 'account/profile/basic_update',  $profile);
	}


	/**
	 * 设置教育信息 // set education information
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/edu_update account/profile/edu_update}
	 *
	 * @access public
	 * @param array $edu_update 要修改的学校信息。格式：array('key1'=>'value1', 'key2'=>'value2', .....)。// the school information to be altered. formatL array...
	 * 支持设置的项：//supporting options
	 *  - type			int		学校类型，1：大学、2：高中、3：中专技校、4：初中、5：小学，默认为1。必填参数 //school type: 1:university 2.high school 3:middle technical school 4:middle school 5:primary school. Default = 1
	 *  - school_id	`	int		学校代码，必填参数 //school code. required
	 *  - id			string	需要修改的教育信息ID，不传则为新建，传则为更新。// education information ID. create a new one if it is not passed. update an existing one if it is passed
	 *  - year			int		入学年份，最小为1900，最大不超过当前年份 // the year of joining the school. min:1900, max:current year
	 *  - department	string	院系或者班别。// department or class
	 *  - visible		int		开放等级，0：仅自己可见、1：关注的人可见、2：所有人可见。//visible type. 0:only to myself, 1: the people I follow 2:everyone.
	 * @return array
	 */
	function edu_update( $edu_update )
	{
		return $this->oauth->post( 'account/profile/edu_update',  $edu_update);
	}

	/**
	 * 根据学校ID删除用户的教育信息 // delete education information according to the schoold ID
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/edu_destroy account/profile/edu_destroy}
	 *
	 * @param int $id 教育信息里的学校ID。// School ID
	 * @return array
	 */
	function edu_destroy( $id )
	{
		$this->id_format( $id );
		$params = array();
		$params['id'] = $id;
		return $this->oauth->post( 'account/profile/edu_destroy', $params);
	}

	/**
	 * 设置职业信息 //set profession information
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/car_update account/profile/car_update}
	 *
	 * @param array $car_update 要修改的职业信息。格式：array('key1'=>'value1', 'key2'=>'value2', .....)。// the profession information that is going to be changed. format: array
	 * 支持设置的项：//supporting options:
	 *  - id			string	需要更新的职业信息ID。// the profession ID that needs to be updated.
	 *  - start			int		进入公司年份，最小为1900，最大为当年年份。// when user entered the company
	 *  - end			int		离开公司年份，至今填0。// how many years has the user left the company. 0 if the user is still there.
	 *  - department	string	工作部门。// working department
	 *  - visible		int		可见范围，0：自己可见、1：关注人可见、2：所有人可见。// visible range. 0:self, 1:fans, 2: everyone
	 *  - province		int		省份代码ID，不可为空值。// province ID, cannot be null
	 *  - city			int		城市代码ID，不可为空值。// city ID, cannot be null
	 *  - company		string	公司名称，不可为空值。// company name, cannot be null
	 * 参数province与city二者必选其一<br /> // must choose one of the province or city
	 * 参数id为空，则为新建职业信息，参数company变为必填项，参数id非空，则为更新，参数company可选 // if id is null, it is creating new professtion information, then company parameter becomes required, if id is not null, then company parameter is optional.
	 * @return array
	 */
	function car_update( $car_update )
	{
		return $this->oauth->post( 'account/profile/car_update', $car_update);
	}

	/**
	 * 根据公司ID删除用户的职业信息 // delete the user's professional information according to company ID.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/profile/car_destroy account/profile/car_destroy}
	 *
	 * @access public
	 * @param int $id  职业信息里的公司ID // company ID
	 * @return array
	 */
	function car_destroy( $id )
	{
		$this->id_format($id);
		$params = array();
		$params['id'] = $id;
		return $this->oauth->post( 'account/profile/car_destroy', $params);
	}

	/**
	 * 更改头像 //change profile image
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/avatar/upload account/avatar/upload}
	 *
	 * @param string $image_path 要上传的头像路径, 支持url。[只支持png/jpg/gif三种格式, 增加格式请修改get_image_mime方法] 必须为小于700K的有效的GIF, JPG图片. 如果图片大于500像素将按比例缩放。// the profile path, support url. (only support png/jpg/gif, you need to alter get_image_mime to add new format.). Must be less than 700K, type should be GIF and JPG.
	 * @return array
	 */
	function update_profile_image( $image_path )
	{
		$params = array();
		$params['image'] = "@{$image_path}";

		return $this->oauth->post('account/avatar/upload', $params);
	}

	/**
	 * 设置隐私信息 //set private information
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/account/update_privacy account/update_privacy}
	 *
	 * @param array $privacy_settings 要修改的隐私设置。格式：array('key1'=>'value1', 'key2'=>'value2', .....)。//private setting. format:array...
	 * 支持设置的项：//supporting options:
	 *  - comment	int	是否可以评论我的微博，0：所有人、1：关注的人，默认为0。//allowed to comment my weibo, 0:everyone 1.the people I am following. default = 0
	 *  - geo		int	是否开启地理信息，0：不开启、1：开启，默认为1。//whether to open location information. 0: No, 1: Yes. Default = 1
	 *  - message	int	是否可以给我发私信，0：所有人、1：关注的人，默认为0。// allowed to send private message to me. 0: everyone, 1: the people I am following. default = 0
	 *  - realname	int	是否可以通过真名搜索到我，0：不可以、1：可以，默认为0。// whether the user can be searched by using real name. 0: No, 1: Yes. default = 0
	 *  - badge		int	勋章是否可见，0：不可见、1：可见，默认为1。//whether can see the badge. 0: No, 1: Yes. Default = 1
	 *  - mobile	int	是否可以通过手机号码搜索到我，0：不可以、1：可以，默认为0。//whether can be found by mobile number. 0:No, 1:Yes. default = 0
	 * 以上参数全部选填 // all above parameters are optional.
	 * @return array
	 */
	function update_privacy( $privacy_settings )
	{
		return $this->oauth->post( 'account/update_privacy', $privacy_settings);
	}


	/**
	 * 获取当前用户的收藏列表 // get the favorites list of current user.
	 *
	 * 返回用户的发布的最近20条收藏信息，和用户收藏页面返回内容是一致的。 // return recent 20 favorites information of the user.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/favorites favorites}
	 *
	 * @access public
	 * @param  int $page 返回结果的页码，默认为1。// return the page that returns the result, default = 1
	 * @param  int $count 单页返回的记录条数，默认为50。// return the number of records each page returns. default = 50
	 * @return array
	 */
	function get_favorites( $page = 1, $count = 50 )
	{
		$params = array();
		$params['page'] = intval($page);
		$params['count'] = intval($count);

		return $this->oauth->get( 'favorites', $params );
	}


	/**
	 * 根据收藏ID获取指定的收藏信息 // get favorites informaton according to favorites ID
	 *
	 * 根据收藏ID获取指定的收藏信息。// get favorites informaton according to favorites ID
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/favorites/show favorites/show}
	 *
	 * @access public
	 * @param int $id 需要查询的收藏ID。// favorites ID that needs to be searched
	 * @return array
	 */
	function favorites_show( $id )
	{
		$params = array();
		$this->id_format($id);
		$params['id'] = $id;
		return $this->oauth->get( 'favorites/show', $params );
	}


	/**
	 * 根据标签获取当前登录用户该标签下的收藏列表 // get the favorites list according to the tags of current user.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/favorites/by_tags favorites/by_tags}
	 *
	 *
	 * @param int $tid  需要查询的标签ID。// tag ID that needs to be searched
	 * @param int $count 单页返回的记录条数，默认为50。// the number of records each page returns, default = 50
	 * @param int $page 返回结果的页码，默认为1。// the page that returns result, default = 1
	 * @return array
	 */
	function favorites_by_tags( $tid, $page = 1, $count = 50)
	{
		$params = array();
		$params['tid'] = $tid;
		$params['count'] = $count;
		$params['page'] = $page;
		return $this->oauth->get( 'favorites/by_tags', $params );
	}


	/**
	 * 获取当前登录用户的收藏标签列表 // get the favorites tags list of curren user.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/favorites/tags favorites/tags}
	 *
	 * @access public
	 * @param int $count 单页返回的记录条数，默认为50。 // the number of records each page returns, default = 50
	 * @param int $page 返回结果的页码，默认为1。// the page that returns result, default = 1
	 * @return array
	 */
	function favorites_tags( $page = 1, $count = 50)
	{
		$params = array();
		$params['count'] = $count;
		$params['page'] = $page;
		return $this->oauth->get( 'favorites/tags', $params );
	}


	/**
	 * 收藏一条微博信息 // add one weibo post to favorites
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/favorites/create favorites/create}
	 *
	 * @access public
	 * @param int $sid 收藏的微博id // the weibo ID that is added to favorites
	 * @return array
	 */
	function add_to_favorites( $sid )
	{
		$this->id_format($sid);
		$params = array();
		$params['id'] = $sid;

		return $this->oauth->post( 'favorites/create', $params );
	}

	/**
	 * 删除微博收藏。// delete the weibo favorites
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/favorites/destroy favorites/destroy}
	 *
	 * @access public
	 * @param int $id 要删除的收藏微博信息ID. //the weibo ID that is deleted
	 * @return array
	 */
	function remove_from_favorites( $id )
	{
		$this->id_format($id);
		$params = array();
		$params['id'] = $id;
		return $this->oauth->post( 'favorites/destroy', $params);
	}


	/**
	 * 批量删除微博收藏。// delete weibo favorites in a batch
	 *
	 * 批量删除当前登录用户的收藏。出现异常时，返回HTTP400错误。// it returns HTTP400 error if there is exception.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/favorites/destroy_batch favorites/destroy_batch}
	 *
	 * @access public
	 * @param mixed $fids 欲删除的一组私信ID，用半角逗号隔开，或者由一组评论ID组成的数组。最多20个。例如："231101027525486630,201100826122315375"或array(231101027525486630,201100826122315375); // the private messages ID that will be deleted, separated by commas, or an array made by comment ID. up to 20.
	 * @return array
	 */
	function remove_from_favorites_batch( $fids )
	{
		$params = array();
		if (is_array($fids) && !empty($fids)) {
			foreach ($fids as $k => $v) {
				$this->id_format($fids[$k]);
			}
			$params['ids'] = join(',', $fids);
		} else {
			$params['ids'] = $fids;
		}

		return $this->oauth->post( 'favorites/destroy_batch', $params);
	}


	/**
	 * 更新一条收藏的收藏标签 //Update a favorites tag
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/favorites/tags/update favorites/tags/update}
	 *
	 * @access public
	 * @param int $id 需要更新的收藏ID。//the favorites ID that needs to be updated.
	 * @param string $tags 需要更新的标签内容，用半角逗号分隔，最多不超过2条。// the tag content that needs to be updated. Separated by commas, less than 2 tags.
	 * @return array
	 */
	function favorites_tags_update( $id,  $tags )
	{
		$params = array();
		$params['id'] = $id;
		if (is_array($tags) && !empty($tags)) {
			foreach ($tags as $k => $v) {
				$this->id_format($tags[$k]);
			}
			$params['tags'] = join(',', $tags);
		} else {
			$params['tags'] = $tags;
		}
		return $this->oauth->post( 'favorites/tags/update', $params );
	}

	/**
	 * 更新当前登录用户所有收藏下的指定标签 // update the specific tags in all the favorites of a user
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/favorites/tags/update_batch favorites/tags/update_batch}
	 *
	 * @param int $tid  需要更新的标签ID。必填 // the tag ID that needs to be updated. Required
	 * @param string $tag  需要更新的标签内容。必填 // the tag content that needs to be updated. Required.
	 * @return array
	 */
	function favorites_update_batch( $tid, $tag )
	{
		$params = array();
		$params['tid'] = $tid;
		$params['tag'] = $tag;
		return $this->oauth->post( 'favorites/tags/update_batch', $params);
	}

	/**
	 * 删除当前登录用户所有收藏下的指定标签 // delete specific tags that in the favorites of a user
	 *
	 * 删除标签后，该用户所有收藏中，添加了该标签的收藏均解除与该标签的关联关系 //after deleting the tags, in all the favorites of the user, the favorites that has the tag will delete the relationship with this tag.
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/favorites/tags/destroy_batch favorites/tags/destroy_batch}
	 *
	 * @param int $tid  需要更新的标签ID。必填 // the tag ID that needs to be updated. Required.
	 * @return array
	 */
	function favorites_tags_destroy_batch( $tid )
	{
		$params = array();
		$params['tid'] = $tid;
		return $this->oauth->post( 'favorites/tags/destroy_batch', $params);
	}

	/**
	 * 获取某用户的话题 // get the topics of certain user
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/trends trends}
	 *
	 * @param int $uid 查询用户的ID。默认为当前用户。可选。 // the user ID. default = current user.
	 * @param int $page 指定返回结果的页码。可选。 // return the page that return the result, optional
	 * @param int $count 单页大小。缺省值10。可选。// page size. default = 10. optional.
	 * @return array
	 */
	function get_trends( $uid = NULL, $page = 1, $count = 10 )
	{
		$params = array();
		if ($uid) {
			$params['uid'] = $uid;
		} else {
			$user_info = $this->get_uid();
			$params['uid'] = $user_info['uid'];
		}
		$this->id_format( $params['uid'] );
		$params['page'] = $page;
		$params['count'] = $count;
		return $this->oauth->get( 'trends', $params );
	}


	/**
	 * 判断当前用户是否关注某话题 // decide whether current user follows certain topic
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/trends/is_follow trends/is_follow}
	 *
	 * @access public
	 * @param string $trend_name 话题关键字。// keywords of the topic
	 * @return array
	 */
	function trends_is_follow( $trend_name )
	{
		$params = array();
		$params['trend_name'] = $trend_name;
		return $this->oauth->get( 'trends/is_follow', $params );
	}

	/**
	 * 返回最近一小时内的热门话题 //return the hot topics within a hour
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/trends/hourly trends/hourly}
	 *
	 * @param  int $base_app 是否基于当前应用来获取数据。1表示基于当前应用来获取数据，默认为0。可选。 // whether to get data based on current application. 1 is yes. default = 0. optional.
	 * @return array
	 */
	function hourly_trends( $base_app = 0 )
	{
		$params = array();
		$params['base_app'] = $base_app;

		return $this->oauth->get( 'trends/hourly', $params );
	}

	/**
	 * 返回最近一天内的热门话题 //return the hot topics within a day
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/trends/daily trends/daily}
	 *
	 * @param int $base_app 是否基于当前应用来获取数据。1表示基于当前应用来获取数据，默认为0。可选。// whether to get data based on current application. 1 is yes. default = 0. optional.
	 * @return array
	 * @return array
	 */
	function daily_trends( $base_app = 0 )
	{
		$params = array();
		$params['base_app'] = $base_app;

		return $this->oauth->get( 'trends/daily', $params );
	}

	/**
	 * 返回最近一周内的热门话题 //return the hot topics within a week
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/trends/weekly trends/weekly}
	 *
	 * @access public
	 * @param int $base_app 是否基于当前应用来获取数据。1表示基于当前应用来获取数据，默认为0。可选。// whether to get data based on current application. 1 is yes. default = 0. optional.
	 * @return array
	 */
	function weekly_trends( $base_app = 0 )
	{
		$params = array();
		$params['base_app'] = $base_app;

		return $this->oauth->get( 'trends/weekly', $params );
	}

	/**
	 * 关注某话题 // follow certain topic
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/trends/follow trends/follow}
	 *
	 * @access public
	 * @param string $trend_name 要关注的话题关键词。// the keywords of the topic
	 * @return array
	 */
	function follow_trends( $trend_name )
	{
		$params = array();
		$params['trend_name'] = $trend_name;
		return $this->oauth->post( 'trends/follow', $params );
	}

	/**
	 * 取消对某话题的关注 // cancel following certain topic
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/trends/destroy trends/destroy}
	 *
	 * @access public
	 * @param int $tid 要取消关注的话题ID。//the topic ID that the user is going to cancel
	 * @return array
	 */
	function unfollow_trends( $tid )
	{
		$this->id_format($tid);

		$params = array();
		$params['trend_id'] = $tid;

		return $this->oauth->post( 'trends/destroy', $params );
	}

	/**
	 * 返回指定用户的标签列表 // return the tag list for a specific user.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/tags tags}
	 *
	 * @param int $uid 查询用户的ID。默认为当前用户。可选。// The user ID that's doing the search. Current user.
	 * @param int $page 指定返回结果的页码。可选。// the page that returns the result. optional
	 * @param int $count 单页大小。缺省值20，最大值200。可选。// the page size. default = 20. MAX=200. Optional.
	 * @return array
	 */
	function get_tags( $uid = NULL, $page = 1, $count = 20 )
	{
		$params = array();
		if ( $uid ) {
			$params['uid'] = $uid;
		} else {
			$user_info = $this->get_uid();
			$params['uid'] = $user_info['uid'];
		}
		$this->id_format( $params['uid'] );
		$params['page'] = $page;
		$params['count'] = $count;
		return $this->oauth->get( 'tags', $params );
	}

	/**
	 * 批量获取用户的标签列表 // get the users's tags list in a batch
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/tags/tags_batch tags/tags_batch}
	 *
	 * @param  string $uids 要获取标签的用户ID。最大20，逗号分隔。必填 // get user ID that is going to get the tags, MAX = 20. Separated by commas. Required.
	 * @return array
	 */
	function get_tags_batch( $uids )
	{
		$params = array();
		if (is_array( $uids ) && !empty( $uids )) {
			foreach ($uids as $k => $v) {
				$this->id_format( $uids[$k] );
			}
			$params['uids'] = join(',', $uids);
		} else {
			$params['uids'] = $uids;
		}
		return $this->oauth->get( 'tags/tags_batch', $params );
	}

	/**
	 * 返回用户感兴趣的标签 //return the tags that users are interested
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/tags/suggestions tags/suggestions}
	 *
	 * @access public
	 * @param int $count 单页大小。缺省值10，最大值10。可选。 //page size. default = 10. Max=10, optional.
	 * @return array
	 */
	function get_suggest_tags( $count = 10)
	{
		$params = array();
		$params['count'] = intval($count);
		return $this->oauth->get( 'tags/suggestions', $params );
	}

	/**
	 * 为当前登录用户添加新的用户标签 // add a new user tag for the current account
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/tags/create tags/create}
	 *
	 * @access public
	 * @param mixed $tags 要创建的一组标签，每个标签的长度不可超过7个汉字，14个半角字符。多个标签之间用逗号间隔，或由多个标签构成的数组。如："abc,drf,efgh,tt"或array("abc", "drf", "efgh", "tt") // The tags that are going to be created, the length of each tag is less than 7 Chinese characters, 14 half-width characters. Multiple tags are separated by commas or an array made by multiple tags.
	 * @return array
	 */
	function add_tags( $tags )
	{
		$params = array();
		if (is_array($tags) && !empty($tags)) {
			$params['tags'] = join(',', $tags);
		} else {
			$params['tags'] = $tags;
		}
		return $this->oauth->post( 'tags/create', $params);
	}

	/**
	 * 删除标签 //delete tag
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/tags/destroy tags/destroy}
	 *
	 * @access public
	 * @param int $tag_id 标签ID，必填参数 //required, tag ID
	 * @return array
	 */
	function delete_tag( $tag_id )
	{
		$params = array();
		$params['tag_id'] = $tag_id;
		return $this->oauth->post( 'tags/destroy', $params );
	}

	/**
	 * 批量删除标签 // delete a batch of tages
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/tags/destroy_batch tags/destroy_batch}
	 *
	 * @access public
	 * @param mixed $ids 必选参数，要删除的tag id，多个id用半角逗号分割，最多10个。或由多个tag id构成的数组。如：“553,554,555"或array(553, 554, 555) // required parameter, the tag id that is going to be deleted, up to 10. Or an array made by multiple tag id. like...
	 * @return array
	 */
	function delete_tags( $ids )
	{
		$params = array();
		if (is_array($ids) && !empty($ids)) {
			$params['ids'] = join(',', $ids);
		} else {
			$params['ids'] = $ids;
		}
		return $this->oauth->post( 'tags/destroy_batch', $params );
	}


	/**
	 * 验证昵称是否可用，并给予建议昵称 // verify nickname, give suggestions on it
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/register/verify_nickname register/verify_nickname}
	 *
	 * @param string $nickname 需要验证的昵称。4-20个字符，支持中英文、数字、"_"或减号。必填 // the nickname that needs to be verified
	 * @return array
	 */
	function verify_nickname( $nickname )
	{
		$params = array();
		$params['nickname'] = $nickname;
		return $this->oauth->get( 'register/verify_nickname', $params );
	}



	/**
	 * 搜索用户时的联想搜索建议 //The suggestions/recommendations given when users are searching other users
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/search/suggestions/users search/suggestions/users}
	 *
	 * @param string $q 搜索的关键字，必须做URLencoding。必填,中间最好不要出现空格 // //keywords, must do URLencoding, required. better not to have spaces in the middle
	 * @param int $count 返回的记录条数，默认为10。// the number of records, default = 10
	 * @return array
	 */
	function search_users( $q,  $count = 10 )
	{
		$params = array();
		$params['q'] = $q;
		$params['count'] = $count;
		return $this->oauth->get( 'search/suggestions/users',  $params );
	}


	/**
	 * 搜索微博时的联想搜索建议 //The suggestions/recommendations given when users are searching weibos
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/search/suggestions/statuses search/suggestions/statuses}
	 *
	 * @param string $q 搜索的关键字，必须做URLencoding。必填 //keywords, must do URLencoding
	 * @param int $count 返回的记录条数，默认为10。// the number of records, default = 10
	 * @return array
	 */
	function search_statuses( $q,  $count = 10)
	{
		$params = array();
		$params['q'] = $q;
		$params['count'] = $count;
		return $this->oauth->get( 'search/suggestions/statuses', $params );
	}


	/**
	 * 搜索学校时的联想搜索建议 //The suggestions/recommendations given when users are searching schools
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/search/suggestions/schools search/suggestions/schools}
	 *
	 * @param string $q 搜索的关键字，必须做URLencoding。必填 //keywords, must do URLencoding, required
	 * @param int $count 返回的记录条数，默认为10。// the number of records, default = 10
	 * @param int type 学校类型，0：全部、1：大学、2：高中、3：中专技校、4：初中、5：小学，默认为0。选填 // school type, 0:all, 1: universty, 2:high school, 3:Secondary technical school 4: middle school 5:primary school. default = 0
	 * @return array
	 */
	function search_schools( $q,  $count = 10,  $type = 1)
	{
		$params = array();
		$params['q'] = $q;
		$params['count'] = $count;
		$params['type'] = $type;
		return $this->oauth->get( 'search/suggestions/schools', $params );
	}

	/**
	 * 搜索公司时的联想搜索建议 // The suggestions/recommendations given when users are searching companies
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/search/suggestions/companies search/suggestions/companies}
	 *
	 * @param string $q 搜索的关键字，必须做URLencoding。必填 //keyword, required
	 * @param int $count 返回的记录条数，默认为10。// the number of records, default = 10
	 * @return array
	 */
	function search_companies( $q, $count = 10)
	{
		$params = array();
		$params['q'] = $q;
		$params['count'] = $count;
		return $this->oauth->get( 'search/suggestions/companies', $params );
	}


	/**
	 * ＠用户时的联想建议 // The suggestions/recommendations given when users are searching
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/search/suggestions/at_users search/suggestions/at_users}
	 *
	 * @param string $q 搜索的关键字，必须做URLencoding。必填 // keywords, must do URLencoding. Required.
	 * @param int $count 返回的记录条数，默认为10。// the number of records returned. default = 10
	 * @param int $type 联想类型，0：关注、1：粉丝。必填 // suggestions type: 0: the person the user is following. 1: user's fans. Required.
	 * @param int $range 联想范围，0：只联想关注人、1：只联想关注人的备注、2：全部，默认为2。选填 // suggestion range: 0: only the people the user is following, 1. only the remarks of the people the user is following 2. All. Default = 2
	 * @return array
	 */
	function search_at_users( $q, $count = 10, $type=0, $range = 2)
	{
		$params = array();
		$params['q'] = $q;
		$params['count'] = $count;
		$params['type'] = $type;
		$params['range'] = $range;
		return $this->oauth->get( 'search/suggestions/at_users', $params );
	}





	/**
	 * 搜索与指定的一个或多个条件相匹配的微博 //Search the weibos that matches 1 or more conditions
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/search/statuses search/statuses}
	 *
	 * @param array $query 搜索选项。格式：array('key0'=>'value0', 'key1'=>'value1', ....)。支持的key: // search options. format:... supporing key:
	 *  - q				string	搜索的关键字，必须进行URLencode。// keyword, must do URLencode
	 *  - filter_ori	int		过滤器，是否为原创，0：全部、1：原创、2：转发，默认为0。// filter, whether it is original, 0: all, 1:original, 2: forwarded, default = 0
	 *  - filter_pic	int		过滤器。是否包含图片，0：全部、1：包含、2：不包含，默认为0。 // filter. whether it has pictures. 0: all, 1: it has, 2: it does not have, default = 0
	 *  - fuid			int		搜索的微博作者的用户UID。 // search the user UID of the weibos' editor
	 *  - province		int		搜索的省份范围，省份ID。 // search province range, province ID
	 *  - city			int		搜索的城市范围，城市ID。// search city range, city ID
	 *  - starttime		int		开始时间，Unix时间戳。// start time, Unix timestamp
	 *  - endtime		int		结束时间，Unix时间戳。// finish time, Unix timestamp
	 *  - count			int		单页返回的记录条数，默认为10。// number of records per page, default = 10
	 *  - page			int		返回结果的页码，默认为1。// the page that returns the result, default = 1
	 *  - needcount		boolean	返回结果中是否包含返回记录数，true：返回、false：不返回，默认为false。//Whether the results returned have the number of records. True: Yes, False: No
	 *  - base_app		int		是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。// Whether to acquire the data from current application. 0 is No (all data), 1 is Yes(current application)
	 * needcount参数不同，会导致相应的返回值结构不同 // if the parameters in needcount are different, the relevant return value format willbe different.
	 * 以上参数全部选填 //All the above parameters are optional.
	 * @return array
	 */
	function search_statuses_high( $query )
	{
		return $this->oauth->get( 'search/statuses', $query );
	}



	/**
	 * 通过关键词搜索用户 //search users based on keywords
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/search/users search/users}
	 *
	 * @param array $query 搜索选项。格式：array('key0'=>'value0', 'key1'=>'value1', ....)。支持的key: // search option. format:array. Supporting keys:
	 *  - q			string	搜索的关键字，必须进行URLencode。// The search keywords
	 *  - snick		int		搜索范围是否包含昵称，0：不包含、1：包含。// whether the search range includes nickname, 0:No, 1: Yes
	 *  - sdomain	int		搜索范围是否包含个性域名，0：不包含、1：包含。//whether the search range includes Personalized Domain Name, 0:No, 1: Yes
	 *  - sintro	int		搜索范围是否包含简介，0：不包含、1：包含。// whether the search range includes brief description, 0:No, 1: Yes
	 *  - stag		int		搜索范围是否包含标签，0：不包含、1：包含。// whether the search range includes labels, 0:No, 1: Yes
	 *  - province	int		搜索的省份范围，省份ID。// the search range of the provinces
	 *  - city		int		搜索的城市范围，城市ID。// the search range of the cities
	 *  - gender	string	搜索的性别范围，m：男、f：女。// the search range of the genders
	 *  - comorsch	string	搜索的公司学校名称。// the name of the company or school being searched
	 *  - sort		int		排序方式，1：按更新时间、2：按粉丝数，默认为1。// The way of sorting 1: as updating time 2: as number of fans. Default = 1
	 *  - count		int		单页返回的记录条数，默认为10。// The number of records returned each page. default = 10
	 *  - page		int		返回结果的页码，默认为1。// The page that returns the result, default = 1
	 *  - base_app	int		是否只获取当前应用的数据。0为否（所有数据），1为是（仅当前应用），默认为0。// Whether to acquire the data from current application. 0 is No (all data), 1 is Yes(current application)
	 * 以上所有参数全部选填 //All the above parameters are optional.
	 * @return array
	 */
	function search_users_keywords( $query )
	{
		return $this->oauth->get( 'search/users', $query );
	}



	/**
	 * 获取系统推荐用户 // Get the users recommended by system
	 *
	 * 返回系统推荐的用户列表。// Get the user list recommended by system
	 * <br />对应API：{@link http://open.weibo.com/wiki/2/suggestions/users/hot suggestions/users/hot}
	 *
	 * @access public
	 * @param string $category 分类，可选参数，返回某一类别的推荐用户，默认为 default。如果不在以下分类中，返回空列表：<br /> // category, optional parameters, return the recommended users belonging to certain category. If it is not in the category, return null list.
	 *  - default:人气关注 //popular concern
	 *  - ent:影视名星 // movie star
	 *  - hk_famous:港台名人 // Hongkong celebrity
	 *  - model:模特
	 *  - cooking:美食&健康
	 *  - sport:体育名人
	 *  - finance:商界名人
	 *  - tech:IT互联网 //IT internet
	 *  - singer:歌手
	 *  - writer：作家
	 *  - moderator:主持人
	 *  - medium:媒体总编
	 *  - stockplayer:炒股高手
	 * @return array
	 */
	function hot_users( $category = "default" )
	{
		$params = array();
		$params['category'] = $category;

		return $this->oauth->get( 'suggestions/users/hot', $params );
	}

	/**
	 * 获取用户可能感兴趣的人 //get people who my account might be interested in.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/suggestions/users/may_interested suggestions/users/may_interested}
	 *
	 * @access public
	 * @param int $page 返回结果的页码，默认为1。//return the result page, default = 1
	 * @param int $count 单页返回的记录条数，默认为10。// return numbers of records each page, default = 10
	 * @return array
	 * @ignore
	 */
	function suggestions_may_interested( $page = 1, $count = 10 )
	{
		$params = array();
		$params['page'] = $page;
		$params['count'] = $count;
		return $this->oauth->get( 'suggestions/users/may_interested', $params);
	}

	/**
	 * 根据一段微博正文推荐相关微博用户。 //Suggest relevant weibo users according to the weibo text
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/suggestions/users/by_status suggestions/users/by_status}
	 *
	 * @access public
	 * @param string $content 微博正文内容。 // weibo text content
	 * @param int $num 返回结果数目，默认为10。// return the number of results, default = 10
	 * @return array
	 */
	function suggestions_users_by_status( $content, $num = 10 )
	{
		$params = array();
		$params['content'] = $content;
		$params['num'] = $num;
		return $this->oauth->get( 'suggestions/users/by_status', $params);
	}

	/**
	 * 热门收藏 // popular favorites
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/suggestions/favorites/hot suggestions/favorites/hot} //correspodent API
	 *
	 * @param int $count 每页返回结果数，默认20。选填 // The number of results one page returns, default = 20, optional.
	 * @param int $page 返回页码，默认1。选填 // returns the number of pages, default = 1, optional.
	 * @return array
	 */
	function hot_favorites( $page = 1, $count = 20 )
	{
		$params = array();
		$params['count'] = $count;
		$params['page'] = $page;
		return $this->oauth->get( 'suggestions/favorites/hot', $params);
	}

	/**
	 * 把某人标识为不感兴趣的人 //label someone as someone I am not interested in.
	 *
	 * 对应API：{@link http://open.weibo.com/wiki/2/suggestions/users/not_interested suggestions/users/not_interested} // correspodent API
	 *
	 * @param int $uid 不感兴趣的用户的UID。 // The UID of the account that I am not interested
	 * @return array
	 */
	function put_users_not_interested( $uid )
	{
		$params = array();
		$params['uid'] = $uid;
		return $this->oauth->post( 'suggestions/users/not_interested', $params);
	}



	// =========================================

	/**
	 * @ignore
	 */
	protected function request_with_pager( $url, $page = false, $count = false, $params = array() )
	{
		if( $page ) $params['page'] = $page;
		if( $count ) $params['count'] = $count;

		return $this->oauth->get($url, $params );
	}

	/**
	 * @ignore
	 */
	protected function request_with_uid( $url, $uid_or_name, $page = false, $count = false, $cursor = false, $post = false, $params = array())
	{
		if( $page ) $params['page'] = $page;
		if( $count ) $params['count'] = $count;
		if( $cursor )$params['cursor'] =  $cursor;

		if( $post ) $method = 'post';
		else $method = 'get';

		if ( $uid_or_name !== NULL ) {
			$this->id_format($uid_or_name);
			$params['id'] = $uid_or_name;
		}

		return $this->oauth->$method($url, $params );

	}

	/**
	 * @ignore
	 */
	protected function id_format(&$id) {
		if ( is_float($id) ) {
			$id = number_format($id, 0, '', '');
		} elseif ( is_string($id) ) {
			$id = trim($id);
		}
	}

}
