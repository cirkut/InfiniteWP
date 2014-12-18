<?php

/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
if(defined('E_DEPRECATED')) {
	error_reporting(error_reporting() & ~E_DEPRECATED);
}

//to handle gpc magic quote on and sql escape if required
$_REQUEST_ORIGINAL = $_REQUEST;
$_GET_ORIGINAL = $_GET;
$_POST_ORIGINAL = $_POST;

$_REQUEST = filterParameters($_REQUEST);
$_GET = filterParameters($_GET);
$_POST = filterParameters($_POST);


//about current installation
define('INSTALL_APP_VERSION', '2.4.5');

define('APP_INSTALL_ROOT', dirname(__FILE__));
define('REQUIRED_MINIMUM_MYSQL_VERSION', '5.0.2');//4.1.2

$maximumExecutionTime = 300 + ini_get('max_execution_time');
@set_time_limit($maximumExecutionTime);//300 => 5 mins

require_once(APP_INSTALL_ROOT.'/../includes/manageCookies.php');

function getRootURL(){
    if(!isset($_SERVER['REQUEST_URI'])){
        $serverrequri = $_SERVER['PHP_SELF'];
    }else{
        $serverrequri =    $_SERVER['REQUEST_URI'];
    }
    $s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
    $serverProtocol = explode('/', $_SERVER["SERVER_PROTOCOL"]);
	$protocol = strtolower(reset($serverProtocol));
    $protocol .= $s;
    $port = ($_SERVER["SERVER_PORT"] == "80") ? "" : (":".$_SERVER["SERVER_PORT"]);
	
	//port issue - fixed.
	$host = explode(":", $_SERVER['HTTP_HOST']);
	$fullURL = $protocol."://".$host[0].$port.$serverrequri;
	
	//old
    //$fullURL = $protocol."://".$_SERVER['HTTP_HOST'].$port.$serverrequri;
	
	$fullURLParts = explode('/', $fullURL);
	array_pop($fullURLParts);
	array_pop($fullURLParts);
	return implode('/', $fullURLParts);
	
}

function checkEmail($email) {
  if(preg_match("/^([a-z0-9\\+_\\-]+)(\\.[a-z0-9\\+_\\-]+)*@([a-z0-9\\-]+\\.)+[a-z]{2,6}$/ix", $email)){
    return true;
  }
  return false;
}

function parseMysqlDump($file, $tableNamePrefix){
	
  global $DBDriver;
  $fileContent = file($file, FILE_SKIP_EMPTY_LINES);
  $currentQuery = '';
  foreach ($fileContent as $sqlLine) {
	  
	  if (substr($sqlLine, 0, 2) == '--' || $sqlLine == '')
		  continue;
		  $currentQuery .= $sqlLine;
		  if (substr(trim($sqlLine), -1, 1) == ';') {
			  
			  $currentQuery = trim(str_replace('iwp_', $tableNamePrefix, $currentQuery));

			  echo '<br>'.substr($currentQuery, 0, 45).'..'; ob_flush();flush();
			  $result = $DBDriver->query($currentQuery) or installDie($DBDriver->error());
		  $currentQuery = '';
     }
   }
   return true;
}

