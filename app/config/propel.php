<?php
global $gblCfg;
return [
	'propel' => [
		'database' => [
			'connections' => [
				'default' => [
					'adapter'    => 'mysql',
					'settings'	 => array(
						'charset'	=> 'utf8'
					),
					'classname'  => 'Propel\Runtime\Connection\DebugPDO',
					'dsn'        => 'mysql:host='.$gblCfg['mysql.db.host'].';dbname='.$gblCfg['mysql.db.name'],
					'user'       => $gblCfg['mysql.db.user'],
					'password'   => $gblCfg['mysql.db.pass'],
					'attributes' => []
				]
			]
		],
		'runtime' => [
			'defaultConnection' => 'default',
			'connections' => ['default']
		],
		'generator' => [
			'defaultConnection' => 'default',
			'connections' => ['default']
		]
	]
];