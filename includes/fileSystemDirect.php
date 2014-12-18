<?php
/************************************************************
* Credits - WordPress
 ************************************************************/

class filesystemDirect extends filesystemBase {

	function __construct($arg) {
		$this->method = 'direct';
		//$this->errors = new WP_Error();
		
	}

	function connect() {
		return true;
	}
	/**
	 * Reads entire file into a string
	 *
	 * @param string $file Name of the file to read.
	 * @return string|bool The function returns the read data or false on failure.
	 */
	function getContents($file) {
		return @file_get_contents($file);
	}
	/**
	 * Reads entire file into an array
	 *
	 * @param string $file Path to the file.
	 * @return array|bool the file contents in an array or false on failure.
	 */
	function getContentsArray($file) {
		return @file($file);
	}
	/**
	 * Write a string to a file
	 *
	 * @param string $file Remote path to the file where to write the data.
	 * @param string $contents The data to write.
	 * @param int $mode (optional) The file permissions as octal number, usually 0644.
	 * @return bool False upon failure.
	 */
	function putContents($file, $contents, $mode = false ) {
		if ( ! ($fp = @fopen($file, 'w')) )
			return false;
		@fwrite($fp, $contents);
		@fclose($fp);
		$this->chmod($file, $mode);
		return true;
	}
	/**
	 * Gets the current working directory
	 *
	 * @return string|bool the current working directory on success, or false on failure.
	 */
	function cwd() {
		return @getcwd();
	}
	/**
	 * Change directory
	 *
	 * @param string $dir The new current directory.
	 * @return bool Returns true on success or false on failure.
	 */
	function chdir($dir) {
		return @chdir($dir);
	}
	/**
	 * Changes file group
	 *
	 * @param string $file Path to the file.
	 * @param mixed $group A group name or number.
	 * @param bool $recursive (optional) If set True changes file group recursively. Defaults to False.
	 * @return bool Returns true on success or false on failure.
	 */
	function chgrp($file, $group, $recursive = false) {
		if ( ! $this->exists($file) )
			return false;
		if ( ! $recursive )
			return @chgrp($file, $group);
		if ( ! $this->isDir($file) )
			return @chgrp($file, $group);
		//Is a directory, and we want recursive
		$file = addTrailingSlash($file);
		$fileList = $this->dirList($file);
		foreach ($fileList as $fileName)
			$this->chgrp($file . $fileName, $group, $recursive);

		return true;
	}
	/**
	 * Changes filesystem permissions
	 *
	 * @param string $file Path to the file.
	 * @param int $mode (optional) The permissions as octal number, usually 0644 for files, 0755 for dirs.
	 * @param bool $recursive (optional) If set True changes file group recursively. Defaults to False.
	 * @return bool Returns true on success or false on failure.
	 */
	function chmod($file, $mode = false, $recursive = false) {
		if ( ! $mode ) {
			if ( $this->isFile($file) )
				$mode = FS_CHMOD_FILE;
			elseif ( $this->isDir($file) )
				$mode = FS_CHMOD_DIR;
			else
				return false;
		}

		if ( ! $recursive || ! $this->isDir($file) )
			return @chmod($file, $mode);
		//Is a directory, and we want recursive
		$file = addTrailingSlash($file);
		$fileList = $this->dirList($file);
		foreach ( (array)$fileList as $fileName => $filemeta)
			$this->chmod($file . $fileName, $mode, $recursive);

		return true;
	}
	/**
	 * Changes file owner
	 *
	 * @param string $file Path to the file.
	 * @param mixed $owner A user name or number.
	 * @param bool $recursive (optional) If set True changes file owner recursively. Defaults to False.
	 * @return bool Returns true on success or false on failure.
	 */
	function chown($file, $owner, $recursive = false) {
		if ( ! $this->exists($file) )
			return false;
		if ( ! $recursive )
			return @chown($file, $owner);
		if ( ! $this->isDir($file) )
			return @chown($file, $owner);
		//Is a directory, and we want recursive
		$fileList = $this->dirList($file);
		foreach ($fileList as $fileName) {
			$this->chown($file . '/' . $fileName, $owner, $recursive);
		}
		return true;
	}
	/**
	 * Gets file owner
	 *
	 * @param string $file Path to the file.
	 * @return string Username of the user.
	 */
	function owner($file) {
		$owneruid = @fileowner($file);
		if ( ! $owneruid )
			return false;
		if ( ! function_exists('posix_getpwuid') )
			return $owneruid;
		$ownerarray = posix_getpwuid($owneruid);
		return $ownerarray['name'];
	}
	/**
	 * Gets file permissions
	 *
	 * FIXME does not handle errors in fileperms()
	 *
	 * @param string $file Path to the file.
	 * @return string Mode of the file (last 4 digits).
	 */
	function getChmod($file) {
		return substr(decoct(@fileperms($file)),3);
	}
	function group($file) {
		$gid = @filegroup($file);
		if ( ! $gid )
			return false;
		if ( ! function_exists('posix_getgrgid') )
			return $gid;
		$grouparray = posix_getgrgid($gid);
		return $grouparray['name'];
	}

