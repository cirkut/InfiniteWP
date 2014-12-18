<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

class panelRequestManager{
	
	private static $addonFunctions = array();
	private static $rawSitesStatsCache = array();
	
	public static function handler($requestData){
		$requestStartTime = microtime(true);
		
                    /* Checking the condition for this action is allowed to user (JS INJECTION ) */
                $userData = DB::getRow("?:users", "userID, accessLevel, permissions", "userID = '".$GLOBALS['userID']."'");
                $Restrict=FALSE;
                if($userData['accessLevel']!='admin')
                {
                    if(function_exists('userRestrictChecking')){
                        $Restrict=userRestrictChecking($userData,$requestData);
                    }
                }	
		//$GLOBALS['printAll'] = true;
		
		$clearPrint = empty($GLOBALS['printAll']) ? true :  false;
		
		if($clearPrint){
			ob_start();
		}
		
		$actionResult = $data = array();
		
		$action 	= $requestData['action'];
		$args		= $requestData['args'];
		$siteIDs 	= $requestData['args']['siteIDs'];
		$params 	= $requestData['args']['params'];
		$extras 	= $requestData['args']['extras'];
		$requiredData = $requestData['requiredData'];		
		$actionID = uniqid('', true);
		Reg::set('currentRequest.actionID', $actionID);
                if(!$Restrict){  //Checking restriction here
		if(manageClients::methodExists($action)){
			manageCookies::cookieUnset('slowDownAjaxCallFrom');
			
			if(!empty($siteIDs)){
				if(function_exists('multiUserAccess')){
					multiUserAccess($siteIDs);
				}
			}
						
			manageClients::execute($action, array('siteIDs' => $siteIDs, 'params' => $params, 'extras' => $extras));
			
			if(Reg::get('currentRequest.exitOnComplete') === true){
				if(Reg::get('settings.executeUsingBrowser') != 1){//to fix update notification going "everything up to date" for fsock users
					executeJobs();
				}
				$exitOnCompleteT = microtime(true);
				exitOnComplete();
				$exitOnCompleteTT = microtime(true) - $exitOnCompleteT;
			}
			if(Reg::get('currentRequest.sendAfterAllLoad') === true){
				sendAfterAllLoad($requiredData);	
			}
			
			$actionResult = self::getActionStatus($actionID, $action);
		}
		if(Reg::get('settings.executeUsingBrowser') != 1 && !defined('CRON_MODE') && !defined('IS_EXECUTE_FILE')){// && !defined('CRON_MODE') && !defined('IS_EXECUTE_FILE') //panelRequestManager::handler() has been called in many places in app. inorder to avoid executeJobs()
			executeJobs();
		}
                }
		$data = self::requiredData($requiredData);
		
		$finalResponse = array();
		$finalResponse = array('actionResult' => $actionResult, 'data' => $data);		
		
		if(empty($requestData['noGeneralCheck'])){
			self::generalCheck($finalResponse);
		}
		
		$finalResponse['sendNextAjaxCallAfter'] = self::getSendNextAjaxCallAfter();
		$finalResponse['showBrowserCloseWarning'] = showBrowserCloseWarning();
		
		
		if($clearPrint){
			$printedText = ob_get_clean();
		}
		
		$finalResponse['debug'] = array('exitOnCompleteTimeTaken' => $exitOnCompleteTT, 
										'currentRequest.exitOnComplete' => var_export(Reg::get('currentRequest.exitOnComplete'), true),
										'totalRequestTimeTaken' => (microtime(true) - $requestStartTime),
										/*'printedText' => $printedText,*/
										);
		
		
	    return json_encode($finalResponse);
	}
	
	public static function userAccess($siteIDs){
		$count = count($siteIDs);
		$accessSitesCount = DB::getfield("?:user_access", "count(siteID)", "userID = '".$GLOBALS['userID']."' AND siteID IN (". implode(', ', $siteIDs).")" );
		if($accessSitesCount == $count && $count > 0){
			return true;
		}		
		return false;
	}
	
	public static function requiredData($requiredData){
		$data = array();
		if(empty($requiredData)){
			 return $data;
		}
		Reg::tplSet('sitesData', self::getSites());
		foreach($requiredData as $action => $args){
			if(method_exists('panelRequestManager', $action)){
                            if($action=='getSitesUpdates'){
                                $data[ $action ] = self::$action($GLOBALS['userID']);
                            }
                            else
                            {
								$data[ $action ] = self::$action($args);
                            }         
			}
			elseif(in_array($action, self::$addonFunctions) && function_exists($action)){
				$data[ $action ] = call_user_func($action, $args);
			}
			elseif(strpos($action, '::') !== false && in_array($action, self::$addonFunctions)){
				$tempMethod = explode('::', $action);
				if(method_exists($tempMethod[0], $tempMethod[1])){
					$data[ $action ] = call_user_func($action, $args);
				}
			}
		}
		return $data;
	}
	
	public static function addFunctions(){
		$args = func_get_args();
		self::$addonFunctions = array_merge(self::$addonFunctions, $args);
	}

	
	public static function getBackups($siteID, $refresh=false){//viewBackups
		
		if($refresh){
			manageClients::getStatsProcessor(array($siteID));
		}
		$sitesStatRaw = DB::getRow("?:site_stats", "*", "stats IS NOT NULL AND siteID = '".$siteID."'");	
		$backups = unserialize(base64_decode($sitesStat['stats']['iwp_backups'])); 
		
		return $backups;
	}
	
	
	public static function addSiteSetGroups($siteID, $groupsPlainText, $groupIDs){
		
		if(empty($siteID)) return false;
		
		if(empty($groupIDs)){ $groupIDs = array(); }
		
		DB::delete("?:groups_sites", "siteID='".$siteID."'");//for updating
		
		$groupNames = explode(',', $groupsPlainText);
		array_walk($groupNames, 'trimValue');
		$groupNames = array_filter($groupNames);
		if(!empty($groupNames)){
			$existingGroups = DB::getArray("?:groups", "*", "name IN ('". implode("', '", $groupNames) ."')", "name");
			foreach($groupNames as $groupName){
				if(isset($existingGroups[$groupName])){
					array_push($groupIDs, $existingGroups[$groupName]['groupID']);
				}
				else{
					$newGroupID = self::addGroup($groupName);
					array_push($groupIDs, $newGroupID);
				}
			}			
		}
		$groupIDs = array_filter(array_unique($groupIDs));
		
		if(!empty($groupIDs)){
			foreach($groupIDs as $groupID){
				DB::replace("?:groups_sites", array('groupID' => $groupID, 'siteID' => $siteID));
			}
		}		
	}
	
	public static function manageGroups($groupsData){
		
		$newGroups = $groupsData['new'];//array('new-0' => 'name', 'new-1' => 'groupname2');
		if(!empty($newGroups)){
			$newGroups = array_filter($newGroups);
		}
		$deleteGroups = $groupsData['delete'];//array(1, 2);//groupIDS
		$updateGroupsSites = (!empty($groupsData['updateSites'])) ? $groupsData['updateSites'] : array();//array(5 => array(1,2), 'new-1' => array(2,4));//'new-1' => its new group this key will be replaced by it id, before processing this array
		$updateGroupsNames  = $groupsData['updateNames'];//array(101 => 'newname', 102 => 'newname2');
		
		if(!empty($newGroups)){
			foreach($newGroups as $newGroupKey => $newGroupName){
				$newGroupID = self::addGroup($newGroupName);
				if($newGroupID){
					$updateGroupsSites[$newGroupID] = $updateGroupsSites[$newGroupKey];//here new-0 will be replaced by groupID
					unset($updateGroupsSites[$newGroupKey]);
				}
			}
		}
		
		if(!empty($updateGroupsSites)){
			$tempUpdateGroupsSites = $updateGroupsSites;
			foreach($tempUpdateGroupsSites as $groupID => $temp){
				if(!is_numeric($groupID)){ unset($updateGroupsSites[$groupID]); }
			}
			self::updateGroupsSites($updateGroupsSites);
		}
		
		if(!empty($updateGroupsNames)){
			foreach($updateGroupsNames as $groupID => $groupName){
				self::updateGroup($groupID, $groupName);
			}
		}
		
		if(!empty($deleteGroups)){
			foreach($deleteGroups as $groupID){
				self::deleteGroup($groupID);
			}
		}
		return true;		
	}
	
	private static function updateGroupsSites($params){
		
		if(empty($params)){ return false; }
		foreach($params as $groupID => $siteIDs){
			if(empty($siteIDs)){ continue; }
			DB::delete("?:groups_sites", "groupID = '".$groupID."'");
			foreach($siteIDs as $siteID){
				if(is_numeric($siteID)){
					DB::replace("?:groups_sites", array('groupID' => $groupID, 'siteID' => $siteID));
				}
			}
		}
		return true;		
	}
	
	public static function getGroupsSites(){
		$groups = $groupsSites = array();
		
		$where = " ";
		$where2 = "1";
	
		$groupsSites = DB::getArray("?:groups_sites GS, ?:groups G", "GS.siteID, GS.groupID", "GS.groupID =  G.groupID ".$where);
		$groups = DB::getArray("?:groups", "groupID, name", $where2." ORDER BY groupID", "groupID");
		
		
		foreach($groupsSites as $groupSites){
			$groups[ $groupSites['groupID'] ]['siteIDs'][] = $groupSites['siteID'];
		}
		return $groups;
	}
	
	private static function addGroup($name){
		return DB::insert("?:groups", array('name' => $name));
	}
	
	private static function updateGroup($groupID, $name){
		return DB::update("?:groups", array('name' => $name), "groupID='".$groupID."'");
	}
	
	private static function deleteGroup($groupID){
		$done = DB::delete("?:groups", "groupID='".$groupID."'");
		if($done){
			$done = DB::delete("?:groups_sites", "groupID='".$groupID."'");
		}
		return $done;
	}
	
	public static function getRawSitesStats($siteIDs=array(), $userID = ''){
		$cacheSlug = 'all';
		if(!empty($siteIDs)){
			sort($siteIDs);
			$cacheSlug =  implode('-', $siteIDs);
		}
        
		if(!empty(self::$rawSitesStatsCache[$cacheSlug])){
			return self::$rawSitesStatsCache[$cacheSlug];
		}
		$where = "";
		$sitesStats = array();
		
		if(function_exists('multiUserRawSitesStats')){
			multiUserRawSitesStats($sitesStats, $siteIDs, $userID);
		}
		else{
			if(!empty($siteIDs)){
				$where = " AND SS.siteID IN (". implode(',', $siteIDs) .")";
			}
					
			$sitesStats = DB::getArray("?:site_stats SS, ?:sites S", "SS.*", "S.siteID = SS.siteID AND SS.stats IS NOT NULL ".$where, "siteID");
		}
		
		if(empty($sitesStats)){ return array(); }
		foreach($sitesStats as $siteID => $sitesStat){
			$sitesStats[$siteID]['stats'] = unserialize(base64_decode($sitesStat['stats']));
		}
		

		self::$rawSitesStatsCache[$cacheSlug]=$sitesStats;
		return $sitesStats;
	}
	
