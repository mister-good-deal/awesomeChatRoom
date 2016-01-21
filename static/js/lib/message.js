/**
 * Message module
 *
 * @module lib/message
 */

define([
    'jquery',
    'module'
], function ($, module) {
    'use strict';

    /**
     * Message object
     *
     * @constructor
     * @alias       module:lib/message
     * @param       {Object} settings Overriden settings
     */
    var Message = function (settings) {
        this.settings  = $.extend(true, {}, this.settings, settings);
        this.initEvents();
    };

    Message.prototype = {
        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "alert": {
                "divId"          : module.config().alert.divId,
                "dismissClass"   : module.config().alert.dismissClass,
                "defaultDuration": module.config().alert.defaultDuration,
                "queue"          : [],
                "lock"           : false
            },
            "popup": {
                "divId"          : module.config().popup.divId,
                "dismissClass"   : module.config().popup.dismissClass,
                "defaultDuration": module.config().popup.defaultDuration,
                "queue"          : [],
                "lock"           : false
            },
            "notification": {
                "divId"          : module.config().notification.divId,
                "dismissClass"   : module.config().notification.dismissClass,
                "defaultDuration": module.config().notification.defaultDuration,
                "queue"          : [],
                "lock"           : false
            },
            "serviceName" : module.config().serviceName,
            "defaultType" : module.config().defaultType,
            "defaultLevel": module.config().defaultLevel
        },

        /**
         * Initialize events
         */
        initEvents: function () {
            $('body').on(
                'click',
                this.settings.alert.divId + ' ' + this.settings.alert.dismissClass,
                $.proxy(this.alertDismiss, this)
            );

            $('body').on(
                'click',
                this.settings.popup.divId + ' ' + this.settings.popup.dismissClass,
                $.proxy(this.popupDismiss, this)
            );

            $('body').on(
                'click',
                this.settings.notification.divId + ' ' + this.settings.notification.dismissClass,
                $.proxy(this.notificationDismiss, this)
            );
        },

        /**
         * Display a message on a large alert div at the top of the user screen
         *
         * @param {Object} message The message to display
         */
        alert: function (message) {
            this.settings.alert.lock = true;
            // var alert = $('#' + this.settings.alert.divId);
            console.log('alert', message);
        },

        /**
         * Display a message on a modal to the user screen
         *
         * @param {Object} message The message to display
         */
        popup: function (message) {
            this.settings.popup.lock = true;
            console.log('popup', message);
        },

        /**
         * Display a message on a medium div at the bottom-right of the user screen
         *
         * @param {Object} message The message to display
         */
        notification: function (message) {
            this.settings.notification.lock = true;
            console.log('notification', message);
        },

        /**
         * Close the alert message
         */
        alertDismiss: function () {
            if (this.settings.alert.lock) {
                console.log('alert dismiss');
                this.settings.alert.lock = false;
                this.dequeueMessage('alert');
            }
        },

        /**
         * Close the popup message
         */
        popupDismiss: function () {
            if (this.settings.popup.lock) {
                console.log('popup dismiss');
                this.settings.popup.lock = false;
                this.dequeueMessage('popup');
            }
        },

        /**
         * Close the notification message
         */
        notificationDismiss: function () {
            if (this.settings.notification.lock) {
                console.log('notification dismiss');
                this.settings.notification.lock = false;
                this.dequeueMessage('notification');
            }
        },

        /**
         * Add a message in a specific queue to display it
         *
         * @param {String}  text     The message text to display
         * @param {String}  type     The message type ("alert", "popup", "notification")
         * @param {String}  level    The message level ("danger", "warning", "info", "success")
         * @param {String}  title    The message title
         * @param {Number} duration The message maximum duration before dismiss (-1 for infinite)
         */
        add: function (text, type, level, title, duration) {
            if (!type) {
                type = this.settings.defaultType;
            }

            this.settings[type].queue.push({
                "text"    : text,
                "level"   : level    || this.settings.defaultLevel,
                "title"   : title    || '',
                "duration": duration || this.settings[type].defaultDuration
            });

            this.dequeueMessage(type);
        },

        /**
         * Parse the WebSocket server response to notify it
         *
         * @param {Object} data JSON encoded data recieved from the WebSocket server
         */
        parseWebsocketData: function (data) {
            this.add(data.text, data.type, data.level, data.title, data.duration);
        },

        /**
         * Dequeue a message from the specific queue if the queue is not empty and the queue is not locked
         *
         * @param {String} type The message type ("alert", "popup", "notification")
         */
        dequeueMessage: function (type) {
            var message,
                dismissMethod,
                self;

            if (!this.settings[type].lock) {
                message       = this.settings[type].queue.shift();
                dismissMethod = type + 'Dismiss';
                self          = this;

                if (message) {
                    // Call the specific method to output the message
                    this[type](message);

                    // Auto dismiss the message after message.duration seconds
                    setTimeout(function () {
                        self[dismissMethod]();
                    }, message.duration * 1000);
                }
            }
        }
    };

    return Message;
});
