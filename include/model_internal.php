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
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $resultArr[] = $row;
        }
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

/**
 * @return mixed
 * Process of activating a business for a corp (vendor for a market):
 * 1. get all the data from corp and business_customer table
 * 2. find the business that are marked active
 * 3. get the user_ids to build the directory and the url
 * 4. both url and the directory are sub to the parent corp in the corp table
 * 5. cope index.php to the created sub directories
 * 6. build the passwords (site and stripe), encrypt and  update the business_customers table
 * 7. Also set the beta flag
 * 8. Anything wrong: log the error and continue
 */
function activate_businesses_for_parent_corp($corp_parent_name, $pwd_prefix, $stripe_pass_prefix, $index_name) {
    $corp_query = "select * from corp where parent_corp = '$corp_parent_name';";
    $corp_result = getDBresult($corp_query);
    $merchant_ids ="";
    foreach ($corp_result as $corp) {
        $merchant_ids .= $corp['merchant_ids'].",";
    }

    $dir1 = getenv('CodeIgniterRootDirectory');
    chdir(dirname($dir1, 1));
    if (!file_exists($corp_parent_name)) {
        mkdir($corp_parent_name, 0777, true);
    }
    chdir($corp_parent_name);
    $general_copy_dest = getcwd() . '/general_function.php';
    if (!file_exists($general_copy_dest)) {
        copy("../general_function.php", $general_copy_dest);
    }
    $array[] = str_getcsv($merchant_ids);
    foreach($array[0] as $value) //loop over values
    {
        $biz_query = "select username from business_customers where businessID = $value and active=1;";
        $user_arr = getDBresult($biz_query);
        $user_id ="";
        if (!empty($user_arr)) {
            $user_id = $user_arr[0]['username'];
        }
        if (empty($user_id)) {
            $result[] = array(
                'user_id' => "business_id: $value did not have a user ID");
            continue;
        }

        try {
            $business_dir_created =0;
            if (!file_exists($user_id)) {
//                echo $user_id . PHP_EOL;
                $dir = getcwd() . "/". $user_id;
                $business_dir_created = (mkdir($dir, 0777, true));
            }
        }
        catch(Exception $e) {
            echo 'Message: ' .$e->getMessage();
        }
        $index_copy_dest = getcwd() . '/'. $user_id . '/index.php';
        if (copy("../index_template.php", $index_copy_dest)) {
//            echo $user_id .PHP_EOL;
        }
        if (($value % 2) == 0) {
            $stripe_pass_postfix = "??";
            $pwd_postfix = "?";
        } else {
            $stripe_pass_postfix = "!!";
            $pwd_postfix = "!";
        }
        $temp_id = (strlen($user_id)>6)?substr($user_id, 2,3):"mess";
        $password = $pwd_prefix . $value . $temp_id .$pwd_postfix;
        $stripe_password = $stripe_pass_prefix .$value .$stripe_pass_postfix;

        // insert the passwords if the fields are empty; we don't want to overwrite the exisitig ones
        $encrypted_password = md5($stripe_password);
        $insert_stripe_password_query =
            "update business_customers set stripe_password = \"$encrypted_password\"
             where businessID=$value and (stripe_password IS NULL OR LENGTH(stripe_password)=0); ";
        $stripe_password_created = insertOrUpdateQuery($insert_stripe_password_query);

        $encrypted_password = md5($password);
        $insert_password_query =
            "update business_customers set password = \"$encrypted_password\"
             where businessID=$value and (password IS NULL OR LENGTH(password)=0); ";
        $password_created = insertOrUpdateQuery($insert_password_query);

        $result[] = array(
            'user_id' => $user_id
            , 'password' =>$password
            ,'stripe_password'=> $stripe_password
            ,'Business directory created'=>$business_dir_created
            ,'stripe_password_created'=> $stripe_password_created
            ,'password_created'=>$password_created
            );
    }

    return ($result);
}


