requirejs.config({
    "paths" : {
        "bootstrap"             : "lib/vendor/bootstrap",
        "bootstrap-select"      : "lib/vendor/bootstrap-select",
        "bootstrap-switch"      : "lib/vendor/bootstrap-switch",
        "jasny-bootstrap"       : "lib/vendor/jasny-bootstrap",
        "domReady"              : "lib/vendor/domReady",
        "jquery"                : "lib/vendor/jquery",
        "loading-overlay"       : "lib/vendor/loading-overlay",
        "lodash"                : "lib/vendor/lodash",
        "require"               : "lib/vendor/require",
        "chat"                  : "lib/chat",
        "forms"                 : "lib/forms",
        "message"               : "lib/message",
        "user"                  : "lib/user",
        "websocket"             : "lib/websocket",
        "iframe"                : "lib/iframe",
        "navigation"            : "lib/navigation"
    },
    "shim"  : {
        "bootstrap"        : {
            "deps": ['jquery']
        },
        "bootstrap-select" : {
            "deps": ['bootstrap']
        },
        "bootstrap-switch" : {
            "deps": ['bootstrap']
        },
        "jasny-bootstrap"  : {
            "deps": ['jquery', 'bootstrap']
        },
        "loading-overlay"  : {
            "deps": ['jquery']
        }
    },
    "config": {
        "navigation": {
            "landingPage" : "chat",
            "urlPrefix"   : "#!",
            "selectors"   : {
                "page"       : ".page",
                "pageLink"   : ".page-link",
                "currentPage": ".current-page"
            }
        },
        "iframe"    : {
            "resizeInterval": 2000,
            "selectors"     : {
                "kibanaIframe"         : "#kibana-iframe",
                "iframeWidthContainer" : "body",
                "iframeHeightContainer": ".content"
            }
        },
        "websocket" : {
            "serverUrl"   : "ws://127.0.0.1:5000",
            "serviceName" : "websocketService",
            "waitInterval": 1000
        },
        "user"      : {
            "selectors": {
                "modals": {
                    "connect": "#connectUserModal"
                }
            }
        },
        "chat"      : {
            "serviceName"  : "chatService",
            "maxUsers"     : 15,
            "animationTime": 500,
            "selectors"    : {
                "global"             : {
                    "chat"              : "#chat",
                    "room"              : ".room",
                    "roomName"          : ".room-name",
                    "roomContents"      : ".room-contents",
                    "roomChat"          : ".chat",
                    "roomSample"        : "#room-sample",
                    "roomHeader"        : ".header",
                    "roomClose"         : ".close-room",
                    "roomMinimize"      : ".minimize",
                    "roomFullscreen"    : ".fullscreen",
                    "roomMessagesUnread": ".messages-unread"
                },
                "roomConnect"        : {
                    "div"         : ".connect-room",
                    "name"        : ".room-name",
                    "publicRooms" : '.public',
                    "privateRooms": '.private',
                    "pseudonym"   : ".pseudonym",
                    "password"    : ".room-password",
                    "connect"     : ".connect"
                },
                "roomCreation"       : {
                    "div"     : ".create-room",
                    "name"    : ".room-name",
                    "type"    : ".room-type",
                    "password": ".room-password",
                    "maxUsers": ".room-max-users",
                    "create"  : ".create"
                },
                "roomSend"           : {
                    "div"      : ".send-action",
                    "message"  : ".message",
                    "recievers": ".recievers",
                    "usersList": ".users-list",
                    "send"     : ".send"
                },
                "roomAction"         : {
                    "loadHistoric"  : ".load-historic",
                    "kickUser"      : ".kick-user",
                    "showUsers"     : ".users",
                    "administration": ".admin"
                },
                "chat"               : {
                    "message"  : ".message",
                    "pseudonym": ".pseudonym",
                    "date"     : ".date",
                    "text"     : ".text"
                },
                "administrationPanel": {
                    "modal"            : ".chat-admin",
                    "modalSample"      : "#chat-admin-sample",
                    "trSample"         : ".sample",
                    "usersList"        : ".users-list",
                    "roomName"         : ".room-name",
                    "kick"             : ".kick",
                    "ban"              : ".ban",
                    "rights"           : ".right",
                    "pseudonym"        : ".user-pseudonym",
                    "toggleRights"     : ".toggle-rights",
                    "bannedList"       : ".banned-list",
                    "ip"               : ".ip",
                    "pseudonymBanned"  : ".pseudonym-banned",
                    "pseudonymAdmin"   : ".pseudonym-admin",
                    "reason"           : ".reason",
                    "date"             : ".date",
                    "inputRoomPassword": ".room-password",
                    "inputRoomName"    : ".room-name"
                },
                "alertInputsChoice"  : {
                    "div"   : "#alert-input-choice",
                    "submit": ".send"
                }
            },
            "commands"     : {
                "kick": /^\/kick '([^']*)'? ?(.*)/,
                "pm"  : /^\/pm '([^']*)' (.*)/
            }
        },
        "message"   : {
            "alert"       : {
                "divId"          : "#alert-container",
                "dismissClass"   : ".dismiss",
                "defaultDuration": 2
            },
            "popup"       : {
                "divId"          : "#popup-container",
                "dismissClass"   : ".dismiss",
                "defaultDuration": 6
            },
            "notification": {
                "divId"          : "#notification-container",
                "dismissClass"   : ".dismiss",
                "defaultDuration": 4
            },
            "serviceName" : "notificationService",
            "defaultType" : "alert",
            "defaultLevel": "info"
        }
    }
});

requirejs(['main']);
