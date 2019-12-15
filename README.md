myBackware
==========

[![Project Status: Unsupported – The project has reached a stable, usable state but the author(s) have ceased all work on it. A new maintainer may be desired.](https://www.repostatus.org/badges/latest/unsupported.svg)](https://www.repostatus.org/#unsupported)

These scripts were developed to create sql dump files for basic disaster
recovery of small MySQL installations.

INSTALL
-------

This comes with two versions on how to get a backup to work. See:
filesystemsave.php
sqldumpcron.php

filesystemsave.php is pre-configured to save files in .bz2
compressed format to a Windows directory.

sqldumpcron.php is preconfigured to email backups to a single
recipient on a Windows computer, bz2 compressed, and gpg encrypted.

Both script should work, when reconfigured, to work on a Linux server.
