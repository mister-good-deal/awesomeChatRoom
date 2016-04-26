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
     * @todo ._assign instead of $.extend ?
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
         * The last loaded message timestamp recieved by room
         */
        "lastMessageLoadedTime": {},
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
         *
         * @method     initEvents
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
         *
         * @method     connectEvent
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
         *
         * @method     createRoomEvent
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
         * @method     displayRoomEvent
         * @param      {event}  e       The fired event
         */
        displayRoomEvent: function (e) {
            $(e.currentTarget).closest(this.settings.selectors.global.roomHeader)
                .next(this.settings.selectors.global.roomContents)
                .slideDown(this.settings.animationTime);

            this.isRoomOpened[$(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-id')] = true;
        },

        /**
         * Event fired when a user wants to minimize a room
         *
         * @method     minimizeRoomEvent
         * @param      {event}  e       The fired event
         */
        minimizeRoomEvent: function (e) {
            $(e.currentTarget).closest(this.settings.selectors.global.roomHeader)
                .next(this.settings.selectors.global.roomContents)
                .slideUp(this.settings.animationTime);

            this.isRoomOpened[
                $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-id')
            ] = false;
        },

        /**
         * Event fired when a user wants to fullscreen / reduce a room
         *
         * @method     fullscreenRoomEvent
         * @param      {event}  e       The fired event
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
         * @method     closeRoomEvent
         * @param      {event}  e       The fired event
         */
        closeRoomEvent: function (e) {
            var room   = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomId = room.attr('data-id');

            this.disconnect(roomId);
            delete this.isRoomOpened[roomId];
            delete this.mouseInRoomChat[roomId];
            room.remove();
        },

        /**
         * Event fired when a user press a key in a chat message input
         *
         * @method     chatTextKeyPressEvent
         * @param      {event}  e       The fired event
         */
        chatTextKeyPressEvent: function (e) {
            var room   = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomId = room.attr('data-id');

            if (e.which === 13) {
                // Enter key pressed
                this.sendMessageEvent(e);
            } else if (e.which === 38) {
                // Up arrow key pressed
                if (this.messagesHistoryPointer[roomId] > 0) {
                    if (this.messagesHistoryPointer[roomId] === this.messagesHistory[roomId].length) {
                        this.messagesCurrent[roomId] = $(e.currentTarget).val();
                    }

                    $(e.currentTarget).val(this.messagesHistory[roomId][--this.messagesHistoryPointer[roomId]]);
                }
            } else if (e.which === 40) {
                // Down arrow key pressed
                if (this.messagesHistoryPointer[roomId] + 1 < this.messagesHistory[roomId].length) {
                    $(e.currentTarget).val(this.messagesHistory[roomId][++this.messagesHistoryPointer[roomId]]);
                } else if (this.messagesHistoryPointer[roomId] + 1 === this.messagesHistory[roomId].length) {
                    this.messagesHistoryPointer[roomId]++;
                    $(e.currentTarget).val(this.messagesCurrent[roomId]);
                }
            }
        },

        /**
         * Event fired when a user wants to send a message
         *
         * @method     sendMessageEvent
         * @param      {event}  e       The fired event
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
         * @method     getHistoricEvent
         * @param      {event}  e       The fired event
         */
        getHistoricEvent: function (e) {
            var room            = $(e.currentTarget).closest(this.settings.selectors.global.room),
                roomId          = room.attr('data-id'),
                password        = room.attr('data-password'),
                lastMessageDate = room.find(this.settings.selectors.global.roomChat).attr('data-last-message-date');

            this.getHistoric(roomId, password, lastMessageDate);
        },

        /**
         * Event fired when a user wants to select a reciever for his message
         *
         * @method     selectUserEvent
         * @param      {event}  e       The fired event
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
         * @method     mouseEnterRoomChatEvent
         * @param      {event}  e       The fired event
         */
        mouseEnterRoomChatEvent: function (e) {
            var roomId = $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-id');

            this.mouseInRoomChat[roomId] = true;
        },

        /**
         * Event fired when the user mouse leaves the room chat div
         *
         * @method     mouseLeaveRoomChatEvent
         * @param      {event}  e       The fired event
         */
        mouseLeaveRoomChatEvent: function (e) {
            var roomId = $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-id');

            this.mouseInRoomChat[roomId] = false;
        },

        /**
         * Event fired when the user wants to display the administration room panel
         *
         * @method     setAministrationPanelEvent
         * @param      {event}  e       The fired event
         */
        setAministrationPanelEvent: function (e) {
            var modal = $(e.currentTarget);

            modal.find(this.settings.selectors.administrationPanel.rights + ' input[type="checkbox"]')
                .bootstrapSwitch();
        },

        /**
         * Event fired when the user wants to display a user rights in administration modal
         *
         * @method     toggleAdministrationRights
         * @param      {event}  e       The fired event
         */
        toggleAdministrationRights: function (e) {
            $(e.currentTarget).closest('tbody').find($(e.currentTarget).attr('data-refer')).toggle();
        },

        /**
         * Event fired when a user wants to kick another user from a room in the administration panel
         *
         * @method     kickUserEvent
         * @param      {event}  e       The fired event
         */
        kickUserEvent: function (e) {
            var pseudonym = $(e.currentTarget).closest('tr').attr('data-pseudonym'),
                modal     = $(e.currentTarget).closest(this.settings.selectors.administrationPanel.modal),
                roomId    = modal.attr('data-room-id'),
                self      = this;

            $(modal).fadeOut(this.settings.animationTime, function () {
                $(self.settings.selectors.alertInputsChoice.div).fadeIn(self.settings.animationTime);
            });

            $.when(this.promises.setReason).done(function (reason) {
                self.kickUser(roomId, pseudonym, reason);
                $(modal).fadeIn(self.settings.animationTime);
            });
        },

        /**
         * Event fired when a user wants to ban another user from a room in the administration panel
         *
         * @method     banUserEvent
         * @param      {event}  e       The fired event
         */
        banUserEvent: function (e) {
            var pseudonym = $(e.currentTarget).closest('tr').attr('data-pseudonym'),
                modal     = $(e.currentTarget).closest(this.settings.selectors.administrationPanel.modal),
                roomId    = modal.attr('data-room-id'),
                self      = this;

            $(modal).fadeOut(this.settings.animationTime, function () {
                $(self.settings.selectors.alertInputsChoice.div).fadeIn(self.settings.animationTime);
            });

            $.when(this.promises.setReason).done(function (reason) {
                self.banUser(roomId, pseudonym, reason);
                $(modal).fadeIn(self.settings.animationTime);
            });
        },

        /**
         * Set the reason of the admin kick / ban action
         *
         * @method     setReasonCallbackEvent
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  inputs  The user inputs as object
         */
        setReasonCallbackEvent: function (form, inputs) {
            var self = this;

            $(this.settings.selectors.alertInputsChoice.div).fadeOut(this.settings.animationTime, function () {
                self.promises.setReason.resolve(_.find(inputs, {"name": "reason"}).value);
                self.promises.setReason = $.Deferred();
                form[0].reset();
            });
        },

        /**
         * Set the room name / password
         *
         * @method     setRoomInfoCallbackEvent
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  inputs  The user inputs as object
         */
        setRoomInfoCallbackEvent: function (form, inputs) {
            var modal           = form.closest(this.settings.selectors.administrationPanel.modal),
                roomId          = modal.attr('data-room-id'),
                oldRoomName     = modal.attr('data-room-name'),
                newRoomName     = _.find(inputs, {"name": "roomName"}).value,
                oldRoomPassword = modal.attr('data-room-password'),
                newRoomPassword = _.find(inputs, {"name": "roomPassword"}).value;

            if (oldRoomName !== newRoomName || oldRoomPassword !== newRoomPassword) {
                this.setRoomInfo(roomId, newRoomName, newRoomPassword);
            }
        },

        /**
         * Event fired when a user selected a room
         *
         * @method     selectRoomEvent
         * @param      {event}  e       The fired event
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
         * @method     connect
         * @param      {String}  pseudonym  The user pseudonym
         * @param      {Number}  roomId     The room ID to connect to
         * @param      {String}  password   The room password to connect to
         */
        connect: function (pseudonym, roomId, password) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "connectRoom",
                "pseudonym": pseudonym || "",
                "roomId"   : roomId,
                "password" : password,
                "location" : this.user.getLocation()
            }));
        },

        /**
         * Disconnect a user from a chat room
         *
         * @method     disconnect
         * @param      {Number}  roomId  The room ID to disconnect to
         */
        disconnect: function (roomId) {
            this.websocket.send(JSON.stringify({
                "service": [this.settings.serviceName],
                "action" : "disconnectFromRoom",
                "roomId" : roomId
            }));
        },

        /**
         * Send a message to all the users in the chat room or at one user in the chat room
         *
         * @method     sendMessage
         * @param      {String}  recievers  The message reciever ('all' || userPseudonym)
         * @param      {String}  message    The txt message to send
         * @param      {Number}  roomId     The chat room name
         * @param      {String}  password   The chat room password if required
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
         * @method     createRoom
         * @param      {String}  roomName  The room name
         * @param      {String}  type      The room type ('public' || 'private')
         * @param      {String}  password  The room password
         * @param      {Number}  maxUsers  The max users number
         */
        createRoom: function (roomName, type, password, maxUsers) {
            this.websocket.send(JSON.stringify({
                "service"     : [this.settings.serviceName],
                "action"      : "createRoom",
                "roomName"    : roomName,
                "roomPassword": password,
                "type"        : type,
                "maxUsers"    : maxUsers,
                "location"    : this.user.getLocation()
            }));
        },

        /**
         * Get room chat historic
         *
         * @method     getHistoric
         * @param      {Number}  roomId           The room ID
         * @param      {String}  password         The room password
         * @param      {String}  lastMessageDate  The last message loaded timestamp
         */
        getHistoric: function (roomId, password, lastMessageDate) {
            this.websocket.send(JSON.stringify({
                "service"        : [this.settings.serviceName],
                "action"         : "getHistoric",
                "roomId"         : roomId,
                "password"       : password,
                "lastMessageDate": lastMessageDate
            }));
        },

        /**
         * Kick a user from a room
         *
         * @method     kickUser
         * @param      {Number}  roomId     The room ID
         * @param      {String}  pseudonym  The user pseudonym to kick
         * @param      {String}  reason     OPTIONAL the reason of the kick
         */
        kickUser: function (roomId, pseudonym, reason) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "kickUser",
                "roomId"   : roomId,
                "pseudonym": pseudonym,
                "reason"   : reason
            }));
        },

        /**
         * Ban a user from a room
         *
         * @method     banUser
         * @param      {Number}  roomId     The room ID
         * @param      {String}  pseudonym  The user pseudonym to ban
         * @param      {String}  reason     OPTIONAL the reason of the ban
         */
        banUser: function (roomId, pseudonym, reason) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "banUser",
                "roomId"   : roomId,
                "pseudonym": pseudonym,
                "reason"   : reason
            }));
        },

        /**
         * Update a user right
         *
         * @method     updateRoomUserRight
         * @param      {Number}   roomId      The room ID
         * @param      {String}   pseudonym   The user pseudonym
         * @param      {String}   rightName   The right name to update
         * @param      {Boolean}  rightValue  The new right value
         */
        updateRoomUserRight: function (roomId, pseudonym, rightName, rightValue) {
            this.websocket.send(JSON.stringify({
                "service"   : [this.settings.serviceName],
                "action"    : "updateRoomUserRight",
                "roomId"    : roomId,
                "pseudonym" : pseudonym,
                "rightName" : rightName,
                "rightValue": rightValue
            }));
        },

        /**
         * Set a new room name / password
         *
         * @method     setRoomInfo
         * @param      {Number}  roomId           The room ID
         * @param      {String}  newRoomName      The new room name
         * @param      {String}  newRoomPassword  The new room password
         */
        setRoomInfo: function (roomId, newRoomName, newRoomPassword) {
            this.websocket.send(JSON.stringify({
                "service" : [this.settings.serviceName],
                "action"  : "setRoomInfo",
                "roomId"  : roomId,
                "roomInfo": {
                    "name"    : newRoomName,
                    "password": newRoomPassword
                }
            }));
        },

        /**
         * Get the rooms basic information (name, type, usersMax, usersConnected)
         *
         * @method     getRoomsInfo
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
         * @method     chatCallback
         * @param      {Object}  data    The server JSON reponse
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
         * @method     connectRoomCallback
         * @param      {Object}  data    The server JSON reponse
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
         * @method     disconnectRoomCallback
         * @param      {Object}  data    The server JSON reponse
         */
        disconnectRoomCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after a user joined or left the room
         *
         * @method     updateRoomUsersCallback
         * @param      {Object}  data    The server JSON reponse
         */
        updateRoomUsersCallback: function (data) {
            var room = $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]'),
                usersList, newPseudonyms, oldPseudonyms, modal, users, self;

            if (room.length > 0) {
                usersList = room.attr('data-users').split(',');
                room.attr('data-users', _(data.pseudonyms).toString());
                this.updateUsersDropdown(room, data.pseudonyms);
                // Update the administration panel
                if (this.user.connected) {
                    newPseudonyms = _.difference(data.pseudonyms, usersList);
                    oldPseudonyms = _.difference(usersList, data.pseudonyms);
                    modal         = $('.modal[data-room-id="' + data.roomId + '"]');
                    users         = modal.find(this.settings.selectors.administrationPanel.usersList);
                    self          = this;

                    _.forEach(newPseudonyms, function (pseudonym) {
                        users.append(
                            self.getUserRightLine(modal, pseudonym, data.right)
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
         * @method     updateRoomUsersRightsCallback
         * @param      {Object}  data    The server JSON reponse
         */
        updateRoomUsersRightsCallback: function (data) {
            var modal = $('.modal[data-room-id="' + data.roomId + '"]');

            this.updateRoomUsersRights(modal, data.usersRights);
        },

        /**
         * Callback after a user get banned or unbanned from a room
         *
         * @method     updateRoomUsersBannedCallback
         * @param      {Object}  data    The server JSON reponse
         */
        updateRoomUsersBannedCallback: function (data) {
            var modal = $('.modal[data-room-id="' + data.roomId + '"]');

            this.updateRoomUsersBanned(modal, data.usersBanned);
        },

        /**
         * Callback after a user attempted to create a room
         *
         * @method     createRoomCallback
         * @param      {Object}  data    The server JSON reponse
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
         * @method     recieveMessageCallback
         * @param      {Object}  data    The server JSON reponse
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
         * @method     sendMessageCallback
         * @param      {Object}  data    The server JSON reponse
         */
        sendMessageCallback: function (data) {
            if (!data.success) {
                messageManager.add(data.text);
            }
        },

        /**
         * Callback after a user attempted to laod more historic of a conversation
         *
         * @method     getHistoricCallback
         * @param      {Object}  data    The server JSON reponse
         */
        getHistoricCallback: function (data) {
            var room     = $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]'),
                roomChat = room.find(this.settings.selectors.global.roomChat);

            if (data.success) {
                this.loadHistoric(roomChat, data.historic);
            }

            messageManager.add(data.text);
        },

        /**
         * Callback after being kicked from a room
         *
         * @method     getKickedCallback
         * @param      {Object}  data    The server JSON reponse
         */
        getKickedCallback: function (data) {
            $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]').remove();
            messageManager.add(data.text, 'alert', 'info', 'Kicked from the room `' + data.roomName + '`', 10);
        },

        /**
         * Callback after kicking a user from a room
         *
         * @method     kickUserCallback
         * @param      {Object}  data    The server JSON reponse
         */
        kickUserCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after being banned from a room
         *
         * @method     getBannedCallback
         * @param      {Object}  data    The server JSON reponse
         */
        getBannedCallback: function (data) {
            $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]').remove();
            messageManager.add(data.text, 'alert', 'danger', 'Banned from the room `' + data.roomName + '`', 20);
        },

        /**
         * Callback after banning a user from a room
         *
         * @method     banUserCallback
         * @param      {Object}  data    The server JSON reponse
         */
        banUserCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after setting a new room name / password
         *
         * @method     setRoomInfoCallback
         * @param      {Object}  data    The server JSON reponse
         */
        setRoomInfoCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after a room information has been changed
         *
         * @method     changeRoomInfoCallback
         * @param      {Object}  data    The server JSON reponse
         */
        changeRoomInfoCallback: function (data) {
            messageManager.add(data.text);
        },

        /**
         * Callback after a room information has been updated
         *
         * @method     changeRoomInfoCallback
         * @param      {Object}  data    The server JSON reponse
         */
        updateRoomInformation: function (data) {
            var room     = $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]'),
                roomInfo = data.roomInfo,
                modal;

            room.attr('data-name', roomInfo.name);
            room.find(this.settings.selectors.global.roomName).text(roomInfo.name);
            room.attr('data-password', roomInfo.password);

            if (this.user.connected) {
                modal = $(this.settings.selectors.administrationPanel.modal + '[data-room-id="' + data.roomId + '"]');

                modal.attr('data-room-name', roomInfo.name);
                modal.find(this.settings.selectors.administrationPanel.roomName).text(roomInfo.name);
                modal.find(this.settings.selectors.administrationPanel.inputRoomName).val(roomInfo.name);
                modal.attr('data-room-password', roomInfo.password);
                modal.find(this.settings.selectors.administrationPanel.inputRoomPassword).val(roomInfo.password);
            }

            this.recieveMessageCallback(data.messageInfo);
        },

        /**
         * Callback after getting the new rooms info
         *
         * @method     getRoomsInfoCallback
         * @param      {Object}  data    The server JSON reponse
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
         * @method     insertRoomInDOM
         * @param      {Object}  data    The server JSON reponse
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

                this.updateUsersDropdown(newRoom, data.pseudonyms);
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
                        "data-room-id"      : roomData.id,
                        "data-room-name"    : roomData.name,
                        "data-room-password": roomData.password
                    });

                    newModal.find(this.settings.selectors.administrationPanel.roomName).text(roomData.name);
                    newModal.find(this.settings.selectors.administrationPanel.inputRoomName).val(roomData.name);
                    newModal.find(this.settings.selectors.administrationPanel.inputRoomPassword).val(roomData.password);
                    newRoom.find(this.settings.selectors.roomAction.administration).attr('data-target', '#' + id);
                    this.updateRoomUsersRights(newModal, data.chatRights);

                    modalSample.after(newModal);
                }
            } else if (data.historic) {
                this.loadHistoric(room.find(this.settings.selectors.global.roomChat), data.historic);
            }
        },

        /**
         * Load conversations historic sent by the server
         *
         * @method     loadHistoric
         * @param      {Object}  roomChatDOM  The room chat jQuery DOM element to insert the conversations historic in
         * @param      {Array}   historic     The conversations historic
         */
        loadHistoric: function (roomChatDOM, historic) {
            var historicLength = historic.length;

            if (historicLength > 0) {
                roomChatDOM.attr('data-last-message-date', historic[historicLength - 1].date);
                roomChatDOM.prepend(this.formatUserMessage({"messages": historic}));
            } else {
                // @todo button to load or automatic ? Alert user when there are no more message
                roomChatDOM.find(this.settings.selectors.roomAction.loadHistoric).remove();
            }
        },

        /**
         * Format a user message in a html div
         *
         * @method     formatUserMessage
         * @param      {Object}  data    The server JSON reponse
         * @return     {Array}   Array of jQuery html div(s) object containing the user message(s)
         */
        formatUserMessage: function (data) {
            var divs = [],
                self = this;

            if (!_.isArray(data.messages)) {
                data.messages = [data];
            }

            _.forEachRight(data.messages, function (message) {
                divs.push(
                    $('<div>', {
                        "class": self.settings.selectors.chat.message.substr(1) + ' ' + message.type
                    }).append(
                        $('<span>', {
                            "class": self.settings.selectors.chat.date.substr(1),
                            "text" : '[' + new Date(_.toInteger(message.date)).toLocaleString() + ']'
                        }),
                        $('<span>', {
                            "class": self.settings.selectors.chat.pseudonym.substr(1),
                            "text" : message.pseudonym
                        }),
                        $('<span>', {
                            "class": self.settings.selectors.chat.text.substr(1),
                            "text" : message.message
                        })
                    )
                );
            });

            return divs;
        },

        /**
         * Update the users list in the dropdown menu recievers
         *
         * @method     updateUsersDropdown
         * @param      {Object}  room        The room jQuery DOM element
         * @param      {Array}   pseudonyms  The new pseudonyms list
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
         * @method     updateRoomUsersRights
         * @param      {Object}  modal        The modal jQuery DOM element
         * @param      {Object}  usersRights  The users rights object returned by the server
         */
        updateRoomUsersRights: function (modal, usersRights) {
            var usersList = modal.find(this.settings.selectors.administrationPanel.usersList),
                trSample  = usersList.find(this.settings.selectors.administrationPanel.trSample),
                roomId    = modal.attr('data-room-id'),
                room      = $(this.settings.selectors.global.room + '[data-id="' + roomId + '"]'),
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
         * @method     getUserRightLine
         * @param      {Object}  modal          The modal jQuery DOM element
         * @param      {String}  pseudonym      The new user pseudonym
         * @param      {Object}  userChatRight  The new user chat right
         * @return     {Object}  The new user right line jQuery DOM element
         *
         * @todo use lodash and no jquery for iterate
         */
        getUserRightLine: function (modal, pseudonym, userChatRight) {
            var usersList = modal.find(this.settings.selectors.administrationPanel.usersList),
                trSample  = usersList.find(this.settings.selectors.administrationPanel.trSample),
                roomId    = modal.attr('data-room-id'),
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
                    if (!userChatRight) {
                        // Disabled rights on unregistered users
                        input.bootstrapSwitch('readonly', true);
                        input.bootstrapSwitch('state', false);
                    } else {
                        // Set the current user rights
                        input.bootstrapSwitch(
                            'state', userChatRight[roomId] ? userChatRight[roomId][rightName] : false
                        );

                        if (!self.user.getChatRoomRight(roomId) || !self.user.getChatRoomRight(roomId).grant) {
                            // Disabled rights if the admin have no "grant" right
                            input.bootstrapSwitch('readonly', true);
                        } else {
                            // Bind event on right change event to update the right instantly
                            input.on('switchChange.bootstrapSwitch', function (ignore, rightValue) {
                                self.updateRoomUserRight(roomId, pseudonym, $(this).attr('name'), rightValue);
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
         * @method     updateRoomUsersBanned
         * @param      {Object}  modal        The modal jQuery DOM element
         * @param      {Object}  usersBanned  The users banned object returned by the server
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
         * @method     isCommand
         * @param      {String}   message   The user input
         * @param      {String}   roomName  The room name
         * @param      {String}   password  The room password
         * @return     {Boolean}  True if the user input was a command else false
         */
        isCommand: function (message, roomName, password) {
            var isCommand = false,
                self      = this,
                regexResult;

            _.forEach(this.settings.commands, function (regex, name) {
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
