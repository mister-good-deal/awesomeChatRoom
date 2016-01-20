(function () {
    'use strict';

    var gulp             = require('gulp'),
        sourcemaps       = require('gulp-sourcemaps'),
        less             = require('gulp-less'),
        bower            = require('gulp-bower'),
        mainBowerFiles   = require('main-bower-files'),
        rename           = require('gulp-rename'),
        shell            = require('gulp-shell'),
        watch            = require('gulp-watch'),
        del              = require('del'),
        CleanPlugin      = require('less-plugin-clean-css'),
        AutoprefixPlugin = require('less-plugin-autoprefix'),
        clean            = new CleanPlugin({
            advanced: true
        }),
        autoprefix = new AutoprefixPlugin({
            browsers: ["last 2 versions"]
        });

    /*============================================
    =            Flush vendor sources            =
    ============================================*/

    gulp.task('flush_bower', function () {
        del('.bowerDependencies');
    });

    gulp.task('flush_npm', function () {
        del('node_modules');
    });

    gulp.task('flush_js', function () {
        del('js/lib/vendor');
    });

    gulp.task('flush_less', function () {
        del(['less/vendor/*', '!less/vendor/variables.less']);
    });

    gulp.task('flush_dist', function () {
        del('dist/*');
    });

    gulp.task('flush_all', ['flush_bower', 'flush_npm', 'flush_js', 'flush_less', 'flush_dist']);

    /*=====  End of Flush vendor sources  ======*/

    /*=============================================
    =            Import vendor sources            =
    =============================================*/

    gulp.task('bower_install', function () {
        return bower({ cmd: 'update'});
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

    gulp.task('install', ['bower_install', 'bower_move_js', 'bower_move_less', 'bower_clean']);

    /*=====  End of Import vendor sources  ======*/

    /*====================================================
    =            Build js / less and optimize            =
    ====================================================*/

    gulp.task('build_js', shell.task('cd ./js&node ../node_modules/requirejs/bin/r.js -o app.build.js'));

    gulp.task('build_less', function () {
        return gulp.src('less/build.less')
            .pipe(sourcemaps.init())
            .pipe(less({
                plugins: [clean, autoprefix]
            }))
            .pipe(sourcemaps.write()) // can't write sourcemap on an external file
            .pipe(rename('style.css'))
            .pipe(gulp.dest('dist'));
    });

    gulp.task('build_all', ['build_js', 'build_less']);

    /*=====  End of Build js / less and optimize  ======*/

    gulp.task('watch', function () {
        watch('less/**/*.less', {read: false}, function (vinyl) {
            var string = vinyl.path;

            switch (vinyl.event) {
                case 'add':
                    string += ' has been created';
                    break;

                case 'change':
                    string += ' has changed';
                    break;

                case 'unlink':
                case 'unlinkDir':
                    string += ' has been removed';
                    break;
            }

            console.log(string);
            gulp.start('build_less');
        });
    });

    gulp.task('default', ['install']);
}());