//TODO
function setActiveVendorsForNextMarketDay() {
    $corp_data = array();

    $corp_query = "select * from corp where domain <> 'default' and active = 1;";
    $corp_result = getDBresult($corp_query);

    if (!empty($corp_result)) {
        $corps_data['success'] = 0;
    } else {
        $corps_data['success'] = -1;
        // nothing is found, we add our default corp for people who want to see what they are missing :-)
        $corp_query = "select * from corp where domain = 'default'";
        $corp_result = getDBresult($corp_query);
    }

    // modify for the client apps
    $dateArr = array();
    foreach ($corp_result as &$row) {
        $weekday = $row['delivery_week_days'];
        $no_days = $row['cutoff_no_days'];
        calc_pickup_cutoff_date($dateArr, $weekday,$no_days);
        $time_string =  date('h:i', strtotime($row["delivery_time"]));
        $row["pickup_date"] = $dateArr["pickup_date"] . " " . $time_string;
        $time_string =  date('h:i', strtotime($row["cutoff_time"]));
        $row["cutoff_date"] = $dateArr["cutoff_date"] . " " . $time_string;
    }
    $corps_data['data'] = $corp_result;
    $corps_data['success'] = 1;

    return ($corps_data);
}


function is_business_available_on_next_market_day($corp_id,$business_id) {
    $today = date('m/d/yy');
    $availability_query = "select availability_dates from business_availability where corp_id = $corp_id and business_id = $business_id;";
    $availability_arr = getDBresult($availability_query);
    $availability_dates = $availability_arr[0]['$availability_dates'];

    $array[] = str_getcsv($availability_dates);
    foreach($array[0] as $value) //loop over values
    {
        echo $value  . PHP_EOL;
        $temp_avail_date = strtotime($value);
        if ($temp_avail_date > $today) {
            echo " got it.";
        }
    }
}
//function partner_etl_all_businesses_availabilities_for_corp($external_corp_id) {
//    $string = 'foo, bar, baz.';
//    $string = preg_replace('/\.$/', '', $string); //Remove dot at end if exists
//    $array2 = explode(', ', $string); //split string into array seperated by ', '
//    $array = array();
//    $array[] = str_getcsv($line);
//    foreach($array as $value) //loop over values
//    {
//        echo $value . PHP_EOL; //print value
//    }
//}

function partner_etl_all_businesses_availabilities_for_corp($corp_external_id) {
    $corp_query = "select corp_id, merchant_ids from corp where external_id= $corp_external_id;";
    $business_ids_arr = getDBresult($corp_query);
    $corp_id = $business_ids_arr[0]['corp_id'];
    $busiesses_set = $business_ids_arr[0]['merchant_ids'];
    $busiesses_set = preg_replace('/\.$/', '', $busiesses_set); //Remove dot at end if exists
    $array2 = explode(', ', $busiesses_set); //split string into array seperated by ', '
    $array = array();
    $array[] = str_getcsv($busiesses_set);
    foreach($array[0] as $value) //loop over values
    {
        $biz_query = "select external_id from business_customers where businessID = $value;";
        $external_id_arr = getDBresult($biz_query);
        $external_id = $external_id_arr[0]['external_id'];
        partner_etl_business_availabilities($corp_external_id,$corp_id, $value, $external_id);
        echo $value  . PHP_EOL;
    }
}


function partner_etl_business_availabilities($external_corp_id, $corp_id, $internal_business_id, $external_vendor_id) {
    $availability_dates =
    file_get_contents("http://mailer.enofileonline.com/api/GetVendorDatesForMarket?marketID=$external_corp_id&vendorID=$external_vendor_id");
    $json_available_dates = json_decode($availability_dates);
    $csv_availability_dates = "";
    foreach($json_available_dates as $key => $value)
    {
//        $temp =  'Your key is: '.$key.' and the value of the key is:'.$value;
        if ($key < 1) {
            $csv_availability_dates = $value;
            continue;
        }
        $csv_availability_dates = $csv_availability_dates . ",". $value;
    }
    $prepared_stmt = "INSERT INTO business_availability (business_id, corp_id, availability_dates) VALUES (?,?,?)
                    ON DUPLICATE KEY UPDATE availability_dates = VALUES(availability_dates);";
    $conn = connectToDB();
    $prepared_query = $conn->prepare($prepared_stmt);
    $rc1 = $prepared_query->bind_param('iis', $internal_business_id, $corp_id
        , $csv_availability_dates);
    $rc2 = $prepared_query->execute();

    return (json_decode($rc2));
}

