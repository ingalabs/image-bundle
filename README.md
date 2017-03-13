Image bundle
============

[![Build Status](https://travis-ci.org/ingalabs/image-bundle.svg?branch=master)](https://travis-ci.org/ingalabs/image-bundle) [![Coverage Status](https://coveralls.io/repos/github/ingalabs/image-bundle/badge.svg)](https://coveralls.io/github/ingalabs/image-bundle?branch=master) [![Latest Stable Version](https://poser.pugx.org/ingalabs/image-bundle/v/stable)](https://packagist.org/packages/ingalabs/image-bundle) [![Latest Unstable Version](https://poser.pugx.org/ingalabs/image-bundle/v/unstable)](https://packagist.org/packages/ingalabs/image-bundle) [![License](https://poser.pugx.org/ingalabs/image-bundle/license)](https://packagist.org/packages/ingalabs/image-bundle)

Image serving bundle.

Installation
------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require inagalabs/image-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...

            new IngaLabs\Bundle\ImageBundle\IngaLabsImageBundle(),
        ];

        // ...
    }

    // ...
}
```

License
-------

This bundle is under [MIT License](http://opensource.org/licenses/mit-license.php).
