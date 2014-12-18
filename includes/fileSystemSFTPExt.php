<?php

/************************************************************
* Credits - WordPress
 ************************************************************/

class filesystemSFTPExt extends filesystemBase {

	var $link = false;
	var $sftp_link = false;
	var $keys = false;
	var $options = array();

	function __construct($opt='') {
		$this->method = 'sftp';

                $path = APP_ROOT.'/lib/phpseclib';
                set_include_path(get_include_path() . PATH_SEPARATOR . $path);
                include_once('Net/SFTP.php');
                
		//$this->errors = new WP_Error();

		// Set defaults:
		if ( empty($opt['port']) )
			$this->options['port'] = 22;
		else
			$this->options['port'] = $opt['port'];

		if ( empty($opt['hostname']) )
			//$this->errors->add('empty_hostname', __('SSH2 hostname is required'));
                        appUpdateMsg('SSH2 hostname is required', true);
		else
			$this->options['hostname'] = $opt['hostname'];

		if ( ! empty($opt['base']) )
			$this->wp_base = $opt['base'];

		if ( empty ($opt['username']) ) {
			//$this->errors->add('empty_username', __('SSH2 username is required'));
                        appUpdateMsg('SFTP username is required', true);
		}

		if ( !empty($opt['username']) )
			$this->options['username'] = $opt['username'];

		if ( empty ($opt['password']) ) {
			if ( !$this->keys )	//password can be blank if we are using keys
				//$this->errors->add('empty_password', __('SSH2 password is required'));
                                appUpdateMsg('SSH2 password is required', true);
		} else {
			$this->options['password'] = $opt['password'];
		}

	}

	function connect() {
		$this->link = new Net_SFTP($this->options['hostname'], $this->options['port']);

		if ( ! $this->link ) {
			//$this->errors->add('connect', sprintf(__('Failed to connect to SSH2 Server %1$s:%2$s'), $this->options['hostname'], $this->options['port']));
                        appUpdateMsg(sprintf('Failed to connect to SSH2 Server %1$s:%2$s', $this->options['hostname'], $this->options['port']), true);
			return false;
		}
		if ( ! $this->link->login($this->options['username'], $this->options['password']) ) {
                    appUpdateMsg(sprintf('Username/Password incorrect for %s', $this->options['username']), true);
                    return false;
		}

		return true;
	}

	function runCommand( $command, $returnbool = false) {

		$validSFTPCommands = array(
		/*
		sftp CLI commands:
		 'cd',
		 'chgrp',
		 'chmod',
		 'chown',
		 'df',
		 'get',
		 'ln',
		 'ls',
		 'mkdir',
		 'put',
		 'pwd',
		 'rename',
		 'rm',
		 'rmdir',
		 'symlink'
		*/
		// Available Net_SFTP commands:
		 'pwd',
		 'chmod', // ignored though
		 'chgrp', // ignored though
		 'chown'  // ignored though
		);
		if ( ! $this->link )
			return false;
                $cmdline = preg_split('/[[:blank:]]+/', $command);
		if ( ! in_array(($cmd=$cmdline[0]), $validSFTPCommands) )
			return false;
                if (substr($cmd, 0, 2) == 'ch') return true;
		$data = $this->link->$cmd();
		if ( $returnbool )
			return ( $data === false ) ? false : '' != trim($data);
		else
			return $data;
	}

        // strip FTP_BASE part of path; reduce to relative path
	function fixPath($file) {
		if (defined('FTP_BASE')) {
		        if (substr($file, 0, ($l=strlen(FTP_BASE))) == FTP_BASE)
	        		$file = ltrim(substr($file, $l), '/');
		}
		return $file;
	}
        
	function getContents( $file ) {
		return $this->link->get($this->fixPath($file));
	}

	function getContentsArray($file) {
		return preg_split("/\n+/", $this->getContents($file));
	}

	function putContents($file, $contents, $mode = false ) {
		$file = $this->fixPath($file);
		$ret = $this->link->put($file, $contents);
		if ($mode !== false) $this->link->chmod($mode, $file);
		return false !== $ret;

	}

	function cwd() {
		$cwd = $this->runCommand('pwd');
		if ( $cwd )
			$cwd = addTrailingSlash($cwd);
		return $cwd;
	}

	function chdir($dir) {
		return $this->link->chdir($this->fixPath($dir));
	}

	function chgrp($file, $group, $recursive = false ) {
		return true; // not supported
	}

	function chmod($file, $mode = false, $recursive = false) {
		return true; // SFTP does support chmod, better though to configure the right (default) permissions on the server side
	}

	/**
	 * Change the ownership of a file / folder.
	 *
	 * @since Unknown
	 *
	 * @param string $file    Path to the file.
	 * @param mixed  $owner   A user name or number.
	 * @param bool $recursive Optional. If set True changes file owner recursivly. Defaults to False.
	 * @return bool Returns true on success or false on failure.
	 */
	function chown( $file, $owner, $recursive = false ) {
		return true; // not supported
	}
        
        function stat($file) {
	        $file = $this->fixPath($file);
        	$stat = $this->link->stat($file);
        	if ($stat !== false) {
        	  if (!isset($stat['permissions'])) {
        	    return false;
                  }
        	  $stat['size'] = $this->link->size($file);
                }
                else {
                }
        	return $stat;
        }

