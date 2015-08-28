var requirejs = require('requirejs');

var config = {
    appDir        : '../app',
    baseUrl       : '../lib',
    dir           : '../dist',
    name          : '../app/main',
    optimizeCss   : "none",
    optimize      : "uglify2",
    removeCombined: true,
    wrap          : true
};

requirejs.optimize(config, function (buildResponse) {
    //buildResponse is just a text output of the modules
    //included. Load the built file for the contents.
    //Use config.out to get the optimized file contents.
    var contents = fs.readFileSync(config.out, 'utf8');
    fs.writeFile('optimized.js', contents, 'utf8', function () {
        console.log('File generated');
    });
}, function(err) {
    console.log('Errors', err);
});