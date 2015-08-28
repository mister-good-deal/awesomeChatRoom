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

            <input id="pseudonym" type="text" name="chatPseudo" value="" placeholder="<?=_('Pseudonym')?>">
            <input id="room-name-connect" type="text" name="roomName" value="" placeholder="<?=_('Room name')?>">
            <button type="button" class="connect"><?=_('Connect')?></button>

            <input id="room-name" type="text" name="roomName" value="" placeholder="<?=_('Room name')?>">
            <button type="button" class="create-room"><?=_('Create a room')?></button>
        </div>
    </body>
</html>