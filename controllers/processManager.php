<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

$settings = Reg::get('settings');
define('MAX_SIMULTANEOUS_REQUEST_PER_IP', $settings['MAX_SIMULTANEOUS_REQUEST_PER_IP'] > 0 ? $settings['MAX_SIMULTANEOUS_REQUEST_PER_IP'] : 2 );

define('MAX_SIMULTANEOUS_REQUEST', $settings['MAX_SIMULTANEOUS_REQUEST'] > 0 ? $settings['MAX_SIMULTANEOUS_REQUEST'] : 3 );
//define('MAX_SIMULTANEOUS_REQUEST_PER_SERVERGROUP', $settings['MAX_SIMULTANEOUS_REQUEST_PER_SERVERGROUP']);

define('TIME_DELAY_BETWEEN_REQUEST_PER_IP', $settings['TIME_DELAY_BETWEEN_REQUEST_PER_IP'] >= 0 ? $settings['TIME_DELAY_BETWEEN_REQUEST_PER_IP'] : 200 );

function executeJobs(){
	
	if(isset($GLOBALS['IS_EXECUTE_JOBS_OPEN']) && $GLOBALS['IS_EXECUTE_JOBS_OPEN']){
		echo 'recurrsive execute jobs call';
		return false;//recurrsive call
	}
	$GLOBALS['IS_EXECUTE_JOBS_OPEN'] = true;
	
	$settings = Reg::get('settings');
			
	  $noRequestRunning = true;
	  $requestInitiated = 0;
	  $requestPending 	= 0;
	  $isExecuteRequest = false;
	  static $lastIPRequestInitiated = '';
	  
	  $totalCurrentRunningRequest = DB::getField("?:history H LEFT JOIN ?:sites S ON H.siteID = S.siteID", "COUNT(H.historyID)", "H.status IN ('initiated', 'running')");
	  
	  if($totalCurrentRunningRequest >= MAX_SIMULTANEOUS_REQUEST){ echo 'MAX_SIMULTANEOUS_REQUEST'; $GLOBALS['IS_EXECUTE_JOBS_OPEN'] = false; return false; }//dont execute any request
			  
	  $runningRequestByIP = DB::getFields("?:history H LEFT JOIN ?:sites S ON H.siteID = S.siteID", "COUNT(H.historyID), S.IP", "H.status IN ('initiated', 'running') AND H.isPluginResponse = '1' GROUP BY S.IP", "IP");//H.isPluginResponse = 1 only WP sites call
	  
	  if(!empty($runningRequestByIP)){ //some request(s) are running
		  $noRequestRunning = false;
		  $runningRequestByServer = DB::getFields("?:history H LEFT JOIN ?:sites S ON H.siteID = S.siteID", "COUNT(H.historyID), S.serverGroup", "H.status IN ('initiated', 'running') GROUP BY S.serverGroup", "serverGroup");			
	  }
	  
	  //get pending request 
	  $pendingRequests = DB::getArray("?:history H LEFT JOIN ?:sites S ON H.siteID = S.siteID", "H.historyID, S.IP, S.serverGroup, H.actionID, H.runCondition, H.isPluginResponse", "(H.status = 'pending' OR (H.status = 'scheduled' AND H.timescheduled <= ".time()." AND H.timescheduled > 0)) ORDER BY H.historyID");
	  
	  if($noRequestRunning){		
		  $runningRequestByIP 	= array();
		  $runningRequestByServer = array();				
	  }
	  
	  
	  if(!empty($runningRequestByIP) && $settings['CONSIDER_3PART_IP_ON_SAME_SERVER'] == 1){//running IP information
		  $tempRunningRequestByIP = $runningRequestByIP;
		  $runningRequestByIP 	= array();
		  foreach($tempRunningRequestByIP as $tempIP => $tempCount){//only for IPv4
			  $IP3Part = explode('.', $tempIP);
			  array_pop($IP3Part);
			  $newTempIP = implode('.', $IP3Part);			  
			  $runningRequestByIP[$newTempIP] = $tempCount;
			  
		  }
	  }
	  
	if(!empty($pendingRequests) && is_array($pendingRequests)){
	  foreach($pendingRequests as $request){
		  $checkIPRestriction = true;
		  
		  $IPConsidered = $request['IP'];
		  
		  if($request['isPluginResponse'] === '0'){
			  $request['IP'] = '';
			  $checkIPRestriction = false;
		  }
		  
		  if($checkIPRestriction && $settings['CONSIDER_3PART_IP_ON_SAME_SERVER'] == 1){//only for IPv4
			  $IP3Part = explode('.', $IPConsidered);
			  array_pop($IP3Part);
			  $IP3Part = implode('.', $IP3Part);			  
			  $IPConsidered = $IP3Part;
		  }
		  
		 
		  if(!empty($request['runCondition']) && !isTaskRunConditionSatisfied($request['runCondition'])){
			   continue;  
		  }
		  
		  if($checkIPRestriction && !isset($runningRequestByIP[ $IPConsidered ])) $runningRequestByIP[ $IPConsidered ] = 0;
		 // if(!isset($runningRequestByServer[ $request['serverGroup'] ])) $runningRequestByServer[ $request['serverGroup'] ] = 0;
		  
		  if($totalCurrentRunningRequest >= MAX_SIMULTANEOUS_REQUEST){ echo 'MAX_SIMULTANEOUS_REQUEST';  $GLOBALS['IS_EXECUTE_JOBS_OPEN'] = false; return false; }
		  
		  //check already request are running in allowed level 
		  if($checkIPRestriction && $runningRequestByIP[ $IPConsidered ] >= MAX_SIMULTANEOUS_REQUEST_PER_IP /*|| $runningRequestByServer[ $request['serverGroup'] ] >=  MAX_SIMULTANEOUS_REQUEST_PER_SERVERGROUP*/){
			 
			  
			  if($runningRequestByIP[ $IPConsidered ] >= MAX_SIMULTANEOUS_REQUEST_PER_IP)
			  echo 'MAX_SIMULTANEOUS_REQUEST_PER_IP<br>';
			 /* if($runningRequestByServer[ $request['serverGroup'] ] >=  MAX_SIMULTANEOUS_REQUEST_PER_SERVERGROUP)
			  echo 'MAX_SIMULTANEOUS_REQUEST_PER_SERVERGROUP<br>';*/
			  continue; //already request are running on the limits
		  }
		  
		  $updateRequest = array('H.status' => 'initiated', 'H.microtimeInitiated' => microtime(true));
		  
		  $isUpdated = DB::update("?:history H", $updateRequest, "(H.status = 'pending' OR (H.status = 'scheduled' AND H.timescheduled <= ".time()." AND H.timescheduled > 0)) AND H.historyID = ".$request['historyID']);
		 
		  $isUpdated = DB::affectedRows();
		  
		  if($isUpdated){
			  //ready to run a child php to run the request
			  
			  if($lastIPRequestInitiated == $IPConsidered){
				  usleep((TIME_DELAY_BETWEEN_REQUEST_PER_IP * 1000));
			  }
			  
			  //(defined('CRON_MODE')  && ( CRON_MODE == 'systemCronShortTime'  || CRON_MODE == 'systemCronDefault') ) //need to avoid balance idle time when systemCron is triggered. so that new trigger call(multiCall) can be called soon
			 // echo '<br>executing child process';
			  if(defined('IS_EXECUTE_FILE') || (defined('CRON_MODE')  && ( CRON_MODE == 'systemCronShortTime'  || CRON_MODE == 'systemCronDefault') ) || $settings['executeUsingBrowser'] == 1){//this will also statisfy Reg::get('settings.executeUsingBrowser') == 1
				  //echo '<br>executing_directly';
				  executeRequest($request['historyID']);
				  $isExecuteRequest = true;
				  $requestPending++;
			  }
			  else{
				 // echo '<br>executing_async';
				 $callAsyncInfo = callURLAsync(APP_URL.EXECUTE_FILE, array('historyID' =>  $request['historyID'], 'actionID' => $request['actionID']));					 
				 onAsyncFailUpdate($request['historyID'], $callAsyncInfo);
				 // echo '<pre>callExecuted:'; var_dump($callAsyncInfo); echo'</pre>';
			  }
			 			 	
			  $requestInitiated++; 
			  
			  if($checkIPRestriction) $runningRequestByIP[ $IPConsidered ]++;
			 // $runningRequestByServer[ $request['serverGroup'] ] ++;
			  $totalCurrentRunningRequest++;
			  
			  
			  if($checkIPRestriction) $lastIPRequestInitiated = $IPConsidered;
			  
			  if($isExecuteRequest){ break; }//breaking here once executeRequest runs(direct call) next forloop job might be executed by other instance because that job loaded in array which already loaded from DB, still only the job inititated here will run  $isUpdated = DB::affectedRows();
		  }
		  else{
			echo 'update error, this request might be executed by someother instance.';  
		  }
	  }
	 } 
	  //return process
	  $GLOBALS['IS_EXECUTE_JOBS_OPEN'] = false;
	  return array('requestInitiated' => $requestInitiated, 'requestPending' => $requestPending);
}

