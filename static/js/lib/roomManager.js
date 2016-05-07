/**
 * RoomManager object to handle all room Object
 *
 * @module roomManager
 */
define([
    'jquery',
    'module',
    'lodash',
    'room'
], function ($, module, _, Room) {
    'use strict';

    /**
     * RoomManager object
     *
     * @param      {WebSocket}  WebSocket  The websocket manager
     * @param      {Object}     settings   Overriden settings
     *
     * @exports    roomManager
     * @see        module:room
     * @see        module:websocket
     *
     * @property   {Object}             settings  The roomManager global settings
     * @property   {WebsocketManager}   websocket The WebsocketManager module
     * @property   {Object}             rooms     Collection of room module
     *
     * @constructor
     * @alias      module:roomManager
     */
    var RoomManager = function (WebSocket, settings) {
        this.settings  = $.extend(true, {}, this.settings, module.config(), settings);
        this.websocket = WebSocket;
        this.rooms     = {};
    };

    RoomManager.prototype = {
        /*==================================================================
        =            Actions that query to the WebSocket server            =
        ==================================================================*/

        /**
         * Get the all rooms
         *
         * @method     getAllRooms
         */
        getAllRooms: function () {
            this.websocket.send(JSON.stringify({
                "service": [this.settings.serviceName],
                "action" : "getAllRooms"
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
         * @method     getAllRoomsCallback
         * @param      {Object}  data    The server JSON reponse
         */
        getAllRoomsCallback: function (data) {
            _.map(data.rooms, _.bind(this.addRoom, this));
        },

        /*=====  End of Callbacks after WebSocket server responses  ======*/

        /*=========================================
        =            Utilities methods            =
        =========================================*/

        /**
         * Add a room to the rooms collection
         *
         * @method     addRoom
         * @param      {Object}  roomAttributes  The room attributes as JSON
         */
        addRoom: function (roomAttributes) {
            var room = new Room(roomAttributes);

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
        }

        /*=====  End of Utilities methods  ======*/

    };

    return RoomManager;
});
