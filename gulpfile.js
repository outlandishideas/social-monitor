"use strict";

var gulp = require('gulp');
var gutil = require('gulp-util');
var loadPlugins = require('gulp-load-plugins');
var injectVariables = require('./gulp/inject-variables');
var reshapeJsonStream = require('./gulp/reshape-json-lang-stream');
var yaml = require('js-yaml');
var fs = require('fs');
var _ = require('lodash');
var dot = require('dot-object');
var path = require('path');
var plugins = loadPlugins();


/**
 * Looks up a key for a color scheme in a config file and returns the path if it exists
 * otherwise the function will return the path to a default color scheme
 * @param configFile
 * @param configPath
 * @return filePath
 */
function loadColors(configFile, configPath) {

	var scssDir = 'assets/scss'; // directory of scss files
	var defaultColors = 'britishcouncil.scss'; // default color scheme
	var defaultPath = path.join(scssDir, defaultColors); // use default file

	var config = yaml.safeLoad(fs.readFileSync(configFile, 'utf8')); // load the specified config file
	var colors = dot.pick(configPath, config);
	
	if(!colors){
		gutil.log(gutil.colors.red('No colorscheme defined'), '-', 'Using default');
		return defaultPath;
	}
	
	var filePath = path.join(scssDir, colors);
	
	try{
        fs.accessSync(filePath, fs.F_OK); // check if the file exists
	}
	catch(err){
		console.log(err);
		filePath = defaultPath;
	}

    return filePath;
}

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
	return gulp.src([
		loadColors('application/configs/config.yaml', 'prod.app.colorscheme'),
		'assets/build/scss/social-monitor.scss'])
		.pipe(plugins.concat('social-monitor.scss'))
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
	gulp.watch(['assets/scss/*.scss', ['application/configs/config.yaml']], ['app:styles']);
});

gulp.task('watch:app:lang', function() {
	gulp.watch(['languages/*.csv', 'assets/js/*.js'], ['app:lang']);
});

gulp.task('app:lang:csv2json', function () {
	return gulp.src('languages/*.csv')
		.pipe(plugins.csv2json({delimiter: ','}))
		.pipe(plugins.rename({extname: '.json'}))
		.pipe(reshapeJsonStream())
		.pipe(gulp.dest('assets/build/lang'));
});

gulp.task('app:lang', ['app:lang:csv2json'], function () {
	var langDir = 'assets/build/lang';
	var files = fs.readdirSync(langDir);
	files.forEach(function (file) {
		var matches = file.match(/lang\.(.{2})\.json/);
		if (matches.length > 1) {
			var lang = matches[1];
			return gulp.src('assets/js/*.js')
				.pipe(
					plugins.transformer({
						path: langDir + '/' + file,
						strictDictionary: false,
						defaultDictionary: langDir + '/lang.en.json',
						pattern: /\{{2}([-_\w\.\s\"\']+\s?\|\s?translate[\w\s\|]*)\}{2}/g // our keys can have hyphens and underscores
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

