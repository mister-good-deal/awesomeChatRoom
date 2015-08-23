/**
 * Websocket module
 *
 * @module lib/websocket
 */
define(['jquery', 'module'], function($, module) {
    'use strict';

    /**
     * Websocket manager class
     *
     * @constructor
     * @alias       module:lib/websocket
     * @param       {object} settings Overriden settings
     */
    var WebsocketManager = function (settings) {
        this.settings = $.extend(true, {}, this.settings, settings);
        this.init();
    };

    WebsocketManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "serverUrl": module.config().serverUrl
        },
        "socket": {},

        /**
         * Launch the WebSocket server
         */
        init: function () {
            this.connect();
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
         * Shorthand method to send data to teh server
         *
         * @param {string} data Data to send to the server
         */
        send: function (data) {
            this.socket.send(data);
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
            console.log(JSON.parse(data));
        },

        /**
         * Add a service to the WebSocket server
         *
         * @param {string} serviceName The service name to add to the WebSocket server
         * @param {string} login       The user login
         * @param {string} password    The user password
         */
        addService: function (serviceName, login, password) {
            this.socket.send(JSON.stringify({
                "action"    : "manageServer",
                "login"     : login,
                "password"  : password,
                "addService": serviceName
            }));
        },

        /**
         * Remove a service from the WebSocket server
         *
         * @param {string} serviceName The service name to remove from the WebSocket server
         * @param {string} login       The user login
         * @param {string} password    The user password
         */
        removeService: function (serviceName, login, password) {
            this.socket.send(JSON.stringify({
                "action"       : "manageServer",
                "login"        : login,
                "password"     : password,
                "removeService": serviceName
            }));
        },

        /**
         * Query the currents running services from the WebSocket server
         *
         * @param {string} login    The user login
         * @param {string} password The user password
         */
        listServices: function (login, password) {
            this.socket.send(JSON.stringify({
                "action"      : "manageServer",
                "login"       : login,
                "password"    : password,
                "listServices": true
            }));
        }
    };

    return WebsocketManager;
});