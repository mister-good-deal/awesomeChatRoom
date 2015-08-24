/**
 * Chat module
 *
 * @module lib/chat
 */

define(['jquery', 'module'], function($, module) {
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
         * If the service is currently running on the server
         */
        "serviceRunning": false,

        /**
         * Connect the user to the chat
         *
         * @param {string} pseudonym The user pseudonym
         */
        connect: function (pseudonym) {
            if (this.user.connected) {
                this.connectRegistered();
            }  else {
                this.connectGuest(pseudonym);
            }
        },

        /**
         * Connect a user to the chat with his account
         */
        connectRegistered: function () {
            this.websocket.send(JSON.stringify({
                "service": [this.settings.serviceName],
                "action" : "connect",
                "user"   : user 
            }));
        },

        /**
         * Connect a user to the chat as a guest
         *
         * @param {string} pseudonym The user pseudonym
         */
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