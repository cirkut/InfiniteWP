<?php
/************************************************************
* Credits - WordPress
 ************************************************************/

class filesystemFTPExt extends filesystemBase {
	var $link;
	var $errors = null;
	var $options = array();

	function __construct($opt='') {
		$this->method = 'FTPExt';
		//$this->errors = new WP_Error();

		//Check if possible to use ftp functions.
		if ( ! extension_loaded('ftp') ) {
			//$this->errors->add('no_ftp_ext', __('The ftp PHP extension is not available'));
			appUpdateMsg('The FTP PHP extension is not available', true);
			return false;
		}

		// Set defaults:
		//This Class uses the timeout on a per-connection basis, Others use it on a per-action basis.

		if ( ! defined('FS_TIMEOUT') )
			define('FS_TIMEOUT', 240);

		if ( empty($opt['port']) )
			$this->options['port'] = 21;
		else
			$this->options['port'] = $opt['port'];

		if ( empty($opt['hostname']) ){
			//$this->errors->add('empty_hostname', __('FTP hostname is required'));
			appUpdateMsg('FTP hostname is required');
		}
		else
			$this->options['hostname'] = $opt['hostname'];

		if ( ! empty($opt['base']) )
			$this->baseDir = $opt['base'];

		// Check if the options provided are OK.
		if ( empty($opt['username']) ){
			//$this->errors->add('empty_username', __('FTP username is required'));
			appUpdateMsg('FTP username is required');
		}
		else
			$this->options['username'] = $opt['username'];

		if ( empty($opt['password']) ){
			//$this->errors->add('empty_password', __('FTP password is required'));
			appUpdateMsg('FTP password is required');
		}
		else
			$this->options['password'] = $opt['password'];

		$this->options['ssl'] = false;
		if ( 'ftps' == $opt['connectionType'] )
			$this->options['ssl'] = true;
	}

	function connect() {
		if(!$this->options['hostname'] || !$this->options['username'] || !$this->options['password']){
			appUpdateMsg('<a href="http://infinitewp.com/knowledge-base/infinitewp-update-file-permission-error-add-ftp-to-config/" target="_blank">See how to add the FTP details</a>.');
			return false;
		}
		
		if ( isset($this->options['ssl']) && $this->options['ssl'] && function_exists('ftp_ssl_connect') )
			$this->link = @ftp_ssl_connect($this->options['hostname'], $this->options['port'], FS_CONNECT_TIMEOUT);
		else
			$this->link = @ftp_connect($this->options['hostname'], $this->options['port'], FS_CONNECT_TIMEOUT);

		if ( ! $this->link ) {
			//$this->errors->add('connect', sprintf(__('Failed to connect to FTP Server %1$s:%2$s'), $this->options['hostname'], $this->options['port']));
			appUpdateMsg(sprintf('Failed to connect to the FTP server "%1$s:%2$s"', $this->options['hostname'], $this->options['port']));
			return false;
		}

		if ( ! @ftp_login($this->link,$this->options['username'], $this->options['password']) ) {
			//$this->errors->add('auth', sprintf(__('Username/Password incorrect for %s'), $this->options['username']));
			appUpdateMsg(sprintf('FTP username or password incorrect for "%s"', $this->options['username']));
			return false;
		}

		//Set the Connection to use Passive FTP
		@ftp_pasv( $this->link, true );
		if ( @ftp_get_option($this->link, FTP_TIMEOUT_SEC) < FS_TIMEOUT )
			@ftp_set_option($this->link, FTP_TIMEOUT_SEC, FS_TIMEOUT);

		return true;
	}

	function getContents($file, $type = '', $resumePos = 0 ) {
		if ( empty($type) )
			$type = FTP_BINARY;

		$tempfile = getTempName($file);
		$temp = fopen($tempfile, 'w+');

		if ( ! $temp )
			return false;

		if ( ! @ftp_fget($this->link, $temp, $file, $type, $resumePos) )
			return false;

		fseek($temp, 0); //Skip back to the start of the file being written to
		$contents = '';

		while ( ! feof($temp) )
			$contents .= fread($temp, 8192);

		fclose($temp);
		unlink($tempfile);
		return $contents;
	}
	function getContentsArray($file) {
		return explode("\n", $this->getContents($file));
	}

