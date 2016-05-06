/**
 * RoomManager module
 *
 * @module               lib/roomManager
 *
 * RoomManager object to handle all room Object
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
     * @class
     * @param      {WebSocket}  WebSocket  The websocket manager
     * @param      {Object}     settings   Overriden settings
     *
     * @alias      module:lib/roomManager
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
         * @method     roomCallbackDispatcher
         * @param      {Object}  data    The server JSON reponse
         */
        roomCallbackDispatcher: function (data) {
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
        }

        /*=====  End of Utilities methods  ======*/

    };

    return RoomManager;
});
