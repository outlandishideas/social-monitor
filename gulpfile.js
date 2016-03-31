"use strict";

var gulp = require('gulp');
var loadPlugins = require('gulp-load-plugins');
var injectVariables = require('./gulp/inject-variables');
var plugins = loadPlugins();

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

gulp.task('build', ['app:styles']);
gulp.task('watch', ['watch:app:styles']);
gulp.task('default', ['build'], function() {
	gulp.start('watch');
});

