# PHP GovTalk

**A library for applications which interface with the UK Government Gateway**

[![Build Status](https://travis-ci.org/JustinBusschau/php-govtalk.png?branch=master)](https://travis-ci.org/JustinBusschau/php-govtalk)
[![Latest Stable Version](https://poser.pugx.org/justinbusschau/php-govtalk/version.png)](https://packagist.org/packages/justinbusschau/php-govtalk)
[![Total Downloads](https://poser.pugx.org/justinbusschau/php-govtalk/d/total.png)](https://packagist.org/packages/justinbusschau/php-govtalk)

The GovTalk Message Envelope is a standard developed by the United Kingdom government as a means of encapsulating
a range of government XML services in a single standard data format.

This project was originally forked from [Fubra Limited](https://github.com/fubralimited/php-govtalk). Only the GovTalk
class is preserved in this library. This library can be used whenever you need to build something that interfaces with any
of the services that use the Government Gateway (e.g. Companies House, HMRC, etc.).

## Installation

The library can be installed via [Composer](http://getcomposer.org/). To install, simply add
it to your `composer.json` file:

```json
{
    "require": {
        "justinbusschau/php-govtalk": "0.*"
    }
}
```

And run composer to update your dependencies:

$ curl -s http://getcomposer.org/installer | php
$ php composer.phar update


## Basic usage

TBD
