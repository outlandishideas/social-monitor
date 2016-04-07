"use strict";

var dot = require('dot-object');
var eventStream = require('event-stream');
var gutil = require('gulp-util');
var _ = require('lodash');

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
				// csv2json is crazy and puts the last column first, so put in any plurals first
				var values = [];
				for (var i=1; i<=6; i++) {
					if (element['plural' + i]) {
						values.push(element['plural' + i]);
					}
				}
				values.push(element.value);
				// convert common labels to intervals
				values = values.map(function(value) {
					value = value.trim();
					if (value.substr(0, 5) == 'zero:') {
						value = '{0} ' + value.substr(5);
					} else if (value.substr(0, 4) == 'one:') {
						value = '{1} ' + value.substr(4);
					} else if (value.substr(0, 4) == 'two:') {
						value = '{2} ' + value.substr(4);
					} else if (value.substr(0, 6) == 'three:') {
						value = '{3} ' + value.substr(6);
					} else if (value.substr(0, 5) == 'more:') {
						value = '[2,Inf[ ' + value.substr(5);
					}
					return value;
				});
				carry[element.key] = values.join('|');
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

