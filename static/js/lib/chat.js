/**
 * Chat module
 *
 * @module lib/chat
 */

define(['jquery', 'module', 'websocket'], function($, module) {
    'use strict';

    /**
     * ChatManager object
     *
     * @constructor
     * @alias       module:lib/chat
     * @param       {WebSocket} WebSocket The websocket manager
     * @param       {User}      User      The current User
     * @param       {object}    settings  Overriden settings
     */
    var ChatManager = function (WebSocket, User, settings) {
        this.settings  = $.extend(true, {}, this.settings, settings);
        this.websocket = WebSocket;
        this.user      = User;
        this.init();
    };

    ChatManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "users"      : [],
            "serviceName": module.config().serviceName
        },
        /**
         * The WebsocketManager instance
         */
        "websocket": {},
        /**
         * The current User instance
         */
        "user": {},

        /**
         * Launch the WebSocket server
         */
        init: function () {
            console.log('init');
        },

        connect: function (pseudonym) {
            if (this.user.connected) {
                this.connectRegistered();
            }  else {
                this.connectGuest(pseudonym);
            }
        },

        connectRegistered: function () {
            this.websocket.send(JSON.stringify({
                "service": [this.settings.serviceName],
                "action" : "connect",
                "user"   : user 
            }));
        },

        connectGuest: function (pseudonym) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "connect",
                "pseudonym": pseudonym 
            }));
                
        }
    };

    return ChatManager;
});