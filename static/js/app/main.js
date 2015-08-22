define(['jquery'], function($) {
    'use strict';

    var WebsocketManager = function (o) {
        this.settings = $.extend(true, {}, this.defaults, o);
        this.init();
    };

    WebsocketManager.prototype = {
        /**
         * Default settings will get overriden if they are set when the WebsocketManager will be instanciated
         */
        "settings": {
            "serverUrl"     : "",
            "serverPassword": ""
        },
        "defaults": {
            "serverUrl": "ws://127.0.0.1:5000"
        },
        "socket"  : {},

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
         * Disconnect the client from the WebSocket server
         */
        disconnect: function () {
            this.socket.send(JSON.stringify({
                "action": "disconnect"
            }));
        },

        /**
         * Send a text message to the WebSocket server
         *
         * @param {string} message The text message to send to the WebSocket server
         */
        sendMessage: function (message) {
            this.socket.send(JSON.stringify({
                "action" : "chat",
                "message": message
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
         */
        addService: function (serviceName) {
            this.socket.send(JSON.stringify({
                "action"    : "manageServer",
                "password"  : this.settings.serverPassword,
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
                "password"     : this.settings.serverPassword,
                "removeService": serviceName
            }));
        },

        /**
         * Query the currents running services from the WebSocket server
         */
        listServices: function () {
            this.socket.send(JSON.stringify({
                "action"      : "manageServer",
                "password"    : this.settings.serverPassword,
                "listServices": true
            }));
        },

        /**
         * Set the WebSocket server password
         *
         * @param {text} password The WebSocket server password
         */
        setServerPassword: function (password) {
            this.settings.serverPassword = password;
        }
    };

    window.WebsocketManager = new WebsocketManager();
});