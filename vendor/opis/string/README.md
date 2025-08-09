Opis String
===========
[![Tests](https://github.com/opis/string/workflows/Tests/badge.svg)](https://github.com/opis/string/actions)
[![Latest Stable Version](https://poser.pugx.org/opis/string/version.png)](https://packagist.org/packages/opis/string)
[![Latest Unstable Version](https://poser.pugx.org/opis/string/v/unstable.png)](https://packagist.org/packages/opis/string)
[![License](https://poser.pugx.org/opis/string/license.png)](https://packagist.org/packages/opis/string)

Multibyte strings
----------------------------

**Opis String** is a tiny library that allows you to work with multibyte encoded strings in an object-oriented manner.
The library has no dependencies to *mb_string* or similar PHP extensions.

## Documentation

The full documentation for this library can be found [here][documentation].

## License

**Opis String** is licensed under the [Apache License, Version 2.0][license].

## Requirements

* PHP ^7.4 || ^8.0
* ext-json
* ext-iconv

## Installation

**Opis String** is available on [Packagist] and it can be installed from a
command line interface by using [Composer].

```bash
composer require opis/string
```

Or you could directly reference it into your `composer.json` file as a dependency

```json
{
    "require": {
        "opis/string": "^2.0"
    }
}
```

[documentation]: https://opis.io/string
[license]: https://www.apache.org/licenses/LICENSE-2.0 "Apache License"
[Packagist]: https://packagist.org/packages/opis/string "Packagist"
[Composer]: https://getcomposer.org "Composer"
