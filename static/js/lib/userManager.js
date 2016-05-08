/**
 * UserManager module
 *
 * @module userManager
 */

define([
    'jquery',
    'lodash',
    'module',
    'message',
    'user',
    'bootstrap',
    'loading-overlay'
], function ($, _, module, Message, User) {
    'use strict';

    /**
     * UserManager module
     *
     * @param      {FormsManager}  Forms     A FormsManager to handle form XHR ajax calls or jsCallbacks
     * @param      {Object}        settings  Overriden settings
     *
     * @exports    userManager
     * @see        module:user
     * @see        module:form
     *
     * @property   {Object}         settings                The userManager global settings
     * @property   {User}           user                    The current user object
     * @property   {Function|null}  connectSuccessCallback  Callback when the user is successfully connected
     *
     * @constructor
     * @alias      module:userManager
     */
    var UserManager = function (Forms, settings) {
            this.settings               = $.extend(true, {}, this.settings, module.config(), settings);
            this.user                   = new User();
            this.connectSuccessCallback = null;
            // Bind forms ajax callback
            Forms.addOnSuccessCallback('user/connect', this.connectSuccess, this);
            Forms.addOnFailCallback('user/connect', this.connectFail, this);
            Forms.addOnRequestFailCallback('user/connect', this.connectRequestFail, this);
            Forms.addBeforeRequestCallback('user/register', this.beforeRegister, this);
            Forms.addOnSuccessCallback('user/register', this.registerSuccess, this);
            Forms.addOnFailCallback('user/register', this.registerFail, this);
            Forms.addOnRequestFailCallback('user/register', this.registerRequestFail, this);
        },
        // @todo remove this
        messageManager = new Message();

    UserManager.prototype = {
        /**
         * Get the current user
         *
         * @method     getCurrent
         * @return     {User}  The current user
         */
        getCurrent: function () {
            return this.user;
        },

        /**
         * Callback when the user connection attempt succeed
         *
         * @method     connectSuccess
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {Object}  data    The server JSON reponse
         */
        connectSuccess: function (form, data) {
            this.user.setAttributes(data);
            this.user.setConnected(true);

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
            this.user.setAttributes(data);
            this.user.setConnected(true);
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
