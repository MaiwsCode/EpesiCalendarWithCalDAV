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

class Connetion_data  {
    public static function login(){
        $login = "your_login";
        return $login;
    }
    public static function password(){
        $password = "your_password";
        return $password;  
    }
    
}
class iCalSyncCommon extends ModuleCommon {


    public static function cron() {
        return array(
           'update' => 2, 
           'push_events' => 4
        );
    }
     // SERVER  -> EPESI
    public static function update() {
          $helper = new helper();
          $rbo = new RBO_RecordsetAccessor('contact');
          $users_urls = $rbo->get_records(array('!calendar_url' => ''));
          foreach($users_urls as $user ){      
          $Connetion_data = "Connetion_data";
          $client = new CalDAVClient($user->get_val('calendar_url'), Connetion_data::login(),Connetion_data::password());
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
                $time = $helper->convert_date_time($time);
                $rbo_meet =  new RBO_RecordsetAccessor('crm_meeting');
                $rbo_phone =  new RBO_RecordsetAccessor('phonecall');
                $rbo_task =  new RBO_RecordsetAccessor('task');
                $catch = $rbo_meet->get_records(array('uid' => $uid));
                $catch2 = $rbo_phone->get_records(array('uid' => $uid));
                $catch3 = $rbo_task->get_records(array('uid' => $uid));
                if($catch != null or $catch2 != null or $catch3 != null){ $exist = true;}
                if($exist == false){
                $description = "[M]";
                if(isset($event[0]["DESCRIPTION"])){
                $description = $event[0]["DESCRIPTION"];}
                $description = $description.=" Dodano z kalendarza Google";
                $start = $event[0]["DTSTART"];
                $date = $helper->convert_date($start);
                $start = $helper->convert_date_time($start);
                $now = date("Y-m-d H:i:s");
                $id = $user->id;
                $data = array('uid' => $uid, 'Title' => $summary, 'date' => $date,'time' => $start,'duration' => 3600,'status' => '0', 'priority' => '1', 'permission' => 0, 'Employees' => $id);
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
   $event = 'BEGIN:VCALENDAR
PRODID:-//SomeExampleStuff//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:{{CR}}
LAST-MODIFIED:{{MOD}}
DTSTAMP:{{STMP}}
UID:{{UNICAL}}
SUMMARY:{{SUMAR}}
DTSTART;TZID=Europe/Berlin:{{ST}}
DTEND;TZID=Europe/Berlin:{{END}}
DESCRIPTION:{{DESC}}
END:VEVENT
END:VCALENDAR';
   //if f_related != null 
   $created = $day->created_on;
   $extra_data = $day->to_array();
   $desc = $extra_data["description"];
   $sumary = $extra_data["title"];
   $st = $day->get_val('date')." ".$day->get_val('time').":00";
   $end = $st;   
   $new_uid = "EPESIexportMeetings".$day->id;
   $new_uid = str_replace(" ", "", $new_uid);
   $st = $helper->toTimeCAL($st);
   $end = $helper->toTimeCAL($end);
   $created = $helper->toTimeCAL($created)."Z";
   $employes = $extra_data["employees"];
   foreach ($employes as $employer ){
        $rbo = new RBO_RecordsetAccessor('contact');
        $user = $rbo->get_record($employer);
        if($user->get_val('calendar_url') != ''){ 
            $client->connect($user->get_val('calendar_url'), Connetion_data::login(),Connetion_data::password());
            $arrayOfCalendars = $client->findCalendars(); 
            $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
            $event = $helper->change_data($event,'{{CR}}',$created);
            $event = $helper->change_data($event,'{{MOD}}',$created);
            $event = $helper->change_data($event,'{{STMP}}',$created);
            $event = $helper->change_data($event,'{{UNICAL}}',$new_uid);
            $event = $helper->change_data($event,'{{SUMAR}}',$sumary);
            $event = $helper->change_data($event,'{{ST}}',$st);
            $event = $helper->change_data($event,'{{END}}',$end); 
            $event = $helper->change_data($event,'{{DESC}}',$desc); 
            $create_new = $client->create($event);
        }
    }
    Utils_RecordBrowserCommon::update_record('crm_meeting', $day->id, array('uid' => $new_uid),$full_update=false, $date=null, $dont_notify=false); 
 }
    $datetime = $date." 00:00:00";
    $rboTask =  new RBO_RecordsetAccessor('task');
    $get_days2 = $rboTask->get_records(array('>=deadline' => $datetime,'uid' => ''));
    foreach($get_days2 as $day){   
   $event = 'BEGIN:VCALENDAR
PRODID:-//SomeExampleStuff//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:{{CR}}
LAST-MODIFIED:{{MOD}}
DTSTAMP:{{STMP}}
UID:{{UNICAL}}
SUMMARY:{{SUMAR}}
DTSTART;TZID=Europe/Berlin:{{ST}}
DTEND;TZID=Europe/Berlin:{{END}}
DESCRIPTION:{{DESC}}
END:VEVENT
END:VCALENDAR';
    $created = $day->created_on;
    $data_extra = $day->to_array();
    $sumary = $data_extra["title"]; 
    $st = $data_extra['deadline']; 
    $desc = $data_extra["description"];
    $end = $st;
    $new_uid = "EPESIexportTasks".$day->id;
    $st = $helper->toTimeCAL($st);
    $end = $helper->toTimeCAL($end);
    $created = $helper->toTimeCAL($created)."Z";
    $end = $st;
    $employes = $data_extra["employees"];
    foreach ($employes as $employer){
        $user = $rbo->get_record($employer);
        if($user->get_val('calendar_url') != ''){ 
            $client->connect($user->get_val('calendar_url'),Connetion_data::login(),Connetion_data::password());
            $arrayOfCalendars = $client->findCalendars(); 
            $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
            $event = $helper->change_data($event,'{{CR}}',$created);
            $event = $helper->change_data($event,'{{MOD}}',$created);
            $event = $helper->change_data($event,'{{STMP}}',$created);
            $event = $helper->change_data($event,'{{UNICAL}}',$new_uid);
            $event = $helper->change_data($event,'{{SUMAR}}',$sumary);
            $event = $helper->change_data($event,'{{ST}}',$st);
            $event = $helper->change_data($event,'{{END}}',$end); 
            $event = $helper->change_data($event,'{{DESC}}',$desc); 
            $create_new = $client->create($event);
        }
    }
     Utils_RecordBrowserCommon::update_record('task', $day->id, array('uid' => $new_uid),$full_update=false, $date=null, $dont_notify=false);
 }
        //for phonecalls
    $rboPhone =  new RBO_RecordsetAccessor('phonecall');
    $get_days3 = $rboPhone->get_records(array('>=date_and_time' => $datetime,'uid' => ''));
    foreach($get_days3 as $day){
    $event = 'BEGIN:VCALENDAR
PRODID:-//SomeExampleStuff//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/Berlin
X-LIC-LOCATION:Europe/Berlin
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
CREATED:{{CR}}
LAST-MODIFIED:{{MOD}}
DTSTAMP:{{STMP}}
UID:{{UNICAL}}
SUMMARY:{{SUMAR}}
DTSTART;TZID=Europe/Berlin:{{ST}}
DTEND;TZID=Europe/Berlin:{{END}}
DESCRIPTION:{{DESC}}
END:VEVENT
END:VCALENDAR';
   $data_extra = $day->to_array();
   $created =  $day->created_on;// $query[$l]["created_on"];
   $sumary = $data_extra['subject'];// $query[$l]["f_subject"];
   $st = $day->get_val('date_and_time');// $query[$l]["f_date_and_time"];
   $desc = $data_extra['description'];// $query[$l]["f_description"];
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
           if ($phonenumber == ""){
               $phonenumber = $x->get_val("mobile_phone");
           }
       }else{
           $rb = new RBO_RecordsetAccessor('company');
           $x = $rb->get_record($number);
           $phonenumber =  $x->get_val('phone'); 
            }
    }
    $new_uid = "EPESIexportPhones".$day->id;
    $new_uid = str_replace(" ", "", $new_uid);
    $sumary = $sumary." \n  TEL: ".$phonenumber;
    $st = $helper->toTimeCAL($st);
    $end = $helper->toTimeCAL($end);
    $created = $helper->toTimeCAL($created)."Z";
    $employes = $data_extra["employees"];
    foreach ($employes as $employer){
        $user = $rbo->get_record($employer);
        if($user->get_val('calendar_url') != ''){ 
            $client = new SimpleCalDAVClient();
            $client->connect($user->get_val('calendar_url'), Connetion_data::login(),Connetion_data::password());
            $arrayOfCalendars = $client->findCalendars(); 
            $client->setCalendar($arrayOfCalendars[$helper->get_calendar_name($user->get_val('calendar_url'))]);
            $event = $helper->change_data($event,'{{CR}}',$created);
            $event = $helper->change_data($event,'{{MOD}}',$created);
            $event = $helper->change_data($event,'{{STMP}}',$created);
            $event = $helper->change_data($event,'{{UNICAL}}',$new_uid);
            $event = $helper->change_data($event,'{{SUMAR}}',$sumary);
            $event = $helper->change_data($event,'{{ST}}',$st);
            $event = $helper->change_data($event,'{{END}}',$end); 
            $event = $helper->change_data($event,'{{DESC}}',$desc); 
            $create_new = $client->create($event);
        }
    }
    Utils_RecordBrowserCommon::update_record('phonecall', $day->id, array('uid' => $new_uid),$full_update=false, $date=null, $dont_notify=false);
   }
  }
}