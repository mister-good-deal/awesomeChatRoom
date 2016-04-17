/**
 * User module
 *
 * @module lib/user
 */

define([
    'jquery',
    'module',
    'message',
    'bootstrap',
    'loading-overlay'
], function ($, module, Message) {
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
            this.settings = $.extend(true, {}, this.settings, module.config(), settings);
            // Bind forms ajax callback
            Forms.addOnSuccessCallback('user/connect', this.connectSuccess, this);
            Forms.addOnFailCallback('user/connect', this.connectFail, this);
            Forms.addOnRequestFailCallback('user/connect', this.connectRequestFail, this);
            Forms.addBeforeRequestCallback('user/register', this.beforeRegister, this);
            Forms.addOnSuccessCallback('user/register', this.registerSuccess, this);
            Forms.addOnFailCallback('user/register', this.registerFail, this);
            Forms.addOnRequestFailCallback('user/register', this.registerRequestFail, this);
        },
        messageManager = new Message();

    UserManager.prototype = {
        /**
         * Default settings
         */
        "settings" : {},
        /**
         * User attributes
         */
        "attributes": {
            "id"                   : "",
            "firstName"            : "",
            "lastName"             : "",
            "pseudonym"            : "",
            "email"                : "",
            "password"             : "",
            "securityToken"        : "",
            "securityTokenExpires" : "",
            "connectionAttempt"    : "",
            "ipAttempt"            : "",
            "ip"                   : "",
            "lastConnection"       : "",
            "lastConnectionAttempt": "",
            "right"                : {},
            "chatRight"            : {}
        },
        /**
         * If the user is connected
         */
        "connected": false,
        /**
         * Callback when the user is successfully connected
         */
        "connectSuccessCallback": null,

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
         * @param      {Number}  roomId  The room name
         * @return     {Object}  The user chat rights for the room
         */
        getChatRoomRight: function (roomId) {
            return this.attributes.chatRight[roomId];
        },

        /**
         * Tells if the user is connected
         *
         * @method     isConnected
         * @return     {Boolean}  True if teh user is connected else false
         */
        isConnected: function () {
            return this.connected === true;
        },

        /**
         * Set the User object with a JSON parameter
         *
         * @method     setAttributes
         * @param      {Object}  data    JSON data
         */
        setAttributes: function (data) {
            this.attributes = $.extend(true, {}, this.attributes, data.user);
        },

        /**
         * Callback when the user connection attempt succeed
         *
         * @method     connectSuccess
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  data    The server JSON reponse
         */
        connectSuccess: function (form, data) {
            this.setAttributes(data);
            this.connected = true;

            $(this.settings.selectors.modals.connect).modal('hide');
            messageManager.add('Connect success !');

            if (typeof this.connectSuccessCallback === 'function') {
                this.connectSuccessCallback.call(this);
            }
        },

        /**
         * Callback when the user connection attempt failed
         *
         * @method     connectFail
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  data    The server JSON reponse
         */
        connectFail: function (form, data) {
            console.log('Fail !', data);
        },

        /**
         * Callback when the user connection request failed
         *
         * @method     connectRequestFail
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  jqXHR   The jQuery jqXHR object
         */
        connectRequestFail: function (form, jqXHR) {
            console.log(jqXHR);
        },

        /**
         * Callback before the register form has been sent
         *
         * @method     beforeRegister
         * @param      {Object}  form    The jQuery DOM form element
         */
        beforeRegister: function (form) {
            form.loadingOverlay();
        },

        /**
         * Callback when the user connection attempt succeed
         *
         * @method     registerSuccess
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  data    The server JSON reponse
         */
        registerSuccess: function (form, data) {
            form.loadingOverlay('remove');
            this.setAttributes(data);
            this.connected = true;
            messageManager.add('Register success !');
            // @todo close the modal
        },

        /**
         * Callback when the user connection attempt failed
         *
         * @method     registerFail
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  data    The server JSON reponse
         */
        registerFail: function (form, data) {
            form.loadingOverlay('remove');
            console.log('Fail !', data);
        },

        /**
         * Callback when the user connection request failed
         *
         * @method     registerRequestFail
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  jqXHR   The jQuery jqXHR object
         */
        registerRequestFail: function (form, jqXHR) {
            form.loadingOverlay('remove');
            console.log(jqXHR);
        }
    };

    return UserManager;
});
