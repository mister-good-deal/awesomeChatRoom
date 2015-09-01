<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Test websocket</title>
        <!-- <link rel="stylesheet" href=""> -->
        <script data-main="/static/js/app"
                src="/static/js/lib/vendors/require.js"
                type="text/javascript"
                charset="utf-8"
                async defer>
        </script>
    </head>
    <body>
        <h1>Test websocket</h1>

        <form action="user/register" method="post" accept-charset="utf-8" data-ajax="false">
            <input type="text" name="firstName" placeholder="<?=_('First name')?>">
            <input type="text" name="lastName" placeholder="<?=_('Last name')?>">
            <input type="text" name="pseudonym" placeholder="<?=_('Pseudonym')?>">
            <input type="email" name="email" placeholder="<?=_('Email')?>">
            <input type="password" name="password" placeholder="<?=_('Password')?>">
            <input type="submit" value="submit">
        </form>

        <form action="user/connect" method="post" accept-charset="utf-8">
            <input type="text" name="login" placeholder="<?=_('Login (Pseudonym or email')?>">
            <input type="password" name="password" placeholder="<?=_('Password')?>">
            <input type="submit" value="submit">
        </form>

        <div id="chat">
            <h3><?=_('Chat')?></h3>

            <!-- connect -->
            <div class="connect-room">
                <input class="pseudonym" type="text" name="chatPseudo" value="" placeholder="<?=_('Pseudonym')?>">
                <input class="room-name" type="text" name="roomName" value="" placeholder="<?=_('Room name')?>">
                <input class="room-password" type="password" name="roomPassword" value="" placeholder="<?=_('Room password')?>">
                <button class="connect" type="button"><?=_('Connect')?></button>
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
            
            <div class="room" data-name="default" data-type="public" data-max-users="200">
                <h3 class="room-name">default</h3>
                <!-- chat message display -->
                <button class="load-historic" type="button"><?=_('Load more')?></button>
                <div class="chat" data-historic-loaded="0"></div>
                <!-- send message -->
                <div class="send-action">
                    <input class="message" type="text" name="message" value="" placeholder="<?=_('Message')?>">
                    <select class="recievers" name="recievers">
                        <option value="all" selected><?=_('All')?></option>
                        <option value="pseudonym">futur pseudonyms list</option>
                        <!-- <input type="text" name="userPseudonym" placeholder="<?=_('User pseudonym')?>"> -->
                    </select>
                    <button class="send" type="button"><?=_('Send message')?></button>
                </div>
            </div>
        </div>
    </body>
</html>