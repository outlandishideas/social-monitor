"use strict";

var gulp = require('gulp');
var loadPlugins = require('gulp-load-plugins');
var injectVariables = require('./gulp/inject-variables');
var reshapeJsonStream = require('./gulp/reshape-json-lang-stream');
var fs = require('fs');
var plugins = loadPlugins();

gulp.task('app:styles:preprocess', function() {
	return gulp.src('assets/scss/*.scss')
		.pipe(injectVariables({
			configFile: 'application/configs/config.yaml',
			replacements: {
				'site-logo': 'prod.app.client_logo'
			}
		}))
		.pipe(gulp.dest('assets/build/scss'));
});


gulp.task('app:styles', ['app:styles:preprocess'], function() {
	var errorHandler = plugins.notify.onError("Error: <%= error.message %>");
	return gulp.src('assets/build/scss/social-monitor.scss')
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

gulp.task('watch:app:lang', function() {
	gulp.watch(['languages/*.csv'], ['app:lang']);
});

gulp.task('app:lang:csv2json', function () {
	return gulp.src('languages/*.csv')
		.pipe(plugins.csv2json({delimiter: ';'}))
		.pipe(plugins.rename({extname: '.json'}))
		.pipe(reshapeJsonStream())
		.pipe(gulp.dest('assets/build/lang'));
});

gulp.task('app:lang', ['app:lang:csv2json'], function () {
	var langDir = 'assets/build/lang';
	var files = fs.readdirSync(langDir);
	// var translations = ['en'];
	files.forEach(function (file) {
		var matches = file.match(/lang\.(.{2})\.json/);
		if (matches.length > 1) {
			var lang = matches[1];
			return gulp.src('assets/js/*.js')
				.pipe(
					plugins.translator({
						localePath: langDir + '/' + file,
						lang: lang
					}).on('end', function() {
						plugins.util.log('app:lang:', plugins.util.colors.green('✔ ') + file);
					}).on('error', function () {
						console.error(arguments);
					})
				)
				.pipe(gulp.dest('public/js/' + lang));
		}
	});
});

gulp.task('build', ['app:styles', 'app:lang']);
gulp.task('watch', ['watch:app:styles', 'watch:app:lang']);
gulp.task('default', ['build'], function() {
	gulp.start('watch');
});

