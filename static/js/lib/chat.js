/**
 * Chat module
 *
 * @module lib/chat
 */

define([
    'jquery',
    'module',
    'lodash',
    'message',
    'bootstrap-switch',
    'bootstrap-select',
    'bootstrap'
], function ($, module, _, Message) {
    'use strict';

    /**
     * ChatManager object
     *
     * @constructor
     * @alias       module:lib/chat
     * @param       {WebSocket}    WebSocket The websocket manager
     * @param       {User}         User      The current User
     * @param       {FormsManager} Forms     A FormsManager to handle form XHR ajax calls or jsCallbacks
     * @param       {Object}       settings  Overriden settings
     *
     * @todo check the id value of the followings
     * this.mouseInRoomChat
     *
     * this.isRoomOpened
     * this.messagesHistory
     * this.messagesHistoryPointer
     * this.messagesCurrent
     */
    var ChatManager = function (WebSocket, User, Forms, settings) {
            var self = this;

            this.settings  = $.extend(true, {}, this.settings, module.config(), settings);
            this.websocket = WebSocket;
            this.user      = User;
            this.initEvents();
            // Add websocket callbacks
            this.websocket.addCallback(this.settings.serviceName, this.chatCallback, this);
            // Add forms callback
            Forms.addJsCallback('setReasonCallbackEvent', this.setReasonCallbackEvent, this);
            Forms.addJsCallback('setRoomInfoCallbackEvent', this.setRoomInfoCallbackEvent, this);
            // Enable selectpicker and load rooms
            $(document).ready(function () {
                $(self.settings.selectors.roomConnect.div + ' ' + self.settings.selectors.roomConnect.name)
                    .selectpicker();
            });

            this.getRoomsInfo();
        },
        messageManager = new Message();

    ChatManager.prototype = {
        /*====================================================
        =            Object settings / properties            =
        ====================================================*/

        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "users": []
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
                this.settings.selectors.roomConnect.div + ' ' + this.settings.selectors.roomConnect.connect,
                $.proxy(this.connectEvent, this)
            );
            // Create a room
            $('body').on(
                'click',
                this.settings.selectors.roomCreation.div + ' ' + this.settings.selectors.roomCreation.create,
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
                this.settings.selectors.global.room + ' ' + this.settings.selectors.roomAction.loadHistoric,
                $.proxy(this.getHistoricEvent, this)
            );
            // Select a reciever for the chat message
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' + this.settings.selectors.roomSend.usersList + ' li a',
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
            // Show / hide the password input when the selected room is public / private
            $('body').on(
                'change',
                this.settings.selectors.roomConnect.div + ' ' + this.settings.selectors.roomConnect.name,
                $.proxy(this.selectRoomEvent, this)
            );
        },

        /**
         * Event fired when a user want to connect to a chat
         */
        connectEvent: function () {
            var connectDiv = $(this.settings.selectors.roomConnect.div),
                pseudonym  = connectDiv.find(this.settings.selectors.roomConnect.pseudonym).val(),
                roomId     = connectDiv.find('select' + this.settings.selectors.roomConnect.name).val(),
                password   = connectDiv.find(this.settings.selectors.roomConnect.password).val();
            //@todo error message when no room selected...
            this.connect(pseudonym, roomId, password);
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

            this.isRoomOpened[
                $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-name')
            ] = false;
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
                roomId       = room.attr('data-id'),
                password     = room.attr('data-password');

            if (_.trim(message) !== '') {
                if (!this.isCommand(message, roomId, password)) {
                    this.sendMessage(recievers, message, roomId, password);
                }

                this.messagesHistory[roomId].push(message);
                this.messagesHistoryPointer[roomId]++;
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
            var modal = $(e.currentTarget);

            modal.find(this.settings.selectors.administrationPanel.rights + ' input[type="checkbox"]')
                .bootstrapSwitch();
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

            $(modal).fadeOut(this.settings.animationTime, function () {
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

            $(modal).fadeOut(this.settings.animationTime, function () {
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
         * @param {Object} form   The jQuery DOM form element
         * @param {Object} inputs The user inputs as object
         */
        setReasonCallbackEvent: function (form, inputs) {
            var self = this;

            $(this.settings.selectors.alertInputsChoice.div).fadeOut(this.settings.animationTime, function () {
                self.promises.setReason.resolve(_.findWhere(inputs, {"name": "reason"}).value);
                self.promises.setReason = $.Deferred();
                form[0].reset();
            });
        },

        /**
         * Set the room name / password
         *
         * @param {Object} form   The jQuery DOM form element
         * @param {Object} inputs The user inputs as object
         */
        setRoomInfoCallbackEvent: function (form, inputs) {
            var modal           = form.closest(this.settings.selectors.administrationPanel.modal),
                oldRoomName     = modal.attr('data-room-name'),
                newRoomName     = _.findWhere(inputs, {"name": "roomName"}).value,
                oldRoomPassword = modal.attr('data-room-password'),
                newRoomPassword = _.findWhere(inputs, {"name": "roomPassword"}).value;

            if (oldRoomName !== newRoomName || oldRoomPassword !== newRoomPassword) {
                this.setRoomInfo(oldRoomName, newRoomName, oldRoomPassword, newRoomPassword);
            }
        },

        /**
         * Event fired when a user selected a room
         *
         * @param {event} e The fired event
         */
        selectRoomEvent: function (e) {
            if ($(e.currentTarget).find('option:selected').attr('data-type') === 'public') {
                $(this.settings.selectors.roomConnect.div + ' ' + this.settings.selectors.roomConnect.password).hide();
            } else {
                $(this.settings.selectors.roomConnect.div + ' ' + this.settings.selectors.roomConnect.password).show();
            }
        },

        /*=====  End of Events  ======*/

        /*==================================================================
        =            Actions that query to the WebSocket server            =
        ==================================================================*/

        /**
         * Connect a user to the chat
         *
         * @param {String} pseudonym The user pseudonym
         * @param {Number} roomId    The room ID to connect to
         * @param {String} password  The room password to connect to
         */
        connect: function (pseudonym, roomId, password) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "connectRoom",
                "pseudonym": pseudonym || "",
                "roomId"   : roomId,
                "password" : password
            }));
        },

        /**
         * Disconnect a user from a chat room
         *
         * @param {String} roomName The room name to connect to
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
         * @param {String} recievers The message reciever ('all' || userPseudonym)
         * @param {String} message   The txt message to send
         * @param {Number} roomId  The chat room name
         * @param {String} password  The chat room password if required
         */
        sendMessage: function (recievers, message, roomId, password) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "sendMessage",
                "roomId"   : roomId,
                "message"  : message,
                "recievers": recievers,
                "password" : password || ''
            }));
        },

        /**
         * Create a chat room
         *
         * @param {String} roomName The room name
         * @param {String} type     The room type ('public' || 'private')
         * @param {String} password The room password
         * @param {Number} maxUsers The max users number
         */
        createRoom: function (roomName, type, password, maxUsers) {
            this.websocket.send(JSON.stringify({
                "service"     : [this.settings.serviceName],
                "action"      : "createRoom",
                "roomName"    : roomName,
                "roomPassword": password,
                "type"        : type,
                "maxUsers"    : maxUsers
            }));
        },

        /**
         * Get room chat historic
         *
         * @param {String} roomName       The room name
         * @param {String} password       The room password
         * @param {Number} historicLoaded The number of historic already loaded
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
         * @param {String} roomName  The room name
         * @param {String} pseudonym The user pseudonym to kick
         * @param {String} reason    OPTIONAL the reason of the kick
         */
        kickUser: function (roomName, pseudonym, reason) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "kickUser",
                "roomName" : roomName,
                "pseudonym": pseudonym,
                "reason"   : reason
            }));
        },

        /**
         * Ban a user from a room
         *
         * @param {String} roomName  The room name
         * @param {String} pseudonym The user pseudonym to ban
         * @param {String} reason    OPTIONAL the reason of the ban
         */
        banUser: function (roomName, pseudonym, reason) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "banUser",
                "roomName" : roomName,
                "pseudonym": pseudonym,
                "reason"   : reason
            }));
        },

        /**
         * Update a user right
         *
         * @param {String}  roomName   The room name
         * @param {String}  pseudonym  The user pseudonym
         * @param {String}  rightName  The right name to update
         * @param {Boolean} rightValue The new right value
         */
        updateRoomUserRight: function (roomName, pseudonym, rightName, rightValue) {
            this.websocket.send(JSON.stringify({
                "service"   : [this.settings.serviceName],
                "action"    : "updateRoomUserRight",
                "roomName"  : roomName,
                "pseudonym" : pseudonym,
                "rightName" : rightName,
                "rightValue": rightValue
            }));
        },

        /**
         * Set a new room name / password
         *
         * @param {String} oldRoomName     The old room name
         * @param {String} newRoomName     The new room name
         * @param {String} oldRoomPassword The old room password
         * @param {String} newRoomPassword The new room password
         */
        setRoomInfo: function (oldRoomName, newRoomName, oldRoomPassword, newRoomPassword) {
            this.websocket.send(JSON.stringify({
                "service"        : [this.settings.serviceName],
                "action"         : "setRoomInfo",
                "oldRoomName"    : oldRoomName,
                "newRoomName"    : newRoomName,
                "oldRoomPassword": oldRoomPassword,
                "newRoomPassword": newRoomPassword
            }));
        },

        /**
         * Get the rooms basic information (name, type, usersMax, usersConnected)
         */
        getRoomsInfo: function () {
            this.websocket.send(JSON.stringify({
                "service": [this.settings.serviceName],
                "action" : "getRoomsInfo"
            }));
        },

        /*=====  End of Actions that query to the WebSocket server  ======*/

        /*==================================================================
        =            Callbacks after WebSocket server responses            =
        ==================================================================*/

        /**
         * Handle the WebSocker server response and process action then
         *
         * @param {Object} data The server JSON reponse
         */
        chatCallback: function (data) {
            if (typeof this[data.action + 'Callback'] === 'function') {
                this[data.action + 'Callback'](data);
            } else if (data.text) {
                messageManager.add(data.text);
            }
        },

        /**
         * Callback after a user attempted to connect to a room
         *
         * @param {Object} data The server JSON reponse
         */
        connectRoomCallback: function (data) {
            if (data.success) {
                this.insertRoomInDOM(data);
            }

            messageManager.add(data.text);
        },

        /**
         * Callback after a user attempted to disconnect from a room
         *
         * @param {Object} data The server JSON reponse
         */
        disconnectRoomCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after a user entered or left the room
         *
         * @param {Object} data The server JSON reponse
         */
        updateRoomUsersCallback: function (data) {
            var room = $(this.settings.selectors.global.room + '[data-name="' + data.roomName + '"]'),
                usersList, newPseudonyms, oldPseudonyms, modal, users, self;

            if (room.length > 0) {
                usersList = room.attr('data-users').split(',');
                room.attr('data-users', _(data.pseudonyms).toString());
                this.updateUsersDropdown(room, data.pseudonyms);
                // Update the administration panel
                if (this.user.connected) {
                    newPseudonyms = _.difference(data.pseudonyms, usersList);
                    oldPseudonyms = _.difference(usersList, data.pseudonyms);
                    modal         = $('.modal[data-room-name="' + data.roomName + '"]');
                    users         = modal.find(this.settings.selectors.administrationPanel.usersList);
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
         * @param {Object} data The server JSON reponse
         */
        updateRoomUsersRightsCallback: function (data) {
            var modal = $('.modal[data-room-name="' + data.roomName + '"]');

            this.updateRoomUsersRights(modal, data.usersRights);
        },

        /**
         * Callback after a user get banned or unbanned from a room
         *
         * @param {Object} data The server JSON reponse
         */
        updateRoomUsersBannedCallback: function (data) {
            var modal = $('.modal[data-room-name="' + data.roomName + '"]');

            this.updateRoomUsersBanned(modal, data.usersBanned);
        },

        /**
         * Callback after a user attempted to create a room
         *
         * @param {Object} data The server JSON reponse
         */
        createRoomCallback: function (data) {
            if (data.success) {
                this.insertRoomInDOM(data);
            }

            messageManager.add(data.text);
        },

        /**
         * Callback after a user recieved a message
         *
         * @param {Object} data The server JSON reponse
         */
        recieveMessageCallback: function (data) {
            var room                = $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]'),
                roomChat            = room.find(this.settings.selectors.global.roomChat),
                messagesUnread      = room.find(this.settings.selectors.global.roomMessagesUnread),
                messagesUnreadValue = messagesUnread.text();

            roomChat.append(this.formatUserMessage(data));

            if (this.isRoomOpened[data.roomId] && !this.mouseInRoomChat[data.roomId]) {
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
         * @param {Object} data The server JSON reponse
         */
        sendMessageCallback: function (data) {
            if (!data.success) {
                messageManager.add(data.text);
            }
        },

        /**
         * Callback after a user attempted to laod more historic of a conversation
         *
         * @param {Object} data The server JSON reponse
         */
        getHistoricCallback: function (data) {
            var room     = $(this.settings.selectors.global.room + '[data-name="' + data.roomName + '"]'),
                roomChat = room.find(this.settings.selectors.global.roomChat);

            this.loadHistoric(roomChat, data.historic);
            messageManager.add(data.text);
        },

        /**
         * Callback after being kicked from a room
         *
         * @param {Object} data The server JSON reponse
         */
        getKickedCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after kicking a user from a room
         *
         * @param {Object} data The server JSON reponse
         */
        kickUserCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after being banned from a room
         *
         * @param {Object} data The server JSON reponse
         */
        getBannedCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after banning a user from a room
         *
         * @param {Object} data The server JSON reponse
         */
        banUserCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after setting a new room name / password
         *
         * @param {Object} data The server JSON reponse
         */
        setRoomInfoCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after a room name / password has been changed
         *
         * @param {Object} data The server JSON reponse
         */
        changeRoomInfoCallback: function (data) {
            var room = $(this.settings.selectors.global.room + '[data-name="' + data.oldRoomName + '"]'),
                modal;

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
                modal = $(this.settings.selectors.administrationPanel.modal +
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

        /**
         * Callback after getting the new rooms info
         *
         * @param {Object} data The server JSON reponse
         */
        getRoomsInfoCallback: function (data) {
            var publicRooms  = [],
                privateRooms = [],
                select       = $(
                    this.settings.selectors.roomConnect.div + ' ' + this.settings.selectors.roomConnect.name
                ),
                option;

            _.forEach(data.roomsInfo, function (roomInfo) {
                option = $('<option>', {
                    "value"       : roomInfo.room.id,
                    "data-subtext": '(' + roomInfo.usersConnected + '/' + roomInfo.room.maxUsers + ')',
                    "data-type"   : roomInfo.room.password ? 'private' : 'public',
                    "text"        : roomInfo.room.name
                });

                if (roomInfo.room.password) {
                    privateRooms.push(option);
                } else {
                    publicRooms.push(option);
                }
            });

            select.find(this.settings.selectors.roomConnect.publicRooms).html(publicRooms);
            select.find(this.settings.selectors.roomConnect.privateRooms).html(privateRooms);
            select.selectpicker('refresh');
        },

        /*=====  End of Callbacks after WebSocket server responses  ======*/

        /*=========================================
        =            Utilities methods            =
        =========================================*/

        /**
         * Insert a room in the user DOM with data recieved from server
         *
         * @param {Object} data The server JSON reponse
         */
        insertRoomInDOM: function (data) {
            var roomData = data.room,
                room     = $(this.settings.selectors.global.room + '[data-id="' + roomData.id + '"]'),
                roomSample, newRoom, newRoomChat, modalSample, newModal, id;

            if (room.length === 0) {
                // Room chat creation if the room does not exist yet
                roomSample  = $(this.settings.selectors.global.roomSample);
                newRoom     = roomSample.clone(true);
                newRoomChat = newRoom.find(this.settings.selectors.global.roomChat);

                newRoom.attr({
                    "data-id"       : roomData.id,
                    "data-name"     : roomData.name,
                    "data-type"     : roomData.password ? 'private' : 'public',
                    "data-pseudonym": data.pseudonym,
                    "data-password" : roomData.password,
                    "data-max-users": roomData.maxUsers,
                    "data-users"    : _(data.pseudonyms).toString()
                });
                newRoom.removeAttr('id');
                newRoom.removeClass('hide');
                newRoom.find(this.settings.selectors.global.roomName).text(roomData.name);
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
                // @todo historic
                this.loadHistoric(newRoomChat, data.historic);
                this.mouseInRoomChat[roomData.id] = false;
                this.isRoomOpened[roomData.id] = true;
                this.messagesHistory[roomData.id] = [];
                this.messagesHistoryPointer[roomData.id] = 0;
                this.messagesCurrent[roomData.id] = '';

                $(this.settings.selectors.global.chat).append(newRoom);
                // Modal room chat administration creation if the user is registered
                if (this.user.isConnected()) {
                    modalSample = $(this.settings.selectors.administrationPanel.modalSample);
                    newModal    = modalSample.clone();
                    id          = 'chat-admin-' + roomData.id;

                    newModal.attr({
                        "id"                : id,
                        "data-room-name"    : roomData.name,
                        "data-room-password": roomData.password
                    });

                    newModal.find(this.settings.selectors.administrationPanel.roomName).text(roomData.name);
                    newModal.find(this.settings.selectors.administrationPanel.inputRoomName).val(roomData.name);
                    newModal.find(this.settings.selectors.administrationPanel.inputRoomPassword).val(roomData.password);
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
         * @param  {Object} roomChatDOM The room chat jQuery DOM element to insert the conversations historic in
         * @param  {Object} historic    The conversations historic
         */
        loadHistoric: function (roomChatDOM, historic) {
            var historicLoaded = roomChatDOM.attr('data-historic-loaded'),
                i;

            if (historic !== undefined) {
                for (i = historic.length - 1; i >= 0; i--) {
                    roomChatDOM.prepend(this.formatUserMessage(historic[i]));
                }

                roomChatDOM.attr('data-historic-loaded', ++historicLoaded);
            }
        },

        /**
         * Format a user message in a html div
         *
         * @param  {Object} data The server JSON reponse
         * @return {Array}       Array of jQuery html div(s) object containing the user message(s)
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
         * @param  {Object} room       The room jQuery DOM element
         * @param  {Array}  pseudonyms The new pseudonyms list
         */
        updateUsersDropdown: function (room, pseudonyms) {
            var list = [];

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
         * @param  {Object} modal       The modal jQuery DOM element
         * @param  {Object} usersRights The users rights object returned by the server
         */
        updateRoomUsersRights: function (modal, usersRights) {
            var usersList = modal.find(this.settings.selectors.administrationPanel.usersList),
                trSample  = usersList.find(this.settings.selectors.administrationPanel.trSample),
                roomName  = modal.attr('data-room-name'),
                room      = $(this.settings.selectors.global.room + '[data-name="' + roomName + '"]'),
                newLines  = [],
                self = this;

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
         * @param  {Object} modal      The modal jQuery DOM element
         * @param  {String} pseudonym  The new user pseudonym
         * @param  {Object} usersRight The new user rights
         * @return {Object}            The new user right line jQuery DOM element
         */
        getUserRightLine: function (modal, pseudonym, usersRight) {
            var usersList = modal.find(this.settings.selectors.administrationPanel.usersList),
                trSample  = usersList.find(this.settings.selectors.administrationPanel.trSample),
                roomName  = modal.attr('data-room-name'),
                newLine   = trSample.clone(),
                refer     = _.uniqueId('right-'),
                self      = this,
                input, rightName;

            newLine.removeClass('hide sample');
            newLine.attr('data-pseudonym', pseudonym);
            newLine.find(this.settings.selectors.administrationPanel.pseudonym).text(pseudonym);
            newLine.find(this.settings.selectors.administrationPanel.toggleRights).attr('data-refer', '.' + refer);

            newLine.each(function () {
                if ($(this).hasClass(self.settings.selectors.administrationPanel.rights.substr(1))) {
                    input = $(this).find('input');
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
                            input.on('switchChange.bootstrapSwitch', function (ignore, rightValue) {
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
         * @param  {Object} modal        The modal jQuery DOM element
         * @param  {Object} usersBanned  The users banned object returned by the server
         */
        updateRoomUsersBanned: function (modal, usersBanned) {
            var bannedList = modal.find(this.settings.selectors.administrationPanel.bannedList),
                trSample   = bannedList.find(this.settings.selectors.administrationPanel.trSample),
                newLines   = [],
                self       = this;

            _.forEach(usersBanned, function (bannedInfo) {
                var newLine = trSample.clone();

                newLine.removeClass('hide sample');
                newLine.find(self.settings.selectors.administrationPanel.ip).text(bannedInfo.ip);
                newLine.find(self.settings.selectors.administrationPanel.pseudonymBanned).text(bannedInfo.pseudonym);
                newLine.find(self.settings.selectors.administrationPanel.pseudonymAdmin).text(bannedInfo.admin);
                newLine.find(self.settings.selectors.administrationPanel.reason).text(bannedInfo.reason);
                newLine.find(self.settings.selectors.administrationPanel.date).text(bannedInfo.date);
                newLines.push(newLine);
            });
            // Clean and insert lines
            bannedList.find('tr').not(trSample).remove();
            trSample.last().after(newLines);
        },

        /**
         * Check if the user input is a command and process it
         *
         * @param  {String}  message  The user input
         * @param  {String}  roomName The room name
         * @param  {String}  password The room password
         * @return {Boolean}          True if the user input was a command else false
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
