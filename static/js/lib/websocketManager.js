/**
 * WebsocketManager module
 *
 * @module websocketManager
 */
define([
    'jquery',
    'module'
], function ($, module) {
    'use strict';

    /**
     * WebsocketManager module
     *
     * @param       {User}    User     The current User
     * @param       {Object}  settings Overridden settings
     *
     * @exports     websocketManager
     *
     * @property   {Object}     settings    The websocketManager global settings
     * @property   {User}       User        The current user
     * @property   {WebSocket}  socket      The websocket resource
     * @property   {Object}     callbacks   Callbacks method to process data received from the WebSocket server
     *
     * @constructor
     * @alias       module:websocketManager
     */
    var WebsocketManager = function (User, settings) {
            this.settings  = $.extend(true, {}, this.settings, module.config(), settings);
            this.user      = User;
            this.socket    = {};
            this.callbacks = {};
            this.init();
        };

    WebsocketManager.prototype = {
        /**
         * Launch the WebSocket server and add the WebSocket server callbacks
         */
        init: function () {
            this.connect();
            this.addCallback(this.settings.serviceName, this.listServiceCallback, this);
        },

        /**
         * Connect the client to the WebSocket server
         *
         * @method     connect
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
         * @method     send
         * @param      {Object}  data    Data to send to the WebSocket server
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
         *
         * @method     disconnect
         */
        disconnect: function () {
            this.socket.send(JSON.stringify({
                "action": "disconnect"
            }));
        },

        /**
         * Treat data received from the WebSocket server
         *
         * @method     treatData
         * @param      {String}  data    The text data received from the WebSocket server
         */
        treatData: function (data) {
            data = JSON.parse(data);
            console.log(data);

            if (data.service && this.callbacks[data.service]) {
                this.callbacks[data.service].callback.call(
                    this.callbacks[data.service].context,
                    data
                );
            } else {
                console.log(data);
            }
        },

        /**
         * Add a callback to process data received from the WebSocket server
         *
         * @method     addCallback
         * @param      {String}    serviceName  The callback service name
         * @param      {Function}  callback     The callback function
         * @param      {Object}    context      The callback context
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
         * @method     addService
         * @param      {String}  serviceName  The service name to add to the WebSocket server
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
         * @method     removeService
         * @param      {String}  serviceName  The service name to remove from the WebSocket server
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
         *
         * @method     listServices
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
         * @method     listServiceCallback
         * @param      {Object}  data    JSON encoded data received from the WebSocket server
         */
        listServiceCallback: function (data) {
            console.log(data.services);
        }
    };

    return WebsocketManager;
});
