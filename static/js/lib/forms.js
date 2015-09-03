/**
 * Forms module
 *
 * @module lib/forms
 */

/*global define*/

define(['jquery', 'domReady!'], function ($) {
    'use strict';

    /**
     * FormsManager object
     *
     * @constructor
     * @alias       module:lib/forms
     * @param       {Message} Message  A Message object to output message in the IHM
     * @param       {object}  settings Overriden settings
     */
    var FormsManager = function (Message, settings) {
        this.settings = $.extend(true, {}, this.settings, settings);
        this.message  = Message;
        this.initEvents();
    };

    FormsManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the FormsManager will be instanciated
         */
        "settings" : {
        },
        /**
         * The currents xhr processing
         */
        "xhrs": [],
        /**
         * Callbacks methods to process specifics responses
         */
        "callbacks": {
            'onSuccess'    : [],
            'onFail'       : [],
            'onRequestFail': []
        },
        /**
         * A Message object to output message in the IHM
         */
        "message": {},

        /**
         * Bind events on all form
         */
        initEvents: function () {
            $('body').on('submit', 'form', $.proxy(this.submit, this));
        },

        /**
         * Send ajax request on form submit if data-ajax = true and process the result
         *
         * @param {event} e The form submit event
         */
        submit: function (e) {
            var form = $(e.currentTarget),
                self = this,
                url;

            if (form.attr('data-ajax') !== 'false') {
                e.preventDefault();

                url = form.attr('action');

                if (this.xhrs[url]) {
                    this.xhrs[url].abort();
                }

                this.xhrs[url] = $.ajax({
                    url     : url,
                    type    : form.attr('method'),
                    dataType: 'json',
                    data    : form.serialize(),
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
         * Processing method on server success response
         *
         * @param {object} form The jQuery DOM form element
         * @param {string} url  The URL called to send the user data
         * @param {object} data The server JSON reponse
         */
        onSuccess: function (form, url, data) {
            if (this.callbacks.onSuccess[url] && typeof this.callbacks.onSuccess[url].callback === 'function') {
                this.callbacks.onSuccess[url].callback.call(this.callbacks.onSuccess[url].context, form, data);
            }
        },

        /**
         * Processing method on server fail response
         *
         * @param {object} form The jQuery DOM form element
         * @param {string} url  The URL called to send the user data
         * @param {object} data The server JSON reponse
         */
        onFail: function (form, url, data) {
            if (this.callbacks.onFail[url] && typeof this.callbacks.onFail[url].callback === 'function') {
                this.callbacks.onFail[url].callback.call(this.callbacks.onFail[url].context, form, data);
            }
        },

        /**
         * Processing method on XHR error
         *
         * @param {object} form  The jQuery DOM form element
         * @param {string} url   The URL called to send the user data
         * @param {object} jqXHR The jQuery jqXHR object
         */
        onRequestFail: function (form, url, jqXHR) {
            if (this.callbacks.onRequestFail[url] && typeof this.callbacks.onRequestFail[url].callback === 'function') {
                this.callbacks.onRequestFail[url].callback.call(this.callbacks.onRequestFail[url].context, form, jqXHR);
            }
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
         * @param {string}   url      The URL called to send the user data
         * @param {function} callback The callback function
         * @param {object}   context  The callback context
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
         * @param {string}   url      The URL called to send the user data
         * @param {function} callback The callback function
         * @param {object}   context  The callback context
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
         * @param {string}   url      The URL called to send the user data
         * @param {function} callback The callback function
         * @param {object}   context  The callback context
         */
        addOnRequestFailCallback: function (url, callback, context) {
            this.callbacks.onRequestFail[url] = {
                "callback": callback,
                "context": context
            };
        }
    };

    return FormsManager;
});