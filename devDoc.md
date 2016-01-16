# Developer coding rules / process

## Linters

- **php** files => phpcs (*PHP_CodeSniffer*)
- **js** files  => jslint and jshint

## Git process

For each developement, create a branch from **master** at the begining of the developement.

The new branch should me names as **(_feature|hotfix|refacto_)/nameInitial-developementName[#ticketNumber]**.

*Exemples*

- feature/rl-addUserPhoneNumber
- hotfix/rl-httpsCertificateFail#6842
- refacto/rl-userClass

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

All methods / functions must be documented with docBlockr and must not raise errors in phpdoc or jsdoc parsing.

*Exemples*

```php
/**
 * [aCoolFunction description]
 *
 * @param  {String}  $param1 [description]
 * @param  {Integer} $param2 [description]
 * @param  {[type]}  $param3 [description]
 * @param  {[type]}  $param4 [description]
 * @return {boolean}         [description]
 */
public function aCoolFunction($param1 = 'toto', $param2 = 3, $param3, $param4) {
    // ...

    return true;
}
```

## Environment and IDE

### Windows

First create the main repository of the project by running the command
`git clone https://github.com/ZiperRom1/web.git web`

Then the technical documentation repository of the projet by running the command
`git clone -b gh-pages --single-branch https://github.com/ZiperRom1/web.git web-doc`

- Install PHP [last realese Thred Safe](http://windows.php.net/downloads/releases/php-7.0.2-Win32-VC14-x64.zip) (dezip and add the repository to the PATH windows variable)
- Install APACHE [last realese](http://www.apachelounge.com/download/VC14/binaries/httpd-2.4.18-win64-VC14.zip) as a service
- Install MySQL [last realese](http://dev.mysql.com/downloads/file/?id=460081) as a service
- Install [Node.js](https://nodejs.org/dist/v5.4.1/node-v5.4.1-x64.msi) with NPM (Node packages manager) and add it to the PATH windows variable
- Install [Composer](https://getcomposer.org/Composer-Setup.exe) (PHP packages manager)
- Install [phpdoc](http://phpdoc.org/) with Composer (on /php PATH run `composer install`)

For IDE I recommend [Sublime Text 3](https://download.sublimetext.com/Sublime%20Text%20Build%203083%20x64.zip) with a stack of [sublime packages](https://packagecontrol.io/) or [PhpStorm](https://download.jetbrains.com/webide/PhpStorm-10.0.3.exe) which is heavier than Sublime Text 3 but a quite nice IDE.
