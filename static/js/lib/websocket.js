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
            "serverUrl": module.config().serverUrl
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
            data = JSON.parse(data);

            switch (data.action) {
                case 'addService':
                    if (data.success) {
                        console.log('Service "' + data.serviceName + '" is now running');
                    } else {
                        console.log('ERROR: ' + data.errors);
                    }
                    
                    break;
            }
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
        }
    };

    return WebsocketManager;
});