
require([
    'jquery',
    'forms',
    'websocket',
    'userManager',
    'roomManager',
    'chat',
    'message',
    'iframe',
    'navigation',
    'bootstrap',
    'jasny-bootstrap',
    'domReady!'
], function ($, FormsManager, WebsocketManager, UserManager, RoomManager, ChatManager, Message, Iframe, Navigation) {
        'use strict';

        var forms          = new FormsManager(),
            messageManager = new Message(),
            navigation     = new Navigation(),
            kibanaIframe   = new Iframe(navigation),
            userManager    = new UserManager(forms),
            websocket      = new WebsocketManager(userManager.user),
            chatManager    = new ChatManager(websocket, userManager.user, forms),
            roomManager    = new RoomManager(websocket);
        // Bind WebSocket server callbacks on different services
        websocket.addCallback(messageManager.settings.serviceName, messageManager.parseWebsocketData, messageManager);
        websocket.addCallback(chatManager.settings.serviceName, chatManager.chatCallbackDispatcher, chatManager);
        websocket.addCallback(roomManager.settings.serviceName, roomManager.roomCallbackDispatcher, roomManager);

        userManager.connectSuccessCallback = function () {
            websocket.send(JSON.stringify({
                "action"  : "connect",
                "service" : ["server"],
                "user"    : this.user.getUser(),
                "location": this.user.getLocation()
            }));
        };
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
