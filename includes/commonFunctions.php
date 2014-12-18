<?php

/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

function paginate($page, $total, $itemsPerPage, $paginationName='pagination'){
	
	if(empty($page) || !is_numeric($page)) $page = 1;
	
	$totalPage = ceil($total / $itemsPerPage);
	
	$prevPage = $page > 1 ? ($page - 1) : '';
	$nextPage = $page < $totalPage ? ($page + 1) : '';
	
	$pagination = array('page'		=> $page,
						'prevPage'	=> $prevPage,
						'nextPage'	=> $nextPage,
						'total'		=> $total,
						'itemPerPage'	=> $itemsPerPage,
						'totalPage'	=> $totalPage,						
						);
					
	Reg::tplSet($paginationName, $pagination);
						
	return 'LIMIT '.(($page - 1)  * $itemsPerPage).', '.$itemsPerPage;
}

function repoDoCall($URL, $data){
	
	$ch = curl_init($URL);
	curl_setopt($ch, CURLOPT_URL, $URL);
	//curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	//curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type: text/plain')); 
	curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko Firefox/16.0');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
	$return=curl_exec($ch);
	
	return $return;
}


function doCall($URL, $data, $timeout=DEFAULT_MAX_CLIENT_REQUEST_TIMEOUT, $options=array()) //Needs a timeout handler
{	
	$SSLVerify = false;
	$URL = trim($URL);
	//if(stripos($URL, 'https://') !== false){ $SSLVerify = true; }
	
	$ch = curl_init($URL);
	curl_setopt($ch, CURLOPT_URL, $URL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
	curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko Firefox/16.0');
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, ($SSLVerify === true) ? 2 : false );
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $SSLVerify);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        
	if(defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
		curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
	}
	
	$contentType = 'application/x-www-form-urlencoded';
	if(!empty($options['contentType'])){
		$contentType = $options['contentType'];
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: '.trim($contentType))); //before array('Content-Type: text/plain') //multipart/form-data
	
	if(!empty($options['httpAuth'])){
		curl_setopt($ch, CURLOPT_USERPWD, $options['httpAuth']['username'].':'.$options['httpAuth']['password']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	}
	
	if(!empty($options['useCookie'])){
		
		$cookieFile = APP_ROOT.'/uploads/.'.sha1(APP_INSTALL_HASH.'WP_Cookie');
		if(!file_exists($cookieFile)){
			 @file_put_contents($cookieFile, '');
		}
		if(file_exists($cookieFile)){
			curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
			curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
		}
	}
	
	if (!ini_get('safe_mode') && !ini_get('open_basedir')){
		@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	}
	
	if($options['file'] == 'download' && !empty($options['filePath'])){
		$fp = fopen($options['filePath'], "w");
    	curl_setopt($ch, CURLOPT_FILE, $fp);	
	}
	
	if(!empty($data)){
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, base64_encode(serialize($data)));
	}
	
	$microtimeStarted 	= microtime(true);
	$response 			= curl_exec($ch);
	$microtimeEnded 	= microtime(true);
	
	$curlInfo = array();
	$curlInfo['info'] = curl_getinfo($ch);
	if(curl_errno($ch)){
		$curlInfo['errorNo'] = curl_errno($ch);
		$curlInfo['error'] = curl_error($ch);
	}
	
	curl_close($ch);
	
	if($options['file'] == 'download' && !empty($options['filePath'])){
		fclose($fp);
	}
	
	return array($response, $microtimeStarted, $microtimeEnded, $curlInfo);
}

function unserializeArray($strBetArray){
	if(empty($strBetArray) || !is_array($strBetArray)){ return false; }
	$newArray = array();
	foreach($strBetArray as $key => $value){
		$newArray[$key] = unserialize($value);
	}
	return $newArray;
}

function getStrBetAll($string, $startString, $endString)
{
	$betArray = array();
	while($string){
		list($strBet, $string) = getStrBet($string, $startString, $endString);
		if(!$strBet) break;
		$betArray[] = $strBet;
	}
	return $betArray;
}

