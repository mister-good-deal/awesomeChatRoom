/**
 * Room module to handle all room attributes
 *
 * @module room
 */
define([
    'jquery',
    'module',
    'lodash'
], function ($, module, _) {
    'use strict';

    /**
     * Room module to handle all room attributes
     *
     * @param      {Object}  attributes               JSON data representing the room attributes
     * @param      {Object}  settings                 Overriden settings
     *
     * @exports    room
     *
     * @property   {Object}  settings                 The room global settings
     * @property   {Object}  attributes               The room attributes
     * @property   {Number}  attributes.id            The room ID
     * @property   {String}  attributes.name          The room name
     * @property   {Number}  attributes.creator       The room creator user ID
     * @property   {String}  attributes.password      The room password
     * @property   {Object}  attributes.creationDate  The room creation date
     * @property   {Number}  attributes.maxUsers      The room maximum number of users
     * @property   {Array}   attributes.clients       The room connected clients
     * @property   {Object}  attributes.pseudonyms    The room clients pseudonym indexed by their ID
     *
     * @constructor
     * @alias module:room
     */
    var Room = function (attributes, settings) {
        this.settings = $.extend(true, {}, this.settings, module.config(), settings);
        this.attributes         = {};
        this.attributes.clients = {};
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
            return this.attributes;
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
            return this.attributes.id;
        },

        /**
         * Get the room name
         *
         * @method     getName
         * @return     {String}  The room Name
         */
        getName: function () {
            return this.attributes.name;
        },

        /**
         * Set the room name
         *
         * @method     setName
         * @param      {String}  name    The new room name
         */
        setName: function (name) {
            this.attributes.name = name;
        },

        /**
         * Get the creator user ID
         *
         * @method     getCreator
         * @return     {Number}  The creator user ID
         */
        getCreator: function () {
            return this.attributes.creator;
        },

        /**
         * Get the room password
         *
         * @method     getPassword
         * @return     {String}  The room password
         */
        getPassword: function () {
            return this.attributes.password;
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
            return this.attributes.creationDate;
        },

        /**
         * Get the max number of users
         *
         * @method     getMaxUsers
         * @return     {Number}  The max number of users
         */
        getMaxUsers: function () {
            return this.attributes.maxUsers;
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

        /**
         * Get the room clients pseudonym
         *
         * @method     getPseudonyms
         * @return     {Object}  The room clients pseudonym indexed by their ID
         */
        getPseudonyms: function () {
            return this.attributes.pseudonyms;
        },

        /**
         * Get the number of connected clients
         *
         * @method     getNumberOfConnectedClients
         * @return     {Number}  The number of connected clients
         */
        getNumberOfConnectedClients: function () {
            return _.size(this.attributes.clients);
        },

        /*=====  End of Getters / setters  ======*/

        /**
         * Determine if the room is public.
         *
         * @method     isPublic
         * @return     {Boolean}  True if the room is public, False otherwise.
         */
        isPublic: function () {
            return _.isUndefined(this.getPassword()) || this.getPassword().length === 0;
        },

        /**
         * Add a new client in the room
         *
         * @method     addClient
         * @param      {Client}  client  The new client to add in the room
         */
        addClient: function (client) {
            if (typeof this.attributes.clients[client.getId()] === 'undefined') {
                this.attributes.clients[client.getId()] = client;
            }
        }
    };

    return Room;
});
