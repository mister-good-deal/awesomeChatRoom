
require([
    'jquery',
    'forms',
    'websocket',
    'user',
    'chat',
    'message',
    'iframe',
    'navigation',
    'bootstrap',
    'jasny-bootstrap',
    'domReady!'
], function ($, FormsManager, WebsocketManager, User, ChatManager, Message, Iframe, Navigation) {
        'use strict';

        var forms          = new FormsManager(),
            messageManager = new Message(),
            navigation     = new Navigation(),
            kibanaIframe   = new Iframe(navigation),
            user           = new User(forms),
            websocket      = new WebsocketManager(user);
        // Bind WebSocket server callbacks
        websocket.addCallback(
            messageManager.settings.serviceName, messageManager.parseWebsocketData, messageManager
        );

        user.connectSuccessCallback = function () {
            websocket.send(JSON.stringify({
                "action" : "register",
                "service": ["server"],
                "user"   : this.attributes
            }));
        };

        new ChatManager(websocket, user, forms);

        // Auto show the menu on page on desktop
        if ($(window).outerWidth() > 768) {
            $('#navbar-menu-left').offcanvas('show');
        }
        // Add navigation specific callbacks
        navigation.addCallback('kibana', kibanaIframe.loadKibanaIframe, kibanaIframe);
        // Load the landing page configured in app.js => config => navigation => landingPage
        navigation.loadLandingPage();
    }
);
