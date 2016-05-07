
require([
    'jquery',
    'lodash',
    'forms',
    'websocketManager',
    'userManager',
    'clientManager',
    'roomManager',
    'chat',
    'message',
    'iframe',
    'navigation',
    'bootstrap',
    'jasny-bootstrap',
    'domReady!'
], function ($, _, FormsManager, WebsocketManager, UserManager, ClientManager, RoomManager, ChatManager, Message, Iframe, Navigation) {
        'use strict';

        var forms          = new FormsManager(),
            messageManager = new Message(),
            navigation     = new Navigation(),
            kibanaIframe   = new Iframe(navigation),
            userManager    = new UserManager(forms),
            websocket      = new WebsocketManager(userManager.getCurrent()),
            roomManager    = new RoomManager(websocket),
            clientManager  = new ClientManager(websocket, userManager.getCurrent()),
            chatManager    = new ChatManager(websocket, userManager.getCurrent(), forms);
        // Bind WebSocket server callbacks on different services
        websocket.addCallback(messageManager.settings.serviceName, messageManager.parseWebsocketData, messageManager);
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
        roomManager.getAllRooms();
    }
);
