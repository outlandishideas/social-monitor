"use strict";

var gutil = require('gulp-util');
var fs = require('fs');
var yaml = require('js-yaml');
var _ = require('lodash');
var dot = require('dot-object');
var eventStream = require('event-stream');

/*
Gulp plugin that replaces scss variable values in the filestream with other values, as specified by the options:
- configFile: The yaml file to get replacement values from
- replacements: A set of key-value pairs, where the keys are the scss variable names, and the values are the
dot-notation paths to the replacement values (in the config file)

E.g. if `replacements` is { 'site-logo': 'path.to.replacement.value' }, then if the yaml file looks like this:
path:
	to:
		replacement:
			value: "'my-logo.png'"
Then the $site-logo variable will be assigned the value 'my-logo.png'
 */
module.exports = function(opt) {

	opt = opt || {};

	if (!opt.configFile) {
		throw new gutil.PluginError('Missing configFile property');
	}
	if (!opt.replacements) {
		throw new gutil.PluginError('Missing replacements property');
	}

	var config = yaml.safeLoad(fs.readFileSync(opt.configFile, "utf8"));

	var replacementValues = [];
	_.each(opt.replacements, function(path, key) {
		var newValue = dot.pick(path, config);
		if (typeof newValue != 'undefined') {
			replacementValues.push({
				key: key,
				value: newValue
			});
		}
	});
	return eventStream.map(function(src, cb) {
		var retVal;

		if (src.isStream()) {
			retVal = cb(new gutil.PluginError('gulp-jslint', 'bad input file ' + src.path));
		} else if (src.isNull()) {
			retVal = cb(null, src);
		} else {
			var fileContents = src.contents.toString('utf8');
			replacementValues.forEach(function(replacement) {
				var regex = new RegExp(replacement.key + ':.*', 'm');
				var newValue = replacement.key + ': ' + replacement.value + ';';
				console.log(regex + ' -> ' + newValue);
				fileContents = fileContents.replace(regex, newValue);
			});
			src.contents = new Buffer(fileContents);

			retVal = cb(null, src);
		}

		return retVal;
	})
};