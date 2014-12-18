<?php
require_once('includes/app.php');
/**
 * Handle file uploads via XMLHttpRequest
 */
class qqUploadedFileXhr {
    /**
     * Save the file to the specified path
     * @return boolean TRUE on success
     */
    function save($path) {    
        $input = fopen("php://input", "r");
        $temp = tmpfile();
        $realSize = stream_copy_to_stream($input, $temp);
        fclose($input);
        
        if ($realSize != $this->getSize()){            
            return false;
        }
        
        $target = fopen($path, "w");        
        fseek($temp, 0, SEEK_SET);
        stream_copy_to_stream($temp, $target);
        fclose($target);
        
        return true;
    }
    function getName() {
        return $_GET['qqfile'];
    }
    function getSize() {
        if (isset($_SERVER["CONTENT_LENGTH"])){
            return (int)$_SERVER["CONTENT_LENGTH"];            
        } else {
            throw new Exception('Getting content length is not supported.');
        }      
    }   
}

/**
 * Handle file uploads via regular form post (uses the $_FILES array)
 */
class qqUploadedFileForm {  
    /**
     * Save the file to the specified path
     * @return boolean TRUE on success
     */
    function save($path) {
        if(!move_uploaded_file($_FILES['qqfile']['tmp_name'], $path)){
            return false;
        }
        return true;
    }
    function getName() {
        return $_FILES['qqfile']['name'];
    }
    function getSize() {
        return $_FILES['qqfile']['size'];
    }
}

class qqFileUploader {
    private $allowedExtensions = array();
    private $sizeLimit = 10485760;
    private $file;

    function __construct(array $allowedExtensions = array(), $sizeLimit = 10485760){        
        $allowedExtensions = array_map("strtolower", $allowedExtensions);
            
        $this->allowedExtensions = $allowedExtensions;        
        $this->sizeLimit = $sizeLimit;
        
        $this->checkServerSettings();       

        if (isset($_GET['qqfile'])) {
            $this->file = new qqUploadedFileXhr();
        } elseif (isset($_FILES['qqfile'])) {
            $this->file = new qqUploadedFileForm();
        } else {
            $this->file = false; 
        }
    }
    
    private function checkServerSettings(){        
      /*  $postSize = $this->toBytes(ini_get('post_max_size'));
        $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));        
        
        if ($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit){
            $size = max(1, $this->sizeLimit / 1024 / 1024) . 'M';             
            die("{'error':'increase post_max_size and upload_max_filesize to $size'}");    
        }        */
    }
    
    private function toBytes($str){
        $val = trim($str);
        $last = strtolower($str[strlen($str)-1]);
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;        
        }
        return $val;
    }
    
    /**
     * Returns array('success'=>true) or array('error'=>'error message')
     */
    function handleUpload($uploadDirectory, $replaceOldFile = FALSE){
		    $pathinfo = pathinfo($this->file->getName());
        $filename = $pathinfo['filename'];
        //$filename = md5(uniqid());
        $ext = $pathinfo['extension'];
		
		
        if(isset($_GET['imageOnly'])){
			$this->allowedExtensions = array('png', 'jpg', 'gif');
        }elseif(isset($_GET['allWPFiles'])){                                                                            //changed by Pheonix
            $this->allowedExtensions = array('png', 'jpg', 'gif','php','js','html','htm','css','xml','csv','zip','pdf','doc','docx','txt','mp3','mo','po');
        }else{
			$this->allowedExtensions = array('zip');
		}

        if($this->allowedExtensions && !in_array(strtolower($ext), $this->allowedExtensions)){
           // $these = implode(', ', $this->allowedExtensions);
		   
            if(isset($_GET['imageOnly'])){
				$these = 'jpg, png, gif';
            }elseif(isset($_GET['allWPFiles'])){
                $these = 'png, jpg, gif, php, js, html, htm, css, xml, csv, zip, pdf, doc, docx, txt, mp3, mo, po';
			}else{
				$these = 'zip';
			}
            return array('error' => 'Upload only "'.$these.'" files. Please try again.');
        }
        
        if (!is_writable($uploadDirectory)){
            return array('error' => "Server error. Upload directory isn't writable.");
        }
        
        if (!$this->file){
            return array('error' => 'No files were uploaded.');
        }
        
        $size = $this->file->getSize();
        
        if ($size == 0) {
            return array('error' => 'File is empty');
        }
        
        if ($size > $this->sizeLimit) {
            return array('error' => 'File is too large');
        }
        
    
        if(!$replaceOldFile){
           if(isset($_GET['allWPFiles']) && file_exists($uploadDirectory . $filename . '.' . $ext.'.swp') ){
                $filename .= rand(10, 99);                                                                  //changed by Pheonix
            }else{
            /// don't overwrite previous files that were uploaded
            while (file_exists($uploadDirectory . $filename . '.' . $ext)) {
                $filename .= rand(10, 99);
            	}
        	}
        }
        
        if(isset($_GET['allWPFiles']) && $this->file->save($uploadDirectory . $filename . '.' . $ext.'.swp')){
            return array('success'=>true,'filename'=>$filename.'.'.$ext.'.swp');       //changed by Pheonix
        }elseif ($this->file->save($uploadDirectory . $filename . '.' . $ext)){
            return array('success'=>true,'filename'=>$filename.'.'.$ext);		//changed by dark
        } else {
            return array('error'=> 'Could not save uploaded file.' .
                'The upload was cancelled, or server error encountered');
        }
        
    }    
}
// list of valid extensions, ex. array("jpeg", "xml", "bmp")
/*?>

<?php*/
$allowedExtensions = array("zip","jpg","png", "gif");
// max file size in bytes
$sizeLimit = 10 * 1024 * 1024;

$uploader = new qqFileUploader($allowedExtensions, $sizeLimit);
$result = $uploader->handleUpload('uploads/');
// to pass data through iframe you will need to encode all html tags
echo htmlspecialchars(json_encode($result), ENT_NOQUOTES);
