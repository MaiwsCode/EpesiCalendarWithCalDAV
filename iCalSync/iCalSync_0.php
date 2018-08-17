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
        iCalSyncCommon::insert_from_server();
        iCalSyncCommon::update_changes_from_server();
    }
 
     
}  
    


?>



