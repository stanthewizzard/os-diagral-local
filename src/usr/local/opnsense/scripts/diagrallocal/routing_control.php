<?php
/* Invoked by a restricted SSH key. Never accepts shell text from the client. */
require_once('config.inc');
$action = $argv[1] ?? getenv('SSH_ORIGINAL_COMMAND');
require_once(__DIR__.'/settings.php');
if (!in_array($action, ['status', 'local', 'cloud'], true)) { exit(64); }
$lock = fopen('/var/run/diagral-local-routing.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) { exit(75); }
$id = '8378647a-7145-4854-9194-32e526062df6';
require_once(__DIR__.'/routing_policy.php');
require_once(__DIR__.'/network_policy.php');
$current = mode_now($id);
$killed = 0;
if ($action !== 'status' && $action !== $current) {
    exec('/usr/local/bin/php /usr/local/opnsense/scripts/diagrallocal/firewall_local.php '.($action === 'local' ? 'apply' : 'remove').' 2>&1', $out, $rc);
    if ($rc !== 0) exit(70);
    exec('/usr/local/sbin/configctl filter reload 2>&1', $out, $rc);
    if ($rc !== 0) exit(70);
    /* Re-read native configuration after the separate PHP process wrote it. */
    $xml = simplexml_load_file('/conf/config.xml');
    $configured = false;
    foreach ($xml->nat->rule as $r) if ((string)$r['uuid'] === $id && (string)$r->disabled !== '1') $configured = true;
    $nat = shell_exec('/sbin/pfctl -sn 2>/dev/null') ?? '';
    $has_dnat = str_contains($nat, 'rdr pass on '.$physical.' inet proto tcp from <DIAGRAL_GATEWAY_MAC>');
    $has_snat = str_contains($nat, 'nat on '.$physical.' inet proto tcp from <DIAGRAL_GATEWAY_MAC>');
    $active = $has_dnat && $has_snat;
    if (($action === 'local' && (!$configured || !$active)) || ($action === 'cloud' && ($configured || $has_dnat || $has_snat))) exit(70);
    /* Kill only TCP6000 states of this MAC, never a whole subnet or all its traffic. */
    $arp = shell_exec('/usr/sbin/arp -an') ?? '';
    $ips = [];
    foreach (explode("\n", $arp) as $line) {
        if (stripos($line, $gateway_mac) !== false && preg_match('/\(([0-9.]+)\)/', $line, $m)) $ips[] = $m[1];
    }
    $states = shell_exec('/sbin/pfctl -ss -vv 2>/dev/null') ?? '';
    foreach (preg_split('/(?=^all )/m', $states) as $block) {
        $head = explode("\n", $block)[0];
        if (!str_starts_with($head, 'all tcp ') || !preg_match('/:6000(?:[ )]|$)/', $head)) continue;
        $owned = false;
        foreach ($ips as $ip) if (preg_match('/(?<![0-9.])'.preg_quote($ip, '/').':/', $head)) $owned = true;
        if (!$owned) continue;
        preg_match_all('/([0-9.]+):6000(?:[ )]|$)/', $head, $destinations);
        $scope = false;
        foreach ($destinations[1] as $dst) {
            if ($dst === $docker_ip || filter_var($dst, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) $scope = true;
        }
        if (!$scope || !preg_match('/id: ([a-f0-9]+) creatorid: ([a-f0-9]+)/', $block, $m)) continue;
        exec('/sbin/pfctl -k id -k '.escapeshellarg($m[1].'/'.$m[2]).' 2>&1', $out, $rc);
        if ($rc === 0) $killed++;
    }
    $current = $action;
}
$guard=json_decode(@file_get_contents('/var/run/diagrallocal-guard.json')?:'null',true);
$guard_last=is_array($guard)?($guard['checked_at']??0):0;
$observed=diagral_observe();
$verified=$current;
if($current==='local' && (!$observed['alias_ready'] || $observed['cloud_ids'])) $verified='unknown';
echo json_encode(['mode'=>$verified,'configured_mode'=>$current,'states_cleared'=>$killed,'active'=>$observed['active'],'alias_ready'=>$observed['alias_ready'],'direct_cloud_states'=>count($observed['cloud_ids']),'local_states'=>$observed['local_states'],'firewall'=>gethostname(),'guard_fresh'=>$guard_last>0 && time()-$guard_last<45,'guard_checked_at'=>$guard_last,'guard_last_repair_at'=>(int)(@file_get_contents('/var/run/diagrallocal-last-repair')?:0)])."\n";
