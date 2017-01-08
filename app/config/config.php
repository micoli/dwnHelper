<?php

define('ROOT_PATH',realpath(dirname(__FILE__).'/..'));
define('APP_PATH',realpath(dirname(__FILE__).'/../App'));
define('VAR_PATH',realpath(dirname(__FILE__).'/../../var'));
define('CFG_PATH',realpath(dirname(__FILE__)));
define('VENDOR_PATH',realpath(dirname(__FILE__).'/../../vendor'));

$app['debug'					] = true;
$gblCfg['application.name'		] = 'App';
$gblCfg['application.version'	] = '0.1.0';
$gblCfg['log.level'				] = Monolog\Logger::DEBUG;
$gblCfg['log.file'				] = VAR_PATH	. '/storage/logs/app.log';
$gblCfg['cache.dir'				] = VAR_PATH	. '/cache/';
$gblCfg['smroutesloader.path'	] = CFG_PATH	. '/routes/';
$gblCfg['twig.class_path'		] = VENDOR_PATH	. '/Twig/lib';
$gblCfg['twig.path'				] = [ROOT_PATH	. '/views'];

$gblCfg['caches.options'		]=array(
	'filesystem' => array (
		'driver'	=> 'file',
		'cache_dir'	=> $gblCfg ['cache.dir']
	),
	'apc' => array (
		'driver'	=> 'apc'
	)
);
$gblCfg['caches.default']='apc';

$gblCfg['torrent'] = json_decode(file_get_contents(dirname(__FILE__).'/../../config/config.json'),true);

return $gblCfg;
