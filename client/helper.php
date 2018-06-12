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
    $year = substr($date, 0,4);
    $month = substr($date,4,2);
    $day = substr($date,6,2);
    $hour = substr($date,9,2);
    $minute = substr($date,11,2);
    $second = substr($date,13,2);
    $time = $year."-".$month."-".$day." ".$hour.":".$minute.":".$second;
    return $time;
}
   public static function change_data($string,$keyword,$data){
          
          $str = $string;
          $str = str_replace($keyword, $data, $string);
          return $str;
          
      }
      
    public static function toTimeCAL($input_date){       
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