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
     * @param       {Object}  settings Overriden settings
     *
     * @exports    websocketManager
     *
     * @property   {User}       User        The curent user
     * @property   {Object}     settings    The websocketManager global settings
     *
     * @constructor
     * @alias       module:websocketManager
     */
    var WebsocketManager = function (User, settings) {
            this.settings = $.extend(true, {}, this.settings, settings);
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
         * @param      {String}  data    Data to send to the WebSocket server
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
         * Treat data recieved from the WebSocket server
         *
         * @method     treatData
         * @param      {String}  data    The text data recieved from the WebSocket server
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
         * Add a callback to process data recieved from the WebSocket server
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
         * @param      {Object}  data    JSON encoded data recieved from the WebSocket server
         */
        listServiceCallback: function (data) {
            console.log(data.services);
        }
    };

    return WebsocketManager;
});
