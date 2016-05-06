/**
 * Room module
 *
 * @module               lib/room
 *
 * Room object to handle all room attributes
 */

define([
    'jquery',
    'module',
    'lodash'
], function ($, module, _) {
    'use strict';

    /**
     * Room object
     *
     * @class
     * @param      {Object}  attributes  JSON data representing the room attributes
     * @param      {Object}  settings    Overriden settings
     *
     * @alias      module:lib/room
     */
    var Room = function (attributes, settings) {
        this.settings = $.extend(true, {}, this.settings, module.config(), settings);
        this.setAttributes(attributes);
    };

    Room.prototype = {
        /*=========================================
        =            Getters / setters            =
        =========================================*/

        /**
         * Get the room attributes
         *
         * @method     getRoom
         * @return     {Object}  The room attributes as JSON
         */
        getRoom: function () {
            return this.attributes.room;
        },

        /**
         * Set the Room object with a JSON parameter
         *
         * @method     setAttributes
         * @param      {Object}  data    JSON data
         */
        setAttributes: function (data) {
            this.attributes = $.extend(true, {}, this.attributes, data);
        },

        /**
         * Get the room id
         *
         * @method     getId
         * @return     {Number}  The room ID
         */
        getId: function () {
            return this.attributes.room.id;
        },

        /**
         * Get the room name
         *
         * @method     getName
         * @return     {String}  The room Name
         */
        getName: function () {
            return this.attributes.room.name;
        },

        /**
         * Set the room name
         *
         * @method     setName
         * @param      {String}  name    The new room name
         */
        setName: function (name) {
            this.attributes.room.name = name;
        },

        /**
         * Get the creator user ID
         *
         * @method     getCreator
         * @return     {Number}  The creator user ID
         */
        getCreator: function () {
            return this.attributes.room.creator;
        },

        /**
         * Get the room password
         *
         * @method     getPassword
         * @return     {String}  The room password
         */
        getPassword: function () {
            return this.attributes.room.password;
        },

        /**
         * Set the room password.
         *
         * @method     setPassword
         * @param      {String}  password  The new room password
         */
        setPassword: function (password) {
            this.settings.password = password;
        },

        /**
         * Get the creation date
         *
         * @method     getCreationDate
         * @return     {Date}  The Creation date
         *
         * @todo       Parse the type
         */
        getCreationDate: function () {
            return this.attributes.room.creationDate;
        },

        /**
         * Get the max number of users
         *
         * @method     getMaxUsers
         * @return     {Number}  The max number of users
         */
        getMaxUsers: function () {
            return this.attributes.room.maxUsers;
        },

        /**
         * Set the max number of users
         *
         * @method     setMaxUsers
         * @param      {Number}  maxUsers  The max number of users
         */
        setMaxUsers: function (maxUsers) {
            this.attributes.maxUsers = maxUsers;
        },

        /**
         * Get the connected clients
         *
         * @method     getClients
         * @return     {Array}  Array of clients object
         */
        getClients: function () {
            return this.attributes.clients;
        },

        /*=====  End of Getters / setters  ======*/

        /**
         * Determine if the room is public.
         *
         * @method     isPublic
         * @return     {Boolean}  True if the room is public, False otherwise.
         */
        isPublic: function () {
            return _.isUndefined(this.getPassword()) || this.getPassword().length > 0;
        }
    };

    return Room;
});