	function owner($file) {
		$stat = $this->stat($file);
		if ( ! $stat )
			return false;
		if ( ! isset($stat['uid']) )
			return false;
		$owneruid = $stat['uid'];
		if ( ! function_exists('posix_getpwuid') )
			return $owneruid;
		$ownerarray = posix_getpwuid($owneruid);
		return $ownerarray['name'];
	}

	function getchmod($file) {
		$stat = $this->stat($file);
		return substr(($stat['permissions'] & 000777), -3);
	}

	function group($file) {
		$stat = $this->stat($file);
		if ( ! $stat )
			return false;
		if ( ! isset($stat['gid']) )
			return false;
		$ownergid = $stat['gid'];
		if ( ! function_exists('posix_getgrgid') )
			return $gid;
		$grouparray = posix_getgrgid($ownergid);
		return $grouparray['name'];
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
		return $this->link->rename($this->fixPath($source), $this->fixPath($destination));
	}


	function delete($file, $recursive = false, $type = false) {
                $file = $this->fixPath($file);
		if ( 'f' === $type || $this->isFile($file) ) {
			return $this->link->delete($file);
                }
		if ( ! $recursive ) {
			 return $this->link->rmdir($file);
                }
		//At this point its a folder, and we're in recursive mode
		$file = addTrailingSlash($file);
		$filelist = $this->dirlist($file, true);
		$retval = true;
		if ( is_array($filelist) ) //false if no files, So check first.
			foreach ($filelist as $filename => $fileinfo)
				if ( ! $this->delete($file . $filename, $recursive, $fileinfo['type']) )
					$retval = false;

		if ( $this->exists($file) && ! $this->link->rmdir($file) )
			$retval = false;
		return $retval;
	}


	function exists($file) {
		return $this->stat($file) !== false;
	}
        
        function S_ISDIR($stat) {
		return( ($stat['permissions'] & 040000) == 040000 );
	}
	

	function S_ISREG($stat) {
		return( ($stat['permissions'] & 0100000) == 0100000 );
	}

	function isFile($file) {
		return $this->S_ISREG($this->stat($file));
	}


	function isDir($path) {
		return $this->S_ISDIR($this->stat($path));
	}


	function isReadable($file) {
		$stat = $this->stat($file);
		$perms = $stat['permissions'];
		return ($perms & 0x000400);
	}


	function isWritable($file) {
		$stat = $this->stat($file);
		$perms = $stat['permissions'];
		return ($perms & 0x000200);
	}


	function atime($file) {
		$stat = $this->stat($file);
		return $stat['atime'];
	}


	function mtime($file) {
		$stat = $this->stat($file);
		return $stat['mtime'];
	}

	function size($file) {
		$stat = $this->stat($file);
		return $stat['size'];
	}


	function touch($file, $time = 0, $atime = 0) {
		//Not implemented.
	}


	function mkdir($path, $chmod = false, $chown = false, $chgrp = false) {
		$path = removeTrailingSlash($this->fixPath($path));
		if ( empty($path) )
			return false;
		return $this->link->mkdir($path);
	}


	function rmdir($path, $recursive = false) {
		return $this->delete($path, $recursive);
	}

	function dirlist($path, $include_hidden = true, $recursive = false) {
		if ( $this->isFile($path) ) {
			$limit_file = basename($path);
			$path = dirname($path);
		} else {
			$limit_file = false;
		}

		if ( ! $this->isDir($path) )
			return false;

		$ret = array();
		$curdir = $this->fixPath($path);
		$dir = $this->link->nlist($curdir);

		if ( ! $dir )
			return false;

		foreach ($dir as $entry) {
			$struc = $this->stat($curdir.'/'.$entry);
			$struc['name'] = $entry;


			if ( '.' == $struc['name'] || '..' == $struc['name'] )
				continue; //Do not care about these folders.

			if ( ! $include_hidden && '.' == $struc['name'][0] )
				continue;

			if ( $limit_file && $struc['name'] != $limit_file )
				continue;

			$struc['perms'] 	= $this->gethchmod($path.'/'.$entry);
			$struc['permsn']	= $struc['permissions'] & 000777;
			$struc['number'] 	= false;
			$struc['owner']    	= $this->owner($path.'/'.$entry);
			$struc['group']    	= $this->group($path.'/'.$entry);
			$struc['size']    	= $this->size($path.'/'.$entry);
			$struc['lastmodunix']= $this->mtime($path.'/'.$entry);
			$struc['lastmod']   = date('M j',$struc['lastmodunix']);
			$struc['time']    	= date('h:i:s',$struc['lastmodunix']);
			$struc['type']		= $this->isDir($path.'/'.$entry) ? 'd' : 'f';

			if ( 'd' == $struc['type'] ) {
				if ( $recursive )
					$struc['files'] = $this->dirlist($path . '/' . $struc['name'], $include_hidden, $recursive);
				else
					$struc['files'] = array();
			}

			$ret[ $struc['name'] ] = $struc;
		}
		return $ret;
	}
	

	function lastError() {
	  return $this->link->getLastSFTPError();
	}


	function getErrors() {
	  return $this->link->getSFTPErrors();
	}
}