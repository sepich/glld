#!/usr/bin/env php
<?php PHP_SAPI === 'cli' or die();
$auth=['user' => '', 'password' => '']; //fix this with valid user/password having Write access to Hosts
ini_set('display_errors', '1');

//auth
require_once dirname(__FILE__).'/../include/classes/core/Z.php';
Z::getInstance()->run(ZBase::EXEC_MODE_API);
$ssid=API::User()->login($auth);
if(!$ssid) die("Unable to login with provided credentials!\n");
API::getWrapper()->auth = $ssid;

require_once dirname(__FILE__).'/../include/db.inc.php';
require_once 'glld.inc.php';

//run all enabled tasks
foreach(taskLoad() as $task){
  if($task['status']) {echo "\nGraph '{$task['graph']['name']}' is disabled\n"; continue;}
  $hosts = getHosts($task['templateid']);
  echo "\nChecking graph '{$task['graph']['name']}' on ".count($hosts)." host(s)\n";
  foreach($hosts as $host) graphCheck($host, $task);
}
echo "\nDone.\n";