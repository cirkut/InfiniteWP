<?php
/************************************************************
* Credits - WordPress
 ************************************************************/

class filesystemBase {
	
	var $verbose = false;
	
	var $cache = array();
	
	var $method = '';
	
	/**
	*to fetch the filesystem method.
	*/
	function  getMethod(){
		 return $this->method;
	 }
	 

	/**
	 * Locates a folder on the remote filesystem.
	 *
	 * Assumes that on Windows systems, Stripping off the Drive letter is OK
	 * Sanitizes \\ to / in windows filepaths.
	 *
	 * @access public
	 *
	 * @param string $folder the folder to locate
	 * @return string The location of the remote path.
	 */
	 
	function findFolder($folder) {

		if ( ( stripos($this->method, 'ftp') !== false) || 'ssh2' == $this->method || 'sftp' == $this->method ) {
			$folder = str_replace(APP_ROOT, APP_FTP_BASE, $folder);
			$folder = str_replace('//', '/', $folder);//removing any // in the path
			return addTrailingSlash($folder);
		} elseif ( 'direct' == $this->method ) {
			$folder = str_replace('\\', '/', $folder); //Windows path sanitisation
			return addTrailingSlash($folder);
		}
		return false;
	}
	/**
	 * Returns the *nix style file permissions for a file
	 *
	 * From the PHP documentation page for fileperms()
	 *
	 * @link http://docs.php.net/fileperms
	 * @access public
	 *
	 * @param string $file string filename
	 * @return int octal representation of permissions
	 */
	function getHChmod($file){
		$perms = $this->getChmod($file);
		if (($perms & 0xC000) == 0xC000) // Socket
			$info = 's';
		elseif (($perms & 0xA000) == 0xA000) // Symbolic Link
			$info = 'l';
		elseif (($perms & 0x8000) == 0x8000) // Regular
			$info = '-';
		elseif (($perms & 0x6000) == 0x6000) // Block special
			$info = 'b';
		elseif (($perms & 0x4000) == 0x4000) // Directory
			$info = 'd';
		elseif (($perms & 0x2000) == 0x2000) // Character special
			$info = 'c';
		elseif (($perms & 0x1000) == 0x1000) // FIFO pipe
			$info = 'p';
		else // Unknown
			$info = 'u';

		// Owner
		$info .= (($perms & 0x0100) ? 'r' : '-');
		$info .= (($perms & 0x0080) ? 'w' : '-');
		$info .= (($perms & 0x0040) ?
					(($perms & 0x0800) ? 's' : 'x' ) :
					(($perms & 0x0800) ? 'S' : '-'));

		// Group
		$info .= (($perms & 0x0020) ? 'r' : '-');
		$info .= (($perms & 0x0010) ? 'w' : '-');
		$info .= (($perms & 0x0008) ?
					(($perms & 0x0400) ? 's' : 'x' ) :
					(($perms & 0x0400) ? 'S' : '-'));

		// World
		$info .= (($perms & 0x0004) ? 'r' : '-');
		$info .= (($perms & 0x0002) ? 'w' : '-');
		$info .= (($perms & 0x0001) ?
					(($perms & 0x0200) ? 't' : 'x' ) :
					(($perms & 0x0200) ? 'T' : '-'));
		return $info;
	}

	/**
	 * Converts *nix style file permissions to a octal number.
	 *
	 * Converts '-rw-r--r--' to 0644
	 * From "info at rvgate dot nl"'s comment on the PHP documentation for chmod()
 	 *
	 * @link http://docs.php.net/manual/en/function.chmod.php#49614
	 * @access public
	 *
	 * @param string $mode string *nix style file permission
	 * @return int octal representation
	 */
	function getNumChmodFromH($mode) {
		$realMode = '';
		$legal =  array('', 'w', 'r', 'x', '-');
		$attArray = preg_split('//', $mode);

		for ($i=0; $i < count($attArray); $i++)
		   if ($key = array_search($attArray[$i], $legal))
			   $realMode .= $legal[$key];

		$mode = str_pad($realMode, 9, '-');
		$trans = array('-'=>'0', 'r'=>'4', 'w'=>'2', 'x'=>'1');
		$mode = strtr($mode,$trans);

		$newmode = '';
		$newmode .= $mode[0] + $mode[1] + $mode[2];
		$newmode .= $mode[3] + $mode[4] + $mode[5];
		$newmode .= $mode[6] + $mode[7] + $mode[8];
		return $newmode;
	}
	
	
	