function modifyConfigFile($file, $config){
	
	//same code at the bottom
	if(class_exists('mysqli')){
		$driver = 'mysqli';
	}
	elseif(function_exists('mysql_connect')){
		$driver = 'mysql';
	}
	else{
		installDie('PHP has no mysql extension installed');	
	}
	
	$appInstallHash = sha1(APP_INSTALL_ROOT.uniqid('', true));
	
	if(isset($config['appDomainPath'])){
		$appDomainPath = $config['appDomainPath'];
	}else{
		$appFullURL = getRootURL();
		$appFullURLArray = explode('//', $appFullURL);	
		$appDomainPath = $appFullURLArray[1];
	}

$fileContent = '<?php 
#Show Error
define(\'APP_SHOW_ERROR\', true);

ini_set(\'display_errors\', (APP_SHOW_ERROR) ? \'On\' : \'Off\');
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);
if(defined(\'E_DEPRECATED\')) {
error_reporting(error_reporting() & ~E_DEPRECATED);
}
define(\'SHOW_SQL_ERROR\', APP_SHOW_ERROR);

define(\'APP_VERSION\', \''.INSTALL_APP_VERSION.'\');
define(\'APP_INSTALL_HASH\', \''.$appInstallHash.'\');

define(\'APP_ROOT\', dirname(__FILE__));
define(\'APP_DOMAIN_PATH\', \''.$appDomainPath.'/\');

define(\'EXECUTE_FILE\', \'execute.php\');
define(\'DEFAULT_MAX_CLIENT_REQUEST_TIMEOUT\', 180);//Request to client wp

$config = array();
$config[\'SQL_DATABASE\'] = \''.$config['dbName'].'\';
$config[\'SQL_HOST\'] = \''.$config['dbHost'].'\';
$config[\'SQL_USERNAME\'] = \''.$config['dbUser'].'\';
$config[\'SQL_PASSWORD\'] = \''.$config['dbPass'].'\';
$config[\'SQL_PORT\'] = \''.$config['dbPort'].'\';
$config[\'SQL_TABLE_NAME_PREFIX\'] = \''.$config['dbTableNamePrefix'].'\';

define(\'SQL_DRIVER\', \''.$driver.'\');


?>';	

	file_put_contents($file, $fileContent) or installDie('Unable to modify the config file.');
	echo '<br>Config file modified.'; ob_flush(); flush();
}

function echoStatusAndExit($status){
	$statusMsg = 'error';
	if($status){
		$statusMsg = 'completed';
	}
	echo '<script>var installStatus=\''.$statusMsg.'\';</script>';
	if($statusMsg == 'completed'){ exit; }
}

function installDie($msg){
	$msg = '<br><strong>Error:</strong> '. $msg;
	echoStatusAndExit(false);
	die($msg);
}

function checkFinal($key){
	if($GLOBALS['check']['final'][$key] === true){
		$class = 'success';
	}
	elseif($GLOBALS['check']['final'][$key] === 1){
		$class = 'warning';
	}
	else{
		$class = 'fail';
	}
	echo $class;
}

function checkAvailable($key){
	echo !empty($GLOBALS['check']['available'][$key]) ? 'ENABLED' : 'DISABLED';
}

function indexPagesClass($indexStep){
	
	$steps = array();
	$steps[0] = '';
	$steps[1] = 'checkRequirement';
	$steps[2] = 'enterDetails';
	$steps[3] = 'install';
	
	$currentStep = empty($_GET['step']) ? '' : $_GET['step'];
	
	$currentStepPosition = array_search($currentStep, $steps);
	
	$indexStepPosition = array_search($indexStep, $steps);
	
	if($indexStepPosition < $currentStepPosition ){
		echo 'rep_sprite_backup completed';
	}
	elseif($indexStepPosition === $currentStepPosition ){
		echo  'rep_sprite_backup current';
	}
	else{
		echo 'linkDisabled';
	}
}


function filterParameters($array, $DBEscapeString=false){
  
	  if(is_array($array)){
		  foreach($array as $key => $value){
			  $array[$key] = filterParameters($array[$key]);
		  }
	  }
	  elseif(is_string($array)){
		  if(get_magic_quotes_gpc()) $array = stripslashes($array);
		  if($DBEscapeString) $array = DB::realEscapeString($array);
	  }
	  return $array;
}

function checkPHPRequirements(){

	$check = array();
	$check['required']['PHP_VERSION'] 		= '5.2.4';
	$check['required']['PHP_SAFE_MODE'] 	= 0;//should be in off
	$check['required']['PHP_WITH_MYSQL'] 	= 1;
	$check['required']['PHP_WITH_OPEN_SSL'] = 1;
	$check['required']['PHP_WITH_CURL'] 	= 1;
	$check['required']['PHP_FILE_UPLOAD'] 	= 1;
	$check['required']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE'] = 1;
//DISABLING FSOCK CHECK	//$check['required']['FSOCK_SAME_URL_CONNECT_CHECK'] 		= 1;
	//$check['required']['MYSQL_VERSION'] 	= '4.1.2';
	
	
	//======================================================================>
	
	$check['available']['PHP_VERSION'] 			= phpversion();
	
	$phpSafeMode = ini_get('safe_mode');
	$check['available']['PHP_SAFE_MODE'] 		= !empty($phpSafeMode);
	$check['available']['PHP_WITH_MYSQL'] 		= (class_exists('mysqli') or function_exists('mysql_connect'));
	$check['available']['PHP_WITH_OPEN_SSL'] 	= function_exists('openssl_verify');
	$check['available']['PHP_WITH_CURL'] 		= function_exists('curl_exec');
	$check['available']['PHP_FILE_UPLOAD'] 		= ini_get('file_uploads')==1 ? true : false;
	
	
	//checking PHP_MAX_EXECUTION_TIME_CONFIGURABLE
	$check['available']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE'] = 0;
	if($GLOBALS['maximumExecutionTime'] == ini_get('max_execution_time')){
		$check['available']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE'] = 1;
	}
	
	
//DISABLING FSOCK CHECK	//$appFullURL = getRootURL();
//	$fURL = $appFullURL."/execute.php";
//	$fsockSameURLConnectCheckResult = fsockSameURLConnectCheck($fURL);
//	$check['available']['FSOCK_SAME_URL_CONNECT_CHECK'] = $fsockSameURLConnectCheckResult['status'];
	
	//======================================================================>
		
			
	$check['final']['PHP_VERSION'] 		    = (version_compare($check['available']['PHP_VERSION'], $check['required']['PHP_VERSION'])  >= 0) ? true: false;
	$check['final']['PHP_SAFE_MODE'] 		= ($check['required']['PHP_SAFE_MODE'] == $check['available']['PHP_SAFE_MODE']) ? true: false;
	$check['final']['PHP_WITH_MYSQL'] 		= ($check['required']['PHP_WITH_MYSQL'] == $check['available']['PHP_WITH_MYSQL']) ? true: false;
	$check['final']['PHP_WITH_OPEN_SSL'] 	= ($check['required']['PHP_WITH_OPEN_SSL'] == $check['available']['PHP_WITH_OPEN_SSL']) ? true: 1;//1 = optional
	$check['final']['PHP_WITH_CURL']		= ($check['required']['PHP_WITH_CURL'] == $check['available']['PHP_WITH_CURL']) ? true: false;
	$check['final']['PHP_FILE_UPLOAD']		= ($check['required']['PHP_FILE_UPLOAD'] == $check['available']['PHP_FILE_UPLOAD']) ? true: false;
	$check['final']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE']  = ($check['required']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE'] == $check['available']['PHP_MAX_EXECUTION_TIME_CONFIGURABLE']) ? true: false;
//DISABLING FSOCK CHECK	//$check['final']['FSOCK_SAME_URL_CONNECT_CHECK'] 		= ($check['required']['FSOCK_SAME_URL_CONNECT_CHECK'] == $check['available']['FSOCK_SAME_URL_CONNECT_CHECK']) ? true: false;
	return $check;

}

function mysqlConnectAndCheck(){

//same code at the top
	if(class_exists('mysqli')){
		$driver = 'mysqli';
	}
	elseif(function_exists('mysql_connect')){
		$driver = 'mysql';
	}
	else{
		installDie('PHP has no mysql extension installed');	
	}
	
	global $DBDriver, $DBResultClass, $config;
	
	
	$DBClass = 'DB'.ucfirst($driver);
	$DBResultClass = $DBClass.'Result';
	
	require_once(APP_INSTALL_ROOT.'/../includes/dbDrivers/'.$driver.'.php');
	
	
	
	if($driver == 'mysql'){
		
		class installDB extends DBMysql{
			
			function __construct($DBHost, $DBUsername, $DBPassword, $DBName, $DBPort){
				parent::__construct($DBHost, $DBUsername, $DBPassword, $DBName, $DBPort);
			}
			
			function connect(){
				$this->DBLink = mysql_connect($this->DBHost.':'.$this->DBPort, $this->DBUsername, $this->DBPassword);
				if (!$this->DBLink) {
					installDie('Mysql connect error: (' . $this->errorNo().') '.$this->error());
				}
				if (!mysql_select_db($this->DBName, $this->DBLink)){
					installDie('Mysql connect error: (' . $this->errorNo().') '.$this->error());
				}
			}
			
		}
		$DBResultClass = 'DBMysqlResult';
	}
	elseif($driver == 'mysqli'){
		
		class installDB extends DBMysqli{
			
			function __construct($DBHost, $DBUsername, $DBPassword, $DBName, $DBPort){
				parent::__construct($DBHost, $DBUsername, $DBPassword, $DBName, $DBPort);
			}
			
			function connect(){
				$this->DBLink = new mysqli($this->DBHost, $this->DBUsername, $this->DBPassword, $this->DBName, $this->DBPort);
				if ($this->DBLink->connect_errno) {
					installDie('Mysql connect error: (' . $this->DBLink->connect_errno.') '.$this->DBLink->connect_error);
				}	
				return true;	
			}			
		}
		$DBResultClass = 'DBMysqliResult';
	}
	
	$DBDriver = new installDB($config['dbHost'], $config['dbUser'], $config['dbPass'], $config['dbName'], $config['dbPort']);
	
	//getting MYSQL_VERSION
	$q = $DBDriver->query("select version() as V");
	if($DBDriver->error()){
		installDie('Unable to fetch Mysql version no');
	}
	$rObj = new $DBResultClass($q);
	$r = $rObj->nextRow($q);

	if(empty($r)){
		installDie('Unable to fetch Mysql version no');
	}
	$mySqlVersionTemp = explode('-', $r['V']);
	$mysqlVersion = reset($mySqlVersionTemp);
	if(version_compare($mysqlVersion, REQUIRED_MINIMUM_MYSQL_VERSION) < 0){
		installDie('Minimum MySQL Version required is '.REQUIRED_MINIMUM_MYSQL_VERSION);
	}
}
//DISABLING FSOCK CHECK
//function fsockSameURLConnectCheck($url){
//	
//	$params=array('check' =>  'sameURL');	
//	
//	$post_params = array();
//	foreach ($params as $key => &$val) {
//      if (is_array($val)) $val = implode(',', $val);
//        $post_params[] = $key.'='.urlencode($val);
//    }
//    $post_string = implode('&', $post_params);
//	
//	$parts = parse_url($url);
//	$host = $parts['host'];
//
//	if (($parts['scheme'] == 'ssl' || $parts['scheme'] == 'https') && extension_loaded('openssl')){
//		$parts['host'] = "ssl://".$parts['host'];
//		$parts['port'] = 443;
//		error_reporting(0);
//	}
//	elseif($parts['port']==''){
//		$parts['port'] = 80;
//	}	
//	  
//    $fp = @fsockopen($parts['host'], $parts['port'], $errno, $errstr, 30);
//	if(!$fp) return array('status' => false, 'resource' => !empty($fp) ? true : false, 'errorNo' => 'unable_to_intiate_fsock', 'error' => 'Unable to initiate FsockOpen');
//	if($errno > 0) return array('status' => false, 'errorNo' => $errno, 'error' => $errno. ':' .$errstr);
//
//    $out = "POST ".$parts['path']." HTTP/1.0\r\n";
//    $out.= "Host: ".$host."\r\n";
//	$out.= "User-agent: " . "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko Firefox/16.0". "\r\n";
//    $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
//    $out.= "Content-Length: ".strlen($post_string)."\r\n";
//    $out.= "Connection: Close\r\n\r\n";
//	
//    if (isset($post_string)) $out.= $post_string;
//	
//    $is_written = fwrite($fp, $out);
//	if(!$is_written){
//		return array('status' => false, 'writable' => false, 'errorNo' => 'unable_to_write_request', 'error' => 'Unable to write request');
//	}
//	
//	$temp = '';
//	 while (!feof($fp)) {
//        $temp .= fgets($fp, 128);
//    }
//	
//	if(strpos($temp, 'same_url_connection') !== false){
//		return array('status' => true);
//	}
//	elseif(strpos($temp, 'WWW-Authenticate:') !== false){
//		return array('status' => false, 'errorNo' => 'authentication_required', 'error' => 'Please remove the folder protection. You can add that after installation. You can also add the credentials in the settings panel.');
//	}
//	else{
//		return array('status' => false, 'errorNo' => 'unable_to_verify', 'error' => 'Unable to verify content');
//	}
//	
//    fclose($fp);
//}

if($GLOBALS['automatedCPanelInstall']) return true;

if(empty($_GET['step'])){

	$continueLink = 'checkRequirement';
}
elseif($_GET['step'] == 'checkRequirement'){

	$check = checkPHPRequirements();
	
	
        manageCookies::cookieSet('isRequirementMet', false,array('expire'=>0));
	foreach($check['final'] as $val){
		if($val){
                        manageCookies::cookieSet('isRequirementMet', true,array('expire'=>0));
		}
		else{
			manageCookies::cookieSet('isRequirementMet', false,array('expire'=>0));
			break;	
		}
	}
	$continueLink = 'enterDetails';
}
elseif($_GET['step'] == 'enterDetails'){
	//get DB and other details display form
	
	$continueLink = 'install';
}
elseif($_GET['step'] == 'install'){
	//set to DB and other details in session
        manageCookies::cookieSet('installConfig', $_POST,array('expire'=>0));
}
elseif($_GET['step'] == 'installIFrame'){
	
	?>
<link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
<style>
body{ 	font-family: 'Droid Sans', sans-serif; font-size:12px; color:#555; line-height:18px; }
</style>
    <?php
	
	echo str_pad(' ', 1000, ' ');echo '&nbsp;';
	
	echo '<br>Checking..'; ob_flush(); flush();
         $config = manageCookies::cookieGet('installConfig');

	//check file writable
	if(!is_writable(APP_INSTALL_ROOT.'/../config.php')){
		installDie('Please set config.php file permission to 666 or writable by script.');
	}
	
	mysqlConnectAndCheck();
	
	//check email and password
	if(!checkEmail($config['email'])){
		installDie('Invalid email address');
	}
	if($config['password'] != $config['password2']){
		installDie('Login credentials passwords don\'t match');
	}
	if(strlen($config['password']) < 6 ){
		installDie('Login credentials password must be atlest 6 characters long');
	}

	//install DB
	echo '<br>Installing DB..'; ob_flush(); flush();
	parseMysqlDump(APP_INSTALL_ROOT.'/install.sql', $config['dbTableNamePrefix']);
	
	//create config file
	modifyConfigFile(APP_INSTALL_ROOT.'/../config.php', $config);
	
	//Run SQL Queries
	//create user
	$isDone = $DBDriver->query("insert into ".$config['dbTableNamePrefix']."users SET email='".$config['email']."', accessLevel='admin' ,  password='".sha1($config['password'])."', notifications='a:1:{s:23:\"updatesNotificationMail\";a:4:{s:9:\"frequency\";s:6:\"weekly\";s:11:\"coreUpdates\";s:1:\"1\";s:13:\"pluginUpdates\";s:1:\"1\";s:12:\"themeUpdates\";s:1:\"1\";}}'");
	
	$installedTime = time();
	$DBDriver->query("update ".$config['dbTableNamePrefix']."options SET optionValue='".$installedTime."' WHERE optionName = 'installedTime'");
	$DBDriver->query("update ".$config['dbTableNamePrefix']."options SET optionValue='".($installedTime + 86400)."' WHERE optionName = 'anonymousDataNextSchedule'");
	
	//update timezone and HTTPS
	$q = $DBDriver->query("select * from ".$config['dbTableNamePrefix']."settings");
	
	$rObj = new $DBResultClass($q);
	$r = $rObj->nextRow($q);
	
	$settings =  unserialize($r['general']);
	
	$timezone = ini_get('date.timezone');
	if ( !empty($timezone)){
		$settings['TIMEZONE'] = $timezone;
	}
	if(isset($config['HTTPS'])) {
		$settings['enableHTTPS'] = (string)$config['HTTPS'];
	}
	
	$DBDriver->query("UPDATE ".$config['dbTableNamePrefix']."settings SET  `general` = '".serialize($settings)."'");
	
	if($isDone){
		echo '<br>User Created.'; ob_flush(); flush();
		echoStatusAndExit($isDone);
	}else{
		installDie('Unable to create user.');
	}
}

//-----------------------------------------------HTML Content Starts here-------------------------------------------------------->
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="robots" content="noindex">
<title>InfiniteWP</title>
<link href='http://fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
<link rel="stylesheet" type="text/css" href="../css/core.min.css" />
<link rel="stylesheet" href="../css/nanoscroller.css" type="text/css" />
<script src="../js/jquery.min.js" type="text/javascript" charset="utf-8"></script>
<script src="../js/apps.min.js" type="text/javascript" charset="utf-8"></script>
<script src="../js/jquery.nanoscroller.min.js" type="text/javascript"></script>
<script>
$(function(){
	$('.httpsCheckbox').live('click', function(){
		makeSelection(this);
		if($(this).hasClass('active')){
			$('#HTTPS').val('1');
		}
		else{
			$('#HTTPS').val('0');
		}
	});
	
	$('a.linkDisabled').live('click', function(e){
		e.preventDefault();
		return false;
	});
	
	$(".btn_next_step").live('mousedown',function(){ 
		 $(this).addClass('pressed');
		}).live('mouseup',function () { 
		$(this).removeClass('pressed');
	});
	
	$('.nano').nanoScroller();
});
</script>
</head>
<body>
<div id="site_cont" style="width: 852px;">
<div id="logo_signin" style="margin-top:50px;"></div>
<div style="text-transform: uppercase; color: #434E51; font-size: 12px; margin-bottom: 20px; text-align: center; font-weight: 700; text-shadow: 0 1px 0 rgba(255, 255, 255, 0.6);">Manage all your WordPress sites</div>
<div class="dialog_cont iwp_installation">
    <div class="th rep_sprite">
        <div class="title droid700">InfiniteWP INSTALLATION</div>
      </div>
    <div class="th_sub rep_sprite">
        <ul>
        <li><a class="<?php indexPagesClass(''); ?>" href="index.php">LICENSE AGREEMENT</a></li>
        <li class="line"></li>
        <li><a class="<?php indexPagesClass('checkRequirement'); ?>" href="index.php?step=checkRequirement">CHECK REQUIREMENTS</a></li>
        <li class="line"></li>
        <li><a class="<?php indexPagesClass('enterDetails'); ?>" href="index.php?step=enterDetails">DB &amp; LOGIN DETAILS</a></li>
        <li class="line"></li>
        <li><a class="<?php indexPagesClass('install'); ?>" href="index.php?step=install">INSTALL IWP</a></li>
      </ul>
      </div>
      
<?php if(empty($_GET['step'])){ ?>


<div class="iwp_installtion_content license_agreement">
        <div class="tr">
        <div style="height:400px; overflow:auto; padding: 0 10px 0 20px;">
       <div class="nano"><div class="content" style="padding-right: 10px;"> <?php include('../license.html'); ?></div></div>
        </div>        
        </div>
      </div>
      
<?php } elseif($_GET['step'] == 'checkRequirement'){ ?>

<div class="iwp_installtion_content check_requirement">
        <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">PHP INFORMATION</div>
            <div class="req_descr">You can view the current state of PHP.</div>
          </div>
        <a href="info.php" target="_blank" class="float-left" style="margin:10px 43px;">View PHP Info</a>
        <div class="clear-both"></div>
      </div>
        <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">PHP VERSION</div>
            <div class="req_descr">InfiniteWP requires PHP version <?php echo $check['required']['PHP_VERSION']; ?> or higher.</div>
          </div>
        <div class="req_result float-left"><?php echo $check['available']['PHP_VERSION']; ?></div>
        <div class="icon_result float-left <?php checkFinal('PHP_VERSION'); ?>"></div>
        <div class="clear-both"></div>
      </div>
        <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">MYSQL SUPPORT</div>
            <div class="req_descr">PHP is required to be compiled with <span class="droid700">Mysql or Mysqli</span> support.</div>
          </div>
        <div class=" req_result float-left"><?php checkAvailable('PHP_WITH_MYSQL'); ?></div>
        <div class="icon_result float-left <?php checkFinal('PHP_WITH_MYSQL'); ?>"></div>
        <div class="clear-both"></div>
      </div>
        <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">SAFE MODE</div>
            <div class="req_descr">PHP safe mode is required to be <span class="droid700">disabled</span>.</div>
          </div>
        <div class=" req_result float-left"><?php checkAvailable('PHP_SAFE_MODE'); ?></div>
        <div class="icon_result float-left <?php checkFinal('PHP_SAFE_MODE'); ?>"></div>
        <div class="clear-both"></div>
      </div>
      <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">OPEN SSL</div>
            <div class="req_descr">Enabling Open SSL makes it secure. However, this is <span class="droid700">optional</span>.</div>
          </div>
        <div class=" req_result float-left"><?php checkAvailable('PHP_WITH_OPEN_SSL'); ?></div>
        <div class="icon_result float-left <?php checkFinal('PHP_WITH_OPEN_SSL'); ?>"></div>
        <div class="clear-both"></div>
      </div>
        <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">FILE UPLOADS</div>
            <div class="req_descr">PHP file uploads option is required to be <span class="droid700">enabled</span>.</div>
          </div>
        <div class=" req_result float-left"><?php checkAvailable('PHP_FILE_UPLOAD'); ?></div>
        <div class="icon_result float-left  <?php checkFinal('PHP_FILE_UPLOAD'); ?>"></div>
        <div class="clear-both"></div>
      </div>
        <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">CURL SUPPORT</div>
            <div class="req_descr">It is required for all communications between the client plugin and the admin panel.</div>
          </div>
        <div class=" req_result float-left"><?php checkAvailable('PHP_WITH_CURL'); ?></div>
        <div class="icon_result float-left <?php checkFinal('PHP_WITH_CURL'); ?>"></div>
        <div class="clear-both"></div>
      </div>
        <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">CONFIGURABLE MAX EXECUTION TIME</div>
            <div class="req_descr">The max execution time should be configurable.</div>
          </div>
        <div class=" req_result float-left"><?php checkAvailable('PHP_MAX_EXECUTION_TIME_CONFIGURABLE'); ?></div>
        <div class="icon_result float-left <?php checkFinal('PHP_MAX_EXECUTION_TIME_CONFIGURABLE'); ?>"></div>
        <div class="clear-both"></div>
      </div>
      <?php /* if(empty($GLOBALS['check']['final']['FSOCK_SAME_URL_CONNECT_CHECK'])){ ?>
      <div class="tr">
        <div class="req_txt float-left">
            <div class="req_title">ASYNC CALL SUPPORT</div>
            <div class="req_descr" style="line-height:16px; padding:5px 0;">PHP should be able to access files in the same server through a full url <br />eg. <?php echo getRootURL().'/execute.php'; ?></div>
            <div class="req_descr" style="line-height:16px; padding:5px 0; color: #A92A2A"><?php if(empty($fsockSameURLConnectCheckResult['status'])){ echo '<span style="font-weight:700;">FSock Error:</span> '.$fsockSameURLConnectCheckResult['error']; } ?></div>
          </div>
        <div class=" req_result float-left"><?php checkAvailable('FSOCK_SAME_URL_CONNECT_CHECK'); ?></div>
        <div class="icon_result float-left <?php checkFinal('FSOCK_SAME_URL_CONNECT_CHECK'); ?>"></div>
        <div class="clear-both"></div>
      </div>
      <?php } */ ?>
      <div class="tr">
        <div class="req_txt float-left">
            <div class="req_descr">Before you proceed to the next step please make sure you have the appropriate permissions for:<br>
            <br>
            <span class="droid700" style="margin-left:20px;">config.php</span> - read/write permission (666 or 644)</div>
          </div>
        <div class="clear-both"></div>
      </div>
      </div>
      
<?php
$isRequirementMet = manageCookies::cookieGet('isRequirementMet');
if(!$isRequirementMet){
	$continueClass='linkDisabled';
	$continueDivClass='disabled';
}
?>
<?php } elseif($_GET['step'] == 'enterDetails'){ ?>

<form action="index.php?step=install" method="post">
<div class="iwp_installtion_content db_login">
   <div class="form float-left" style="border-right:1px solid #efefef;">
    <div class="form_title">ENTER DATABASE DETAILS</div>
    <div class="label">DB HOST</div>
    <input name="dbHost" type="text" value="localhost">
    
    <div class="label">DB PORT</div>
    <input name="dbPort" type="text" value="3306">
    
    <div class="label">DB NAME</div>
    <input name="dbName" type="text" value="">
    
    <div class="label">DB USERNAME</div>
    <input name="dbUser" type="text" value="">
    
    <div class="label">DB PASSWORD</div>
    <input name="dbPass" type="text" value="">
    
    <div class="label">DB TABLE NAME PREFIX</div>
    <input name="dbTableNamePrefix" type="text" value="iwp_">
    
  </div>
  <div class="form float-left">
    <div class="form_title">CREATE LOGIN CREDENTIALS</div>
    <div class="label">EMAIL</div>
    <input name="email" type="text">
    
    <div class="label">PASSWORD</div>
    <input name="password" type="password">
    
    <div class="label">PASSWORD AGAIN</div>
    <input name="password2" type="password">
    
    <div class="form_title">SETTINGS</div>    
    <div class="checkbox httpsCheckbox<?php if($_SERVER["HTTPS"] == "on"){ ?> active<?php } ?>" style="color:#737987;">Enable HTTPS</div>
    <div style="color:#9398A2;">You can change this setting by editing APP_HTTPS in config.php</div>
    <input name="HTTPS" id="HTTPS" type="hidden" value="<?php if($_SERVER["HTTPS"] == "on"){ ?>1<?php } else{ ?>0<?php } ?>">
    
    <input type="submit" name="step" value="Install" style="display:none;" id="installButton" />
  </div>
</div>
</form>
<?php $continueOnClick='$(\'#installButton\').click();'; ?>

<?php } elseif($_GET['step'] == 'install'){ ?>
<div class="iwp_installtion_content install_final">

	<script>
    function installStatus(status){
        clearInterval(D);
        if(status=='completed'){
            document.getElementById('installSuccessMsg').style.display = 'block';
        }
        else if(status=='error'){
            document.getElementById('installErrorMsg').style.display = 'block';
        }
    }
    function scrollToEnd() {
        document.getElementById("installIFrame").contentWindow.scrollTo(0,document.getElementById("installIFrame").contentWindow.document.body.scrollHeight)
    }
    var D = setInterval(function(){  scrollToEnd(); if(window.installIFrame.installStatus != undefined){ installStatus(window.installIFrame.installStatus); } }, 500);
    </script>
    
    <iframe name="installIFrame" id="installIFrame" src="index.php?step=installIFrame" width="100%" height="300"></iframe>
    
    <span id="installSuccessMsg" style="display:none;">
        <div class="successMsg" style="margin-top:10px;">
        <span class="success_icon"></span>Woohooo! It's all done &amp; ready to go.
        <a href="../login.php">Login Now</a>    
        </div>
        <div style="text-align:center; color:#8A1010;">For added security, delete or rename the "install" folder.</div>
    </span>
    
    <div id="installErrorMsg" style="display:none; margin-top:10px;">
    <span style="font-size:14px;">&laquo;</span> <a onClick="history.go(-1);">Edit DB &amp; Login Details</a>
    </div>

</div>

<?php } elseif($_GET['step'] == 'installIFrame'){ ?>
<?php } ?>    
    
    <div class="clear-both"></div>
    <div class="th rep_sprite" style="border-top:1px solid #c6c9ca; height:35px;">
    
    <?php if($_GET['step']!='install'){ ?>
        <a <?php if(empty($continueOnClick)){ ?> href="index.php?step=<?php echo $continueLink; ?>"<?php } ?> onClick="<?php echo $continueOnClick; ?>" style="text-decoration:none;" class="continueLink <?php echo $continueClass; ?>"><div class="btn_next_step float-right rep_sprite <?php echo $continueDivClass; ?>"><?php if($_GET['step']=='enterDetails'){ ?>Install<?php }elseif(empty($_GET['step'])) { ?>Agree &amp; Install<?php } else { ?>Continue<?php }?>
        <div class="taper"></div>
      </div></a>
     <?php } ?> 
      </div>
  </div>
  </div>
  </body>
  </html>