function getStrBet($string, $startString, $endString)//note endstring must be after the start string
{
	if(!$startString) { $startPos = 0; }
	else{
		$startPos = strpos($string, $startString);
		if($startPos === false) { return false; }
		$startPos = $startPos + strlen($startString);
	}
	
	if(!$endString)
	{
		$strBet = substr($string, $startPos);
		return array($strBet, substr($string, strpos($string, $strBet)));
	}
	
	$endPos = strpos($string, $endString, $startPos);
	if(!$endPos) return false;
	
	$strBet = substr($string, $startPos, ($endPos - $startPos));
	return array($strBet, substr($string, $endPos+strlen($endString)));
}


function fixObject (&$object){
  if (!is_object ($object) && gettype ($object) == 'object')
	return ($object = unserialize (serialize ($object)));
  return $object;
}

function objectToArray($o) {
	if (is_object($o)) {
			$o = get_object_vars($o);
	}
	if (is_array($o)) {
		return array_map(__FUNCTION__, $o);
	}
	else {
		// Return array
		return $o;
	}
}


function callURLAsync($url, $params=array()){

    $post_params = array();
	foreach ($params as $key => &$val) {
      if (is_array($val)) $val = implode(',', $val);
        $post_params[] = $key.'='.urlencode($val);
    }
    $post_string = implode('&', $post_params);

    $parts = parse_url($url);
	$host = $parts['host'];

	if (($parts['scheme'] == 'ssl' || $parts['scheme'] == 'https') && extension_loaded('openssl')){
		$parts['host'] = "ssl://".$parts['host'];
		$parts['port'] = 443;
		error_reporting(0);
	}
	elseif($parts['port']==''){
		$parts['port'] = 80;
	}	
	  
    $fp = @fsockopen($parts['host'], $parts['port'], $errno, $errstr, 30);
	if(!$fp) return array('status' => false, 'resource' => !empty($fp) ? true : false, 'errorNo' => 'unable_to_intiate_fsock', 'error' => 'Unable to initiate FsockOpen');
	if($errno > 0) return array('status' => false, 'errorNo' => $errno, 'error' => $errno. ':' .$errstr);

	$settings = Reg::get('settings');

    $out = "POST ".$parts['path']." HTTP/1.0\r\n";
    $out.= "Host: ".$host."\r\n";
	if(!empty($settings['httpAuth']['username'])){
		$out.= "Authorization: Basic ".base64_encode($settings['httpAuth']['username'].':'.$settings['httpAuth']['password'])."\r\n";
	}
	$out.= "User-agent: " . "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko Firefox/16.0". "\r\n";
    $out.= "Content-Type: application/x-www-form-urlencoded\r\n"; 
    $out.= "Content-Length: ".strlen($post_string)."\r\n";
    $out.= "Connection: Close\r\n\r\n";
    if (isset($post_string)) $out.= $post_string;

    $is_written = fwrite($fp, $out);
	if(!$is_written){
		return array('status' => false, 'writable' => false);
	}
	
	/*if($settings['enableFsockFget'] == 1){
		fgets($fp, 128);
	}*/
	
    fclose($fp);
	return array('status' => true);
}

