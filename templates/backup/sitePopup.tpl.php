<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
?>
<?php  $sitesData = Reg::tplGet('sitesData'); 
		$restrictions = array();
		if(function_exists('multiUserAllowAccess')) {
			$restrictions = multiUserAllowAccess();
                        $restrictions = $restrictions['restrict'];
                }
?>
<div class="dialog_cont view_backup">
  <div class="th rep_sprite">
    <div class="title droid700">BACKUPS - <?php echo $sitesData[$d['siteID']]['name'] ?></div>
    <a class="cancel rep_sprite_backup">cancel</a></div>
  <?php 
  
  if(!empty($d['siteBackups'])){
  foreach($d['siteBackups'] as $siteID => $siteTaskType){ 
  	TPL::captureClear('oldBackups');
  	foreach($siteTaskType as $key => $siteBackups){
		
		if($key != 'backupNow'){
			TPL::captureStart('oldBackups');
			echo TPL::captureGet('oldBackups');
		}
		foreach($siteBackups as $siteBackup){ ?>
  <div class="item_ind float-left topBackup">
  	<div class="backup_name stats"><?php echo $siteBackup['backupName']; ?></div>
    <div class="rep_sprite_backup stats files delConfHide"><?php if($siteBackup['what'] == 'full'){ ?>Files + DB<?php } elseif($siteBackup['what'] == 'db'){ ?>DB<?php } elseif($siteBackup['what'] == 'files'){ ?>Files<?php }?></div>
    <div class="rep_sprite_backup stats size delConfHide"><?php echo $siteBackup['size']; ?></div>
    <div class="rep_sprite_backup stats time delConfHide"><?php echo @date(Reg::get('dateFormatLong'), $siteBackup['time']); ?></div>
    
     <?php if(empty($restrictions) || !in_array("restoreDeleteDownloadBackup", $restrictions)){ ?> 
    <div class="row_backup_action rep_sprite" style="float:right;"><a class="trash rep_sprite_backup removeBackup" sid="<?php echo $siteBackup['siteID']; ?>" taskName="<?php echo $siteBackup['data']['scheduleKey']; ?>" referencekey="<?php echo $siteBackup['referenceKey']; ?>"></a><div class="del_conf" style="display:none;"><div class="label">Sure?</div><div class="yes deleteBackup">Yes</div><div class="no deleteBackup">No</div></div></div>
    
    <?php if(!empty($siteBackup['downloadURL'])){ ?> <div class="row_backup_action rep_sprite delConfHide" style="float:right;"><a class="download rep_sprite_backup" href="<?php echo $siteBackup['downloadURL']; ?>"></a></div><?php } ?>
    <div class="row_action float-left delConfHide"><a class="restoreBackup needConfirm" sid="<?php echo $siteBackup['siteID']; ?>" taskName="<?php echo $siteBackup['data']['scheduleKey']; ?>" referencekey="<?php echo $siteBackup['referenceKey']; ?>">Restore</a></div>
     <?php } ?>
  </div>
  <?php }//end foreach($siteBackups as $siteBackup)
			
		if($key != 'backupNow'){
			TPL::captureStop('oldBackups');
		}
 	 }//end foreach($siteTaskType as $key => $siteBackups)
	  if($oldBackupsHTML = trim(TPL::captureGet('oldBackups'))){
		  ?> <div style="border-top: 1px solid #F1F1D7;margin-top: -1px;padding: 10px;font-weight: 700;margin-left: 35px;">Old backups</div> <?php
		  echo $oldBackupsHTML;
	  }
	
  	}
  }
  else{ if(empty($restrictions) || !in_array("createBackup", $restrictions)){ ?>
  <div class="empty_data_set"><div class="line2">Looks like there are <span class="droid700">no backups here</span>. Create a <a sid="<?php echo $d['siteID']; ?>" id="singleBackupNow">Backup Now</a>.</div></div><?php }
  else { ?>
  		<div class="empty_data_set"><div class="line2">Looks like there are <span class="droid700">no backups here</span>.</div></div>
  	<?php }
  }
  ?>
  <div class="clear-both"></div>
</div>