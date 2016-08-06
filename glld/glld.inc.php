<?php

$colors=['2ECC40','FF4136','001F3F','85144B','0074D9','FF851B','7FDBFF','F012BE','39CCCC','3D9970','01FF70','FFDC00','B10DC9','dddddd','aaaaaa','111111'];

//check if table exist
if (DBselect("SHOW TABLES LIKE 'glld'")->num_rows==0 ){
  if(!DBexecute("CREATE TABLE `glld` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `templateid` INT NOT NULL,
      `graph` TEXT NOT NULL,
      `items` TEXT NOT NULL,
      `disabled` BOOL NOT NULL)"))
    die("Unable to create table 'glld' for configuration storage");
}

//update/create graph on Host
function CheckHost($host, $task){
  echo "{$host['name']}: ";
  $tograph=getHostItems($host, $task); //filter items to find derived from prototype
  echo "Found ".count($tograph)." LLD items to draw. ";
  if(!count($tograph)) {echo "Check that user has Write access to this host!\n"; return;}

  //check if graph exist and has all items
  $graph = API::Graph()->get([
      'hostids' => $host['hostid'],
      'templated' => false,
      'inherited' => false,
      'selectItems' => [],
      'editable' => true,
      'filter' => ['name' => $task['graph']['name']]
    ]);

  if(!$graph) {
    $g=MakeGraph($task, $tograph);
    echo "Graph does not exist, created ({$g['graphid']})\n";
  }
  elseif(count($graph[0]['items']) != count($tograph)) {
    $g=MakeGraph($task, $tograph, $graph[0]['graphid']);
    echo "Graph exist with different items count, updated ({$g['graphid']})\n";
  }
  else {
    $graph=reset($graph); //[0]
    //check graph settings
    $eq=true;
    foreach(['width', 'height', 'graphtype', 'show_legend', 'show_work_period', 'show_triggers', 'percent_left', 'percent_right', 'ymin_type', 'yaxismin', 'ymax_type', 'yaxismax'] as $param){
      if($graph[$param]!=$task['graph'][$param]) {
        echo "Param $param differs, updating\n";
        print_r($graph);
        print_r($task['graph']);
        $eq=false;
        break;
      }
    }
    //check that all needed items are drawn (to check items settings there would need another API query for each = slow)
    if($eq) {
      $drawn=[];
      foreach($graph['items'] as $i) $drawn[]=$i['itemid'];
      foreach($tograph as $i) if(array_search($i['itemid'], $drawn)===false) { $eq=false; break; }
      if(!$eq) echo "Not all needed items drawn, updating\n";
    }
    if($eq) echo "Graph exist and has all the items, skipping\n";
    else MakeGraph($task, $tograph, $graph['graphid']);
  }
}

//create or update graph
function MakeGraph($task, $tograph, $graphid=0){
  global $colors;
  $colornum=0;

  //prepare items
  foreach($tograph as $i){
    $gitems[] = array_merge(
      $task['items'][ $i['itemDiscovery']['parent_itemid'] ],
      array(
          'itemid'=>$i['itemid'],
          'color' => $colors[$colornum]
        )
    );
    $colornum++;
    if($colornum >= count($colors)) $colornum=0;
  }
  $data=$task['graph'];
  $data['gitems']=$gitems;
  //draw
  if($graphid) {
    $data['graphid']=$graphid;
    return API::Graph()->update($data);
  }
  else return API::Graph()->create($data);
}

//show list
function taskList(){
  //get model
  $tasks=LoadTasks();

  //prepare view
  $widget = (new CWidget())
    ->setTitle(_('Graph LLD templates'))
    ->setControls((new CForm('get'))
      ->cleanItems()
      ->addItem((new CList())->addItem(new CSubmit('form', _('Create graph'))))
    );

  $tasksForm = (new CForm())->setName('tasksForm');
  $tasksTable = (new CTableInfo())
    ->setHeader([
      (new CColHeader(
        (new CCheckBox('all_tasks'))->onClick("checkAll('".$tasksForm->getName()."', 'all_tasks', 'taskid');")
      ))->addClass(ZBX_STYLE_CELL_WIDTH),
      _('Name'),
      _('Type'),
      _('Template'),
      _('Prototypes'),
      _('Status')
    ]);
  foreach ($tasks as $task) {
    $status = new CCol(
      (new CLink(
        discovery_status2str($task['status']),
        '?taskid[]='.$task['id'].
        '&action='.($task['status']==0 ? 'task.massdisable' : 'task.massenable')
      ))
        ->addClass(ZBX_STYLE_LINK_ACTION)
        ->addClass(discovery_status2style($task['status']))
        ->addSID()
    );
    $proto='';
    foreach($task['items'] as $item) $proto.=", {$item['name']}";
    $proto=substr($proto, 2);

    $tasksTable->addRow([
      new CCheckBox("taskid[{$task['id']}]", $task['id']),
      new CLink($task['graph']['name'], '?form=update&id='.$task['id']),
      graphType($task['graph']['graphtype']),
      $task['template'],
      $proto,
      $status
    ]);
  }

  // append table to form
  $tasksForm->addItem([
    $tasksTable,
    //$this->data['paging'],
    new CActionButtonList('action', 'taskid', [
      'task.massenable' => ['name' => _('Enable'), 'confirm' => _('Enable selected graphs?')],
      'task.massdisable' => ['name' => _('Disable'), 'confirm' => _('Disable selected graphs?')],
      'task.massrun' => ['name' => _('Run'), 'confirm' => _('Apply this graph to hosts?')],
      'task.massclean' => ['name' => _('Clean'), 'confirm' => _('Clean this graph from hosts?')],
      'task.massdelete' => ['name' => _('Delete'), 'confirm' => _('Delete selected graph?')]
    ])
  ]);

  // append form to widget
  $widget->addItem($tasksForm);
  $widget->show();
}

//edit/add entry
function taskEdit(){
  $id=(int)getRequest('id');
  if(getRequest('delete')) taskDelete(); //delete form button

  //read from db
  if($id && !getRequest('form_refresh')) $data=LoadTasks($id);
  //defaults
  else {
    $checkbox = empty($_POST['form']) ? 1 : 0;
    $data = [
      'id' => $id,
      'templateid' => (int)getRequest('templateid'),
      'graph' => [
        'name' => getRequest('name'),
        'width' => getRequest('width',900),
        'height' => getRequest('height',200),
        'graphtype' => getRequest('graphtype',0),
        'show_legend' => getRequest('show_legend', $checkbox),
        'show_work_period' => getRequest('show_work_period', $checkbox),
        'show_triggers' => getRequest('show_triggers', $checkbox),
        'percent_left' => getRequest('percent_left', 0),
        'percent_right' => getRequest('percent_right',0),
        'ymin_type' => getRequest('ymin_type',GRAPH_YAXIS_TYPE_CALCULATED),
        'yaxismin' => getRequest('yaxismin',0),
        'ymax_type' => getRequest('ymax_type',GRAPH_YAXIS_TYPE_CALCULATED),
        'yaxismax' => getRequest('yaxismax',100),
        'show_3d' => getRequest('show_3d',1)
      ],
      'items' => getRequest('items',[])
    ];
  }
  //read template/proto names by id
  if($data['templateid']){
    $getInfo = API::Template()->get([
      'templateids' => $data['templateid'],
      'output' => ['name']
    ]);
    $data['template'] = empty($getInfo) ? 'error' : $getInfo[0]['name'];
    $jsdata = [[ "id"=>$data['templateid'], "name" => $data['template'] ]];
  }
  else $jsdata=null;
  foreach($data['items'] as $k=>$v) {
    $getInfo = API::ItemPrototype()->get([
      'itemids' => $k,
      'output' => ['name']
    ]);
    $data['items'][$k]['name'] = empty($getInfo) ? 'error' : $getInfo[0]['name'];
  }

  //validate on save
  if(!empty($_POST['add']) || !empty($_POST['update'])) {
    $fields=[
      'name' =>             [T_ZBX_STR, O_OPT, null,    NOT_EMPTY,    null],
      'width' =>            [T_ZBX_INT, O_OPT, null,    BETWEEN(20, 65535),  null],
      'height' =>           [T_ZBX_INT, O_OPT, null,    BETWEEN(20, 65535),  null],
      'graphtype' =>        [T_ZBX_INT, O_OPT, null,    IN('0,1,2,3'),   null],
      'show_3d' =>          [T_ZBX_INT, O_OPT, P_NZERO, IN('0,1'),    null],
      'show_legend' =>      [T_ZBX_INT, O_OPT, P_NZERO, IN('0,1'),    null],
      'ymin_type' =>        [T_ZBX_INT, O_OPT, null,    IN('0,1,2'),  null],
      'ymax_type' =>        [T_ZBX_INT, O_OPT, null,    IN('0,1,2'),  null],
      'percent_left' =>     [T_ZBX_DBL, O_OPT, null,    BETWEEN(0, 100), null, _('Percentile line (left)')],
      'percent_right' =>    [T_ZBX_DBL, O_OPT, null,    BETWEEN(0, 100), null, _('Percentile line (right)')],
      'items' =>            [T_ZBX_STR, O_OPT, null,    null,     null],
      'show_work_period' => [T_ZBX_INT, O_OPT, null,    IN('1'),    null],
      'show_triggers' =>    [T_ZBX_INT, O_OPT, null,    IN('1'),    null],
      'templateid' =>       [T_ZBX_INT, O_OPT, null,    NOT_EMPTY,      null],
      'form' =>             [T_ZBX_STR, O_OPT, P_SYS,   null,     null]
    ];
    if(check_fields($fields)) {
      if (!$data['templateid']) show_messages(false, '', "No template selected");
      elseif (!count($data['items'])) show_messages(false, '', "No items assigned");
      else {
        if(getRequest('form')=='update') $old=LoadTasks($id); //for graphs name update
        DBstart();
        $sql="templateid={$data['templateid']}, graph=".zbx_dbstr(serialize($data['graph'])).", items=".zbx_dbstr(serialize($data['items']));
        $result = getRequest('form')=='update' ? DBexecute("UPDATE glld SET $sql WHERE id=$id") : DBexecute("INSERT INTO glld SET $sql");
        DBend($result);
        if($result) {
          if(getRequest('form')=='update') taskUpdate($old, $data);
          header("Location: ?"); die();
        }
      }
    }
  }

  //prepare view
  $widget = new CWidget();
  if($id) $widget->setTitle(_('Edit graph'));
  else $widget->setTitle(_('New graph'));

  // create form
  $glldForm = (new CForm())
    ->setName('glldForm')
    ->addVar('form', getRequest('form'));
  if ($id) {
    $glldForm->addVar('id', $id);
  }
  foreach($data['items'] as $k=>$item) {
    $item=array_merge(['calc_fnc'=>2, 'drawtype' =>0, 'yaxisside' => 0, 'type'=>0], $item); //defaults
    $tag=new CInput('hidden', 'item[][id]', $k);
    $tag->setAttribute('data-name',$item['name']);
    $tag->setAttribute('data-calc',$item['calc_fnc']);
    $tag->setAttribute('data-drawtype',$item['drawtype']);
    $tag->setAttribute('data-yaxisside',$item['yaxisside']);
    $tag->setAttribute('data-type',$item['type']);
    $glldForm->addItem($tag);
  }

  // create form list
  $glldFormList = new CFormList('glldFormList');
  $glldFormList
    ->addRow(_('Name'),
      (new CTextBox('name', $data['graph']['name']))
        ->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
        ->setAttribute('autofocus', 'autofocus')
    )
    ->addRow(_('Width'),
      (new CNumericBox('width', $data['graph']['width'], 5))
        ->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
    )
    ->addRow(_('Height'),
      (new CNumericBox('height', $data['graph']['height'], 5))
        ->setWidth(ZBX_TEXTAREA_NUMERIC_STANDARD_WIDTH)
    )
    ->addRow(_('Graph type'),
      (new CComboBox('graphtype', $data['graph']['graphtype'], 'submit()', graphType()))
    )
    ->addRow(_('Show legend'),
      (new CCheckBox('show_legend'))
        ->setChecked($data['graph']['show_legend'] == 1)
    );

  // append graph types to form list
  if ($data['graph']['graphtype'] == GRAPH_TYPE_NORMAL || $data['graph']['graphtype'] == GRAPH_TYPE_STACKED) {
    $glldFormList->addRow(_('Show working time'),
      (new CCheckBox('show_work_period'))
        ->setChecked($data['graph']['show_work_period'] == 1)
    );
    $glldFormList->addRow(_('Show triggers'),
      (new CCheckbox('show_triggers'))
        ->setchecked($data['graph']['show_triggers'] == 1)
    );

    if ($data['graph']['graphtype'] == GRAPH_TYPE_NORMAL) {
      // percent left
      $percentLeftTextBox = (new CTextBox('percent_left', $data['graph']['percent_left'], 0, 7))
        ->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
      $percentLeftCheckbox = (new CCheckBox('visible[percent_left]'))
        ->setChecked(true)
        ->onClick('javascript: showHideVisible("percent_left");');

      if(isset($_POST['visible']) && isset($_POST['visible']['percent_left'])) {
        $percentLeftCheckbox->setChecked(true);
      }
      elseif ($data['graph']['percent_left'] == 0) {
        $percentLeftTextBox->addStyle('visibility: hidden;');
        $percentLeftCheckbox->setChecked(false);
      }

      $glldFormList->addRow(_('Percentile line (left)'), [$percentLeftCheckbox, SPACE, $percentLeftTextBox]);

      // percent right
      $percentRightTextBox = (new CTextBox('percent_right', $data['graph']['percent_right'], 0, 7))
        ->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
      $percentRightCheckbox = (new CCheckBox('visible[percent_right]'))
        ->setChecked(true)
        ->onClick('javascript: showHideVisible("percent_right");');

      if(isset($_POST['visible']) && isset($_POST['visible']['percent_right'])) {
        $percentRightCheckbox->setChecked(true);
      }
      elseif ($data['graph']['percent_right'] == 0) {
        $percentRightTextBox->addStyle('visibility: hidden;');
        $percentRightCheckbox->setChecked(false);
      }

      $glldFormList->addRow(_('Percentile line (right)'), [$percentRightCheckbox, SPACE, $percentRightTextBox]);
    }

    $yaxisMinData = [(new CComboBox('ymin_type', $data['graph']['ymin_type'], null, [
      GRAPH_YAXIS_TYPE_CALCULATED => _('Calculated'),
      GRAPH_YAXIS_TYPE_FIXED => _('Fixed')
    ]))];

    if ($data['graph']['ymin_type'] == GRAPH_YAXIS_TYPE_FIXED) {
      $yaxisMinData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
      $yaxisMinData[] = (new CTextBox('yaxismin', $data['graph']['yaxismin'], 0))
        ->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
    }
    else {
      $glldForm->addVar('yaxismin', $data['graph']['yaxismin']);
    }

    $glldFormList->addRow(_('Y axis MIN value'), $yaxisMinData);

    $yaxisMaxData = [(new CComboBox('ymax_type', $data['graph']['ymax_type'], null, [
      GRAPH_YAXIS_TYPE_CALCULATED => _('Calculated'),
      GRAPH_YAXIS_TYPE_FIXED => _('Fixed')
    ]))];

    if ($data['graph']['ymax_type'] == GRAPH_YAXIS_TYPE_FIXED) {
      $yaxisMaxData[] = (new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN);
      $yaxisMaxData[] = (new CTextBox('yaxismax', $data['graph']['yaxismax'], 0))
        ->setWidth(ZBX_TEXTAREA_TINY_WIDTH);
    }
    else {
      $glldForm->addVar('yaxismax', $data['graph']['yaxismax']);
    }

    $glldFormList->addRow(_('Y axis MAX value'), $yaxisMaxData);
  }
  else {
    $glldFormList->addRow(_('3D view'),
      (new CCheckBox('show_3d'))
        ->setChecked($data['graph']['show_3d'] == 1)
    );
  }

  $glldFormList
    ->addRow(_('Template'),
      (new CMultiSelect([
        'name' => 'templateid',
        'objectName' => 'templates',
        'selectedLimit' => 1,
        'data' => $jsdata,
        'popup' => [
          'parameters' => 'srctbl=templates&srcfld1=hostid&srcfld2=host&dstfrm=glldForm'.
            '&dstfld1=templateid&templated_hosts=1&multiselect=0&with_items=1'
        ]
      ]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
    );

  // append graph items
  $itemsTable = (new CTable())
    ->setId('itemsTable')
    ->setHeader([
      (new CColHeader(_('Name')))->setWidth(($data['graph']['graphtype'] == GRAPH_TYPE_NORMAL) ? 280 : 360),
      ($data['graph']['graphtype'] == GRAPH_TYPE_PIE || $data['graph']['graphtype'] == GRAPH_TYPE_EXPLODED)
        ? (new CColHeader(_('Type')))->setWidth(80)
        : null,
      (new CColHeader(_('Function')))->setWidth(80),
      ($data['graph']['graphtype'] == GRAPH_TYPE_NORMAL)
        ? (new CColHeader(_('Draw style')))
          ->addClass(ZBX_STYLE_NOWRAP)
          ->setWidth(80)
        : null,
      ($data['graph']['graphtype'] == GRAPH_TYPE_NORMAL || $data['graph']['graphtype'] == GRAPH_TYPE_STACKED)
        ? (new CColHeader(_('Y axis side')))
          ->addClass(ZBX_STYLE_NOWRAP)
          ->setWidth(80)
        : null,
      (new CColHeader(_('Action')))
        ->addClass(ZBX_STYLE_NOWRAP)
        ->setWidth(80)
    ])
    ->setFooter(
      (new CCol(
        (new CButton('add_protoitem', _('Add prototype')))->addClass(ZBX_STYLE_BTN_LINK)
      ))->setColSpan(5)->setId('itemButtonsRow')
    );

  $glldFormList->addRow(_('Items'), (new CDiv($itemsTable))->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR));

  // append tabs to form
  $glldTabs = (new CTabView())->addTab('glldTab', _('Discovery rule'), $glldFormList);

  // append buttons to form
  if($id) {
    $glldTabs->setFooter(makeFormFooter(
      new CSubmit('update', _('Update')),
      [
        new CButtonDelete(_('Delete graph?'), url_param('form').url_param(['taskid[]'=>$id], 0)),
        new CButtonCancel()
      ]
    ));
  }
  else {
    $glldTabs->setFooter(makeFormFooter(
      new CSubmit('add', _('Add')),
      [new CButtonCancel()]
    ));
  }

  $glldForm->addItem($glldTabs);
  $widget->addItem($glldForm);
  $widget->addItem("<script src='glld/glld.js' type='text/javascript'></script>");
  $widget->show();
}

//generate popup table for LLDs of template
function itemPopup(){
  $page['title'] = 'Item prototypes';
  //$page['file'] = 'popup.php';
  define('ZBX_PAGE_NO_MENU', 1);
  require_once dirname(__FILE__).'/../include/page_header.php';

  $widget = (new CWidget())->setTitle('Item prototypes');
  $table = (new CTableInfo())->setHeader([' ', _('Name'), _('Key'), _('Type'), _('Status')]);
  $table->setNoDataMessage(_('No data found. Is template selected?'));

  $items = API::ItemPrototype()->get([
    'output' => ['itemid', 'name', 'key_', 'flags', 'type', 'value_type', 'status', 'state'],
    'templateids' => $_GET['popup']
  ]);

  order_result($items, 'name');
  foreach ($items as $item) {
    $name = (new CLink($item['name'], '#'))->setAttribute('data-id', $item['itemid'])->addClass('item_name');
    $table->addRow([
      new CCheckBox('items['.zbx_jsValue($item['itemid']).']', $item['itemid']),
      $name,
      $item['key_'],
      itemValueTypeString($item['value_type']),
      (new CSpan(itemIndicator($item['status'], $item['state'])))
        ->addClass(itemIndicatorStyle($item['status'], $item['state']))
      ]);
  }
  if (count($items)) $table->setFooter(
    new CCol( new CButton('mselect', _('Select')) )
  );
  $widget->addItem("<script src='glld/glld.js' type='text/javascript'></script>");
  $widget->addItem($table)->show();

  require_once dirname(__FILE__).'/../include/page_footer.php';
  die();
}

//enable/disable tasks
function taskStatus($disable){
  DBstart();
  $result=DBexecute("UPDATE glld SET disabled=".(int)$disable." WHERE id IN(".implode(',', getRequest('taskid')).")");
  DBend($result);
  if($result) {header("Location: ?"); die();}
}

//cleanup and delete task
function taskDelete(){
  taskClean();
  DBstart();
  $result=DBexecute("DELETE FROM glld WHERE id IN(".implode(',', getRequest('taskid')).")");
  DBend($result);
  if($result) {header("Location: ?"); die();}
}

//run task
function taskRun(){
  echo "<pre>";
  foreach(getRequest('taskid') as $id){
    $task=LoadTasks($id);
    if(!$task) {echo "\nTask id=$id not found!\n"; continue;}
    if($task['status']) {echo "\nGraph '{$task['graph']['name']}' is disabled\n"; continue;}
    $hosts = getHosts($task['templateid']);
    echo "\nChecking graph '{$task['graph']['name']}' on ".count($hosts)." host(s)\n";
    foreach($hosts as $host) CheckHost($host, $task);
  }
  echo "</pre>";
}

//clean all graphs for task
function taskClean() {
  echo "<pre>";
  foreach(getRequest('taskid') as $id){
    $task=LoadTasks($id);
    if(!$task) echo "Task id=$id not found!\n";
    $hosts = getHosts($task['templateid']);
    echo "\nRemoving graph '{$task['graph']['name']}' from ".count($hosts)." host(s)\n";
    foreach($hosts as $host){
      $graph = API::Graph()->get([
        'hostids' => $host['hostid'],
        'templated' => false,
        'inherited' => false,
        'editable' => true,
        'filter' => ['name' => $task['graph']['name']],
        'output' => []
      ]);
      if(!$graph) echo "{$host['name']}: graph not found\n";
      else API::Graph()->delete([$graph[0]['graphid']]);
    }
  }
  echo "</pre>";
}

//get all hosts in given template and its child templates
function getHosts($templateid){
  $result=[];
  $hosts = API::Host()->get([
    'output' => ['name','status'],
    'templateids' => $templateid,
    //'limit' => 1,
    //'editable' => true
    'templated_hosts' => true
  ]);
  foreach($hosts as $host){
    if($host['status']==0) $result[]=$host;
    else if($host['status']==3) $result=array_merge($result, getHosts($host['hostid']));
  }
  return $result;
}

//load task(s) from db, no id = all
function LoadTasks($id=null){
  $tasks=[];
  $where=($id)? "WHERE id=".(int)$id : "";
  $res=DBselect("SELECT * FROM glld $where");
  if($res->num_rows) {
    while ($row=DBfetch($res)) {
      $tasks[]=[
        'id' => $row['id'],
        'templateid' => $row['templateid'],
        'graph' => unserialize($row['graph']),
        'items' => unserialize($row['items']),
        'status' => $row['disabled']
      ];
    }
  } else return false;
  //read templates name by id
  foreach($tasks as $k=>$task) {
    $getInfo = API::Template()->get([
      'templateids' => $task['templateid'],
      'output' => ['name']
    ]);
    $tasks[$k]['template'] = empty($getInfo) ? 'error' : $getInfo[0]['name'];
  }
  return ($id) ? reset($tasks) : $tasks;
}

//filter all host items to find derived from our prototype
function getHostItems($host, $task){
  $tograph=[];
  $cache=[]; //speed up processing parent chain
  $items = API::Item()->get([
      'output' => [],
      'selectItemDiscovery' => ['parent_itemid'],
      'hostids' => $host['hostid'],
      'inherited' => false,
      'monitored' => true,
      'editable' => true
    ]);

  foreach ($items as $i) {
    if(!isset($i['itemDiscovery']['parent_itemid'])) continue; //manually created (non-LLD inherited) item
    $pid=$i['itemDiscovery']['parent_itemid']; //iherited from
    if(array_key_exists($pid, $cache)){
      if($cache[$pid]) { //found in cache, get proto in one step
        $i['itemDiscovery']['parent_itemid']=$cache[$pid];
        $tograph[]=$i;
      }
      continue; //else - negative cache
    }
    if(array_key_exists($pid, $task['items'])) $tograph[]=$i; //directly inherited from proto (no chain)
    else {
      $parent=['templateid' => $pid];
      while ($parent['templateid']) { //read all chain to the top
        $parent = API::ItemPrototype()->get([ 'output' => ['templateid'], 'itemids' => $parent['templateid'] ]);
        $parent = reset($parent); //[0]
        if(!$parent['templateid']) $cache[$pid]=false; //the top
        if(array_key_exists($parent['itemid'], $task['items'])) {  //found
          $cache[$pid]=$parent['itemid']; //cache proto
          $i['itemDiscovery']['parent_itemid']=$parent['itemid']; //save proto to item
          $tograph[]=$i;
          break;
        }
      }
    }
  }
  return $tograph;
}

//force update
function taskUpdate($old, $new){
    // $hosts = getHosts($task['templateid']);
    // echo "\nChecking graph '{$task['graph']['name']}' on ".count($hosts)." host(s)\n";
    // foreach($hosts as $host) CheckHost($host, $task);
}