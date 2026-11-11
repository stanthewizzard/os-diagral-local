<?php
/* Invoked by a restricted SSH key. Never accepts shell text from the client. */
require_once('config.inc');
$action = $argv[1] ?? getenv('SSH_ORIGINAL_COMMAND');
require_once(__DIR__.'/settings.php');
if (!in_array($action, ['status', 'local', 'cloud'], true)) { exit(64); }
$lock = fopen('/var/run/diagral-local-routing.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) { exit(75); }
$id = '8378647a-7145-4854-9194-32e526062df6';
function mode_now($id) {
    global $config, $physical, $docker_ip, $interface, $gateway_mac;
    $configured = false;
    foreach (($config['nat']['rule'] ?? []) as $r) {
        if (($r['@attributes']['uuid'] ?? '') === $id && empty($r['disabled'])) {
            if (($r['target'] ?? '') !== $docker_ip || ($r['interface'] ?? '') !== $interface) return 'unknown';
            $configured = true;
        }
    }
    if ($configured) {
        $mac_ok = false;
        foreach (($config['OPNsense']['Firewall']['Alias']['aliases']['alias'] ?? []) as $alias) {
            if (($alias['name'] ?? '') === 'DIAGRAL_GATEWAY_MAC' && strtolower(trim($alias['content'] ?? '')) === $gateway_mac) $mac_ok = true;
        }
        if (!$mac_ok) return 'unknown';
    }
    $nat = shell_exec('/sbin/pfctl -sn 2>/dev/null') ?? '';
    $dnat = str_contains($nat, 'rdr pass on '.$physical.' inet proto tcp from <DIAGRAL_GATEWAY_MAC>');
    $snat = str_contains($nat, 'nat on '.$physical.' inet proto tcp from <DIAGRAL_GATEWAY_MAC>');
    return $configured && $dnat && $snat ? 'local' : (!$configured && !$dnat && !$snat ? 'cloud' : 'unknown');
}
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
echo json_encode(['mode'=>$current, 'states_cleared'=>$killed])."\n";