	function putContents($file, $contents, $mode = false ) {
		$tempfile = getTempName($file);
		$temp = fopen($tempfile, 'w+');
		if ( ! $temp )
			return false;

		fwrite($temp, $contents);
		fseek($temp, 0); //Skip back to the start of the file being written to

		$type = isBinary($contents) ? FTP_BINARY : FTP_ASCII;
		$ret = @ftp_fput($this->link, $file, $temp, $type);

		fclose($temp);
		unlink($tempfile);

		$this->chmod($file, $mode);

		return $ret;
	}
	function cwd() {
		$cwd = @ftp_pwd($this->link);
		if ( $cwd )
			$cwd = addTrailingSlash($cwd);
		return $cwd;
	}
	function chdir($dir) {
		return @ftp_chdir($this->link, $dir);
	}
	function chgrp($file, $group, $recursive = false ) {
		return false;
	}
	function chmod($file, $mode = false, $recursive = false) {
		if ( ! $mode ) {
			if ( $this->isFile($file) )
				$mode = FS_CHMOD_FILE;
			elseif ( $this->isDir($file) )
				$mode = FS_CHMOD_DIR;
			else
				return false;
		}

		// chmod any sub-objects if recursive.
		if ( $recursive && $this->isDir($file) ) {
			$fileList = $this->dirList($file);
			foreach ( (array)$fileList as $fileName => $filemeta )
				$this->chmod($file . '/' . $fileName, $mode, $recursive);
		}

		// chmod the file or directory
		if ( ! function_exists('ftp_chmod') )
			return (bool)@ftp_site($this->link, sprintf('CHMOD %o %s', $mode, $file));
		return (bool)@ftp_chmod($this->link, $mode, $file);
	}
	function chown($file, $owner, $recursive = false ) {
		return false;
	}
	function owner($file) {
		$dir = $this->dirList($file);
		return $dir[$file]['owner'];
	}
	function getChmod($file) {
		$dir = $this->dirList($file);
		return $dir[$file]['permsn'];
	}
	function group($file) {
		$dir = $this->dirList($file);
		return $dir[$file]['group'];
	}
	function copy($source, $destination, $overwrite = false, $mode = false) {
		if ( ! $overwrite && $this->exists($destination) )
			return false;
		$content = $this->getContents($source);
		if ( false === $content)
			return false;
		return $this->putContents($destination, $content, $mode);
	}
	function move($source, $destination, $overwrite = false) {
		return ftp_rename($this->link, $source, $destination);
	}

	function delete($file, $recursive = false, $type = false) {
		if ( empty($file) )
			return false;
		if ( 'f' == $type || $this->isFile($file) )
			return @ftp_delete($this->link, $file);
		if ( !$recursive )
			return @ftp_rmdir($this->link, $file);

		$fileList = $this->dirList( addTrailingSlash($file) );
		if ( !empty($fileList) )
			foreach ( $fileList as $deleteFile )
				$this->delete( addTrailingSlash($file) . $deleteFile['name'], $recursive, $deleteFile['type'] );
		return @ftp_rmdir($this->link, $file);
	}

	function exists($file) {
		$list = @ftp_nlist($this->link, $file);
		return !empty($list); //empty list = no file, so invert.
	}
	function isFile($file) {
		return $this->exists($file) && !$this->isDir($file);
	}
	function isDir($path) {
		$cwd = $this->cwd();
		$result = @ftp_chdir($this->link, addTrailingSlash($path) );
		if ( $result && $path == $this->cwd() || $this->cwd() != $cwd ) {
			@ftp_chdir($this->link, $cwd);
			return true;
		}
		return false;
	}
	function isReadable($file) {
		//Get dir list, Check if the file is readable by the current user??
		return true;
	}
	function isWritable($file) {
		//Get dir list, Check if the file is writable by the current user??
		return true;
	}
	function atime($file) {
		return false;
	}
	function mtime($file) {
		return ftp_mdtm($this->link, $file);
	}
	function size($file) {
		return ftp_size($this->link, $file);
	}
	function touch($file, $time = 0, $atime = 0) {
		return false;
	}
	function mkDir($path, $chmod = false, $chown = false, $chgrp = false) {
		$path = removeTrailingSlash($path);
		if ( empty($path) )
			return false;

		if ( !@ftp_mkdir($this->link, $path) )
			return false;
		$this->chmod($path, $chmod);
		if ( $chown )
			$this->chown($path, $chown);
		if ( $chgrp )
			$this->chgrp($path, $chgrp);
		return true;
	}
	function rmDir($path, $recursive = false) {
		return $this->delete($path, $recursive);
	}

