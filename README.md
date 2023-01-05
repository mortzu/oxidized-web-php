# Installation

* Allow ``exec`` and ``shell_exec`` in PHP
* Setup webserver to handle ``PATH_INFO`` correctly (for [nginx](https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/))
* Setup webserver vhost according to nginx.conf
* Copy config.defaults.php to config.php
* Set ``$oxidized_nodes_cache`` to a PHP writable file
* Set ``$oxidized_url`` to URL of oxidized (e.g. http://127.0.0.1:8888)
* Set ``$oxidized_repository_path`` to the Git repository of oxidized (have to be a non-bare repository)

# Non-bare Git repository

* Create a non-bare repository at the location of ``repo: /home/oxidized/backups``

Set up hooks to fix Git repository at commit:
```
hooks:
  check_out_index:
    type: exec
    events: [post_store]
    cmd: 'git -C /home/oxidized/backups checkout -- .'
    timeout: 60
  push_to_remote:
    type: exec
    events: [post_store]
    cmd: 'git -C /home/oxidized/backups push -q'
    timeout: 60
```
