<?php
    define('ERROR',  ' *ERROR* ');
    define('POINTS_EARNED_ORDER_TYPE', 1);
    define('BaseURL', 'http://tapin-servers.dev/');
    define('MerchantsBaseURL', 'http://tapin-servers.dev/merchants/');

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
    const DELIVERY_BOTH =12


?>