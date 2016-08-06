# Graph LLD
This is Zabbix 3 plugin to allow graph multiple LLD items on the same chart ([ZBXNEXT-927](https://support.zabbix.com/browse/ZBXNEXT-927)).  
For example you might have some `disk discovery`, which creates item `free disk space, %` for each disk found. With this plugin now you can create and automatically keep in sync graph with all the items on single chart:  
![Free](https://habrastorage.org/files/a14/1ac/18b/a141ac18b87e4bf4bebde40539c178d3.png)  
Interface for this looks mostly the same as for usual `Graph prototype`:   
![gLLD Edit](https://habrastorage.org/files/33d/91c/d81/33d91cd8110b4d39a8785f23307479cc.png)  
with additional field for `Template`. 
You can create multiple such `Graphs` for different Templates and ItemPrototypes. Also you can mix different ItemPrototypes of same Template on one chart.
![gLLD List](https://habrastorage.org/files/afc/68b/2a5/afc68b2a54b04fdab1a9c3ce91303c2a.png)
When `Graph` is created such actions possible:
* *Run* - create standalone Graphs for selected prototype(s)
* *Clean* - remove generated standalone graphs from all hosts in Template
* *Delete* - same as Clean, but also removes Graph entry

## How it works
When you executing *Run* action this happens:
* Search for Hosts having this Template assigned
* For each Host - search LLD items derived from ItemPrototype(s) selected for Graph
* Create or update standalone Graph on Host with found Items (will update only if something changed)

## Installation
Unzip (or clone) to root of your zabbix-frontend-php folder (which is `/usr/share/zabbix` for Debian)  
Then add to `Main Menu` with something like this:
```diff
+++ include/menu.inc.php	2016-07-24 13:04:06.000000000 -0700
@@ -240,6 +240,10 @@
 					'url' => 'services.php',
 					'label' => _('IT services')
 				],
+				[
+					'url' => 'glld.php',
+					'label' => _('GLLD')
+				],
 			]
 		],
 		'admin' => [
```

Now you can create new Graphs and apply them in Zabbix Web Interface. But as time goes - discovery would find new items (or remove missed ones) and standalone Graphs would become outdated. One solution is to press *Run* to update them time to time. Better solution is to configure cron job, for this fix `/glld/glld.cli.php` with service user creds:
```php
#!/usr/bin/env php
<?php PHP_SAPI === 'cli' or die();
$auth=['user' => '', 'password' => '']; //fix this with valid user/password having Write access to Hosts
...
```
Note that user should have write access to Host on which you going to create Graphs.  
Check it executing `glld/glld.cli.php` from shell (you might need to do `chmod +x glld/glld.cli.php`)  
Then add it to cron, for example via such `/etc/cron.d/zabbix-glld-update`:  
```bash
#m h dom mon dow user     command
47 * *   *   *   www-data /usr/share/zabbix/glld/glld.cli.php >/dev/null
```
This would update graphs once per hour (on each 47th minute)
