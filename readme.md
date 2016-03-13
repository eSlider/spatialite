Repository
================

Static linux binaries and PHP shell wrapper driver.


About
=====

SpatiaLite is an open source library intended to extend the SQLite core to support fully fledged Spatial SQL capabilities.
SQLite is intrinsically simple and lightweight:

* a single lightweight library implementing the full SQL engine
* standard SQL implementation: almost complete SQL-92
* no complex client/server architecture
* a whole database simply corresponds to a single monolithic file (no size limits)
* any DB-file can be safely exchanged across different platforms, because the internal architecture is universally portable
* no installation, no configuration

SpatiaLite is smoothly integrated into SQLite to provide a complete and powerful Spatial DBMS (mostly OGC-SFS compliant).
Using SQLite + SpatiaLite you can effectively deploy an alternative open source Spatial DBMS roughly equivalent to PostgreSQL + PostGIS.

SpatiaLite is licensed under the MPL tri-license terms; you are free to choose the best-fit license between:

* the [MPL 1.1](http://www.mozilla.org/MPL/MPL-1.1.html) 
* the [GPL v2.0](http://www.gnu.org/licenses/gpl-2.0.html#TOC1) or any subsequent version
* the [LGPL v2.1](http://www.gnu.org/licenses/lgpl-2.1.html) or any subsequent version


Develop
=======

`composer update`

Setup 
=====

In order to get native driver work, you need set absolute path of `sqlite3.extension_dir` variable in `php.ini` file to the `bin/x64` directory, where `mod_spatialite.so` can be found.
### Example: 
`sqlite3.extension_dir = /var/www/project_name/vendor/eslider/spatialite/bin/x64/mod_spatialite`


Tests
=====

PHP Unit test command:

`bin/phpunit tests`

By the first time tests creates an `spatialite.sqlite` file in the project directory. 
The file has geometries and spatial functions. This initial process take some time (>1 min). 
Next time tests runs faster.

Refer
=====

Official PHP repository located at http://www.gaia-gis.it/
Powered by  https://www.sqlite.org/
