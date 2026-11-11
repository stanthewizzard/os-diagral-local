<?php
require_once('script/load_phalcon.php');
require_once('config.inc');
require_once('util.inc');
$name='os-diagral-local';
if (getenv('PKG_UPGRADE') !== false && ($argv[1] ?? '') === 'remove') exit(0);
$plugins=array_values(array_filter(explode(',', $config['system']['firmware']['plugins'] ?? '')));
$plugins=array_values(array_filter($plugins, static fn($value) => $value !== $name));
if (($argv[1] ?? '') === 'add') $plugins[]=$name;
elseif (($argv[1] ?? '') !== 'remove') throw new RuntimeException('Use add or remove');
$config['system']['firmware']['plugins']=implode(',', $plugins);
write_config('Update local Diagral Local plugin registration');
(new OPNsense\Base\Menu\MenuSystem())->invalidateCache();