	/**
	 * Unzips a specified ZIP file to a location on the Filesystem via the WordPress Filesystem Abstraction.
	 * Does not extract a root-level __MACOSX directory, if present.
	 *
	 * Attempts to increase the PHP Memory limit to 256M before uncompressing,
	 * However, The most memory required shouldn't be much larger than the Archive itself.
	 *
	 *
	 * @param string $file Full path and filename of zip archive
	 * @param string $to Full path on the filesystem to extract archive to
	 * @return mixed WP_Error on failure, True on success
	 */
	function unZipFile($file, $to) {
	
		if ( ! $GLOBALS['FileSystemObj'] || !is_object($GLOBALS['FileSystemObj']) ){
			//return new WP_Error('fs_unavailable', __('Could not access filesystem.'));
			appUpdateMsg('Could not access file system.', true);
		}
	
		// Unzip can use a lot of memory, but not this much hopefully
		@ini_set('memory_limit', '256M');
	
		$neededDirs = array();
		$to = addTrailingSlash($to);
	
		// Determine any parent dir's needed (of the upgrade directory)
		if ( ! $GLOBALS['FileSystemObj']->isDir($to) ) { //Only do parents if no children exist
			$path = preg_split('![/\\\]!', removeTrailingSlash($to));
			for ( $i = count($path); $i >= 0; $i-- ) {
				if ( empty($path[$i]) )
					continue;
	
				$dir = implode('/', array_slice($path, 0, $i+1) );
				if ( preg_match('!^[a-z]:$!i', $dir) ) // Skip it if it looks like a Windows Drive letter.
					continue;
	
				if ( ! $GLOBALS['FileSystemObj']->isDir($dir) )
					$neededDirs[] = $dir;
				else
					break; // A folder exists, therefor, we dont need the check the levels below this
			}
		}
	
		if ( class_exists('ZipArchive') ) {
			$result = $this->zipArchiveUnZip($file, $to, $neededDirs);
			if ( true === $result ) {
				return $result;
			} elseif ( $result != 'incompatible_archive' ) {
				return $result;
			}
		}
		// Fall through to PclZip if ZipArchive is not available, or encountered an error opening the file.
		return $this->pclZipUnZip($file, $to, $neededDirs);
	}
	
	/**
	 * This function should not be called directly, use unzip_file instead. Attempts to unzip an archive using the ZipArchive class.
	 *
	 * @see unzip_file
	 * @access private
	 *
	 * @param string $file Full path and filename of zip archive
	 * @param string $to Full path on the filesystem to extract archive to
	 * @param array $neededDirs A partial list of required folders needed to be created.
	 * @return mixed WP_Error on failure, True on success
	 */
	function zipArchiveUnZip($file, $to, $neededDirs = array() ) {
		//global $GLOBALS['FileSystemObj'];
		$z = new ZipArchive();
	
		// PHP4-compat - php4 classes can't contain constants
		$zopen = $z->open($file, /* ZIPARCHIVE::CHECKCONS */ 4);
		if ( true !== $zopen ){
			//return new WP_Error('incompatible_archive', __('Incompatible Archive.'));
			//appUpdateMsg('Incompatible Archive', true);
			return 'incompatible_archive';
		}
	
		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			if ( ! $info = $z->statIndex($i) ){
				//return new WP_Error('stat_failed', __('Could not retrieve file from archive.'));
				appUpdateMsg('Could not retrieve file from archive.', true);
				return false;
			}
	
			if ( '__MACOSX/' === substr($info['name'], 0, 9) ) // Skip the OS X-created __MACOSX directory
				continue;
	
			if ( '/' == substr($info['name'], -1) ) // directory
				$neededDirs[] = $to . removeTrailingSlash($info['name']);
			else
				$neededDirs[] = $to . removeTrailingSlash(dirname($info['name']));
		}
	