	public static function getSitesBackups($siteIDs=array()){
		$sitesStats = self::getRawSitesStats($siteIDs);
		
		$sitesBackups = array();
		
		foreach($sitesStats as $siteID => $siteStats){
			
			$backupKeys = array_keys($siteStats['stats']['iwp_backups']);
				
			foreach($backupKeys as $key => $backupKey){
				
				$backupTaskType = 'backupNow';
				if($backupKey != 'Backup Now'){
					$siteExist = DB::getExists("?:backup_schedules_link BSL, ?:backup_schedules BS", "siteID", "BS.scheduleKey = '".$backupKey."' AND BSL.siteID='".$siteID."' AND BS.scheduleID = BSL.scheduleID");	
					
					if($siteExist){ continue;  }					
					$backupTaskType = 'otherBackup';
				}
				
				if(empty($siteStats['stats']['iwp_backups'][$backupKey])){
					continue;
				}
				
				$siteBackupsTemp = $siteStats['stats']['iwp_backups'][$backupKey];
				$siteBackups[$siteID] = array();
				krsort( $siteBackupsTemp );
				
				foreach($siteBackupsTemp as $referenceKey => $siteBackupTemp){
					if(!empty($siteBackupTemp['error'])){ continue; }
					
					$otherParts = '';
													
					if(empty($siteBackupTemp['server']['file_url']) && !empty($siteBackupTemp['ftp'])){
						$otherParts = $siteBackupTemp['ftp'];
					}
					if(empty($siteBackupTemp['server']['file_url']) && !empty($siteBackupTemp['amazons3'])){
						$otherParts = $siteBackupTemp['amazons3'];
					}
					if(empty($siteBackupTemp['server']['file_url']) && !empty($siteBackupTemp['dropbox'])){
						$otherParts = $siteBackupTemp['dropbox'];
					}
					if(empty($siteBackupTemp['server']['file_url']) && !empty($siteBackupTemp['gDriveOrgFileName'])){
						$otherParts = $siteBackupTemp['gDriveOrgFileName'];
					}
					if((is_array($siteBackupTemp['server']['file_url']))||(is_array($otherParts)))
					{
						$fileURLParts = explode('/', $siteBackupTemp['server']['file_url'][0] ? $siteBackupTemp['server']['file_url'][0] : $otherParts[0]);
					}
					else
					{
						$fileURLParts = explode('/', $siteBackupTemp['server']['file_url'] ? $siteBackupTemp['server']['file_url'] : $otherParts);
					}
					$fileName = array_pop($fileURLParts);
					$fileNameParts = explode('_', $fileName);
					$what = $fileNameParts[2];
					
					$repo = '';
					//only showing files which are available
					if(array_key_exists('server', $siteBackupTemp))
					{
						$repo = "Server";
					}
					if(array_key_exists('ftp', $siteBackupTemp))
					{
						$repo = "FTP";
					}
					if(array_key_exists('amazons3', $siteBackupTemp))
					{
						$repo = "Amazon S3";
					}
					if(array_key_exists('dropbox', $siteBackupTemp))
					{
						$repo = "Dropbox";
					}
					if(array_key_exists('gDriveOrgFileName', $siteBackupTemp))
					{
						$repo = "G Drive";
					}
										
					$sitesBackups[$siteID][$backupTaskType][] = array('time' => $siteBackupTemp['time'],
																  'type' => 'backupNow',
																  'downloadURL' => $siteBackupTemp['server']['file_url'],
																  'size' => $siteBackupTemp['size'],
																  'what' => $what,
																  'referenceKey' => $referenceKey,
																  'backupName' => $siteBackupTemp['backup_name'],
																  'siteID' => $siteID,
																  'repository' => $repo,
																  'backupTaskType' => $backupTaskType,
																  'data' => array('scheduleKey' => $backupKey));
				}
			}			
		}
		
		return $sitesBackups;
	}
	
	public static function getSitesBackupsHTML(){
		$sitesBackups = self::getSitesBackups();
		$HTML = TPL::get('/templates/backup/view.tpl.php', array('sitesBackups' => $sitesBackups));
		return $HTML;
	}
	
	public static function getSiteBackupsHTML($siteID){
		$sitesBackups = self::getSitesBackups(array($siteID));
		$HTML = TPL::get('/templates/backup/sitePopup.tpl.php', array('siteBackups' => $sitesBackups, 'siteID' => $siteID));
		return $HTML;
	}
	
	public static function siteIsWritable(){
		
		$sitesStats = self::getRawSitesStats();
		foreach($sitesStats as $siteID){
			$siteIsWritable[$siteID['siteID']] = $siteID['stats']['writable'];			
		}
	    return $siteIsWritable;
	}
	
	public static function getSitesUpdates($userID = ''){
		
		$siteView = $pluginView = $themeView = $coreView = array();
		$sitesStats = self::getRawSitesStats(array(), $userID);

		foreach($sitesStats as $siteID){
			
			$siteID['stats']['premium_updates'] = (array)$siteID['stats']['premium_updates'];
			foreach($siteID['stats']['premium_updates'] as $item){			
				$isHiddenItem = false;
				$ignoredUpdates = DB::getField("?:hide_list", "URL", "URL = '".$item['slug']."' AND siteID = '".$siteID['siteID']."'"); 
				
				$pluginView['plugins'][$item['slug']][$siteID['siteID']] = $siteView[$siteID['siteID']]['plugins'][$item['slug']] = array_change_key_case($item, CASE_LOWER);				
				
				if($ignoredUpdates){ 
					$pluginView['plugins'][$item['slug']][$siteID['siteID']]['hiddenItem'] = $siteView[$siteID['siteID']]['plugins'][$item['slug']]['hiddenItem'] = true;
				} 
			}
			
			$siteID['stats']['upgradable_plugins'] = (array)$siteID['stats']['upgradable_plugins'];
			foreach($siteID['stats']['upgradable_plugins'] as $item){			
				$temp = objectToArray($item);
				if(!is_array($temp))
				$temp=array();
				
				$isHiddenItem = false;
				$ignoredUpdates = DB::getField("?:hide_list", "URL", "URL = '".$item->file."' AND siteID = '".$siteID['siteID']."'"); 
				if($ignoredUpdates){ 
					$isHiddenItem = true;
				} 
				$temp['hiddenItem'] = $isHiddenItem;
				
				$pluginView['plugins'][$item->file][$siteID['siteID']] = $siteView[$siteID['siteID']]['plugins'][$item->file] = $temp;
			}
			
			$siteID['stats']['upgradable_themes'] = (array)$siteID['stats']['upgradable_themes'];
			foreach($siteID['stats']['upgradable_themes'] as $item){
				
				$isHiddenItem = false;
				$ignoredUpdates = DB::getField("?:hide_list", "URL", "URL = '".$item['theme_tmp']."' AND siteID = '".$siteID['siteID']."'"); 
				if($ignoredUpdates){ 
					$isHiddenItem = true;
				} 
				$item['hiddenItem'] = $isHiddenItem;
				
				$themeView['themes'][$item['theme_tmp']][$siteID['siteID']] = $siteView[$siteID['siteID']]['themes'][$item['theme_tmp']] = $item;
							
			}
			
			if(!empty($siteID['stats']['core_updates'])){
				
				$item = $siteID['stats']['core_updates'];
				$temp = objectToArray($item);
				if(!is_array($temp))
				$temp=array();
				$isHiddenItem = false;
				$ignoredUpdates = DB::getField("?:hide_list", "URL", "URL = '".$item->current."' AND siteID = '".$siteID['siteID']."'"); 
				if($ignoredUpdates){ 
					$isHiddenItem = true;
				}
				$temp['hiddenItem'] = $isHiddenItem;
				
				$coreView['core'][$item->current][$siteID['siteID']] = $siteView[$siteID['siteID']]['core'][$item->current] = $temp;
			}	
		}

		ksortTree($siteView, 3);
		ksortTree($pluginView, 2);
		ksortTree($themeView, 2);
		ksortTree($coreView, 2);
		
		$siteViewCount = array();//count of plugins, themes, core by site view
		$totalUpdateCount = $allUpdatesCount = 0;
		foreach($siteView as $siteID => $siteValues){
			$siteViewCount[$siteID]['core'] = $siteViewCount[$siteID]['themes'] = $siteViewCount[$siteID]['plugins'] = 0;
			foreach($siteValues as $type => $items){
				foreach($items as $item){
					if(empty($item['hiddenItem'])){						
						$siteViewCount[$siteID][$type]++;
						$totalUpdateCount++;
					}
				}
			}
			
		}
		
		$lastReloadTime = DB::getField("?:site_stats", "lastUpdatedTime", "1 ORDER BY lastUpdatedTime LIMIT 1");
		$lastReloadTime = ($lastReloadTime > 0) ? @date('M d @ h:ia', $lastReloadTime) : '';
		
    	return array('siteView' => $siteView, 'pluginsView' => $pluginView, 'themesView' => $themeView, 'coreView' => $coreView, 'siteViewCount' => $siteViewCount, 'totalUpdateCount' => $totalUpdateCount, 'lastReloadTime' => $lastReloadTime);
	}
	
	public static function getSites(){
		
		$sitesData = array();
		
		if(function_exists('multiUserGetSites')){
			multiUserGetSites($sitesData);
		}
		else{
			$sitesData = DB::getArray("?:sites", "siteID, URL, adminURL, name, IP, adminUsername, isOpenSSLActive, network, parent, httpAuth, callOpt, connectURL, connectionStatus, links, notes", "1 ORDER BY name", "siteID");
		}
		
		$groupsSites = DB::getArray("?:groups_sites", "*", "1");
		if(!empty($groupsSites)){
			foreach($groupsSites as $groupSite){
				if(!empty($sitesData[$groupSite['siteID']])){
					$sitesData[$groupSite['siteID']]['groupIDs'][] = $groupSite['groupID'];
				}
			}
		}
		if(is_array($sitesData)){
			foreach($sitesData as $siteID => $siteData){
				if(!empty($siteData['httpAuth'])){
					$sitesData[$siteID]['httpAuth'] = @unserialize($siteData['httpAuth']);
				}
				if(!empty($siteData['callOpt'])){
					$sitesData[$siteID]['callOpt'] = @unserialize($siteData['callOpt']);
				}if(!empty($siteData['links'])){
					$sitesData[$siteID]['links'] = explode(",",$siteData['links']);
				}
			}
		}
		return $sitesData;
	}
	
	public static function getSitesList(){
		$sitesData = array();
		
		if(function_exists('multiUserSitesList')){
			multiUserSitesList($sitesData);
		}
		else{
			$sitesData = DB::getArray("?:sites", "siteID, URL, adminURL, name, IP, adminUsername, isOpenSSLActive, network, parent", "1 ORDER BY name", "siteID");
		}
		
		if(is_array($sitesData)){
			foreach($sitesData as $k => $v){
				$sitesData['s'.$k] = $v;
				unset($sitesData[$k]);
			}
		}
		return $sitesData;
	}
	
	public static function getSearchedPluginsThemes(){
		
		$actionID = Reg::get('currentRequest.actionID');
		
		$datas = DB::getFields("?:temp_storage", "data", "type = 'getPluginsThemes' AND paramID = '".$actionID."'");
		
		DB::delete("?:temp_storage", "type = 'getPluginsThemes' AND paramID = '".$actionID."'");
		
		if(empty($datas)){
			return array();
		}
		$finalData = array();
		foreach($datas as $data){
			$finalData = array_merge_recursive($finalData, (array)unserialize($data));	
		}
	
		arrayMergeRecursiveNumericKeyHackFix($finalData);		
		ksortTree($finalData);	
		
		//finding not installed for site view only	
		$typeItems = array_keys($finalData['typeView']);		
		foreach($typeItems as $item){
			foreach($finalData['siteView'] as $siteID => $value){
				if(empty($value['active'][$item]) && empty($value['inactive'][$item])){
					$typeViewTemp = reset($finalData['typeView'][$item]);
					$finalData['siteView'][$siteID]['notInstalled'][$item] = reset($typeViewTemp);
				}
			}		
		}
		
		return $finalData;
	}
	