function fsockSameURLConnectCheck($url, $fget=true){
	
	if($fget){
		$params=array('check' =>  'sameURL');	
	}
	else{
		$fsockSameURLCheckUsingDBValue =  uniqid('fsock_', true);
		$params=array('check' =>  'sameURLUsingDB', 'fsockSameURLCheckUsingDBValue' => $fsockSameURLCheckUsingDBValue);
	}
	
	$post_params = array();
	foreach ($params as $key => &$val) {
      if (is_array($val)) $val = implode(',', $val);
        $post_params[] = $key.'='.urlencode($val);
    }
    $post_string = implode('&', $post_params);
	
	$parts = parse_url($url);
	$host = $parts['host'];

	if (($parts['scheme'] == 'ssl' || $parts['scheme'] == 'https') && extension_loaded('openssl')){
		$parts['host'] = "ssl://".$parts['host'];
		$parts['port'] = 443;
		error_reporting(0);
	}
	elseif($parts['port']==''){
		$parts['port'] = 80;
	}
	  
    $fp = @fsockopen($parts['host'], $parts['port'], $errno, $errstr, 30);
	if(!$fp) return array('status' => false, 'resource' => !empty($fp) ? true : false, 'errorNo' => 'unable_to_intiate_fsock', 'error' => 'Unable to initiate FsockOpen');
	if($errno > 0) return array('status' => false, 'errorNo' => $errno, 'error' => $errno. ':' .$errstr);

	$settings = Reg::get('settings');
	
    $out = "POST ".$parts['path']." HTTP/1.0\r\n";
    $out.= "Host: ".$host."\r\n";
	if(!empty($settings['httpAuth']['username'])){
		$out.= "Authorization: Basic ".base64_encode($settings['httpAuth']['username'].':'.$settings['httpAuth']['password'])."\r\n";
	}
	$out.= "User-agent: " . "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko Firefox/16.0". "\r\n";
    $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
    $out.= "Content-Length: ".strlen($post_string)."\r\n";
    $out.= "Connection: Close\r\n\r\n";
	
    if (isset($post_string)) $out.= $post_string;
	
    $is_written = fwrite($fp, $out);
	if(!$is_written){
		return array('status' => false, 'writable' => false, 'errorNo' => 'unable_to_write_request', 'error' => 'Unable to write request');
	}
	
	$temp = '';
	if($fget){		
		 while (!feof($fp)) {
			$temp .= fgets($fp, 128);
		}
	}
	
	fclose($fp);
	
	if($fget){
		if(strpos($temp, 'WWW-Authenticate:') !== false){
			return array('status' => false, 'errorNo' => 'authentication_required', 'error' => 'Your IWP Admin Panel has folder protection.<br><a onclick="$(\'#settings_btn\').click();$(\'#authUsername\').focus();">Set the credentials</a> in settings -> Folder protection.');
		}
		else{			
			return fsockSameURLConnectCheck($url, false);
		}
	}
	else{
		sleep(1);//due to fsock non-blocking mode we have to wait
		if(!empty($fsockSameURLCheckUsingDBValue) && $fsockSameURLCheckUsingDBValue == getOption('fsockSameURLCheckUsingDBValue')){
			return array('status' => true);
		}
		else{
			return array('status' => false, 'errorNo' => 'unable_to_verify', 'error' => 'Unable to verify content(method using DB)');
		}
	}
   
}

