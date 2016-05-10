
require([
    'jquery',
    'lodash',
    'formManager',
    'websocketManager',
    'userManager',
    'clientManager',
    'roomManager',
    'chat',
    'notification',
    'iframeManager',
    'navigation',
    'bootstrap',
    'jasny-bootstrap',
    'domReady!'
], function ($, _, Forms, Websocket, UserManager, ClientManager, RoomManager, ChatManager, Notification, Iframe, Navigation) {
        'use strict';

        var forms          = new Forms(),
            notification   = new Notification(),
            navigation     = new Navigation(),
            kibanaIframe   = new Iframe(navigation),
            userManager    = new UserManager(forms),
            websocket      = new Websocket(userManager.getCurrent()),
            clientManager  = new ClientManager(websocket, userManager.getCurrent()),
            roomManager    = new RoomManager(websocket, clientManager.getCurrent()),
            chatManager    = new ChatManager(websocket, userManager.getCurrent(), forms);
        // Bind WebSocket server callbacks on different services
        websocket.addCallback(notification.settings.serviceName, notification.parseWebsocketData, notification);
        websocket.addCallback(chatManager.settings.serviceName, chatManager.wsCallbackDispatcher, chatManager);
        websocket.addCallback(roomManager.settings.serviceName, roomManager.wsCallbackDispatcher, roomManager);
        websocket.addCallback(clientManager.settings.serviceName, clientManager.wsCallbackDispatcher, clientManager);
        // Update the client user object on the WebSocket server after the client connection
        userManager.connectSuccessCallback = _.bind(clientManager.updateUser, clientManager);
        // Auto show the menu on page on desktop
        if ($(window).outerWidth() > 768) {
            $('#navbar-menu-left').offcanvas('show');
        }
        // Add navigation specific callbacks
        navigation.addCallback('kibana', kibanaIframe.loadKibanaIframe, kibanaIframe);
        // Load the landing page configured in app.js => config => navigation => landingPage
        navigation.loadLandingPage();
        // Load all the rooms
        roomManager.getAll();
    }
);
