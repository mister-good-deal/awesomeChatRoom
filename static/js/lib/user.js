/**
 * User module
 *
 * @module lib/user
 */

define([
    'jquery',
    'message'
], function ($, Message) {
    'use strict';

    /**
     * UserManager object
     *
     * @constructor
     * @alias       module:lib/user
     * @param       {FormsManager} Forms    A FormsManager to handle form XHR ajax calls or jsCallbacks
     * @param       {Object}       settings Overriden settings
     */
    var UserManager = function (Forms, settings) {
            this.settings = $.extend(true, {}, this.settings, settings);
            // Bind forms ajax callback
            Forms.addOnSuccessCallback('user/connect', this.connectSuccess, this);
            Forms.addOnFailCallback('user/connect', this.connectFail, this);
            Forms.addOnRequestFailCallback('user/connect', this.connectRequestFail, this);
            Forms.addOnSuccessCallback('user/register', this.registerSuccess, this);
            Forms.addOnFailCallback('user/register', this.registerFail, this);
            Forms.addOnRequestFailCallback('user/register', this.registerRequestFail, this);
        },
        messageManager = new Message();

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
         * If the user is connected
         */
        "connected": false,

        /**
         * Get first name
         *
         * @return {String} The user first name
         */
        getFirstName: function () {
            return this.settings.firstName;
        },

        /**
         * Get last name
         *
         * @return {String} The user last name
         */
        getLastName: function () {
            return this.settings.lastName;
        },

        /**
         * Get pseudonym
         *
         * @return {String} The user pseudonym
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
         * @return {String} The user email
         */
        getEmail: function () {
            return this.settings.email;
        },

        /**
         * Get password
         *
         * @return {String} The user password
         */
        getPassword: function () {
            return this.settings.password;
        },

        /**
         * Get chat rights
         *
         * @param  {String} roomName The room name
         * @return {String}          The user chat rights for the room
         */
        getChatRights: function (roomName) {
            return this.settings.chatRights[roomName];
        },

        /**
         * Set the User object with a JSON parameter
         *
         * @param {Object} data JSON data
         */
        setAttributes: function (data) {
            this.settings.firstName  = data.user.firstName || "";
            this.settings.lastName   = data.user.lastName || "";
            this.settings.pseudonym  = data.user.pseudonym || "";
            this.settings.email      = data.user.email || "";
            this.settings.password   = data.user.password || "";
            this.settings.chatRights = data.user.chatRights || {};
        },

        /**
         * Callback when the user connection attempt succeed
         *
         * @param {Object} form The jQuery DOM form element
         * @param {Object} data The server JSON reponse
         */
        connectSuccess: function (form, data) {
            this.setAttributes(data);
            this.connected = true;
            messageManager.add('Connect success !');
        },

        /**
         * Callback when the user connection attempt failed
         *
         * @param {Object} form The jQuery DOM form element
         * @param {Object} data The server JSON reponse
         */
        connectFail: function (form, data) {
            console.log('Fail !', data);
        },

        /**
         * Callback when the user connection request failed
         *
         * @param {Object} form  The jQuery DOM form element
         * @param {Object} jqXHR The jQuery jqXHR object
         */
        connectRequestFail: function (form, jqXHR) {
            console.log(jqXHR);
        },

        /**
         * Callback when the user connection attempt succeed
         *
         * @param {Object} form The jQuery DOM form element
         * @param {Object} data The server JSON reponse
         */
        registerSuccess: function (form, data) {
            this.setAttributes(data);
            this.connected = true;
            messageManager.add('Register success !');
        },

        /**
         * Callback when the user connection attempt failed
         *
         * @param {Object} form The jQuery DOM form element
         * @param {Object} data The server JSON reponse
         */
        registerFail: function (form, data) {
            console.log('Fail !', data);
        },

        /**
         * Callback when the user connection request failed
         *
         * @param {Object} form  The jQuery DOM form element
         * @param {Object} jqXHR The jQuery jqXHR object
         */
        registerRequestFail: function (form, jqXHR) {
            console.log(jqXHR);
        }
    };

    return UserManager;
});
