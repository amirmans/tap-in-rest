<?php
    define('ERROR',  ' *ERROR* ');
    define('POINTS_EARNED_ORDER_TYPE', 1);


    $application_env = getenv('EnvMode');
    $testingHost = $_SERVER['APP_HOSTNAME']."/merchants/";
    if ($application_env ==="development") {
        define('BaseURL', 'http://tapin-servers.dv/');
        define('MerchantsBaseURL', 'http://tapin-servers.dv/merchants/');
    } else if ($application_env ==='testing') {
      define('BaseURL', $_SERVER['APP_HOSTNAME']);
      define('MerchantsBaseURL', $testingHost);
    } else if ($application_env ==='production') {
        define('BaseURL', 'https://tapforall.com/tap-in-servers/merchants/');
        define('MerchantsBaseURL', 'https://tapforall.com/tap-in-servers/merchants/');
    } else {
        define('BaseURL', $_SERVER['APP_HOSTNAME']);
        define('MerchantsBaseURL', $testingHost);
    }

    //order status
    const ORDER_STATUS_DONE = 10;

    const ORDER_STATUS_COMPLETE = 3;
    const ORDER_STATUS_APPROVED = 2;
    const ORDER_STATUS_REJECTED = 0;
    const ORDER_STATUS_NEW = 1;

    // pick up  and delivery modes
    const PICKUP_COUNTER = 1;
    const PICKUP_LOCATION = 2;
    const PICKUP_BOTH = 3;

    const DELIVERY_TABLE = 4;
    const DELIVERY_LOCATION = 8;
    const DELIVERY_BOTH =12;
?>