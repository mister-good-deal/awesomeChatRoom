(function () {
    'use strict';

    var gulp            = require('gulp'),
        bower           = require('gulp-bower'),
        requirejs       = require('requirejs'),
        requirejsConfig = {
            baseUrl       : './lib',
            name          : 'index',
            // optimizeCss   : "none",
            optimize      : "uglify",
            // removeCombined: true,
            // wrap          : true,
            mainConfigFile: './app/main.js',
            out           : './dist/app.js'
        };

    gulp.task('requirejs', function (taskReady) {
        requirejs.optimize(requirejsConfig, function () {
            taskReady();
        }, function (error) {
            console.error('requirejs task failed', JSON.stringify(error));
            process.exit(1);
        });
    });

    gulp.task('bower', function() {
      return bower({cmd: 'update'})
        .pipe(gulp.dest('lib/vendor'));
    });

    gulp.task('default', ['bower']);
}());
