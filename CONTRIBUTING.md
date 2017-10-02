# Contributing Guidelines

**For feature additions or bug fixes on this project**
* [Fork this repository](https://help.github.com/articles/fork-a-repo).
* [Create a topic branch](http://git-scm.com/book/en/Git-Branching-Branching-Workflows).
* Make your feature addition or bug fix.
* Add tests for it. This is vital - please don't skip this part.
* Ensure that all tests pass, and that you have reasonable coverage, by running:

    ```
    composer install --dev
    vendor/bin/phpcs --standard=PSR2 src && vendor/bin/phpunit --coverage-text
    ```

    The [Travis CI build](https://travis-ci.org/justinbusschau/php-govtalk) runs on PHP `5.3`, `5.4` and `5.5`.

* Commit the modifications to your own forked repo in your topic branch.
* Ensure your code is nicely formatted in the [PSR-2](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md)
  style and that all tests pass.
* [Submit a pull request](https://help.github.com/articles/using-pull-requests).
* Check that the Travis CI build passed. If not, rinse and repeat.

**For adding a library to the list of libraries that currently use / extend GovTalk**
* Once you have a something that you want this library to reference,
  add the project to [Composer](http://getcomposer.org/) and [Travis](http://travis-ci.org).
* Fork this project
* Update the README.md (and nothing else) to include
  - The name of your library/project
  - The full name of the Composer Package
  - The name of the maintainer of the package
* Comit the changes to your own forked repo.
* [Submit a pull request](https://help.github.com/articles/using-pull-requests).