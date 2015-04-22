
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

When filtered for log level, prettylog will print a dot for each omitted log message. Disable this by:

	--no-dots

Prettylog will also add a yellow "time gap" line between log entries that are more than 30 seconds apart. Disable this by:

	--no-gaps

To print the usage and options:

	--help


Version 0.1-dev
===============

This is a very early release. Use at your own risk. Comments and suggestions are welcome.

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
