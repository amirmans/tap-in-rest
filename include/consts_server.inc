<?php
    define('ERROR',  ' *ERROR* ');
    define('POINTS_EARNED_ORDER_TYPE', 1);


    $application_env = getenv('EnvMode');
    if ($application_env ==="development") {
        define('BaseURL', 'http://tapin-servers.dv/');
        define('MerchantsBaseURL', 'http://tapin-servers.dv/merchants/');
    } else if ($application_env ==='staging') {
        define('BaseURL', 'http://tapforall.com/staging/');
        define('MerchantsBaseURL', 'http://tapforall.com/staging/merchants/');
    } else if ($application_env ==='production') {
        define('BaseURL', 'https://tapforall.com/merchants/');
        define('MerchantsBaseURL', 'https://tapforall.com/merchants/tap-in-adminpanel/');
    } else {
        define('BaseURL', 'http://tapforall.com/staging/');
        define('MerchantsBaseURL', 'http://tapforall.com/staging/merchants/');
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