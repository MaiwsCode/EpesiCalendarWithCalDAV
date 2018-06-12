<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class helper {

    public static function get_date(){
    $date = date("Y-m-d");
    $date = str_replace("-", "" ,$date);
    $date = $date."T000000Z";
    return $date;
    
    }
    public static function  get_calendar_name($link){
    	$link_ = strrev($link);
        $link_ = substr($link_, 1);
        $link_ = explode("/", $link_);
        $link_ = $link_[0];
        $link_ = strrev($link_);
        return $link_;
    }
    public function duration($start,$stop){
          $start_ = strtotime($start);
          $stop_ = strtotime($stop);
          $calc = $stop_ - $start_;
          return $calc;
    }

    public static function set_access_status_numeric($status_in){
        $status = $status_in;
        switch ($status){
            case "PRIVATE":
                $status = 2;
                break;
            case "PUBLIC":
                $status = 0;
                break;
            default :
                $status = 0;
                break;
        }
        return $status;
    }
    public static function set_access_status($status_in){
        $status = "PUBLIC";
        switch ($status_in){
            case 0:
                $status = "PUBLIC";
                break;
            case 1:
                 $status = "PUBLIC";
                 break;
            case 2:
                 $status = "PRIVATE";
                 break;
            default:
                 $status = "PUBLIC";
                break;
        }
        return $status;
    }   
   
    
    public static function set_data($array,$array_name){
    
        try{
            return $array[$array_name];
        
        }
        catch(Exception $e){
            return "";
        }
    
    }
    public static function convert_date($date){
        $year = substr($date, 0,4);
        $month = substr($date,4,2);
        $day = substr($date,6,2);
        $time = $year."-".$month."-".$day;
        return $time;
    }
    public static function convert_date_time($date){
        //20180622T180000 => 2018 06 22 T 18 00 00
        if(strlen($date)>11){
            $year = substr($date, 0,4);
            $month = substr($date,4,2);
            $day = substr($date,6,2);
            $hour = substr($date,9,2);
            $minute = substr($date,11,2);
            $second = substr($date,13,2);
            $time = $year."-".$month."-".$day." ".$hour.":".$minute.":".$second;
            return $time;
        }
        else{
            $time = '';
            return $time;
        }
    }
    public static function change_data($string,$keyword,$data){ 
          $str = $string;
          $str = str_replace($keyword, $data, $string);
          return $str;
          
      }

    public static function toDateCAL($input_date){       
         $timestamp = strtotime($input_date);
         $date = date("Ymd",$timestamp);
         return $date;  
    }
    public static function toDateTimeCAL($input_date){       
         $timestamp = strtotime($input_date);
         $date = date("Ymd\THis",$timestamp);
         return $date;  
    }
    public static function calc_duration($input_date,$duration) {
        $timestamp = strtotime($input_date);
        $timestamp = $timestamp + $duration;
        $date = date("Ymd\THis",$timestamp);
        return $date;    
    }
}