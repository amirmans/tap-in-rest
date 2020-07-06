<?php


$config = array(
	// These are the settings for development mode
	'development' => array(
		'db' => array(
			'host'     => getenv('DB_Host'),
			'dbname'   => getenv('DB_Name'),
			'username' => getenv('DB_Username'),
			'password' => getenv('DB_Password'),
			'port'     => '3306'
		),
	),

	'testing' => array(
		'db' => array(
			'host'     => getenv('DB_Host'),
			'dbname'   => getenv('DB_Name'),
			'username' => getenv('DB_Username'),
			'password' => getenv('DB_Password'),
			'port'     => '3306'
		),
	),

	// These are the settings for production mode
	'production' => array(
		'db' => array(
			'host'     => getenv('DB_Host'),
			'dbname'   => getenv('DB_Name'),
			'username' => getenv('DB_Username'),
			'password' => getenv('DB_Password'),
			'port'     => '3306'
		),
	)
);