		$neededDirs = array_unique($neededDirs);
		foreach ( $neededDirs as $dir ) {
			// Check the parent folders of the folders all exist within the creation array.
			if ( removeTrailingSlash($to) == $dir ) // Skip over the working directory, We know this exists (or will exist)
				continue;
			if ( strpos($dir, $to) === false ) // If the directory is not within the working directory, Skip it
				continue;
	
			$parentFolder = dirname($dir);
			while ( !empty($parentFolder) && removeTrailingSlash($to) != $parentFolder && !in_array($parentFolder, $neededDirs) ) {
				$neededDirs[] = $parentFolder;
				$parentFolder = dirname($parentFolder);
			}
		}
		asort($neededDirs);
	
		// Create those directories if need be:
		foreach ( $neededDirs as $_dir ) {
			if ( ! $GLOBALS['FileSystemObj']->mkDir($_dir, FS_CHMOD_DIR) && ! $GLOBALS['FileSystemObj']->isDir($_dir) ){ // Only check to see if the Dir exists upon creation failure. Less I/O this way.
				//return new WP_Error('mkdir_failed', __('Could not create directory.'), $_dir);
				appUpdateMsg('Could not create directory '.$_dir, true);
				return false;
			}
		}
		unset($neededDirs);
	
		for ( $i = 0; $i < $z->numFiles; $i++ ) {
			if ( ! $info = $z->statIndex($i) ){
				//return new WP_Error('stat_failed', __('Could not retrieve file from archive.'));
				appUpdateMsg('Could not retrieve file from archive', true);
				return false;
			}
	
			if ( '/' == substr($info['name'], -1) ) // directory
				continue;
	
			if ( '__MACOSX/' === substr($info['name'], 0, 9) ) // Don't extract the OS X-created __MACOSX directory files
				continue;
	
			$contents = $z->getFromIndex($i);
			if ( false === $contents ){
				//return new WP_Error('extract_failed', __('Could not extract file from archive.'), $info['name']);
				appUpdateMsg('Could not extract '.$info['name'].' file from archive.', true);
				return false;
			}
	
			if ( ! $GLOBALS['FileSystemObj']->putContents( $to . $info['name'], $contents, FS_CHMOD_FILE) ){
				//return new WP_Error('copy_failed', __('Could not copy file.'), $to . $info['name']);
				appUpdateMsg('Could not copy file '.$to . $info['name'], true);
				return false;
			}
		}
	
		$z->close();
	