function isTaskRunConditionSatisfied($runCondition){
	
	if(empty($runCondition)){ return true; }
	
	$runCondition = unserialize($runCondition);
	
	if(empty($runCondition['satisfyType'])){
		$runCondition['satisfyType'] = 'OR';
	}
	
	if($runCondition['satisfyType'] != 'AND' && $runCondition['satisfyType'] != 'OR'){
		return ;
	}
	
	$OK = true;
	
	
	if(!empty($runCondition['query'])){
		$query = $runCondition['query'];
		$tempResult = DB::getExists('?:'.$query['table'], $query['select'], $query['where']);
		if($runCondition['satisfyType'] == 'OR' && !empty($tempResult)){
			return true;
		}
		elseif($runCondition['satisfyType'] == 'AND' && empty($tempResult)){
			$OK = false;
		}
	}
	if(!empty($runCondition['maxWaitTime'])){
		$tempResult = ($runCondition['maxWaitTime'] <= time());
		if($runCondition['satisfyType'] == 'OR' && !empty($tempResult)){
			return true;
		}
		elseif($runCondition['satisfyType'] == 'AND' && empty($tempResult)){
			$OK = false;
		}
	}
	
	if($runCondition['satisfyType'] == 'OR'){
		return false;
	}
	elseif($runCondition['satisfyType'] == 'AND'){
		return $OK;
	}
	return ;	
}

?>