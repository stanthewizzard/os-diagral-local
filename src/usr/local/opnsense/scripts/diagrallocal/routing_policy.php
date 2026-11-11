<?php
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
