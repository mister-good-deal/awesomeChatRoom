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