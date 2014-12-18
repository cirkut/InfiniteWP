<?php

/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/

include("includes/app.php");
onBrowserLoad();
initMenus();


if(function_exists('multiUserStatus')){
	multiUserStatus();
}
else{
	if(userStatus() != 'admin'){
		userLogout();
	}
}



$mainJson = json_encode(panelRequestManager::getSitesUpdates());
$toolTipData = json_encode(panelRequestManager::getUserHelp());
$favourites =  json_encode(panelRequestManager::getFavourites());
$sitesData = json_encode(panelRequestManager::getSites());
$sitesListData = json_encode(panelRequestManager::getSitesList());
$groupData = json_encode(panelRequestManager::getGroupsSites());
$updateAvailable = json_encode(checkUpdate(false, false));
$updateAvailableNotify = json_encode(panelRequestManager::isUpdateHideNotify());
$totalSettings =  json_encode(array("data"=>panelRequestManager::requiredData(array("getSettingsAll"=>1))));
$fixedNotifications = json_encode(getNotifications(true));
$cronFrequency = json_encode(getRealSystemCronRunningFrequency());

$multiUserAllowAccess = json_encode(panelRequestManager::requiredData(array("multiUserAllowAccess"=>1)));
$min = '.min';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "//www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="//www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="robots" content="noindex">
<title>InfiniteWP</title>
<link href='//fonts.googleapis.com/css?family=Droid+Sans:400,700' rel='stylesheet' type='text/css'>
<link rel="stylesheet" href="css/select2.css?<?php echo APP_VERSION; ?>" type="text/css" />
<link rel="stylesheet" href="css/core<?php echo $min; ?>.css?<?php echo APP_VERSION; ?>" type="text/css" />
<link rel="stylesheet" href="css/datepicker.css?<?php echo APP_VERSION; ?>" type="text/css" />
<link rel="stylesheet" href="css/nanoscroller.css?<?php echo APP_VERSION; ?>" type="text/css" />
<link rel="stylesheet" href="css/jPaginator.css?<?php echo APP_VERSION; ?>" type="text/css" media="screen"/>
<link rel="stylesheet" href="css/jquery-ui.min.css?<?php echo APP_VERSION; ?>" type="text/css" media="all" />
<link rel="stylesheet" href="css/jquery.qtip.css?<?php echo APP_VERSION; ?>" type="text/css" media="all" />
<link rel="stylesheet" href="css/custom_checkbox.css?<?php echo APP_VERSION; ?>" type="text/css" media="all" />
<link rel="shortcut icon" href="images/favicon.png" type="image/x-icon"/>
<link href="//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css" rel="stylesheet">
<!--[if lt IE 9]>
	<link rel="stylesheet" type="text/css" href="css/ie8nlr.css?<?php echo APP_VERSION; ?>" />
