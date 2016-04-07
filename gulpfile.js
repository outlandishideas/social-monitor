"use strict";

var gulp = require('gulp');
var loadPlugins = require('gulp-load-plugins');
var injectVariables = require('./gulp/inject-variables');
var reshapeJsonStream = require('./gulp/reshape-json-lang-stream');
var plugins = loadPlugins();

gulp.task('app:styles:preprocess', function() {
	return gulp.src('assets/scss/*.scss')
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
	return gulp.src('languages/*.csv')
		.pipe(plugins.csv2json({delimiter: ';'}))
		.pipe(plugins.rename({extname: '.json'}))
		.pipe(reshapeJsonStream())
		.pipe(gulp.dest('languages/json'));
});

gulp.task('translate', ['csv2json'], function () {
	// var translations = ['en'];
	// translations.forEach(function (translation) {
	// 	var source = 'languages/json/lang.' + translation + '.json';
	// 	console.log(source);
		return gulp.src('assets/js/*.js')
			.pipe(
				plugins.translator({
					localePath: 'languages/json/lang.' + 'en' + '.json',
					lang: 'en'
				}).on('error', function () {
					console.error(arguments);
				})
			)
			.pipe(gulp.dest('public/js/' + 'en'));
	// });
});

gulp.task('build', ['app:styles']);
gulp.task('watch', ['watch:app:styles']);
gulp.task('default', ['build'], function() {
	gulp.start('watch');
});

