<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Test websocket</title>
        <link rel="stylesheet" href="/static/dist/css/bootstrap.css">
        <link rel="stylesheet" href="/static/dist/css/bootstrap-theme.css">
        <script data-main="/static/js/app"
                src="/static/js/lib/vendors/require.js"
                type="text/javascript"
                charset="utf-8"
                async defer>
        </script>
    </head>
    <body>
        <h1>Test websocket</h1>
    
        <!-- register user -->
        <form action="user/register" method="post" accept-charset="utf-8" data-ajax="false">
            <input type="text" name="firstName" placeholder="<?=_('First name')?>">
            <input type="text" name="lastName" placeholder="<?=_('Last name')?>">
            <input type="text" name="pseudonym" placeholder="<?=_('Pseudonym')?>">
            <input type="email" name="email" placeholder="<?=_('Email')?>">
            <input type="password" name="password" placeholder="<?=_('Password')?>">
            <input type="submit" value="submit">
        </form>
        
        <!-- connect user -->
        <form action="user/connect" method="post" accept-charset="utf-8">
            <input type="text" name="login" placeholder="<?=_('Login (Pseudonym or email')?>">
            <input type="password" name="password" placeholder="<?=_('Password')?>">
            <input type="submit" value="submit">
        </form>

        <!-- connect room -->
        <div class="connect-room">
            <input class="pseudonym" type="text" name="chatPseudo" value="" placeholder="<?=_('Pseudonym')?>">
            <input class="room-name" type="text" name="roomName" value="" placeholder="<?=_('Room name')?>">
            <input class="room-password" type="password" name="roomPassword" value="" placeholder="<?=_('Room password')?>">
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
                    <span
                        class="users glyphicon glyphicon-user pull-left"
                        type="button"
                        data-title="<?= _('Connected users')?>"
                        data-toggle="popover"
                        data-placement="right"
                        data-html="true"
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
                                      list="chatCommands"
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
    </body>
</html>