<![endif]-->
<script src="js/jquery.min.js?<?php echo APP_VERSION; ?>" type="text/javascript" charset="utf-8"></script>
<script src="js/jquery-ui.min.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/select2.min.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/fileuploader.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/apps<?php echo $min; ?>.js?<?php echo APP_VERSION; ?>" type="text/javascript" charset="utf-8"></script>
<script src="js/load<?php echo $min; ?>.js?<?php echo APP_VERSION; ?>" type="text/javascript" charset="utf-8"></script>
<script src="js/jPaginator-min.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/jquery.qtip.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script src="js/jquery.mousewheel.js?<?php echo APP_VERSION; ?>" type="text/javascript"></script>
<script>
var permissions = <?php echo $multiUserAllowAccess; ?>;
var systemURL = "<?php echo APP_URL;?>";
var serviceURL = "<?php echo getOption('serviceURL');?>";
var appVersion = "<?php echo APP_VERSION; ?>";
var appInstallHash = "<?php echo APP_INSTALL_HASH; ?>";
var IP = "<?php echo $_SERVER['REMOTE_ADDR']; ?>";
var APP_PHP_CRON_CMD = "<?php echo APP_PHP_CRON_CMD; ?>";
var APP_ROOT = decodeURIComponent("<?php echo rawurlencode(APP_ROOT); ?>");
var CRON_FREQUENCY = "<?php echo ($cronFrequency == 0) ? 'Server cron is not running.' : 'Currently, the cron is running every '.$cronFrequency.' min';?>"
var mainJson = <?php echo $mainJson?>;
var sitesjson = mainJson.siteView;
var pluginsjson = mainJson.pluginsView.plugins;
var themesjson = mainJson.themesView.themes;
var wpjson = mainJson.coreView.core;
var toolTipData = <?php echo $toolTipData;?>;
var favourites = <?php echo $favourites; ?>;
var site = <?php echo  $sitesData;?>;
var sitesList = <?php echo  $sitesListData;?>;
var group = <?php echo  $groupData;?>;
var totalSites = getPropertyCount(site);
var totalGroups = getPropertyCount(group);
var totalUpdates =  getPropertyCount(mainJson);
var pluginsStatus,themesStatus;
var updateAvailable   = <?php echo $updateAvailable;?>;
var updateAvailableNotify=<?php echo $updateAvailableNotify;?>;
var fixedNotifications = <?php echo $fixedNotifications;?>;
var settingsData = <?php echo $totalSettings; ?>;
settingsData['data']['getSettingsAll']['settings']['timeZone'] = '';
var forcedAjaxInterval = <?php echo FORCED_AJAX_CALL_MIN_INTERVAL; ?>;	// forced ajax interval if set


<?php
$clientUpdates = manageCookies::cookieGet('clientUpdates');
if(!isset($clientUpdates['clientUpdateVersion'])) $clientUpdates['clientUpdateVersion'] = '';
?>
var clientUpdateVersion = '<?php echo $clientUpdates['clientUpdateVersion'];?>';
var currentUserAccessLevel = "<?php echo userStatus(); ?>";
var googleSettings='';
var cpBrandingSettings='';
var uptimeMonitoringSettings='';
var googleAnalyticsAccess='';
var googleWebMastersAccess='';
var googlePageSpeedAccess='';

<?php echo getAddonHeadJS(); ?>
<?php if(!empty($_REQUEST['page'])) {?>
	reloadStatsControl=0;
<?php } ?>

</script>
<script type="text/javascript" src="js/init<?php echo $min; ?>.js?<?php echo APP_VERSION; ?>" charset="utf-8"></script>
<script type="text/javascript" src="js/jquery.nanoscroller.min.js?<?php echo APP_VERSION; ?>"></script>
<script type="text/javascript" src="js/datepicker.js?<?php echo APP_VERSION; ?>"></script>
<script type="text/javascript" src="js/eye.js?<?php echo APP_VERSION; ?>"></script>
<script type="text/javascript" src="js/utils.js?<?php echo APP_VERSION; ?>"></script>
<script type="text/javascript" src="js/layout.js?<?php echo APP_VERSION; ?>"></script>

<?php if(userStatus() != 'admin'){ ?>
<script>
$(function () {
	$(".settingsButtons").click();
});
</script>
<?php } ?>
<!-- addon ext src starts here -->
<?php echo getAddonsHTMLHead(); ?>
<?php if(!empty($_REQUEST['page']))
{ ?>
	<script>
    $(function () { 
		reloadStatsControl=0;
		<?php if($_REQUEST['page']=="addons") ?>
					$("#iwpAddonsBtn").click();
		
		processPage("<?php echo $_REQUEST['page'];?>");
    
    });
    </script>
<?php } ?>
<style>
@media only screen and (max-width : 1360px) {
ul#header_nav li.resp_hdr_logout { display:inline; }
#header_bar a.logout { display:none;}
}
</style>
</head>
<body>
<div class="notification_cont"></div>
<div id="fb-root"></div>
<div id="updateOverLay" style='display:none;-ms-filter: "progid:DXImageTransform.Microsoft.Alpha(Opacity=70)"; background-color:#000; opacity:0.7;position:fixed;top: 0;left: 0;width: 100%;height: 100%; z-index:1020'></div>
<div id="loadingDiv" style="display:none">Loading...</div>
<div id="modalDiv"></div>

