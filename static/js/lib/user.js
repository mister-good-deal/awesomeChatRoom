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
     * @param       {object} settings Overriden settings
     */
    var UserManager = function (settings) {
        this.settings  = $.extend(true, {}, this.settings, settings);
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
        "connected": false,

        /**
         * Set the User object after the server connection response
         *
         * @param  {object} data The server response as a JSON
         */
        connect: function (data) {
            this.settings.firstName = data.firstName || "";
            this.settings.lastName  = data.lastName || "";
            this.settings.pseudonym = data.pseudonym || "";
            this.settings.email     = data.email || "";
        }
    };

    return UserManager;
});