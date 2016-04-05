"use strict";

var gulp = require('gulp');
var loadPlugins = require('gulp-load-plugins');
var injectVariables = require('./gulp/inject-variables');
var plugins = loadPlugins();
var fs = require('fs');
var through = require('through2');
var dot = require('dot-object');

var ReshapeJSONStream = through.obj(function (file) {
	var rawData = JSON.parse(String(file.contents));
	var reshapedData = rawData.reduce(function (carry, element) {
		carry[element.key] = element.value;
		return carry;
	}, {});
	file.contents = new Buffer(JSON.stringify(dot.object(reshapedData)));
	this.push(file);
});

gulp.task('app:styles:preprocess', function() {
	return gulp.src('assets/*.scss')
		.pipe(injectVariables({
			configFile: 'application/configs/config.yaml',
			replacements: {
				'site-logo': 'prod.app.client_logo'
			}
		}))
		.pipe(gulp.dest('assets/build'));
});


gulp.task('app:styles', ['app:styles:preprocess'], function() {
	var errorHandler = plugins.notify.onError("Error: <%= error.message %>");
	return gulp.src('assets/build/social-monitor.scss')
		.pipe(plugins.plumber({errorHandler: errorHandler}))
		.pipe(plugins.sourcemaps.init())
		.pipe(plugins.sass({
			errLogToConsole: true
			// outputStyle: 'compressed'
		}))
		.pipe(plugins.sourcemaps.write('.'))
		.pipe(gulp.dest('public/css'));
	//todo: clean up temp build files?
});

gulp.task('watch:app:styles', function() {
	gulp.watch(['assets/*.scss', ['application/configs/config.yaml']], ['app:styles']);
});

gulp.task('csv2json', function () {
	var stream = gulp.src('languages/*.csv')
		.pipe(plugins.csv2json({delimiter: ';'}))
		.pipe(plugins.rename({extname: '.json'}))
		.pipe(ReshapeJSONStream)
		.pipe(gulp.dest('languages/json'));
});

gulp.task('translate', ['csv2json'], function () {
	var translations = ['en'];
	translations.forEach(function (translation) {
		var source = 'languages/json/lang.' + translation + '.json';
		gulp.src('assets/js/*.js')
			.pipe(
				plugins.translator({
					localePath: source,
					lang: translation
				}).on('error', function () {
					console.error(arguments);
				})
			)
			.pipe(gulp.dest('public/js/' + translation));
	});
});

gulp.task('build', ['app:styles']);
gulp.task('watch', ['watch:app:styles']);
gulp.task('default', ['build'], function() {
	gulp.start('watch');
});

