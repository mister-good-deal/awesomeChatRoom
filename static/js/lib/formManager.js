/**
 * FormManager module
 *
 * @module formManager
 */

define([
    'jquery',
    'module',
    'domReady!'
], function ($, module) {
    'use strict';

    /**
     * FormManager module
     *
     * @param       {Object}    settings Overriden settings
     *
     * @exports     formManager
     *
     * @property    {Object}    settings                The formManager global settings
     * @property    {Object}    xhrs                    The currents xhr processing
     * @property    {Object}    jsCallbacks             Callbacks methods to process users input on user sumbit action
     * @property    {Object}    callbacks               Callbacks to process the XHR server response
     * @property    {Object}    callbacks.beforeRequest Before request callbacks
     * @property    {Object}    callbacks.onSuccess     On success callbacks
     * @property    {Object}    callbacks.onFail        On fail callbacks
     * @property    {Object}    callbacks.onRequestFail On request fail callbacks
     *
     * @constructor
     * @alias       module:formManager
     */
    var FormManager = function (settings) {
            this.settings    = $.extend(true, {}, this.settings, module.config(), settings);
            this.xhrs        = {};
            this.jsCallbacks = {};
            this.callbacks   = {
                "beforeRequest": {},
                "onSuccess"    : {},
                "onFail"       : {},
                "onRequestFail": {}
            };

            this.initEvents();
        };

    FormManager.prototype = {
        /**
         * Bind events on all form
         */
        initEvents: function () {
            $('body').on('submit', 'form', $.proxy(this.submit, this));
        },

        /**
         * Process the form with specified method in the data-send-action HTML attribute (default AJAX call)
         *
         * @param {event} e The form submit event
         */
        submit: function (e) {
            var form = $(e.currentTarget),
                self = this,
                url;

            switch (form.attr('data-send-action')) {
            case 'jsCallback':
                e.preventDefault();
                this.onJsCallback(form, form.attr('data-callback-name'), form.serializeArray());

                break;

            default:
                e.preventDefault();

                url = form.attr('action');

                if (this.xhrs[url]) {
                    this.xhrs[url].abort();
                }

                this.beforeRequest(form, url);

                this.xhrs[url] = $.ajax({
                    url     : url,
                    type    : form.attr('method'),
                    dataType: 'json',
                    data    : form.serialize()
                }).done(function (data) {
                    if (data.success) {
                        self.onSuccess(form, url, data);
                    } else {
                        self.onFail(form, url, data);
                    }
                }).fail(function (jqXHR) {
                    self.onRequestFail(form, url, jqXHR);
                }).always(function () {
                    delete self.xhrs[url];
                });
            }
        },

        /**
         * Processing method before the request has been sent to the server
         *
         * @method     beforeRequest
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {String}  url     The URL called to send the user data
         */
        beforeRequest: function (form, url) {
            if (this.callbacks.beforeRequest[url] && typeof this.callbacks.beforeRequest[url].callback === 'function') {
                this.callbacks.beforeRequest[url].callback.call(this.callbacks.beforeRequest[url].context, form);
            }
        },

        /**
         * Processing method on server success response
         *
         * @method     onSuccess
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {String}  url     The URL called to send the user data
         * @param      {Object}  data    The server JSON reponse
         */
        onSuccess: function (form, url, data) {
            if (this.callbacks.onSuccess[url] && typeof this.callbacks.onSuccess[url].callback === 'function') {
                this.callbacks.onSuccess[url].callback.call(this.callbacks.onSuccess[url].context, form, data);
            }
        },

        /**
         * Processing method on server fail response
         *
         * @method     onFail
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {String}  url     The URL called to send the user data
         * @param      {Object}  data    The server JSON reponse
         */
        onFail: function (form, url, data) {
            if (this.callbacks.onFail[url] && typeof this.callbacks.onFail[url].callback === 'function') {
                this.callbacks.onFail[url].callback.call(this.callbacks.onFail[url].context, form, data);
            }
        },

        /**
         * Processing method on XHR error
         *
         * @method     onRequestFail
         * @param      {Object}  form    The jQuery DOM form element
         * @param      {String}  url     The URL called to send the user data
         * @param      {Object}  jqXHR   The jQuery jqXHR object
         */
        onRequestFail: function (form, url, jqXHR) {
            if (this.callbacks.onRequestFail[url] && typeof this.callbacks.onRequestFail[url].callback === 'function') {
                this.callbacks.onRequestFail[url].callback.call(this.callbacks.onRequestFail[url].context, form, jqXHR);
            }
        },

        /**
         * Processing method on sumbit event
         *
         * @method     onJsCallback
         * @param      {Object}  form          The jQuery DOM form element
         * @param      {String}  callbackName  The callback function name
         * @param      {Object}  inputs        The user inputs as object
         */
        onJsCallback: function (form, callbackName, inputs) {
            if (this.jsCallbacks[callbackName] && typeof this.jsCallbacks[callbackName].callback === 'function') {
                this.jsCallbacks[callbackName].callback.call(this.jsCallbacks[callbackName].context, form, inputs);
            }
        },

        /**
         * Add a callback to process the url server success JSON repsonse
         *
         * The callback takes 1 argument:
         *
         * - The jQuery DOM form element
         *
         * Example: function callback(form) { ... }
         *
         * @method     addBeforeRequestCallback
         * @param      {String}    url       The URL called to send the user data
         * @param      {Function}  callback  The callback function
         * @param      {Object}    context   The callback context
         */
        addBeforeRequestCallback: function (url, callback, context) {
            this.callbacks.beforeRequest[url] = {
                "callback": callback,
                "context" : context
            };
        },

        /**
         * Add a callback to process the url server success JSON repsonse
         *
         * The callback takes 2 arguments:
         *
         * - The jQuery DOM form element
         * - The server JSON reponse
         *
         * Example: function callback(form, data) { ... }
         *
         * @method     addOnSuccessCallback
         * @param      {String}    url       The URL called to send the user data
         * @param      {Function}  callback  The callback function
         * @param      {Object}    context   The callback context
         */
        addOnSuccessCallback: function (url, callback, context) {
            this.callbacks.onSuccess[url] = {
                "callback": callback,
                "context" : context
            };
        },

        /**
         * Add a callback to process the url server fail JSON repsonse
         *
         * The callback takes 2 arguments:
         *
         * - The jQuery DOM form element
         * - The server JSON reponse
         *
         * Example: function callback(form, data) { ... }
         *
         * @method     addOnFailCallback
         * @param      {String}    url       The URL called to send the user data
         * @param      {Function}  callback  The callback function
         * @param      {Object}    context   The callback context
         */
        addOnFailCallback: function (url, callback, context) {
            this.callbacks.onFail[url] = {
                "callback": callback,
                "context" : context
            };
        },

        /**
         * Add a callback to process the url server fail XHR
         *
         * The callback takes 2 arguments:
         *
         * - The jQuery DOM form element
         * - The jQuery jqXHR object
         *
         * Example: function callback(form, jqXHR) { ... }
         *
         * @method     addOnRequestFailCallback
         * @param      {String}    url       The URL called to send the user data
         * @param      {Function}  callback  The callback function
         * @param      {Object}    context   The callback context
         */
        addOnRequestFailCallback: function (url, callback, context) {
            this.callbacks.onRequestFail[url] = {
                "callback": callback,
                "context" : context
            };
        },

        /**
         * Add a callback to process the form user inputs on submit
         *
         * The callback takes 2 arguments:
         *
         * - The jQuery DOM form element
         * - The user inputs as object
         *
         * Example: function callback(form, inputs) { ... }
         *
         * @method     addJsCallback
         * @param      {String}    callbackName  The callback function name
         * @param      {Function}  callback      The callback function
         * @param      {Object}    context       The callback context
         */
        addJsCallback: function (callbackName, callback, context) {
            this.jsCallbacks[callbackName] = {
                "callback": callback,
                "context" : context
            };
        }
    };

    return FormManager;
});
