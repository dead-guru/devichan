devichan - A Dockerized lightweight and full featured PHP imageboard based on vichan 
========================================================

Real board: https://4.dead.guru/

<img width="500" alt="image" src="https://user-images.githubusercontent.com/1472664/211690585-1732c076-4889-447f-88ff-8912b18b4a05.png">




New Features
------------
* docker-compose
* Updated twig (1 -> 3), jquery (2 -> 3)
* add 404 and 500 error pages
* Banners for each board
* statistics page (stats.php)
* Removed lot of dead code
* tons of small fixes of js and templates

About
------------
vichan is a free light-weight, fast, highly configurable and user-friendly
imageboard software package. It is written in PHP and has few dependencies.

While there is currently no active development besides fixing security problems, we don't exclude the possibility to refactor the code in order to meet today's standards and continue our work from the point where [@czaks](https://github.com/czaks) retired in 2017.
Before this milestone is achieved though, we strongly urge you to consider other imageboard packages. It is the opinion of the vichan development team that no new vichan imageboards should be deployed at the moment, and other imageboard packages used instead.

Some documentation may be found on vichan [wiki](https://github.com/vichan-devel/vichan/wiki). (feel free to contribute)

History
------------
devichan is a fork of (now defunc'd) [vichan](https://github.com/vichan-devel/vichan),
a great imageboard package, actively building on it and adding a lot of features and other
improvements.

Requirements
------------
1. Docker
2. Docker Compose

Contributing
------------
You can contribute to devichan by:
*	Developing patches/improvements/translations and using GitHub to submit pull requests
*	Providing feedback and suggestions
*	Writing/editing documentation

Configuration
-------------
1. For configuration create `inc/secrets.php`.
2. Advanced awesome configuration example: https://gist.github.com/assada/e551d4de17218d50cc1549c8cd6c2c09

Installation
-------------
1. Download and extract devichan to your web directory or get the latest
    development version with:

        git clone git@github.com:dead-guru/devichan.git

2. run ```docker-compose build``` inside the directory	
3. run ```docker-compose exec cphp composer install``` inside the directory	
4. Navigate to ```http://localhost/install.php``` in your web browser and follow the
    prompts.
5. devichan should now be installed. Log in to ```mod.php``` with the
    default username and password combination: **admin / password**.
6. You can install some "themes" on `/mod.php?/themes`


!!!Please remember to change the administrator account password.

See also: vichan [Configuration Basics](https://github.com/vichan-devel/vichan/wiki/config).

Upgrade
-------
To upgrade from any version of Tinyboard or vichan or devichan:

Either run ```git pull``` to update your files, if you used git, or
backup your ```inc/instance-config.php```, replace all your files in place
(don't remove boards etc.), then put ```inc/instance-config.php``` back and
finally run ```install.php```.

Support
--------
devichan is still beta software -- there are bound to be bugs. If you find a
bug, please report it.

CLI tools
-----------------
There are a few command line interface tools, based on Tinyboard-Tools. These need
to be launched from a Unix shell account (SSH, or something). They are located in a ```tools/```
directory.

You actually don't need these tools for your imageboard functioning, they are aimed
at the power users. You won't be able to run these from shared hosting accounts
(i.e. all free web servers).

Oekaki
------
vichan makes use of [wPaint](https://github.com/websanova/wPaint) for oekaki. After you pull the repository, however, you will need to download wPaint separately using git's `submodule` feature. Use the following commands:

```
git submodule init
git submodule update
```

To enable oekaki, add all the scripts listed in `js/wpaint.js` to your `instance-config.php`.

WebM support
------------
Read `inc/lib/webm/README.md` for information about enabling webm.

vichan API
----------
vichan provides by default a 4chan-compatible JSON API. For documentation on this, see:
https://github.com/vichan-devel/vichan-API/ .

License
--------
See [LICENSE.md](http://github.com/vichan-devel/vichan/blob/master/LICENSE.md).

