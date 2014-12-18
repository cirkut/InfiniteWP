<?php
#
# CronAPI
# This Class is used to manage the EasyCron API with IWP Admin Panel
# Develope by : Revmakx Team
#

/**
 * ClassName : easyCronAPI
 */
class easyCronAPI {
	
	public $apiUrl = 'http://www.easycron.com/rest/';
	private $apiToken;
	public $apiSettings, $runTimeSettings;
	
	/*
	 *  CronAPI constructur, It's set the default values for various calls 
	 */ 
	function __construct() {
		$this->apiSettings = array(
			'email_me' => 0,
			'log_output_length' => 0,
			'testfirst' => 1,
		);
	}
	
	/*
	 * _doCall is a private function, So we prevent the outside trigres
	 * @Parms method : REST method 
	 * @Parms parms : input datas for Easy Cran API
	 * @Return Array/false
	 */
	private function _doCall($method,$parms) {
		$error = false;
		$parms['token'] = $this->apiToken;
        $kpv = array();
        foreach ($parms as $key => $val) {
            $kpv[] = $key . '=' . urlencode($val);
        }
		
        $kpv = implode('&', $kpv);
        $url = $this->apiUrl . $method . '?' . $kpv;
		
		// create curl resource
        $ch = curl_init();

        // set url
        curl_setopt($ch, CURLOPT_URL, $url);

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $res = curl_exec($ch);
		
		if(curl_errno($ch)){
			$error = array('status' => 'error', 'error' => array('message' => 'API communication error: ('.curl_errno($ch).') '.curl_error($ch)));
		}
		
		$curlInfo = curl_getinfo($ch);
		if($curlInfo['http_code'] != 200){
			$error = array('status' => 'error', 'error' => array('message' => 'API communication error: ('.$curlInfo['http_code'].') '.$GOLBALS['httpErrorCodes'][$curlInfo['http_code']] ));
		}

        // close curl resource to free up system resources
        curl_close($ch);
		
	    //$res = file_get_contents($url);
		
		if($error){
			return $error;	
		}		
		

        if ($res) {
            return json_decode($res, true);   
        } else {
            return false;
        }
	}
	
	/*
	 * Used to set the runtime parameters
	 * @Parms requestData : Reference variable
	 * @Return : Call by Reference
	 */
	private function _getOptions(&$requestData) {
		if(is_array($this->runTimeSettings) && count($this->runTimeSettings)>0) {
			foreach ($this->runTimeSettings as $key => $val) {
				$requestData[$key] = $val;	
			}
		}
	}
	
	/*
	 * Set the Easy Cron API token to our private variable
	 * @Parms token : EasyCron API Token 
	 */
	public function setToken($token) {
		$this->apiToken = $token;
	}
	
	/*
	 * Set some additional parameters you need to set on EASY CRON 
	 */
	public function setOptions($parms = array()) {
		$this->runTimeSettings = $parms; 
	}
	
	/*
	 * Add Cron url to East Cron
	 * @Parms url : URL of the cron job. Maximum length is 255 chars.
	 * @Parms frequency : Time intrevel to trigger the cron must be (1 to 59)
	 * @Return Array
	 */
	public function add($url, $frequency) {
		$requestData = array();
		$requestData['email_me'] = $this->apiSettings['email_me'];
		$requestData['log_output_length'] = $this->apiSettings['log_output_length'];
		$requestData['testfirst'] = $this->apiSettings['testfirst'];
		$this->_getOptions($requestData);
		
		$requestData['url'] = $url;
		$requestData['cron_expression'] = '*/'.$frequency.' * * * *';
		return $this->_doCall('add', $requestData); 		
	}
	
	/*
	 * Edit Cron url on East Cron
	 * @Parms id : unique identifier for this cron task
	 * @Parms url : URL of the cron job. Maximum length is 255 chars.
	 * @Parms frequency : Time intrevel to trigger the cron must be (1 to 59)
	 * @Return Array
	 */
	public function edit($id, $url, $frequency) {
		$requestData = array();
		$requestData['email_me'] = $this->apiSettings['email_me'];
		$requestData['log_output_length'] = $this->apiSettings['log_output_length'];
		$requestData['testfirst'] = $this->apiSettings['testfirst'];
		$this->_getOptions($requestData);
		
		$requestData['id'] = $id;
		$requestData['url'] = $url;
		$requestData['cron_expression'] = '*/'.$frequency.' * * * *';
		return $this->_doCall('edit', $requestData); 		
		
	}
	
	/*
	 * Enable Cron url on East Cron
	 * @Parms id : unique identifier for this cron task
	 * @Return Array
	 */
	public function enable($id) {
		$requestData = array();
		$requestData['id'] = $id;
		$this->_getOptions($requestData);
		
		return $this->_doCall('enable', $requestData); 		
	}
	
	/*
	 * Disable Cron url on East Cron
	 * @Parms id : unique identifier for this cron task
	 * @Return Array
	 */
	public function disable($id) {
		$requestData = array();
		$requestData['id'] = $id;
		$this->_getOptions($requestData);
		
		return $this->_doCall('disable', $requestData); 		
	}
	
	/*
	 * Delete Cron url on East Cron
	 * @Parms id : unique identifier for this cron task
	 * @Return Array
	 */
	public function delete($id) {
		$requestData = array();
		$requestData['id'] = $id;
		$this->_getOptions($requestData);
		
		return $this->_doCall('delete', $requestData); 		
	}

	/*
	 * Get the Cron Information for a specific Cron
	 * @Parms id : unique identifier for this cron task
	 * @Return Array
	 */
	public function log($id) {
		$requestData = array();
		$requestData['id'] = $id;
		$this->_getOptions($requestData);
		
		return $this->_doCall('log', $requestData); 		
	}
	
	/*
	 * Get the cronList from Easy Cron Server
	 * @Return Array
	 */
	public function cronList() {
		$requestData = array();
		$this->_getOptions($requestData);
		return $this->_doCall('list', $requestData);
	}
	
	/*
	 * Get the time zone from Easy Cron Server
	 * @Return Array
	 */
	public function timezone() {
		$requestData = array();
		$this->_getOptions($requestData);
		return $this->_doCall('timezone', $requestData); 		
	}
	
}
?>