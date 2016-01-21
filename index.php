<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Test websocket</title>
        <link rel="stylesheet" href="/static/dist/style.css">
        <!-- <link rel="stylesheet" href="/static/dist/css/bootstrap-theme.css"> -->
        <script src="/static/dist/app.js" type="text/javascript" charset="utf-8" async defer></script>
        <!--<script data-main="/static/js/app"
                src="/static/js/lib/vendor/require.js"
                type="text/javascript"
                charset="utf-8"
                async defer>
        </script> -->
    </head>
    <body>
        <h1>Test websocket</h1>

        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#registerUserModal">
            <?=_('Register')?>
        </button>

        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#connectUserModal">
            <?=_('Connect')?>
        </button>

        <!-- connect room -->
        <div class="connect-room">
            <input class="pseudonym" type="text" name="chatPseudo" placeholder="<?=_('Pseudonym')?>">
            <select class="room-name selectpicker"
                    name="roomName"
                    data-title="<?=_('Select a room')?>"
                    data-show-subtext="true"
                    data-live-search="true"
            >
                <optgroup class="public" label="<?=_('Public rooms')?>"></optgroup>
                <optgroup class="private" label="<?=_('Private rooms')?>"></optgroup>
            </select>
            <input class="room-password" type="password" name="roomPassword"  placeholder="<?=_('Room password')?>">
            <button class="connect btn btn-primary" type="button"><?=_('Connect')?></button>
        </div>

        <!-- create room -->
        <div class="create-room">
            <input class="room-name" type="text" name="roomName" value="" placeholder="<?=_('Room name')?>">
            <select class="room-type" name="roomType">
                <option value="public" selected><?=_('Public')?></option>
                <option value="private"><?=_('Private')?></option>
            </select>
            <input class="room-password" type="password" name="roomPassword" value="" placeholder="<?=_('Password')?>">
            <input class="room-max-users" type="number" name="roomMaxUsers" value="" placeholder="<?=_('Max users')?>">
            <button class="create" type="button"><?=_('Create a room')?></button>
        </div>

        <div id="chat">
            <!-- rooms -->
            <div id="room-sample"
                class="room hide"
                data-name=""
                data-type=""
                data-pseudonym=""
                data-users=""
                data-max-users=""
                data-password=""
            >
                <!-- room title -->
                <h3 class="header">
                    <span class="users glyphicon glyphicon-user pull-left"
                          type="button"
                          data-title="<?= _('Connected users')?>"
                          data-toggle="popover"
                          data-placement="right"
                          data-html="true"
                    ></span>
                    <span class="admin glyphicon glyphicon-cog pull-left"
                          data-toggle="modal"
                          data-target="#chat-admin-id"
                    ></span>
                    <span class="room-name"><?= _('default')?></span>
                    <span class="badge messages-unread"></span>
                    <span class="close-room pull-right glyphicon glyphicon-remove"></span>
                    <span class="fullscreen pull-right glyphicon glyphicon-fullscreen"></span>
                    <span class="minimize pull-right glyphicon glyphicon-minus"></span>
                </h3>
                <!-- room contents -->
                <div class="room-contents">
                    <!-- messages display -->
                    <div class="chat" data-historic-loaded="0">
                        <button class="load-historic" type="button"><?=_('Load more')?></button>
                    </div>
                    <!-- send message -->
                    <form class="send-action no-ajax">
                        <div class="form-group">
                            <textarea class="message form-control"
                                      rows="3"
                                      name="message"
                                      placeholder="<?=_('Message')?>"
                            ></textarea>
                        </div>
                        <div class="form-group">
                            <div class="input-group-btn dropup">
                                <button type="button"
                                        class="btn btn-default dropdown-toggle recievers"
                                        data-toggle="dropdown"
                                        data-value="all"
                                        aria-haspopup="true"
                                        aria-expanded="false"
                                ><?=_('Send to')?> (<span class="value"><?=_('All')?></span>) <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu users-list">
                                    <li data-value="all">
                                        <a href="#" title="<?=_('All')?>"><?=_('All')?></a>
                                    </li>
                                </ul>
                                <button class="send btn btn-default" type="submit"><?=_('Send message')?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- register user modal -->
        <div class="modal" id="registerUserModal" tabindex="-1" role="dialog" aria-labelledby="registerModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button"class="close" data-dismiss="modal" aria-label="<?=_('Close')?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="registerModalLabel"><?=_('Register')?></h4>
                    </div>
                    <div class="modal-body">
                        <form action="user/register" data-send-action="ajax" method="post">
                            <div class="form-group">
                                <label for="firstNameRegister"><?=_('First name')?></label>
                                <input id="firstNameRegister"
                                       class="form-control"
                                       type="text"
                                       name="firstName"
                                       placeholder="<?=_('First name')?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="lastNameRegister"><?=_('Last name')?></label>
                                <input id="lastNameRegister"
                                       class="form-control"
                                       type="text"
                                       name="lastName"
                                       placeholder="<?=_('Last name')?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="pseudonymRegister"><?=_('Pseudonym')?></label>
                                <input id="pseudonymRegister"
                                       class="form-control"
                                       type="text"
                                       name="pseudonym"
                                       placeholder="<?=_('Pseudonym')?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="emailRegister"><?=_('Email')?></label>
                                <input id="emailRegister"
                                       class="form-control"
                                       type="email"
                                       name="email"
                                       placeholder="<?=_('Email')?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="passwordRegister"><?=_('Password')?></label>
                                <input id ="passwordRegister"
                                       class="form-control"
                                       type="password"
                                       name="password"
                                       placeholder="<?=_('Password')?>"
                                >
                            </div>
                            <div class="form-group">
                                <input type="submit" class="btn btn-default" value="<?=_('Register')?>">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- connect user modal -->
        <div class="modal" id="connectUserModal" tabindex="-1" role="dialog" aria-labelledby="connectModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button"class="close" data-dismiss="modal" aria-label="<?=_('Close')?>">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 class="modal-title" id="connectModalLabel"><?=_('Connect')?></h4>
                    </div>
                    <div class="modal-body">
                        <!-- connect user -->
                        <form action="user/connect" data-send-action="ajax" method="post">
                            <div class="form-group">
                                <label for="loginConnect"><?=_('First name')?></label>
                                <input id="loginConnect"
                                       class="form-control"
                                       type="text"
                                       name="login"
                                       placeholder="<?=_('Login (Pseudonym or email')?>"
                                >
                            </div>
                            <div class="form-group">
                                <label for="loginPassword"><?=_('Password')?></label>
                                <input id="loginPassword"
                                       class="form-control"
                                       type="password"
                                       name="password"
                                       placeholder="<?=_('Password')?>"
                                >
                            </div>
                            <div class="form-group">
                                <input type="submit" class="btn btn-default" value="<?=_('Connect')?>">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- admin chat modal -->
        <div class="modal chat-admin"
             id="chat-admin-sample"
             tabindex="-1"
             role="dialog"
             aria-labelledby="adminChatModalLabel"
             data-room-name=""
             data-room-password=""
        >
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <h4 id="adminChatModalLabel" class="modal-title">
                            <?=_('Chat administration')?> "<span class="room-name"></span>"
                        </h4>
                    </div>
                    <div class="modal-body">
                        <form class="form-inline"
                              data-send-action="jsCallback"
                              data-callback-name="setRoomInfosCallbackEvent"
                        >
                            <div class="form-group">
                                <label><?=_('Room name')?></label>
                                <input class="room-name" type="text" name="roomName" value="">
                            </div>
                            <div class="form-group">
                                <label><?=_('Room password')?></label>
                                <input class="room-password" type="password" name="roomPassword" value="">
                            </div>
                            <button class="send btn btn-default" type="submit"><?=_('Change')?></button>
                        </form>
                        <div class="panel panel-default">
                            <div class="panel-heading"><?=_('Users list')?></div>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?=_('User pseudonym')?></th>
                                        <th colspan="3"><?=_('Actions')?></th>
                                    </tr>
                                </thead>
                                <tbody class="users-list">
                                    <tr class="hide sample" data-pseudonym="">
                                        <td class="user-pseudonym">Pseudonym</td>
                                        <td>
                                            <button class="btn btn-default kick" type="button"><?=_('Kick')?></button>
                                        </td>
                                        <td>
                                            <button class="btn btn-default ban" type="button"><?=_('Ban')?></button>
                                        </td>
                                        <td>
                                            <button class="toggle-rights btn btn-default" type="button" data-refer="">
                                                <?=_('Rights')?> <span class="caret"></span>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="hide sample right" data-pseudonym="">
                                        <td colspan="4" class="warper">
                                            <label><?=_('Kick users')?></label>
                                            <input type="checkbox"
                                                   name="kick"
                                                   data-on-color="success"
                                                   data-off-color="danger"
                                                   data-size="mini"
                                            >
                                        </td>
                                    </tr>
                                    <tr class="hide sample right" data-pseudonym="">
                                        <td colspan="4" class="warper">
                                            <label><?=_('Ban users')?></label>
                                            <input type="checkbox"
                                                   name="ban"
                                                   data-on-color="success"
                                                   data-off-color="danger"
                                                   data-size="mini"
                                            >
                                        </td>
                                    </tr>
                                    <tr class="hide sample right" data-pseudonym="">
                                        <td colspan="4" class="warper">
                                            <label><?=_('Grant users rights')?></label>
                                            <input type="checkbox"
                                                   name="grant"
                                                   data-on-color="success"
                                                   data-off-color="danger"
                                                   data-size="mini"
                                            >
                                        </td>
                                    </tr>
                                    <tr class="hide sample right" data-pseudonym="">
                                        <td colspan="4" class="warper">
                                            <label><?=_('Rename room name')?></label>
                                            <input type="checkbox"
                                                   name="rename"
                                                   data-on-color="success"
                                                   data-off-color="danger"
                                                   data-size="mini"
                                            >
                                        </td>
                                    </tr>
                                    <tr class="hide sample right" data-pseudonym="">
                                        <td colspan="4" class="warper">
                                            <label><?=_('Change room password')?></label>
                                            <input type="checkbox"
                                                   name="password"
                                                   data-on-color="success"
                                                   data-off-color="danger"
                                                   data-size="mini"
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading"><?=_('Users banned')?></div>
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?=_('Ip')?></th>
                                        <th><?=_('Pseudonym')?></th>
                                        <th><?=_('Banned by')?></th>
                                        <th><?=_('Reason')?></th>
                                        <th><?=_('At')?></th>
                                        <th><?=_('Actions')?></th>
                                    </tr>
                                </thead>
                                <tbody class="banned-list">
                                    <tr class="hide sample">
                                        <td class="ip">0.0.0.0</td>
                                        <td class="pseudonym-banned">pseudonym used</td>
                                        <td class="pseudonym-admin">admin pseudonym</td>
                                        <td class="reason">reason</td>
                                        <td class="date">01/01/2000</td>
                                        <td>
                                            <button class="btn btn-default unbanned" type="button">
                                                <?=_('unbanned')?>
                                            </button>
                                        </td>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- alert to display user choice input on kick / ban events -->
        <div id="alert-input-choice" class="alert alert-info">
            <p><?=_('Complete the reason')?></p>
            <form data-send-action="jsCallback" data-callback-name="setReasonCallbackEvent">
                <div class="form-group">
                    <textarea class="reason form-control"
                              rows="3"
                              name="reason"
                              placeholder="<?=_('Reason')?>"
                    ></textarea>
                </div>
                <button class="send btn btn-default" type="submit"><?=_('OK')?></button>
            </form>
        </div>
    </body>
</html>