function partner_etl_products_for_vendor($external_corp_id, $internal_business_id, $external_vendor_id) {
    $jsonData =
        file_get_contents("http://mailer.enofileonline.com/api/getvendorproductsformarket?vendorID=$external_vendor_id&marketID=$external_corp_id");
    $decoded_dataArray = json_decode($jsonData, true);
    $conn = connectToDB();
    // if the json the product list is empty, we return falso;
    $rc1 = false;
    $rc2 = false;
    foreach ($decoded_dataArray as $row) {
        $external_id = $row["productID"];
        $name = $row["product"];
        $short_description = $row["info"]; $short_description .= $row["miscInfo"];
        if (empty($short_description)) {
            $short_description = "";
        }
        $keywords = $row["keywords"];
        if (empty($keywords)) {
            $keywords="";
        }
        $prepared_stmt = "INSERT INTO product (businessID, external_id, `name`
               ,short_description) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    short_description = VALUES(short_description),
                    short_description = VALUES(`name`);";
        $prepared_query = $conn->prepare($prepared_stmt);
        $rc1 = $prepared_query->bind_param('isss', $internal_business_id, $external_id
            ,$name, $short_description);

        $rc2 = $prepared_query->execute();
    }

    return $rc2;
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
                $internal_business_id = $prepared_query->insert_id;
                partner_etl_business_availabilities($corp_external_id, $internal_business_id, $external_id);
                partner_etl_products_for_vendor($corp_external_id, $internal_business_id, $external_id);
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
    $insert_category_query = "update business_customers set stripe_password = \"$edcrypted_password\", `decoded_stripe_password`=\"$stripe_key_password\" where businessID = $business_id";
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
        case 'partner_etl_products_for_vendor':
            $pos = stripos($cmd, "partner_etl_products_for_vendor");
            if ($pos !== false) {
                $external_corp_id = filter_input(INPUT_GET, 'external_corp_id');
                $external_vendor_id = filter_input(INPUT_GET, 'external_vendor_id');
                $internal_business_id = filter_input(INPUT_GET, 'internal_business_id');
                $result = partner_etl_products_for_vendor($external_corp_id, $internal_business_id, $external_vendor_id);
                echo json_encode( $result);

//                break 2;
            }
            break;

        case 'partner_etl_all_businesses_availabilities_for_corp':
            $pos = stripos($cmd, "partner_etl_all_businesses_availabilities_for_corp");
            if ($pos !== false) {
                $external_corp_id = filter_input(INPUT_GET, 'external_corp_id');
                $result = partner_etl_all_businesses_availabilities_for_corp($external_corp_id);
                echo json_encode($result);

//                break 2;
            }
            break;

        case 'partner_etl_business_availabilities':
            $pos = stripos($cmd, "partner_etl_business_availabilities");
            if ($pos !== false) {
                $external_corp_id = filter_input(INPUT_GET, 'external_corp_id');
                $external_vendor_id = filter_input(INPUT_GET, 'external_vendor_id');
                $internal_business_id = filter_input(INPUT_GET, 'internal_business_id');
                $result = partner_etl_business_availabilities($external_corp_id, $internal_business_id, $external_vendor_id);
                echo json_encode( $result);

//                break 2;
            }
            break;

        case 'is_business_available_on_next_market_day':
            $pos = stripos($cmd, "is_business_available_on_next_market_day");
            if ($pos !== false) {
                $corp_id = filter_input(INPUT_GET, 'corp_id');
                $business_id = filter_input(INPUT_GET, 'business_id');
                $result = is_business_available_on_next_market_day($corp_id,$business_id);
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

        case 'activate_businesses_for_parent_corp':
            $pos = stripos($cmd, "activate_businesses_for_parent_corp");
            if ($pos !== false) {
                $stripe_password_prefix = filter_input(INPUT_GET, 'stripe_password_prefix');
                $pwd_prefix = filter_input(INPUT_GET, 'pwd_prefix');
                $corp_parent_name = filter_input(INPUT_GET, 'corp_parent_name');
                $result = activate_businesses_for_parent_corp($corp_parent_name, $pwd_prefix,$stripe_password_prefix,"index.php");
                echo json_encode( $result);
            }
            break;

        default:
            break;
    } // switch