	function copy($source, $destination, $overwrite = false, $mode = false) {
		if ( ! $overwrite && $this->exists($destination) )
			return false;

		$rtval = copy($source, $destination);
		if ( $mode )
			$this->chmod($destination, $mode);
		return $rtval;
	}

	function move($source, $destination, $overwrite = false) {
		if ( ! $overwrite && $this->exists($destination) )
			return false;

		// try using rename first. if that fails (for example, source is read only) try copy
		if ( @rename($source, $destination) )
			return true;

		if ( $this->copy($source, $destination, $overwrite) && $this->exists($destination) ) {
			$this->delete($source);
			return true;
		} else {
			return false;
		}
	}

	function delete($file, $recursive = false, $type = false) {
		if ( empty($file) ) //Some filesystems report this as /, which can cause non-expected recursive deletion of all files in the filesystem.
			return false;
		$file = str_replace('\\', '/', $file); //for win32, occasional problems deleting files otherwise

		if ( 'f' == $type || $this->isFile($file) )
			return @unlink($file);
		if ( ! $recursive && $this->isDir($file) )
			return @rmdir($file);

		//At this point its a folder, and we're in recursive mode
		$file = addTrailingSlash($file);
		$fileList = $this->dirList($file, true);

		$retval = true;
		if ( is_array($fileList) ) //false if no files, So check first.
			foreach ($fileList as $fileName => $fileinfo)
				if ( ! $this->delete($file . $fileName, $recursive, $fileinfo['type']) )
					$retval = false;

		if ( file_exists($file) && ! @rmdir($file) )
			$retval = false;
		return $retval;
	}

	function exists($file) {
		return @file_exists($file);
	}

	function isFile($file) {
		return @is_file($file);
	}

	function isDir($path) {
		return @is_dir($path);
	}

	function isReadable($file) {
		return @is_readable($file);
	}

	function isWritable($file) {
		return @is_writable($file);
	}

	function atime($file) {
		return @fileatime($file);
	}

	function mtime($file) {
		return @filemtime($file);
	}
	function size($file) {
		return @filesize($file);
	}

	function touch($file, $time = 0, $atime = 0) {
		if ($time == 0)
			$time = time();
		if ($atime == 0)
			$atime = time();
		return @touch($file, $time, $atime);
	}

	function mkDir($path, $chmod = false, $chown = false, $chgrp = false) {
		// safe mode fails with a trailing slash under certain PHP versions.
		$path = removeTrailingSlash($path);
		if ( empty($path) )
			return false;

		if ( ! $chmod )
			$chmod = FS_CHMOD_DIR;

		if ( ! @mkdir($path) )
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

	function dirList($path, $includeHidden = true, $recursive = false) {
		if ( $this->isFile($path) ) {
			$limitFile = basename($path);
			$path = dirname($path);
		} else {
			$limitFile = false;
		}

		if ( ! $this->isDir($path) )
			return false; //$path should be a directory, return false on nor directory or path wrong.

		$dir = @dir($path);
		if ( ! $dir )
			return false; // tries to open the directory, return false on failure.

		$ret = array();

		while (false !== ($entry = $dir->read()) ) {
			$struc = array();
			$struc['name'] = $entry;

			if ( '.' == $struc['name'] || '..' == $struc['name'] )
				continue;

			if ( ! $includeHidden && '.' == $struc['name'][0] )
				continue;

			if ( $limitFile && $struc['name'] != $limitFile)
				continue;

			$struc['perms'] 	= $this->getHChmod($path.'/'.$entry);
			$struc['permsn']	= $this->getNumChmodFromH($struc['perms']);
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
					$struc['files'] = $this->dirList($path . '/' . $struc['name'], $includeHidden, $recursive);
				else
					$struc['files'] = array();
			}

			$ret[ $struc['name'] ] = $struc;
		}
		$dir->close();
		unset($dir);
		return $ret;
	}
}
