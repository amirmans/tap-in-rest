<?php
date_default_timezone_set('America/Los_Angeles');
static $conn = 0;

//define('__ROOT__', dirname(dirname(dirname(__FILE__))));
//$includePath = __ROOT__ . "../includes/";
include_once(dirname(dirname(__FILE__)) . '/include/config_db.inc.php');
include_once(dirname(dirname(__FILE__)) . '/utils/ti_functions.php');
include_once(dirname(dirname(__FILE__)) . '/include/consts_server.inc');
include_once(dirname(dirname(__FILE__)) . '/include/error_logging/error.php');

/*--------- database functions -----------------*/
function connectToDB()
{
    //    global $db_host, $db_user, $db_pass, $db_name;

    global $config;
    $model_config = $config[APPLICATION_ENV];
    $db_host = $model_config['db']['host'];
    $db_name = $model_config['db']['dbname'];
    $db_user = $model_config['db']['username'];
    $db_pass = $model_config['db']['password'];

    $conn = mysqli_connect('p:' . $db_host, $db_user, $db_pass, $db_name) or die("Error - connecting to db" . $conn . mysqli_error($conn));
    $GLOBALS['conn'] = $conn;

    return $conn;
}

function getDBConnection()
{
    $conn = $GLOBALS['conn'];
    if ($conn == nil) {
        $conn = connectToDB();
    }
    $GLOBALS['conn'] = $conn;
    return ($conn);
}

function getDBresult($query)
{
    $conn = connectToDB();
    $conn->set_charset("utf8");
    $result = $conn->query($query);

    $resultArr = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $resultArr[] = $row;
    }
    return $resultArr;
}

function insertOrUpdateQuery($query)
{
    $conn = connectToDB();

    if ($conn->query($query) === TRUE) {
        return 1;
    } else {
        return -1;
    }
}


function get_new_foreign_key($value_to_find, $foreign_key_array, $match_field, $value_field)
{

    foreach ($foreign_key_array as $array_elem) {
        if ($array_elem[$match_field] == $value_to_find)
            return $array_elem[$value_field];
    }

    return 0;
}

function deleteAllProductRelatedInfo($businessID, $to_db)
{

//    set @newBusinessID = $businessID;
//
//
//delete from artdoost_local_tapin.product_option where product_id in (select product_id from artdoost_local_tapin.`product` where businessID = @newBusinessID);
//delete from artdoost_local_tapin.product_option where option_id in (select option_id from artdoost_local_tapin.`option` where business_id = @newBusinessID);
//
//delete from artdoost_local_tapin.product_option where product_option_category_id in (select product_option_category_id
//from artdoost_local_tapin.`product_option_category` where business_id = @newBusinessID);
//
//delete from artdoost_local_tapin.product_option where product_option_category_id is null;
//delete from artdoost_local_tapin.product_option_category where business_id = @newBusinessID;
//delete from artdoost_local_tapin.product_category where business_id = @newBusinessID;
//delete from artdoost_local_tapin.product where businessID = @newBusinessID;
//delete from artdoost_local_tapin.`option` where business_id = @newBusinessID;
//
//delete from temp_db.product_option where product_option_category_id not in (select product_option_category_id from temp_db.product_option_category);
//delete from artdoost_local_tapin.product_option where product_option_category_id not in (select product_option_category_id from artdoost_local_tapin.product_option_category);
//
//delete from artdoost_local_tapin.product_option where option_id not in (select option_id from artdoost_local_tapin.`option`);
//delete from temp_db.product_option where option_id not in (select option_id from temp_db.`option`);

}


