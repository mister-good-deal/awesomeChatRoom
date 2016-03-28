(function () {
    'use strict';

    var gulp             = require('gulp'),
        sourcemaps       = require('gulp-sourcemaps'),
        less             = require('gulp-less'),
        bower            = require('gulp-bower'),
        mainBowerFiles   = require('main-bower-files'),
        rename           = require('gulp-rename'),
        jshint           = require('gulp-jshint'),
        jscs             = require('gulp-jscs'),
        phpcs            = require('gulp-phpcs'),
        phpcbf           = require('gulp-phpcbf'),
        stylish          = require('jshint-stylish'),
        jsdoc            = require('gulp-jsdoc3'),
        map              = require('map-stream'),
        watch            = require('gulp-watch'),
        del              = require('del'),
        exec             = require('child_process').exec,
        gulpSequence     = require('gulp-sequence'),
        CleanPlugin      = require('less-plugin-clean-css'),
        AutoprefixPlugin = require('less-plugin-autoprefix'),
        gitInfo          = require('git-rev'),
        jsdocConfig      = require('./jsdocConfig.json'),
        jsSrc            = ['js/lib/*.js', 'js/app.js', 'js/main.js'],
        phpSrc           = ['../php/**/*.php', '!../php/vendor/**/*.*'],
        clean            = new CleanPlugin({
            advanced: true
        }),
        autoprefix = new AutoprefixPlugin({
            browsers: ["last 2 versions"]
        }),
        gitDoc           = function (repo, currentBranch, callback) {
            exec(
                'cd .. ' +
                '&call git add ' + repo + ' ' +
                '&call git stash save "' + repo + '" ' +
                '&call git checkout gh-pages ' +
                '&call git pull origin gh-pages ',
                function (err, output) {
                    console.log(output);
                }
            );

            del('../' + repo);

            exec(
                'cd .. ' +
                '&call git stash apply --quiet' +
                '&call git add ' + repo + ' ' +
                '&call git commit ' + repo + ' -m "update ' + repo + '" ' +
                '&call git stash drop --quiet' +
                '&call git checkout ' + currentBranch + ' ' +
                '&call git stash apply --quiet ' +
                '&call git stash drop --quiet',
                function (err, output) {
                    console.log(output);
                }
            );

            callback();
        },
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
        gulpSequence(['flush_bower', 'flush_npm', 'flush_js', 'flush_less', 'flush_dist'], done);
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

    gulp.task('bower_move_fonts', ['bower_install'], function () {
        return gulp.src('.bowerDependencies/bootstrap/fonts/**').pipe(gulp.dest('fonts'));
    });

    gulp.task('bower_clean', ['bower_move_js', 'bower_move_less', 'bower_move_fonts'], function () {
        del(['js/lib/vendor/*', '!js/lib/vendor/*.js']);
        del(['less/vendor/*', '!less/vendor/*.less', '!less/vendor/mixins']);
    });

    gulp.task('install', function (done) {
        gulpSequence('bower_install', ['bower_move_js', 'bower_move_less', 'bower_move_fonts'], 'bower_clean', done);
    });

    /*=====  End of Import vendor sources  ======*/

    /*====================================================
    =            Build js / less and optimize            =
    ====================================================*/

    gulp.task('build_js', function (done) {
        exec(
            'cd ./js&node ../node_modules/requirejs/bin/r.js -o app.build.js',
            function (err, output) {
                console.log(output);
                done(err);
            }
        );
    });

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
        gulpSequence(['build_js', 'build_less'], done);
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
        return gulp.src(jsSrc)
            .pipe(jscs({fix: true}))
            .pipe(jscs.reporter())
            .pipe(gulp.dest(function (file) {
                return file.base;
            }));
    });

    gulp.task('js_jshint', function () {
        return gulp.src(jsSrc)
            .pipe(jshint())
            .pipe(jshint.reporter(stylish))
            .pipe(jshintReporter);
    });

    gulp.task('php_phpcs', function () {
        return gulp.src(phpSrc)
            .pipe(phpcs({
                bin            : process.cwd().replace('static', 'php') + '\\vendor\\bin\\phpcs.bat',
                standard       : 'PSR2',
                warningSeverity: 0
            }))
            .pipe(phpcs.reporter('log'));
    });

    gulp.task('php_phpcbf', function () {
        return gulp.src(phpSrc)
            .pipe(phpcbf({
                bin            : process.cwd().replace('static', 'php') + '\\vendor\\bin\\phpcbf.bat',
                standard       : 'PSR2',
                warningSeverity: 0
            }))
            .pipe(gulp.dest(function (file) {
                return file.base;
            }));
    });

    gulp.task('js_lint', function (done) {
        gulpSequence('js_jscs', 'js_jshint', done);
    });

    gulp.task('php_lint', function (done) {
        gulpSequence('php_phpcbf', 'php_phpcs', done);
    });

    /*=====  End of Linters  ======*/

    /*================================================
    =            Documentation generation            =
    ================================================*/

    gulp.task('git_stash', function (done) {
        exec(
            'cd .. ' +
            '&call git stash save "WIP" ',
            function (err, output) {
                console.log(output);
                done(err);
            }
        );
    });

    gulp.task('jsdoc_generation', function (cb) {
        gulp.src(['../README.md', './js/**/*.js'], {read: false}).pipe(jsdoc(jsdocConfig, cb));
    });

    gulp.task('phpdoc_generation', function (done) {
        exec(
            'cd ../php ' +
            '&"./vendor/bin/phpdoc"',
            function (err, output) {
                console.log(output);
                done(err);
            }
        );
    });

    gulp.task('git_js_doc', function (done) {
        gitInfo.branch(function (currentBranch) {
            gitDoc('jsDoc', currentBranch, done);
        });
    });

    gulp.task('git_php_doc', function (done) {
        gitInfo.branch(function (currentBranch) {
            gitDoc('phpDoc', currentBranch, done);
        });
    });

    gulp.task('jsdoc', function (done) {
        gulpSequence('git_stash', 'jsdoc_generation', 'git_js_doc', done);
    });

    gulp.task('phpdoc', function (done) {
        gulpSequence('git_stash', 'phpdoc_generation', 'git_php_doc', done);
    });

    gulp.task('push_doc', function (done) {
        gitInfo.branch(function (currentBranch) {
            exec(
                'git stash save "WIP on ' + currentBranch + '" ' +
                '&call git checkout gh-pages ' +
                '&call git pull origin gh-pages ' +
                '&call git push origin gh-pages ' +
                '&call git checkout ' + currentBranch + ' ' +
                '&call git stash pop --quiet',
                function (err, output) {
                    console.log(output);
                    done(err);
                }
            );
        });
    });

    gulp.task('doc', function (done) {
        gulpSequence('jsdoc', 'phpdoc', done);
    });

    /*=====  End of Documentation generation  ======*/

    /*=================================================
    =            Deployment preprocessing            =
    =================================================*/

    gulp.task('deploy_static', function (done) {
        gulpSequence('install', 'js_lint', 'build', 'jsdoc', 'push_doc', done);
    });

    gulp.task('deploy_php', function (done) {
        gulpSequence('php_lint', 'phpdoc', 'push_doc', done);
    });

    gulp.task('deploy', function (done) {
        gulpSequence('deploy_static', 'deploy_php', done);
    });

    /*=====  End of Deployment preprocessing  ======*/

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