		return true;
	}
	
	/**
	 * This function should not be called directly, use unzip_file instead. Attempts to unzip an archive using the PclZip library.
	 *
	 * @since 3.0.0
	 * @see unzip_file
	 * @access private
	 *
	 * @param string $file Full path and filename of zip archive
	 * @param string $to Full path on the filesystem to extract archive to
	 * @param array $neededDirs A partial list of required folders needed to be created.
	 * @return mixed WP_Error on failure, True on success
	 */
	function pclZipUnZip($file, $to, $neededDirs = array()) {
		//global $GLOBALS['FileSystemObj'];
		// See #15789 - PclZip uses string functions on binary data, If it's overloaded with Multibyte safe functions the results are incorrect.
		if ( ini_get('mbstring.func_overload') && function_exists('mb_internal_encoding') ) {
			$previous_encoding = mb_internal_encoding();
			mb_internal_encoding('ISO-8859-1');
		}
	
		require_once(APP_ROOT . '/lib/pclzip.php');
	
		$archive = new PclZip($file);
	
		$archive_files = $archive->extract(PCLZIP_OPT_EXTRACT_AS_STRING);
	
		if ( isset($previous_encoding) )
			mb_internal_encoding($previous_encoding);
	
		// Is the archive valid?
		if ( !is_array($archive_files) ){
			//return new WP_Error('incompatible_archive', __('Incompatible Archive.'), $archive->errorInfo(true));
			appUpdateMsg('Incompatible Archive '.$archive->errorInfo(true), true);
			return false;
		}
	
		if ( 0 == count($archive_files) ){
			//return new WP_Error('empty_archive', __('Empty archive.'));
			appUpdateMsg('Empty archive', true);
			return false;
		}
	
		// Determine any children directories needed (From within the archive)
		foreach ( $archive_files as $file ) {
			if ( '__MACOSX/' === substr($file['filename'], 0, 9) ) // Skip the OS X-created __MACOSX directory
				continue;
	
			$neededDirs[] = $to . removeTrailingSlash( $file['folder'] ? $file['filename'] : dirname($file['filename']) );
		}
	
		$neededDirs = array_unique($neededDirs);
		foreach ( $neededDirs as $dir ) {
			// Check the parent folders of the folders all exist within the creation array.
			if ( removeTrailingSlash($to) == $dir ) // Skip over the working directory, We know this exists (or will exist)
				continue;
			if ( strpos($dir, $to) === false ) // If the directory is not within the working directory, Skip it
				continue;
	
			$parentFolder = dirname($dir);
			while ( !empty($parentFolder) && removeTrailingSlash($to) != $parentFolder && !in_array($parentFolder, $neededDirs) ) {
				$neededDirs[] = $parentFolder;
				$parentFolder = dirname($parentFolder);
			}
		}
		asort($neededDirs);
	
		// Create those directories if need be:
		foreach ( $neededDirs as $_dir ) {
			if ( ! $GLOBALS['FileSystemObj']->mkDir($_dir, FS_CHMOD_DIR) && ! $GLOBALS['FileSystemObj']->isDir($_dir) ){ // Only check to see if the dir exists upon creation failure. Less I/O this way.
				//return new WP_Error('mkdir_failed', __('Could not create directory.'), $_dir);
				appUpdateMsg('Could not create directory '.$_dir, true);
				return false;
			}
		}
		unset($neededDirs);
	
		// Extract the files from the zip
		foreach ( $archive_files as $file ) {
			if ( $file['folder'] )
				continue;
	
			if ( '__MACOSX/' === substr($file['filename'], 0, 9) ) // Don't extract the OS X-created __MACOSX directory files
				continue;
	
			if ( ! $GLOBALS['FileSystemObj']->putContents( $to . $file['filename'], $file['content'], FS_CHMOD_FILE) ){
				//return new WP_Error('copy_failed', __('Could not copy file.'), $to . $file['filename']);
				appUpdateMsg('Could not copy file '.$to . $file['filename'], true);
				return false;
			}
		}
		return true;
	}
	
	/**
	 * Copies a directory from one location to another via the WordPress Filesystem Abstraction.
	 *
	 * @param string $from source directory
	 * @param string $to destination directory
	 * @param array $skipList a list of files/folders to skip copying
	 * @return mixed WP_Error on failure, True on success.
	 */
	function copyDir($from, $to, $skipList = array() ) {
		//global $GLOBALS['FileSystemObj'];
	
		$dirlist = $GLOBALS['FileSystemObj']->dirList($from);
	
		$from = addTrailingSlash($from);
		$to = addTrailingSlash($to);
	
		$skip_regex = '';
		foreach ( (array)$skipList as $key => $skipFile )
			$skip_regex .= preg_quote($skipFile, '!') . '|';
	
		if ( !empty($skip_regex) )
			$skip_regex = '!(' . rtrim($skip_regex, '|') . ')$!i';
	
		foreach ( (array) $dirlist as $filename => $fileinfo ) {
			if ( !empty($skip_regex) )
				if ( preg_match($skip_regex, $from . $filename) )
					continue;
	
			if ( 'f' == $fileinfo['type'] ) {
				if ( ! $GLOBALS['FileSystemObj']->copy($from . $filename, $to . $filename, true, FS_CHMOD_FILE) ) {
					// If copy failed, chmod file to 0644 and try again.
					$GLOBALS['FileSystemObj']->chmod($to . $filename, 0644);
					if ( ! $GLOBALS['FileSystemObj']->copy($from . $filename, $to . $filename, true, FS_CHMOD_FILE) ){
						//return new WP_Error('copy_failed', __('Could not copy file.'), $to . $filename);
						appUpdateMsg('Could not copy file '.$to . $file['filename'], true);
						return false;
					}
				}
			} elseif ( 'd' == $fileinfo['type'] ) {
				if ( !$GLOBALS['FileSystemObj']->isDir($to . $filename) ) {
					if ( !$GLOBALS['FileSystemObj']->mkDir($to . $filename, FS_CHMOD_DIR) ){
						//return new WP_Error('mkdir_failed', __('Could not create directory.'), $to . $filename);
						appUpdateMsg('Could not create directory '.$to . $filename, true);
						return false;
					}
				}
				$result = $this->copyDir($from . $filename, $to . $filename, $skipList);
				if ( $result == false )
					return $result;
			}
		}
		return true;
	}
}
