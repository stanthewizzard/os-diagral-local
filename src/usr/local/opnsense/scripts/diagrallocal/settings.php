<?php
require_once('config.inc');
$settings = $config['OPNsense']['DiagralLocal']['general'] ?? [];
$gateway_mac = strtolower($settings['gateway_mac'] ?? '');
$docker_ip = $settings['docker_ip'] ?? '';
$interface = $settings['interface'] ?? 'lan';
$physical = $config['interfaces'][$interface]['if'] ?? '';
if (!preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/', $gateway_mac)
    || !filter_var($docker_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
    || !preg_match('/^[a-zA-Z0-9_.]+$/', $physical)) {
    echo json_encode(['mode'=>'unknown','error'=>'Configure gateway MAC, Docker IPv4 and interface'])."\n";
    exit(78);
}
