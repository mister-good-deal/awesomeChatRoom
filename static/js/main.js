require(
    ['jquery', 'forms', 'websocket', 'user', 'chat', 'message', 'iframe', 'bootstrap', 'jasny-bootstrap', 'domReady!'],
    function ($, FormsManager, WebsocketManager, User, ChatManager, Message, Iframe) {
        'use strict';

        var forms          = new FormsManager(),
            messageManager = new Message(),
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

        new Iframe();
    }
);
