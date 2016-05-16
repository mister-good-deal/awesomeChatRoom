/**
 * ChatManager module
 *
 * @module chatManager
 */

define([
    'jquery',
    'module',
    'lodash',
    'notification',
    'bootstrap'
], function ($, module, _, Notification) {
    'use strict';

    /**
     * ChatManager module
     *
     * @param       {WebsocketManager}  WebsocketManager    The websocket manager
     * @param       {Client}            Client              The current Client
     * @param       {FormManager}       Forms               A FormsManager to handle form XHR ajax calls or jsCallbacks
     * @param       {Object}            settings            Overridden settings
     *
     * @exports     chatManager
     * @see         module:websocketManager
     * @see         module:client
     * @see         module:formManager
     * @see         module:notification
     *
     * @property   {Object}             settings                The chatManager global settings
     * @property   {WebsocketManager}   websocket               The WebsocketManager module
     * @property   {Client}             client                  The current Client
     * @property   {Notification}       notification            The Notification module
     * @property   {Object}             messagesCurrent         The current user message (not sent) by room
     * @property   {Object}             messagesHistory         A messages sent history by room
     * @property   {Object}             messagesHistoryPointer  Pointer in the array messagesHistory by room
     * @property   {Object}             lastMessageLoadedTime   The last loaded message timestamp received by room
     * @property   {Object}             promises                Global promises handler
     *
     * @constructor
     * @alias       module:chatManager
     *
     * @todo ._assign instead of $.extend ?
     */
    var ChatManager = function (WebsocketManager, Client, Forms, settings) {
        this.settings               = $.extend(true, {}, this.settings, module.config(), settings);
        this.websocketManager       = WebsocketManager;
        this.client                 = Client;
        this.notification           = new Notification();
        this.messagesCurrent        = {};
        this.messagesHistory        = {};
        this.messagesHistoryPointer = {};
        this.lastMessageLoadedTime  = {};
        this.promises               = {
            "setReason": $.Deferred()
        };
        // Add forms callback
        Forms.addJsCallback('setReasonCallbackEvent', this.setReasonCallbackEvent, this);
        Forms.addJsCallback('setRoomInfoCallbackEvent', this.setRoomInfoCallbackEvent, this);

        this.initEvents();
    };

    ChatManager.prototype = {
        /*==============================
        =            Events            =
        ==============================*/

        /**
         * Initialize all the events
         *
         * @method     initEvents
         */
        initEvents: function () {
            // Listen the "enter" keypress event on the chat text input
            $('body').on(
                'keydown',
                this.settings.selectors.global.room + ' ' +
                    this.settings.selectors.roomSend.div + ' ' +
                    this.settings.selectors.roomSend.message,
                $.proxy(this.chatTextKeyPressEvent, this)
            );
            // Send a message in a room
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' +
                    this.settings.selectors.roomSend.div + ' ' +
                    this.settings.selectors.roomSend.send,
                $.proxy(this.sendMessageEvent, this)
            );
            // Load more messages in a room
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' + this.settings.selectors.roomAction.loadHistoric,
                $.proxy(this.getHistoricEvent, this)
            );
            // Select a receiver for the chat message
            $('body').on(
                'click',
                this.settings.selectors.global.room + ' ' + this.settings.selectors.roomSend.usersList + ' li a',
                $.proxy(this.selectUserEvent, this)
            );
        },

        /**
         * Event fired when a user press a key in a chat message input
         *
         * @method     chatTextKeyPressEvent
         * @param      {event}  e       The fired event
         */
        chatTextKeyPressEvent: function (e) {
            var roomId = $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-id');

            if (e.which === 13) {
                // Enter key pressed
                this.sendMessageEvent(e);
            } else if (e.which === 38) {
                // Up arrow key pressed
                if (this.messagesHistoryPointer[roomId] > 0) {
                    if (this.messagesHistoryPointer[roomId] === this.messagesHistory[roomId].length) {
                        this.messagesCurrent[roomId] = $(e.currentTarget).val();
                    }

                    $(e.currentTarget).val(this.messagesHistory[roomId][--this.messagesHistoryPointer[roomId]]);
                }
            } else if (e.which === 40) {
                // Down arrow key pressed
                if (this.messagesHistoryPointer[roomId] + 1 < this.messagesHistory[roomId].length) {
                    $(e.currentTarget).val(this.messagesHistory[roomId][++this.messagesHistoryPointer[roomId]]);
                } else if (this.messagesHistoryPointer[roomId] + 1 === this.messagesHistory[roomId].length) {
                    this.messagesHistoryPointer[roomId]++;
                    $(e.currentTarget).val(this.messagesCurrent[roomId]);
                }
            }
        },

        /**
         * Event fired when a user wants to send a message
         *
         * @method     sendMessageEvent
         * @param      {event}  e       The fired event
         */
        sendMessageEvent: function (e) {
            var sendDiv      = $(e.currentTarget).closest(this.settings.selectors.chatSend.div),
                receivers    = sendDiv.find(this.settings.selectors.chatSend.receivers).attr('data-value'),
                messageInput = sendDiv.find(this.settings.selectors.chatSend.message),
                message      = _.trim(messageInput.val()),
                roomId       = $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-id');

            if (message !== '') {
                if (!this.isCommand(message, roomId)) {
                    this.sendMessage(roomId, message, receivers);
                }

                this.messagesHistory[roomId].push(message);
                this.messagesHistoryPointer[roomId]++;
                messageInput.val('');
            }

            e.preventDefault();
        },

        /**
         * Event fired when a user wants to get more historic of a conversation
         *
         * @method     getHistoricEvent
         * @param      {event}  e       The fired event
         */
        getHistoricEvent: function (e) {
            var roomId = $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-id');

            this.getHistoric(roomId);
        },

        /**
         * Event fired when a user wants to select a receiver for his message
         *
         * @method     selectUserEvent
         * @param      {event}  e       The fired event
         */
        selectUserEvent: function (e) {
            var value     = $(e.currentTarget).closest('li').attr('data-value'),
                receivers = $(e.currentTarget).closest(this.settings.selectors.chatSend.usersList)
                    .siblings(this.settings.selectors.chatSend.receivers);

            receivers.attr('data-value', value);
            receivers.find('.value').text(value);

            e.preventDefault();
        },

        /*=====  End of Events  ======*/

        /*==================================================================
        =            Actions that query to the WebSocket server            =
        ==================================================================*/

        /**
         * Send a message to all the users in the chat room or at one user in the chat room
         *
         * @method     sendMessage
         * @param      {Number}  roomId     The chat room ID
         * @param      {String}  message    The text message to send
         * @param      {String}  receivers  The message receiver ('all' || userPseudonym)
         */
        sendMessage: function (roomId, message, receivers) {
            this.websocket.send(JSON.stringify({
                "service"  : [this.settings.serviceName],
                "action"   : "sendMessage",
                "roomId"   : roomId,
                "message"  : message,
                "receivers": receivers
            }));
        },

        /**
         * Get room chat historic
         *
         * @method     getHistoric
         * @param      {Number}  roomId The room ID
         */
        getHistoric: function (roomId) {
            this.websocket.send(JSON.stringify({
                "service"        : [this.settings.serviceName],
                "action"         : "getHistoric",
                "roomId"         : roomId,
                "lastMessageDate": this.lastMessageLoadedTime[roomId] || null
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
            } else if (data.text) {
                this.notification.add(data.text);
            }
        },

        /**
         * Callback after a user recieved a message
         *
         * @method     recieveMessageCallback
         * @param      {Object}  data    The server JSON reponse
         */
        recieveMessageCallback: function (data) {
            var room                = $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]'),
                roomChat            = room.find(this.settings.selectors.global.roomChat),
                messagesUnread      = room.find(this.settings.selectors.global.roomMessagesUnread),
                messagesUnreadValue = messagesUnread.text();

            roomChat.append(this.formatUserMessage(data));

            if (this.isRoomOpened[data.roomId] && !this.mouseInRoomChat[data.roomId]) {
                roomChat.scrollTop(room.height());
                messagesUnread.text('');
            } else {
                if (messagesUnreadValue === '') {
                    messagesUnread.text('1');
                } else {
                    messagesUnread.text(++messagesUnreadValue);
                }
            }
        },

        /**
         * Callback after a user sent a message
         *
         * @method     sendMessageCallback
         * @param      {Object}  data    The server JSON reponse
         */
        sendMessageCallback: function (data) {
            if (!data.success) {
                this.notification.add(data.text);
            }
        },

        /**
         * Callback after a user attempted to laod more historic of a conversation
         *
         * @method     getHistoricCallback
         * @param      {Object}  data    The server JSON reponse
         */
        getHistoricCallback: function (data) {
            var room     = $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]'),
                roomChat = room.find(this.settings.selectors.global.roomChat);

            if (data.success) {
                this.loadHistoric(roomChat, data.historic);
            }

            this.notification.add(data.text);
        },

        /*=====  End of Callbacks after WebSocket server responses  ======*/

        /*=========================================
        =            Utilities methods            =
        =========================================*/

        /**
         * Load conversations historic sent by the server
         *
         * @method     loadHistoric
         * @param      {Object}  roomChatDOM  The room chat jQuery DOM element to insert the conversations historic in
         * @param      {Array}   historic     The conversations historic
         */
        loadHistoric: function (roomChatDOM, historic) {
            var historicLength = historic.length;

            if (historicLength > 0) {
                roomChatDOM.attr('data-last-message-date', historic[historicLength - 1].date);
                roomChatDOM.prepend(this.formatUserMessage({"messages": historic}));
            } else {
                // @todo button to load or automatic ? Alert user when there are no more message
                roomChatDOM.find(this.settings.selectors.roomAction.loadHistoric).remove();
            }
        },

        /**
         * Format a user message in a html div
         *
         * @method     formatUserMessage
         * @param      {Object}  data    The server JSON reponse
         * @return     {Array}   Array of jQuery html div(s) object containing the user message(s)
         */
        formatUserMessage: function (data) {
            var divs = [],
                self = this;

            if (!_.isArray(data.messages)) {
                data.messages = [data];
            }

            _.forEachRight(data.messages, function (message) {
                divs.push(
                    $('<div>', {
                        "class": self.settings.selectors.chat.message.substr(1) + ' ' + message.type
                    }).append(
                        $('<span>', {
                            "class": self.settings.selectors.chat.date.substr(1),
                            "text" : '[' + new Date(_.toInteger(message.date)).toLocaleString() + ']'
                        }),
                        $('<span>', {
                            "class": self.settings.selectors.chat.pseudonym.substr(1),
                            "text" : message.pseudonym
                        }),
                        $('<span>', {
                            "class": self.settings.selectors.chat.text.substr(1),
                            "text" : message.message
                        })
                    )
                );
            });

            return divs;
        },

        /**
         * Check if the user input is a command and process it
         *
         * @method     isCommand
         * @param      {String}   message   The user input
         * @param      {String}   roomName  The room name
         * @param      {String}   password  The room password
         * @return     {Boolean}  True if the user input was a command else false
         */
        isCommand: function (message, roomName, password) {
            var isCommand = false,
                self      = this,
                regexResult;

            _.forEach(this.settings.commands, function (regex, name) {
                regexResult = regex.exec(message);

                if (regexResult !== null) {
                    isCommand = true;

                    switch (name) {
                    case 'kick':
                        self.kickUser(roomName, regexResult[1], regexResult[2] || '');

                        break;

                    case 'pm':
                        self.sendMessage(regexResult[1], regexResult[2], roomName, password);

                        break;
                    }

                    return false;
                }
            });

            return isCommand;
        }

        /*=====  End of Utilities methods  ======*/
    };

    return ChatManager;
});
