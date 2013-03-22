<?php
class Model_TwitterStatusCodes {
	const OK = 200; // Success!
	const NOT_MODIFIED = 304; // There was no new data to return.
	const BAD_REQUEST = 400; // The request was invalid. An accompanying error message will explain why. This is the status code will be returned during version 1.0 rate limiting. In API v1.1, a request without authentication is considered invalid and you will get this response.
	const UNAUTHORIZED = 401; // Authentication credentials were missing or incorrect.
	const FORBIDDEN = 403; // The request is understood, but it has been refused or access is not allowed. An accompanying error message will explain why. This code is used when requests are being denied due to update limits.
	const NOT_FOUND = 404; // The URI requested is invalid or the resource requested, such as a user, does not exists. Also returned when the requested format is not supported by the requested method.
	const NOT_ACCEPTABLE = 406; // Returned by the Search API when an invalid format is specified in the request.
	const ENHANCE_YOUR_CALM = 420; // Returned by the version 1 Search and Trends APIs when you are being rate limited.
	const UNPROCESSABLE_ENTITY = 422; // Returned when an image uploaded to POST account/update_profile_banner is unable to be processed.
	const TOO_MANY_REQUESTS = 429; // Returned in API v1.1 when a request cannot be served due to the application's rate limit having been exhausted for the resource. See Rate Limiting in API v1.1.
	const INTERNAL_SERVER_ERROR = 500; // Something is broken. Please post to the group so the Twitter team can investigate.
	const BAD_GATEWAY = 502; // Twitter is down or being upgraded.
	const SERVICE_UNAVAILABLE = 503; // The Twitter servers are up, but overloaded with requests. Try again later.
	const GATEWAY_TIMEOUT = 504; //The Twitter servers are up, but the request couldn't be serviced due to some failure within our stack. Try again later.
}