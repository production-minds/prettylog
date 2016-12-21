
What is it?
===========

A simple log prettifier extensible with syntax parsers for various log formats.

Components are log entries are colored for easy skimming, and data parts are printed as pretty JSON.


Usage
-----

	tail -f /var/log/php5-fpm.log | prettylog

Arguments
---------

To print only messages matching or above the specified [monolog](https://github.com/Seldaek/monolog)
level (see [RFC5424](http://tools.ietf.org/html/rfc5424)):

	--min-level="debug|info|notice|warning|error|critical|alert|emergency"

Alternative short syntax:

	-l debug|info|notice|warning|error|critical|alert|emergency

When filtered for log level, `prettylog` will print a dot for each omitted log message. Disable this by:

	--no-dots

`prettylog` will also add a yellow "time gap" line between log entries that are more than 30 seconds apart. Disable this by:

	--no-gaps

To highlight text in the output (ANSI mode only) use the `--hilite` (or `-H`) option:

    --hilite=text
    --hilite='some words'

You can also provide regular expressions (following the [PCRE syntax](http://php.net/manual/en/reference.pcre.pattern.syntax.php) if you start the hilite value with a slash `/` and end it with `/` or `/i` (for case-insensitive):

    --hilite='/reg(ular)? ?ex(pression)?/i'

To print the usage and options:

	--help

Supported formats
-----------------

Currently there are parsers for:

- [monolog](https://github.com/Seldaek/monolog) `LineFormatter` and `JsonFormatter` syntax (such as [Symfony](http://symfony.com/) application logs)
- [php-fpm](http://php-fpm.org/) logs
- [syslog](http://en.wikipedia.org/wiki/Syslog) files

As part of my daily routine I tend to deal with the following log formats too, so I will probably add parsers for:

- [nginx](http://nginx.org/) access and error logs (need to add support for multi-line log entries)
- [supervisor](http://supervisord.org/) logs


Version 0.8-dev
===============

This is an early release. Use at your own risk. Comments and suggestions are welcome.

Building
========

Install [composer](https://getcomposer.org/):

	curl -sS https://getcomposer.org/installer | php

Install [box](http://box-project.org/):

	curl -LSs https://box-project.github.io/box2/installer.php | php
	sudo mv box.phar /usr/local/bin/box
	chmod 755 /usr/local/bin/box

Build:

	box build
