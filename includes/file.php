<?php
/************************************************************
* Credits - WordPress
 ************************************************************/

function addTrailingSlash($string) {
	return removeTrailingSlash($string) . '/';
}

function removeTrailingSlash($string) {
	return rtrim($string, '/');
}

function isBinary( $text ) {
	return (bool) preg_match('|[^\x20-\x7E]|', $text); //chr(32)..chr(127)
}

function getTempName($fileName = '', $dir = '') {
	if ( empty($dir) )
		$dir = getTempDir();
	$fileName = basename($fileName);
	if ( empty($fileName) )
		$fileName = time();

	$fileName = preg_replace('|\..*$|', '.tmp', $fileName);
	$fileName = $dir . getUniqueFileName($dir, $fileName);
	touch($fileName);
	return $fileName;
}

function getUniqueFileName( $dir, $fileName) {

	// separate the fileName into a name and extension
	$info = pathinfo($fileName);
	$ext = !empty($info['extension']) ? '.' . $info['extension'] : '';
	$name = basename($fileName, $ext);

	// edge case: if file is named '.ext', treat as an empty name
	if ( $name === $ext )
		$name = '';

	// Increment the file number until we have a unique file to save in $dir. Use callback if supplied.
	
	$number = '';

	// change '.ext' to lower case
	if ( $ext && strtolower($ext) != $ext ) {
		$ext2 = strtolower($ext);
		$fileName2 = preg_replace( '|' . preg_quote($ext) . '$|', $ext2, $fileName );

		// check for both lower and upper case extension or image sub-sizes may be overwritten
		while ( file_exists($dir . "/$fileName") || file_exists($dir . "/$fileName2") ) {
			$newNumber = $number + 1;
			$fileName = str_replace( "$number$ext", "$newNumber$ext", $fileName );
			$fileName2 = str_replace( "$number$ext2", "$newNumber$ext2", $fileName2 );
			$number = $newNumber;
		}
		return $fileName2;
	}

	while ( file_exists( $dir . "/$fileName" ) ) {
		if ( '' == "$number$ext" )
			$fileName = $fileName . ++$number . $ext;
		else
			$fileName = str_replace( "$number$ext", ++$number . $ext, $fileName );
	}
	

	return $fileName;
}

function getTempDir() {
	static $temp;
	if ( defined('TEMP_DIR') )
		return addTrailingSlash(TEMP_DIR);

	if ( $temp )
		return addTrailingSlash($temp);

	$temp = APP_ROOT . '/updates/';
	if ( is_dir($temp) && @is_writable($temp) )
		return $temp;

	if  ( function_exists('sys_get_temp_dir') ) {
		$temp = sys_get_temp_dir();
		if ( @is_writable($temp) )
			return addTrailingSlash($temp);
	}

	$temp = ini_get('upload_tmp_dir');
	if ( is_dir($temp) && @is_writable($temp) )
		return addTrailingSlash($temp);

	$temp = '/tmp/';
	return $temp;
}

function getFileSystemMethod($args = array(), $context = false) {
	$method = defined('FS_METHOD') ? FS_METHOD : false; //Please ensure that this is either 'direct', 'ssh', 'ftpext' or 'ftpsockets'

	if ( ! $method && function_exists('getmyuid') && function_exists('fileowner') ){
		if ( !$context )
			$context = APP_ROOT.'/updates';
		$context = addTrailingSlash($context);
		$tempFileName = $context . 'tempWriteTest_' . time();
		$tempHandle = @fopen($tempFileName, 'w');
		if ( $tempHandle ) {
			if ( getmyuid() == @fileowner($tempFileName) )
				$method = 'direct';
			@fclose($tempHandle);
			@unlink($tempFileName);
		}
 	}

	//if ( ! $method && isset($args['connectionType']) && 'ssh' == $args['connectionType'] && extension_loaded('ssh2') && function_exists('stream_get_contents') ) $method = 'ssh2';
	if ( ! $method && defined('APP_FTP_USE_SFTP') && APP_FTP_USE_SFTP == 1 ) $method = 'SFTPExt';
	if ( ! $method && extension_loaded('ftp') ) $method = 'FTPExt';
	//if ( ! $method && ( extension_loaded('sockets') || function_exists('fsockopen') ) ) $method = 'ftpsockets'; //Sockets: Socket extension; PHP Mode: FSockopen / fwrite / fread
	return $method;
}

function initFileSystem($args = false, $context = false){
	
	if(empty($args)){
		$args = array('hostname' => APP_FTP_HOST, 'port' => APP_FTP_PORT, 'username' => APP_FTP_USER, 'password' => APP_FTP_PASS, 'base' => APP_FTP_BASE, 'connectionType' => (defined('APP_FTP_SSL') && APP_FTP_SSL) ? 'ftps' : '');
	}

	require_once(APP_ROOT . '/includes/fileSystemBase.php');

	$method = getFileSystemMethod($args, $context);

	if (!$method)
		return false;

	if (!class_exists("fileSystem".ucfirst($method))){
		require_once(APP_ROOT . '/includes/fileSystem'.ucfirst($method).'.php');
	}
	$method = "fileSystem".ucfirst($method);
        
        $methodStr = 'direct';
        if($method=="SFTPExt") {
            $methodStr = 'SFTP';
        }elseif($method=="FTPExt") {
            $methodStr = 'FTP';
        }
	
	appUpdateMsg('Using '.$methodStr.' file system..');
	
	$GLOBALS['FileSystemObj'] = new $method($args);

	//Define the timeouts for the connections. Only available after the construct is called to allow for per-transport overriding of the default.
	if ( ! defined('FS_CONNECT_TIMEOUT') )
		define('FS_CONNECT_TIMEOUT', 30);
	if ( ! defined('FS_TIMEOUT') )
		define('FS_TIMEOUT', 30);

	//if ( is_error($FileSystemObj->errors) && $FileSystemObj->errors->get_error_code() )
	//	return false;

	if ( !$GLOBALS['FileSystemObj']->connect() )
		return false; //There was an error connecting to the server.

	// Set the permission constants if not already set.
	if ( ! defined('FS_CHMOD_DIR') )
		define('FS_CHMOD_DIR', 0755 );
	if ( ! defined('FS_CHMOD_FILE') )
		define('FS_CHMOD_FILE', 0644 );

	return true;
}

