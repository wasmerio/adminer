Adminer Customization
=====================

Customizations for [Adminer](https://www.adminer.org), the best database management tool written in PHP.

- clean design
- visual resolution for production/development servers
- SQL autocomplete plugin
- plugin that dumps to PHP code
- plugin that saves menu scroll position
- plugin that allows magic login with Wasmer (requires `WASMER_GRAPHQL_URL` environment variable)

Testing
-------

You can test the plugin locally by running the following command:

```
mkdir data
wasmer run . --net --mapdir=/data:data --env="WASMER_GRAPHQL_URL=https://registry.wasmer.io/graphql"
```

This will start the Adminer application in a Wasmer container and map the current directory to the `/data` directory.

Installation
------------

```
composer require dg/adminer
```

Create file `index.php` somewhere in your document root, for example in subdirectory `adminer/`:

```php
<?php
touch(__DIR__ . '/adminer.css');
require __DIR__ . '/../../vendor/dg/adminer/index.php'; // CHECK THAT THIS PATH IS CORRECT
```

And then navigate your browser to this directory (i.e. `http://localhost/adminer`).


Autologin
---------

If you want to login automatically (use that for non-public environment only, e.g. localhost), you can do that by setting environment variables `ADMINER_SERVER`, `ADMINER_USERNAME` and `ADMINER_PASSWORD`. If you want to be automatically redirected to https, set `ADMINER_HTTPS_REDIRECT` environment variable to `true`.


Login page
----------

![login page](https://dg.github.io/adminer/images/screenshot1.png)

Tables overview
---------------

![tables overview](https://dg.github.io/adminer/images/screenshot2.png)

Red top border indicates production server
------------------------------------------

![production server](https://dg.github.io/adminer/images/screenshot3.png)

Use `tab` for autocomplete
--------------------------

![autocomplete](https://dg.github.io/adminer/images/screenshot4.png)

Export as PHP code for [Nette Database](https://github.com/nette/database)
--------------------------------------------------------------------------

![export](https://dg.github.io/adminer/images/screenshot5.png?v1)

Export as PHP code for [Nette Forms](https://github.com/nette/forms)
--------------------------------------------------------------------------

![export](https://dg.github.io/adminer/images/screenshot6.png?v1)

![export](https://dg.github.io/adminer/images/screenshot7.png)
