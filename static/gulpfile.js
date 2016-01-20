(function () {
    'use strict';

    var gulp             = require('gulp'),
        sourcemaps       = require('gulp-sourcemaps'),
        less             = require('gulp-less'),
        bower            = require('gulp-bower'),
        mainBowerFiles   = require('main-bower-files'),
        rename           = require('gulp-rename'),
        shell            = require('gulp-shell'),
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

    gulp.task('bower_move_js', ['bower_install'], function () {
        return gulp.src(mainBowerFiles()).pipe(gulp.dest('js/lib/vendor'));
    });

    gulp.task('bower_move_less', ['bower_install'], function () {
        gulp.src(mainBowerFiles()).pipe(gulp.dest('less/vendor'));

        return gulp.src(
            ['.bowerDependencies/bootstrap/less/**', '!.bowerDependencies/bootstrap/less/variables.less']
        ).pipe(gulp.dest('less/vendor'));
    });

    gulp.task('bower_clean', ['bower_move_js', 'bower_move_less'], function () {
        del(['js/lib/vendor/*', '!js/lib/vendor/*.js']);
        del(['less/vendor/*', '!less/vendor/*.less', '!less/vendor/mixins']);
    });
    
    gulp.task('build', shell.task('cd ./js&node ../node_modules/requirejs/bin/r.js -o app.build.js'));

    gulp.task('compile', function () {
        return gulp.src('less/build.less')
            .pipe(sourcemaps.init())
            .pipe(less({
                plugins: [clean, autoprefix]
            }))
            .pipe(sourcemaps.write())
            .pipe(rename('style.css'))
            .pipe(gulp.dest('dist'));
    });

    gulp.task('default', ['bower_install', 'bower_move_js', 'bower_move_less', 'bower_clean']);
}());
