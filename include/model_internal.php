<?php
error_reporting(0);
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

    // echo APPLICATION_ENV." ";
    global $config;
    $model_config = $config[APPLICATION_ENV];
    $db_host = $model_config['db']['host'];
    $db_name = $model_config['db']['dbname'];
    $db_user = $model_config['db']['username'];
    $db_pass = $model_config['db']['password'];
    $db_port = $model_config['db']['port'];

    // echo "$db_host $db_user $db_name\n";
    // not using the permanent connection any more as I understand it is not recommended for the web
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port) or die("Error - connecting to db" . $conn . mysqli_error($conn));
    $GLOBALS['conn'] = $conn;

    return $conn;
}

function getDBConnection()
{
    $conn = $GLOBALS['conn'];
    if ($conn == 0) {
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
        return ($conn->affected_rows);
    } else {
        return -1;
    }
}

function insert_business_keywords($market_name) {
    $return_data = array();

    $corp_query = "select * from corp where corp_name = '$market_name' and active = 1;";
    $corp_result = getDBresult($corp_query);

    if (empty($corp_result)) {
        $return_data['success'] = -1;
        // nothing is found
        return ($return_data);
    }
    $return_data['success'] = 0;
    $csvString = $corp_result[0]['merchant_ids'];
    $biz_ids = explode(PHP_EOL, $csvString);
    foreach ($biz_ids as $merchant) {
        $biz_array = str_getcsv($merchant);
    }

    $keywords ="";
    foreach ($biz_array as $biz) {
        $query = "select category_name from product_category where business_id = $biz";
        $result = getDBresult($query);
        if (count($result)) {
            foreach ($result as $val) {
               $keywords = $keywords . $val['category_name'].",";
            }

            if (!empty($keywords)) {
                $insert_category_query =
                    "update business_customers  set keywords = '$keywords' where businessID = $biz";
                    $rt = insertOrUpdateQuery($insert_category_query);
//                if ($rt) {
//                    $return_data['success'] = -1;
//                    break;
//                }
            }
        }
    }

    $return_data["last business id"] = $biz;
    return ($return_data);
}

function partner_etl() {
    $jsonData = file_get_contents("https://mailer.enofileonline.com/api/GetMarkets");
    $decoded_dataArray = json_decode($jsonData, true);
    $conn = connectToDB();
    foreach ($decoded_dataArray as $row) {
        $corp_external_id = $row["marketID"];
        $corp_name = $row["market"];
        $corp_address = $row["address"] . " " . $row["zip"];

//      $prepared_stmt = "INSERT INTO corp (external_id, corp_name, address) VALUES (?,?, ?)";
//      $prepared_query = $conn->prepare($prepared_stmt);
//      $rc1 = $prepared_query->bind_param('iss', $external_id,$corp_name, $address);
//
//      $rc2 = $prepared_query->execute();

        //now enter the vendor information for this market
        $vendor_url = "https://mailer.enofileonline.com/api/GetVendorsForMarket?id=".$corp_external_id;
        $jsonData = file_get_contents($vendor_url);
        $decoded_dataArray = json_decode($jsonData, true);
        $keywords = "";
        $merchant_ids = "";
        foreach ($decoded_dataArray as $row) {
//            echo $row;
            $external_id = $row['vendorID'];
            $name = $row['company'];
            $email = $row['email'];
            $phone = $row['phone'];
            $description = "";
            if (!empty($row['description']))
                $description = $row['description'];
            $website= $row['website'];
            if ($row['certifiedOrganic'] == "true") {
                $keywords = $keywords . ",". 'certifiedOrganic';
            }
            $prepared_stmt = "INSERT INTO business_customers (external_id, name, website, email, phone
               ,description , keywords) VALUES (?,?,?,?,?,?,?)";
            $prepared_query = $conn->prepare($prepared_stmt);
            $rc1 = $prepared_query->bind_param('issssss', $external_id
                ,$name, $website,$email,$phone, $description, $keywords);

            $rc2 = $prepared_query->execute();
            if ($rc2) {
                $merchant_ids = $merchant_ids . "," . $prepared_query->insert_id;
            }
        }

        $prepared_stmt = "INSERT INTO corp (external_id, corp_name, merchant_ids,address)
            VALUES (?,?,?,?)";
        $prepared_query = $conn->prepare($prepared_stmt);
        $rc1 = $prepared_query->bind_param('isss', $corp_external_id,$corp_name
            ,$merchant_ids, $corp_address);

        $rc2 = $prepared_query->execute();
    }

    return $rc2;
}

