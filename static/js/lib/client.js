/**
 * Client module
 *
 * @module               lib/client
 *
 * Client object to handle all client attributes
 */

define([
    'jquery',
    'module',
    'lodash'
], function ($, module, _) {
    'use strict';

    /**
     * Client object
     *
     * @class
     * @param      {Object}  attributes  JSON data representing the client attributes
     * @param      {Object}  settings    Overriden settings
     *
     * @alias      module:lib/client
     */
    var Client = function (attributes, settings) {
        this.settings   = $.extend(true, {}, this.settings, module.config(), settings);
        this.attributes = {};

        if (!_.isEmpty(attributes)) {
            this.setAttributes(attributes);
        }
    };

    Client.prototype = {
        /*=========================================
        =            Getters / setters            =
        =========================================*/

        /**
         * Get the client attributes
         *
         * @method     getAttributes
         * @return     {Object}  The client attributes as JSON
         */
        getAttributes: function () {
            return this.attributes;
        },

        /**
         * Set the Client object with a JSON parameter
         *
         * @method     setAttributes
         * @param      {Object}  data    JSON data
         */
        setAttributes: function (data) {
            this.attributes = $.extend(true, {}, this.attributes, data);
        },

        /**
         * Get the client connection
         *
         * @method     getConnection
         * @return     {Object}  The client connection
         */
        getConnection: function () {
            return this.attributes.connection;
        },

        /**
         * Set the client connection
         *
         * @method     setConnection
         * @param      {Object}  connection  The client connection
         */
        setConnection: function (connection) {
            this.attributes.connection = connection;
        },

        /**
         * Get the client ID
         *
         * @method     getId
         * @return     {String}  The client ID
         */
        getId: function () {
            return this.attributes.id;
        },

        /**
         * Set the client ID
         *
         * @method     setId
         * @param      {String}  Id      The client ID
         */
        setId: function (Id) {
            this.attributes.id = Id;
        },

        /**
         * Get the client user object
         *
         * @method     getUser
         * @return     {User}  The client user object
         */
        getUser: function () {
            return this.attributes.user;
        },

        /**
         * Set the client user object
         *
         * @method     setUser
         * @param      {User}  user    The client user object
         */
        setUser: function (user) {
            this.attributes.user = user;
        },

        /**
         * Get the client location
         *
         * @method     getLocation
         * @return     {Object}  The location in {"lat": "latitude", "lon": "longitude"} format
         */
        getLocation: function () {
            return this.attributes.location;
        },

        /**
         * Set the location based on navigator.geolocation.getCurrentPosition returned object
         *
         * @method     setLocation
         * @param      {Object}  coordinates  The navigator.geolocation.getCurrentPosition returned object
         */
        setLocation: function (coordinates) {
            this.attributes.location = {
                'lat': coordinates.coords.latitude,
                'lon': coordinates.coords.longitude
            };
        },

        /**
         * Set location based on MaxMind geoip database and the client IP address
         *
         * @method     setLocationWithGeoip
         */
        setLocationWithGeoip: function () {
            var self = this;

            if (_.isEmpty(this.attributes.location)) {
                $.getJSON('GeoIp/getLocation', function (location) {
                    self.attributes.location = location;
                });
            }
        }

        /*=====  End of Getters / setters  ======*/
    };

    return Client;
});
