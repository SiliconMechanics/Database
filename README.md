#  Database Interface Abstraction Library

This is the database interface abstraction library used by (Silicon Mechanics)[http://www.siliconmechanics.com/].  It is derived from the library used by the (MythWeb)[http://www.mythtv.org/wiki/MythWeb] plugin for (MythTV)[http://www.mythtv.org/].  The original author provided the code to us under the terms that we would release changes back to the open source community.

This code is licensed under the GPL.

## Requirements

 - PHP 5.0 or better
 - The mysql, mysqli, pg, or PDO extensions

## Justification

The PHP world doesn't need another database interface abstraction library.  That's what PDO is for.  This code was originally written when PDO was immature and most other options weren't mature enough.  You *probably* should not use this code for new projects in favor of PDO.

## Bugs

The library is mature and well-tested in a high-pressure production environment.  This forked version has not been well tested yet.  No bugs are expected.  Find one and we'll squish it.

