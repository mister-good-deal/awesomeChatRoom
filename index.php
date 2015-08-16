
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Test websocket</title>
        <!-- <link rel="stylesheet" href=""> -->
        <script data-main="/static/js/app"
                src="/static/js/lib/require.js"
                type="text/javascript"
                charset="utf-8"
                async defer>
        </script>
    </head>
    <body>
        <h1>Test websocket</h1>

        <form action="user/register" method="post" accept-charset="utf-8">
            <input type="text" name="firstName" value="" placeholder="First name">
            <input type="text" name="lastName" value="" placeholder="Last name">
            <input type="text" name="pseudonym" value="test" placeholder="Pseudo">
            <input type="email" name="email" value="" placeholder="Email">
            <input type="submit" value="submit">
        </form>
    </body>
</html>