	public static function updateSettings($settings){
		
		
		$updateSettings = array();
		
		if(!empty($settings['general'])){
			if($settings['general']['autoSelectConnectionMethod'] == 1){
				$currentGeneralSettings = Reg::get('settings');
				$settings['general']['executeUsingBrowser'] = $currentGeneralSettings['executeUsingBrowser'];
				if($settings['general']['TIMEZONE'] != ''){
					@date_default_timezone_set( $settings['general']['TIMEZONE']);
				}
				if(!empty($currentGeneralSettings['httpAuth'])){
					$settings['general']['httpAuth'] = $currentGeneralSettings['httpAuth'];
				}
			}
			$updateSettings['general'] = serialize($settings['general']);
		}
		if(!empty($settings['notifications'])){
			DB::update("?:users", array("notifications" => serialize($settings['notifications'])), "userID = '".$GLOBALS['userID']."' ");
		}
		
		if(!empty($updateSettings)){
			$updateSettings['timeUpdated'] = time();
			return DB::update("?:settings", $updateSettings, "1");
		}
	}
	public static function updateSecuritySettings($settings){
		if(isset($settings['allowedLoginIPsCount'])){
			DB::delete("?:allowed_login_ips", "1");
			if(!empty($settings['allowedLoginIPs'])){
				foreach($settings['allowedLoginIPs'] as $IP){
					DB::insert("?:allowed_login_ips", array('IP' => $IP));
				}
			}
		}
		$updateSettings = array();
		$currentGeneralSettings = Reg::get('settings');
		if(!empty($settings['httpAuth'])){		
			$settings['httpAuth']['username'] = trim($settings['httpAuth']['username']);
			$settings['general'] = $currentGeneralSettings;
			$settings['general']['httpAuth'] = $settings['httpAuth'];
			// $updateSettings['general'] = serialize($settings['general']);
		}else{
			$settings['general'] = $currentGeneralSettings;
			unset($settings['general']['httpAuth']);

		}
		$settings['general']['enableHTTPS'] =  $settings['enableHTTPS'];
		$updateSettings['general'] = serialize($settings['general']);


		if(!empty($updateSettings)){
			$updateSettings['timeUpdated'] = time();
			return DB::update("?:settings", $updateSettings, "1");
		}
		
	}
	
	public static function updateSettingsMerge($settings){//currently supports general setting (app settings) alone //changes will be updated, keeping old settings using array merge
		$updateSettings = array();
		if(!empty($settings['general'])){
			$currentGeneralSettings = Reg::get('settings');
			$settings['general'] = array_merge($currentGeneralSettings, $settings['general']);
			$updateSettings['general'] = serialize($settings['general']);
		}
		if(!empty($updateSettings)){
			return DB::update("?:settings", $updateSettings, "1");
		}
		
	}
	
	public static function getSettings(){
		$settings =  array();
		$settings['allowedLoginIPs'] = DB::getFields("?:allowed_login_ips", "IP", "1", "IP");
	
		$settingsRow = DB::getRow("?:settings", "*", "1");
		$settings['general'] = @unserialize($settingsRow['general']);
                
                $userID=$GLOBALS['userID'];
                $settingsRow = DB::getRow("?:users", "notifications", "userID='".$userID."'");
		$settings['notifications'] = @unserialize($settingsRow['notifications']);
		
		return $settings;
	}
	
	public static function getSettingsAll(){
		
		return array('settings' => self::getSettings(),
					 'accountSettings' => self::getAccountSettings($GLOBALS['userID']));
	}
	
	public static function getRecentHistory(){
		$limit = 10;
		$actionIDs = DB::getFields("?:history", "actionID", "showUser='Y' GROUP BY actionID ORDER BY historyID DESC LIMIT ".$limit);	
		if(empty($actionIDs)){ return array(); }
		$actionHistory = array();
		foreach($actionIDs as $actionID){
			$actionHistory[ $actionID ] = self::getActionStatus();
		}
		return $actionHistory;
	}
	
	public static function getActionStatus($actionID, $action=''){
		

		$historyDatas = DB::getArray("?:history", "historyID, siteID, type, userID, action, status, error, microtimeAdded", "actionID = '".$actionID."' ORDER BY historyID ASC", "historyID");		

		if(empty($historyDatas)){ return false;	}
		
		$totalRequest = count($historyDatas);
		$totalNonSuccessRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status != 'completed'");
		$totalPendingRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status IN ('pending','initiated', 'running', 'processingResponse')");
		$totalSuccessRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status = 'completed'");
		
		$totalMultiCallRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status = 'multiCallWaiting'");

		
		if($totalPendingRequest > 0){ $status = 'pending';  }
		elseif($totalMultiCallRequest > 0){ $status = 'multiCallWaiting'; }	 //Modified to get status= 'multiCallWaitig' in processQueue.tpl && view.tpl.
		elseif($totalNonSuccessRequest == 0){ $status = 'success'; }
		elseif($totalNonSuccessRequest < $totalRequest){ $status = 'partial'; }
		elseif($totalNonSuccessRequest == $totalRequest){ $status = 'error'; }
		
		$historyStatusSummary = array('total' => $totalRequest,
									  'pending' => $totalPendingRequest,
									  'nonSuccess' => $totalNonSuccessRequest,
									  'success' => $totalSuccessRequest);
	
		$historyData = reset($historyDatas);
		$type = $historyData['type'];//getting type from first history only, assuming type is common for one actionID
		$action = $historyData['action'];
		$time = $historyData['microtimeAdded'];//getting time from first history ordered by historyID ASC
		$actionSitesCount = count($actionHistory);
		$userID = $historyData['userID']; //who creaed this history.
		
		$historyIDs = array_keys($historyDatas);
		

		$historyAdditionalDatas = DB::getArray("?:history_additional_data HAD, ?:history H", "HAD.*, H.siteID, H.URL, H.microtimeInitiated", "H.actionID = '".$actionID."' AND HAD.historyID = H.historyID");		
		if(empty($historyAdditionalDatas)){ return false; }
		
		$historyAdditionalDatasStatusArray = DB::getFields("?:history_additional_data HAD, ?:history H", "count(HAD.historyID), HAD.status", "H.actionID = '".$actionID."' AND HAD.historyID = H.historyID GROUP BY status", "status");		
		if(empty($historyAdditionalDatasStatusArray)){
			$historyAdditionalDatasStatusArray = array();
		}
		$historyAdditionalDatasStatusArray['total'] = count($historyAdditionalDatas);
		
		$detailedActions = DB::getArray("?:history_additional_data HAD, ?:history H", "count(DISTINCT HAD.historyID) as sitesCount, count(DISTINCT HAD.uniqueName) as detailedActionCount,  HAD.detailedAction, HAD.uniqueName", "H.actionID = '".$actionID."' AND HAD.historyID = H.historyID GROUP BY detailedAction ","detailedAction");
		
		
		if($status == 'success'){//up to this line status only check connection is done successfully after this we will check task completed or not
			if(empty($historyAdditionalDatasStatusArray['success'])){
				$status = 'error';
			}
			elseif($historyAdditionalDatasStatusArray['total'] > $historyAdditionalDatasStatusArray['success']){
				$status = 'partial';
			}
		}
			
		$actionResult = array(
						'userID' => $userID,
						'status' => $status,
						'statusMsg' => $status,
						'actionID' => $actionID,
						'historyID' => $historyID,
						'statusSummary' => $historyAdditionalDatasStatusArray,
						'historyStatusSummary' => $historyStatusSummary,
						'detailedStatus' => $historyAdditionalDatas,
						'detailedActions' => $detailedActions, 
						'type' => $type,
						'action' => $action,
						'time' => (int)$time,
						'actionSitesCount' => $actionSitesCount,
						'errors' => $errors,
						);
		
		return $actionResult;
	}
	
