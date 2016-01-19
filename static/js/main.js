require(
    ['jquery', 'message', 'forms', 'websocket', 'user', 'chat', 'bootstrap'],
    function ($, Message, FormsManager, WebsocketManager, User, ChatManager) {
        'use strict';

        var message   = new Message(),
            forms     = new FormsManager(message),
            user      = new User(message, forms),
            websocket = new WebsocketManager(message, user),
            chat      = new ChatManager(message, websocket, user, forms);

        // Bind WebSocket server callbacks
        websocket.addCallback(message.settings.serviceName, message.parseWebsocketData, message);

        // Make it global to develop
        window.WebsocketManager = websocket;
        window.ChatManager      = chat;
    }
);
