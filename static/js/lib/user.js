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
         * Set the User object with a JSON parameter
         *
         * @param {object} JSON data
         */
        setAttributes: function (data) {
            this.settings.firstName = data.firstName || "";
            this.settings.lastName  = data.lastName || "";
            this.settings.pseudonym = data.pseudonym || "";
            this.settings.email     = data.email || "";
            this.settings.password  = data.password || "";
        },

        /**
         * Callback when the user connection attempt succeed
         *
         * @param {object} form The jQuery DOM form element
         * @param {object} data The server JSON reponse
         */
        connectSuccess: function (form, data) {
            this.setAttributes(data);
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