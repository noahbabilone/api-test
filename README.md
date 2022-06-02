API
========

Requirements
------------

* PHP 7.2.5 or higher;
* PDO-SQLite PHP extension enabled;
* and the [usual Symfony application requirements][2].

Usage
-----
There's no need to configure anything to run the application. If you have
[installed Symfony][4] binary, run this command:

```bash
$ cd my_project/
$ symfony serve
```

Generate the SSL Keys
----------------------
```bash
$ php bin/console lexik:jwt:generate-keypair --skip-if-exists
```
