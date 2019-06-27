<?php


$config = array(
    // These are the settings for development mode
    'development' => array(
        'db' => array(
            'host'     => 'localhost',
            'dbname'   => 'artdoost_local_tapin',
            'username' => 'artdoost_dbadmin',
            'password' => 'id0ntknow',
        ),
    ),

    'testing' => array(
        'db' => array(
            'host'     => 'localhost',
            'dbname'   => 'artdoost_stage_tapin',
            'username' => 'artdoost_dbadmin',
            'password' => 'id0ntknow'

        ),
    ),

    // These are the settings for production mode
    'production' => array(
        'db' => array(
            'host'     => 'localhost',
            'dbname'   => 'artdoost_tapin_prod_v2',
            'username' => 'artdoost_tapin',
            'password' => 'mfood0716!!'
        ),
    )
);