function validate_stripe_secret_key($secret_key) {
    try {
        \Stripe\Stripe::setApiKey($secret_key);

        // create a test customer to see if the provided secret key is valid
        $response = \Stripe\Customer::create(["description" => "Test Customer - Validate Secret Key"]);

        return true;
    }
// error will be thrown when provided secret key is not valid
    catch (\Stripe\Error\InvalidRequest $e) {
        // Invalid parameters were supplied to Stripe's API
        $body = $e->getJsonBody();
        $err  = $body['error'];

        $messages = array();
        $messages[] = 'Status is: ' . $e->getHttpStatus();
        $messages[] = 'Type is: ' . $err['type'];
        $messages[] = 'Code is: ' . $err['code'];
        $messages[] = 'Decline Code is: ' . $err['decline_code'];
        $messages[] = 'Message: ' . $err['message'];

        return false;
    }
    catch (\Stripe\Error\Authentication $e) {
        // Authentication with Stripe's API failed
        // (maybe you changed API keys recently)
        $body = $e->getJsonBody();
        $err  = $body['error'];

        $messages = array();
        $messages[] = 'Status is: ' . $e->getHttpStatus();
        $messages[] = 'Type is: ' . $err['type'];
        $messages[] = 'Code is: ' . $err['code'];
        $messages[] = 'Decline Code is: ' . $err['decline_code'];
        $messages[] = 'Message: ' . $err['message'];

        return false;
    }
    catch (\Stripe\Error\ApiConnection $e) {
        // Network communication with Stripe failed
        $body = $e->getJsonBody();
        $err  = $body['error'];

        $messages = array();
        $messages[] = 'Status is: ' . $e->getHttpStatus();
        $messages[] = 'Type is: ' . $err['type'];
        $messages[] = 'Code is: ' . $err['code'];
        $messages[] = 'Decline Code is: ' . $err['decline_code'];
        $messages[] = 'Message: ' . $err['message'];

        return false;
    }
    catch (\Stripe\Error\Base $e) {
        // Display a very generic error to the user, and maybe send
        // yourself an email
        $body = $e->getJsonBody();
        $err  = $body['error'];

        $messages = array();
        $messages[] = 'Status is: ' . $e->getHttpStatus();
        $messages[] = 'Type is: ' . $err['type'];
        $messages[] = 'Code is: ' . $err['code'];
        $messages[] = 'Decline Code is: ' . $err['decline_code'];
        $messages[] = 'Message: ' . $err['message'];

        return false;
    }
    catch (Exception $e) {
        // Something else happened, completely unrelated to Stripe
        $body = $e->getJsonBody();
        $err  = $body['error'];

        $messages = array();
        $messages[] = 'Status is: ' . $e->getHttpStatus();
        $messages[] = 'Type is: ' . $err['type'];
        $messages[] = 'Code is: ' . $err['code'];
        $messages[] = 'Decline Code is: ' . $err['decline_code'];
        $messages[] = 'Message: ' . $err['message'];

        return false;
    }

}

function set_stripe_key_password($business_id, $stripe_key_password) {
    $edcrypted_password = md5($stripe_key_password);
    $insert_category_query = "update business_customers set stripe_password = \"$edcrypted_password\" where businessID = $business_id";
    $rt = insertOrUpdateQuery($insert_category_query);

    return $rt;
}

if (!defined('APPLICATION_ENV')) define('APPLICATION_ENV',
    getenv('EnvMode') ? getenv('EnvMode') : 'production');

if (!empty($_REQUEST['cmd'])) {
    $cmd = $_REQUEST['cmd'];
}
else {
    $cmd = "";
}
$return_result = array();
header('Content-type: application/json');

// process loop
//$cmdCounter = 0;

    switch ($cmd) {

        case 'etl':
            $pos = stripos($cmd, "etl");
            if ($pos !== false) {
                $order_id = filter_input(INPUT_GET, 'order_id');
                $business_id = filter_input(INPUT_GET, 'business_id');
                $result = partner_etl();
                echo json_encode( $result);

//                break 2;
            }
            break;

        case 'insert_business_keywords':
            $pos = stripos($cmd, "insert_business_keywords");
            if ($pos !== false) {
                $market_name = filter_input(INPUT_GET, 'market_name');
                $result = insert_business_keywords($market_name);
                echo json_encode( $result);

//                break 2;
            }
            break;

        case 'validate_stripe_secret_key':
            $pos = stripos($cmd, "validate_stripe_secret_key");
            if ($pos !== false) {
                $stripe_secret_key = filter_input(INPUT_GET, 'stripe_secret_key');
                $result = validate_stripe_secret_key($stripe_secret_key);
                echo json_encode( $result);

//                break 2;
            }
            break;

        case 'set_stripe_key_password':
            $pos = stripos($cmd, "set_stripe_key_password");
            if ($pos !== false) {
                $stripe_key_password = filter_input(INPUT_GET, 'stripe_key_password');
                $business_id = filter_input(INPUT_GET, 'business_id');
                $result = set_stripe_key_password($business_id, $stripe_key_password);
                echo json_encode( $result);

//                break 2;
            }
            break;

        default:
            break;
    } // switch