<!--<div class="overlay_test"></div>-->
<div id="dynamic_resize">
<div id="header_bar"> <div class="header_container"><a href="<?php echo APP_URL; ?>" style="text-decoration:none;">
    <div id="logo"></div></a>
    <div id="admin_panel_label">Admin Panel v<?php echo APP_VERSION; ?></div>
    
    <a class="float-left fb_icon_hdr" href="//www.facebook.com/infinitewp" target="_blank"></a><a class="float-left twitter_icon_hdr" href="//twitter.com/infinitewp" target="_blank"></a>
    <ul id="header_nav">
      <!--<li><a href="">Suggest an Idea</a></li>-->
      <li class="restrictCronToggle"><a class="updates_centre first-level" id="updateCentreBtn">IWP Update Centre<span id="updateCenterCount" style="display:none" class="count">1</span></a>
      	
        <div id="updates_centre_cont" style="display:none">
                   
          <div class="th rep_sprite" style="border-top: 1px solid #C1C4C6; height: 38px; border-bottom: 0;">
            <div class="btn_action float-right"><a class="rep_sprite updateActionBtn">Check Now</a></div>
            
          </div>
        </div>
      </li>
       <?php if(userStatus() == 'admin'){ ?><li><a class="first-level" id="iwpAddonsBtn">Addons <span class="count" style="<?php if(($addonUpdate = getAddonAlertCount()) < 1){ ?>display:none;<?php } ?>"><?php echo $addonUpdate; ?></span></a></li><?php } ?>
       <li><a class="first-level" href="//www.google.com/moderator/#16/e=1f9ff1" target="_blank">Got an idea?</a></li>
      <li class="help"><a class="first-level">Help <span style="font-size:7px;">▼</span></a>
      	<ul class="sub_menu_ul">
        	<li><a href="//infinitewp.com/forum/" target="_blank">Discussion Forum</a></li>
            <li><a href="javascript:loadReport('',1)">Report Issue</a></li>
            <li><a class="takeTour">Take the tour</a></li>
        </ul>
      </li>
      <li class="user_mail"><a id="user_email_acc" style="color:#e9e9e9;"><?php echo $GLOBALS['email']; ?> </a><li>
      <li class="settings" title="Settings" id="mainSettings">
        <a id="settings_btn">Settings</a>
      </li>
	  <li class="resp_hdr_logout"><a class="first-level" href="login.php?logout=now">Logout</a></li>
    </ul>
    <div class="clear-both"></div></div><a href="login.php?logout=now" class="logout">Logout</a>
  </div>
<div id="site_cont">
  
  <div id="main_cont">
    
    <ul class="site_nav">
    	<?php printMenus(); ?>
    </ul>
      
      
    
    <div class="btn_reload rep_sprite float-right"><a class="rep_sprite_backup user_select_no" id="reloadStats">Reload Data</a></div>
	<div class="checkbox user_select_no" style="float:right; width:70px; cursor:pointer;" id="clearPluginCache">Clear cache</div>
    <div id="lastReloadTime"></div>
    <ul class="site_nav single_nav float-left"><li class="l1 navLinks" page="history"><a>Activity Log</a></li></ul>
    <div class="clear-both"></div>
    <hr class="dotted" />
    <div class="page_section_title">UPDATES</div>
    <div id="pageContent">
      <div class="empty_data_set welcome">
        <div class="line1">Hey there. Welcome to InfiniteWP.</div>
        <div class="line2">Lets now manage WordPress, the IWP way!</div>
        <div class="line3">
          <div class="welcome_arrow"></div>
          Add a WordPress site to IWP.<br />
          <span style="font-size:12px">(Before adding the website please install and activate InfiniteWP Client Plugin in your WordPress site)</span> </div>
        <a href="//www.youtube.com/watch?v=q94w5Vlpwog" target="_blank">See How</a>. </div>
    </div>
  </div>
</div>
</div>
<div id="bottom_toolbar" class="siteSearch">
  <div id="activityPopup"> </div>
</div>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");

  (function() {
    var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
    po.src = '//apis.google.com/js/plusone.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
 
  })();

</script>
</body>
</html>