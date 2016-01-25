(function () {
    'use strict';

    var gulp             = require('gulp'),
        sourcemaps       = require('gulp-sourcemaps'),
        less             = require('gulp-less'),
        bower            = require('gulp-bower'),
        mainBowerFiles   = require('main-bower-files'),
        rename           = require('gulp-rename'),
        shell            = require('gulp-shell'),
        jshint           = require('gulp-jshint'),
        jscs             = require('gulp-jscs'),
        phpcs            = require('gulp-phpcs'),
        phpcbf           = require('gulp-phpcbf'),
        stylish          = require('jshint-stylish'),
        jsdoc            = require('gulp-jsdoc3'),
        map              = require('map-stream'),
        watch            = require('gulp-watch'),
        del              = require('del'),
        runSequence      = require('run-sequence'),
        CleanPlugin      = require('less-plugin-clean-css'),
        AutoprefixPlugin = require('less-plugin-autoprefix'),
        clean            = new CleanPlugin({
            advanced: true
        }),
        autoprefix = new AutoprefixPlugin({
            browsers: ["last 2 versions"]
        }),
        jshintReporter;

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

    gulp.task('flush', function (done) {
        runSequence(['flush_bower', 'flush_npm', 'flush_js', 'flush_less', 'flush_dist'], done);
    });

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

    gulp.task('install', function (done) {
        runSequence('bower_install', ['bower_move_js', 'bower_move_less'], 'bower_clean', done);
    });

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
            .pipe(sourcemaps.write()) // @todo can't write sourcemap on an external file
            .pipe(rename('style.css'))
            .pipe(gulp.dest('dist'));
    });

    gulp.task('build', function (done) {
        runSequence(['build_js', 'build_less'], done);
    });

    /*=====  End of Build js / less and optimize  ======*/

    /*===============================
    =            Linters            =
    ===============================*/

    jshintReporter = map(function (file, cb) {
        if (!file.jshint.success) {
            console.log('[FAIL] ' + file.path);
        } else {
            console.log('[OK]   ' + file.path);
        }

        cb(null, file);
    });

    gulp.task('js_jscs', function () {
        return gulp.src('js/lib/*.js')
            .pipe(jscs({fix: true}))
            .pipe(jscs.reporter())
            .pipe(gulp.dest('js/lib'));
    });

    gulp.task('js_jshint', function () {
        return gulp.src(['js/lib/*.js', 'js/main.js'])
            .pipe(jshint())
            .pipe(jshint.reporter(stylish))
            .pipe(jshintReporter);
    });

    gulp.task('php_phpcs', function () {
        return gulp.src(['../php/**/*.php', '!../php/vendor/**/*.*'])
            .pipe(phpcs({
                bin            : process.cwd().replace('static', 'php') + '\\vendor\\bin\\phpcs.bat',
                standard       : 'PSR2',
                warningSeverity: 0
            }))
            .pipe(phpcs.reporter('log'));
    });

    gulp.task('php_phpcbf', function () {
        return gulp.src(['../php/**/*.php', '!../php/vendor/**/*.*'])
            .pipe(phpcbf({
                bin            : process.cwd().replace('static', 'php') + '\\vendor\\bin\\phpcbf.bat',
                standard       : 'PSR2',
                warningSeverity: 0
            }))
            .pipe(gulp.dest('../php'));
    });

    gulp.task('js_lint', function (done) {
        runSequence('js_jscs', 'js_jshint', done);
    });

    gulp.task('php_lint', function (done) {
        runSequence('php_phpcbf', 'php_phpcs', done);
    });

    /*=====  End of Linters  ======*/

    /*================================================
    =            Documentation generation            =
    ================================================*/

    gulp.task('jsdoc', function (cb) {
        var config = require('./jsdocConfig.json');

        gulp.src(['../README.md', './js/**/*.js'], {read: false})
            .pipe(jsdoc(config, cb));
    });

    gulp.task('phpdoc', shell.task('cd ../php&"./vendor/bin/phpdoc"'));

    gulp.task('push_phpdoc', shell.task(
        'cd ../../web-doc&call git add phpDoc&call git commit phpDoc -m "update phpDoc"&call git push origin gh-pages'
    ));

    gulp.task('push_jsdoc', shell.task(
        'cd ../../web-doc&call git add jsDoc&call git commit jsDoc -m "update jsDoc"&call git push origin gh-pages'
    ));

    gulp.task('doc', function (done) {
        runSequence('jsdoc', 'phpdoc', done);
    });

    gulp.task('push_doc', shell.task(
        'cd ../../web-doc&call git add .&call git commit -a -m "update docs"&call git push origin gh-pages'
    ));

    /*=====  End of Documentation generation  ======*/

    /*========================================
    =            Watch less files            =
    ========================================*/

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

    /*=====  End of Watch less files  ======*/

    gulp.task('default', ['install']);
}());