	public static function getWaitData($params=array()){
        $waitActions= manageCookies::cookieGet('waitActions');
        if(empty($waitActions)) {
            $waitActions = array();
        }
		if(!empty($params)){
			foreach($params as $actionID => $value){
				if($value == 'sendData'){
					$waitActions[$actionID]['sendData'] = true;
				}
			}
            manageCookies::cookieSet('waitActions',$waitActions,array('expire'=>0));
			return true;
		}
		
			
        if(count($waitActions)==0) return false;
		$result = array();
		
		foreach($waitActions as $actionID => $waitAction){
			$sendData = false;
			$result[$actionID] = array();
			
			if( !empty($waitAction['sendData']) || ($waitAction['timeInitiated'] > 0 && $waitAction['timeInitiated'] < (time() - (5 *60))) ){
				$sendData = true;
			}
			
			$totalRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."'");
			$totalPendingRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status IN ('pending', 'running', 'initiated', 'processingResponse')");
			$totalSuccessRequest = DB::getField("?:history", "count(status)", "actionID = '".$actionID."' AND status = 'completed'");
			
			$result[$actionID]['total'] = $totalRequest;
			$result[$actionID]['loaded'] = $totalSuccessRequest;
			
			
			if($totalPendingRequest == 0) $sendData = true;
			
			if($sendData){
				$currentActionID = Reg::get('currentRequest.actionID');
				Reg::set('currentRequest.actionID', $actionID);
				$result[$actionID]['requiredData'] = $waitActions[$actionID]['requiredData'];			
				$result[$actionID]['data'] = self::requiredData($result[$actionID]['requiredData']);
				$result[$actionID]['actionResult'] = self::getActionStatus($actionID);
				Reg::set('currentRequest.actionID', $currentActionID);
			}
			
			if($sendData || $waitAction['timeExpiresFromSession'] < time()){
				unset($waitActions[$actionID]);
			}			
		}
		manageCookies::cookieSet('waitActions',$waitActions,array('expire'=>0));
		return $result;
		
	}	
	
	public static function getHistoryPageHTML($args){
		$itemsPerPage = 20;		
		$page = (isset($args['page']) && !empty($args['page'])) ? $args['page'] : 1;
		$where = "showUser='Y'";
		if(!empty($args['dates'])){
			$dates 		= explode('-', $args['dates']);
			$fromDate 	= strtotime(trim($dates[0]));
			$toDate		= strtotime(trim($dates[1]));
			if(!empty($fromDate) && !empty($toDate) && $fromDate != -1 && $toDate != -1){
				$toDate += 86399;
				$where .= " AND microtimeAdded >= ".$fromDate." AND  microtimeAdded <= ".$toDate." ";
			}
		}
		
		if(!empty($args['userID'])){
			$where .= " AND userID = '".$args['userID']."' ";
		}
		$where2 = " ";
		setHook('historyHTML', $where2);

		$total = DB::getField("?:history", "SQL_CALC_FOUND_ROWS actionID", $where2.$where. " GROUP BY actionID");
		$total = DB::getField("SELECT FOUND_ROWS()");

		$limitSQL = paginate($page, $total, $itemsPerPage);
		
		$actionIDs = DB::getFields("?:history", "actionID", $where2.$where. " GROUP BY actionID ORDER BY historyID DESC ".$limitSQL);
		
		if(!empty($actionIDs)){ 
			$actionsHistoryData = array();
			foreach($actionIDs as $actionID){
				$actionsHistoryData[ $actionID ] = self::getActionStatus($actionID);
			}
		}
		
		$HTML = TPL::get('/templates/history/view.tpl.php', array('actionsHistoryData' => $actionsHistoryData));
		
		return $HTML;
	}
	
	public static function getHistoryPanelHTML(){
		$itemsPerPage = 10;
		
		$where = '';
		
		setHook('historyHTML', $where);
		
		$actionIDs = DB::getFields("?:history", "actionID", $where. " showUser='Y' GROUP BY actionID ORDER BY historyID DESC LIMIT ".$itemsPerPage);
		
		if(empty($actionIDs)){ $actionIDs = array(); }
		$actionsHistoryData = array();
		$showInProgress = false;
		foreach($actionIDs as $actionID){
			$actionsHistoryData[ $actionID ] = self::getActionStatus($actionID);
			if(($actionsHistoryData[ $actionID ]['status'] == 'pending')||($actionsHistoryData[ $actionID ]['status'] == 'multiCallWaiting')){ $showInProgress = true; }
		}		
		$HTML = TPL::get('/templates/history/processQueue.tpl.php', array('actionsHistoryData' => $actionsHistoryData, 'showInProgress' => $showInProgress));
		
		return $HTML;
	}
	
	public static function addHide($params){
		
		if(empty($params)){
			 return false; 
		}
		foreach($params as $siteID => $value){			
			DB::insert("?:hide_list", array('type' => $value['type'], 'siteID' => $siteID, 'name' => $value['name'], 'URL' => $value['path']));			
		}
	}
	
	public static function getHide(){
	
		$getHide = DB::getArray("?:hide_list", "*", "1");
		$hide = array();
		foreach($getHide as $v){
			$hide[$v["siteID"]][] = array('type' => $v["type"], 'name' => $v["name"], 'URL' => $v["URL"]);	
		}
		return $hide;
	}
	
	public static function removeHide($params){
		
		if(empty($params)){
			 return false; 
		}		
		foreach($params as $siteID => $value){
			$isDone = DB::delete("?:hide_list","type = '".$value['type']."' AND siteID = '".$siteID."' AND URL  = '".$value['path']."' ");
		}
		return $isDone;
	}
	
	public static function addFavourites($params){
		if(empty($params)){
			 return false; 
		}

		return DB::insert("?:favourites", array('type' => $params['type'], 'name' => $params['name'], 'URL' => $params['URL'], 'slug' => $params['slug']));
	}
	
	public static function getFavourites(){
		
		$getFavourites = DB::getArray("?:favourites", "*", 1);
		$favourites = array();
		foreach($getFavourites as $v){
			$favourites[$v["type"]][] = array('name' => $v["name"], 'URL' => $v["URL"], 'slug' => $v["slug"]);			
		}
		return $favourites;
	}
	
	public static function removeFavourites($params){
		return DB::delete("?:favourites","type = '".$params['type']."' AND URL  = '".$params['URL']."' ");
	}
	
	public static function updateAccountSettings($params){
		$userData = array();
		$userID = $GLOBALS['userID'];
		$where = "userID = ".$userID;
		
		if( !empty($params['currentPassword']) && !empty($params['newPassword']) ){
			
			$where .= " && password='".sha1($params['currentPassword'])."'";
			$isPasswordCorrect = DB::getExists("?:users", "userID", $where);
			if(!$isPasswordCorrect){
				return array('status' => 'error', 'error' => 'invalid_password', 'errorArray' => array('currentPassword' => 'invalid'));
			}
			
			$userData['password'] = sha1($params['newPassword']);
		}
		if( !empty($params['email']) ){
			$userData['email'] = $params['email'];
		}
		if(empty($userData)){
			return array('status' => 'error', 'error' => 'empty', 'errorArray' => array('currentPassword' => 'invalid', 'email' => 'invalid'));
		}
		
		$isUpdated = DB::update("?:users", $userData, $where);
		if($isUpdated){
			return array('status' => 'success', 'error' => '');	
		}
		return array('status' => 'error', 'error' => 'db_error');
	}
	
	public static function getAccountSettings($userID){
		return DB::getRow("?:users", "email", "userID='".$userID."'");
	}

	public static function getWPRepositoryHTML($params){
		
		$searchVar = $params['searchVar'];
		$searchItem = $params['searchItem'];
		$type = $params['type'];
		if($type =='plugins')
		{
			$action='query_plugins';
			$URL= 'http://api.wordpress.org/plugins/info/1.0/';
		}
		if($type=='themes')
		{
			$action='query_themes';
			$URL= 'http://api.wordpress.org/themes/info/1.0/';
		}
		$args = (object)$args;
		//$args->search= 'WP ecommerce';
		if($searchVar==1)
		$args->search=$searchItem;
		else
		$args->browse=$searchItem;
		$args->per_page=30;
		$args->page=1;
		$args->fields['downloadlink'] = true;
		$Array['action']=$action;
		$Array['request']=serialize($args);
		
	
		$return = unserialize(repoDoCall($URL,$Array));
		
		$return=$return->$params['type'];
		foreach($return as $item)
		{
			//Limit description to 400char, and remove any HTML.
			$description = strip_tags( $item->description);
			if ( strlen( $description ) > 400 )
			{
				if(function_exists('mb_substr'))
					$description = mb_substr( $description, 0, 400 ) . '&#8230;';
				else
					$description = substr( $description, 0, 400 ) . '&#8230;';
			}
			//remove any trailing entities
			$description = preg_replace( '/&[^;\s]{0,6}$/', '', $description );
			//strip leading/trailing & multiple consecutive lines
			$description = trim( $description );
			$description = preg_replace( "|(\r?\n)+|", "\n", $description );
			//\n => <br>
			$description = nl2br( $description );	
			$existFav = DB::getField("?:favourites", "count(ID)", "type = '".$type."' AND name = '".$item->name."'");
						
			if($type=='plugins')
			{
				$content = $content.'<div class="tr"><div class="name">'.$item->name.'<div class="wp_repository_search_results_actions"><a class="installItem multiple" dlink='.$item->download_link.' plugin_themes_slug="'.$item->slug.'">Install</a>';
				$content = $content.'<a href="http://wordpress.org/plugins/'.$item->slug.'/" target="_blank">Details</a>';
				if($existFav == 1){
				$content = $content.'<a class="addToFavorites disabled" >Favourite</a>'; 
				}
				else 
				$content = $content.'<a class="addToFavorites" utype="'.$type.'" iname="'.$item->name.'" islug="'.$item->slug.'" dlink="'.$item->download_link.'" >Add to Favorites</a>';  
				$content = $content.'</div></div> <div class="version">'.$item->version.'</div> <a class="rating" title="(based on '.$item->num_ratings.' ratings)"><div class="rating_fill" style="width:'.$item->rating.'%;"></div><div class="stars"></div></a>   <div class="descr">'.$description.'</div>
                  <div class="clear-both"></div>
                </div>';
			}
			else
			{
				$content=$content.'<div class="theme_column"> <div class="thumb" preview="'.$item->preview_url.'"><div class="icon_preview rep_sprite_backup">Preview</div><div class="btn_preview"></div><img src="'.$item->screenshot_url.'"  /></div><div class="theme_name droid700">'.$item->name.'</div>
                <div class="wp_repository_search_results_actions"><a class="installItem multiple" dlink='.$item->download_link.'  plugin_themes_slug="'.$item->slug.'">Install</a>';
				
			$content=$content.'<a href="http://wordpress.org/themes/'.$item->slug.'/" target="_blank">Details</a>';
			  if($existFav == 1){
				$content = $content.'<a class="addToFavorites disabled" >Favourite</a>'; 
				}
	 			else
			$content=$content.'	<a class="addToFavorites" utype="'.$type.'" iname="'.$item->name.'" islug="'.$item->slug.'" dlink="http://wordpress.org/themes/download/'.$item->slug.'.'.$item->version.'.zip" >Add to Favorites</a>';
			$content = $content.'</div>
                <div class="clear-both"></div>
                <div class="theme_descr">'.$description.'</div>
              </div>';
			
			}
		}
		return utf8_encode($content);
	}
	
	public static function getFavDownloadLinks($params){
		$linksArray = array();
		foreach($params['searchItem'] as $key => $value)
		{
		
		    //$searchVar = $params['searchVar'];
			
			//$is_url = parse_URL($searchItem);
		
			if(!empty($value['slug']))
			{
				$searchItem = $value['slug'];
				$type = $params['type'];
				if($type =='plugins')
				{
					$action='plugin_information';
					$URL= 'http://api.wordpress.org/plugins/info/1.0/';
				}
				if($type=='themes')
				{
					$action='theme_information';
					$URL= 'http://api.wordpress.org/themes/info/1.0/';
				}
				$args = (object)$args;
				//$args->search= 'WP ecommerce';
				
				$args->slug=$searchItem;
				//$args->page=1;
				$args->fields['downloadlink'] = true;
				$Array['action']=$action;
				$Array['request']=serialize($args);
				
			
				$return = unserialize(repoDoCall($URL,$Array));
				if(!empty($return->download_link))
				{
					$linksArray[$key] = $return->download_link;
				}
				else{
					$linksArray[$key] = $value['downloadLink'];
				}				
			}
			else
			{//this $value should be URL
				$linksArray[$key] = $value['downloadLink'];
			}
			//$return=$return->$params['type'];
		}
		return $linksArray;
	}
	
	public static function installNotInstalledPlugin($params)
	{
		$plugin_slug = $params['plugin_slug'];
		$siteID = $params['siteID'];
		//$searchItem = $params['searchItem'];
		$type = $params['type'];
		if($type =='plugins')
		{
			$action='plugin_information';
			$URL= 'http://api.wordpress.org/plugins/info/1.0/';
		}
		if($type=='themes')
		{
			$action='theme_information';
			$URL= 'http://api.wordpress.org/themes/info/1.0/';
		}
		$args = (object)$args;
		//$args->search= 'WP ecommerce';
		/* if($searchVar==1)
		$args->search=$searchItem;
		else
		$args->browse=$searchItem;
		$args->per_page=30;
		$args->page=1; */
		$args -> slug = $plugin_slug;
		$args->fields['downloadlink'] = true;
		$Array['action']=$action;
		$Array['request']=serialize($args);
		

		$link = unserialize(repoDoCall($URL,$Array));
		$return = array($link,$siteID);
		//$return=$return->$params['type'];
		
		return $return;
		
	}
	
	
	
	public static function getUserHelp(){
		$help = DB::getField("?:users", "help", "userID='".$GLOBALS['userID']."'");
		if(empty($help)){
			return array();	
		}
		return (array)unserialize($help);
	}
	
	public static function updateUserHelp($params){
		$oldHelp = self::getUserHelp();
		$params = array_merge($oldHelp, (array)$params);
		$help = DB::update("?:users", array('help' => serialize($params)), "userID='".$GLOBALS['userID']."'");
		return $help;
	}
	
	public static function getReportIssueData($actionID){
		$issue = getReportIssueData($actionID);
		$issue['report'] = serialize($issue['report']);
		return $issue;
	}
	
	public static function updatesNotificationMailTest(){
		return updatesNotificationMailSend(true);	
	}
	
	public static function getClientUpdateAvailableSiteIDs(){
		
		$rawSiteStats = self::getRawSitesStats(array(), $GLOBALS['userID']);

		if(function_exists('multiUserGetSites')){
                    $userAccess=DB::getRow("?:users", "permissions,accessLevel", "userID='".$GLOBALS['userID']."'");
                    if(($userAccess['accessLevel']!='admin')&&!empty($userAccess['permissions']))
                    {
                        $permissions=  unserialize($userAccess['permissions']);
                        if(!in_array('updates', $permissions['access']))
                        {
                            return FALSE;
                        }
                    }
		}
                manageCookies::cookieUnset('clientUpdates');
		foreach($rawSiteStats as $siteID => $statsArray){
               
			$stats = $statsArray['stats'];

			//check iwp-client plugin have any updates
			if( !empty($stats['client_new_version']) || version_compare($stats['client_version'], '0.1.4') != 1 ){
				if(!isset($clientUpdates)){
					$clientUpdates = array();
				}				
				
				if( !empty($stats['client_new_version']) && version_compare($stats['client_version'], $stats['client_new_version']) == -1 ){//fixed repeated Client update popup
					if(!isset($clientUpdates['clientUpdateVersion']) || version_compare($clientUpdates['clientUpdateVersion'], $stats['client_new_version']) == -1){
						$clientUpdates['clientUpdateVersion'] = $stats['client_new_version'];
						$clientUpdates['clientUpdatePackage'] = $stats['client_new_package'];
					}
				}
				elseif( version_compare($stats['client_version'], '0.1.4') != 1 ){
					$clientUpdates['clientUpdateVersion'] = '1.0.0';
					$clientUpdates['clientUpdatePackage'] = 'http://downloads.wordpress.org/plugin/iwp-client.zip';
				}
			}
		}
		
		$clientPluginBetaUpdate = getOption('clientPluginBetaUpdate');
		if(!empty($clientPluginBetaUpdate)){
			$clientPluginBetaUpdate = unserialize($clientPluginBetaUpdate);
			if(!empty($clientPluginBetaUpdate['version']) && !empty($clientPluginBetaUpdate['downloadURL'])){
				if(empty($clientUpdates['clientUpdateVersion']) || (!empty($clientUpdates['clientUpdateVersion']) && version_compare($clientUpdates['clientUpdateVersion'], $clientPluginBetaUpdate['version']) == -1)){
					$clientUpdates['clientUpdateVersion'] = $clientPluginBetaUpdate['version'];
					$clientUpdates['clientUpdatePackage'] = $clientPluginBetaUpdate['downloadURL'];			
				}
			}
		}
		
		if(empty($clientUpdates['clientUpdateVersion'])){
            manageCookies::cookieSet('clientUpdates',$clientUpdates,array('expire'=>0));
			return false;
		}
		
		$clientUpdates['siteIDs'] = array();
		foreach($rawSiteStats as $siteID => $statsArray){			
			$stats = $statsArray['stats'];
			//check iwp-client plugin have any updates
			if(version_compare($stats['client_version'], $clientUpdates['clientUpdateVersion']) == -1  && DB::getExists("?:sites", "siteID", "siteID = '".$siteID."' AND (network = 0 OR (network = 1 AND parent = 1)) ")){
				$clientUpdates['siteIDs'][] = $siteID;
			}
		}
		manageCookies::cookieSet('clientUpdates',$clientUpdates,array('expire'=>0));
		//return $clientUpdates['siteIDs'];
		return (!empty($clientUpdates['siteIDs']) ? $clientUpdates : false);
		
	}
	
	public static function generalCheck(&$finalResponse){
		
		if($updateAvailable = checkUpdate()){
			if( getOption('updateHideNotify') != $updateAvailable['newVersion'] && getOption('updateNotifySentToJS') != $updateAvailable['newVersion'] ){
				$finalResponse['updateAvailable'] = $updateAvailable;
				updateOption('updateNotifySentToJS', $updateAvailable['newVersion']);
			}
		}

		$notifications = getNotifications(true);
		if(!empty($notifications)){
			$finalResponse['notifications'] = $notifications;
		}
		
		$waitData = self::getWaitData();
		if(!empty($waitData)){
			$finalResponse['data']['getWaitData'] = $waitData;
		}
		
		$alertCount = getAddonAlertCount();
		//$cookieAlertCount = manageCookies::cookieGet('addonAlertCount');
                $cookieAlertCount = getOption('addonAlertCount');
		if($cookieAlertCount !== $alertCount){
			//manageCookies::cookieSet('addonAlertCount',$alertCount,array('expire'=>0));
                        updateOption('addonAlertCount',  $alertCount);
			$finalResponse['addonAlertCount'] = $alertCount;
		}
		
	}
	
	public static function updateHideNotify($version){//IWP update
		return updateOption('updateHideNotify', $version);
	}
	
	public static function isUpdateHideNotify(){
		$updateAvailable = checkUpdate(false, false);
		if(!empty($updateAvailable)){
			if($updateAvailable['newVersion'] == getOption('updateHideNotify')){
				return true;	
			}
		}
		return false;
	}
	
	public static function forceCheckUpdate(){
		return checkUpdate(true);
	}
	
	public static function sendReportIssue($params){
		return sendReportIssue($params);
	}
	
	public static function getResponseMoreInfo($historyID){
		return getResponseMoreInfo($historyID);
	}
	
	public static function updateSite($params){
		
		if(empty($params['siteID'])){ return false; }
		
		$siteData = array( "adminURL" 		=> $params['adminURL'],
						   "adminUsername"	=> $params['adminUsername'],
						   "URL"			=> $params['URL'],
						   "connectURL"		=> $params['connectURL'],
						  ); // save data
						  
		if(!empty($params['httpAuth']['username'])){
			  $siteData['httpAuth'] = serialize($params['httpAuth']);
		}
		else{
			$siteData['httpAuth'] = '';
		}
		
		if(!empty($params['callOpt'])){
			$siteData['callOpt'] = serialize($params['callOpt']);
		}
		else{
			$siteData['callOpt'] = '';
		}
	  
		$isDone = DB::update('?:sites', $siteData, "siteID = '".$params['siteID']."'"); 
		
		if($isDone){
			panelRequestManager::addSiteSetGroups($params['siteID'], $params['groupsPlainText'], $params['groupIDs']);	
		}
		return $isDone;
	}	
	public static function repositoryTestConnection($params){
		return repositoryTestConnection($params);
	}
	public static function getAddonsPageHTML(){
		
		$data = array();
		$data['installedAddons'] = getInstalledAddons(true);
		$data['newAddons'] = getNewAddonsAvailable();
		$data['promoAddons'] = getPromoAddons();
		$data['promos'] = getOption('promos');
		$data['isAppRegistered'] = isAppRegistered();
		

		$HTML = TPL::get('/templates/addons/view.tpl.php', $data);
		return $HTML;
	}
	
	public static function activateAddons($params){
		return activateAddons($params['addons']);
	}
	
	public static function deactivateAddons($params){
		return deactivateAddons($params['addons']);
	}
	
	public static function IWPAuthUser($params){
		
		$serviceURL = getOption('serviceURL');
		$registerURL = str_replace(array('service.', 'dev_service/', 'http://'), array('', '', 'https://'), $serviceURL);// to bring http://infinitewp.com/
		$registerURL .= 'app-login/';
		
		$data = array('appInstallHash' => APP_INSTALL_HASH,
					  'installedHash' => getInstalledHash());

		$params['appDetails'] = base64_encode(serialize($data));
		
		list($rawResponseData, , , $curlInfo)  = doCall($registerURL, $params, $timeout=60, array('normalPost' => 1));
		if($curlInfo['info']['http_code'] != 200 || !empty($curlInfo['errorNo'])){
			
		}
		return json_decode($rawResponseData);
	}
	
	public static function runOffBrowserLoad($params){
		
		if(Reg::get('settings.executeUsingBrowser') != 1){//using fsock
			callURLAsync(APP_URL.EXECUTE_FILE, array('runOffBrowserLoad' => 'true'));
		}
		elseif(Reg::get('settings.executeUsingBrowser') == 1){
			Reg::set('currentRequest.runOffBrowserLoad', 'true');
		}
	}
	
	public static function getSendNextAjaxCallAfter(){
		$time = time();
		$isTaskActive = DB::getExists("?:history H", "H.historyID", "(H.status IN('writingRequest','pending','multiCallWaiting','initiated','running','processingResponse') OR (H.status = 'scheduled' AND H.timescheduled <= ".($time - 120)." AND H.timescheduled > 0)) LIMIT 1");
        $slowDownAjaxCallFrom = manageCookies::cookieGet('slowDownAjaxCallFrom');
		
		if($isTaskActive){
			manageCookies::cookieUnset('slowDownAjaxCallFrom');
			return 0;
		}
		elseif(!empty($slowDownAjaxCallFrom)) {
			if($slowDownAjaxCallFrom['sec60'] < $time){
				return 60;
			}
			elseif($slowDownAjaxCallFrom['sec30'] < $time){
				return 30;
			}
			elseif($slowDownAjaxCallFrom['sec10'] < $time){
				return 10;
			}
			
		}
		else{
            $slowDownAjaxCallFrom = array();
			$slowDownAjaxCallFrom['sec10'] = $time + 12;//two calls of 10 sec
			$slowDownAjaxCallFrom['sec30'] = $time + 35;//two calls of 30 sec
			$slowDownAjaxCallFrom['sec60'] = $time + 105;//from there 60 sec each call
            manageCookies::cookieSet('slowDownAjaxCallFrom',$slowDownAjaxCallFrom,array('expire'=>0));
			return 0;
		}
		
		return 0;//safe		
	}
	
	public static function getSystemCronRunningFrequency(){
		return getSystemCronRunningFrequency();
	}
	
	public static function terminateBackupMulticallProcess($params){
		
		if(!empty($params['actionID'])){
			$historyDatas = DB::getArray("?:history", "historyID, siteID", "actionID = '".$params['actionID']."' AND status = 'multiCallWaiting'"); 
			
			if(!empty($historyDatas) && is_array($historyDatas)){
				foreach($historyDatas as $key => $historyData){
					updateHistory(array("status" => "error", "error" => "task_killed"), $historyData['historyID'], array("status" => "error", "errorMsg" => "Task killed by user"));
					$allParams = array('action' => 'removeBackup', 'args' => array('params' => array('resultID' => $historyData['historyID']), 'siteIDs' => array($historyData['siteID']), 'extras' => array('sendAfterAllLoad' => false, 'doNotShowUser' => true, 'runCondition' => true)));
					panelRequestManager::handler($allParams);
				}
			}
		}
	}
	
	public static function fetchRecentPluginsStatus(){
		$plugins_status = array();
		$sitesStats = panelRequestManager::getRawSitesStats();
		foreach ($sitesStats as $siteID => $data) {
			$plugins_status[$siteID] = $data['stats']['plugins_status'];
		}
		return $plugins_status;
	}
	
	public static function fetchRecentThemesStatus(){
		$themes_status = array();
		$sitesStats = panelRequestManager::getRawSitesStats();
		foreach ($sitesStats as $siteID => $data) {
			$themes_status[$siteID] = $data['stats']['themes_status'];
		}
		return $themes_status;
	}
        
        public static function getRecentPluginsStatus(){
		$plugins_status = array();
		$sitesStats = panelRequestManager::getRawSitesStats();
		foreach ($sitesStats as $siteID => $data) {
			$plugins_status[$siteID] = $data['stats']['plugins_status'];
		}
		return $plugins_status;
	}
	
	public static function getRecentThemesStatus(){
		$themes_status = array();
		$sitesStats = panelRequestManager::getRawSitesStats();
		foreach ($sitesStats as $siteID => $data) {
			$themes_status[$siteID] = $data['stats']['themes_status'];
		}
		return $themes_status;
	}
	
	public static function pluginInintializationReload($params){
		addNotification($type='N', $title='Installations & Activations started', $message='You can start using the addon after completion of installation & activation of the plugin and processing '.$params.' for the first time.', $state='U', $callbackOnClose='', $callbackReference='');
	}

	public static function googleServicesSaveAPIKeys($params)
	{
		if(!empty($params)){
			if ( updateOption('googleAPIKeys', serialize(array('clientID' => $params['clientID'], 'clientSecretKey' => $params['clientSecretKey']))) ){
				return array('clientID' => $params['clientID'], 'clientSecretKey' => $params['clientSecretKey']);
			}else{
				return false;
			}
		}
	}
	public static function googleServicesGetAPIKeys(){
		$googleServicesAPIKeys = getOption('googleAPIKeys');
		if(!empty($googleServicesAPIKeys)){
			$googleServicesAPIKeys = unserialize($googleServicesAPIKeys);
			return $googleServicesAPIKeys;
		}
	}

	public static function setFTPValues($params){
		updateOption('FTPCredentials', serialize($params));
		return $params;
	}
	public static function getFTPValues(){
		$FTPCreds = @unserialize(getOption('FTPCredentials'));
		return $FTPCreds;
	}
	
	private function generate_timezone_list(){
            $timezone_list = array('Pacific/Midway'=>'(UTC-11:00) Pacific/Midway','Pacific/Niue'=>'(UTC-11:00) Pacific/Niue','Pacific/Pago_Pago'=>'(UTC-11:00) Pacific/Pago_Pago','Pacific/Johnston'=>'(UTC-10:00) Pacific/Johnston','Pacific/Honolulu'=>'(UTC-10:00) Pacific/Honolulu','Pacific/Rarotonga'=>'(UTC-10:00) Pacific/Rarotonga','Pacific/Tahiti'=>'(UTC-10:00) Pacific/Tahiti','Pacific/Marquesas'=>'(UTC-09:30) Pacific/Marquesas','Pacific/Gambier'=>'(UTC-09:00) Pacific/Gambier','America/Adak'=>'(UTC-09:00) America/Adak','America/Metlakatla'=>'(UTC-08:00) America/Metlakatla','America/Juneau'=>'(UTC-08:00) America/Juneau','Pacific/Pitcairn'=>'(UTC-08:00) Pacific/Pitcairn','America/Sitka'=>'(UTC-08:00) America/Sitka','America/Anchorage'=>'(UTC-08:00) America/Anchorage','America/Nome'=>'(UTC-08:00) America/Nome','America/Yakutat'=>'(UTC-08:00) America/Yakutat','America/Santa_Isabel'=>'(UTC-07:00) America/Santa_Isabel','America/Hermosillo'=>'(UTC-07:00) America/Hermosillo','America/Phoenix'=>'(UTC-07:00) America/Phoenix','America/Dawson_Creek'=>'(UTC-07:00) America/Dawson_Creek','America/Creston'=>'(UTC-07:00) America/Creston','America/Dawson'=>'(UTC-07:00) America/Dawson','America/Whitehorse'=>'(UTC-07:00) America/Whitehorse','America/Los_Angeles'=>'(UTC-07:00) America/Los_Angeles','America/Vancouver'=>'(UTC-07:00) America/Vancouver','America/Tijuana'=>'(UTC-07:00) America/Tijuana','America/Denver'=>'(UTC-06:00) America/Denver','America/Belize'=>'(UTC-06:00) America/Belize','America/Regina'=>'(UTC-06:00) America/Regina','Pacific/Galapagos'=>'(UTC-06:00) Pacific/Galapagos','America/Edmonton'=>'(UTC-06:00) America/Edmonton','America/Guatemala'=>'(UTC-06:00) America/Guatemala','America/Ojinaga'=>'(UTC-06:00) America/Ojinaga','America/Mazatlan'=>'(UTC-06:00) America/Mazatlan','America/El_Salvador'=>'(UTC-06:00) America/El_Salvador','America/Managua'=>'(UTC-06:00) America/Managua','America/Inuvik'=>'(UTC-06:00) America/Inuvik','Pacific/Easter'=>'(UTC-06:00) Pacific/Easter','America/Swift_Current'=>'(UTC-06:00) America/Swift_Current','America/Yellowknife'=>'(UTC-06:00) America/Yellowknife','America/Chihuahua'=>'(UTC-06:00) America/Chihuahua','America/Cambridge_Bay'=>'(UTC-06:00) America/Cambridge_Bay','America/Costa_Rica'=>'(UTC-06:00) America/Costa_Rica','America/Boise'=>'(UTC-06:00) America/Boise','America/Tegucigalpa'=>'(UTC-06:00) America/Tegucigalpa','America/Eirunepe'=>'(UTC-05:00) America/Eirunepe','America/Menominee'=>'(UTC-05:00) America/Menominee','America/Jamaica'=>'(UTC-05:00) America/Jamaica','America/North_Dakota/Beulah'=>'(UTC-05:00) America/North_Dakota/Beulah','America/Chicago'=>'(UTC-05:00) America/Chicago','America/Cancun'=>'(UTC-05:00) America/Cancun','America/Merida'=>'(UTC-05:00) America/Merida','America/Mexico_City'=>'(UTC-05:00) America/Mexico_City','America/North_Dakota/Center'=>'(UTC-05:00) America/North_Dakota/Center','America/Bahia_Banderas'=>'(UTC-05:00) America/Bahia_Banderas','America/Atikokan'=>'(UTC-05:00) America/Atikokan','America/Cayman'=>'(UTC-05:00) America/Cayman','America/Bogota'=>'(UTC-05:00) America/Bogota','America/Monterrey'=>'(UTC-05:00) America/Monterrey','America/Panama'=>'(UTC-05:00) America/Panama','America/Rio_Branco'=>'(UTC-05:00) America/Rio_Branco','America/Resolute'=>'(UTC-05:00) America/Resolute','America/North_Dakota/New_Salem'=>'(UTC-05:00) America/North_Dakota/New_Salem','America/Lima'=>'(UTC-05:00) America/Lima','America/Indiana/Knox'=>'(UTC-05:00) America/Indiana/Knox','America/Winnipeg'=>'(UTC-05:00) America/Winnipeg','America/Indiana/Tell_City'=>'(UTC-05:00) America/Indiana/Tell_City','America/Rainy_River'=>'(UTC-05:00) America/Rainy_River','America/Rankin_Inlet'=>'(UTC-05:00) America/Rankin_Inlet','America/Matamoros'=>'(UTC-05:00) America/Matamoros','America/Guayaquil'=>'(UTC-05:00) America/Guayaquil','America/Caracas'=>'(UTC-04:30) America/Caracas','America/Curacao'=>'(UTC-04:00) America/Curacao','America/Indiana/Petersburg'=>'(UTC-04:00) America/Indiana/Petersburg','America/Indiana/Marengo'=>'(UTC-04:00) America/Indiana/Marengo','America/Indiana/Vevay'=>'(UTC-04:00) America/Indiana/Vevay','America/Iqaluit'=>'(UTC-04:00) America/Iqaluit','America/Indiana/Winamac'=>'(UTC-04:00) America/Indiana/Winamac','America/Indiana/Vincennes'=>'(UTC-04:00) America/Indiana/Vincennes','America/Martinique'=>'(UTC-04:00) America/Martinique','America/Indiana/Indianapolis'=>'(UTC-04:00) America/Indiana/Indianapolis','America/Marigot'=>'(UTC-04:00) America/Marigot','America/Detroit'=>'(UTC-04:00) America/Detroit','America/Guyana'=>'(UTC-04:00) America/Guyana','America/Guadeloupe'=>'(UTC-04:00) America/Guadeloupe','America/Havana'=>'(UTC-04:00) America/Havana','America/Grand_Turk'=>'(UTC-04:00) America/Grand_Turk','America/Cuiaba'=>'(UTC-04:00) America/Cuiaba','America/Grenada'=>'(UTC-04:00) America/Grenada','America/Dominica'=>'(UTC-04:00) America/Dominica','America/Asuncion'=>'(UTC-04:00) America/Asuncion','America/Kralendijk'=>'(UTC-04:00) America/Kralendijk','America/Santiago'=>'(UTC-04:00) America/Santiago','America/Santo_Domingo'=>'(UTC-04:00) America/Santo_Domingo','America/Kentucky/Monticello'=>'(UTC-04:00) America/Kentucky/Monticello','America/Puerto_Rico'=>'(UTC-04:00) America/Puerto_Rico','America/Port_of_Spain'=>'(UTC-04:00) America/Port_of_Spain','America/Porto_Velho'=>'(UTC-04:00) America/Porto_Velho','America/La_Paz'=>'(UTC-04:00) America/La_Paz','America/St_Barthelemy'=>'(UTC-04:00) America/St_Barthelemy','America/Thunder_Bay'=>'(UTC-04:00) America/Thunder_Bay','America/Tortola'=>'(UTC-04:00) America/Tortola','America/St_Vincent'=>'(UTC-04:00) America/St_Vincent','America/St_Thomas'=>'(UTC-04:00) America/St_Thomas','America/St_Kitts'=>'(UTC-04:00) America/St_Kitts','America/St_Lucia'=>'(UTC-04:00) America/St_Lucia','America/Port-au-Prince'=>'(UTC-04:00) America/Port-au-Prince','America/Kentucky/Louisville'=>'(UTC-04:00) America/Kentucky/Louisville','America/Lower_Princes'=>'(UTC-04:00) America/Lower_Princes','America/Toronto'=>'(UTC-04:00) America/Toronto','America/Aruba'=>'(UTC-04:00) America/Aruba','America/Barbados'=>'(UTC-04:00) America/Barbados','America/Blanc-Sablon'=>'(UTC-04:00) America/Blanc-Sablon','America/Campo_Grande'=>'(UTC-04:00) America/Campo_Grande','America/Boa_Vista'=>'(UTC-04:00) America/Boa_Vista','America/Montserrat'=>'(UTC-04:00) America/Montserrat','America/Nassau'=>'(UTC-04:00) America/Nassau','America/Anguilla'=>'(UTC-04:00) America/Anguilla','Antarctica/Palmer'=>'(UTC-04:00) Antarctica/Palmer','America/Antigua'=>'(UTC-04:00) America/Antigua','America/Pangnirtung'=>'(UTC-04:00) America/Pangnirtung','America/New_York'=>'(UTC-04:00) America/New_York','America/Nipigon'=>'(UTC-04:00) America/Nipigon','America/Manaus'=>'(UTC-04:00) America/Manaus','America/Montevideo'=>'(UTC-03:00) America/Montevideo','Antarctica/Rothera'=>'(UTC-03:00) Antarctica/Rothera','Atlantic/Stanley'=>'(UTC-03:00) Atlantic/Stanley','Atlantic/Bermuda'=>'(UTC-03:00) Atlantic/Bermuda','America/Thule'=>'(UTC-03:00) America/Thule','America/Sao_Paulo'=>'(UTC-03:00) America/Sao_Paulo','America/Paramaribo'=>'(UTC-03:00) America/Paramaribo','America/Recife'=>'(UTC-03:00) America/Recife','America/Santarem'=>'(UTC-03:00) America/Santarem','America/Moncton'=>'(UTC-03:00) America/Moncton','America/Maceio'=>'(UTC-03:00) America/Maceio','America/Argentina/Salta'=>'(UTC-03:00) America/Argentina/Salta','America/Argentina/San_Juan'=>'(UTC-03:00) America/Argentina/San_Juan','America/Argentina/San_Luis'=>'(UTC-03:00) America/Argentina/San_Luis','America/Argentina/Tucuman'=>'(UTC-03:00) America/Argentina/Tucuman','America/Argentina/Rio_Gallegos'=>'(UTC-03:00) America/Argentina/Rio_Gallegos','America/Argentina/Mendoza'=>'(UTC-03:00) America/Argentina/Mendoza','America/Argentina/Cordoba'=>'(UTC-03:00) America/Argentina/Cordoba','America/Argentina/Jujuy'=>'(UTC-03:00) America/Argentina/Jujuy','America/Argentina/La_Rioja'=>'(UTC-03:00) America/Argentina/La_Rioja','America/Argentina/Buenos_Aires'=>'(UTC-03:00) America/Argentina/Buenos_Aires','America/Argentina/Ushuaia'=>'(UTC-03:00) America/Argentina/Ushuaia','America/Bahia'=>'(UTC-03:00) America/Bahia','America/Fortaleza'=>'(UTC-03:00) America/Fortaleza','America/Glace_Bay'=>'(UTC-03:00) America/Glace_Bay','America/Goose_Bay'=>'(UTC-03:00) America/Goose_Bay','America/Halifax'=>'(UTC-03:00) America/Halifax','America/Argentina/Catamarca'=>'(UTC-03:00) America/Argentina/Catamarca','America/Araguaina'=>'(UTC-03:00) America/Araguaina','America/Belem'=>'(UTC-03:00) America/Belem','America/Cayenne'=>'(UTC-03:00) America/Cayenne','America/St_Johns'=>'(UTC-02:30) America/St_Johns','Atlantic/South_Georgia'=>'(UTC-02:00) Atlantic/South_Georgia','America/Noronha'=>'(UTC-02:00) America/Noronha','America/Miquelon'=>'(UTC-02:00) America/Miquelon','America/Godthab'=>'(UTC-02:00) America/Godthab','Atlantic/Cape_Verde'=>'(UTC-01:00) Atlantic/Cape_Verde','Africa/Bissau'=>'(UTC+00:00) Africa/Bissau','Africa/Conakry'=>'(UTC+00:00) Africa/Conakry','Africa/Freetown'=>'(UTC+00:00) Africa/Freetown','Africa/Banjul'=>'(UTC+00:00) Africa/Banjul','Africa/Dakar'=>'(UTC+00:00) Africa/Dakar','Atlantic/Azores'=>'(UTC+00:00) Atlantic/Azores','Atlantic/Reykjavik'=>'(UTC+00:00) Atlantic/Reykjavik','Atlantic/St_Helena'=>'(UTC+00:00) Atlantic/St_Helena','Africa/Abidjan'=>'(UTC+00:00) Africa/Abidjan','Africa/Bamako'=>'(UTC+00:00) Africa/Bamako','America/Scoresbysund'=>'(UTC+00:00) America/Scoresbysund','Africa/Accra'=>'(UTC+00:00) Africa/Accra','Africa/Lome'=>'(UTC+00:00) Africa/Lome','Africa/Nouakchott'=>'(UTC+00:00) Africa/Nouakchott','Africa/Sao_Tome'=>'(UTC+00:00) Africa/Sao_Tome','Africa/Ouagadougou'=>'(UTC+00:00) Africa/Ouagadougou','Africa/Monrovia'=>'(UTC+00:00) Africa/Monrovia','America/Danmarkshavn'=>'(UTC+00:00) America/Danmarkshavn','Africa/Niamey'=>'(UTC+01:00) Africa/Niamey','Africa/Brazzaville'=>'(UTC+01:00) Africa/Brazzaville','Europe/Lisbon'=>'(UTC+01:00) Europe/Lisbon','Atlantic/Canary'=>'(UTC+01:00) Atlantic/Canary','Europe/Dublin'=>'(UTC+01:00) Europe/Dublin','Africa/Porto-Novo'=>'(UTC+01:00) Africa/Porto-Novo','Africa/Tunis'=>'(UTC+01:00) Africa/Tunis','Africa/Windhoek'=>'(UTC+01:00) Africa/Windhoek','Atlantic/Madeira'=>'(UTC+01:00) Atlantic/Madeira','Atlantic/Faroe'=>'(UTC+01:00) Atlantic/Faroe','Africa/Casablanca'=>'(UTC+01:00) Africa/Casablanca','Europe/London'=>'(UTC+01:00) Europe/London','Africa/Bangui'=>'(UTC+01:00) Africa/Bangui','Africa/Ndjamena'=>'(UTC+01:00) Africa/Ndjamena','Africa/Luanda'=>'(UTC+01:00) Africa/Luanda','Europe/Isle_of_Man'=>'(UTC+01:00) Europe/Isle_of_Man','Europe/Jersey'=>'(UTC+01:00) Europe/Jersey','Europe/Guernsey'=>'(UTC+01:00) Europe/Guernsey','Africa/Malabo'=>'(UTC+01:00) Africa/Malabo','Africa/El_Aaiun'=>'(UTC+01:00) Africa/El_Aaiun','Africa/Lagos'=>'(UTC+01:00) Africa/Lagos','Africa/Libreville'=>'(UTC+01:00) Africa/Libreville','Africa/Douala'=>'(UTC+01:00) Africa/Douala','Africa/Algiers'=>'(UTC+01:00) Africa/Algiers','Africa/Kinshasa'=>'(UTC+01:00) Africa/Kinshasa','Europe/Monaco'=>'(UTC+02:00) Europe/Monaco','Europe/Amsterdam'=>'(UTC+02:00) Europe/Amsterdam','Europe/Oslo'=>'(UTC+02:00) Europe/Oslo','Europe/Prague'=>'(UTC+02:00) Europe/Prague','Europe/Podgorica'=>'(UTC+02:00) Europe/Podgorica','Europe/Gibraltar'=>'(UTC+02:00) Europe/Gibraltar','Europe/Paris'=>'(UTC+02:00) Europe/Paris','Europe/Andorra'=>'(UTC+02:00) Europe/Andorra','Europe/Budapest'=>'(UTC+02:00) Europe/Budapest','Europe/Brussels'=>'(UTC+02:00) Europe/Brussels','Europe/Ljubljana'=>'(UTC+02:00) Europe/Ljubljana','Europe/Busingen'=>'(UTC+02:00) Europe/Busingen','Europe/Belgrade'=>'(UTC+02:00) Europe/Belgrade','Europe/Berlin'=>'(UTC+02:00) Europe/Berlin','Europe/Bratislava'=>'(UTC+02:00) Europe/Bratislava','Europe/Madrid'=>'(UTC+02:00) Europe/Madrid','Europe/Luxembourg'=>'(UTC+02:00) Europe/Luxembourg','Europe/Copenhagen'=>'(UTC+02:00) Europe/Copenhagen','Europe/Malta'=>'(UTC+02:00) Europe/Malta','Africa/Ceuta'=>'(UTC+02:00) Africa/Ceuta','Africa/Johannesburg'=>'(UTC+02:00) Africa/Johannesburg','Africa/Kigali'=>'(UTC+02:00) Africa/Kigali','Africa/Harare'=>'(UTC+02:00) Africa/Harare','Africa/Gaborone'=>'(UTC+02:00) Africa/Gaborone','Africa/Cairo'=>'(UTC+02:00) Africa/Cairo','Africa/Lubumbashi'=>'(UTC+02:00) Africa/Lubumbashi','Africa/Lusaka'=>'(UTC+02:00) Africa/Lusaka','Africa/Tripoli'=>'(UTC+02:00) Africa/Tripoli','Africa/Mbabane'=>'(UTC+02:00) Africa/Mbabane','Africa/Maseru'=>'(UTC+02:00) Africa/Maseru','Africa/Maputo'=>'(UTC+02:00) Africa/Maputo','Europe/Rome'=>'(UTC+02:00) Europe/Rome','Africa/Bujumbura'=>'(UTC+02:00) Africa/Bujumbura','Europe/Tirane'=>'(UTC+02:00) Europe/Tirane','Europe/Vaduz'=>'(UTC+02:00) Europe/Vaduz','Europe/Stockholm'=>'(UTC+02:00) Europe/Stockholm','Europe/Skopje'=>'(UTC+02:00) Europe/Skopje','Europe/San_Marino'=>'(UTC+02:00) Europe/San_Marino','Europe/Sarajevo'=>'(UTC+02:00) Europe/Sarajevo','Europe/Vatican'=>'(UTC+02:00) Europe/Vatican','Europe/Vienna'=>'(UTC+02:00) Europe/Vienna','Europe/Zurich'=>'(UTC+02:00) Europe/Zurich','Africa/Blantyre'=>'(UTC+02:00) Africa/Blantyre','Europe/Zagreb'=>'(UTC+02:00) Europe/Zagreb','Europe/Warsaw'=>'(UTC+02:00) Europe/Warsaw','Europe/Athens'=>'(UTC+03:00) Europe/Athens','Europe/Bucharest'=>'(UTC+03:00) Europe/Bucharest','Indian/Comoro'=>'(UTC+03:00) Indian/Comoro','Europe/Uzhgorod'=>'(UTC+03:00) Europe/Uzhgorod','Europe/Tallinn'=>'(UTC+03:00) Europe/Tallinn','Europe/Sofia'=>'(UTC+03:00) Europe/Sofia','Europe/Vilnius'=>'(UTC+03:00) Europe/Vilnius','Europe/Zaporozhye'=>'(UTC+03:00) Europe/Zaporozhye','Indian/Mayotte'=>'(UTC+03:00) Indian/Mayotte','Indian/Antananarivo'=>'(UTC+03:00) Indian/Antananarivo','Europe/Simferopol'=>'(UTC+03:00) Europe/Simferopol','Europe/Riga'=>'(UTC+03:00) Europe/Riga','Europe/Istanbul'=>'(UTC+03:00) Europe/Istanbul','Europe/Helsinki'=>'(UTC+03:00) Europe/Helsinki','Europe/Kaliningrad'=>'(UTC+03:00) Europe/Kaliningrad','Europe/Kiev'=>'(UTC+03:00) Europe/Kiev','Europe/Minsk'=>'(UTC+03:00) Europe/Minsk','Europe/Mariehamn'=>'(UTC+03:00) Europe/Mariehamn','Europe/Chisinau'=>'(UTC+03:00) Europe/Chisinau','Antarctica/Syowa'=>'(UTC+03:00) Antarctica/Syowa','Asia/Beirut'=>'(UTC+03:00) Asia/Beirut','Asia/Riyadh'=>'(UTC+03:00) Asia/Riyadh','Asia/Bahrain'=>'(UTC+03:00) Asia/Bahrain','Asia/Baghdad'=>'(UTC+03:00) Asia/Baghdad','Asia/Nicosia'=>'(UTC+03:00) Asia/Nicosia','Asia/Damascus'=>'(UTC+03:00) Asia/Damascus','Asia/Qatar'=>'(UTC+03:00) Asia/Qatar','Asia/Kuwait'=>'(UTC+03:00) Asia/Kuwait','Asia/Jerusalem'=>'(UTC+03:00) Asia/Jerusalem','Asia/Hebron'=>'(UTC+03:00) Asia/Hebron','Asia/Gaza'=>'(UTC+03:00) Asia/Gaza','Asia/Aden'=>'(UTC+03:00) Asia/Aden','Asia/Amman'=>'(UTC+03:00) Asia/Amman','Africa/Khartoum'=>'(UTC+03:00) Africa/Khartoum','Africa/Dar_es_Salaam'=>'(UTC+03:00) Africa/Dar_es_Salaam','Africa/Kampala'=>'(UTC+03:00) Africa/Kampala','Africa/Juba'=>'(UTC+03:00) Africa/Juba','Africa/Mogadishu'=>'(UTC+03:00) Africa/Mogadishu','Africa/Nairobi'=>'(UTC+03:00) Africa/Nairobi','Africa/Addis_Ababa'=>'(UTC+03:00) Africa/Addis_Ababa','Africa/Asmara'=>'(UTC+03:00) Africa/Asmara','Africa/Djibouti'=>'(UTC+03:00) Africa/Djibouti','Asia/Muscat'=>'(UTC+04:00) Asia/Muscat','Europe/Samara'=>'(UTC+04:00) Europe/Samara','Indian/Mauritius'=>'(UTC+04:00) Indian/Mauritius','Europe/Volgograd'=>'(UTC+04:00) Europe/Volgograd','Indian/Reunion'=>'(UTC+04:00) Indian/Reunion','Indian/Mahe'=>'(UTC+04:00) Indian/Mahe','Europe/Moscow'=>'(UTC+04:00) Europe/Moscow','Asia/Dubai'=>'(UTC+04:00) Asia/Dubai','Asia/Yerevan'=>'(UTC+04:00) Asia/Yerevan','Asia/Tbilisi'=>'(UTC+04:00) Asia/Tbilisi','Asia/Tehran'=>'(UTC+04:30) Asia/Tehran','Asia/Kabul'=>'(UTC+04:30) Asia/Kabul','Asia/Samarkand'=>'(UTC+05:00) Asia/Samarkand','Asia/Baku'=>'(UTC+05:00) Asia/Baku','Asia/Dushanbe'=>'(UTC+05:00) Asia/Dushanbe','Asia/Oral'=>'(UTC+05:00) Asia/Oral','Asia/Aqtau'=>'(UTC+05:00) Asia/Aqtau','Indian/Maldives'=>'(UTC+05:00) Indian/Maldives','Asia/Tashkent'=>'(UTC+05:00) Asia/Tashkent','Indian/Kerguelen'=>'(UTC+05:00) Indian/Kerguelen','Antarctica/Mawson'=>'(UTC+05:00) Antarctica/Mawson','Asia/Karachi'=>'(UTC+05:00) Asia/Karachi','Asia/Aqtobe'=>'(UTC+05:00) Asia/Aqtobe','Asia/Ashgabat'=>'(UTC+05:00) Asia/Ashgabat','Asia/Kolkata'=>'(UTC+05:30) Asia/Kolkata','Asia/Colombo'=>'(UTC+05:30) Asia/Colombo','Asia/Kathmandu'=>'(UTC+05:45) Asia/Kathmandu','Asia/Qyzylorda'=>'(UTC+06:00) Asia/Qyzylorda','Asia/Bishkek'=>'(UTC+06:00) Asia/Bishkek','Asia/Yekaterinburg'=>'(UTC+06:00) Asia/Yekaterinburg','Asia/Thimphu'=>'(UTC+06:00) Asia/Thimphu','Indian/Chagos'=>'(UTC+06:00) Indian/Chagos','Asia/Almaty'=>'(UTC+06:00) Asia/Almaty','Asia/Dhaka'=>'(UTC+06:00) Asia/Dhaka','Antarctica/Vostok'=>'(UTC+06:00) Antarctica/Vostok','Asia/Rangoon'=>'(UTC+06:30) Asia/Rangoon','Indian/Cocos'=>'(UTC+06:30) Indian/Cocos','Asia/Novokuznetsk'=>'(UTC+07:00) Asia/Novokuznetsk','Asia/Bangkok'=>'(UTC+07:00) Asia/Bangkok','Asia/Novosibirsk'=>'(UTC+07:00) Asia/Novosibirsk','Antarctica/Davis'=>'(UTC+07:00) Antarctica/Davis','Asia/Vientiane'=>'(UTC+07:00) Asia/Vientiane','Indian/Christmas'=>'(UTC+07:00) Indian/Christmas','Asia/Pontianak'=>'(UTC+07:00) Asia/Pontianak','Asia/Omsk'=>'(UTC+07:00) Asia/Omsk','Asia/Ho_Chi_Minh'=>'(UTC+07:00) Asia/Ho_Chi_Minh','Asia/Hovd'=>'(UTC+07:00) Asia/Hovd','Asia/Jakarta'=>'(UTC+07:00) Asia/Jakarta','Asia/Phnom_Penh'=>'(UTC+07:00) Asia/Phnom_Penh','Asia/Kuching'=>'(UTC+08:00) Asia/Kuching','Asia/Harbin'=>'(UTC+08:00) Asia/Harbin','Australia/Perth'=>'(UTC+08:00) Australia/Perth','Asia/Singapore'=>'(UTC+08:00) Asia/Singapore','Asia/Taipei'=>'(UTC+08:00) Asia/Taipei','Asia/Ulaanbaatar'=>'(UTC+08:00) Asia/Ulaanbaatar','Antarctica/Casey'=>'(UTC+08:00) Antarctica/Casey','Asia/Macau'=>'(UTC+08:00) Asia/Macau','Asia/Urumqi'=>'(UTC+08:00) Asia/Urumqi','Asia/Manila'=>'(UTC+08:00) Asia/Manila','Asia/Hong_Kong'=>'(UTC+08:00) Asia/Hong_Kong','Asia/Kuala_Lumpur'=>'(UTC+08:00) Asia/Kuala_Lumpur','Asia/Choibalsan'=>'(UTC+08:00) Asia/Choibalsan','Asia/Kashgar'=>'(UTC+08:00) Asia/Kashgar','Asia/Shanghai'=>'(UTC+08:00) Asia/Shanghai','Asia/Makassar'=>'(UTC+08:00) Asia/Makassar','Asia/Brunei'=>'(UTC+08:00) Asia/Brunei','Asia/Chongqing'=>'(UTC+08:00) Asia/Chongqing','Asia/Krasnoyarsk'=>'(UTC+08:00) Asia/Krasnoyarsk','Australia/Eucla'=>'(UTC+08:45) Australia/Eucla','Asia/Seoul'=>'(UTC+09:00) Asia/Seoul','Asia/Irkutsk'=>'(UTC+09:00) Asia/Irkutsk','Asia/Jayapura'=>'(UTC+09:00) Asia/Jayapura','Pacific/Palau'=>'(UTC+09:00) Pacific/Palau','Asia/Pyongyang'=>'(UTC+09:00) Asia/Pyongyang','Asia/Dili'=>'(UTC+09:00) Asia/Dili','Asia/Tokyo'=>'(UTC+09:00) Asia/Tokyo','Australia/Darwin'=>'(UTC+09:30) Australia/Darwin','Australia/Broken_Hill'=>'(UTC+09:30) Australia/Broken_Hill','Australia/Adelaide'=>'(UTC+09:30) Australia/Adelaide','Australia/Hobart'=>'(UTC+10:00) Australia/Hobart','Pacific/Chuuk'=>'(UTC+10:00) Pacific/Chuuk','Pacific/Guam'=>'(UTC+10:00) Pacific/Guam','Antarctica/DumontDUrville'=>'(UTC+10:00) Antarctica/DumontDUrville','Australia/Brisbane'=>'(UTC+10:00) Australia/Brisbane','Pacific/Saipan'=>'(UTC+10:00) Pacific/Saipan','Australia/Sydney'=>'(UTC+10:00) Australia/Sydney','Australia/Currie'=>'(UTC+10:00) Australia/Currie','Asia/Yakutsk'=>'(UTC+10:00) Asia/Yakutsk','Asia/Khandyga'=>'(UTC+10:00) Asia/Khandyga','Australia/Melbourne'=>'(UTC+10:00) Australia/Melbourne','Pacific/Port_Moresby'=>'(UTC+10:00) Pacific/Port_Moresby','Australia/Lindeman'=>'(UTC+10:00) Australia/Lindeman','Australia/Lord_Howe'=>'(UTC+10:30) Australia/Lord_Howe','Asia/Ust-Nera'=>'(UTC+11:00) Asia/Ust-Nera','Pacific/Noumea'=>'(UTC+11:00) Pacific/Noumea','Pacific/Pohnpei'=>'(UTC+11:00) Pacific/Pohnpei','Asia/Vladivostok'=>'(UTC+11:00) Asia/Vladivostok','Asia/Sakhalin'=>'(UTC+11:00) Asia/Sakhalin','Pacific/Kosrae'=>'(UTC+11:00) Pacific/Kosrae','Antarctica/Macquarie'=>'(UTC+11:00) Antarctica/Macquarie','Pacific/Guadalcanal'=>'(UTC+11:00) Pacific/Guadalcanal','Pacific/Efate'=>'(UTC+11:00) Pacific/Efate','Pacific/Norfolk'=>'(UTC+11:30) Pacific/Norfolk','Antarctica/McMurdo'=>'(UTC+12:00) Antarctica/McMurdo','Asia/Kamchatka'=>'(UTC+12:00) Asia/Kamchatka','Asia/Magadan'=>'(UTC+12:00) Asia/Magadan','Asia/Anadyr'=>'(UTC+12:00) Asia/Anadyr','Pacific/Fiji'=>'(UTC+12:00) Pacific/Fiji','Pacific/Majuro'=>'(UTC+12:00) Pacific/Majuro','Pacific/Wake'=>'(UTC+12:00) Pacific/Wake','Pacific/Nauru'=>'(UTC+12:00) Pacific/Nauru','Pacific/Auckland'=>'(UTC+12:00) Pacific/Auckland','Pacific/Kwajalein'=>'(UTC+12:00) Pacific/Kwajalein','Pacific/Funafuti'=>'(UTC+12:00) Pacific/Funafuti','Pacific/Tarawa'=>'(UTC+12:00) Pacific/Tarawa','Pacific/Wallis'=>'(UTC+12:00) Pacific/Wallis','Pacific/Chatham'=>'(UTC+12:45) Pacific/Chatham','Pacific/Tongatapu'=>'(UTC+13:00) Pacific/Tongatapu','Pacific/Enderbury'=>'(UTC+13:00) Pacific/Enderbury','Pacific/Fakaofo'=>'(UTC+13:00) Pacific/Fakaofo','Pacific/Apia'=>'(UTC+13:00) Pacific/Apia','Pacific/Kiritimati'=>'(UTC+14:00) Pacific/Kiritimati');
	    return $timezone_list;
	}

	

	public static function getTimeZones(){
		$timezones = panelRequestManager::generate_timezone_list();

		return array('timeZones'=>$timezones);
	}


	function getReaddedSite(){
		$actionID = Reg::get('currentRequest.actionID');
		$data = DB::getRow("?:history", "siteID,historyID", "actionID = '".$actionID."'");
		$siteID = $data['siteID'];
		$historyID = $data['historyID'];
		$status = DB::getField("?:history_additional_data", "status", "historyID = '".$historyID."'");
		if($status == 'success'){
			return array('siteID'=>$siteID);
		}else{
			return false;
		}
	}

	function iwpMaintenance(){
		$actionID = Reg::get('currentRequest.actionID');
		$data = DB::getRow("?:history", "siteID,historyID", "actionID = '".$actionID."'");
		$siteID = $data['siteID'];
		$historyID = $data['historyID'];
		$data = DB::getRow("?:history_additional_data", "uniqueName,status", "historyID = '".$historyID."'");
		$action = $data['uniqueName'];
		$status = $data['status'];
		if($status == 'success'){
			if($action == 'maintenance0'){$cStatus=1;}elseif($action == 'maintenance1'){$cStatus=2;}
			if(DB::update("?:sites",array('connectionStatus'=>$cStatus),"siteID='".$siteID."'")){
				return array('siteID'=>$siteID,'action'=>$action);
			}else{
				return false;
			}
			
		}else{
			return false;
		}
	}

	function iwpUpdateNotes($params){
		if(DB::update("?:sites", array('notes'=>$params['notes']), "siteID='".$params['siteID']."'") ){
			return $params;
		}else{
			return false;
		}
	}

	function iwpUpdateLinks($params){
		if(DB::update("?:sites", array('links'=>$params['links']), "siteID='".$params['siteID']."'") ){
			$params['links'] = explode(",",$params['links']);
			return $params;
		}else{
			return false;
		}
	}
        
        public static function iwpLoadServerInfo($siteID){
                $historyData = DB::getRow("?:sites", "siteTechinicalInfo", "siteID='".$siteID."'");
                //$returnData = json_encode(unserialize($historyData['siteTechinicalInfo']));
                $HTML = TPL::get('/templates/site/serverInformation.tpl.php', array('historyData' => unserialize($historyData['siteTechinicalInfo']), 'siteID' => $siteID));
		return $HTML;
	}
        
        public static function getlastMaintenanceModeHTML($siteID){
            $lastHTML = DB::getRow("?:sites", "lastMaintenanceModeHTML,siteID", "siteID='".$siteID."'");
            return $lastHTML;
        }

}

?>