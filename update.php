<?php

/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

define('UPDATE_PAGE', true);
include('includes/app.php');

if(!empty($_GET['downloadToken'])){
	$GLOBALS['downloadToken'] = $_GET['downloadToken'];
}


if( $_GET['action'] == 'appUpdate' && !empty($_GET['newVersion']) ){
	$updateAvailable = checkUpdate(false, false);
	if($_GET['newVersion'] == $updateAvailable['newVersion']){
		echo str_pad(' ', 400);
		?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Updating</title>
</head>
<body style="font-family: 'Droid Sans', sans-serif; font-size:12px; color: #555; line-height:24px;">
<div style="margin-top:-30px;"><?php processAppUpdate(); ?></div>
</body>
</html>
<?php
	}
}

if( $_GET['action'] == 'installAddons'){
	$addons = getNewAddonsAvailable();
	if(empty($addons)){
		echo "No, Addons to update.";
		exit;	
	}
	echo "<br><strong>Install Addons</strong><br>";
	echo str_pad(' ', 400);
		?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Updating</title>
</head>
<body style="font-family: 'Droid Sans', sans-serif; font-size:12px; color: #555; line-height:24px;">
<div style="margin-top:-30px;"><?php downloadAndInstallAddons($addons); ?></div>
</body>
</html>
<?php
	
}

if( $_GET['action'] == 'updateAddons' && (!empty($_GET['addons']) || !empty($_GET['addon'])) ){
	$addons = array();
	if(!empty($_GET['addons'])){
		$tempAddons = explode('__IWP__', $_GET['addons']);
		foreach($tempAddons as $addonDetails){
			$addonDetails = trim($addonDetails);
			if(!empty($addonDetails) && is_string($addonDetails)){
				$addonDetails = explode('__AD__', $addonDetails);
				$addons[$addonDetails[0]] = array('slug' => $addonDetails[0], 'version' => $addonDetails[1]);
			}
		}
	}
	elseif(!empty($_GET['addon'])){
		$addonDetails = explode('__AD__', $_GET['addon']);
		$addons = array($_GET['addon'] => array('slug' => $addonDetails[0], 'version' => $addonDetails[1]));
	}
	else{
		echo "Invalid paramenters";
		exit;	
	}
	
	echo "<br><strong>Update Addon(s)</strong><br>";
	echo str_pad(' ', 400);
		?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Updating</title>
</head>
<body style="font-family: 'Droid Sans', sans-serif; font-size:12px; color: #555; line-height:24px;">
<div style="margin-top:-30px;"><?php updateAddons($addons); ?></div>
</body>
</html>
<?php
	
}

?>