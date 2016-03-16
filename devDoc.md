# Developer coding rules / process

## Linters

- **php** files => [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer/wiki)
- **js** files  => [jshint](http://jshint.com/) and [jscs](http://jscs.info/)

## Git process

For each developement, create a branch from **master** at the begining of the developement.

The new branch should me names as **(_feature|hotfix|refacto_)/nameInitial-developementTopic[#ticketNumber]**.

*Exemples*

*(User name is Romain Laneuville)*

- feature/rl-addUserPhoneNumber
- hotfix/rl-httpsCertificateFail#6842
- refacto/rl-userPhpClass

When the developement is over, merge **master** into your branch and create a **pull request**.

**Commits directly on master is forbidden**.

## Indent and spacing

Indentation must be **4 spaces** in all files (no tabulation).

In **js** files, align variables declaration like this:

```js
var oneVar     = 'toto',
    oneMoreVar = 'tata',
    aNumber    = 5,
    tempVar1, tempVar2;
```

In **php** files, align variables declaration like this:

```php
$oneVar     = 'toto',
$oneMoreVar = 'tata',
$aNumber    = 5,
$tempVar1, $tempVar2;
```

## Documentation

All methods / functions must be documented with phpDoc / jsDoc and must not raise errors in phpdoc or jsdoc parsing.

*Exemples*

```php
/**
 * [aCoolFunction description]
 *
 * @param  string  $param1 [description]
 * @param  int     $param2 [description]
 * @param  [type]  $param3 [description]
 * @param  [type]  $param4 [description]
 *
 * @return bool            [description]
 */
public function aCoolFunction(string $param1 = 'toto', int $param2 = 3, $param3, $param4): bool
{
    // ...

    return true;
}
```

All PHP methods parameters type and methods return type must be specified as the PHP7 type hint feature which are

- self
- array
- callable
- bool
- float
- int
- string

## Environment and IDE

### Windows

First create the main repository of the project by running the command

`git clone https://github.com/ZiperRom1/web.git web`

Then the technical documentation repository of the projet by running the command

`git clone -b gh-pages --single-branch https://github.com/ZiperRom1/web.git web-doc`

- Install PHP [last realese Thred Safe](http://windows.php.net/downloads/releases/php-7.0.2-Win32-VC14-x64.zip) (dezip and add the repository to the PATH windows variable)
- Install APACHE [last realese](http://www.apachelounge.com/download/VC14/binaries/httpd-2.4.18-win64-VC14.zip) as a service (run `[path to apache repository]/httpd.exe -k install`)
- Install MySQL [last realese](http://dev.mysql.com/get/Downloads/MySQLInstaller/mysql-installer-community-5.7.10.0.msi) as a service (use root as root password)
- Install [Node.js](https://nodejs.org/dist/v5.4.1/node-v5.4.1-x64.msi) with NPM (Node packages manager) and add it to the PATH windows variable
- Install [Composer](https://getcomposer.org/Composer-Setup.exe) (PHP packages manager)
- Install [gulp](http://gulpjs.com/) with NPM (run `npm install --global gulp`)

####Setup Apache

In [apache folder]/conf/httpd.conf check those lines

- `ServerRoot "[absolute path to your apache folder]"`

- `DocumentRoot "absolute path to the project root directory"` (or use advanced virtualHost conf)

- `Listen 8080` (not necessary but prefer changing the port)

- `DirectoryIndex index.php index.html`

- `LoadModule rewrite_module modules/mod_rewrite.so`

- `LoadModule proxy_ftp_module modules/mod_proxy_ftp.so`

- `LoadModule php7_module "[absolute path to your php folder]/php7apache2_4.dll"`

- `PHPIniDir "[absolute path to your php folder (with slash no back-slash)]"`

- In *IfModule mime_module* section: `AddHandler application/x-httpd-php .php`

- Replace `AllowOverride None` by `AllowOverride All` in all occurences (especially the document root file section)

- Add
```
<FilesMatch \.php$>
    SetHandler application/x-httpd-php
</FilesMatch>
```

####Setup PHP

In php.ini, check thoses values

- `short_open_tag = On`

- `extension_dir = "ext"`

- `extension=php_curl.dll`

- `extension=php_gettext.dll`

- `extension=php_mbstring.dll`

- `extension=php_openssl.dll`

- `extension=php_pdo_mysql.dll`

- `extension=php_sockets.dll`

- `extension=php_shmop.dll`

In project /php folder create a `conf.ini` based on `con-example.ini`

Install dev dependencies with Composer (on /php PATH run `composer install`)

####Setup MySQL

Create an empty database (ex: `CREATE SCHEMA ``websocket`` DEFAULT CHARACTER SET utf8 ;`) and setup its name in /php/conf.ini => `[Database]` => `dsn` dbname value

Create tables with the ORM, run those commands

`php [absolute path to the project root directory]/php/launchORM.php`

`create all`

`exit`

####Setup source files

In /static project PATH run the followings commands to install dev-dependencies

`npm install`

`gulp install`

To compile js and less files into dist repository run

`gulp build`

Here are all the gulp commands you can run

- `gulp install` Retrieve and move all the js and less vendor sources files on right folders

- `gulp flush_bower` Flush the .bowerDependencies repository

- `gulp flush_npm` Flush the node_modules repository

- `gulp flush_js` Flush all js vendor sources files

- `gulp flush_less` Flush all less vendor sources files

- `gulp flush_dist` Flush the dist repository

- `gulp flush` Flush all (combination of all the flushes)

- `gulp build_js` Compile and optimize the js sources files into one file (app.js) in dist repository

- `gulp build_less` Compile and optimize the less sources files into one file (style.css) in dist repository

- `gulp build` Compile and optimize both js and less files (combination of build_js and build_less)

- `gulp js_jscs` Parse js source files with jscs linter, fix coding style and prompt non fixed ones

- `gulp js_jshint` Parse js source files with jshint linter and show errors

- `gulp js_lint` Parse js source files with jshint and jslint linters and show errors

- `gulp php_phpcs` Parse php source files with phpcs linter and show errors

- `gulp php_phpcbf` Parse php source files with phpcbf linter, fix coding style and prompt non fixed ones

- `gulp php_lint` Parse php source files with phpcbf and phpcs linters and show errors

- `gulp jsdoc` Generate the jsdoc

- `gulp phpdoc` Generate the phpdoc

- `gulp push_jsdoc` Add commit and push the jsdoc on git gh-pages branch

- `gulp push_phpdoc` Add commit and push the phpdoc on git gh-pages branch

- `gulp doc` Generate the jsdoc and the phpdoc

- `gulp push_doc` Add commit and push the phpdoc and the jsdoc on git gh-pages branch

- `gulp watch`  Wait from any change in /static/less directory and run `gulp build_less` on change

####Run the server

Finally run the websocket server with this command

`php [absolute path to the project root directory]/php/launchWebsocketServer.php`

Then go to your web browser with javascript console enabled and hit the index.php of the project

Create a new user and give him admin rights on the userRights table (put "1" in both right columns)

To enable the chat service, connect with an admin user then run in the web javascript console

`window.WebsocketManager.addService('chatService');`

####IDE

For IDE I recommend [Sublime Text 3](https://download.sublimetext.com/Sublime%20Text%20Build%203083%20x64.zip) with a stack of [sublime packages](https://packagecontrol.io/) or [PhpStorm](https://download.jetbrains.com/webide/PhpStorm-10.0.3.exe) which is heavier than Sublime Text 3 but a quite nice IDE.

####Todo

- Créer un user chat rights par défaut à la création d'un user sinon bug dans => `chat.js:1226:30`
