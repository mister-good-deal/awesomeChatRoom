/**
 * UserManager module
 *
 * @module lib/userManager
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
     * UserManager object
     *
     * @class
     * @param      {FormsManager}  Forms     A FormsManager to handle form XHR ajax calls or jsCallbacks
     * @param      {Object}        settings  Overriden settings
     *
     * @alias      module:lib/userManager
     */
    var UserManager = function (Forms, settings) {
            var self = this;

            this.settings = $.extend(true, {}, this.settings, module.config(), settings);
            this.user     = new User();
            // Bind forms ajax callback
            Forms.addOnSuccessCallback('user/connect', this.connectSuccess, this);
            Forms.addOnFailCallback('user/connect', this.connectFail, this);
            Forms.addOnRequestFailCallback('user/connect', this.connectRequestFail, this);
            Forms.addBeforeRequestCallback('user/register', this.beforeRegister, this);
            Forms.addOnSuccessCallback('user/register', this.registerSuccess, this);
            Forms.addOnFailCallback('user/register', this.registerFail, this);
            Forms.addOnRequestFailCallback('user/register', this.registerRequestFail, this);
            // Set user geoloc
            // @todo seems that maximumAge value is not evaluated and the watch constant refresh every few seconds
            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(
                    _.bind(this.user.setLocation, this.user),
                    _.bind(this.user.setLocationWithGeoip, this.user),
                    {
                        "maximumAge"        : self.settings.locationRefreshInterval,
                        "timeout"           : self.settings.locationTimeout,
                        "enableHighAccuracy": true
                    }
                );
            }

            _.delay(_.bind(this.user.setLocationWithGeoip, this.user), 5000);
        },
        messageManager = new Message();

    UserManager.prototype = {
        /**
         * Callback when the user is successfully connected
         */
        "connectSuccessCallback": null,

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