function move_business_info($businessID, $from_db, $to_db)
{

    deleteAllProductRelatedInfo($businessID, $to_db);

    $business_query = "INSERT INTO $to_db.business_customers SELECT * FROM $from_db.business_customers
  where businessID = $businessID";
    $conn = connectToDB();
    $conn->set_charset("utf8");
    $conn->query($business_query);
    $new_business_id = mysqli_insert_id($conn);
    if ($new_business_id <= 0) {
        $business_query = "select businessID from  $to_db.business_customers where `name` =  (SELECT
        `name` FROM $from_db.business_customers WHERE businessID = $businessID);";

        $result = getDBresult($business_query);
        $new_business_id = $result[0]['businessID'];
    }

    if ($new_business_id == 0) {
        echo "Could find $businessID" . PHP_EOL;
    }

    //product_option_category
    $select_from_option_category_query = "SELECT `business_id`, `name`, `desc`, `only_choose_one`, `listing_order`, `product_option_category_id`
    from $from_db.`product_option_category` where `business_id` = $businessID;";
    $result = $conn->query($select_from_option_category_query);

    $product_option_category_ids = array();
    $product_option_category_id = array();
    while ($resultRow = mysqli_fetch_assoc($result)) {
        $product_option_category_name = $resultRow['name'];
        $select_to_option_category_query = "SELECT * from $to_db.`product_option_category`
        where `business_id` = $new_business_id and `name` = \"$product_option_category_name\";";
        $to_db_result = getDBresult($select_to_option_category_query);
        $product_option_category_name = $conn->real_escape_string($product_option_category_name);

        if (empty($to_db_result)) {
            $category_desc = $resultRow['desc'];
            $category_only_choose_one = $resultRow['only_choose_one'];
            $category_listing_order = $resultRow['listing_order'];

            $insert_query = "insert into $to_db.product_option_category ( `business_id`, `name`, `desc`
            , `only_choose_one`, `listing_order`)
            values ( $businessID, '$product_option_category_name', '$category_desc', '$category_only_choose_one',
            '$category_listing_order'); ";
            $conn->query($insert_query);
            $new_product_option_category_id = mysqli_insert_id($conn);
            if ($new_product_option_category_id == 0) {
                echo "Error in processing Product Option Category - Something went wrong in inserting $product_option_category_name" . PHP_EOL;
            }

            $product_option_category_id["to_db"] = $new_product_option_category_id;
        } else {
            $product_option_category_id["to_db"] = $to_db_result[0]["product_option_category_id"];
        }

        $product_option_category_id["from_db"] = $resultRow["product_option_category_id"];
        $product_option_category_ids[] = $product_option_category_id;
    }

    // option
    $select_from_option_query = "SELECT `business_id`, `product_option_category_id`, `name`, `price`, `description`, `availability_status`, `option_id`
    from $from_db.`option` where `business_id` = $businessID;";
    $result = $conn->query($select_from_option_query);

    $option_ids = array();
    $option_id = array();
    while ($resultRow = mysqli_fetch_assoc($result)) {
        $option_name = $resultRow['name'];
        $option_price = $resultRow['price'];
        $option_description = $resultRow['description'];
        $option_availability_status = $resultRow['availability_status'];

        $new_foreign_key = get_new_foreign_key($resultRow["product_option_category_id"]
            , $product_option_category_ids, "from_db", "to_db");

        $select_to_option_query = "SELECT * from $to_db.`option`
        where `business_id` = $new_business_id and `name` = '$option_name' and price='$option_price'
        and description = '$option_description' and availability_status = '$option_availability_status'
        and `product_option_category_id` = '$new_foreign_key';";
        $to_db_result = getDBresult($select_to_option_query);

        if (empty($to_db_result)) {

            $insert_query = "INSERT INTO $to_db.`option`( `business_id` , `product_option_category_id` , `name`
            ,`price` ,`description` , `availability_status`)
            values ( '$new_business_id', '$new_foreign_key' , '$option_name' ,'$option_price', '$option_description'
            , '$option_availability_status'); ";
            $conn->query($insert_query);
            $new_option_id = mysqli_insert_id($conn);
            if ($new_option_id == 0) {
                echo "new_option_id is 0 - Could not insert $product_option_category_id in option" . PHP_EOL;
            }

            $option_id["to_db"] = $new_option_id;
        } else if (empty($to_db_result[0]["product_option_category_id"])) {
            $to_db_option_id = $to_db_result[0]["option_id"];
            $update_query = "update $to_db.`option` set `product_option_category_id` = '$new_foreign_key'
            where `option_id` = '$to_db_option_id';";
            $conn->query($update_query);
            $option_id["to_db"] = $to_db_option_id;
        } else {
            $option_id["to_db"] = $to_db_result[0]["option_id"];
        }

        $option_id["from_db"] = $resultRow["option_id"];
        $option_ids[] = $option_id;

    }

    // product category
    $select_product_category_query = "SELECT * from $from_db.`product_category` where `business_id` = $businessID;";
    $result = $conn->query($select_product_category_query);

    $product_category_ids = array();
    $product_category_id = array();
    while ($resultRow = mysqli_fetch_assoc($result)) {
        $product_category_name = $resultRow['category_name'];
        $select_to_product_category_query = "SELECT * from $to_db.`product_category`
        where `business_id` = $new_business_id and `name` = \"$product_category_name\";";
        $to_db_result = getDBresult($select_to_product_category_query);

        if (empty($to_db_result)) {
            $product_category_desc = $resultRow['desc'];
            $product_category_icon_url = $resultRow['icon_url'];
            $product_category_listing_order = $resultRow['listing_order'];

            $insert_query = "insert into $to_db.product_category ( `business_id`, `category_name`, `desc`
            , `icon_url`, `listing_order`)
            values ( $businessID, '$product_category_name', '$product_category_desc', '$product_category_icon_url',
            '$product_category_listing_order'); ";
            $conn->query($insert_query);
            $new_product_category_id = mysqli_insert_id($conn);
            if ($new_product_category_id == 0) {
                echo "$new_product_category_id is zero" . PHP_EOL;
            }

            $product_category_id["to_db"] = $new_product_category_id;
        } else {
            $product_category_id["to_db"] = $to_db_result[0]["product_category_id"];
        }

        $product_category_id["from_db"] = $resultRow["product_category_id"];
        $product_category_ids[] = $product_category_id;
    }

    // product
    $select_product_query = "SELECT * from $from_db.`product` where `businessID` = $businessID;";
    $result = $conn->query($select_product_query);

    $product_ids = array();
    $product_id = array();
    while ($resultRow = mysqli_fetch_assoc($result)) {
        $product_name = $resultRow['name'];
        $product_price = $resultRow['price'];
        $product_short_description = $resultRow['short_description'];
        $product_availability_status = $resultRow['availability_status'];
        $product_name = $conn->real_escape_string($product_name);

        //for insert
        $product_long_description = $resultRow['long_description'];
        $product_listing_order = $resultRow['listing_order'];
        $product_more_information = $resultRow['more_information'];
        $product_keywords = $resultRow['product_keywords'];
        $product_detail_information = $resultRow['detail_information'];
        $product_bought_with_rewards = $resultRow['bought_with_rewards'];
        $product_pictures = $resultRow['pictures'];
        $product_has_option = $resultRow['has_option'];

        $new_product_category_id = get_new_foreign_key($resultRow["product_category_id"]
            , $product_category_ids, "from_db", "to_db");

        $select_product_query = "SELECT * from $to_db.`product`
        where `businessID` = $new_business_id and `name` = '$product_name' and `product_category_id` = '$new_product_category_id'
        and price='$product_price'
        and short_description = '$product_short_description' and availability_status = '$product_availability_status';";
        $to_db_result = getDBresult($select_product_query);

        if (empty($to_db_result)) {
            $insert_query = "INSERT INTO $to_db.`product`(`businessID`, `name`
            , `product_category_id`, `short_description`, `long_description`, `price`, `pictures`, `detail_information`
            , `availability_status`, `has_option`, `bought_with_rewards`, `more_information`, `product_keywords`
            , `listing_order`)
            values ( '$new_business_id', '$product_name', '$new_product_category_id', '$product_short_description'
            , '$product_long_description', '$product_price', '$product_pictures', '$product_detail_information', '$product_availability_status'
            , '$product_has_option', '$product_bought_with_rewards', '$product_more_information', '$product_keywords'
            , '$product_listing_order');";
            $conn->query($insert_query);
            $new_product_id = mysqli_insert_id($conn);
            if ($new_product_id == 0) {
                echo "Error in processing Products.  Something went wrong with $new_product_id" . PHP_EOL;
            }


            $product_id["to_db"] = $new_product_id;
        } else {
            $product_id["to_db"] = $to_db_result[0]["product_id"];
        }

        $product_id["from_db"] = $resultRow["product_id"];
        $product_ids[] = $product_id;

    }

    // product_option
    $select_from_product_option_query = "select o.* from $from_db.product_option o left join $from_db.product p
    on o.product_id = p.product_id where p.businessID = $businessID;";
    $result = $conn->query($select_from_product_option_query);

    $product_option_ids = array();
    $product_option_id = array();
    while ($resultRow = mysqli_fetch_assoc($result)) {
        $product_option_name = $resultRow['name'];
        $product_option_price = $resultRow['price'];
        $product_option_description = $resultRow['description'];
        $product_option_availability_status = $resultRow['availability_status'];
        $product_option_name = $conn->real_escape_string($product_option_name);
        $product_option_description = $conn->real_escape_string($product_option_description);

        $new_product_option_category_id = get_new_foreign_key($resultRow["product_option_category_id"]
            , $product_option_category_ids, "from_db", "to_db");
        $new_product_id = get_new_foreign_key($resultRow["product_id"]
            , $product_ids, "from_db", "to_db");
        $new_option_id = get_new_foreign_key($resultRow["option_id"]
            , $option_ids, "from_db", "to_db");

        if ($new_product_option_category_id == 0 || $new_product_id == 0 || $new_option_id == 0) {
            echo "Wrong!" . PHP_EOL;
        }

        $select_to_product_option_query = "SELECT * from $to_db.`product_option`
        where `name` = '$product_option_name' and price='$product_option_price'
        and description = '$product_option_description' and availability_status = '$product_option_availability_status'
        and `product_option_category_id` = '$new_product_option_category_id'
        and `product_id` = '$new_product_id'
        and `option_id` = '$new_option_id';";
        $to_db_result = getDBresult($select_to_product_option_query);


        if (empty($to_db_result)) {
            $insert_query = "INSERT INTO $to_db.`product_option`( `option_id`, `product_id`
            , `product_option_category_id` , `name` ,`price` ,`description` , `availability_status`)
            values ('$new_option_id', '$new_product_id' , '$new_product_option_category_id'
            , '$product_option_name','$product_option_price','$product_option_description'
            , '$product_option_availability_status'); ";
            $conn->query($insert_query);
            $new_product_option_id = mysqli_insert_id($conn);

            if ($new_product_option_id == 0) {
                echo "Wrong - new option id is 0!" . PHP_EOL;
            }

            $product_option_id["to_db"] = $new_product_option_id;
        } else {
            $to_db_product_option_id = $to_db_result[0]["product_option_id"];
            $update_query = "update $to_db.`product_option` set `product_option_category_id` = '$new_product_option_category_id'
                  ,`option_id` = '$new_option_id'
                  ,`product_id` = '$new_product_id' where `product_option_id` = '$to_db_product_option_id';";
            $conn->query($update_query);
            $product_option_id["to_db"] = $to_db_product_option_id;
        }

        $product_option_id["from_db"] = $resultRow["product_option_id"];
        $product_option_ids[] = $product_option_id;

    }


}

// main block
if (!defined('APPLICATION_ENV')) define('APPLICATION_ENV',
    getenv('EnvMode') ? getenv('EnvMode') : 'production');


$cmd = $_REQUEST['cmd'];
$return_result = array();
header('Content-type: application/json');

// process loop
switch ($cmd) {
    case 'calc_pickup_cutoff_dates':
        $int_weekday = filter_input(INPUT_GET, 'week_day_no');
        $no_days = filter_input(INPUT_GET, 'no_days_before_market_day');
        $dateArr = calc_pickup_cutoff_date( $int_weekday, $no_days);
        echo json_encode($dateArr);

        break;

    default:
        break;
} // switch
?>