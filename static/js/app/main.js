define(
    ['jquery', 'forms', 'websocket', 'user', 'chat'],
    function($, FormsManager, WebsocketManager, User, ChatManager) {
    'use strict';

    var forms     = new FormsManager(),
        user      = new User(forms),
        websocket = new WebsocketManager(user),
        chat      = new ChatManager(websocket, user);

    
    // Make it global to develop
    window.WebsocketManager = websocket;
    window.ChatManager      = chat;
});