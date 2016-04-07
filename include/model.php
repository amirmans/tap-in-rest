<?php
date_default_timezone_set('America/Los_Angeles');
$conn = nil;

//define('__ROOT__', dirname(dirname(dirname(__FILE__))));
//$includePath = __ROOT__ . "../includes/";
include_once(dirname(dirname(__FILE__)) . '/include/config_db.inc.php');
include_once(dirname(dirname(__FILE__)) . '/utils/ti_functions.php');
include_once(dirname(dirname(__FILE__)) . '/include/consts.inc');
include_once(dirname(dirname(__FILE__)) . '/error_logging/error.php');




/*--------- database functions -----------------*/
function connectToDB()
{
    global $db_host, $db_user, $db_pass, $db_name;
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

function AllDeliveriesForDate($decoded_stop_date)
{
    $decoded_stop_date = preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($decoded_stop_date));
    $stop_date = html_entity_decode($decoded_stop_date, null, 'UTF-8');

    $sqlStatement = "SELECT a.home_phone, a.method, a.order_number, a.pickup_date, a.delivery_date, a.s_i, a.driverins "
        . ",CONCAT_WS(\" \", b.first_name, b.last_name) as name, "
        . "b.email, b.alldriver,"
        . "CONCAT_WS(\" \", b.address_1, b.address_2, CONCAT_WS(\",\", b.city, state), b.zip_code) as address"
        . ", CONCAT_WS(\" \", b.address_1, b.address_2) as spot_address, b.lat as lat, b.lng as lng
  FROM t_order "
        . "AS a INNER JOIN t_regist AS b ON a.home_phone = b.home_phone "
        . "where (delivery_date = '$stop_date')";

    return (getDBresult($sqlStatement));
}

function AllDeliveriesBetweenDates($decoded_stop_date1, $decoded_stop_date2)
{
    $decoded_stop_date1 = preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($decoded_stop_date1));
    $stop_date1 = html_entity_decode($decoded_stop_date1, null, 'UTF-8');
    $decoded_stop_date2 = preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($decoded_stop_date2));
    $stop_date2 = html_entity_decode($decoded_stop_date2, null, 'UTF-8');

    $sqlStatement = "SELECT a.home_phone, a.method, a.order_number, a.pickup_date, a.delivery_date, a.s_i, a.driverins "
        . ",CONCAT_WS(\" \", b.first_name, b.last_name) as name, "
        . "b.email, b.alldriver,"
        . "CONCAT_WS(\" \", b.address_1, b.address_2, CONCAT_WS(\",\", b.city, state), b.zip_code) as address"
        . ", CONCAT_WS(\" \", b.address_1, b.address_2) as spot_address, b.lat as lat, b.lng as lng FROM t_order "
        . "AS a INNER JOIN t_regist AS b ON a.home_phone = b.home_phone "
        . "where (delivery_date >= '$stop_date1') and (delivery_date < '$stop_date2')";

    return (getDBresult($sqlStatement));
}

function products_for_business($businessID, $consumer_id)
{
    if (empty($consumer_id)) {
        $consumer_id = -1;
    }
    $product_query = "SELECT distinct product_id, category_name, s.businessID, s.SKU, s.name, s.product_category_id
       ,s.short_description, s.long_description, s.availability_status, s.price, s.sales_price, s.sales_start_date, s.sales_end_date
       ,s.pictures, s.detail_information, s.runtime_fields, s.runtime_fields_detail
       ,s.has_option, s.bought_with_rewards, s.more_information
       ,q.avg as ti_rating, q.consumer_id
      from (SELECT distinct p.product_id, p.businessID, p.SKU, p.name, p.product_category_id,
       p.short_description, p.long_description, p.price, p.pictures, p.detail_information,
       p.runtime_fields, p.sales_price, p.sales_start_date, p.sales_end_date, p.availability_status,
       p.has_option, p.bought_with_rewards, p.more_information, p.runtime_fields_detail, c.category_name
           FROM product p, product_category c, product_option o
       WHERE p.businessID = $businessID and p.availability_status = 1 AND p.product_category_id = c.product_category_id) as s
   left join (select id, avg, consumer_id from rating where type = 2 and consumer_id = $consumer_id) as q on q.id = s.product_id;";

    $conn = connectToDB();
    $conn->set_charset("utf8");
    $product_result = $conn->query($product_query);

    $resultArr = array();
    $category_name = "";
    while ($row = mysqli_fetch_assoc($product_result)) {
        if (!empty($row["pictures"])) {
            $row["pictures"] = removeslashes($row["pictures"]);
        }
        if (empty($row["ti_rating"]) || (strcasecmp($row["ti_rating"], "Null") == 0) ) {
            $row["ti_rating"] = 0.0;
        }
        // if (!empty($row["ti_rating"]) && $row["ti_rating"]> 4.5) {
        //     $favorite[] = $row;
        // }

        if ($row["category_name"] <> $category_name) {
            $category_name = $row["category_name"];
        }
        $product_id = $row["product_id"];
        $option_query = "select option_id, name, price, description from product_option where product_id = $product_id and availability_status = 1;";
//            $option_result = $conn->query($option_query);
        $option_result = $conn->query($option_query);
        $option_resultArr["options"] = array();
        while ($option_row = mysqli_fetch_assoc($option_result)) {
            $option_resultArr["options"][] = $option_row;
        }
        $row["options"] = $option_resultArr["options"];
        $resultArr[$category_name][] = $row;
    }
    // if (empty($favorite)) {
    //     $emptyArr = array();
    //     $resultArr["favorites"] = $emptyArr;
    // } else {
    //     $resultArr["favorites"] = $favorite;
    // }
    // $resultArr["favorites"] = $favorite;

    return $resultArr;
}


