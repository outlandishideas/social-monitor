"use strict";

var gulp = require('gulp');
var loadPlugins = require('gulp-load-plugins');
var plugins = loadPlugins();

gulp.task('app:styles', function() {
	var appStylesheet = 'assets/social-monitor.scss';
	var dest = 'public/css';
	var errorHandler = plugins.notify.onError("Error: <%= error.message %>");
	return gulp.src(appStylesheet)
		.pipe(plugins.plumber({errorHandler: errorHandler}))
		.pipe(plugins.sourcemaps.init())
		.pipe(plugins.sass({
			errLogToConsole: true
			// outputStyle: 'compressed'
		}))
		.pipe(plugins.sourcemaps.write('.'))
		.pipe(gulp.dest(dest));
});

gulp.task('watch:app:styles', function() {
	var scssGlob = 'assets/social-monitor.scss';
	gulp.watch(scssGlob, ['app:styles']);
});

gulp.task('build', ['app:styles']);
gulp.task('watch', ['watch:app:styles']);
gulp.task('default', ['build'], function() {
	gulp.start('watch');
});

