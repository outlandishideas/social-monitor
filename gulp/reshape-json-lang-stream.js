"use strict";

var dot = require('dot-object');
var eventStream = require('event-stream');
var gutil = require('gulp-util');

module.exports = function(opt) {

	opt = opt || {};

	return eventStream.map(function (src, cb) {
		var retVal;

		if (src.isStream()) {
			retVal = cb(new gutil.PluginError('gulp-jslint', 'bad input file ' + src.path));
		} else if (src.isNull()) {
			retVal = cb(null, src);
		} else {
			var filename = src.history[src.history.length-1];
			var rawData = JSON.parse(src.contents.toString('utf8'));
			var reshapedData = rawData.reduce(function (carry, element) {
				carry[element.key] = element.value;
				return carry;
			}, {});
			src.contents = new Buffer(JSON.stringify(dot.object(reshapedData)));

			var lastIndex = filename.lastIndexOf('/');
			if (lastIndex == -1) {
				lastIndex = filename.lastIndexOf('\\');
			}
			gutil.log('reshape-json:', gutil.colors.green('✔ ') + filename.substr(lastIndex+1));

			retVal = cb(null, src);
		}

		return retVal;
	});
};

