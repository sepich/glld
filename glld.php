<?php
//automate graphing of multiple LLD items on same graph

require_once dirname(__FILE__).'/include/config.inc.php';
require_once dirname(__FILE__).'/glld/glld.inc.php';
if (!empty($_GET['popup'])) itemPopup(); //popup for item proto select

$page['title'] = _('Graph multiple LLD items');
$page['file'] = 'glld.php';
$page['scripts'] = ['multiselect.js'];

require_once dirname(__FILE__).'/include/page_header.php';
ob_end_flush();

//routing
switch (getRequest('action')) {
  case 'task.massdisable':  taskStatus(true);    break;
  case 'task.massenable':   taskStatus(false);   break;
  case 'task.massrun':      taskRun();           break;
  case 'task.massclean':    taskClean();         break;
  case 'task.massdelete':   taskDelete();        break;
  default:
    if (isset($_REQUEST['form'])) taskEdit();
    else taskList();
}

require_once dirname(__FILE__).'/include/page_footer.php';