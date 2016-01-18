(function () {
    'use strict';

    var gulp              = require('gulp'),
        bower             = require('gulp-bower'),
        requirejsOptimize = require('gulp-requirejs-optimize'),
        sourcemaps        = require('gulp-sourcemaps');

    gulp.task('build', function () {
        return gulp.src('app.js')
            .pipe(requirejsOptimize())
            .pipe(gulp.dest('dist'));
    });

    gulp.task('bower', function() {
        return bower({cmd: 'update'});
    });

    gulp.task('default', ['bower']);
}());
