/**
 * Chat module
 *
 * @module lib/chat
 */

/*global define*/

define(['jquery', 'module'], function ($, module) {
    'use strict';

    /**
     * ChatManager object
     *
     * @constructor
     * @alias       module:lib/chat
     * @param       {Message}   Message   A Message object to output message in the IHM
     * @param       {WebSocket} WebSocket The websocket manager
     * @param       {User}      User      The current User
     * @param       {object}    settings  Overriden settings
     */
    var ChatManager = function (Message, WebSocket, User, settings) {
        this.settings  = $.extend(true, {}, this.settings, settings);
        this.message   = Message;
        this.websocket = WebSocket;
        this.user      = User;
        this.initEvents();

        // Add websocket callbacks
        this.websocket.addCallback(this.settings.serviceName, this.connectCallback, this);
    };

    ChatManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "users"       : [],
            "serviceName" : module.config().serviceName,
            "divId"       : module.config().divId,
            "pseudonymId" : module.config().pseudonymId,
            "connectClass": module.config().connectClass
        },
        /**
         * A Message object to output message in the IHM
         */
        "message": {},
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

        initEvents: function () {
            $('body').on(
                'click',
                this.settings.divId + ' ' + this.settings.connectClass,
                $.proxy(this.connectEvent, this)
            );  
        },

        connectEvent: function () {
            this.connect($(this.settings.pseudonymId).val());
        },

        /**
         * Connect the user to the chat
         *
         * @param {string} pseudonym The user pseudonym
         */
        connect: function (pseudonym) {
            if (this.user.connected) {
                this.connectRegistered();
            } else {
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
                "user"   : this.user.settings
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
        },

        /**
         * Handle the WebSocker server response after a connection attempt
         *
         * @param {object} data The server JSON reponse
         */
        connectCallback: function (data) {
            this.message.add(data.text);
        }
    };

    return ChatManager;
});