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
     * @param       {Object}            rooms               Collection of room module
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
     * @property   {Object}             rooms                   Collection of room module
     * @property   {Notification}       notification            The Notification module
     * @property   {Object}             messagesCurrent         The current user message (not sent) by room
     * @property   {Object}             messagesHistory         A messages sent history by room
     * @property   {Object}             messagesHistoryPointer  Pointer in the array messagesHistory by room
     * @property   {Object}             lastMessageLoadedTime   The last loaded message timestamp received by room
     *
     * @constructor
     * @alias       module:chatManager
     *
     * @todo ._assign instead of $.extend ?
     */
    var ChatManager = function (WebsocketManager, Client, rooms, Forms, settings) {
        this.settings               = $.extend(true, {}, this.settings, module.config(), settings);
        this.websocketManager       = WebsocketManager;
        this.client                 = Client;
        this.rooms                  = rooms;
        this.notification           = new Notification();
        this.messagesCurrent        = {};
        this.messagesHistory        = {};
        this.messagesHistoryPointer = {};
        this.lastMessageLoadedTime  = {};
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
            var body = $('body');
            // Listen the "enter" keypress event on the chat text input
            body.on(
                'keydown',
                this.settings.selectors.global.room + ' ' +
                    this.settings.selectors.chatSend.div + ' ' +
                    this.settings.selectors.chatSend.message,
                $.proxy(this.chatTextKeyPressEvent, this)
            );
            // Send a message in a room
            body.on(
                'click',
                this.settings.selectors.global.room + ' ' +
                    this.settings.selectors.chatSend.div + ' ' +
                    this.settings.selectors.chatSend.send,
                $.proxy(this.sendMessageEvent, this)
            );
            // Load more messages in a room
            body.on(
                'click',
                this.settings.selectors.global.room + ' ' + this.settings.selectors.chatAction.loadHistoric,
                $.proxy(this.getHistoricEvent, this)
            );
            // Select a receiver for the chat message
            body.on(
                'click',
                this.settings.selectors.global.room + ' ' + this.settings.selectors.chatSend.usersList + ' li a',
                $.proxy(this.selectUserEvent, this)
            );
        },

        /**
         * Event fired when a user press a key in a chat message input
         *
         * @method     chatTextKeyPressEvent
         * @param      {Event}  e       The fired event
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
         * @param      {Event}  e       The fired event
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
         * @param      {Event}  e       The fired event
         */
        getHistoricEvent: function (e) {
            var roomId = $(e.currentTarget).closest(this.settings.selectors.global.room).attr('data-id');

            this.getHistoric(roomId);
        },

        /**
         * Event fired when a user wants to select a receiver for his message
         *
         * @method     selectUserEvent
         * @param      {Event}  e       The fired event
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
            this.websocket.send({
                "service"  : [this.settings.serviceName],
                "action"   : "sendMessage",
                "roomId"   : roomId,
                "message"  : message,
                "receivers": receivers
            });
        },

        /**
         * Get room chat historic
         *
         * @method     getHistoric
         * @param      {Number}  roomId The room ID
         */
        getHistoric: function (roomId) {
            this.websocket.send({
                "service"        : [this.settings.serviceName],
                "action"         : "getHistoric",
                "roomId"         : roomId,
                "lastMessageDate": this.lastMessageLoadedTime[roomId] || null
            });
        },

        /*=====  End of Actions that query to the WebSocket server  ======*/

        /*==================================================================
        =            Callbacks after WebSocket server responses            =
        ==================================================================*/

        /**
         * Handle the WebSocket server response and process action with the right callback
         *
         * @method     wsCallbackDispatcher
         * @param      {Object}         data            The server JSON response
         * @param      {String}         data.action     The callback to call
         * @param      {Number}         data.roomId     The room ID
         * @param      {Array}          data.historic   The list of messages found
         * @param      {String}         data.text       The message to display
         * @param      {Boolean}        data.success    True if the action was successfully done, false otherwise
         * @param      {Object|String}  data.messages   The message(s) to display in the chat
         */
        wsCallbackDispatcher: function (data) {
            switch (data.action) {
                case 'receiveMessage':
                    this.receiveMessageCallback(data);
                    break;

                case 'sendMessage':
                    this.sendMessageCallback(data);
                    break;

                case 'getHistoric':
                    this.getHistoricCallback(data);
                    break;

                default:
                    this.notification.add(data.text);
            }
        },

        /**
         * Callback after a user received a message
         *
         * @method     receiveMessageCallback
         * @param      {Object}         data            The server JSON response
         * @param      {Number}         data.roomId     The room ID
         * @param      {Object|String}  data.messages   The message(s) to display in the chat
         */
        receiveMessageCallback: function (data) {
            var room                = $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]'),
                chat                = room.find(this.settings.selectors.global.chat),
                messagesUnread      = room.find(this.settings.selectors.global.messagesUnread),
                messagesUnreadValue = messagesUnread.text();

            chat.append(this.formatUserMessage(data));

            if (this.rooms[data.roomId].isOpened()) {
                chat.scrollTop(room.height());
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
         * @param      {Object}  data           The server JSON response
         * @param      {String}  data.text      The message to display
         * @param      {Boolean} data.success   True if the action was successfully done, false otherwise
         */
        sendMessageCallback: function (data) {
            if (!data.success) {
                this.notification.add(data.text);
            }
        },

        /**
         * Callback after a user attempted to load more historic of a conversation
         *
         * @method     getHistoricCallback
         * @param      {Object}  data           The server JSON response
         * @param      {Number}  data.roomId    The room ID
         * @param      {Array}   data.historic  The list of messages found
         * @param      {String}  data.text      The message to display
         * @param      {Boolean} data.success   True if the action was successfully done, false otherwise
         */
        getHistoricCallback: function (data) {
            var room = $(this.settings.selectors.global.room + '[data-id="' + data.roomId + '"]');

            if (data.success) {
                this.loadHistoric(room.find(this.settings.selectors.global.room), data.historic, data.roomId);
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
         * @param      {Number}  roomId       The room ID
         */
        loadHistoric: function (roomChatDOM, historic, roomId) {
            var historicLength = historic.length;

            if (historicLength > 0) {
                this.lastMessageLoadedTime[roomId] = historic[historicLength - 1].date;
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
         * @param      {Object}         data            The server JSON response
         * @param      {Object|String}  data.messages   The message(s) to display in the chat
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
         * @param      {Number}   roomId    The room ID
         * @return     {Boolean}  True if the user input was a command else false
         */
        isCommand: function (message, roomId) {
            var isCommand = false,
                self      = this,
                regexResult;

            _.forEach(this.settings.commands, function (regex, name) {
                regexResult = regex.exec(message);

                if (regexResult !== null) {
                    isCommand = true;

                    switch (name) {
                        case 'pm':
                            self.sendMessage(roomId, regexResult[1], regexResult[2]);

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
