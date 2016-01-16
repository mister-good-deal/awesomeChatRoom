(function () {
    'use strict';

    var gulp  = require('gulp'),
        bower = require('gulp-bower');

    gulp.task('bower', function() {
      return bower({cmd: 'update'})
        .pipe(gulp.dest('lib/vendor'));
    });

    gulp.task('default', ['bower']);
}());
