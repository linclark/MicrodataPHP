MicrodataPHP [![Build Status](https://travis-ci.org/linclark/MicrodataPHP.svg?branch=2.0.x)](https://travis-ci.org/linclark/MicrodataPHP)
============

Microdata is a syntax for embedding machine-readable metadata in HTML.

MicrodataPHP is a PHP library for extracting microdata from HTML documents. It
is inspired by MicrodataJS, which is inspired by the native Microdata DOM API.

Example use:

```
require 'vendor/autoload.php';

use linclark\MicrodataPHP\MicrodataPhp;

$url = 'http://example.com';
$md = new MicrodataPhp($url);
$data = $md->obj();

// Get a property of a top level item.
print $data->items[0]->properties['name'][0];

// Get a property of a nested item.
print $data->items[0]->properties['hiringOrganization'][0]->properties['name'][0];
```

Requirements
============
- Autoloading (e.g. the autoloader provided by [Composer](https://getcomposer.org/))
- PHP 5.3+

Check out the 1.x branch for older setups.
