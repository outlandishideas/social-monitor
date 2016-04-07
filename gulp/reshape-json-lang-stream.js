"use strict";

var dot = require('dot-object');
var eventStream = require('event-stream');

module.exports = function(opt) {

	opt = opt || {};

	return eventStream.map(function (src, cb) {
		var retVal;

		if (src.isStream()) {
			retVal = cb(new gutil.PluginError('gulp-jslint', 'bad input file ' + src.path));
		} else if (src.isNull()) {
			retVal = cb(null, src);
		} else {
			var rawData = JSON.parse(src.contents.toString('utf8'));
			var reshapedData = rawData.reduce(function (carry, element) {
				carry[element.key] = element.value;
				return carry;
			}, {});
			src.contents = new Buffer(JSON.stringify(dot.object(reshapedData)));

			retVal = cb(null, src);
		}

		return retVal;
	});
};

