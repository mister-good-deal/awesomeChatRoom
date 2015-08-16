requirejs.config({
    baseUrl: '/static/js/lib',
    paths  : {
        app   : '../app',
        jquery: 'jquery-2.1.4',
    }
});

requirejs(['app/main']);