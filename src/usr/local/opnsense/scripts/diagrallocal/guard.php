<?php
require_once(__DIR__.'/settings.php');
require_once(__DIR__.'/routing_policy.php');
require_once(__DIR__.'/network_policy.php');
$lock=fopen('/var/run/diagral-local-routing.lock','c');
if(!flock($lock,LOCK_EX|LOCK_NB)) exit(75);
$id='8378647a-7145-4854-9194-32e526062df6';
$mode=mode_now($id);$observation=diagral_observe();$cleared=0;
if($mode==='local' && $observation['active']) {
    if(!$observation['alias_ready']) {
        exec('/usr/local/opnsense/scripts/filter/update_tables.py --aliases DIAGRAL_GATEWAY_MAC 2>&1',$out,$rc);
        $observation=diagral_observe();
    }
    $stamp='/var/run/diagrallocal-last-repair';
    $last=is_file($stamp)?(int)file_get_contents($stamp):0;
    if($observation['alias_ready'] && $observation['cloud_ids'] && time()-$last>=60) {
        /* Recheck CARP immediately before touching state; never act on BACKUP. */
        if(!diagral_active(shell_exec('/sbin/ifconfig '.escapeshellarg($physical))??'')) exit(75);
        file_put_contents($stamp,(string)time());
        foreach($observation['cloud_ids'] as $state) {
            exec('/sbin/pfctl -k id -k '.escapeshellarg($state).' 2>&1',$out,$rc);
            if($rc===0)$cleared++;
        }
        if($cleared) syslog(LOG_NOTICE,'Diagral Local: removed '.$cleared.' direct cloud states; local reconnect expected');
    }
}
echo json_encode(['configured_mode'=>$mode,'active'=>$observation['active'],'alias_ready'=>$observation['alias_ready'],'direct_cloud_states'=>count($observation['cloud_ids']),'states_cleared'=>$cleared,'checked_at'=>time()])."\n";
