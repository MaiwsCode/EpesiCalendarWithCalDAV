<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');
require 'client/CalDAVCalendar.php';
require 'client/CalDAVClient.php';
require 'client/CalDAVObject.php';
require 'client/CalDAVFilter.php';
require 'client/CalDAVException.php';
require 'client/SimpleCalDAVClient.php';
require 'client/class.iCalReader.php';
require 'client/helper.php';

class iCalSyncCommon extends ModuleCommon {

    public static function user_settings() {
        return array("iCalSync"=> 'settings');
     }

    public static function cron() {
        return array(
           'insert_from_server' => 2, 
           'update_changes_from_server' => 3,
        );
    }


    public static function insert_from_server() {
        unlink('data.ics');
        $helper = new helper();
        $rbo = new RBO_RecordsetAccessor('contact');
        $rbo_meet = new RBO_RecordsetAccessor('crm_meeting');
        $rbo_cal_events = new RBO_RecordsetAccessor('calendar_sync');
        $users_urls = $rbo->get_records(array('!calendar_url' => ''));
        $list_of_uid = array();
        foreach($users_urls as $user ){    
            $client = new SimpleCalDAVClient();
            $correct_url = false;
            try{  
                $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                $arrayOfCalendars = $client->findCalendars(); 
                if(count($arrayOfCalendars)>0){
                    $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                    $correct_url = true;
                }else{
                }
            }
            catch(Exception $e){}
            if($correct_url){
                $start = $helper->get_date();
                $result = $client->GetEvents($start);
                for( $i = 0; $i < count($result); $i++) {
                    $exist = false;
                    $obj = new CalDAVObject($result[$i]->getHref(), $result[$i]->getData(), $result[$i]->getEtag());
                    $file = fopen("data.ics","w+");
                    fputs($file,$obj->getData(), strlen($obj->getData()));
                    fclose($file);
                    $ical = new ical('data.ics');
                    $event = $ical->events();
                    $uid = $event[0]["UID"];
                    $list_of_uid[] = $uid;
                    $cal_events = $rbo_cal_events->get_records(array('uid'=>$uid),array(),array());
                    if(count($cal_events) > 0){
                        print("Ten event już istnieje <BR>");
                    }
                    else{
                        print("Nowy event! <BR>");
                        $summary = $event[0]["SUMMARY"]; 
                        $time = $event[0]["DTSTART"];
                        $status = null;
                        if(isset($event[0]["CLASS"])){
                             $status = $event[0]["CLASS"];          
                        }
                        if($status == null){
                            $status = "PUBLIC";
                        }   
                        $status = $helper->set_access_status_numeric($status);
                        $time = $helper->convert_date_time($time);
                        $description ="";
                        if(isset($event[0]["DESCRIPTION"])){
                            $description = $event[0]["DESCRIPTION"];
                            $description= str_replace('\n', '<br>', $description);
                        }
                        $start = $event[0]["DTSTART"];
                        $end = $event[0]["DTEND"];
                        $date = $helper->convert_date($start);
                        $start = $helper->convert_date_time($start);
                        if($start == ''){
                            $start = null;
                            $duration = -1;
                        }else{
                            $end = $helper->convert_date_time($end);
                            $duration = $helper->duration($start, $end);
                        }
                        $now = date("Y-m-d H:i:s");
                        $user_id = $user->id;
                        $data = array('uid' => $uid, 'Title' => $summary, 'date' => $date,'time' => $start,
                        'duration' => $duration,'status' => 0, 'priority' => '1', 'permission' => $status, 'Employees' => $user_id, 'Description'=> $description);
                        $event = $rbo_meet->new_record($data);
                        $event->created_by = $user->get_val('login');
                        $event->created_on = $now;           
                        $event->save();   
                        print_r($event);
                        $data = array('uid' => $uid, 'table name' => 'crm_meeting', 'event_id' => $event->id, 'etag' => $result[$i]->getEtag(), 'href' => $result[$i]->getHref() );
                        $events_list = $rbo_cal_events->new_record($data);
                        $events_list->created_by = $user->get_val('login');
                        $events_list->created_on = $now;    
                        $events_list->save();
                    }
                }
            }
            $to_remove = array();
            $cal_events = $rbo_cal_events->get_records(array('!uid' => ''),array(),array());
            foreach($cal_events as $cal_event){
                $exist = true;
                foreach($list_of_uid as $uid){
                    if($cal_event['uid'] == $uid){
                        $exist = true;
                        break;
                    }
                    else{
                        $exist = false;
                    }
                }
                if($exist == false){
                    array_push($to_remove, array('id' => $cal_event->id, 'table' => $cal_event['table name'] ,'event_id' => $cal_event['event_id']));
                }
            }
            foreach($to_remove as $remove){
                $rbo_cal_events->delete_record($remove['id']);
                $remove_event = new RBO_RecordsetAccessor($remove['table']);
                $remove_event->delete_record($remove["event_id"]);
                print("Removing event ".$remove['id']." <BR>");
            }
        }
    }
    public static function update_changes_from_server(){
        $helper = new helper();
        $rbo = new RBO_RecordsetAccessor('contact');
        $rbo_cal_events = new RBO_RecordsetAccessor('calendar_sync');
        $users = $rbo->get_records(array('!calendar_url' => ''),array(),array());
        print("<BR>".count($users)." użytkownikow do przejrzenia<BR>");
        foreach ($users as $user){
            $_user = $rbo->get_record($user->id);
            $name = $_user->get_val('login',$nolink=True);
            $client = new SimpleCalDAVClient();
            $correct_url = false;
                try{  
                    $client->connect($_user['calendar_url'], $name,$_user["cal_password"]);
                    $arrayOfCalendars = $client->findCalendars(); 
                    if(count($arrayOfCalendars)>0){
                        $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($_user['calendar_url'])]);
                        $correct_url = true;
                    }else{
                    }
                }
                catch(Exception $e){}
                if($correct_url){
                    print("Przeglądam kalendarz dla użytkownika ".$name);
                    $start = $helper->get_date();
                    $result = $client->GetEvents($start);
                    print("<BR> EVENTÓW: ".count($result));
                    for( $i = 0; $i < count($result); $i++) {
                        $event = $rbo_cal_events->get_records(array('etag'=> $result[$i]->getEtag()),array(),array());
                        if(count($event) <= 0){
                            print("EVENT się nie zgadza<BR>");
                            // otrzymuje ktory event zmienic
                            $events = $rbo_cal_events->get_records(array('href'=> $result[$i]->getHref()),array(),array());
                            foreach($events as $event){
                                print("Zmiana eventu ".$event['event_id']." ".$event['etag']."<BR>");
                                //sprawdzam jaki to typ eventu 
                                $event_type = $event['table_name'];
                                $changes = new RBO_RecordsetAccessor($event_type);
                                $obj = new CalDAVObject($result[$i]->getHref(), $result[$i]->getData(), $result[$i]->getEtag());
                                $file = fopen("data.ics","w+");
                                fputs($file,$obj->getData(), strlen($obj->getData()));
                                fclose($file);
                                $ical = new ical('data.ics');
                                $_event = $ical->events();
                                $summary = $_event[0]["SUMMARY"]; 
                                $time = $_event[0]["DTSTART"];
                                $status = null;
                                if(isset($_event[0]["CLASS"])){
                                    $status = $_event[0]["CLASS"];          
                                }
                                if($status == null){
                                    $status = "PUBLIC";
                                }   
                                $status = $helper->set_access_status_numeric($status);
                                $time = $helper->convert_date_time($time);
                                $description ="";
                                if(isset($_event[0]["DESCRIPTION"])){
                                    $description = $_event[0]["DESCRIPTION"];
                                    $description= str_replace('\n', '<br>', $description);
                                }
                                $start = $_event[0]["DTSTART"];
                                if($event_type == 'crm_meeting'){
                                    $start = $_event[0]["DTSTART"];
                                    $end = $_event[0]["DTEND"];
                                    $_date = $helper->convert_date($start);
                                    $start = $helper->convert_date_time($start);
                                    if($start == ''){
                                        $start = null;
                                        $duration = -1;
                                    }else{
                                        $end = $helper->convert_date_time($end);
                                        $duration = $helper->duration($start, $end);
                                    }
                                    Utils_RecordBrowserCommon::update_record('calendar_sync', $event->id, 
                                        array('etag' => $result[$i]->getEtag(),'href'=> $result[$i]->getHref()),
                                        $full_update=false, 
                                        $date=null, 
                                        $dont_notify=false);    
                                    $succes = $changes->update_record($event['event_id'], array(
                                        'date' => $_date, 
                                        'time' => $start ,
                                        'title' => $summary,
                                        'duration' => $duration,
                                        'permission' => $status,
                                        'description'=>$desc,
                                    ));
                                    print("Aktualizacja zakończona". $success);
                                }
                                if($event_type == 'task'){
                                    $_date = $helper->convert_date($start);
                                    $start = $helper->convert_date_time($start);
                                    $without = 0;
                                    if($start == ''){
                                        $start = $_date." 12:00:00";
                                        $without = 1;
                                    }
                                    Utils_RecordBrowserCommon::update_record('calendar_sync', $event->id, 
                                    array('etag' => $result[$i]->getEtag(),'href'=> $result[$i]->getHref()),
                                    $full_update=false, 
                                    $date=null, 
                                    $dont_notify=false);    
                                    $succes = $changes->update_record($event['event_id'], array(
                                        'deadline' => $start,
                                        'title' => $summary,
                                        'timeless' => $without,
                                        'permission' => $status,
                                        'description'=>$desc,
                                    ));
                                print("Aktualizacja zakończona". $success);
                                }
                                if($event_type == 'phonecall'){
                                    $start = $_event[0]["DTSTART"];
                                    $start = $helper->convert_date_time($start);
                                    if($start == ''){
                                        $start =  $helper->convert_date($_event[0]["DTSTART"])." 12:00:00";
                                        $end = $start;
                                    }
                                    $end = $start;
                                    Utils_RecordBrowserCommon::update_record('calendar_sync', $event->id, 
                                    array('etag' => $result[$i]->getEtag(),'href'=> $result[$i]->getHref()),
                                    $full_update=false, 
                                    $date=null, 
                                    $dont_notify=false);    
                                $succes = $changes->update_record($event['event_id'], array(
                                    'subject' => $summary,
                                    'date_and_time' => $start,
                                    'permission' => $status,
                                    'description'=>$desc,
                                ));
                                print("Aktualizacja zakończona". $success);
                                }
                                //wprowadzam zmiany na epesi
                                //aktualizuje etag 
                            }
                        }
                    }
                }
            }
        
    }
    public static function event_to_server($record,$table){
        $helper = new helper();
        $rbo_event = new RBO_RecordsetAccessor($table);
        $rbo_cal_events = new RBO_RecordsetAccessor('calendar_sync');
        $users = $record['employees'];
        foreach ($users as $employer){
            $rbo = new RBO_RecordsetAccessor('contact');
            $client = new SimpleCalDAVClient();
            $user = $rbo->get_record($employer);
            if($user->get_val('calendar_url') != ''){ 
                $correct_url = false;
                try{  
                    $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                    $arrayOfCalendars = $client->findCalendars(); 
                    if(count($arrayOfCalendars)>0){
                        $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                        $correct_url = true;
                    }else{
                    }
                }
                catch(Exception $e){}
                if($correct_url){
                    if($table == 'crm_meeting'){
                        $desc = $record['description'];
                        $desc = str_replace('<br>', '\n', $desc);
                        $sumary = $record["title"];
                        $st = $record['time'];
                        $end = null;
                        if($st == null){
                            $st = $record['date'];
                            $end = $record['date'];
                        }
                        if($end == null){
                            $end = $record['duration'];  
                            $time = $helper->calc_duration($st, $end);
                            $end = $helper->toDateTimeCAL($time);
                        }
                        $new_uid = $table."".$record['id'];
                        $status = $record['permission'];
                        $status = $helper->set_access_status($status);
                        $create_new = $client->create(helper::export($sumary,$desc, $st, $end,$new_uid,$status));

                        //zapisz do dodatkowej tabeli
                        $data = array('uid' => $new_uid, 'table name' => 'crm_meeting', 'event_id' => $record['id'], 
                                      'etag' => $create_new->getEtag() , 'href' => $create_new->getHref() );
                        $events_list = $rbo_cal_events->new_record($data);   
                        $events_list->created_by = $user->get_val('login');
                        $events_list->created_on = $now;    
                        $events_list->save();

                    }
                    if($table =='task'){
                        $sumary = $record["title"]; 
                        $st = $record['deadline']; 
                        $desc = $record['description'];
                        $end = $st;
                        $new_uid = $table.$record['id'];
                        if($record['timeless'] == '1'){
                            $time = $record['deadline'];
                            $time = $helper->toDateCAL($time);
                        }else{
                           $time = $record['deadline'];
                           
                        }
                        $created = $helper->toDateTimeCAL($created)."Z";
                        $status = $record['permission'];
                        $status = $helper->set_access_status($status);
                        $create_new = $client->create(helper::export($sumary,$desc, $time, $time,$new_uid,$status));

                        //zapisz do dodatkowej tabeli
                        $data = array('uid' => $new_uid, 'table name' => 'task', 'event_id' => $record['id'],
                         'etag' => $create_new->getEtag(), 'href' => $create_new->getHref()  );
                        $events_list = $rbo_cal_events->new_record($data); 
                        $events_list->created_by = $user->get_val('login');
                        $events_list->created_on = $now;      
                        $events_list->save();
                    }
                    if($table == 'phonecall'){
                        $sumary = $record['subject'];
                        $st = $record['date_and_time'];
                        $desc = $record['description'];
                        $end = $st;
                        $phonenumber = $record["other_phone_number"];
                        $who = $record["other_customer_name"];
                        if($phonenumber == ""){ 
                            $klient = $record["customer"];
                            $klient = explode("/", $klient);
                            $number = $klient[1];
                            $number = intval($number);
                            $klient = $klient[0];
                            if($klient == 'contact'){
                                $rb = new RBO_RecordsetAccessor('contact');
                                $x = $rb->get_record($number);
                                $who = $x->get_val('last_name',$nolink = True)." ".$x->get_val('first_name',$nolink = True);
                                $phonenumber = $x->get_val('work_phone');
                            if ($phonenumber == ""){ $phonenumber = $x->get_val("mobile_phone");}
                            }else{
                                $rb = new RBO_RecordsetAccessor('company');
                                $x = $rb->get_record($number);
                                $who = $x->get_val('company_name',$nolink = True);
                                $phonenumber =  $x->get_val('phone'); 
                            }
                        }
                        $new_uid = "phonecall".$record['id'];
                        $sumary = $sumary." TEL: ".$phonenumber;
                        $sumary = $sumary." - ".$who;
                        $st = $helper->toDateTimeCAL($st);
                        $end = $helper->toDateTimeCAL($end);
                        $created = $helper->toDateTimeCAL($created)."Z";
                        $status = $record['permission'];
                        $status = $helper->set_access_status($status);
                        $create_new = $client->create(helper::export($sumary,$desc, $record['date_and_time'], $record['date_and_time'],$new_uid,$status));

                        //zapisz do dodatkowej tabeli
                        $data = array('uid' => $new_uid, 'table name' => 'phonecall', 'event_id' => $record['id'], 
                                      'etag' => $create_new->getEtag() , 'href' => $create_new->getHref() );
                        $events_list = $rbo_cal_events->new_record($data);   
                        $events_list->created_by = $user->get_val('login');
                        $events_list->created_on = $now;    
                        $events_list->save();
                    }

                }
            }
        }   
    }

    public static function delete($table,$record){
        $helper = new helper();
        $rbo_user = new RBO_RecordsetAccessor("contact");
        $rbo_cal_events = new RBO_RecordsetAccessor('calendar_sync');
        $employers = $record['employees'];
        foreach($employers as $employer){
            $client = new SimpleCalDAVClient();
            $user = $rbo_user->get_record($employer);
            if($user->get_val('calendar_url') != ''){ 
                $correct_url = false;
                try{  
                    $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                    $arrayOfCalendars = $client->findCalendars(); 
                    if(count($arrayOfCalendars)>0){
                        $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                        $correct_url = true;
                    }else{}
                }
                catch(Exception $e){}
                if($correct_url){
                    if($table == 'crm_meeting'){
                        $events = $rbo_cal_events->get_records(array('event_id' => $record["id"], 'table_name' => $table),array(),array());
                        foreach($events as $event){
                            $client->delete($event['href'],$event['etag']);
                        }
                    }
                    if($table == 'task'){
                        $events = $rbo_cal_events->get_records(array('event_id' => $record['id'], 'table_name' => $table),array(),array());
                        foreach($events as $event){
                            $client->delete($event['href'],$event['etag']);

                        }
                    }
                    if($table == 'phonecall'){
                        $events = $rbo_cal_events->get_records(array('event_id' => $record['id'], 'table_name' => $table),array(),array());
                        foreach($events as $event){
                            $client->delete($event['href'],$event['etag']);
                        }
                    } 
                }    
            }
        }
    }
    public static function edit($table,$record){
        $helper = new helper();
        $rbo_user = new RBO_RecordsetAccessor("contact");
        $rbo_cal_events = new RBO_RecordsetAccessor('calendar_sync');
        $employers = $record['employees'];
        foreach($employers as $employer){
            $client = new SimpleCalDAVClient();
            $user = $rbo_user->get_record($employer);
            if($user->get_val('calendar_url') != ''){ 
                $correct_url = false;
                try{  
                    $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                    $arrayOfCalendars = $client->findCalendars(); 
                    if(count($arrayOfCalendars)>0){
                        $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                        $correct_url = true;
                    }else{}
                }
                catch(Exception $e){}
                if($correct_url){
                    if($table == 'crm_meeting'){
                        $events = $rbo_cal_events->get_records(array('event_id' => $record["id"], 'table_name' => $table),array(),array());
                        foreach($events as $event){
                            $desc = $record['description'];
                            $desc = str_replace('<br>', '\n', $desc);
                            $sumary = $record["title"];
                            $st = $record['time'];
                            $end = null;
                            if($st == null){
                                $st = $record['date'];
                                $end = $record['date'];
                            }
                            if($end == null){
                                $end = $record['duration'];  
                                $time = $helper->calc_duration($st, $end);
                                $end = $helper->toDateTimeCAL($time);
                            }
                            $uid = $event['uid'];
                            $status = $record['permission'];
                            $status = $helper->set_access_status($status);
                            $new_data = helper::export($sumary,$desc, $st, $end,$new_uid,$status);
                            $obj = $client->change($event['href'],$new_data,$event['etag']);
                            Utils_RecordBrowserCommon::update_record('calendar_sync', $event->id, 
                                                                     array('etag' => $obj->getEtag(),'href'=> $obj->getHref()),
                                                                     $full_update=false, 
                                                                     $date=null, 
                                                                     $dont_notify=false); 
                        }
                    }
                    if($table == 'task'){
                        $events = $rbo_cal_events->get_records(array('event_id' => $record['id'], 'table_name' => $table),array(),array());
                        foreach($events as $event){
                            $sumary = $record["title"]; 
                            $st = $record['deadline']; 
                            $desc = $record['description'];
                            $end = $st;
                            $uid = $event['uid'];
                            if($record['timeless'] == '1'){
                                $time = $record['deadline'];
                                $time = $helper->toDateCAL($time);
                            }else{
                                $time = $record['deadline'];
                            }
                            $created = $helper->toDateTimeCAL($created)."Z";
                            $status = $record['permission'];
                            $status = $helper->set_access_status($status);
                            $new_data = helper::export($sumary,$desc, $time, $time,$uid,$status);
                            $obj = $client->change($event['href'],$new_data,$event['etag']);
                            Utils_RecordBrowserCommon::update_record('calendar_sync', $event->id, 
                                                                     array('etag' => $obj->getEtag(),'href'=> $obj->getHref()),
                                                                     $full_update=false, 
                                                                     $date=null, 
                                                                     $dont_notify=false); 
                        }
                    }
                    if($table == 'phonecall'){
                        $events = $rbo_cal_events->get_records(array('event_id' => $record['id'], 'table_name' => $table),array(),array());
                        foreach($events as $event){
                            $sumary = $record['subject'];
                            $st = $record['date_and_time'];
                            $desc = $record['description'];
                            $end = $st;
                            $phonenumber = $record["other_phone_number"];
                            $who = $record["other_customer_name"];
                            if($phonenumber == ""){ 
                                $klient = $record["customer"];
                                $klient = explode("/", $klient);
                                $number = $klient[1];
                                $number = intval($number);
                                $klient = $klient[0];
                                if($klient == 'contact'){
                                    $rb = new RBO_RecordsetAccessor('contact');
                                    $x = $rb->get_record($number);
                                    $who = $x->get_val('last_name',$nolink = True)." ".$x->get_val('first_name',$nolink = True);
                                    $phonenumber = $x->get_val('work_phone');
                                if ($phonenumber == ""){ $phonenumber = $x->get_val("mobile_phone");}
                                }else{
                                    $rb = new RBO_RecordsetAccessor('company');
                                    $x = $rb->get_record($number);
                                    $who = $x->get_val('company_name',$nolink = True);
                                    $phonenumber =  $x->get_val('phone'); 
                                }
                            }
                            $uid = $event['uid'];
                            $sumary = $sumary." TEL: ".$phonenumber;
                            $sumary = $sumary." - ".$who;
                            $st = $helper->toDateTimeCAL($st);
                            $end = $helper->toDateTimeCAL($end);
                            $status = $record['permission'];
                            $status = $helper->set_access_status($status);
                            $new_data = helper::export($sumary,$desc, $st,
                            $end,$uid,$status);
                            $obj = $client->change($event['href'],$new_data,$event['etag']);
                            Utils_RecordBrowserCommon::update_record('calendar_sync', $event->id, 
                                                                      array('etag' => $obj->getEtag(),'href'=> $obj->getHref()),
                                                                      $full_update=false, 
                                                                      $date=null, 
                                                                      $dont_notify=false); 
                        }
                    } 
                }
            }
        }
    }


    public static function on_action_meeting($record, $mode){
        if ($mode === 'edit'){
            iCalSyncCommon::edit('crm_meeting',$record);
        }
        if($mode === "delete"){
            iCalSyncCommon::delete("crm_meeting",$record);
        }
        if($mode === "added"){
            iCalSyncCommon::event_to_server($record, 'crm_meeting');
        }
    }
    public static function on_action_phonecall($record, $mode){
        if ($mode === 'edit'){
            iCalSyncCommon::edit('phonecall',$record);
        }
        if($mode === "delete"){
            iCalSyncCommon::delete("phonecall",$record);
        }
        if($mode === "added"){
            iCalSyncCommon::event_to_server($record,'phonecall');
        }
    }
    public static function on_action_task($record, $mode){
        if ($mode === 'edit'){
            iCalSyncCommon::edit('task',$record);
        }
        if($mode === "delete"){
            iCalSyncCommon::delete("task",$record);
        }
        if($mode === "added"){
            iCalSyncCommon::event_to_server($record,'task');
        }
    }
}