(function () {
    'use strict';

    var gulp             = require('gulp'),
        sourcemaps       = require('gulp-sourcemaps'),
        less             = require('gulp-less'),
        bower            = require('gulp-bower'),
        mainBowerFiles   = require('main-bower-files'),
        rename           = require('gulp-rename'),
        del              = require('del'),
        CleanPlugin      = require('less-plugin-clean-css'),
        AutoprefixPlugin = require('less-plugin-autoprefix'),
        clean            = new CleanPlugin({
            advanced: true
        }),
        autoprefix = new AutoprefixPlugin({
            browsers: ["last 2 versions"]
        });

    gulp.task('bower_install', function () {
        return bower();
    });

    gulp.task('bower_move', ['bower_install'], function () {
        gulp.src(mainBowerFiles()).pipe(gulp.dest('vendor'));

        return gulp.src(
            ['vendor/bootstrap/less/**', '!vendor/bootstrap/less/variables.less']
        ).pipe(gulp.dest('vendor'));
    });

    gulp.task('bower_clean', ['bower_move'], function () {
        del(['vendor/*', '!vendor/*.less', '!vendor/mixins']);
    });

    gulp.task('compile', function () {
        return gulp.src('build.less')
            .pipe(sourcemaps.init())
            .pipe(less({
                plugins: [clean, autoprefix]
            }))
            .pipe(sourcemaps.write())
            .pipe(rename('style.css'))
            .pipe(gulp.dest('../dist'));
    });

    gulp.task('default', ['bower_install', 'bower_move', 'bower_clean']);
}());
