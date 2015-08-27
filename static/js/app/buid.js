({
    appDir : '../app',
    baseUrl: '/static/js/lib',
    dir    : '../dist',
    modules: [
        {
            name: '../app/main'
        }
    ],
    optimizeCss        : "none",
    optimize           : "uglify2",
    removeCombined     : true
});