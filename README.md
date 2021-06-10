# bom-gif
A script for generating a gif of the last hour of BOM rain radar images.

## Install
Install dependencies using composer.

```bash
$ composer install
```

You may want to change some of the params at the top of `bom-gif.php`.

## Usage
You can make a gif via the command line:

```bash
$ php index.php > bom.gif
```

Alternatively, you can serve the script via a web server and see the results in
your browser.

## Requirements
* PHP 7.0.
* PHP GD module.
* Write access to whatever directory you set as the cache dir.
