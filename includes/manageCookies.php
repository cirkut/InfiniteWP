<?php
class manageCookies {

	private static $_encodeFlg = true;
	private static $_extende = false;
	private static $_cookiePrx = "iwp_";

	/*
	** Set the cookie
	*/
	public static function cookieSet($cName, $cValue, $extra=array()) {
		$defalutParms = array(
			'expire'	=>	time()+60*60*24*5,
			'path'		=>	'',
			'domain'	=>	'',
			'secure'	=>	false,
			'httponly'	=>	false
		);
		$parms = array_merge($defalutParms,$extra);
		@extract($parms);
		if($cValue != "") {
			if(self::$_encodeFlg) {
				$cValue = base64_encode(serialize($cValue));
			}
			$cValue = urlencode($cValue);
		}
		$cName = self::$_cookiePrx.$cName;
                $_COOKIE[$cName] = $cValue;
		setcookie ( $cName ,$cValue ,$expire ,$path ,$domain ,$secure ,$httponly);
	}

	/*
	** get the cookie
	*/
	public static function cookieGet($cName,$encodeflg = true, $extende = false) {
		$tempCName = self::$_cookiePrx.$cName;
                if(isset($_COOKIE[$tempCName]) && (!empty($_COOKIE[$tempCName]))) {
                    $cValue = $_COOKIE[$tempCName];
                    $cValue = urldecode($cValue);
                    if(self::$_encodeFlg && $encodeflg) {
                            $cValue = unserialize(base64_decode($cValue));
                    }
                    if(self::$_extende || $extende) {
                            self::cookieSet ( $cName , $cValue );
                    }
                    return $cValue;
                } else {
                    return '';
                }
	}

	/*
	** cookie unset
	*/
	public static function cookieUnset($cName) {
		if(is_array($cName)) {
			foreach($cName as $cData) {
                                $_COOKIE[$cData] = '';
				self::cookieset($cData,'',array('expire'=>time()-3600));
			}
		} else {
                        $_COOKIE[$cName] = '';
			self::cookieset($cName,'',array('expire'=>time()-3600));
		}
	}
        
        /*
	** All cookie unset 
	*/
	public static function cookieAllUnset() {
            if (!empty($_COOKIE)) {
                $cNames = array();
                foreach ($_COOKIE as $cName=>$cData) {
                    $cName = str_replace(self::$_cookiePrx, '', $cName);
                    array_push($cNames, $cName);
                }
                if(count($cNames)>0) {
                    self::cookieUnset($cNames);
                }
            }
	}
}
?>