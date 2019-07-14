<?php


$config = array(
    // These are the settings for development mode
    'development' => array(
        'db' => array(
            'host'     => 'localhost',
            'dbname'   => 'artdoost_local_tapin',
            'username' => 'artdoost_dbadmin',
            'password' => 'id0ntknow',
            'port'     => '3306'
        ),
    ),

    'testing' => array(
        'db' => array(
            'host'     => $_SERVER['RDS_HOSTNAME'],
            'dbname'   => $_SERVER['RDS_DB_NAME'],
            'username' => $_SERVER['RDS_USERNAME'],
            'password' => $_SERVER['RDS_PASSWORD'],
            'port'     => $_SERVER['RDS_PORT']
        ),
    ),

    // These are the settings for production mode
    'production' => array(
        'db' => array(
            'host'     => 'localhost',
            'dbname'   => 'artdoost_tapin_prod_v2',
            'username' => 'artdoost_tapin',
            'password' => 'mfood0716!!',
            'port'     => '3306'
        ),
    )
);
