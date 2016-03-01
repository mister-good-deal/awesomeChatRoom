require(
    ['forms', 'websocket', 'user', 'chat', 'message', 'bootstrap'],
    function (FormsManager, WebsocketManager, User, ChatManager, Message) {
        'use strict';

        var forms          = new FormsManager(),
            messageManager = new Message(),
            user           = new User(forms),
            websocket      = new WebsocketManager(user),
            chat           = new ChatManager(websocket, user, forms);
        // Bind WebSocket server callbacks
        websocket.addCallback(
            messageManager.settings.serviceName, messageManager.parseWebsocketData, messageManager
        );

        user.connectSuccessCallback = function () {
            websocket.send(JSON.stringify({"action": "register", "user": this.settings.attributes}));
        };
        // Make it global to develop
        window.WebsocketManager = websocket;
        window.ChatManager      = chat;
    }
);