	function parseListing($line) {
		static $isWindows;
		if ( is_null($isWindows) )
			$isWindows = stripos( ftp_systype($this->link), 'win') !== false;

		if ( $isWindows && preg_match('/([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|<DIR>) +(.+)/', $line, $lucifer) ) {
			$b = array();
			if ( $lucifer[3] < 70 )
				$lucifer[3] +=2000;
			else
				$lucifer[3] += 1900; // 4digit year fix
			$b['isdir'] = ( $lucifer[7] == '<DIR>');
			if ( $b['isdir'] )
				$b['type'] = 'd';
			else
				$b['type'] = 'f';
			$b['size'] = $lucifer[7];
			$b['month'] = $lucifer[1];
			$b['day'] = $lucifer[2];
			$b['year'] = $lucifer[3];
			$b['hour'] = $lucifer[4];
			$b['minute'] = $lucifer[5];
			$b['time'] = @mktime($lucifer[4] + (strcasecmp($lucifer[6], "PM") == 0 ? 12 : 0), $lucifer[5], 0, $lucifer[1], $lucifer[2], $lucifer[3]);
			$b['am/pm'] = $lucifer[6];
			$b['name'] = $lucifer[8];
		} elseif ( !$isWindows && $lucifer = preg_split('/[ ]/', $line, 9, PREG_SPLIT_NO_EMPTY)) {
			//echo $line."\n";
			$lcount = count($lucifer);
			if ( $lcount < 8 )
				return '';
			$b = array();
			$b['isdir'] = $lucifer[0]{0} === 'd';
			$b['islink'] = $lucifer[0]{0} === 'l';
			if ( $b['isdir'] )
				$b['type'] = 'd';
			elseif ( $b['islink'] )
				$b['type'] = 'l';
			else
				$b['type'] = 'f';
			$b['perms'] = $lucifer[0];
			$b['number'] = $lucifer[1];
			$b['owner'] = $lucifer[2];
			$b['group'] = $lucifer[3];
			$b['size'] = $lucifer[4];
			if ( $lcount == 8 ) {
				sscanf($lucifer[5], '%d-%d-%d', $b['year'], $b['month'], $b['day']);
				sscanf($lucifer[6], '%d:%d', $b['hour'], $b['minute']);
				$b['time'] = @mktime($b['hour'], $b['minute'], 0, $b['month'], $b['day'], $b['year']);
				$b['name'] = $lucifer[7];
			} else {
				$b['month'] = $lucifer[5];
				$b['day'] = $lucifer[6];
				if ( preg_match('/([0-9]{2}):([0-9]{2})/', $lucifer[7], $l2) ) {
					$b['year'] = date("Y");
					$b['hour'] = $l2[1];
					$b['minute'] = $l2[2];
				} else {
					$b['year'] = $lucifer[7];
					$b['hour'] = 0;
					$b['minute'] = 0;
				}
				$b['time'] = strtotime( sprintf('%d %s %d %02d:%02d', $b['day'], $b['month'], $b['year'], $b['hour'], $b['minute']) );
				$b['name'] = $lucifer[8];
			}
		}

		return $b;
	}

	function dirList($path = '.', $includeHidden = true, $recursive = false) {
		if ( $this->isFile($path) ) {
			$limitFile = basename($path);
			$path = dirname($path) . '/';
		} else {
			$limitFile = false;
		}

		$pwd = @ftp_pwd($this->link);
		if ( ! @ftp_chdir($this->link, $path) ) // Cant change to folder = folder doesn't exist
			return false;
		$list = @ftp_rawlist($this->link, '-a', false);
		@ftp_chdir($this->link, $pwd);

		if ( empty($list) ) // Empty array = non-existent folder (real folder will show . at least)
			return false;

		$dirList = array();
		foreach ( $list as $k => $v ) {
			$entry = $this->parseListing($v);
			if ( empty($entry) )
				continue;

			if ( '.' == $entry['name'] || '..' == $entry['name'] )
				continue;

			if ( ! $includeHidden && '.' == $entry['name'][0] )
				continue;

			if ( $limitFile && $entry['name'] != $limitFile)
				continue;

			$dirList[ $entry['name'] ] = $entry;
		}

		$ret = array();
		foreach ( (array)$dirList as $struc ) {
			if ( 'd' == $struc['type'] ) {
				if ( $recursive )
					$struc['files'] = $this->dirList($path . '/' . $struc['name'], $includeHidden, $recursive);
				else
					$struc['files'] = array();
			}

			$ret[ $struc['name'] ] = $struc;
		}
		return $ret;
	}

	function __destruct() {
		if ( $this->link )
			ftp_close($this->link);
	}
}
