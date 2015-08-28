/*global requirejs*/

requirejs.config({
    baseUrl: '/static/js/lib',
    paths: {
        app     : '../app',
        jquery  : 'vendors/jquery-2.1.4',
        domReady: 'vendors/domReady',
        chat    : 'chat',
        user    : 'user',
        forms   : 'forms',
        message : 'message'
    },
    config: {
        'websocket': {
            serverUrl  : 'ws://127.0.0.1:5000',
            serviceName: 'websocketService'
        },
        'chat': {
            serviceName      : 'chatService',
            divId            : '#chat',
            pseudonymId      : '#pseudonym',
            roomNameConnectId: '#room-name-connect',
            connectClass     : '.connect',
            roomNameId       : '#room-name',
            createRoomClass  : '.create-room',
            maxUsers         : 15
        },
        'message': {
            'alert': {
                divId          : '#alert-container',
                dismissClass   : '.dismiss',
                defaultDuration: 2
            },
            'popup': {
                divId          : '#popup-container',
                dismissClass   : '.dismiss',
                defaultDuration: 6
            },
            'notification': {
                divId          : '#notification-container',
                dismissClass   : '.dismiss',
                defaultDuration: 4
            },
            serviceName : 'notificationService',
            defaultType : 'alert',
            defaultLevel: 'info'
        }
    }
});

requirejs(['app/main']);