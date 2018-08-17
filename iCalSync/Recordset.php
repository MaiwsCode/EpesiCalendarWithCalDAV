<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
 
class iCalSync_Recordset  extends RBO_Recordset {
 
    function table_name() { // - choose a name for the table that will be stored in EPESI database
 
        return 'calendar_sync';
 
    }
    function fields() { // - here you choose the fields to add to the record browser

      //  Unikalne id z w CalDavie
        $uid = new RBO_Field_Text(_M('uid'));
        $uid->set_required()->set_visible()->set_length(255);
        //typ zdarzenia meeting/task/phonecalls           
        $table = new RBO_Field_Text(_M('table name'));
        $table->set_required()->set_visible()->set_length(255);
        //id eventu w typie zdarzen
        $event_id = new RBO_Field_Text(_M("event_id"));
        $event_id->set_required()->set_visible()->set_length(255);
        //info ze w CalDavie zostało usuniete
        $etag = new RBO_Field_Text("etag");
        $etag->set_required()->set_visible()->set_length(255);

        $href = new RBO_Field_Text("href");
        $href->set_required()->set_visible()->set_length(255);
        
        return array($uid, $table, $event_id,$etag,$href); // - remember to return all defined fields
 
 
    }
    
}

?>