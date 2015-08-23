define(['jquery', 'websocket', 'user', 'chat'], function($, WebsocketManager, User, ChatManager) {
    'use strict';

    var websocket = new WebsocketManager(),
        user      = new User(),
        chat      = new ChatManager(websocket, user);

    
    // Make it global to develop
    window.WebsocketManager = websocket;
    window.ChatManager      = chat;
});