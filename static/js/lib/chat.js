/**
 * Chat module
 *
 * @module lib/chat
 */

/*global define*/

define(['jquery', 'module', 'lodash', 'bootstrap-switch', 'bootstrap'], function ($, module, _) {
    'use strict';

    /**
     * ChatManager object
     *
     * @constructor
     * @alias       module:lib/chat
     * @param       {Message}      Message   A Message object to output message in the IHM
     * @param       {WebSocket}    WebSocket The websocket manager
     * @param       {User}         User      The current User
     * @param       {FormsManager} Forms     A FormsManager to handle form XHR ajax calls or jsCallbacks
     * @param       {object}       settings  Overriden settings
     */
    var ChatManager = function (Message, WebSocket, User, Forms, settings) {
        this.settings  = $.extend(true, {}, this.settings, settings);
        this.message   = Message;
        this.websocket = WebSocket;
        this.user      = User;
        this.initEvents();

        // Add websocket callbacks
        this.websocket.addCallback(this.settings.serviceName, this.chatCallback, this);

        // Bind forms callback
        Forms.addJsCallback('setReasonCallbackEvent', this.setReasonCallbackEvent, this);
        Forms.addJsCallback('setRoomInfosCallbackEvent', this.setRoomInfosCallbackEvent, this);
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
            "animationTime"    : module.config().animationTime,
            "selectors"        : {
                "global": {
                    "chat"              : module.config().selectors.global.chat,
                    "room"              : module.config().selectors.global.room,
                    "roomName"          : module.config().selectors.global.roomName,
                    "roomContents"      : module.config().selectors.global.roomContents,
                    "roomChat"          : module.config().selectors.global.roomChat,
                    "roomSample"        : module.config().selectors.global.roomSample,
                    "roomHeader"        : module.config().selectors.global.roomHeader,
                    "roomClose"         : module.config().selectors.global.roomClose,
                    "roomMinimize"      : module.config().selectors.global.roomMinimize,
                    "roomFullscreen"    : module.config().selectors.global.roomFullscreen,
                    "roomMessagesUnread": module.config().selectors.global.roomMessagesUnread
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
                    "loadHistoric"  : module.config().selectors.roomAction.loadHistoric,
                    "kickUser"      : module.config().selectors.roomAction.kickUser,
                    "showUsers"     : module.config().selectors.roomAction.showUsers,
                    "administration": module.config().selectors.roomAction.administration
                },
                "chat": {
                    "message"  : module.config().selectors.chat.message,
                    "pseudonym": module.config().selectors.chat.pseudonym,
                    "date"     : module.config().selectors.chat.date,
                    "text"     : module.config().selectors.chat.text
                },
                "administrationPanel": {
                    "modal"            : module.config().selectors.administrationPanel.modal,
                    "modalSample"      : module.config().selectors.administrationPanel.modalSample,
                    "trSample"         : module.config().selectors.administrationPanel.trSample,
                    "usersList"        : module.config().selectors.administrationPanel.usersList,
                    "roomName"         : module.config().selectors.administrationPanel.roomName,
                    "kick"             : module.config().selectors.administrationPanel.kick,
                    "ban"              : module.config().selectors.administrationPanel.ban,
                    "rights"           : module.config().selectors.administrationPanel.rights,
                    "pseudonym"        : module.config().selectors.administrationPanel.pseudonym,
                    "toggleRights"     : module.config().selectors.administrationPanel.toggleRights,
                    "bannedList"       : module.config().selectors.administrationPanel.bannedList,
                    "ip"               : module.config().selectors.administrationPanel.ip,
                    "pseudonymBanned"  : module.config().selectors.administrationPanel.pseudonymBanned,
                    "pseudonymAdmin"   : module.config().selectors.administrationPanel.pseudonymAdmin,
                    "reason"           : module.config().selectors.administrationPanel.reason,
                    "date"             : module.config().selectors.administrationPanel.date,
                    "inputRoomPassword": module.config().selectors.administrationPanel.inputRoomPassword,
                    "inputRoomName"    : module.config().selectors.administrationPanel.inputRoomName
                },
                "alertInputsChoice": {
                    "div"   : module.config().selectors.alertInputsChoice.div,
                    "submit": module.config().selectors.alertInputsChoice.submit
                }
            }
        },
        /**
         * Global promises handler
         */
        "promises": {
            "setReason": $.Deferred()
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
         * The current user message (not sent) by room
         */
        "messagesCurrent": {},
        /**
         * A messages sent history by room
         */
        "messagesHistory": {},
        /**
         * Pointer in the array messagesHistory by room
         */
        "messagesHistoryPointer": {},
        /**
         * Monitor mouse position when it is in or out a room chat div
         */
        "mouseInRoomChat": {},
        /**
         * Monitor if a room is opened or not
         */
        "isRoomOpened": {},
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
            // Display a room
            $('body').on(
                'click',
                this.settings.selectors.global.roomName,
                $.proxy(this.displayRoomEvent, this)
            );
            // Minimize a room
            $('body').on(
                'click',
                this.settings.selectors.global.roomMinimize,
                $.proxy(this.minimizeRoomEvent, this)
            );
            // Fullscreen a room
            $('body').on(
                'click',
                this.settings.selectors.global.roomFullscreen,
                $.proxy(this.fullscreenRoomEvent, this)
            );
            // Close a room
            $('body').on(
                'click',
                this.settings.selectors.global.roomClose,
                $.proxy(this.closeRoomEvent, this)
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
            // Select a reciever for the chat message
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' +
                this.settings.selectors.roomSend.usersList + ' li a',
                $.proxy(this.selectUserEvent, this)
            );
            // Monitor the mouse when it is in a roomChat div
            $('body').on(
                'mouseenter',
                this.settings.selectors.global.roomChat,
                $.proxy(this.mouseEnterRoomChatEvent, this)
            );
            // Monitor the mouse when it is not in a roomChat div
            $('body').on(
                'mouseleave',
                this.settings.selectors.global.roomChat,
                $.proxy(this.mouseLeaveRoomChatEvent, this)
            );
            // Set the modal admin contents before it is opened
            $('body').on(
                'show.bs.modal',
                this.settings.selectors.administrationPanel.div,
                $.proxy(this.setAministrationPanelEvent, this)
            );
            // Toggle the user right in the administration panel
            $('body').on(
                'click',
                this.settings.selectors.administrationPanel.modal + ' ' +
                this.settings.selectors.administrationPanel.toggleRights,
                $.proxy(this.toggleAdministrationRights, this)
            );
            // Kick a user from a room in the administration panel
            $('body').on(
                'click',
                this.settings.selectors.administrationPanel.modal + ' ' +
                this.settings.selectors.administrationPanel.kick,
                $.proxy(this.kickUserEvent, this)
            );
            // Ban a user from a room in the administration panel
            $('body').on(
                'click',
                this.settings.selectors.administrationPanel.modal + ' ' +
                this.settings.selectors.administrationPanel.ban,
                $.proxy(this.banUserEvent, this)
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
         * Event fired when a user wants to display a room
         *
         * @param {event} e The fired event
         */
        displayRoomEvent: function (e) {
            $(e.currentTarget).closest(this.settings.selectors.global.roomHeader)
            .next(this.settings.selectors.global.roomContents)
            .slideDown(this.settings.animationTime);

            this.isRoomOpened[$(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-name')] = true;
        },

        /**
         * Event fired when a user wants to minimize a room
         *
         * @param {event} e The fired event
         */
        minimizeRoomEvent: function (e) {
            $(e.currentTarget).closest(this.settings.selectors.global.roomHeader)
            .next(this.settings.selectors.global.roomContents)
            .slideUp(this.settings.animationTime);

            this.isRoomOpened[$(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-name')] = false;
        },

        /**
         * Event fired when a user wants to fullscreen / reduce a room
         *
         * @param {event} e The fired event
         */
        fullscreenRoomEvent: function (e) {
            var room = $(e.currentTarget).closest(this.settings.selectors.global.room);

            if ($(e.currentTarget).hasClass('glyphicon-fullscreen')) {
                room.css('width', '100%');
                $(e.currentTarget).removeClass('glyphicon-fullscreen');
                $(e.currentTarget).addClass('glyphicon-resize-small');
            } else {
                room.removeAttr('style');
                $(e.currentTarget).removeClass('glyphicon-resize-small');
                $(e.currentTarget).addClass('glyphicon-fullscreen');
            }
        },

        /**
         * Event fired when a user wants to close a room
         *
         * @param {event} e The fired event
         */
        closeRoomEvent: function (e) {
            var room     = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomName = room.attr('data-name');

            this.disconnect(roomName);
            delete this.isRoomOpened[roomName];
            delete this.mouseInRoomChat[roomName];
            room.remove();
        },

        /**
         * Event fired when a user press a key in a chat message input
         *
         * @param {event} e The fired event
         */
        chatTextKeyPressEvent: function (e) {
            var room     = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomName = room.attr('data-name');

            if (e.which === 13) {
                // Enter key pressed
                this.sendMessageEvent(e);
            } else if (e.which === 38) {
                // Up arrow key pressed
                if (this.messagesHistoryPointer[roomName] > 0) {
                    if (this.messagesHistoryPointer[roomName] === this.messagesHistory[roomName].length) {
                        this.messagesCurrent[roomName] = $(e.currentTarget).val();
                    }

                    $(e.currentTarget).val(this.messagesHistory[roomName][--this.messagesHistoryPointer[roomName]]);
                }
            } else if (e.which === 40) {
                // Down arrow key pressed
                if (this.messagesHistoryPointer[roomName] + 1 < this.messagesHistory[roomName].length) {
                    $(e.currentTarget).val(this.messagesHistory[roomName][++this.messagesHistoryPointer[roomName]]);
                } else if (this.messagesHistoryPointer[roomName] + 1 === this.messagesHistory[roomName].length) {
                    this.messagesHistoryPointer[roomName]++;
                    $(e.currentTarget).val(this.messagesCurrent[roomName]);
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

                this.messagesHistory[roomName].push(message);
                this.messagesHistoryPointer[roomName]++;
                messageInput.val('');
            }
            
            e.preventDefault();
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

        /**
         * Event fired when the user mouse enters the room chat div
         *
         * @param {event} e The fired event
         */
        mouseEnterRoomChatEvent: function (e) {
            var roomName = $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-name');

            this.mouseInRoomChat[roomName] = true;
        },
        
        /**
         * Event fired when the user mouse leaves the room chat div
         *
         * @param {event} e The fired event
         */
        mouseLeaveRoomChatEvent: function (e) {
            var roomName = $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-name');

            this.mouseInRoomChat[roomName] = false;
        },

        /**
         * Event fired when the user wants to display the administration room panel
         *
         * @param {event} e The fired event
         */
        setAministrationPanelEvent: function (e) {
            var modal    = $(e.currentTarget),
                roomName = $(e.relatedTarget).closest(this.settings.selectors.global.room).attr('data-name');

            modal.find(this.settings.selectors.administrationPanel.rights + ' input[type="checkbox"]').bootstrapSwitch();
        },

        /**
         * Event fired when the user wants to display a user rights in administration modal
         *
         * @param {event} e The fired event
         */
        toggleAdministrationRights: function (e) {
            $(e.currentTarget).closest('tbody').find($(e.currentTarget).attr('data-refer')).toggle();
        },

        /**
         * Event fired when a user wants to kick another user from a room in the administration panel
         *
         * @param {event} e The fired event
         */
        kickUserEvent: function (e) {
            var pseudonym = $(e.currentTarget).closest('tr').attr('data-pseudonym'),
                modal     = $(e.currentTarget).closest(this.settings.selectors.administrationPanel.modal),
                roomName  = modal.attr('data-room-name'),
                self      = this;

            $(modal).fadeOut(this.settings.animationTime, function() {
                $(self.settings.selectors.alertInputsChoice.div).fadeIn(self.settings.animationTime);
            });

            $.when(this.promises.setReason).done(function (reason) {
                self.kickUser(roomName, pseudonym, reason);
                $(modal).fadeIn(self.settings.animationTime);
            });
        },

        /**
         * Event fired when a user wants to ban another user from a room in the administration panel
         *
         * @param {event} e The fired event
         */
        banUserEvent: function (e) {
            var pseudonym = $(e.currentTarget).closest('tr').attr('data-pseudonym'),
                modal     = $(e.currentTarget).closest(this.settings.selectors.administrationPanel.modal),
                roomName  = modal.attr('data-room-name'),
                self      = this;

            $(modal).fadeOut(this.settings.animationTime, function() {
                $(self.settings.selectors.alertInputsChoice.div).fadeIn(self.settings.animationTime);
            });

            $.when(this.promises.setReason).done(function (reason) {
                self.banUser(roomName, pseudonym, reason);
                $(modal).fadeIn(self.settings.animationTime);
            });
        },

        /**
         * Set the reason of the admin kick / ban action
         *
         * @param {object} form   The jQuery DOM form element
         * @param {object} inputs The user inputs as object
         */
        setReasonCallbackEvent: function (form, inputs) {
            var self = this;

            $(this.settings.selectors.alertInputsChoice.div).fadeOut(this.settings.animationTime, function() {
                self.promises.setReason.resolve(_.findWhere(inputs, {"name": "reason"}).value);
                self.promises.setReason = $.Deferred();
                form[0].reset();
            });
        },

        /**
         * Set the room name / password
         *
         * @param {object} form   The jQuery DOM form element
         * @param {object} inputs The user inputs as object
         */
        setRoomInfosCallbackEvent: function (form, inputs) {
            var modal           = form.closest(this.settings.selectors.administrationPanel.modal),
                oldRoomName     = modal.attr('data-room-name'),
                newRoomName     = _.findWhere(inputs, {"name": "roomName"}).value,
                oldRoomPassword = modal.attr('data-room-password'),
                newRoomPassword = _.findWhere(inputs, {"name": "roomPassword"}).value;

            if (oldRoomName !== newRoomName || oldRoomPassword !== newRoomPassword) {
                this.setRoomInfos(oldRoomName, newRoomName, oldRoomPassword, newRoomPassword);
            }
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
         * Disconnect a user from a chat room
         *
         * @param {string} roomName The room name to connect to
         */
        disconnect: function (roomName) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "disconnectFromRoom",
                "roomName" : roomName
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

        /**
         * Ban a user from a room
         *
         * @param {string} roomName  The room name
         * @param {string} pseudonym The user pseudonym to ban
         * @param {string} reason    OPTIONAL the reason of the ban
         */
        banUser: function (roomName, pseudonym, reason) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "banUser",
                "user"     : this.user.settings,
                "roomName" : roomName,
                "pseudonym": pseudonym,
                "reason"   : reason
            }));
        },

        /**
         * Update a user right
         *
         * @param {string}  roomName   The room name
         * @param {string}  pseudonym  The user pseudonym
         * @param {string}  rightName  The right name to update
         * @param {boolean} rightValue The new right value
         */
        updateRoomUserRight: function (roomName, pseudonym, rightName, rightValue) {
            this.websocket.send(JSON.stringify({
                "service"   : [this.settings.serviceName],
                "action"    : "updateRoomUserRight",
                "user"      : this.user.settings,
                "roomName"  : roomName,
                "pseudonym" : pseudonym,
                "rightName" : rightName,
                "rightValue": rightValue
            }));
        },

        /**
         * Set a new room name / password
         *
         * @param {string} oldRoomName     The old room name
         * @param {string} newRoomName     The new room name
         * @param {string} oldRoomPassword The old room password
         * @param {string} newRoomPassword The new room password
         */
        setRoomInfos: function (oldRoomName, newRoomName, oldRoomPassword, newRoomPassword) {
            this.websocket.send(JSON.stringify({
                "service"        : [this.settings.serviceName],
                "action"         : "setRoomInfos",
                "user"           : this.user.settings,
                "oldRoomName"    : oldRoomName,
                "newRoomName"    : newRoomName,
                "oldRoomPassword": oldRoomPassword,
                "newRoomPassword": newRoomPassword
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

                case 'disconnectFromRoom':
                    this.disconnectRoomCallback(data);

                    break;

                case 'updateRoomUsers':
                    this.updateRoomUsersCallback(data);

                    break;

                case 'updateRoomUsersRights':
                    this.updateRoomUsersRightsCallback(data);

                    break;

                case 'updateRoomUsersBanned':
                    this.updateRoomUsersBannedCallback(data);

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

                case 'getKicked':
                    this.getKickedCallback(data);

                    break;

                case 'kickUser':
                    this.kickUserCallback(data);

                    break;

                case 'getBanned':
                    this.getBannedCallback(data);

                    break;

                case 'banUser':
                    this.banUserCallback(data);

                    break;

                case 'setRoomInfos':
                    this.setRoomInfosCallback(data);

                    break;

                case 'changeRoomInfos':
                    this.changeRoomInfosCallback(data);

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
         * Callback after a user attempted to disconnect from a room
         *
         * @param {object} data The server JSON reponse
         */
        disconnectRoomCallback: function (data) {
            this.message.add(data.text);
        },

        /**
         * Callback after a user entered or left the room
         *
         * @param {object} data The server JSON reponse
         */
        updateRoomUsersCallback: function (data) {
            var room = $(this.settings.selectors.global.room + '[data-name="' + data.roomName + '"]'),
                usersList;

            if (room.length > 0) {
                usersList = room.attr('data-users').split(',');
                room.attr('data-users', _(data.pseudonyms).toString());
                this.updateUsersDropdown(room, data.pseudonyms);

                // Update the administration panel
                if (this.user.connected) {
                    var newPseudonyms = _.difference(data.pseudonyms, usersList),
                        oldPseudonyms = _.difference(usersList, data.pseudonyms),
                        modal         = $('.modal[data-room-name="' + data.roomName + '"]'),
                        users         = modal.find(this.settings.selectors.administrationPanel.usersList),
                        self          = this;

                    _.forEach(newPseudonyms, function (pseudonym) {
                        users.append(
                            self.getUserRightLine(modal, pseudonym)
                        );
                    });

                    _.forEach(oldPseudonyms, function (pseudonym) {
                        users.find('tr[data-pseudonym="' + pseudonym + '"]').remove();
                    });
                }
            }
        },

        /**
         * Callback after a registered user entered or left the room
         *
         * @param {object} data The server JSON reponse
         */
        updateRoomUsersRightsCallback: function (data) {
            var modal = $('.modal[data-room-name="' + data.roomName + '"]');
            
            this.updateRoomUsersRights(modal, data.usersRights);
        },

        /**
         * Callback after a user get banned or unbanned from a room
         *
         * @param {object} data The server JSON reponse
         */
        updateRoomUsersBannedCallback: function (data) {
            var modal = $('.modal[data-room-name="' + data.roomName + '"]');
            
            this.updateRoomUsersBanned(modal, data.usersBanned);
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
            var room                = $(this.settings.selectors.global.room + '[data-name="' + data.roomName + '"]'),
                roomChat            = room.find(this.settings.selectors.global.roomChat),
                messagesUnread      = room.find(this.settings.selectors.global.roomMessagesUnread),
                messagesUnreadValue = messagesUnread.text();

            roomChat.append(this.formatUserMessage(data));

            if (this.isRoomOpened[data.roomName] && !this.mouseInRoomChat[data.roomName]) {
                roomChat.scrollTop(room.height());
                messagesUnread.text('');
            } else {
                if (messagesUnreadValue === '') {
                    messagesUnread.text('1');
                } else {
                    messagesUnread.text(++messagesUnreadValue);
                }
            }
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
         * Callback after kicking a user from a room
         *
         * @param {object} data The server JSON reponse
         */
        kickUserCallback: function (data) {
            this.message.add(data.text);
        },

        /**
         * Callback after being banned from a room
         *
         * @param {object} data The server JSON reponse
         */
        getBannedCallback: function (data) {
            this.message.add(data.text);
        },

        /**
         * Callback after banning a user from a room
         *
         * @param {object} data The server JSON reponse
         */
        banUserCallback: function (data) {
            this.message.add(data.text);
        },

        /**
         * Callback after setting a new room name / password
         *
         * @param {object} data The server JSON reponse
         */
        setRoomInfosCallback: function (data) {
            this.message.add(data.text);
        },

        /**
         * Callback after a room name / password has been changed
         *
         * @param {object} data The server JSON reponse
         */
        changeRoomInfosCallback: function (data) {
            var room = $(this.settings.selectors.global.room + '[data-name="' + data.oldRoomName + '"]');

            if (data.oldRoomName !== data.newRoomName) {
                room.attr('data-name', data.newRoomName);
                room.find(this.settings.selectors.global.roomName).text(data.newRoomName);
                this.mouseInRoomChat[data.newRoomName]        = this.mouseInRoomChat[data.oldRoomName];
                this.isRoomOpened[data.newRoomName]           = this.isRoomOpened[data.oldRoomName];
                this.messagesHistory[data.newRoomName]        = this.messagesHistory[data.oldRoomName];
                this.messagesHistoryPointer[data.newRoomName] = this.messagesHistoryPointer[data.oldRoomName];
                this.messagesCurrent[data.newRoomName]        = this.messagesCurrent[data.oldRoomName];
                delete this.mouseInRoomChat[data.oldRoomName];
                delete this.isRoomOpened[data.oldRoomName];
                delete this.messagesHistory[data.oldRoomName];
                delete this.messagesHistoryPointer[data.oldRoomName];
                delete this.messagesCurrent[data.oldRoomName];
            }

            if (data.oldRoomPassword !== data.newRoomPassword) {
                room.attr('data-password', data.newRoomPassword);
            }

            if (this.user.connected) {
                var modal = $(this.settings.selectors.administrationPanel.modal +
                    '[data-room-name="' + data.oldRoomName + '"]');

                if (data.oldRoomName !== data.newRoomName) {
                    modal.attr('data-room-name', data.newRoomName);
                    modal.find(this.settings.selectors.administrationPanel.roomName).text(data.newRoomName);
                    modal.find(this.settings.selectors.administrationPanel.inputRoomName).val(data.newRoomName);
                }

                if (data.oldRoomPassword !== data.newRoomPassword) {
                    modal.attr('data-room-password', data.newRoomPassword);
                    modal.find(this.settings.selectors.administrationPanel.inputRoomPassword).val(data.newRoomPassword);
                }
            }

            this.recieveMessageCallback(data);
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
                // Room chat creation if the room does not exist yet
                var roomSample  = $(this.settings.selectors.global.roomSample),
                    newRoom     = roomSample.clone(true),
                    newRoomChat = newRoom.find(this.settings.selectors.global.roomChat),
                    i;

                newRoom.attr({
                    "data-name"     : data.roomName,
                    "data-type"     : data.type,
                    "data-pseudonym": data.pseudonym,
                    "data-password" : data.password,
                    "data-max-users": data.maxUsers,
                    "data-users"    : _(data.pseudonyms).toString()
                });
                newRoom.removeAttr('id');
                newRoom.removeClass('hide');
                newRoom.find(this.settings.selectors.global.roomName).text(data.roomName);
                newRoom.find(this.settings.selectors.roomAction.showUsers).popover({
                    content: function () {
                        var list = $('<ul>');

                        _.forEach(newRoom.attr('data-users').split(','), function (pseudonym) {
                            list.append($('<li>', {"text": pseudonym}));
                        });

                        return list.html();
                    }
                });

                newRoomChat.attr('data-historic-loaded', 0);

                this.updateUsersDropdown(newRoom, data.pseudonyms);
                this.loadHistoric(newRoomChat, data.historic);
                this.mouseInRoomChat[data.roomName]        = false;
                this.isRoomOpened[data.roomName]           = true;
                this.messagesHistory[data.roomName]        = [];
                this.messagesHistoryPointer[data.roomName] = 0;
                this.messagesCurrent[data.roomName]        = '';
                
                $(this.settings.selectors.global.chat).append(newRoom);

                // Modal room chat administration creation if the user is registered
                if (data.usersRights !== undefined) {
                    var modalSample = $(this.settings.selectors.administrationPanel.modalSample),
                        newModal    = modalSample.clone(),
                        id          = _.uniqueId('chat-admin-');

                    newModal.attr({
                        "id"                : id,
                        "data-room-name"    : data.roomName,
                        "data-room-password": data.roomPassword
                    });

                    newModal.find(this.settings.selectors.administrationPanel.roomName).text(data.roomName);
                    newModal.find(this.settings.selectors.administrationPanel.inputRoomName).val(data.roomName);
                    newModal.find(this.settings.selectors.administrationPanel.inputRoomPassword).val(data.roomPassword);
                    newRoom.find(this.settings.selectors.roomAction.administration).attr('data-target', '#' + id);
                    this.updateRoomUsersRights(newModal, data.usersRights);

                    modalSample.after(newModal);
                }
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
         * @return {array}       Array of jQuery html div(s) object containing the user message(s)
         */
        formatUserMessage: function (data) {
            var divs = [],
                self = this;

            if (!_.isArray(data.text)) {
                data.text = [data.text];
            }

            _.forEach(data.text, function (text) {
                divs.push(
                    $('<div>', {
                        "class": self.settings.selectors.chat.message.substr(1) + ' ' + data.type
                    }).append(
                        $('<span>', {
                            "class": self.settings.selectors.chat.date.substr(1),
                            "text" : '[' + data.time + ']'
                        }),
                        $('<span>', {
                            "class": self.settings.selectors.chat.pseudonym.substr(1),
                            "text" : data.pseudonym
                        }),
                        $('<span>', {
                            "class": self.settings.selectors.chat.text.substr(1),
                            "text" : text
                        })
                    )
                );
            });

            return divs;
        },

        /**
         * Update the users list in the dropdown menu recievers
         *
         * @param  {object} room       The room jQuery DOM element
         * @param  {array}  pseudonyms The new pseudonyms list
         * @todo                       Traduction on 'all' value
         */
        updateUsersDropdown: function (room, pseudonyms) {
            var list = [],
                self = this;

            list.push($('<li>', {
                    "data-value": "all"
                }).append($('<a>', {
                    "href" : "#",
                    "title": "all",
                    "text" : "all"
                })));

            _.forEach(pseudonyms, function (pseudonym) {
                if (pseudonym !== room.attr('data-pseudonym')) {
                        list.push($('<li>', {
                        "data-value": pseudonym
                    }).append($('<a>', {
                        "href" : "#",
                        "title": pseudonym,
                        "text" : pseudonym
                    })));
                }
            });

            room.find(this.settings.selectors.roomSend.div + ' ' + this.settings.selectors.roomSend.usersList)
            .html(list);
        },

        /**
         * Update the users list in the administration modal
         *
         * @param  {object} modal       The modal jQuery DOM element
         * @param  {object} usersRights The users rights object returned by the server
         */
        updateRoomUsersRights: function (modal, usersRights) {
            var usersList = modal.find(this.settings.selectors.administrationPanel.usersList),
                trSample  = usersList.find(this.settings.selectors.administrationPanel.trSample),
                roomName  = modal.attr('data-room-name'),
                room      = $(this.settings.selectors.global.room + '[data-name="' + roomName + '"]'),
                newLines  = [],
                self      = this;

            if (room.length > 0) {
                _.forEach(room.attr('data-users').split(','), function (pseudonym) {
                    newLines.push(self.getUserRightLine(modal, pseudonym, usersRights[pseudonym]));
                });

                // Clean and insert lines
                usersList.find('tr').not(trSample).remove();
                trSample.last().after(newLines);
            }
        },

        /**
         * Get a new user right line in the administration panel
         *
         * @param  {object} modal      The modal jQuery DOM element
         * @param  {string} pseudonym  The new user pseudonym
         * @param  {object} usersRight The new user rights
         * @return {object}            The new user right line jQuery DOM element
         */
        getUserRightLine: function (modal, pseudonym, usersRight) {
            var usersList = modal.find(this.settings.selectors.administrationPanel.usersList),
                trSample  = usersList.find(this.settings.selectors.administrationPanel.trSample),
                roomName  = modal.attr('data-room-name'),
                newLine   = trSample.clone(),
                refer     = _.uniqueId('right-'),
                self      = this;

            newLine.removeClass('hide sample');
            newLine.attr('data-pseudonym', pseudonym);
            newLine.find(this.settings.selectors.administrationPanel.pseudonym).text(pseudonym);
            newLine.find(this.settings.selectors.administrationPanel.toggleRights).attr('data-refer', '.' + refer);

            newLine.each(function() {
                if ($(this).hasClass(self.settings.selectors.administrationPanel.rights.substr(1))) {
                    var input     = $(this).find('input'),
                        rightName = input.attr('name');

                    $(this).addClass(refer);
                    // Unregistered user
                    if (usersRight === undefined) {
                        // Disabled rights on unregistered users
                        input.bootstrapSwitch('readonly', true);
                        input.bootstrapSwitch('state', false);
                    } else {
                        // Set the current user rights
                        input.bootstrapSwitch('state', usersRight[rightName]);

                        if (!self.user.getChatRights(roomName).grant) {
                            // Disabled rights if the admin have no "grant" right
                            input.bootstrapSwitch('readonly', true);
                        } else {
                            // Bind event on right change event to update the right instantly
                            input.on('switchChange.bootstrapSwitch', function(e, rightValue) {
                                self.updateRoomUserRight(roomName, pseudonym, $(this).attr('name'), rightValue);
                            });
                        }
                    }
                }
            });

            return newLine;
        },

        /**
         * Update the ip banned list in the administration modal
         *
         * @param  {object} modal        The modal jQuery DOM element
         * @param  {object} usersBanned  The users banned object returned by the server
         */
        updateRoomUsersBanned: function (modal, usersBanned) {
            var bannedList = modal.find(this.settings.selectors.administrationPanel.bannedList),
                trSample   = bannedList.find(this.settings.selectors.administrationPanel.trSample),
                newLines   = [],
                self       = this;
            
            _.forEach(usersBanned, function (bannedInfos) {
                var newLine = trSample.clone();

                newLine.removeClass('hide sample');
                newLine.find(self.settings.selectors.administrationPanel.ip).text(bannedInfos.ip);
                newLine.find(self.settings.selectors.administrationPanel.pseudonymBanned).text(bannedInfos.pseudonym);
                newLine.find(self.settings.selectors.administrationPanel.pseudonymAdmin).text(bannedInfos.admin);
                newLine.find(self.settings.selectors.administrationPanel.reason).text(bannedInfos.reason);
                newLine.find(self.settings.selectors.administrationPanel.date).text(bannedInfos.date);
                newLines.push(newLine);
            });

            // Clean and insert lines
            bannedList.find('tr').not(trSample).remove();
            trSample.last().after(newLines);
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

            _.forEach(this.commands, function (regex, name) {
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