function previous_order($business_id, $consumer_id) {
    $query = "select i.* from order_item i inner join (select max(order_id) id from `order`
      where business_id = $business_id and consumer_id = $consumer_id) t on t.id = i.order_id;";

    return (getDBresult($query));
}


function save_order($business_id, $customer_id, $total, $orderData) {
  $conn = getDBConnection();
  $insert_query = "insert into `order` (business_id, consumer_id, total, status, date)
    Values ($business_id, $customer_id, $total, 1, now());";
    $conn->query($insert_query);

    $order_id = mysqli_insert_id($conn);


    $prepared_stmt = "INSERT INTO order_item (order_id, product_id, option_ids, price, quantity) VALUES (?,?,?,?,?)";
     foreach ($orderData as $orderRow) {
         $option_ids_fld = json_decode ($orderRow["options"]);
         $option_ids_fld = implode (', ',$orderRow["options"]);
         $prepared_query = $conn->prepare($prepared_stmt);
         $rc = $prepared_query->bind_param('sssdi', $order_id, $orderRow["product_id"],$option_ids_fld,
                $orderRow["price"], $orderRow["quantity"]);
         $rc = $prepared_query->execute();
     }

    return $order_id;
}

function save_points_for_customer_in_business($businessID, $consumerID, $orderID, $points, $pointReason) {
    if ($points < 0)
    {
        $time_field_name = "time_redeemed";
    }
    else {
        $time_field_name = "time_earned";
    }
    $insert_query = "INSERT INTO points (consumer_id, business_id, points_reason_id, points, order_id, $time_field_name )
      VALUES ($consumerID, $businessID, $pointReason, $points, $orderID, now());";
    getDBresult($insert_query);
    return 1;
}

function get_all_points_for_customer($businessID, $consumerID) {
  $query = "select consumer_id, business_id, points_reason_id, points, order_id, time_earned,time_redeemed
    from points where
    (ISNULL(time_expired) = 1 or time_expired > now())
    and consumer_id = $consumerID";

    if ($businessID  && $businessID <> "0") {
     $query .= " and business_id = $businessID;";
   }
  else {
    $query .= ";";
  }

    $result = getDBresult($query);

    $total_redeemed_points = 0;
    $total_earned_points = 0;
    $total_available_points = 0;
    $points_earned = array();
    $points_redeemed = array();
    foreach ($result as $row) {
        if ($row["points"]  > 0) {
          $points_earned[] = $row;
          $total_earned_points += $row["points"];
        } else {
          $points_redeemed[] = $row;
          $total_redeemed_points -= $row["points"];
        }
    }
    $total_available = $total_earned_points - $total_redeemed_points;
    $return_result["total_earned_points"] = $total_earned_points;
    $return_result["total_redeemed_points"] = $total_redeemed_points;
    $return_result["total_available_points"] = $total_available;
    $return_result["points_earned"] =  $points_earned;
    $return_result["points_redeemed"] =  $points_redeemed;

    $next_level_query= " SELECT coalesce(points,0) as points, coalesce(equivalent,0) as dollar_value, points_level_name
      , message FROM points_map main RIGHT JOIN
      (SELECT MIN(points) as next_level FROM points_map WHERE  $total_available < points ) as sub
      on sub.next_level = main.points";
    $current_level_query = "SELECT coalesce(points,0) as points, coalesce(equivalent,0) as dollar_value, points_level_name
      , message FROM points_map main RIGHT JOIN
      (SELECT MAX(points) as next_level FROM points_map WHERE  $total_available >= points ) as sub on sub.next_level = main.points";

    if ($businessID  && $businessID <> "0") {
        $next_level_query .= " and business_id = $businessID;";
        $current_level_query .= " and business_id = $businessID;";
    }
    else {
        $next_level_query .= ";";
        $current_level_query .= ";";
    }

    $points_next_level = getDBresult($next_level_query);
    $points_current_level = getDBresult($current_level_query);

    $return_result["current_points_level"] = $points_current_level[0];
    $return_result["next_points_level"] = $points_next_level[0];

    return $return_result;
}
/********************************************************************************************************/
/*   SIS Inv                                                                                            */
/********************************************************************************************************/
function login($userName, $password)
{
    $query = "select count(*) from user  where user_name = '$userName' and password = '$password';";
    return (getDBresult($query));
}

