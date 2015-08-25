/*global requirejs*/

requirejs.config({
    baseUrl: '/static/js/lib',
    paths: {
        app     : '../app',
        jquery  : 'vendors/jquery-2.1.4',
        domReady: 'vendors/domReady',
        chat    : 'chat',
        user    : 'user',
        forms   : 'forms'
    },
    config: {
        'websocket': {
            serverUrl: 'ws://127.0.0.1:5000'
        },
        'chat': {
            serviceName: 'chatService'
        }
    }
});

requirejs(['app/main']);