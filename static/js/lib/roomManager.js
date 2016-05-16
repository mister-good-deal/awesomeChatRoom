/**
 * RoomManager module to handle all room Object
 *
 * @module roomManager
 */
define([
    'jquery',
    'module',
    'lodash',
    'room',
    'client',
    'notification',
    'bootstrap-select',
    'bootstrap'
], function ($, module, _, Room, Client, Notification) {
    'use strict';

    /**
     * RoomManager module
     *
     * @param      {WebsocketManager}   WebsocketManager    The websocket manager
     * @param      {Client}             Client              The current Client
     * @param      {Object}             settings            Overridden settings
     *
     * @exports    roomManager
     * @see        module:websocketManager
     * @see        module:client
     * @see        module:notification
     * @see        module:room
     *
     * @property   {Object}             settings        The roomManager global settings
     * @property   {WebsocketManager}   websocket       The WebsocketManager module
     * @property   {Client}             client          The current Client
     * @property   {Notification}       notification    The Notification module
     * @property   {Array}              mouseInRoom     Array of rooms ID that tells if the mouse is in a room DOM area
     * @property   {Object}             rooms           Collection of room module
     * @property   {Object}             promises        Global promises handler
     *
     * @constructor
     * @alias      module:roomManager
     */
    var RoomManager = function (WebsocketManager, Client, settings) {
        this.settings         = $.extend(true, {}, this.settings, module.config(), settings);
        this.websocketManager = WebsocketManager;
        this.client           = Client;
        this.notification     = new Notification();
        this.mouseInRoom      = [];
        this.rooms            = {};
        this.promises         = {
            "setReason": $.Deferred()
        };
        // Add forms callback
        // Forms.addJsCallback('setReasonCallbackEvent', this.setReasonCallbackEvent, this);
        // Forms.addJsCallback('setRoomInfoCallbackEvent', this.setRoomInfoCallbackEvent, this);

        this.initEvents();
    };

    RoomManager.prototype = {
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
            // Display a room
            // @todo
            $('body').on(
                'click',
                this.settings.selectors.global.roomName,
                $.proxy(this.displayRoomEvent, this)
            );
            // Minimize a room
            // @todo
            $('body').on(
                'click',
                this.settings.selectors.global.roomMinimize,
                $.proxy(this.minimizeRoomEvent, this)
            );
            // Fullscreen a room
            // @todo
            $('body').on(
                'click',
                this.settings.selectors.global.roomFullscreen,
                $.proxy(this.fullscreenRoomEvent, this)
            );
            // Close a room
            // @todo
            $('body').on(
                'click',
                this.settings.selectors.global.roomClose,
                $.proxy(this.closeRoomEvent, this)
            );
            // Monitor the mouse when it is in a roomChat div
            // @todo
            $('body').on(
                'mouseenter',
                this.settings.selectors.global.roomChat,
                $.proxy(this.mouseEnterRoomChatEvent, this)
            );
            // Monitor the mouse when it is not in a roomChat div
            // @todo
            $('body').on(
                'mouseleave',
                this.settings.selectors.global.roomChat,
                $.proxy(this.mouseLeaveRoomChatEvent, this)
            );
            // Toggle the user right in the administration panel
            // @todo
            $('body').on(
                'click',
                this.settings.selectors.administrationPanel.modal + ' ' +
                this.settings.selectors.administrationPanel.toggleRights,
                $.proxy(this.toggleAdministrationRights, this)
            );
            // Kick a user from a room in the administration panel
            // @todo
            $('body').on(
                'click',
                this.settings.selectors.administrationPanel.modal + ' ' +
                this.settings.selectors.administrationPanel.kick,
                $.proxy(this.kickUserEvent, this)
            );
            // Ban a user from a room in the administration panel
            // @todo
            $('body').on(
                'click',
                this.settings.selectors.administrationPanel.modal + ' ' +
                this.settings.selectors.administrationPanel.ban,
                $.proxy(this.banUserEvent, this)
            );
            // Show / hide the password input when the selected room is public / private
            // @todo
            $('body').on(
                'change',
                this.settings.selectors.roomConnect.div + ' ' + this.settings.selectors.roomConnect.name,
                $.proxy(this.selectRoomEvent, this)
            );
        },

        /**
         * Event fired when a user want to connect to a room
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

        /*=====  End of Events  ======*/

        /*==================================================================
        =            Actions that query to the WebSocket server            =
        ==================================================================*/

        /**
         * Get the all rooms
         *
         * @method     getAll
         */
        getAll: function () {
            this.websocketManager.send(JSON.stringify({
                "service": [this.settings.serviceName],
                "action" : "getAll"
            }));
        },

        /**
         * Connect to a room
         *
         * @method     connect
         * @param      {String}  pseudonym  The user pseudonym
         * @param      {Number}  roomId     The room id
         * @param      {String}  password   The room password
         */
        connect: function (pseudonym, roomId, password) {
            this.websocketManager.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "connect",
                "pseudonym": pseudonym || this.client.getUser().getPseudonym(),
                "roomId"   : roomId,
                "password" : password || ''
            }));
        },

        /*=====  End of Actions that query to the WebSocket server  ======*/

        /*==================================================================
        =            Callbacks after WebSocket server responses            =
        ==================================================================*/

        /**
         * Handle the WebSocker server response and process action with the right callback
         *
         * @method     wsCallbackDispatcher
         * @param      {Object}  data    The server JSON reponse
         */
        wsCallbackDispatcher: function (data) {
            if (typeof this[data.action + 'Callback'] === 'function') {
                this[data.action + 'Callback'](data);
            }
        },

        /**
         * Add all rooms to the rooms collection
         *
         * @method     getAllCallback
         * @param      {Object}  data    The server JSON response
         */
        getAllCallback: function (data) {
            _.map(data.rooms, _.bind(this.addRoom, this));
        },

        /**
         * Insert the room in DOM if it is not already in and add clients to the room
         *
         * @method     connectCallback
         * @param      {Object}  data    The server JSON response
         *
         * @todo Add roomBan collection from server ?
         */
        connectCallback: function (data) {
            var self = this;

            if (data.success) {
                _.forEach(data.clients, function (clientAttributes) {
                    self.addClient(clientAttributes, self.rooms[data.roomId], data.pseudonyms);
                });

                if (!this.isRoomInDom(this.rooms[data.roomId])) {
                    this.insertRoomInDOM(this.rooms[data.roomId]);
                }
            }

            this.notification.add(data.text);
        },

        /**
         * Add a client in the room
         *
         * @method     updateClientsCallback
         * @param      {Object}  data    The server JSON response
         */
        addClientInRoomCallback: function (data) {
            var client = new Client(data.client);

            client.setPseudonym(data.pseudonym);
            this.rooms[data.roomId].addClient(client);
        },

        /**
         * Callback after updated a user room right
         *
         * @method     updateUserRightCallback
         * @param      {Object}  data    The server JSON response
         */
        updateUserRightCallback: function (data) {
            this.notification.add(data.text);
        },

        /**
         * Change a user right in the admin panel
         *
         * @method     changeUserRightCallback
         * @param      {Object}  data    The server JSON response
         */
        changeUserRightCallback: function (data) {
            console.log("changeUserRightCallback", data);
        },

        /*=====  End of Callbacks after WebSocket server responses  ======*/

        /*=========================================
        =            Utilities methods            =
        =========================================*/

        /**
         * Add a client in a room
         *
         * @method     addClient
         * @param      {Object}  clientAttributes  The client attributes
         * @param      {Room}    room              The room to add the client in
         * @param      {Object}  pseudonyms        The room pseudonyms list
         */
        addClient: function (clientAttributes, room, pseudonyms) {
            var client = new Client(clientAttributes);

            client.setPseudonym(pseudonyms[client.getId()]);
            room.addClient(client);
        },

        /**
         * Add a room to the rooms collection
         *
         * @method     addRoom
         * @param      {Object}  roomAttributes  The room attributes as JSON
         */
        addRoom: function (roomAttributes) {
            var room = new Room(roomAttributes.room);

            room.setNumberOfConnectedClients(roomAttributes.connectedClients);

            if (_.isUndefined(this.rooms[room.getId()])) {
                this.rooms[room.getId()] = room;
            }

            this.updateRoomList();
        },

        /**
         * Update the room list in the select picker
         *
         * @method     updateRoomList
         */
        updateRoomList: function () {
            var publicRooms  = [],
                privateRooms = [],
                select       = $(
                    this.settings.selectors.roomConnect.div + ' ' + this.settings.selectors.roomConnect.name
                ),
                option;

            _.forEach(this.rooms, function (room) {
                option = $('<option>', {
                    "value"       : room.getId(),
                    "data-subtext": '(' + room.getNumberOfConnectedClients() + '/' + room.getMaxUsers() + ')',
                    "data-type"   : room.isPublic() ? 'public' : 'private',
                    "text"        : room.getName()
                });

                if (room.isPublic()) {
                    publicRooms.push(option);
                } else {
                    privateRooms.push(option);
                }
            });

            select.find(this.settings.selectors.roomConnect.publicRooms).html(publicRooms);
            select.find(this.settings.selectors.roomConnect.privateRooms).html(privateRooms);
            select.selectpicker('refresh');
        },

        /**
         * Insert a room in the DOM with a Room object
         *
         * @method     insertRoomInDOM
         * @param      {Room}  room    The Room object to insert
         */
        insertRoomInDOM: function (room) {
            var roomSample = $(this.settings.selectors.global.roomSample),
                newRoom    = roomSample.clone(true),
                modalSample, newModal, id;

            newRoom.attr('data-id', room.getId());
            newRoom.removeAttr('id');
            newRoom.removeClass('hide');
            newRoom.find(this.settings.selectors.global.roomName).text(room.getName());
            newRoom.find(this.settings.selectors.roomAction.showUsers).popover({
                content: function () {
                    var list = $('<ul>');

                    _.forEach(room.getPseudonyms(), function (pseudonym) {
                        list.append($('<li>', {"text": pseudonym}));
                    });

                    return list.html();
                }
            });
            // @todo this.loadHistoric(room);
            this.mouseInRoom[room.getId()] = false;
            room.setOpened(true);

            $(this.settings.selectors.global.rooms).append(newRoom);
            // Modal room chat administration creation if the user is connected
            if (this.client.getUser().isConnected()) {
                modalSample = $(this.settings.selectors.administrationPanel.modalSample);
                newModal    = modalSample.clone();
                id          = 'chat-admin-' + room.getId();

                newModal.attr({
                    "id"          : id,
                    "data-room-id": room.getId()
                });

                newModal.find(this.settings.selectors.administrationPanel.roomName).text(room.getName());
                newModal.find(this.settings.selectors.administrationPanel.inputRoomName).val(room.getName());
                newModal.find(this.settings.selectors.administrationPanel.inputRoomPassword).val(room.getPassword());
                newRoom.find(this.settings.selectors.roomAction.administration).attr('data-target', '#' + id);
                // @todo this.updateRoomUsersRights(newModal, data.usersRights, room.getId());

                modalSample.after(newModal);
            }
        },

        /**
         * Determine if room is in DOM
         *
         * @method     isRoomInDom
         * @param      {Room}     room    The room object to check
         * @return     {Boolean}  True if room is in DOM, False otherwise
         */
        isRoomInDom: function (room) {
            return $(this.settings.selectors.global.room + '[data-id="' + room.getId() + '"]').length > 0;
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
         * Update the users rights list in the administration modal
         *
         * @method     updateRoomUsersRights
         * @param      {Object}  modal        The modal jQuery DOM element
         * @param      {Object}  usersRights  The users rights object returned by the server
         * @param      {Number}  roomId       The room id
         */
        updateRoomUsersRights: function (modal, usersRights, roomId) {
            var self = this;
            // Self update user chat room right
            if (_.isObject(usersRights[this.user.getPseudonym()])) {
                this.user.setChatRoomRight(roomId, usersRights[this.user.getPseudonym()]);
            }

            _.forEach(usersRights, function (rights, pseudonym) {
                self.updateUserRightLine(modal, pseudonym, rights);
            });
        },

        /**
         * Update a user right line in the administration panel
         *
         * @method     updateUserRightLine
         * @param      {Object}  modal          The modal jQuery DOM element
         * @param      {String}  pseudonym      The new user pseudonym
         * @param      {Object}  userChatRight  The new user chat right
         */
        updateUserRightLine: function (modal, pseudonym, userChatRight) {
            var usersList = modal.find(this.settings.selectors.administrationPanel.usersList),
                roomId    = modal.attr('data-room-id'),
                self      = this,
                rights    = usersList.find(
                    this.settings.selectors.administrationPanel.rights + '[data-pseudonym="' + pseudonym + '"]'
                ),
                input, rightName;

            if (rights.length > 0) {
                rights.each(function () {
                    input     = $(this).find('input');
                    rightName = input.attr('name');
                    // @fixme bootstrapSwitch issue on readonly and disabled state
                    // @see https://github.com/nostalgiaz/bootstrap-switch/issues/494
                    // Fix <<<
                    if (input.bootstrapSwitch('readonly')) {
                        input.bootstrapSwitch('readonly', false);
                    }
                    // Fix >>>
                    input.bootstrapSwitch(
                        'state',
                        userChatRight ? userChatRight[rightName] : false,
                        true
                    );

                    if (!userChatRight) {
                        // Disabled rights on unregistered users
                        input.bootstrapSwitch('state', false, true);
                        input.bootstrapSwitch('readonly', true);
                    } else {
                        // Set the current user rights
                        input.bootstrapSwitch(
                            'state', userChatRight[rightName], true
                        );

                        if (!self.user.getChatRoomRight(roomId).grant && !self.user.getRight().chatAdmin) {
                            // Disabled rights if the admin have no "grant" right
                            input.bootstrapSwitch('readonly', true);
                        } else {
                            // @fixme need to reset the event on update because readonly inputs lost the event binding
                            input.off('switchChange.bootstrapSwitch');
                            // Bind event on right change event to update the right instantly
                            input.on('switchChange.bootstrapSwitch', function (ignore, rightValue) {
                                self.updateRoomUserRight(roomId, pseudonym, $(this).attr('name'), rightValue);
                            });
                        }
                    }
                });
            } else {
                usersList.append(this.getUserRightLine(modal, pseudonym, userChatRight));
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
                    input     = $(this).find('input');
                    rightName = input.attr('name');

                    $(this).addClass(refer);
                    // Unregistered user
                    if (!userChatRight) {
                        // Disabled rights on unregistered users
                        input.bootstrapSwitch('state', false, true);
                        input.bootstrapSwitch('readonly', true);
                    } else {
                        // Set the current user rights
                        input.bootstrapSwitch(
                            'state', userChatRight[rightName], true
                        );

                        if (!self.user.getChatRoomRight(roomId).grant && !self.user.getRight().chatAdmin) {
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
        }

        /*=====  End of Utilities methods  ======*/

    };

    return RoomManager;
});
