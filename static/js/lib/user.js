/**
 * User module
 *
 * @module lib/user
 */

/*global define*/

define(['jquery'], function ($, Message) {
    'use strict';

    /**
     * UserManager object
     *
     * @constructor
     * @alias       module:lib/user
     * @param       {object}       settings Overriden settings
     * @param       {Message}      Message A Message object to output message in the IHM
     * @param       {FormsManager} Forms   A FormsManager to handle form XHR ajax calls
     */
    var UserManager = function (Message, Forms, settings) {
        this.settings = $.extend(true, {}, this.settings, settings);
        this.message  = Message;

        // Bind ajax callback
        Forms.addOnSuccessCallback('user/connect', this.connectSuccess, this);
        Forms.addOnFailCallback('user/connect', this.connectFail, this);
        Forms.addOnRequestFailCallback('user/connect', this.connectRequestFail, this);
    };

    UserManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the UserManager will be instanciated
         */
        "settings" : {
            "firstName" : "",
            "lastName"  : "",
            "pseudonym" : "",
            "email"     : "",
            "password"  : "",
            "chatRights": {}
        },
        /**
         * A Message object to output message in the IHM
         */
        "message": {},
        /**
         * If the user is connected
         */
        "connected": false,

        /**
         * Get first name
         *
         * @return {string} The user first name
         */
        getFirstName: function () {
            return this.settings.firstName;
        },

        /**
         * Get last name
         *
         * @return {string} The user last name
         */
        getLastName: function () {
            return this.settings.lastName;
        },

        /**
         * Get pseudonym
         *
         * @return {string} The user pseudonym
         */
        getPseudonym: function () {
            var pseudonym = this.settings.pseudonym;

            if (pseudonym === '') {
                pseudonym = this.settings.firstName + ' ' + this.settings.lastName;
            }
            
            return pseudonym;
        },

        /**
         * Get email
         *
         * @return {string} The user email
         */
        getEmail: function () {
            return this.settings.email;
        },

        /**
         * Get password
         *
         * @return {string} The user password
         */
        getPassword: function () {
            return this.settings.password;
        },

        /**
         * Get chat rights
         *
         * @param  {string} The room name
         * @return {string} The user chat rights for the room
         */
        getChatRights: function (roomName) {
            return this.settings.chatRights[roomName];
        },

        /**
         * Set the User object with a JSON parameter
         *
         * @param {object} JSON data
         */
        setAttributes: function (data) {
            this.settings.firstName  = data.user.firstName || "";
            this.settings.lastName   = data.user.lastName  || "";
            this.settings.pseudonym  = data.user.pseudonym || "";
            this.settings.email      = data.user.email     || "";
            this.settings.password   = data.user.password  || "";
            this.settings.chatRights = data.user.chatRights  || {};
        },

        /**
         * Callback when the user connection attempt succeed
         *
         * @param {object} form The jQuery DOM form element
         * @param {object} data The server JSON reponse
         */
        connectSuccess: function (form, data) {
            this.setAttributes(data);
            this.connected = true;
            this.message.add('Connect success !');
        },

        /**
         * Callback when the user connection attempt failed
         *
         * @param {object} form The jQuery DOM form element
         * @param {object} data The server JSON reponse
         */
        connectFail: function (form, data) {
            console.log('Fail !');
        },

        /**
         * Callback when the user connection request failed
         *
         * @param {object} form  The jQuery DOM form element
         * @param {object} jqXHR The jQuery jqXHR object
         */
        connectRequestFail: function (form, jqXHR) {
            console.log(jqXHR);
        }
    };

    return UserManager;
});