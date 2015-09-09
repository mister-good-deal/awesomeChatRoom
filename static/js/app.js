/*global requirejs*/

requirejs.config({
    baseUrl: '/static/js/lib',
    paths: {
        app      : '../app',
        jquery   : 'vendors/jquery-2.1.4',
        lodash   : 'vendors/lodash-3.10.1',
        bootstrap: 'vendors/bootstrap-3.3.5',
        domReady : 'vendors/domReady',
        chat     : 'chat',
        user     : 'user',
        forms    : 'forms',
        message  : 'message'
    },
    config: {
        'websocket': {
            serverUrl  : 'ws://127.0.0.1:5000',
            serviceName: 'websocketService'
        },
        'chat': {
            serviceName      : 'chatService',
            maxUsers         : 15,
            animationTime    : 500,
            'selectors'      : {
                'global': {
                    chat              : '#chat',
                    room              : '.room',
                    roomName          : '.room-name',
                    roomContents      : '.room-contents',
                    roomChat          : '.chat',
                    roomSample        : '#room-sample',
                    roomHeader        : '.header',
                    roomClose         : '.close-room',
                    roomMinimize      : '.minimize',
                    roomFullscreen    : '.fullscreen',
                    roomMessagesUnread: '.messages-unread'
                },
                'roomConnect': {
                    div      : '.connect-room',
                    name     : '.room-name',
                    pseudonym: '.pseudonym',
                    password : '.room-password',
                    connect  : '.connect'
                },
                'roomCreation': {
                    div     : '.create-room',
                    name    : '.room-name',
                    type    : '.room-type',
                    password: '.room-password',
                    maxUsers: '.room-max-users',
                    create  : '.create'
                },
                'roomSend': {
                    div      : '.send-action',
                    message  : '.message',
                    recievers: '.recievers',
                    usersList: '.users-list',
                    send     : '.send'
                },
                "roomAction": {
                    loadHistoric: '.load-historic',
                    kickUser    : '.kick-user',
                    showUsers   : '.users'
                },
                'chat': {
                    message  : '.message',
                    pseudonym: '.pseudonym',
                    date     : '.date',
                    text     : '.text'
                }
            },
            'commands': {
                kick: /^\/kick '([^']*)'? ?(.*)/,
                pm  : /^\/pm '([^']*)' (.*)/
            }
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