define(['jquery'], function($) {
    'use strict';

    var WebsocketManager = function (o) {
        this.settings = $.extend(true, {}, this.defaults, o);
        this.init();
    };

    WebsocketManager.prototype = {
        "settings": {},
        "socket"  : {},
        "defaults": {
            "serverUrl": "ws://127.0.0.1:5000",
        },

        init: function () {
            this.connect();
        },

        connect: function () {
            try {
                this.socket = new WebSocket(this.settings.serverUrl);

                this.socket.onopen = function () {
                    console.log('socket opened');
                }

                this.socket.onclose = function () {
                    console.log('socket closed');
                }

                this.socket.onerror = function () {
                    console.log('socket error');
                }

                this.socket.onmessage = function (message) {
                    this.treatData(message.data)
                }
            } catch (error) {
                console.log(error);
            }
        },

        sendMessage: function (message) {
            this.socket.send(JSON.stringify({
                "action": "chat",
                "message": message
            }));
        },

        treatData: function (data) {
            console.log('Server: ' +  data);
        }
    };

    window.WebsocketManager = new WebsocketManager();
});