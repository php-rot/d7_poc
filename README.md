# php-rot concept applied to Drupal 7

This repository takes portions of the proof-of-concept found in the
[main repository](https://github.com/php-rot/rot) to demonstrate that a
Drupal 7 site including static assets (css, js, etc) can be moved outside the
docroot and served through an immutable bootloader.

If you wanted to set this up & run it, you would
 1. Host the `docroot` directory on a webserver. (`php -S` is not sufficient.)
 2. In the repository root, `mkdir a`.  Untar Drupal 7 in here.
 
# Roadmap
The main repo currently incorporates a simple approach at actually
updating composer packages in the inactive mutable area. That functionality
is not present here - instead the main next goals would be
  * to implement a software installer/updater separate from the bootloader -
    beyond PoC, these arguably become separate concerns.
  * for a regular Drupal 7 module installable via the standard methods to
    be able to perform all steps (except, if necessary, document root changing)
    to transition an existing site to a php-rot enabled site.