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
            "maxUsers"         : module.config().maxUsers,
            "selectors"        : {
                "global": {
                    "chat"    : module.config().selectors.global.chat,
                    "room"    : module.config().selectors.global.room,
                    "roomName": module.config().selectors.global.roomName,
                    "roomChat": module.config().selectors.global.roomChat
                },
                "roomConnect": {
                    "div"      : module.config().selectors.roomConnect.div,
                    "name"     : module.config().selectors.roomConnect.name,
                    "pseudonym": module.config().selectors.roomConnect.pseudonym,
                    "password" : module.config().selectors.roomConnect.password,
                    "connect"  : module.config().selectors.roomConnect.connect
                },
                "roomCreation": {
                    "div"     : module.config().selectors.roomCreation.div,
                    "name"    : module.config().selectors.roomCreation.name,
                    "type"    : module.config().selectors.roomCreation.type,
                    "password": module.config().selectors.roomCreation.password,
                    "maxUsers": module.config().selectors.roomCreation.maxUsers,
                    "create"  : module.config().selectors.roomCreation.create
                },
                "roomSend": {
                    "div"      : module.config().selectors.roomSend.div,
                    "message"  : module.config().selectors.roomSend.message,
                    "recievers": module.config().selectors.roomSend.recievers,
                    "send"     : module.config().selectors.roomSend.send
                },
                "chat": {
                    "message"  : module.config().selectors.chat.message,
                    "pseudonym": module.config().selectors.chat.pseudonym,
                    "date"     : module.config().selectors.chat.date,
                    "text"     : module.config().selectors.chat.text
                }
            }
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
            // Connect to a room
            $('body').on(
                'click',
                this.settings.selectors.global.chat + ' ' +
                this.settings.selectors.roomConnect.div + ' ' +
                this.settings.selectors.roomConnect.connect,
                $.proxy(this.connectEvent, this)
            );
            // Create a room
            $('body').on(
                'click',
                this.settings.selectors.global.chat + ' ' +
                this.settings.selectors.roomCreation.div + ' ' +
                this.settings.selectors.roomCreation.create,
                $.proxy(this.createRoomEvent, this)
            );
            // Send a message in a room
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' +
                this.settings.selectors.roomSend.div + ' ' +
                this.settings.selectors.roomSend.send,
                $.proxy(this.sendMessageEvent, this)
            );  
        },

        /**
         * Event fired when a user want to connect to a chat
         */
        connectEvent: function () {
            var connectDiv = $(this.settings.selectors.global.chat + ' ' + this.settings.selectors.roomConnect.div),
                pseudonym  = connectDiv.find(this.settings.selectors.roomConnect.pseudonym).val(),
                roomName   = connectDiv.find(this.settings.selectors.roomConnect.name).val(),
                password   = connectDiv.find(this.settings.selectors.roomConnect.password).val();

            this.connect(pseudonym, roomName, password);
        },

        /**
         * Event fired when a user wants to create a chat room
         */
        createRoomEvent: function () {
            var createDiv = $(this.settings.selectors.global.chat + ' ' + this.settings.selectors.roomCreation.div),
                roomName  = createDiv.find(this.settings.selectors.roomCreation.name).val(),
                type      = createDiv.find(this.settings.selectors.roomCreation.type).val(),
                password  = createDiv.find(this.settings.selectors.roomCreation.password).val(),
                maxUsers  = createDiv.find(this.settings.selectors.roomCreation.maxUsers).val();

            this.createRoom(roomName, type, password, maxUsers);
        },

        /**
         * Event fired when a user wants to send a message
         *
         * @param {event} e The fired event
         */
        sendMessageEvent: function (e) {
            var sendDiv = $(e.currentTarget).closest(
                    this.settings.selectors.global.room + ' ' + this.settings.selectors.roomSend.div
                ),
                recievers = sendDiv.find(this.settings.selectors.roomSend.recievers).val(),
                message   = sendDiv.find(this.settings.selectors.roomSend.message).val(),
                room      = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomName  = room.attr('data-name'),
                password  = room.attr('data-password');

            this.sendMessage(recievers, message, roomName, password);
        },

        /**
         * Handle the WebSocker server response and process action then
         *
         * @param {object} data The server JSON reponse
         */
        chatCallback: function (data) {
            switch (data.action) {
                case 'connect':
                    this.connectRoomCallback(data);

                    break;

                case 'createRoom':
                    this.createRoomCallback(data);

                    break;

                case 'recieveMessage':
                    this.recieveMessageCallback(data);

                    break;

                case 'sendMessage':
                    this.sendMessageCallback(data);

                    break;
                
                default:
                    if (data.text) {
                        this.message.add(data.text);
                    }
            }
        },

        /**
         * Callback after a user attempted to connect to a room
         *
         * @param {object} data The server JSON reponse
         */
        connectRoomCallback: function (data) {
            if (data.success) {
                this.insertRoomInDOM(data);
            }

            this.message.add(data.text);
        },

        /**
         * Callback after a user attempted to create a room
         *
         * @param {object} data The server JSON reponse
         */
        createRoomCallback: function (data) {
            if (data.success) {
                this.insertRoomInDOM(data);
            }

            this.message.add(data.text);
        },

        /**
         * Callback after a user recieved a message
         *
         * @param {object} data The server JSON reponse
         */
        recieveMessageCallback: function (data) {
            var room = $(this.settings.selectors.global.room + '[data-name="' + data.roomName + '"]');

            room.find(this.settings.selectors.global.roomChat).append(this.formatUserMessage(data));
        },

        /**
         * Callback after a user sent a message
         *
         * @param {object} data The server JSON reponse
         */
        sendMessageCallback: function (data) {
            console.log('Message sent', data);
        },

        /**
         * Connect the user to the chat
         *
         * @param {string} pseudonym The user pseudonym
         * @param {string} roomName  The room name to connect to
         * @param {string} password  The room password to connect to
         */
        connect: function (pseudonym, roomName, password) {
            if (this.user.connected) {
                this.connectRegistered(roomName, password);
            } else {
                this.connectGuest(pseudonym, roomName, password);
            }
        },

        /**
         * Connect a user to the chat with his account
         *
         * @param {string} roomName The room name to connect to
         * @param {string} password The room password to connect to
         */
        connectRegistered: function (roomName, password) {
            this.websocket.send(JSON.stringify({
                "service" : [this.settings.serviceName],
                "action"  : "connect",
                "user"    : this.user.settings,
                "roomName": roomName,
                "password": password
            }));
        },

        /**
         * Connect a user to the chat as a guest
         *
         * @param {string} pseudonym The user pseudonym
         * @param {string} roomName  The room name to connect to
         * @param {string} password  The room password to connect to
         */
        connectGuest: function (pseudonym, roomName, password) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "connect",
                "pseudonym": pseudonym,
                "roomName" : roomName,
                "password" : password
            }));
        },

        /**
         * Send a message to all the users in the chat room or at one user in teh chat room
         *
         * @param {string} recievers The message reciever ('all' || userPseudonym)
         * @param {string} message   The txt message to send
         * @param {string} roomName  The chat room name
         * @param {string} password  The chat room password if required
         */
        sendMessage: function (recievers, message, roomName, password) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "sendMessage",
                "roomName" : roomName,
                "message"  : message,
                "recievers": recievers,
                "password" : password || ''
            }));
        },

        /**
         * Create a chat room
         *
         * @param {string}  roomName The room name
         * @param {string}  type     The room type ('public' || 'private')
         * @param {string}  password The room password
         * @param {integer} maxUsers The max users number
         */
        createRoom: function (roomName, type, password, maxUsers) {
            this.websocket.send(JSON.stringify({
                "service"     : [this.settings.serviceName],
                "action"      : "createRoom",
                "login"       : this.user.getEmail(),
                "password"    : this.user.getPassword(),
                "roomName"    : roomName,
                "type"        : type,
                "roomPassword": password,
                "maxUsers"    : maxUsers
            }));
        },

        /**
         * Insert a room in the user DOM with data recieved from server
         *
         * @param {object} data The server JSON reponse
         */
        insertRoomInDOM: function (data) {
            if ($(this.settings.selectors.global.room + '[data-name="' + data.roomName + '"]').length === 0) {
                var defaultRoom = $(this.settings.selectors.global.room + '[data-name="default"]'),
                    newRoom     = defaultRoom.clone(true),
                    newRoomChat = newRoom.find(this.settings.selectors.global.roomChat),
                    i;

                newRoom.attr('data-name', data.roomName);
                newRoom.attr('data-type', data.type);
                newRoom.attr('data-password', data.password);
                newRoom.attr('data-max-users', data.maxUsers);
                newRoom.find(this.settings.selectors.global.roomName).text(data.roomName);
                newRoomChat.html('');

                if (data.historic) {
                    this.loadHistoric(newRoomChat, data.historic);
                }

                defaultRoom.after(newRoom);
            }
        },

        /**
         * Load conversations historic sent by the server
         *
         * @param  {object} roomChatDOM The room chat jQuery DOM element to insert the conversations historic in
         * @param  {object} historic    The conversations historic
         */
        loadHistoric: function (roomChatDOM, historic) {
            for (i = historic.length - 1; i >= 0; i--) {
                roomChatDOM.prepend(this.formatUserMessage(historic[i]));
            }
        },

        /**
         * Format a user message in a html div
         *
         * @param  {object} data The server JSON reponse
         * @return {object}      jQuery html div object containing the user message
         */
        formatUserMessage: function (data) {
            return $('<div>', {
                "class": this.settings.selectors.chat.message.substr(1) + ' ' + data.type
            }).append(
                $('<span>', {
                    "class": this.settings.selectors.chat.date.substr(1),
                    "text" : '[' + data.time + ']'
                }),
                $('<span>', {
                    "class": this.settings.selectors.chat.pseudonym.substr(1),
                    "text" : data.pseudonym
                }),
                $('<span>', {
                    "class": this.settings.selectors.chat.text.substr(1),
                    "text" : data.text
                })
            );
        }
    };

    return ChatManager;
});