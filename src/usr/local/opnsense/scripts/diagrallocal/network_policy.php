<?php
/* Pure selectors: never identify a gateway by port alone. */
function diagral_connections($states, $ips, $docker) {
    $cloud=[]; $local=0;
    foreach (preg_split('/(?=^all )/m', $states) as $block) {
        $head=explode("\n",$block)[0];
        if (!str_starts_with($head,'all tcp ')) continue;
        $owned=false;
        foreach ($ips as $ip) if (preg_match('/(?<![0-9.])'.preg_quote($ip,'/').':/', $head)) $owned=true;
        if (!$owned) continue;
        if (preg_match('/(?<![0-9.])'.preg_quote($docker,'/').':6000(?:[ )]|$)/',$head)) { $local++; continue; }
        preg_match_all('/([0-9.]+):6000(?:[ )]|$)/',$head,$destinations);
        $public=false;
        foreach ($destinations[1] as $ip) if (filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) $public=true;
        if ($public && preg_match('/id: ([a-f0-9]+) creatorid: ([a-f0-9]+)/',$block,$m)) $cloud[]=$m[1].'/'.$m[2];
    }
    return ['cloud_ids'=>array_values(array_unique($cloud)), 'local_states'=>$local];
}
function diagral_active($ifconfig) {
    if (trim($ifconfig)==='') return false;
    preg_match_all('/carp: (\w+)/',$ifconfig,$m);
    return !$m[1] || count(array_filter($m[1],fn($s)=>$s!=='MASTER'))===0;
}
function diagral_gateway_ips($arp,$mac) {
    $ips=[];
    foreach(explode("\n",$arp) as $line) if(stripos($line,$mac)!==false && preg_match('/\(([0-9.]+)\)/',$line,$m)) $ips[]=$m[1];
    return array_values(array_unique($ips));
}
function diagral_observe() {
    global $physical,$gateway_mac,$docker_ip;
    $ips=diagral_gateway_ips(shell_exec('/usr/sbin/arp -an')??'', $gateway_mac);
    $table=preg_split('/\s+/',trim(shell_exec('/sbin/pfctl -t DIAGRAL_GATEWAY_MAC -T show 2>/dev/null')??''));
    $ready=(bool)$ips && !array_diff($ips,$table);
    return ['active'=>diagral_active(shell_exec('/sbin/ifconfig '.escapeshellarg($physical))??''),'alias_ready'=>$ready]
        +diagral_connections(shell_exec('/sbin/pfctl -ss -vv 2>/dev/null')??'',$ips,$docker_ip);
}
