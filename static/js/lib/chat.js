/**
 * Chat module
 *
 * @module lib/chat
 */

/*global define*/

define(['jquery', 'module', 'lodash'], function ($, module, _) {
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
        /*====================================================
        =            Object settings / properties            =
        ====================================================*/
        
        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "users"            : [],
            "serviceName"      : module.config().serviceName,
            "maxUsers"         : module.config().maxUsers,
            "selectors"        : {
                "global": {
                    "chat"        : module.config().selectors.global.chat,
                    "room"        : module.config().selectors.global.room,
                    "roomName"    : module.config().selectors.global.roomName,
                    "roomContents": module.config().selectors.global.roomContents,
                    "roomChat"    : module.config().selectors.global.roomChat,
                    "roomSample"  : module.config().selectors.global.roomSample
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
                    "usersList": module.config().selectors.roomSend.usersList,
                    "send"     : module.config().selectors.roomSend.send
                },
                "roomAction": {
                    "loadHistoric": module.config().selectors.roomAction.loadHistoric,
                    "kickUser"    : module.config().selectors.roomAction.kickUser
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
         * List of all commands name and regex
         */
        "commands": {
            "kick": module.config().commands.kick,
            "pm"  : module.config().commands.pm
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
         * A messages sent history
         */
        "messagesHistory": [],
        /**
         * Pointer in the array messagesHistory
         */
        "messagesHistoryPointer": 0,
        /**
         * The current User instance
         */
        "user": {},
        /**
         * If the service is currently running on the server
         */
        "serviceRunning": false,
        
        /*=====  End of Object settings / properties  ======*/

        /*==============================
        =            Events            =
        ==============================*/
        
        /**
         * Initialize all the events
         */
        initEvents: function () {
            // Connect to a room
            $('body').on(
                'click',
                this.settings.selectors.roomConnect.div + ' ' +
                this.settings.selectors.roomConnect.connect,
                $.proxy(this.connectEvent, this)
            );
            // Create a room
            $('body').on(
                'click',
                this.settings.selectors.roomCreation.div + ' ' +
                this.settings.selectors.roomCreation.create,
                $.proxy(this.createRoomEvent, this)
            );
            // Toggle a room
            $('body').on(
                'click',
                this.settings.selectors.global.roomName,
                $.proxy(this.toggleRoomEvent, this)
            );
            // Listen the "enter" keypress event on the chat text input
            $('body').on(
                'keydown',
                this.settings.selectors.global.room + ' ' +
                this.settings.selectors.roomSend.div + ' ' +
                this.settings.selectors.roomSend.message,
                $.proxy(this.chatTextKeyPressEvent, this)
            );
            // Send a message in a room
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' +
                this.settings.selectors.roomSend.div + ' ' +
                this.settings.selectors.roomSend.send,
                $.proxy(this.sendMessageEvent, this)
            );
            // Load more messages in a room
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' +
                this.settings.selectors.roomAction.loadHistoric,
                $.proxy(this.getHistoricEvent, this)
            );
            // Kick a user from a room
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' +
                this.settings.selectors.roomAction.kickUser,
                $.proxy(this.kickUserEvent, this)
            );
            // Select a reciever for the chat message
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' +
                this.settings.selectors.roomSend.usersList + ' li a',
                $.proxy(this.selectUserEvent, this)
            );
        },

        /**
         * Event fired when a user want to connect to a chat
         */
        connectEvent: function () {
            var connectDiv = $(this.settings.selectors.roomConnect.div),
                pseudonym  = connectDiv.find(this.settings.selectors.roomConnect.pseudonym).val(),
                roomName   = connectDiv.find(this.settings.selectors.roomConnect.name).val(),
                password   = connectDiv.find(this.settings.selectors.roomConnect.password).val();

            this.connect(pseudonym, roomName, password);
        },

        /**
         * Event fired when a user wants to create a chat room
         */
        createRoomEvent: function () {
            var createDiv = $(this.settings.selectors.roomCreation.div),
                roomName  = createDiv.find(this.settings.selectors.roomCreation.name).val(),
                type      = createDiv.find(this.settings.selectors.roomCreation.type).val(),
                password  = createDiv.find(this.settings.selectors.roomCreation.password).val(),
                maxUsers  = createDiv.find(this.settings.selectors.roomCreation.maxUsers).val();

            this.createRoom(roomName, type, password, maxUsers);
        },

        /**
         * Event fired when a user wants to show / hide a room
         *
         * @param {event} e The fired event
         */
        toggleRoomEvent: function (e) {
            $(e.currentTarget).siblings(this.settings.global.roomContents).slideToggle(500);
        },

        /**
         * Event fired when a user press a key in a chat message input
         *
         * @param {event} e The fired event
         */
        chatTextKeyPressEvent: function (e) {
            if (e.which === 13) {
                // Enter key pressed
                this.sendMessageEvent(e);
            } else if (e.which === 38) {
                // Up arrow key pressed
                if (this.messagesHistoryPointer - 1 >= 0) {
                    $(e.currentTarget).val(this.messagesHistory[--this.messagesHistoryPointer]);
                }
            } else if (e.which === 40) {
                // Down arrow key pressed
                if (this.messagesHistoryPointer + 1 <= this.messagesHistory.length) {
                    $(e.currentTarget).val(this.messagesHistory[++this.messagesHistoryPointer]);
                }
            }
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
                recievers    = sendDiv.find(this.settings.selectors.roomSend.recievers).attr('data-value'),
                messageInput = sendDiv.find(this.settings.selectors.roomSend.message),
                message      = messageInput.val(),
                room         = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomName     = room.attr('data-name'),
                password     = room.attr('data-password');

            if (_.trim(message) !== '') {
                if (!this.isCommand(message, roomName, password)) {
                    this.sendMessage(recievers, message, roomName, password);
                }

                this.messagesHistory.push(message);
                this.messagesHistoryPointer++;
                messageInput.val('');
            }
        },

        /**
         * Event fired when a user wants to get more historic of a conversation
         *
         * @param {event} e The fired event
         */
        getHistoricEvent: function (e) {
            var room           = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomName       = room.attr('data-name'),
                password       = room.attr('data-password'),
                historicLoaded = room.find(this.settings.selectors.global.roomChat).attr('data-historic-loaded');

            this.getHistoric(roomName, password, historicLoaded);
        },

        /**
         * Event fired when a user wants to kick another user from a room
         *
         * @param {event} e The fired event
         */
        kickUserEvent: function (e) {
            var room      = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomName  = room.attr('data-name'),
                pseudonym = $(e.currentTarget).next(this.settings.selectors.chat.pseudonym).text();

            this.kickUser(roomName, pseudonym);
        },

        /**
         * Event fired when a user wants to select a reciever for his message
         *
         * @param {event} e The fired event
         */
        selectUserEvent: function (e) {
            var value     = $(e.currentTarget).closest('li').attr('data-value'),
                recievers = $(e.currentTarget).closest(this.settings.selectors.roomSend.usersList)
                    .siblings(this.settings.selectors.roomSend.recievers);

            recievers.attr('data-value', value);
            recievers.find('.value').text(value);

            e.preventDefault();
        },
        
        /*=====  End of Events  ======*/

        /*==================================================================
        =            Actions that query to the WebSocket server            =
        ==================================================================*/
        
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
         * Send a message to all the users in the chat room or at one user in the chat room
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
         * Get room chat historic
         *
         * @param {string}  roomName       The room name
         * @param {string}  password       The room password
         * @param {integer} historicLoaded The number of historic already loaded
         */
        getHistoric: function (roomName, password, historicLoaded) {
            this.websocket.send(JSON.stringify({
                "service"       : [this.settings.serviceName],
                "action"        : "getHistoric",
                "roomName"      : roomName,
                "roomPassword"  : password,
                "historicLoaded": historicLoaded
            }));
        },

        /**
         * Kick a user from a room
         *
         * @param {string} roomName  The room name
         * @param {string} pseudonym The user pseudonym to kick
         * @param {string} reason    OPTIONAL the reason of the kick
         */
        kickUser: function (roomName, pseudonym, reason) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "kickUser",
                "user"     : this.user.settings,
                "roomName" : roomName,
                "pseudonym": pseudonym,
                "reason"   : reason
            }));
        },
        
        /*=====  End of Actions that query to the WebSocket server  ======*/
        
        /*==================================================================
        =            Callbacks after WebSocket server responses            =
        ==================================================================*/
        
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

                case 'getHistoric':
                    this.getHistoricCallback(data);

                    break;

                case 'getkicked':
                    this.getKickedCallback(data);

                    break;

                case 'kickUser':
                    this.kickUserCallback(data);

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
            if (!data.success) {
                this.message.add(data.text);
            }
        },

        /**
         * Callback after a user attempted to laod more historic of a conversation
         *
         * @param {object} data The server JSON reponse
         */
        getHistoricCallback: function (data) {
            var room     = $(this.settings.selectors.global.room + '[data-name="' + data.roomName + '"]'),
                roomChat = room.find(this.settings.selectors.global.roomChat);

            this.loadHistoric(roomChat, data.historic);
            this.message.add(data.text);
        },

        /**
         * Callback after being kicked from a room
         *
         * @param {object} data The server JSON reponse
         */
        getKickedCallback: function (data) {
            this.message.add(data.text);
        },

        /**
         * Callback after kicked a user from a room
         *
         * @param {object} data The server JSON reponse
         */
        kickUserCallback: function (data) {
            if (!data.success) {
                this.message.add(data.text);
            }
        },
        
        /*=====  End of Callbacks after WebSocket server responses  ======*/
        
        /*=========================================
        =            Utilities methods            =
        =========================================*/
        
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
         * Insert a room in the user DOM with data recieved from server
         *
         * @param {object} data The server JSON reponse
         */
        insertRoomInDOM: function (data) {
            var room = $(this.settings.selectors.global.room + '[data-name="' + data.roomName + '"]');

            if (room.length === 0) {
                var roomSample  = $(this.settings.selectors.global.roomSample),
                    newRoom     = roomSample.clone(true),
                    newRoomChat = newRoom.find(this.settings.selectors.global.roomChat),
                    i;

                newRoom.attr('data-name', data.roomName);
                newRoom.attr('data-type', data.type);
                newRoom.attr('data-password', data.password);
                newRoom.attr('data-max-users', data.maxUsers);
                newRoom.removeAttr('id');
                newRoom.removeClass('hide');
                newRoom.find(this.settings.selectors.global.roomName).text(data.roomName);
                newRoomChat.attr('data-historic-loaded', 0);

                if (data.historic) {
                    this.loadHistoric(newRoomChat, data.historic);
                }

                $(this.settings.selectors.global.chat).append(newRoom);
            } else if (data.historic) {
                this.loadHistoric(room.find(this.settings.selectors.global.roomChat), data.historic);
            }
        },

        /**
         * Load conversations historic sent by the server
         *
         * @param  {object} roomChatDOM The room chat jQuery DOM element to insert the conversations historic in
         * @param  {object} historic    The conversations historic
         */
        loadHistoric: function (roomChatDOM, historic) {
            var historicLoaded = roomChatDOM.attr('data-historic-loaded'),
                i;

            for (i = historic.length - 1; i >= 0; i--) {
                roomChatDOM.prepend(this.formatUserMessage(historic[i]));
            }

            roomChatDOM.attr('data-historic-loaded', ++historicLoaded);
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
                $('<button>', {
                    "class": this.settings.selectors.roomAction.kickUser.substr(1),
                    "text" : 'Kick'
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
        },

        /**
         * Check if the user input is a command and process it
         *
         * @param  {string}  message  The user input
         * @param  {string}  roomName The room name
         * @param  {string}  password The room password
         * @return {boolean}          True if the user input was a command else false
         */
        isCommand: function (message, roomName, password) {
            var isCommand = false,
                self      = this,
                regexResult;

            _.each(this.commands, function (regex, name) {
                regexResult = regex.exec(message);

                if (regexResult !== null) {
                    isCommand = true;

                    switch (name) {
                        case 'kick':
                            self.kickUser(roomName, regexResult[1], regexResult[2] || '');

                            break;

                        case 'pm':
                            self.sendMessage(regexResult[1], regexResult[2], roomName, password);

                            break;
                    }

                    return false;
                }
            });

            return isCommand;
        }
        
        /*=====  End of Utilities methods  ======*/
    };

    return ChatManager;
});