<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Test websocket</title>
        <link rel="stylesheet" href="static/dist/style.css">
        <!--<script src="static/dist/app.js" type="text/javascript" charset="utf-8" async defer></script>-->
        <script data-main="/static/js/app"
                src="/static/js/lib/vendor/require.js"
                type="text/javascript"
                charset="utf-8"
                async defer>
        </script>

        <?php

        require_once 'php/autoloader.php';

        use \classes\IniManager as Ini;
        use \classes\WebContentInclude as WebContentInclude;

        ?>
    </head>
    <body>

        <!--===========================
        =            Menus            =
        ============================-->

        <?php WebContentInclude::includeDirectoryFiles(Ini::getParam('Web', 'menusPath')); ?>

        <!--====  End of Menus  ====-->

        <!--===========================
        =            Pages            =
        ============================-->

        <?php WebContentInclude::includeDirectoryFiles(Ini::getParam('Web', 'pagesPath')); ?>

        <!--====  End of Pages  ====-->

        <!--============================
        =            Modals            =
        =============================-->

        <?php WebContentInclude::includeDirectoryFiles(Ini::getParam('Web', 'modalsPath')); ?>

        <!--====  End of Modals  ====-->

        <!--============================
        =            Alerts            =
        =============================-->

        <?php WebContentInclude::includeDirectoryFiles(Ini::getParam('Web', 'alertsPath')); ?>

        <!--====  End of Alerts  ====-->

    </body>
</html>
