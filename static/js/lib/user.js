/**
 * User module
 *
 * @module lib/user
 */

define(['jquery'], function($) {
    'use strict';

    /**
     * UserManager object
     *
     * @constructor
     * @alias       module:lib/user
     * @param       {object}       settings Overriden settings
     * @param       {FormsManager} forms    A FormsManager to handle form XHR ajax calls
     */
    var UserManager = function (forms, settings) {
        this.settings  = $.extend(true, {}, this.settings, settings);

        // Bind ajax callback
        forms.addOnSuccessCallback('user/connect', this.connectSuccess, this);
        forms.addOnFailCallback('user/connect', this.connectFail, this);
    };

    UserManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the UserManager will be instanciated
         */
        "settings" : {
            "firstName": "",
            "lastName" : "",
            "pseudonym": "",
            "email"    : "",
            "password" : ""
        },
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
            return this.settings.pseudonym;
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
         * Set the User object with a JSON parameter
         *
         * @param {object} JSON data
         */
        setAttributes: function (data) {
            this.settings.firstName = data.user.firstName || "";
            this.settings.lastName  = data.user.lastName || "";
            this.settings.pseudonym = data.user.pseudonym || "";
            this.settings.email     = data.user.email || "";
            this.settings.password  = data.user.password || "";
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
        },

        /**
         * Callback when the user connection attempt failed
         *
         * @param {object} form The jQuery DOM form element
         * @param {object} data The server JSON reponse
         */
        connectFail: function (form, data) {
            console.log('Fail !');
        }
    };

    return UserManager;
});