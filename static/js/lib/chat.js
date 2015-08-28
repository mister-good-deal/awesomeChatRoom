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
        this.websocket.addCallback(this.settings.serviceName, this.chatCallback, this);
    };

    ChatManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "users"            : [],
            "serviceName"      : module.config().serviceName,
            "divId"            : module.config().divId,
            "pseudonymId"      : module.config().pseudonymId,
            "roomNameConnectId": module.config().roomNameConnectId,
            "connectClass"     : module.config().connectClass,
            "roomNameId"       : module.config().roomNameId,
            "createRoomClass"  : module.config().createRoomClass,
            "maxUsers"         : module.config().maxUsers
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

        /**
         * Initialize all the events
         */
        initEvents: function () {
            $('body').on(
                'click',
                this.settings.divId + ' ' + this.settings.connectClass,
                $.proxy(this.connectEvent, this)
            );

            $('body').on(
                'click',
                this.settings.divId + ' ' + this.settings.createRoomClass,
                $.proxy(this.createRoomEvent, this)
            );  
        },

        /**
         * Event fired when a user want to connect to a chat
         */
        connectEvent: function () {
            this.connect($(this.settings.pseudonymId).val(), $(this.settings.roomNameConnectId).val());
        },

        /**
         * Connect the user to the chat
         *
         * @param {string} pseudonym The user pseudonym
         * @param {string} roomName  The room name to connect to
         */
        connect: function (pseudonym, roomName) {
            if (this.user.connected) {
                this.connectRegistered(roomName);
            } else {
                this.connectGuest(pseudonym, roomName);
            }
        },

        /**
         * Connect a user to the chat with his account
         *
         * @param {string} roomName The room name to connect to
         */
        connectRegistered: function (roomName) {
            this.websocket.send(JSON.stringify({
                "service" : [this.settings.serviceName],
                "action"  : "connect",
                "user"    : this.user.settings,
                "roomName": roomName
            }));
        },

        /**
         * Connect a user to the chat as a guest
         *
         * @param {string} pseudonym The user pseudonym
         * @param {string} roomName  The room name to connect to
         */
        connectGuest: function (pseudonym, roomName) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "connect",
                "pseudonym": pseudonym,
                "roomName" : roomName
            }));
        },

        /**
         * Handle the WebSocker server response and process action then
         *
         * @param {object} data The server JSON reponse
         */
        chatCallback: function (data) {
            switch (data.action) {
                case 'connect':
                    this.message.add(data.text);
                    break;

                case 'createRoom':
                    this.message.add(data.text);
                    break;
                
                default:
                    if (data.text) {
                        this.message.add(data.text);
                    }
            }
        },

        createRoom: function (roomName, type, roomPassword, maxUsers) {
            this.websocket.send(JSON.stringify({
                "service"      : [this.settings.serviceName],
                "action"       : "createRoom",
                "login"        : this.user.getEmail(),
                "password"     : this.user.getPassword(),
                "roomName"     : roomName,
                "type"         : type || 'public',
                "roomPassword" : roomPassword || '',
                "maxUsers"     : maxUsers || this.settings.maxUsers
            }));
        },

        createRoomEvent: function () {
            this.createRoom($(this.settings.roomNameId).val());
        }
    };

    return ChatManager;
});