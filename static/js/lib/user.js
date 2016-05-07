/**
 * User module
 *
 * @module lib/user
 */

define([
    'jquery',
    'lodash',
    'module'
], function ($, _, module) {
    'use strict';

    /**
     * User object
     *
     * @class
     * @param      {Object}  attributes  JSON data representing the user attributes
     * @param      {Object}  settings    Overriden settings
     *
     * @alias      module:lib/user
     */
    var User = function (attributes, settings) {
        this.settings   = $.extend(true, {}, this.settings, module.config(), settings);
        this.attributes = {};
        this.connected  = false;

        if (!_.isEmpty(attributes)) {
            this.setAttributes(attributes);
        }
    };

    User.prototype = {
        /*=========================================
        =            Getters / setters            =
        =========================================*/

        /**
         * Get the User attributes
         *
         * @method     getAttributes
         * @return     {Object}  The User attributes
         */
        getAttributes: function () {
            return this.attributes;
        },

        /**
         * Set the User object with a JSON parameter
         *
         * @method     setAttributes
         * @param      {Object}  data    JSON data
         */
        setAttributes: function (data) {
            this.attributes = $.extend(true, {}, this.attributes, data.user, data.right, data.chatRight);
        },

        /**
         * Get first name
         *
         * @method     getFirstName
         * @return     {String}  The user first name
         */
        getFirstName: function () {
            return this.attributes.firstName;
        },

        /**
         * Get last name
         *
         * @method     getLastName
         * @return     {String}  The user last name
         */
        getLastName: function () {
            return this.attributes.lastName;
        },

        /**
         * Get pseudonym
         *
         * @method     getPseudonym
         * @return     {String}  The user pseudonym
         */
        getPseudonym: function () {
            var pseudonym = this.attributes.pseudonym;

            if (pseudonym === '') {
                pseudonym = this.attributes.firstName + ' ' + this.attributes.lastName;
            }

            return pseudonym;
        },

        /**
         * Get email
         *
         * @method     getEmail
         * @return     {String}  The user email
         */
        getEmail: function () {
            return this.attributes.email;
        },

        /**
         * Get password
         *
         * @return {String} The user password
         */
        getPassword: function () {
            return this.attributes.password;
        },

        /**
         * Get user right
         *
         * @method     getRight
         * @return     {Object}  The user right
         */
        getRight: function () {
            return this.attributes.right;
        },

        /**
         * Get the given chat room right
         *
         * @method     getChatRoomRight
         * @param      {Number}  roomId  The room ID
         * @return     {Object}  The user chat rights for the room or empty object if he does not have right
         */
        getChatRoomRight: function (roomId) {
            return this.attributes.chatRight[roomId] ? this.attributes.chatRight[roomId] : {};
        },

        /**
         * Set the given chat room right
         *
         * @method     getChatRoomRight
         * @param      {Number}  roomId     The room ID
         * @param      {Object}  chatRight  The new chat right
         */
        setChatRoomRight: function (roomId, chatRight) {
            this.attributes.chatRight[roomId] = chatRight;
        },

        /**
         * Set the connected state
         *
         * @method     setConnected
         * @param      {Boolean}  connected  The connected state
         */
        setConnected: function (connected) {
            this.connected = connected;
        },

        /*=====  End of Getters / setters  ======*/

        /**
         * Determine if the user is connected.
         *
         * @method     isConnected
         * @return     {Boolean}  True if the user is connected, False otherwise.
         */
        isConnected: function () {
            return this.connected;
        }
    };

    return User;
});
