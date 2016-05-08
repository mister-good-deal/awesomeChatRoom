/**
 * ClientManager module
 *
 * @module clientManager
 */

define([
    'jquery',
    'lodash',
    'module',
    'client'
], function ($, _, module, Client) {
    'use strict';

    /**
     * ClientManager module
     *
     * @param      {WebsocketManager}   WebSocket  The websocket manager
     * @param      {User}               user       The current User object to bind to the client
     * @param      {Object}             settings   Overriden settings
     *
     * @exports    clientManager
     *
     * @property   {Object}             settings    The clientManager global settings
     * @property   {WebsocketManager}   websocket   The WebsocketManager module
     * @property   {Client}             client      The current Client object
     *
     * @constructor
     * @alias      module:clientManager
     */
    var ClientManager = function (WebSocket, user, settings) {
        var self = this;

        this.settings  = $.extend(true, {}, this.settings, module.config(), settings);
        this.websocket = WebSocket;
        this.client    = new Client();
        this.client.setUser(user);
        // Set client geoloc
        // @todo seems that maximumAge value is not evaluated and the watch constant refresh every few seconds
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(
                _.bind(this.setLocation, this),
                _.bind(this.setLocationWithGeoip, this),
                {
                    "maximumAge"        : self.settings.locationRefreshInterval,
                    "timeout"           : self.settings.locationTimeout,
                    "enableHighAccuracy": true
                }
            );
        }
        // If the location is not set after 5 sec, sets it with geoIp service
        _.delay(_.bind(this.setLocationWithGeoip, this), 5000);
    };

    ClientManager.prototype = {
        /**
         * Get the current client
         *
         * @method     getCurrent
         * @return     {Client}  The current client
         */
        getCurrent: function () {
            return this.client;
        },

        /*==================================================================
        =            Actions that query to the WebSocket server            =
        ==================================================================*/

        /**
         * Update the client location
         *
         * @method     updateLocation
         */
        updateLocation: function () {
            this.websocket.send(JSON.stringify({
                "service" : [this.settings.serviceName],
                "action"  : "updateLocation",
                "location": this.client.getLocation() || []
            }));
        },

        /**
         * Update the client user
         *
         * @method     updateUser
         */
        updateUser: function () {
            this.websocket.send(JSON.stringify({
                "service": [this.settings.serviceName],
                "action" : "updateUser",
                "user"   : this.client.getUser().getAttributes()
            }));
        },

        /*=====  End of Actions that query to the WebSocket server  ======*/

        /*==================================================================
        =            Callbacks after WebSocket server responses            =
        ==================================================================*/

        /**
         * Handle the WebSocker server response and process action with the right callback
         *
         * @method     wsCallbackDispatcher
         * @param      {Object}  data    The server JSON reponse
         */
        wsCallbackDispatcher: function (data) {
            if (typeof this[data.action + 'Callback'] === 'function') {
                this[data.action + 'Callback'](data);
            }
        },

        /**
         * Add client information to the client object
         *
         * @method     connectCallback
         * @param      {Object}  data    The server JSON reponse
         */
        connectCallback: function (data) {
            this.client.setId(data.id);
            this.client.setConnection(data.connection);
        },

        /*=====  End of Callbacks after WebSocket server responses  ======*/

        /*=========================================
        =            Utilities methods            =
        =========================================*/

        /**
         * Set the location based on navigator.geolocation.getCurrentPosition returned object
         *
         * @method     setLocation
         * @param      {Object}  coordinates  The navigator.geolocation.getCurrentPosition returned object
         */
        setLocation: function (coordinates) {
            this.client.setLocation(coordinates);
            this.updateLocation();
        },

        /**
         * Set location based on MaxMind geoip database and the client IP address
         *
         * @method     setLocationWithGeoip
         */
        setLocationWithGeoip: function () {
            this.client.setLocationWithGeoip();
            this.updateLocation();
        }

        /*=====  End of Utilities methods  ======*/
    };

    return ClientManager;
});
