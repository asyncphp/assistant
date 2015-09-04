# Assistant

[![Build Status](http://img.shields.io/travis/asyncphp/assistant.svg?style=flat-square)](https://travis-ci.org/asyncphp/assistant)
[![Code Quality](http://img.shields.io/scrutinizer/g/asyncphp/assistant.svg?style=flat-square)](https://scrutinizer-ci.com/g/asyncphp/assistant)
[![Code Coverage](http://img.shields.io/scrutinizer/coverage/g/asyncphp/assistant.svg?style=flat-square)](https://scrutinizer-ci.com/g/asyncphp/assistant)
[![Version](http://img.shields.io/packagist/v/asyncphp/assistant.svg?style=flat-square)](https://packagist.org/packages/asyncphp/assistant)
[![License](http://img.shields.io/packagist/l/asyncphp/assistant.svg?style=flat-square)](license.md)

A neat wrapper around multi-process abstractions and distributed event emitters.

## Usage

```php
use AsyncPHP\Assistant\Proxy\DoormanProxy;
use AsyncPHP\Doorman\Manager\ProcessManager;
use AsyncPHP\Remit\Location\InMemoryLocation;

$proxy = new DoormanProxy(
    new ProcessManager(),
    new ZeroMqServer(
        new InMemoryLocation("127.0.0.1", 5555)
    )
);

$proxy
    ->parallel(function() {
        // do this task in a separate process
    })
    ->synchronous(function() {
        // ...then do this task in the parent process
    })
    ->parallel([
        function() {
            // ...then do this task in a separate process

            $this->emit("custom event", ["hello world"]);
        },
        function() {
            // ...and do this at the same time, in a separate process
        },
    ]);

$proxy->addListener("custom event", function($message) {
    print "custom event emitted with: {$message}";
});

while ($proxy->tick()) {
    usleep(25000);
}
```

You can find more in-depth documentation in [docs/en](docs/en/introduction.md).

## Motivation

[Doorman](https://github.com/asyncphp/doorman) and [Remit](https://github.com/asyncphp/remit) at great, but they can be tricky to learn and verbose at times. This aims to simplify that.

## Versioning

This library follows [Semver](http://semver.org). According to Semver, you will be able to upgrade to any minor or patch version of this library without any breaking changes to the public API. Semver also requires that we clearly define the public API for this library.

All methods, with `public` visibility, are part of the public API. All other methods are not part of the public API. Where possible, we'll try to keep `protected` methods backwards-compatible in minor/patch versions, but if you're overriding methods then please test your work before upgrading.

## Thanks

I'd like to thank [SilverStripe](http://www.silverstripe.com) for letting me work on fun projects like this. Feel free to talk to me about using the [framework and CMS](http://www.silverstripe.org) or [working at SilverStripe](http://www.silverstripe.com/who-we-are/#careers).