function sis_worksteps()
{
    $query = "select workstep_name from workstep_info;";
    return (getDBresult($query));
}

function getAllBatchesForJob($job_id)
{
    $query = "SELECT
  j.order_date, c.customer_name, p.product_name, b.*
  FROM
  job j,
  customer c,
  product p,
  batch b
  WHERE
  j.customer_id = c.customer_id
  AND p.job_id = j.job_id
  AND b.job_id = j.job_id
  AND j.job_id = $job_id;";

    return (getDBresult($query));
}

// main block
$cmd = $_REQUEST['cmd'];
$return_result = array();

// process loop
$cmdCounter = 0;
header('Content-type: application/json');
do {
    switch ($cmdCounter) {

        case 0:
        $pos = stripos($cmd, "products_for_business");
        if ($pos !== false) {
            $businessID = filter_input(INPUT_GET, 'businessID');
            $consumerID = filter_input(INPUT_GET, 'consumerID');
            $return_result = products_for_business($businessID, $consumerID);
            $final_result["message"] = "";
            $final_result["status"] = 1;
            $final_result["data"] = $return_result;
            echo json_encode($final_result);

            break 2;
        }
        case 1:
        $request = json_decode(file_get_contents('php://input'), TRUE);
        $cmd_post = $request["cmd"];
        $pos = stripos($cmd_post, "save_order");
        if ($pos !== false) {
            $order_id = save_order($request["business_id"], $request["consumer_id"], $request["total"], $request["data"]);
            $pointsToAdd = round($request["total"],0,PHP_ROUND_HALF_UP);
            save_points_for_customer_in_business($request["business_id"], $request["consumer_id"], $order_id, $pointsToAdd, 1);
            if ($request["points_redeemed"] && $request["points_redeemed"] != 0 ) {
                // making sure the points to redeem is always negative even if it is passed as a positive number
                $pointsToRedeem = -1 * abs($request["points_redeemed"]);
                save_points_for_customer_in_business($request["business_id"], $request["consumer_id"], $order_id, $pointsToRedeem, 1);
            }

            $final_result["message"] = "";
            $final_result["status"] = 1;
            $final_result["data"]["order_id"] = $order_id;
            $final_result["data"]["points"] = $pointsToAdd;
            echo json_encode($final_result);

            break 2;
        }
        case 2:
            $pos = stripos($cmd, "previous_order");
            if ($pos !== false) {
                $business_id = filter_input(INPUT_GET, 'business_id');
                $consumer_id = filter_input(INPUT_GET, 'consumer_id');
                $return_result = previous_order($business_id, $consumer_id);
                $final_result["message"] = "order is retrieved";
                $final_result["status"] = 1;
                $final_result["data"] = $return_result;
                echo json_encode($final_result);

                break 2;
            }
        case 3:
            $pos = stripos($cmd, "save_points");
            if ($pos !== false) {
                $businessID = filter_input(INPUT_GET, 'businessID');
                $consumerID = filter_input(INPUT_GET, 'consumerID');
                $orderID = filter_input(INPUT_GET, 'orderID');
                $points = filter_input(INPUT_GET, 'points');
                $pointReason = filter_input(INPUT_GET, 'pointReason');
                $return_result = save_points_for_customer_in_business($businessID, $consumerID, $orderID, $points, $pointReason);
                if (empty($return_result) ) {
                    $final_result['points_id'] = -1;
                    $final_result["status"] = -1;
                } else {
                    $final_result["status"] = 1;
                    $final_result["points_id"] = $return_result;
                }
                echo json_encode($final_result);

                break 2;
            }
            case 4:
                $pos = stripos($cmd, "get_all_points");
                if ($pos !== false) {
                    $businessID = filter_input(INPUT_GET, 'businessID');
                    $consumerID = filter_input(INPUT_GET, 'consumerID');

                    $return_result = get_all_points_for_customer($businessID, $consumerID);
                    if (empty($return_result) ) {
                        $final_result['data'] = array();
                        $final_result["status"] = -1;
                    } else {
                        $final_result["status"] = 1;
                        $final_result["data"] = $return_result;
                    }
                    echo json_encode($final_result);

                    break 2;
                }
        default:
            break 2;
    } // switch

    $cmdCounter++;
} while ($cmdCounter < 5) ;
?>
