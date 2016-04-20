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
        CleanPlugin      = require('less-plugin-clean-css'),
        AutoprefixPlugin = require('less-plugin-autoprefix'),
        jsdocConfig      = require('./jsdocConfig.json'),
        docPath          = '../../ziperrom1.github.io/awesomechatroom-doc',
        jsSrc            = ['js/lib/*.js', 'js/app.js', 'js/main.js'],
        phpSrc           = ['../php/**/*.php', '!../php/vendor/**/*.*'],
        clean            = new CleanPlugin({
            advanced: true
        }),
        autoprefix = new AutoprefixPlugin({
            browsers: ["last 2 versions"]
        }),
        jshintReporter;

    /**
     * Add and commit git documentation changes on phpDoc or jsDoc
     *
     * @method     gitDoc
     * @param      {String}    directory  The directory name ("phpDoc" or "jsDoc")
     * @param      {Function}  callback   A callback function to monitor the end of the command
     */
    function gitDoc(directory, callback) {
        exec(
            'cd ' + docPath + ' ' +
            '&call git add ' + directory + ' ' +
            '&call git commit ' + directory + ' -m "update ' + directory + '"',
            function (err, output) {
                console.log(output);
                callback(err);
            }
        );
    }

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

    gulp.task('flush', gulp.parallel('flush_bower', 'flush_npm', 'flush_js', 'flush_less', 'flush_dist'));

    /*=====  End of Flush vendor sources  ======*/

    /*=============================================
    =            Import vendor sources            =
    =============================================*/

    gulp.task('bower_install', function () {
        return bower({ cmd: 'update'});
    });

    gulp.task('bower_move_js', function () {
        return gulp.src(mainBowerFiles()).pipe(gulp.dest('js/lib/vendor'));
    });

    gulp.task('bower_move_less', function () {
        // Import less vendor src and exclude .*variables.less pattern
        gulp.src(mainBowerFiles({"filter": /^(?!.*variables\.less$).*/})).pipe(gulp.dest('less/vendor'));
        // Import less vendor src that have .*variables.less pattern and rename it
        gulp.src('.bowerDependencies/jasny-bootstrap/less/variables.less')
        .pipe(rename('jasny-bootstrap-variables.less'))
        .pipe(gulp.dest('less/vendor'));

        return gulp.src([
            '.bowerDependencies/bootstrap/less/**',
            '!.bowerDependencies/bootstrap/less/variables.less'
        ]).pipe(gulp.dest('less/vendor'));
    });

    gulp.task('bower_move_fonts', function () {
        return gulp.src('.bowerDependencies/bootstrap/fonts/**').pipe(gulp.dest('fonts'));
    });

    gulp.task('bower_clean', gulp.series('bower_move_js', 'bower_move_less', 'bower_move_fonts'), function () {
        del(['js/lib/vendor/*', '!js/lib/vendor/*.js']);
        del(['less/vendor/*', '!less/vendor/*.less', '!less/vendor/mixins']);
    });

    gulp.task('install', gulp.series(
        'bower_install',
        gulp.parallel('bower_move_js', 'bower_move_less', 'bower_move_fonts'),
        'bower_clean'
    ));

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

    gulp.task('build', gulp.parallel('build_js', 'build_less'));

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

    gulp.task('js_lint', gulp.parallel('js_jscs', 'js_jshint'));

    gulp.task('php_lint', gulp.parallel('php_phpcbf', 'php_phpcs'));

    /*=====  End of Linters  ======*/

    /*================================================
    =            Documentation generation            =
    ================================================*/

    gulp.task('jsdoc_generation', function (cb) {
        gulp.src(['../README.md', './js/**/*.js'], {read: false}).pipe(jsdoc(jsdocConfig, cb));
    });

    gulp.task('phpdoc_generation', function (done) {
        exec(
            'cd ../php ' +
            '&"./vendor/bin/phpdoc" --quiet',
            function (err, output) {
                console.log(output);
                done(err);
            }
        );
    });

    gulp.task('git_js_doc', function (done) {
        gitDoc('jsDoc', done);
    });

    gulp.task('git_php_doc', function (done) {
        gitDoc('phpDoc', done);
    });

    gulp.task('jsDoc', gulp.series('jsdoc_generation', 'git_js_doc'));

    gulp.task('phpDoc', gulp.series('phpdoc_generation', 'git_php_doc'));

    gulp.task('push_doc', function (done) {
        exec(
            'cd ' + docPath + ' ' +
            '&call git pull origin master ' +
            '&call git push origin master',
            function (err, output) {
                console.log(output);
                done(err);
            }
        );
    });

    gulp.task('doc', gulp.series('jsDoc', 'phpDoc'));

    /*=====  End of Documentation generation  ======*/

    /*=================================================
    =            Deployment preprocessing            =
    =================================================*/

    gulp.task('deploy_static', gulp.series('install', 'js_lint', 'build', 'jsDoc', 'push_doc'));

    gulp.task('deploy_php', gulp.series('php_lint', 'phpDoc', 'push_doc'));

    gulp.task('deploy', gulp.series('deploy_static', 'deploy_php'));

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

    gulp.task('default', gulp.series('install'));
}());
