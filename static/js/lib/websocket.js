/**
 * Websocket module
 *
 * @module lib/websocket
 */

/*global define, WebSocket*/

define(['jquery', 'module'], function ($, module) {
    'use strict';

    /**
     * Websocket manager class
     *
     * @constructor
     * @alias       module:lib/websocket
     * @param       {Message} Message  A Message object to output message in the IHM
     * @param       {User}    User     The current User
     * @param       {object}  settings Overriden settings
     */
    var WebsocketManager = function (Message, User, settings) {
        this.settings = $.extend(true, {}, this.settings, settings);
        this.message  = Message;
        this.user     = User;
        this.init();
    };

    WebsocketManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "serverUrl"   : module.config().serverUrl,
            "serviceName" : module.config().serviceName,
            "waitInterval": module.config().waitInterval
        },
        /**
         * The websocket ressource
         */
        "socket": {},
        /**
         * A Message object to output message in the IHM
         */
        "message": {},
        /**
         * The current User instance
         */
        "user": {},
        /**
         * Callbacks method to process data recieved from the WebSocket server
         */
        "callbacks": {},

        /**
         * Launch the WebSocket server and add the WebSocket server callbacks
         */
        init: function () {
            this.connect();
            this.addCallback(this.settings.serviceName, this.listServiceCallback, this);
        },

        /**
         * Connect the client to the WebSocket server
         */
        connect: function () {
            var self = this;

            try {
                this.socket = new WebSocket(this.settings.serverUrl);

                this.socket.onopen = function () {
                    console.log('socket opened');
                };

                this.socket.onclose = function () {
                    console.log('socket closed');
                };

                this.socket.onerror = function () {
                    console.log('socket error');
                };

                this.socket.onmessage = function (message) {
                    self.treatData(message.data);
                };
            } catch (error) {
                console.log(error);
            }
        },

        /**
         * Shorthand method to send data to the WebSocket server or delay until the websocket is ready
         *
         * @param {string} data Data to send to the WebSocket server
         */
        send: function (data) {
            var self = this;

            if (this.socket.readyState !== 1) {
                setTimeout(function () {
                    self.send(data);
                }, self.settings.waitInterval);
            } else {
                this.socket.send(data);
            }
        },

        /**
         * Disconnect the client from the WebSocket server
         */
        disconnect: function () {
            this.socket.send(JSON.stringify({
                "action": "disconnect"
            }));
        },

        /**
         * Treat data recieved from the WebSocket server
         *
         * @param {string} data The text data recieved from the WebSocket server
         */
        treatData: function (data) {
            data = JSON.parse(data);

            if (data.service && this.callbacks[data.service]) {
                this.callbacks[data.service].callback.call(
                    this.callbacks[data.service].context,
                    data
                );
            }
        },

        /**
         * Add a callback to process data recieved from the WebSocket server
         *
         * @param {string}   serviceName The callback service name
         * @param {function} callback    The callback function
         * @param {object}   context     The callback context
         */
        addCallback: function (serviceName, callback, context) {
            this.callbacks[serviceName] = {
                "callback": callback,
                "context" : context
            };
        },

        /**
         * Add a service to the WebSocket server
         *
         * @param {string} serviceName The service name to add to the WebSocket server
         */
        addService: function (serviceName) {
            this.socket.send(JSON.stringify({
                "action"    : "manageServer",
                "login"     : this.user.getEmail(),
                "password"  : this.user.getPassword(),
                "addService": serviceName
            }));
        },

        /**
         * Remove a service from the WebSocket server
         *
         * @param {string} serviceName The service name to remove from the WebSocket server
         */
        removeService: function (serviceName) {
            this.socket.send(JSON.stringify({
                "action"       : "manageServer",
                "login"        : this.user.getEmail(),
                "password"     : this.user.getPassword(),
                "removeService": serviceName
            }));
        },

        /**
         * Query the currents running services from the WebSocket server
         */
        listServices: function () {
            this.socket.send(JSON.stringify({
                "action"      : "manageServer",
                "login"       : this.user.getEmail(),
                "password"    : this.user.getPassword(),
                "listServices": true
            }));
        },

        /**
         * Parse the WebSocket server response to retrieve the services list
         *
         * @param {object} data JSON encoded data recieved from the WebSocket server
         */
        listServiceCallback: function (data) {
            console.log(data.services);
        }
    };

    return WebsocketManager;
});