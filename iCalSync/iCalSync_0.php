<?php
defined("_VALID_ACCESS") || die('Direct access forbidden'); // This is a security feature.

/*require 'client/CalDAVCalendar.php';
require 'client/CalDAVClient.php';
require 'client/CalDAVObject.php';
require 'client/CalDAVFilter.php';
require 'client/CalDAVException.php';
require 'client/SimpleCalDAVClient.php';
require 'client/class.iCalReader.php';*/


class iCalSync extends Module { // Note, how the class' name reflects module's path.

   
   //Pisane i testowane funkcje aby widzieć rezulaty na bieżąco - plik nie istotny w działaniu programu 
  
  public function body(){

       
          }
public function settings()
	{
         
              //  $this->update_epesi();
             //   $this->add_events();
	}
   /*     
public function get_date(){
    $date = date("Y-m-d");
    $date = str_replace("-", "" ,$date);
    $date = $date."T000000Z";
    return $date;
    
}
public function  get_calendar_name($link){
    
    	$link_ = strrev($link);
        $link_ = substr($link_, 1);
        $link_ = explode("/", $link_);
        $link_ = $link_[0];
        $link_ = strrev($link_);
        return $link_;
}

        
public function set_data($array,$array_name){
    
    try{
        return $array[$array_name];
        
    }
    catch(Exception $e){
        return "";
    }
    
}
public function convert_date($date){

    $year = substr($date, 0,4);
    $month = substr($date,4,2);
    $day = substr($date,6,2);
    $time = $year."-".$month."-".$day;
    return $time;
}
public function convert_date_time($date){
    //20180622T180000 => 2018 06 22 T 18 00 00
    $year = substr($date, 0,4);
    $month = substr($date,4,2);
    $day = substr($date,6,2);
    $hour = substr($date,9,2);
    $minute = substr($date,11,2);
    $second = substr($date,13,2);
    $time = $year."-".$month."-".$day." ".$hour.":".$minute.":".$second;
    return $time;
}


public function update_epesi(){
    
          $users_count = DB::GetAll("SELECT COUNT(*) FROM contact_data_1 WHERE f_calendar_url != '' ");
          $users_count = $users_count[0][0];
          $users_to_sync = DB::GetArray("SELECT f_login , f_calendar_url FROM contact_data_1 WHERE f_calendar_url != '' ");
          for($u =0;$u<$users_count;$u++){      
          $client = new CalDAVClient($users_to_sync[$u]["f_calendar_url"],"test","test");
          $start = $this->get_date();
          $result = $client->GetEvents($start);
//for meetings
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
                $time = $this->convert_date_time($time);
               
                $catch = DB::GetAll("SELECT * FROM public.crm_meeting_data_1 WHERE  f_related = '$uid'");
                $catch2 = DB::GetAll("SELECT * FROM public.phonecall_data_1 WHERE  f_related = '$uid'");
                $catch3 = DB::GetAll("SELECT * FROM public.task_data_1 WHERE  f_related = '$uid'");
                if($catch != null or $catch2 != null or $catch3 != null){ $exist = true;}
                if($exist == false){
                $description = "[M]";
                if($event[0]["DESCRIPTION"]){
                $description = $event[0]["DESCRIPTION"];}
                $description = $description.=" Dodano z kalendarza Google";
                $start = $event[0]["DTSTART"];
                $date = $this->convert_date($start);
                $start = $this->convert_date_time($start);
                $now = date("Y-m-d H:i:s");
                DB::Execute("INSERT INTO public.crm_meeting_data_1 "
                        . "(created_by,created_on, f_title,f_description,f_date,f_time,f_duration,f_employees,f_status,f_priority,f_permission,f_related) "
                        . "VALUES( ".$users_to_sync[$u]['f_login'].",'$now', '$summary','$description','$date','$start',3600,'__".$users_to_sync[$u]['f_login']."__',0,1,0,'$uid')");
         }
        } 
       }
      }
      public function change_data($string,$keyword,$data){
          
          $str = $string;
          $str = str_replace($keyword, $data, $string);
          return $str;
          
      }
      
     public function toTimeCAL($date){
    
         $date = str_replace("-", "",$date );
         $date = str_replace(":", "",$date );
         $date = str_replace(" ", "T",$date );
         return $date;  
         
     }
      public function add_events(){
        $date = date_create(date('Y-m-d'));
        date_sub($date, date_interval_create_from_date_string('14 days'));
        $date =  date_format($date, 'Y-m-d');
        $users_count = DB::GetAll("SELECT COUNT(*) FROM contact_data_1 WHERE f_calendar_url != ''");
        $users_count = $users_count[0][0];
        $users_to_sync = DB::GetArray("SELECT f_login , f_calendar_url FROM contact_data_1 WHERE f_calendar_url != '' ");
        for($u =0;$u<$users_count;$u++){      
       // $client = new CalDAVClient($users_to_sync[$u]["adress_url"],"test","test");  
        $client = new SimpleCalDAVClient();
        $client->connect($users_to_sync[$u]["f_calendar_url"], 'test', 'test');
	$arrayOfCalendars = $client->findCalendars(); 
	$client->setCalendar($arrayOfCalendars[$this->get_calendar_name($users_to_sync[$u]["f_calendar_url"])]);
        $query = DB::GetArray("SELECT * FROM public.crm_meeting_data_1 WHERE created_by = ".$users_to_sync[$u]["f_login"]." and f_date >= '$date'");
        //for meetings
        for($l = 0;$l<count($query);$l++){
        
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
   $created = $query[$l]["created_on"];
   $sumary = $query[$l]["f_title"];
   $unical = $query[$l]["f_related"];
   $st = $query[$l]["f_time"];
   $desc = $query[$l]["f_description"];
   $end = $st;
   
   $new_uid = "EPESIexportMeetings".$query[$l]["id"];
   if($query[$l]["f_related"] == ""){
          $new_uid = str_replace(" ", "", $new_uid);
          DB::Execute("UPDATE public.crm_meeting_data_1 SET f_related = '".$new_uid."' WHERE ID = ".$query[$l]["id"]);
       
   
   
   $st = $this->toTimeCAL($st);
   $end = $this->toTimeCAL($end);
   $created = $this->toTimeCAL($created)."Z";
   
   
    $event = $this->change_data($event,'{{CR}}',$created);
    $event = $this->change_data($event,'{{MOD}}',$created);
    $event = $this->change_data($event,'{{STMP}}',$created);
    $event = $this->change_data($event,'{{UNICAL}}',$new_uid);
    $event = $this->change_data($event,'{{SUMAR}}',$sumary);
    $event = $this->change_data($event,'{{ST}}',$st);
    $event = $this->change_data($event,'{{END}}',$end); 
    $event = $this->change_data($event,'{{DESC}}',$desc); 
        $create_new = $client->create($event);
         }
        }
        //for tasks 
        $datetime = $date." 00:00:00";
       $query = DB::GetArray("SELECT * FROM public.task_data_1 WHERE created_by = ".$users_to_sync[$u]["f_login"]." and f_deadline >= '$datetime' ");
       for($l = 0;$l<count($query);$l++){
        
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
   $created = $query[$l]["created_on"];
   $sumary = $query[$l]["f_title"];
   $unical = $query[$l]["f_related"];
   $st = $query[$l]["f_deadline"];
   $desc = $query[$l]["f_description"];
   $end = $st;
   
   $new_uid = "EPESIexportTasks".$query[$l]["id"];
   if($query[$l]["f_related"] == ""){
          $new_uid = str_replace(" ", "", $new_uid);
          DB::Execute("UPDATE public.crm_meeting_data_1 SET f_related = '".$new_uid."' WHERE ID = ".$query[$l]["id"]);
       
   
   
   $st = $this->toTimeCAL($st);
   $end = $this->toTimeCAL($end);
   $created = $this->toTimeCAL($created)."Z";
   
   
    $event = $this->change_data($event,'{{CR}}',$created);
    $event = $this->change_data($event,'{{MOD}}',$created);
    $event = $this->change_data($event,'{{STMP}}',$created);
    $event = $this->change_data($event,'{{UNICAL}}',$new_uid);
    $event = $this->change_data($event,'{{SUMAR}}',$sumary);
    $event = $this->change_data($event,'{{ST}}',$st);
    $event = $this->change_data($event,'{{END}}',$end); 
    $event = $this->change_data($event,'{{DESC}}',$desc); 
        $create_new = $client->create($event);
         }
        }
        
        
        //for phonecalls
              $query = DB::GetArray("SELECT * FROM public.phonecall_data_1 WHERE created_by = ".$users_to_sync[$u]["f_login"]." and f_date_and_time >= '$datetime'");
        for($l = 0;$l<count($query);$l++){
        
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
   $created = $query[$l]["created_on"];
   $sumary = $query[$l]["f_subject"];
   $unical = $query[$l]["f_related"];
   $st = $query[$l]["f_date_and_time"];
   $desc = $query[$l]["f_description"];
   $end = $st;
   $phonenumber = $query[$l]["f_other_phone_number"];
   if($phonenumber == ""){
       $klient = $query[$l]["f_customer"];
       $klient = explode("/", $klient);
       $type = $klient[0];
       $klient = $klient[1];
       if($type == "company"){
           $get_phone = DB::GetArray("SELECT f_phone FROM public.company_data_1");
           $phonenumber = $get_phone[0][0];
       }
       else{
           $get_phone = DB::GetArray("SELECT f_mobile_phone FROM public.contact_data_1");
           $phonenumber = $get_phone[0][0];
           if($get_phone == null){
               $get_phone = DB::GetArray("SELECT f_work_phone FROM public.contact_data_1");
               $phonenumber = $get_phone[0][0];
           }
       }
       
       
   }
   
   
   $new_uid = "EPESIexportPhones".$query[$l]["id"];
   if($query[$l]["f_related"] == ""){
          $new_uid = str_replace(" ", "", $new_uid);
          DB::Execute("UPDATE public.phonecall_data_1 SET f_related = '".$new_uid."' WHERE ID = ".$query[$l]["id"]);
       
   
   $sumary = $sumary." \n  TEL: ".$phonenumber;
   $st = $this->toTimeCAL($st);
   $end = $this->toTimeCAL($end);
   $created = $this->toTimeCAL($created)."Z";
   
   
    $event = $this->change_data($event,'{{CR}}',$created);
    $event = $this->change_data($event,'{{MOD}}',$created);
    $event = $this->change_data($event,'{{STMP}}',$created);
    $event = $this->change_data($event,'{{UNICAL}}',$new_uid);
    $event = $this->change_data($event,'{{SUMAR}}',$sumary);
    $event = $this->change_data($event,'{{ST}}',$st);
    $event = $this->change_data($event,'{{END}}',$end); 
    $event = $this->change_data($event,'{{DESC}}',$desc); 
        $create_new = $client->create($event);
         }
        }
      }   
     }
     */
     
}
    


?>



