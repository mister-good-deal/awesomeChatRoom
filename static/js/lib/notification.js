/**
 * Notification module
 *
 * @module notification
 */

define([
    'jquery',
    'module'
], function ($, module) {
    'use strict';

    /**
     * Notification module
     *
     * @param       {Object} settings Overriden settings
     *
     * @exports    notification
     *
     * @constructor
     * @alias       module:notification
     */
    var Notification = function (settings) {
        this.settings = $.extend(true, {}, this.settings, module.config(), settings);
    };

    Notification.prototype = {
        /**
         * Initialize events
         *
         * @method     initEvents
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
         * Display a notification on a large alert div at the top of the user screen
         *
         * @method     alert
         * @param      {Object}  notification  The notification to display
         */
        alert: function (notification) {
            this.settings.alert.lock = true;
            // Var alert = $('#' + this.settings.alert.divId);
            console.log('alert', notification);
        },

        /**
         * Display a notification on a modal to the user screen
         *
         * @method     popup
         * @param      {Object}  notification  The notification to display
         */
        popup: function (notification) {
            this.settings.popup.lock = true;
            console.log('popup', notification);
        },

        /**
         * Display a notification on a medium div at the bottom-right of the user screen
         *
         * @method     notification
         * @param      {Object}  notification  The notification to display
         */
        notification: function (notification) {
            this.settings.notification.lock = true;
            console.log('notification', notification);
        },

        /**
         * Close the alert notification
         *
         * @method     alertDismiss
         */
        alertDismiss: function () {
            if (this.settings.alert.lock) {
                console.log('alert dismiss');
                this.settings.alert.lock = false;
                this.dequeueNotification('alert');
            }
        },

        /**
         * Close the popup notification
         *
         * @method     popupDismiss
         */
        popupDismiss: function () {
            if (this.settings.popup.lock) {
                console.log('popup dismiss');
                this.settings.popup.lock = false;
                this.dequeueNotification('popup');
            }
        },

        /**
         * Close the notification notification
         *
         * @method     notificationDismiss
         */
        notificationDismiss: function () {
            if (this.settings.notification.lock) {
                console.log('notification dismiss');
                this.settings.notification.lock = false;
                this.dequeueNotification('notification');
            }
        },

        /**
         * Add a notification in a specific queue to display it
         *
         * @method     add
         * @param      {String}  text           The notification text to display
         * @param      {String}  [type=alert]   The notification type ("alert", "popup", "notification")
         * @param      {String}  [level=info]   The notification level ("danger", "warning", "info", "success")
         * @param      {String}  [title='']     The notification title
         * @param      {Number}  [duration=2]   The notification maximum duration in second before dismiss (-1 for infinite)
         */
        add: function (text, type, level, title, duration) {
            if (!type) {
                type = this.settings.defaultType;
            }

            this.settings[type].queue.push({
                "text"    : text,
                "level"   : level || this.settings.defaultLevel,
                "title"   : title || '',
                "duration": duration || this.settings[type].defaultDuration
            });

            this.dequeueNotification(type);
        },

        /**
         * Parse the WebSocket server response to notify it
         *
         * @method     parseWebsocketData
         * @param      {Object}  data    JSON encoded data recieved from the WebSocket server
         */
        parseWebsocketData: function (data) {
            this.add(data.text, data.type, data.level, data.title, data.duration);
        },

        /**
         * Dequeue a notification from the specific queue if the queue is not empty and the queue is not locked
         *
         * @method     dequeueNotification
         * @param      {String}  type    The notification type ("alert", "popup", "notification")
         */
        dequeueNotification: function (type) {
            var notification,
                dismissMethod,
                self;

            if (!this.settings[type].lock) {
                notification  = this.settings[type].queue.shift();
                dismissMethod = type + 'Dismiss';
                self          = this;

                if (notification) {
                    // Call the specific method to output the notification
                    this[type](notification);
                    // Auto dismiss the notification after notification.duration seconds
                    setTimeout(function () {
                        self[dismissMethod]();
                    }, notification.duration * 1000);
                }
            }
        }
    };

    return Notification;
});
