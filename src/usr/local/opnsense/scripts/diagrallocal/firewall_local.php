<?php
/* Scoped persistent forwarding for the owned Diagral gateway. */
require_once('config.inc');
require_once('util.inc');
require_once(__DIR__.'/settings.php');
$action = $argv[1] ?? 'inspect';
$dnat_id = '8378647a-7145-4854-9194-32e526062df6';
$description = 'Diagral Local gateway TCP6000';
$aliases = ['DIAGRAL_GATEWAY_MAC', 'DIAGRAL_INTERNAL_NETWORKS'];
if (!in_array($action, ['apply', 'remove'])) { echo "apply or remove required\n"; exit(1); }
/* Remove only our own objects. Keep unrelated concurrent configuration. */
$config['nat']['rule'] = array_values(array_filter($config['nat']['rule'] ?? [],
    fn($r) => ($r['@attributes']['uuid'] ?? '') !== $dnat_id));
$config['nat']['outbound']['rule'] = array_values(array_filter($config['nat']['outbound']['rule'] ?? [],
    fn($r) => ($r['descr'] ?? '') !== $description.' return path'));
$config['OPNsense']['Firewall']['Alias']['aliases']['alias'] = array_values(array_filter(
    $config['OPNsense']['Firewall']['Alias']['aliases']['alias'] ?? [],
    fn($a) => !in_array($a['name'] ?? '', $aliases)));
if ($action === 'apply') {
    foreach ([
        ['880668fb-a5ec-4c10-9276-b05c796f172d', $aliases[0], 'mac', $gateway_mac],
        ['d3dfd916-08ac-4bd1-b8ee-2a4b109d8620', $aliases[1], 'network',
         "10.0.0.0/8\n172.16.0.0/12\n192.168.0.0/16\n127.0.0.0/8\n169.254.0.0/16\n224.0.0.0/4\n240.0.0.0/4"]
    ] as [$id,$name,$type,$content]) {
        $config['OPNsense']['Firewall']['Alias']['aliases']['alias'][] = [
            '@attributes'=>['uuid'=>$id], 'enabled'=>'1', 'name'=>$name,
            'type'=>$type, 'content'=>$content, 'description'=>'Diagral Local scoped routing',
            'counters'=>'0'];
    }
    $config['nat']['rule'][] = ['@attributes'=>['uuid'=>$dnat_id],
        'sequence'=>'50','disabled'=>'0','nordr'=>'0','interface'=>$interface,
        'ipprotocol'=>'inet','protocol'=>'tcp',
        'source'=>['network'=>$aliases[0], 'not'=>'0'],
        'destination'=>['network'=>$aliases[1],'not'=>'1','port'=>'6000'],
        'target'=>$docker_ip,'local-port'=>'6000','descr'=>$description,
        'natreflection'=>'disable','pass'=>'pass','nosync'=>'0'];
    $config['nat']['outbound']['rule'][] = [
        'interface'=>$interface,'ipprotocol'=>'inet','protocol'=>'tcp',
        'source'=>['network'=>$aliases[0]],'destination'=>['address'=>$docker_ip],
        'dstport'=>'6000','target'=>$interface.'ip','descr'=>$description.' return path'];
}
write_config('Diagral Local scoped routing '.$action);
echo "Scoped Diagral configuration: $action\n";
