<?php

#
# manageEasyCron
# This Class is used to manage the EasyCron API with IWP Admin Panel
# Develope by : Revmakx Team
#

/**
 * ClassName : manageEasyCron
 */
 
require_once(APP_ROOT."/lib/easyCronAPI.php");

class manageEasyCron{
	
	private static $manageCronID;
	private static $taskCronID;
	private static $token;
	private static $easyCronAPIObj;
	
/*	public static function __construct(){
		
		self::loadAPIDetails();
	}
	*/
	
	/*
	* This function will save the API token and then creates task cron and manage cron.
	*/
	public static function activate($params){
				
		$token = $params['token'];
		$manageCronID = $taskCronID = '';
		
		updateOption("easyCronToken", $token);	
		self::loadAPIDetails();
		
		//first add 1 min cron, if free user it will give error
		//add "task cron" 
		$url = APP_FULL_URL.'cron.php?type=task';
		
		self::$easyCronAPIObj->setOptions(array('cron_job_name' => 'IWP Task Cron'));
		$result = self::$easyCronAPIObj->add($url, 1);
		if(!empty($result) && $result['status']=='success'){
			$taskCronID = $result['cron_job_id'];
		}
		else{
			return $result;
		}		
		
		
		//add "manage cron" 
		$url = APP_FULL_URL.'cron.php?type=manage';
		
		self::$easyCronAPIObj->setOptions(array('cron_job_name' => 'IWP Manage Cron'));
		$result = self::$easyCronAPIObj->add($url, 30);
		if(!empty($result) && $result['status']=='success'){
			$manageCronID = $result['cron_job_id'];
		}
		else{
			return $result;
		}
		
		//save the IDs in DB
		if($taskCronID && $manageCronID){
			updateOption("easyCronJobIDs", serialize(array('taskCronID' => $taskCronID, 'manageCronID' => $manageCronID)));
		}
		
		self::loadAPIDetails();
		return array('status' => 'success');
				
	}
	
	/*
	* This function will delete task cron and manage cron.
	*/
	public static function deactivate(){
		
		self::loadAPIDetails();
		
		$manageCronIDResult = self::$easyCronAPIObj->delete(self::$manageCronID);
		$taskCronIDResult = self::$easyCronAPIObj->delete(self::$taskCronID);
		deleteOption("easyCronJobIDs");		
		self::loadAPIDetails();
		if($manageCronIDResult['status']=='error' || $taskCronIDResult['status']=='error'){
			//error message
			return array('status' => 'error', 'error' => array('message' => 'Deactivated. But there is error in deleting the cron task, please manually delete cron job IDs '.self::$manageCronID.' and  '.self::$taskCronID.''));
		}
		else{
			//success
			return array('status' => 'success');
		}
	}
	
	/*
	* To check whether easycron is active or not.
	*/
	public static function isActive(){
		
		self::loadAPIDetails();
		
		if(!empty(self::$manageCronID) && !empty(self::$taskCronID)){
			return true;
		}
		return false;
	}
	
	/*
	* To get a saved token from DB.
	*/
	public static function getTokenFromDB(){
		return getOption("easyCronToken");
	}
	
	
	/*
	* To enable taskCron (1 min)
	*/
	public static function taskCronEnable(){
		
		self::loadAPIDetails();
		
		$result = self::$easyCronAPIObj->enable(self::$taskCronID);
		return $result;
	}
	
	/*
	* To disable taskCron (1 min)
	*/
	public static function taskCronDisable(){
		
		self::loadAPIDetails();
		
		$result = self::$easyCronAPIObj->disable(self::$taskCronID);
		return $result;
	}
	
	/*
	* To enable manage cron (30 mins)
	*/
	public static function manageCronEnable(){
		
		self::loadAPIDetails();
		
		$result = self::$easyCronAPIObj->enable(self::$manageCronID);
		return $result;
	}
	
	/*
	* To get API token from DB
	*/
	private static function loadAPIDetails(){
		
		self::$easyCronAPIObj = new easyCronAPI();

		self::$token = getOption("easyCronToken");
		self::$easyCronAPIObj->setToken(self::$token);
		$jobIDs = array();
		$easyCronJobIDs = getOption("easyCronJobIDs");
		if(!empty($easyCronJobIDs)){
			$jobIDs = unserialize($easyCronJobIDs);
		}
		self::$manageCronID = $jobIDs['manageCronID'];
		self::$taskCronID = $jobIDs['taskCronID'];
	}
	
}

panelRequestManager::addFunctions('manageEasyCron::activate', 'manageEasyCron::deactivate', 'manageEasyCron::isActive', 'manageEasyCron::taskCronEnable', 'manageEasyCron::taskCronDisable', 'manageEasyCron::manageCronEnable', 'manageEasyCron::getTokenFromDB');