function filterParameters($array, $DBEscapeString=true){
  
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

function IPInRange($IP, $range) {

	if (strpos($range, '*') !==false) { // a.b.*.* format
	  // Just convert to A-B format by setting * to 0 for A and 255 for B
	  $lower = str_replace('*', '0', $range);
	  $upper = str_replace('*', '255', $range);
	  $range = "$lower-$upper";
	}
	
	if (strpos($range, '-')!==false) { // A-B format
	  list($lower, $upper) = explode('-', $range, 2);
	  $lowerDec = (float)sprintf("%u", ip2long($lower));
	  $upperDec = (float)sprintf("%u", ip2long($upper));
	  $IPDec = (float)sprintf("%u", ip2long($IP));
	  return ( ($IPDec>=$lowerDec) && ($IPDec<=$upperDec) );
	}
	if($IP == $range) return true;
	return false;
}

function ksortTree( &$array, $sortMaxLevel=-1, $currentLevel=0 )
{
  if((int)$sortMaxLevel > -1 && $sortMaxLevel <= $currentLevel){ return false;}
  
  if (!is_array($array)) {
    return false;
  }
 
  ksort($array);
  foreach ($array as $k=>$v) {
	$currentLevel++;
    ksortTree($array[$k], $sortMaxLevel, $currentLevel);
  }
  return true;
}

function trimValue(&$v){
	$v = trim($v);
}

function arrayMergeRecursiveNumericKeyHackFix(&$array){
	if(!is_array($array)){ return; }

	foreach($array as $key => $value){
		$finalKey = $key;
		$numKey = preg_replace("/[^0-9]/", '', $key);
		if($key == '_'.$numKey){
			unset($array[$key]);
			$array[$numKey] = $value;
			$finalKey = $numKey;
		}
		arrayMergeRecursiveNumericKeyHackFix($array[$finalKey]);
	}
	return;

}

function appErrorHandler($errno, $errstr,  $errfile, $errline, $errcontext )
{
   	if(!isset($GLOBALS['appErrorHandlerErrors'])){
		$GLOBALS['appErrorHandlerErrors'] = '';	
	}
    $GLOBALS['appErrorHandlerErrors'] .= @date('Y-m-d H:i:s')." ERR: errno:".$errno." (".$errstr.") file:".$errfile.", line:".$errline.".\r\n";
	//$GLOBALS['appErrorHandlerErrors'] .= ", context:".var_export($errcontext, 1)." \n\n";
	return false;
}

function appErrorHandlerWriteFile(){

	if(!empty($GLOBALS['appErrorHandlerErrors'])){
		@file_put_contents(APP_ROOT.'/appErrorLogs.txt', $GLOBALS['appErrorHandlerErrors'], FILE_APPEND);
		unset($GLOBALS['appErrorHandlerErrors']);
	}
}

set_error_handler('appErrorHandler', E_ERROR|E_WARNING|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR|E_COMPILE_WARNING);
@register_shutdown_function('appErrorHandlerWriteFile');

if (!function_exists('json_encode'))
{
  function json_encode($a=false)
  {
    if (is_null($a)) return 'null';
    if ($a === false) return 'false';
    if ($a === true) return 'true';
    if (is_scalar($a))
    {
      if (is_float($a))
      {
        // Always use "." for floats.
        return floatval(str_replace(",", ".", strval($a)));
      }

      if (is_string($a))
      {
        static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
        return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
      }
      else
        return $a;
    }
    $isList = true;
    for ($i = 0, reset($a); $i < count($a); $i++, next($a))
    {
      if (key($a) !== $i)
      {
        $isList = false;
        break;
      }
    }
    $result = array();
    if ($isList)
    {
      foreach ($a as $v) $result[] = json_encode($v);
      return '[' . join(',', $result) . ']';
    }
    else
    {
      foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
      return '{' . join(',', $result) . '}';
    }
  }
}


function downloadURL($URL, $filePath){
	
	return (fopenDownloadURL($URL, $filePath) || curlDownloadURL($URL, $filePath));

}

function curlDownloadURL($URL, $filePath){
	
	//$options = array('file' => 'download', 'filePath' => $filePath);
	//$callResponse = doCall($URL, '', 60, $options);
	
	$fp = fopen ($filePath, 'w');
	$ch = curl_init($URL);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	
	if (!ini_get('safe_mode') && !ini_get('open_basedir')){
		@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	}	
	$callResponse = curl_exec($ch);	
	curl_close($ch);
	fclose($fp);

	if($callResponse == 1){
		return true;
	}
	return false;
	
}

function fopenDownloadURL($URL, $filePath){
	
	 if (function_exists('ini_get') && ini_get('allow_url_fopen') == 1) {
		 $src = @fopen($URL, "r");
		 $dest = @fopen($filePath, 'wb');
		 if($src && $dest){
			 while ($content = @fread($src, 1024 * 1024)) {
				@fwrite($dest, $content);
			 }
    
			@fclose($src);
			@fclose($dest);
			return true;
		 }		
	 }
	 return false;
}



function protocolRedirect(){

	if(APP_HTTPS == 1 && $_SERVER['HTTPS'] != 'on'){
		header('Location: '.APP_URL);	
	}
	elseif(APP_HTTPS == 0 && $_SERVER['HTTPS'] == 'on'){
		header('Location: '.APP_URL);
	}
}

function checkOpenSSL(){
	if(!function_exists('openssl_verify')){
		return false;
	}
	else{
		$key = @openssl_pkey_new();
		@openssl_pkey_export($key, $privateKey);
		$privateKey	= base64_encode($privateKey);
		$publicKey = @openssl_pkey_get_details($key);
		$publicKey 	= $publicKey["key"];
		
		if(empty($publicKey) || empty($privateKey)){
			return false;
		}
	}
	return true;
}

function httpBuildURLCustom($parts){
	
	if(is_array($parts['query'])){
		$parts['query'] = http_build_query($parts['query']);
	}
	$URL = $parts['scheme'].'://'
		.($parts['user'] ? $parts['user'].':'.$parts['pass'].'@' : '')
		.$parts['host']
		.((!empty($parts['port']) && $parts['port'] != 80) ? ':'.$parts['port'] : '')
		.($parts['path'] ? $parts['path'] : '')
		.($parts['query'] ? '?'.$parts['query'] : '')
		.($parts['fragment'] ? '#'.$parts['fragment'] : '');
	return $URL;
}
?>