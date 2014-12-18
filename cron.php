<?php

/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
$cronStartTime = microtime(true);
define('USER_SESSION_NOT_REQUIRED', true);
//require_once('includes/app.php');
require_once(dirname(__FILE__).'/includes/app.php');

cronCheck();
cronRun();

?>