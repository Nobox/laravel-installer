# Nobox Laravel Installer


#### Server Requirements

* PHP >= 5.5.9

#### Installer requirements

* composer
* git (if you want the installer takes care of linking your project to a empty github repository)


#### Via Nobox Installer

Download the nobox installer using Composer:

```
composer global require "nobox/nobox-laravel-installer=~1.5"
```

Once installed make sure place the ``~/.composer/vendor/bin directory`` in your **PATH** so the ``nobox`` executable could be located by your system.


Now you can install a new project with the nobox fork of laravel just using the **nobox new** command in the directory you specify.

```
nobox new project-name
```

If you want to see all the commands instalation logs use the flag -v


#### What includes the installation?

1. ``composer install`` ( will install all composer dependencies )
2. ``npm install`` ( will install all the npm dependencies )
3. ``bower install`` ( will install all the bower dependencies)
4. ``cp .env.example .env && php artisan key:generate`` ( prepare your local .env file and generates app key )
5. ``gulp`` (compiles the assets so the site is ready)
5. (optional) Links empty github repository to your project directory

