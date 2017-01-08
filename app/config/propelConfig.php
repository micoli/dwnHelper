<?php

function initPropelConfig($globalPropelCnxName){
	$globalPropelConfig = include __DIR__.'/propel.php';

	$serviceContainer = \Propel\Runtime\Propel::getServiceContainer ();
	$serviceContainer->checkVersion ( '2.0.0-dev' );
	$serviceContainer->setAdapterClass ( $globalPropelCnxName, $globalPropelConfig['propel']['database']['connections'][$globalPropelCnxName]['adapter'] );

	$manager = new \Propel\Runtime\Connection\ConnectionManagerSingle ();
	$manager->setConfiguration ( $globalPropelConfig['propel']['database']['connections'][$globalPropelCnxName]);
	$manager->setName ( $globalPropelCnxName );

	$serviceContainer->setConnectionManager ( $globalPropelCnxName, $manager );
	$serviceContainer->setDefaultDatasource ( $globalPropelCnxName );
}
initPropelConfig('default');

//vendor/doctrine/orm/bin/doctrine orm:convert-mapping --from-database annotation src/App/Repositories/Entities
//vendor/doctrine/orm/bin/doctrine orm:generate-entities src/App/Repositories/repositories