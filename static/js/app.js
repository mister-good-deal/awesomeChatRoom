requirejs.config({
    baseUrl: '/static/js/lib',
    paths  : {
        app      : '../app',
        jquery   : 'jquery-2.1.4',
        websocket: 'websocket',
        chat     : 'chat',
        user     : 'user'
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