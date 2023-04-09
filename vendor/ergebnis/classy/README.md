# classy

[![Integrate](https://github.com/ergebnis/classy/workflows/Integrate/badge.svg)](https://github.com/ergebnis/classy/actions)
[![Prune](https://github.com/ergebnis/classy/workflows/Prune/badge.svg)](https://github.com/ergebnis/classy/actions)
[![Release](https://github.com/ergebnis/classy/workflows/Release/badge.svg)](https://github.com/ergebnis/classy/actions)
[![Renew](https://github.com/ergebnis/classy/workflows/Renew/badge.svg)](https://github.com/ergebnis/classy/actions)

[![Code Coverage](https://codecov.io/gh/ergebnis/classy/branch/main/graph/badge.svg)](https://codecov.io/gh/ergebnis/classy)
[![Type Coverage](https://shepherd.dev/github/ergebnis/classy/coverage.svg)](https://shepherd.dev/github/ergebnis/classy)

[![Latest Stable Version](https://poser.pugx.org/ergebnis/classy/v/stable)](https://packagist.org/packages/ergebnis/classy)
[![Total Downloads](https://poser.pugx.org/ergebnis/classy/downloads)](https://packagist.org/packages/ergebnis/classy)

Provides a finder for classy constructs (classes, enums, interfaces, and traits).

## Installation

Run

```sh
$ composer require ergebnis/classy
```

## Usage

### Collect classy constructs from source code

Use `Constructs::fromSource()` to collect classy constructs in source code:

```php
<?php

use Ergebnis\Classy\Construct;
use Ergebnis\Classy\Constructs;

$source = <<<'PHP'
<?php

namespace Example;

class Foo {}

enum Bar {}

interface Baz {}

trait Qux {}
PHP;

$constructs = Constructs::fromSource($source);

$names = array_map(static function (Construct $construct): string {
    return $construct->name();
}, $constructs);

var_dump($names); // ['Example\Bar', 'Example\Baz', 'Example\Foo', 'Example\Qux']
```

### Collect classy constructs from a directory

Use `Constructs::fromDirectory()` to collect classy constructs in a directory:

```php
<?php

use Ergebnis\Classy\Construct;
use Ergebnis\Classy\Constructs;

$constructs = Constructs::fromDirectory(__DIR__ . '/example');

$names = array_map(static function (Construct $construct): string {
    return $construct->name();
}, $constructs);

var_dump($names); // ['Example\Bar', 'Example\Bar\Baz', 'Example\Foo\Bar\Baz']
```

## Changelog

Please have a look at [`CHANGELOG.md`](CHANGELOG.md).

## Contributing

Please have a look at [`CONTRIBUTING.md`](.github/CONTRIBUTING.md).

## Code of Conduct

Please have a look at [`CODE_OF_CONDUCT.md`](https://github.com/ergebnis/.github/blob/main/CODE_OF_CONDUCT.md).

## License

This package is licensed using the MIT License.

Please have a look at [`LICENSE.md`](LICENSE.md).

## Credits

The algorithm for finding classes in PHP files in [`Constructs`](src/Constructs.php) has been adopted from [`Zend\File\ClassFileLocator`](https://github.com/zendframework/zend-file/blob/release-2.7.1/src/ClassFileLocator.php) (originally licensed under BSD-3-Clause).

## Curious what I am building?

:mailbox_with_mail: [Subscribe to my list](https://localheinz.com/projects/), and I will occasionally send you an email to let you know what I am working on.
