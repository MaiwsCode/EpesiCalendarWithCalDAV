<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class iCalSyncInstall extends ModuleInstall {

	public function install() {
// Here you can place installation process for the module
		$ret = true;
			$field = array('name' => _M('calendar url'), 'type'=>'text', 'required'=>false, 'visable'=>true,
			'param'=>255);
			Utils_RecordBrowserCommon::new_record_field('contact',$field);
			$field = array('name' => _M('cal password'), 'type'=>'text', 'required'=>false, 'visable'=>true,
			'param'=>255);
			Utils_RecordBrowserCommon::new_record_field('contact',$field);
			$field = array('name' => _M('uid'), 'type'=>'text', 'required'=>false, 'visable'=>false,
			'param'=>255);
			Utils_RecordBrowserCommon::new_record_field('phonecall',$field);
			Utils_RecordBrowserCommon::new_record_field('crm_meeting',$field);
			Utils_RecordBrowserCommon::new_record_field('task',$field);
		Utils_RecordBrowserCommon::register_processing_callback('crm_meeting', array($this->get_type () . 'Common', 'on_action_meeting'));
		Utils_RecordBrowserCommon::register_processing_callback('phonecall', array($this->get_type () . 'Common', 'on_action_phonecall'));
		Utils_RecordBrowserCommon::register_processing_callback('task', array($this->get_type () . 'Common', 'on_action_task'));
            //    DB::Execute('CREATE TABLE public.users_calendar("ID" integer NOT NULL DEFAULT nextval("users_calendar_ID_seq"::regclass),user_id integer,adress_url text COLLATE pg_catalog."default",CONSTRAINT users_calendar_pkey PRIMARY KEY ("ID"))');
		//Utils_RecordBrowserCommon::register_processing_callback('crm_calendar',array('iCalSyncCommon','add_action_bar'));
		return $ret; // Return true on success and false on failure
	}

public function uninstall() {
// Here you can place uninstallation process for the module
		$ret = true;
		Utils_RecordBrowserCommon::delete_record_field('contact','calendar url');
		Utils_RecordBrowserCommon::delete_record_field('contact','cal password');
		Utils_RecordBrowserCommon::delete_record_field('crm_meeting','uid');
		Utils_RecordBrowserCommon::delete_record_field('phonecall','uid');
		Utils_RecordBrowserCommon::delete_record_field('task','uid');
		Utils_RecordBrowserCommon::unregister_processing_callback('crm_meeting', array($this->get_type () . 'Common', 'on_action_meeting'));
		Utils_RecordBrowserCommon::unregister_processing_callback('phonecall', array($this->get_type () . 'Common', 'on_action_phonecall'));
		Utils_RecordBrowserCommon::unregister_processing_callback('task', array($this->get_type () . 'Common', 'on_action_task'));
		Utils_RecordBrowserCommon::delete_record_field('contact','calendar url');
		Utils_RecordBrowserCommon::delete_record_field('contact','cal password');
		$ret = true;
             //   DB::Execute("DROP TABLE public.users_calendar");
		return $ret; // Return false on success and false on failure
	}

	public function requires($v) {
// Returns list of modules and their versions, that are required to run this module
		return array(); 
	}
	public function version() {
	// Return version name of the module
			return array('1.0'); 
		}

	public function simple_setup() {
// Indicates if this module should be visible on the module list in Main Setup's simple view
		return true; 
	}

}

?>