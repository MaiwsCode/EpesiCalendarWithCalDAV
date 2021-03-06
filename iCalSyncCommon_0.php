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


    public static function cron() {
        return array(
           'update' => 2, 
           'update_changes' => 3,
        );
    }
     // SERVER -> EPESI
   public static function update() {
        $helper = new helper();
        $rbo = new RBO_RecordsetAccessor('contact');
        $users_urls = $rbo->get_records(array('!calendar_url' => ''));
        foreach($users_urls as $user ){      
            $Connetion_data = "Connetion_data";
            $client = new CalDAVClient($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
            $start = $helper->get_date();
            $result = $client->GetEvents($start);
            for( $i = 0; $i < count($result); $i++) {
                $exist = false;
                $obj = new CalDAVObject($result[$i]["href"], $result[$i]["data"], $result[$i]["etag"]);
                $file = fopen("data.ics","w+");
                fputs($file,$obj->getData(), strlen($obj->getData()));
                fclose($file);
                $ical = new ical('data.ics');
                $event = $ical->events();
                $summary = $event[0]["SUMMARY"]; 
                $uid = $event[0]["UID"];
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
                $rbo_meet =  new RBO_RecordsetAccessor('crm_meeting');
                $rbo_phone =  new RBO_RecordsetAccessor('phonecall');
                $rbo_task =  new RBO_RecordsetAccessor('task');
                $catch = $rbo_meet->get_records(array('uid' => $uid));
                $catch2 = $rbo_phone->get_records(array('uid' => $uid));
                $catch3 = $rbo_task->get_records(array('uid' => $uid));
                if($catch != null or $catch2 != null or $catch3 != null){ $exist = true;}
                if($exist == false){
                    if(isset($event[0]["DESCRIPTION"])){
                        $description = $event[0]["DESCRIPTION"];
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
                    $id = $user->id;
                    $data = array('uid' => $uid, 'Title' => $summary, 'date' => $date,'time' => $start,
                    'duration' => $duration,'status' => 0, 'priority' => '1', 'permission' => $status, 'Employees' => $id);
                    $event = $rbo_meet->new_record($data);
                    $event->created_by = $user->get_val('login');
                    $event->created_on = $now;           
                    $event->save();       
                }
            } 
        }
    }
    // EPESI EVENTS --> SERVER
    public static function push_events(){
        $helper = new helper();
        $date = date_create(date('Y-m-d'));
        date_sub($date, date_interval_create_from_date_string('14 days'));
        $date =  date_format($date, 'Y-m-d');
        $rbo = new RBO_RecordsetAccessor('contact');
        $client = new SimpleCalDAVClient();
        $rbo_meet =  new RBO_RecordsetAccessor('crm_meeting');
        $get_days = $rbo_meet->get_records(array('>=date' => $date,'uid' => ''));
        //for meetings
        foreach($get_days as $day){   
        $created = $day->created_on;
        $extra_data = $day->to_array();
        $desc = $day->get_val('description');
     //   $desc = str_replace("<br>", "  ", $desc);
        $sumary = $extra_data["title"];
        $st = $day['time'];
        $end = null;
        if($st == null){
            $st = $day['date'];
            $end = $day['date'];
        }
        if($end == null){
            $end = $extra_data['duration'];  
            $time = $helper->calc_duration($st, $end);
            $end = $helper->toDateTimeCAL($time);
        }
        $new_uid = "EPESIexportMeetings".$day->id;
        $new_uid = str_replace(" ", "", $new_uid);
        $created = $helper->toDateTimeCAL($created)."Z";
        $employes = $extra_data["employees"];
        $status = $extra_data['permission'];
        $status = $helper->set_access_status($status);
        foreach ($employes as $employer ){
            $rbo = new RBO_RecordsetAccessor('contact');
            $user = $rbo->get_record($employer);
            if($user->get_val('calendar_url') != ''){ 
                $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                $arrayOfCalendars = $client->findCalendars(); 
                $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                $create_new = $client->create(helper::export($sumary,$desc, $st, $end,$new_uid,$status));
            }
        }
        Utils_RecordBrowserCommon::update_record('crm_meeting', $day->id, array('uid' => $new_uid),$full_update=false, $date=null, $dont_notify=false); 
        }
        $datetime = $date." 00:00:00";
        $rboTask =  new RBO_RecordsetAccessor('task');
        $get_days2 = $rboTask->get_records(array('>=deadline' => $datetime,'uid' => ''));
        foreach($get_days2 as $day){   
        $created = $day->created_on;
        $data_extra = $day->to_array();
        $sumary = $data_extra["title"]; 
        $st = $day['deadline']; 
        $desc = $day->get_val('description');
      //  $desc = str_replace("<br>", "  ", $desc);
        $end = $st;
        $new_uid = "EPESIexportTasks".$day->id;
        if($day['timeless'] == '1'){
            $time = $day['deadline'];
            $time = $helper->toDateCAL($time);
        }else{
           $time = $day['deadline'];
           
        }
        $created = $helper->toDateTimeCAL($created)."Z";
        $status = $data_extra['permission'];
        $status = $helper->set_access_status($status);
        $employes = $data_extra["employees"];
        foreach ($employes as $employer){
            $user = $rbo->get_record($employer);
            if($user->get_val('calendar_url') != ''){ 
                $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                $arrayOfCalendars = $client->findCalendars(); 
                $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                $create_new = $client->create(helper::export($sumary,$desc, $time, $time,$new_uid,$status));
            }
        }
       Utils_RecordBrowserCommon::update_record('task', $day->id, array('uid' => $new_uid),$full_update=false, $date=null, $dont_notify=false);
    }
        //for phonecalls
        $rboPhone =  new RBO_RecordsetAccessor('phonecall');
        $get_days3 = $rboPhone->get_records(array('>=date_and_time' => $datetime,'uid' => ''));
        foreach($get_days3 as $day){
        $data_extra = $day->to_array();
        $created =  $day->created_on;// $query[$l]["created_on"];
        $sumary = $data_extra['subject'];// $query[$l]["f_subject"];
        $st = $day['date_and_time'];// $query[$l]["f_date_and_time"];
        $desc = $day->get_val('description');
       // $desc = str_replace("<br>", "  ", $desc);
        $end = $st;
        $phonenumber = $data_extra["other_phone_number"];
        if($phonenumber == ""){ 
            $klient = $data_extra["customer"];
            $klient = explode("/", $klient);
            $number = $klient[1];
            $number = intval($number);
            $klient = $klient[0];
            if($klient == 'contact'){
                $rb = new RBO_RecordsetAccessor('contact');
                $x = $rb->get_record($number);
                $phonenumber = $x->get_val('work_phone');
            if ($phonenumber == ""){ $phonenumber = $x->get_val("mobile_phone");}
            }else{
                $rb = new RBO_RecordsetAccessor('company');
                $x = $rb->get_record($number);
                $phonenumber =  $x->get_val('phone'); 
            }
        }
        $new_uid = "EPESIexportPhones".$day->id;
        $new_uid = str_replace(" ", "", $new_uid);
        $sumary = $sumary." TEL: ".$phonenumber;
        $st = $helper->toDateTimeCAL($st);
        $end = $helper->toDateTimeCAL($end);
        $created = $helper->toDateTimeCAL($created)."Z";
        $employes = $data_extra["employees"];
        $status = $data_extra['permission'];
        $status = $helper->set_access_status($status);
        foreach ($employes as $employer){
            $user = $rbo->get_record($employer);
            if($user->get_val('calendar_url') != ''){ 
                $client = new SimpleCalDAVClient();
                $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                $arrayOfCalendars = $client->findCalendars(); 
                $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                $create_new = $client->create(helper::export($sumary,$desc, $day['date_and_time'], $day['date_and_time'],$new_uid,$status));
            }
        }
        Utils_RecordBrowserCommon::update_record('phonecall', $day->id, array('uid' => $new_uid),$full_update=false, $date=null, $dont_notify=false);
    }
  }

    public static function update_changes(){
        $helper = new helper();
        $rbo = new RBO_RecordsetAccessor('contact');
        $users_urls = $rbo->get_records(array('!calendar_url' => ''));
        foreach($users_urls as $user ){      
            $Connetion_data = "Connetion_data";
            $client = new CalDAVClient($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
            $start = $helper->get_date();
            $result = $client->GetEvents($start);
            for( $i = 0; $i < count($result); $i++) {
                $exist = false;
                $obj = new CalDAVObject($result[$i]["href"], $result[$i]["data"], $result[$i]["etag"]);
                $file = fopen("data.ics","w+");
                fputs($file,$obj->getData(), strlen($obj->getData()));
                fclose($file);
                $ical = new ical('data.ics');
                $event = $ical->events();
                $summary = $event[0]["SUMMARY"]; 
                $uid = $event[0]["UID"];
                $start = $event[0]["DTSTART"];
                $end = $event[0]["DTEND"];
                $status = null;
                if(isset($event[0]["CLASS"])){
                    $status = $event[0]["CLASS"];          
                }
                if($status == null){
                    $status = "PUBLIC";
                }
                $status = $helper->set_access_status_numeric($status);
                $time = $helper->convert_date_time($time);
                $rbo_meet =  new RBO_RecordsetAccessor('crm_meeting');
                $rbo_phone =  new RBO_RecordsetAccessor('phonecall');
                $rbo_task =  new RBO_RecordsetAccessor('task');
                $catch = $rbo_meet->get_records(array('uid' => $uid));
                $catch2 = $rbo_phone->get_records(array('uid' => $uid));
                $catch3 = $rbo_task->get_records(array('uid' => $uid));
                
                //Metings
                if($catch != null){
                  //  print("UPDATING - ".$uid . "<BR>");
                    $get_event = $rbo_meet->get_records(array("uid" => $uid));
                    foreach($get_event as $ev){
                       $get_event = $ev;
                    }
                    $id = $get_event['id'];
                    $change = false;
                    $date = $helper->convert_date($start);
                    $start = $helper->convert_date_time($start);
                    if($start == ''){
                        $start = null;
                        $duration = -1;
                    }else{
                        $end = $helper->convert_date_time($end);
                        $duration = $helper->duration($start, $end);
                    } 
                    if($summary != $get_event['title']){$change = true; }
                    if($start != $get_event['time']){$change = true; }
                    if($duration != $get_event['duration']){$change = true;}
                    if(intval($status) !=intval($get_event['permission'])){$change = true; }
                    if($change == true){
                        Utils_RecordBrowserCommon::update_record('crm_meeting', $id, array('uid' => $uid,
                    'title' => $summary,'date' => $date,'time' => $start,
                    'duration' => $duration,'status' => 0, 'priority' => '1',
                     'permission' => $status),$full_update=false, $date=null, $dont_notify=false);
                    }      
                }

                // PHOnes
                if($catch2 != null){
                   // print("UPDATING - ".$uid . "<BR>");
                    $get_event = $rbo_phone->get_records(array("uid" => $uid));
                    foreach($get_event as $ev){
                       $get_event = $ev;
                    }
                    $id = $get_event['id'];
                    $change = false;
                    $start = $helper->convert_date_time($start);
                    if($start == ''){
                        $start =  $helper->convert_date($event[0]["DTSTART"])." 12:00:00";
                        $end = $start;
                    }
                    $end = $start;
                    $phonenumber = $get_event["other_phone_number"];
                    if($phonenumber == ""){ 
                        $klient = $get_event["customer"];
                        $klient = explode("/", $klient);
                        $number = $klient[1];
                        $number = intval($number);
                        $klient = $klient[0];
                        if($klient == 'contact'){
                            $rb = new RBO_RecordsetAccessor('contact');
                            $x = $rb->get_record($number);
                            $phonenumber = $x->get_val('work_phone');
                        if ($phonenumber == ""){ $phonenumber = $x->get_val("mobile_phone");}
                    }else{
                        $rb = new RBO_RecordsetAccessor('company');
                        $x = $rb->get_record($number);
                        $phonenumber =  $x->get_val('phone'); 
                        }
                    }

                    $sum = $get_event['subject']." TEL: ".$phonenumber;
                    //if($summary != $sum){$change = true; print("CHANGING SUMMARY <BR>"); }
                if($start != $get_event['time']){$change = true;/* print("CHANGING TIME <BR>");*/ }
                if(intval($status) != intval($get_event['permission'])){$change = true;/* print("CHANGING PERMISSION <BR>");*/ }
                    if($change == true){
                        if($summary != $sum ){
                            Utils_RecordBrowserCommon::update_record('phonecall', $id, array('uid' => $uid,
                            'subject' => $summary,'date_and_time' => $start,
                            'status' => 0, 'priority' => '1',
                             'permission' => $status
                        ),$full_update=false, $date=null, $dont_notify=false);
                }
                else{
                    Utils_RecordBrowserCommon::update_record('phonecall', $id, array('uid' => $uid,
                       'date_and_time' => $start,
                        'status' => 0, 'priority' => '1',
                         'permission' => $status
                    ),$full_update=false, $date=null, $dont_notify=false);
                }
            }     
        }
                if($catch3 != null){
                    print("UPDATING - ".$uid . "<BR>");
                    $get_event = $rbo_task->get_records(array("uid" => $uid));
                    foreach($get_event as $ev){
                       $get_event = $ev;
                    }
                    $id = $get_event['id'];
                    $change = false;
                    $date = $helper->convert_date($start);
                    $start = $helper->convert_date_time($start);
                    $without = 1;
                    if($start == ''){
                        $start = $date." 12:00:00";
                        $without = 0;
                    }
                    if($summary != $get_event['title']){$change = true; print(" CHANGING TITLE <BR>"); }
                    if($start != $get_event['deadline']){$change = true; print(" CHANGING TIME <BR>");}
                    if(intval($status) !=intval($get_event['permission'])){$change = true; print(" CHANGING STATUS <BR>");}
                    if($change == true){
                    if($without == 0){
                        print("UPDATING <br>");
                    Utils_RecordBrowserCommon::update_record('task', $id, array('uid' => $uid,
                    'title' => $summary,'deadline' => $start,'timeless' => '1',
                    'status' => 0, 'priority' => '1',
                    'permission' => $status),$full_update=false, $date=null, $dont_notify=false);
                      }else{print("UPDATING <br>");
                            Utils_RecordBrowserCommon::update_record('task', $id, array('uid' => $uid,
                            'title' => $summary,'deadline' => $start,'timeless' => '0',
                            'status' => 0, 'priority' => '1',
                            'permission' => $status),$full_update=false, $date=null, $dont_notify=false);     
                        }
                    }             
                }
            }
        }
    }
   /* public static function menu() {
	    return array('CAL'=>array());
    }*/

    public static function delete($table,$record){
       // $db = new RBO_RecordsetAccessor($table);
        $client = new SimpleCalDAVClient();
      //  $record = $db->get_record($id);
        $helper = new helper();
        $user = new RBO_RecordsetAccessor("contact");
        $start = null;
        $end = null;
        $uid = $record["uid"];;
        switch($table){
            case "phonecall":
                $start = $record['date_and_time'];
                $start = strtotime($start);
                $start = date('Ymd',$start);
                $end = $start."T235959Z";
                $start = $start."T000000Z";
            break;
            case "task":
                $start = $record['deadline'];
                $start = strtotime($start);
                $start = date('Ymd',$start);
                $end = $start."T235959Z";
                $start = $start."T000000Z";
            break;
            case "crm_meeting":
                $start = $record['date'];
                $start = strtotime($start);
                $start = date('Ymd',$start);
                $end = $start."T235959Z";
                $start = $start."T000000Z";
            break;
        }
        $employes = $record["employees"];
        foreach($employes as $employer){
            $user = $user->get_record($employer);
            if(isset($user["calendar_url"])){
                $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                $arrayOfCalendars = $client->findCalendars(); 
                $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                $events = $client->getEvents($start,$end);
                
                foreach($events as $event_){
                    $obj = new CalDAVObject($event_->getHref(), $event_->getData(), $event_->getEtag());
                    $file = fopen("data.ics","w+");
                    fputs($file,$obj->getData(), strlen($obj->getData()));
                    fclose($file);
                    $ical = new ical('data.ics');
                    $event = $ical->events();
                    if($uid == $event[0]["UID"]){
                        $client->delete($event_->getHref(),$event_->getEtag());
                      //  Base_StatusBarCommon::message("DELETED FROM".$table."=> id:".$id."<Br> FOR UID $uid and ".$event[0]["UID"]);
                    }
                }
            }
        }   
    }
    public static function edit($table,$record){
      //  Base_StatusBarCommon::message($table." ".$id);
       // $db = new RBO_RecordsetAccessor($table);
        $client = new SimpleCalDAVClient();
       // $record = $db->get_record($id);
        $helper = new helper();
        $user = new RBO_RecordsetAccessor("contact");
        $start = null;
        $end = null;
        $uid = $record["uid"];;
        $title = "";
        $cal_start_time = "";
        $cal_end_time = "";
        switch($table){
            case "phonecall":
                $start = $record['date_and_time'];
                $cal_start_time = $record['date_and_time'];
                $cal_end_time = $record['date_and_time'];
                $start = strtotime($start);
                $end = $start + (60*60*24*14);
                $start = $start-(60*60*24*14);
                $start = date('Ymd',$start);
                $end = date("Ymd",$end);
                $end = $end."T235959Z";
                $start = $start."T000000Z";
                $phonenumber = $record["other_phone_number"];
                if($phonenumber == ""){ 
                    $klient = $record["customer"];
                    $klient = explode("/", $klient);
                    $number = $klient[1];
                    $number = intval($number);
                    $klient = $klient[0];
                if($klient == 'contact'){
                    $rb = new RBO_RecordsetAccessor('contact');
                    $x = $rb->get_record($number);
                    $phonenumber = $x->get_val('work_phone');
                if ($phonenumber == ""){ $phonenumber = $x->get_val("mobile_phone");}
                }else{
                    $rb = new RBO_RecordsetAccessor('company');
                    $x = $rb->get_record($number);
                    $phonenumber =  $x->get_val('phone'); 
                    }
                }
                $title = $record["subject"]." TEL: ".$phonenumber;
            break;
            case "task":
                $start = $record['deadline'];
                $start = strtotime($start);
                $e = $start;
                $s = $start - (60*60*24*14);
                $e = $e + (60*60*24*14);
                $start = date('Ymd',$s);
                $end = date('Ymd',$e);
                $end = $end."T235959Z";
                $start = $start."T000000Z";
                $title = $record["title"];
                if($record['timeless'] == '1'){
                    $time = $record['deadline'];
                    $time = $helper->toDateCAL($time);
                    $cal_start_time =$time;
                    $cal_end_time = $cal_start_time;
                }else{
                   $cal_start_time = ($record['deadline']);
                   $cal_end_time = $cal_start_time;
                }
            break;
            case "crm_meeting":
                $start = $record['date'];
                $start = strtotime($start);
                $end = $start + (60*60*24*14);
                $start = $start - (60*60*24*14);
                $start = date('Ymd',$start);
                $end = date("Ymd",$end);
                $end = $end."T235959Z";
                $start = $start."T000000Z";
                $title = $record["title"];
                $cal_start_time = $record['time'];
                $cal_end_time = null;
                if($cal_start_time == null){
                    $cal_start_time = $record['date'];
                    $cal_end_time =$record['date'];

                }
                if($cal_end_time == null){
                    $cal_end_time = $record['duration'];  
                    $time = $helper->calc_duration($cal_start_time, $cal_end_time);
                    $cal_end_time = $helper->toDateTimeCAL($time);
                }
            break;
        }
        $employes = $record["employees"];
        foreach($employes as $employer){
            $user = $user->get_record($employer);
            if($user->get_val('calendar_url') != ""){
                $client->connect($user->get_val('calendar_url'), $user->get_val('login',$nolink=TRUE),$user->get_val("cal_password",$nolink=TRUE));
                $arrayOfCalendars = $client->findCalendars(); 
                $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
                $events = $client->getEvents($start,$end);
                foreach($events as $event_){
                    $obj = new CalDAVObject($event_->getHref(), $event_->getData(), $event_->getEtag());
                    $file = fopen("data.ics","w+");
                    fputs($file,$obj->getData(), strlen($obj->getData()));
                    fclose($file);
                    $ical = new ical('data.ics');
                    $event = $ical->events();
                    if($uid == $event[0]["UID"]){
                        //function change ( $href, $new_data, $etag )
                       // print($uid." ".$event[0]["UID"]."<BR>");
                      /*  $change = false;
                        if($title != $event[0]["SUMMARY"]){
                            $change = true;
                         //   print($title." ".$event[0]["SUMMARY"]."<BR>");
                        }
                        if($helper->normalTimeToCalDav($cal_start_time) != $event[0]["DTSTART"]){
                          //  print($helper->normalTimeToCalDav($cal_start_time). " : ".$event[0]["DTSTART"]."<BR>");
                            $change = true;
                        }
                        if($helper->normalTimeToCalDav($cal_end_time) != $event[0]["DTEND"]){
                            $change = true;
                           // print($helper->normalTimeToCalDav($cal_end_time). " : ".$event[0]["DTEND"]."<BR>");
                        }
                        if(intval($record["permission"]) != intval($helper->set_access_status_numeric($event[0]["CLASS"]))){
                            $change = true;
                        }   
                        if($change){*/
                            $desc = "";
                            $status = $helper->set_access_status($record['permission']);
                            $new_data = helper::export($title,$desc, $cal_start_time, $cal_end_time,$uid,$status);
                            $obj = $client->change($obj->getHref(),$new_data,$obj->getEtag());
                       // }
                    }
                }
            }
        }   
    }
    public static function get_calendar_table_from_int($table_int){
        switch($table_int){
            case 1:
                return "phonecall";
            break;

            case 2:
                return "task";
            break;

            case 3:
                 return "crm_meeting";  
            break;
            
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
            iCalSyncCommon::push_events();
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
            iCalSyncCommon::push_events();
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
            iCalSyncCommon::push_events();
        }
    }
}