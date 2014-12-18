<?php
/************************************************************
 * InfiniteWP Admin panel									*
 * Copyright (c) 2012 Revmakx								*
 * www.revmakx.com											*
 *															*
 ************************************************************/
?>
<?php  $historyData = Reg::tplGet('historyData'); ?>
<div class="dialog_cont view_backup server_info" style="width:960px">
  <div class="th rep_sprite">
    <div class="title droid700">Server Information </div>
    <a class="cancel rep_sprite_backup">cancel</a></div>
  <?php  if(!empty($d['historyData'])){?>
    <div class="item_ind topBackup server_info_th">
        <div class="backup_name stats config">Server Configuration</div>
        <div class="backup_name stats suggestion">Suggestion</div>
        <div class="backup_name stats value">Value</div>
        <div class="backup_name stats status">Status</div>
        <div class="clear-both"></div>
    </div>
      <?php foreach($d['historyData']['serverInfo'] as $infoKey=>$infoValue){
          if($infoValue['pass']==="ok") {
              $statusClass = "ok";
              $statusString = "OK";
          }elseif($infoValue['pass']===true) {
              $statusClass = "good";
              $statusString = "Good";
          }else{
              $statusClass = "bad";
              $statusString = "Bad";
          }
      ?>
    <div class="item_ind topBackup">
        <div class="backup_name stats config"><?php echo $infoValue['name']; ?></div>
        <div class="backup_name stats suggestion"><?php echo $infoValue['suggeted']; ?></div>
        <div class="backup_name stats value"><?php echo $infoValue['status']; ?></div>
        <div class="backup_name stats status <?php echo $statusClass;?>"><?php echo $statusString;?></div>
        <div class="clear-both"></div>
    </div>
      <?php }?>
    <?php foreach($d['historyData']['mysqlInfo'] as $infoKey=>$infoValue){
        if($infoValue['pass']==="ok") {
              $statusClass = "ok";
              $statusString = "OK";
          }elseif($infoValue['pass']===true) {
              $statusClass = "good";
              $statusString = "Good";
          }else{
              $statusClass = "bad";
              $statusString = "Bad";
          }
    ?>
    <div class="item_ind topBackup">
        <div class="backup_name stats config"><?php echo $infoValue['name']; ?></div>
        <div class="backup_name stats suggestion"><?php echo $infoValue['suggeted']; ?></div>
        <div class="backup_name stats value"><?php echo $infoValue['status']; ?></div>
        <div class="backup_name stats status <?php echo $statusClass;?>"><?php echo $statusString; ?></div>
        <div class="clear-both"></div>
    </div>
      <?php }?>
    <?php foreach($d['historyData']['functionList'] as $infoKey=>$infoValue){
        if($infoValue['pass']==="ok") {
              $statusClass = "ok";
              $statusString = "OK";
          }elseif($infoValue['pass']===true) {
              $statusClass = "good";
              $statusString = "Good";
          }else{
              $statusClass = "bad";
              $statusString = "Bad";
          }
    ?>
    <div class="item_ind topBackup">
        <div class="backup_name stats config"><?php echo $infoValue['name']; ?></div>
        <div class="backup_name stats suggestion"><?php echo $infoValue['suggeted']; ?></div>
        <div class="backup_name stats value"><?php echo $infoValue['status']; ?></div>
        <div class="backup_name stats status <?php echo $statusClass;?>"><?php echo $statusString; ?></div>
        <div class="clear-both"></div>
    </div>
      <?php }?>
    <div class="th rep_sprite" style="border-top: 1px solid #D2D5D7; box-shadow: 0 0 0 rgba(0, 0, 0, 0);">
    <div class="title droid700">Folder Information </div>
    </div>
    <div class="item_ind topBackup server_info_th">
        <div class="backup_name stats config">Relative Path</div>
        <div class="backup_name stats suggestion">Suggestion</div>
        <div class="backup_name stats value">Value</div>
        <div class="backup_name stats status">Owner (UID:GID)</div>
        <div class="clear-both"></div>
    </div>
    
     <?php foreach($d['historyData']['directoryInfo']['status'] as $infoValue){?>
    <div class="item_ind topBackup">
        <div class="backup_name stats config"><?php echo $infoValue['title']; ?></div>
        <div class="backup_name stats suggestion"><?php echo $infoValue['suggestion']; ?></div>
        <div class="backup_name stats value"><?php echo $infoValue['value']; ?></div>
        <div class="backup_name stats status"><?php echo $infoValue['owner']; ?></div>
        <div class="clear-both"></div>
    </div>
      <?php }?>
    
  <?php } else{ ?><div class="empty_data_set"><div class="line2">We are not able to connect to your WordPress site. Please try again in sometime.</div></div><?php }
  ?>
  <div class="clear-both"></div>
</div>
