<?php
date_default_timezone_set('America/Los_Angeles');
static $conn = nil;

//define('__ROOT__', dirname(dirname(dirname(__FILE__))));
//$includePath = __ROOT__ . "../includes/";
include_once(dirname(dirname(__FILE__)) . '/include/config_db.inc.php');
include_once(dirname(dirname(__FILE__)) . '/utils/ti_functions.php');
include_once(dirname(dirname(__FILE__)) . '/include/consts_server.inc');
include_once(dirname(dirname(__FILE__)) . '/include/error_logging/error.php');

function send_mail_for_new_order($businessID, $orderID) {

    $ch = curl_init();
    $merchantMailURL = MerchantsBaseURL . "mail_new_order.php";
    // curl_setopt($ch, CURLOPT_URL, "www.artdoost.com/adminpanel/mail_new_order.php");
    curl_setopt($ch, CURLOPT_URL, $merchantMailURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "order_id=" . $orderID . "&business_id=" . $businessID);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $server_output = curl_exec($ch);
    curl_close($ch);
    return $server_output;
}
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

function get_all_businesses_info($consumer_id) {
    $tableName = 'business_customers';
    if ($consumer_id) {
        $query = "select distinct a.*, if (r.avg is null, 0, r.avg)
        as ti_rating from business_customers a
        left join (select id, avg, consumer_id from rating where type = 1 and consumer_id = $consumer_id) r
        on r.id = a.businessID  where a.active = 1;";
    } else {
        // passing 0 as as ti_rating for now.  Deleting this field in the business_customers table
        $query = "select distinct a.*, (0) as ti_rating from business_customers a
        left join (select id, avg, consumer_id from rating where type = 1) r on r.id = a.businessID where a.active = 1;";
    }

    $conn = connectToDB();
    $business_result = $conn->query($query);

    $resultArr = array();
    //in mysql weekday number for monday is 0 and sunday is 6
//    $day_number1 = date('N', strtotime("Sunday"));
//    $day_number2 = date('N', strtotime("Monday"));
    $day_number = date('N', time());
    if ($day_number > 6) $day_number = 0;

    while ($row = mysqli_fetch_assoc($business_result)) {
        $business_id = $row["businessID"];

        $hours_query = "select businessID, opening_time, closing_time, break_start, break_end from  opening_hours where
          weekday_id = $day_number and businessID = $business_id order by priority DESC limit 1;";
        $hours_result = getDBresult($hours_query);
        $row["opening_time"] = $hours_result[0]["opening_time"];
        $row["closing_time"] = $hours_result[0]["closing_time"];
        $row["break_start"] = $hours_result[0]["break_start"];
        $row["break_end"] = $hours_result[0]["break_end"];

        $resultArr[] = $row;
    }

    return getDBresult($query);
}

function products_for_business($businessID, $sub_businesses, $consumer_id)
{
    if (empty($consumer_id)) {
        $consumer_id = -1;
    }
    if (empty($sub_businesses)) {
        $product_query = "SELECT distinct product_id, category_icon, product_icon, category_name, s.businessID,  COALESCE(s.product_keywords, '') as product_keywords, s.SKU, s.name, s.product_category_id
      ,s.short_description, s.long_description, s.availability_status, s.price, s.sales_price, s.sales_start_date, s.sales_end_date
      ,s.pictures, s.detail_information, s.runtime_fields, s.runtime_fields_detail
      ,s.has_option, s.bought_with_rewards, s.more_information
      ,q.avg as ti_rating, q.consumer_id
      from (SELECT distinct p.product_id, p.businessID, p.SKU, p.name, p.product_keywords, p.product_category_id,
      p.short_description, p.long_description, p.price, p.pictures, p.detail_information,
      p.runtime_fields, p.sales_price, p.sales_start_date, p.sales_end_date, p.availability_status,
      p.has_option, p.bought_with_rewards, p.more_information, p.runtime_fields_detail, c.category_name, biz.icon as category_icon, biz.icon as product_icon, c.listing_order
      FROM product p, product_category c, product_option o, business_customers biz
      WHERE p.businessID = $businessID AND c.business_id =  $businessID AND biz.businessID = p.businessID
      AND p.product_category_id = c.product_category_id) as s
      left join (select id, avg, consumer_id from rating where type = 2 and consumer_id = $consumer_id) as q on q.id = s.product_id
      ORDER BY s.listing_order, category_name ASC, s.name;";
    } else {
      //   $product_query = "SELECT distinct product_id, category_name, s.businessID,  COALESCE(s.product_keywords, '') as product_keywords, s.SKU, s.name, s.product_category_id
      // ,s.short_description, s.long_description, s.availability_status, s.price, s.sales_price, s.sales_start_date, s.sales_end_date
      // ,s.pictures, s.detail_information, s.runtime_fields, s.runtime_fields_detail
      // ,s.has_option, s.bought_with_rewards, s.more_information
      // ,q.avg as ti_rating, q.consumer_id
      // from (SELECT distinct p.product_id, p.businessID, p.SKU, p.name, p.product_keywords, p.product_category_id,
      // p.short_description, p.long_description, p.price, p.pictures, p.detail_information,
      // p.runtime_fields, p.sales_price, p.sales_start_date, p.sales_end_date, p.availability_status,
      // p.has_option, p.bought_with_rewards, p.more_information, p.runtime_fields_detail, c.category_name, c.listing_order
      // FROM product p, product_category c, product_option o
      // WHERE p.businessID in ($sub_businesses) AND c.business_id in ($sub_businesses)
      // AND p.product_category_id = c.product_category_id) as s
      // left join (select id, avg, consumer_id from rating where type = 2 and consumer_id = $consumer_id) as q on q.id = s.product_id
      // ORDER BY s.listing_order, category_name ASC, s.name;";


      $product_query = "SELECT distinct product_id, category_icon, product_icon, category_name, s.businessID,  COALESCE(s.product_keywords, '') as product_keywords, s.SKU, s.name, s.product_category_id
            ,s.short_description, s.long_description, s.availability_status, s.price, s.sales_price, s.sales_start_date, s.sales_end_date
            ,s.pictures, s.detail_information, s.runtime_fields, s.runtime_fields_detail
            ,s.has_option, s.bought_with_rewards, s.more_information
            ,q.avg as ti_rating, q.consumer_id
            from (SELECT distinct p.product_id, p.businessID, p.SKU, p.name, p.product_keywords, p.product_category_id,
            p.short_description, p.long_description, p.price, p.pictures, p.detail_information,
            p.runtime_fields, p.sales_price, p.sales_start_date, p.sales_end_date, p.availability_status,
            p.has_option, p.bought_with_rewards, p.more_information, p.runtime_fields_detail, c.category_name, biz.icon as category_icon, biz.icon as product_icon, c.listing_order
            FROM product p, product_category c, product_option o, business_customers biz
            WHERE p.businessID in ($sub_businesses) AND c.business_id = p.businessID AND biz.businessID = p.businessID
            AND p.product_category_id = c.product_category_id) as s
            left join (select id, avg, consumer_id from rating where type = 2 and consumer_id = $consumer_id) as q on q.id = s.product_id
            ORDER BY s.listing_order, category_name ASC, s.name;";
    }

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
        $optionWithCategories =  get_options_for_products($product_id, $businessID, $sub_businesses);
        //         $option_query = "select option_id, name, price, description from product_option where product_id = $product_id;";
        // //            $option_result = $conn->query($option_query);
        //         $option_result = $conn->query($option_query);
        //         $option_resultArr["options"] = array();
        //         while ($option_row = mysqli_fetch_assoc($option_result)) {
        //             $option_resultArr["options"][] = $option_row;
        //         }
        // $row["options"] = $option_resultArr["options"];
        $row["options"] = $optionWithCategories;
        $resultArr[$category_name][] = $row;

    }

    return $resultArr;
}

function save_cc_info($request) {
    $conn = getDBConnection();
    $consumer_id = $request["consumer_id"];
    $name_on_card = $request["name_on_card"];
    if (!$name_on_card) {
        $name_on_card = "";
    }
    $card_type = $request["card_type"];
    if (!$card_type) {
        $card_type = "";
    }
    $default = $request["default"];
    if ( (empty($default)) || ($default > 1) || ($default < 0)) {
        $default = 0;
    }

    if ($default == 1) {
        $updateQuery = "update consumer_cc_info set `default` = 0 where consumer_id = $consumer_id";
        insertOrUpdateQuery($updateQuery);
    }

    $prepared_stmt = "INSERT INTO consumer_cc_info
  (consumer_id, name_on_card, cc_no, expiration_date, cvv, zip_code, card_type, `default`)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE
  name_on_card = ?, cc_no = ?, expiration_date = ?, cvv = ?, zip_code = ?, card_type = ?, `default` = ?;";
    $prepared_query = $conn->prepare($prepared_stmt);
    $rc = $prepared_query->bind_param('sssssssssssssss', $consumer_id, $name_on_card, $request["cc_no"]
        ,$request["expiration_date"], $request["cvv"], $request["zip_code"], $card_type, $default, $name_on_card, $request["cc_no"]
        , $request["expiration_date"], $request["cvv"], $request["zip_code"], $card_type, $default);

    $rc = $prepared_query->execute();

    return 0;
}


function previous_order($business_id, $consumer_id) {
    // $query = "select i.* from order_item i inner join (select max(order_id) id from `order`
    //   where business_id = $business_id and consumer_id = $consumer_id) t on t.id = i.order_id;";
    $query = "select i.*, p.name as product_name, p.short_description as product_short_description, COALESCE(q.avg, 0) as ti_rating, note from order_item i
  inner join (select order_id, note from `order`
  where business_id = $business_id and consumer_id = $consumer_id order by order_id DESC limit 1) t on t.order_id = i.order_id
  left join product p on p.product_id = i.product_id
  left join (select avg, id  from rating where consumer_id = $consumer_id) as q on q.id = i.product_id;";

    //      $result = getDBresult($query);
    $conn = connectToDB();
    $conn->set_charset("utf8");
    $result = $conn->query($query);
    $resultWithOptions = array();
    while ($resultRow = mysqli_fetch_assoc($result)) {
        $resultRow["options"] = array();
        $options_csv = $resultRow["option_ids"];
        if (empty($options_csv)) {
            $resultWithOptions[] = $resultRow;
        } else {
            $product_id = $resultRow["product_id"];
            $optionArray = explode(',', $options_csv);
            $options = array();
            foreach ($optionArray as $option) {
                $optionQuery = "select option_id, name, price from product_option where option_id = $option  and product_id = $product_id";
                $db_options = $conn->query($optionQuery);
                while ($option_row = mysqli_fetch_assoc($db_options)) {
                    $options["options"][] = $option_row;
                }
            }
            $resultRow["options"] =  $options["options"];
            $resultWithOptions[] = $resultRow;
        }
    }
    return ($resultWithOptions);
}

function save_order($business_id, $customer_id, $total, $subtotal, $tip_amount, $points_dollar_amount
                    , $tax_amount, $cc_last_4_digits, $orderData, $note, $consumer_delivery_id, $delivery_charge_amount
                    , $promotion_code, $promotion_discount_amount) {
    if (empty($note)) {
        $note = "";
    }
    //number of items in an order is different from number of data, a entry could have quantity of grater than 1
    $no_items_in_order = 0;
    foreach ($orderData as $orderRow) {
        $no_items_in_order += $orderRow["quantity"];
    }
    if (empty($subtotal)) {
        $subtotal = 0.0;
    }
    if (empty($tip_amount)) {
        $tip_amount = 0.0;
    }
    if (empty($points_dollar_amount)) {
        $points_dollar_amount = 0.0;
    }
    if (empty($tax_amount)) {
        $tax_amount = 0.0;
    }
    if (empty($cc_last_4_digits)) {
        $cc_last_4_digits = "";
    }
    if (empty($consumer_delivery_id)) {
        $consumer_delivery_id = 0;
    }
    if (empty($promotion_code)) {
        $promotion_code = "";
    } else {
        $promotion_code = '"' . $promotion_code . '"';
    }
    if (empty($promotion_discount_amount)) {
        $promotion_discount_amount = 0.0;
    }
    if (empty($delivery_charge_amount)) {
        $delivery_charge_amount = 0.0;
    }

    $conn = connectToDB();
    $conn->set_charset("utf8");
    // status
    $insert_query = "insert into `order` (business_id, consumer_id, total, subtotal, tip_amount,
      points_dollar_amount, tax_amount, cc_last_4_digits, status, no_items, note, date, consumer_delivery_id,
      delivery_charge_amount, promotion_code, promotion_discount_amount)
    Values ($business_id, $customer_id, $total, $subtotal, $tip_amount, $points_dollar_amount, $tax_amount,
      \"$cc_last_4_digits\", 1, $no_items_in_order, \"$note\", now(), $consumer_delivery_id, $delivery_charge_amount,
      $promotion_code, $promotion_discount_amount);";

    $conn->query($insert_query);

    $order_id = mysqli_insert_id($conn);

    $prepared_stmt = "INSERT INTO order_item (order_id, product_id, option_ids, price, quantity) VALUES (?,?,?,?,?)";
    foreach ($orderData as $orderRow) {
//           $option_ids_fld = json_decode ($orderRow["options"]);
        $option_ids_fld = implode (', ',$orderRow["options"]);
        $prepared_query = $conn->prepare($prepared_stmt);
        $rc = $prepared_query->bind_param('sssdi', $order_id, $orderRow["product_id"],$option_ids_fld,
            $orderRow["price"], $orderRow["quantity"]);
        $rc = $prepared_query->execute();
    }
    if ($order_id > 0){
        send_mail_for_new_order($business_id, $order_id);

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
    $query = "select consumer_id, business_id, points_reason_id, points, order_id, time_earned,time_redeemed,
  case `points`.`time_earned`
  when '0000-00-00 00:00:00'    then `points`.`time_redeemed`
  when 'NULL' then `points`.`time_redeemed`
  ELSE  `points`.`time_earned`
  end as activity_time
  from points where
  (ISNULL(time_expired) = 1 or time_expired > now())
  and consumer_id = '$consumerID'";

    if ($businessID  && $businessID <> "0") {
        $query .= " and business_id = $businessID";
    }

    $query .= " order by (activity_time) DESC;";

    $result = getDBresult($query);

    $total_redeemed_points = 0;
    $total_earned_points = 0;
    //$total_available_points = 0;
    // $points_earned = array();
    // $points_redeemed = array();
    $points = array();
    foreach ($result as $row) {
        if ($row["points"]  > 0) {
            // $points_earned[] = $row;
            $total_earned_points += $row["points"];
        } else {
            // $points_redeemed[] = $row;
            $total_redeemed_points -= $row["points"];
        }
        $points[] = $row;
    }
    $total_available = $total_earned_points - $total_redeemed_points;
    $return_result["total_earned_points"] = $total_earned_points;
    $return_result["total_redeemed_points"] = $total_redeemed_points;
    $return_result["total_available_points"] = $total_available;
    // $return_result["points_earned"] =  $points_earned;
    // $return_result["points_redeemed"] =  $points_redeemed;
    $return_result["points"] =  $points;

    $next_level_query=
        "SELECT coalesce(points,0) as points, coalesce(equivalent,0) as dollar_value, points_level_name
, message FROM points_map main RIGHT JOIN
(SELECT MIN(points) as next_level FROM points_map WHERE  $total_available < points ) as sub on sub.next_level = main.points;";
    $current_level_query =
        "SELECT coalesce(points,0) as points, coalesce(equivalent,0) as dollar_value, points_level_name
, message FROM points_map main RIGHT JOIN
(SELECT MAX(points) as next_level FROM points_map WHERE  $total_available >= points ) as sub on sub.next_level = main.points;";

    if ($businessID  && $businessID <> "0") {
        $next_level_query=
            "SELECT coalesce(points,0) as points, coalesce(equivalent,0) as dollar_value, points_level_name
, message FROM points_map main RIGHT JOIN
(SELECT MIN(points) as next_level FROM points_map WHERE  $total_available < points and business_id = $businessID ) as sub on sub.next_level = main.points;";
        $current_level_query =
            "SELECT coalesce(points,0) as points, coalesce(equivalent,0) as dollar_value, points_level_name
, message FROM points_map main RIGHT JOIN
(SELECT MAX(points) as next_level FROM points_map WHERE  $total_available >= points and business_id = $businessID) as sub on sub.next_level = main.points;";

    }

    $points_next_level = getDBresult($next_level_query);
    $points_current_level = getDBresult($current_level_query);

    $return_result["current_points_level"] = $points_current_level[0];
    $return_result["next_points_level"] = $points_next_level[0];

    return $return_result;
}

function ti_setRating($type, $id, $rating, $consumer_id) {

    $query = "INSERT INTO rating (id, consumer_id, type, avg) VALUES($id, $consumer_id, $type, $rating) ON DUPLICATE KEY UPDATE
  id = $id, consumer_id = $consumer_id, type = $type, avg = $rating;";

    return (getDBresult($query));
}

/**
 * This is for testing purposes and isn't used in the actual program
 */
function get_all_orders() {
    $query = "select * from `order` order by order_id desc;";
    return (getDBresult($query));
}

function get_options_for_products($product_id, $business_id, $sub_businesses) {
    if (empty($sub_businesses) ) {
        $option_category_query = "select * from product_option_category where business_id = $business_id order by listing_order;";
    }
    else {
        $option_category_query = "select * from product_option_category where business_id in ($sub_businesses) order by listing_order, business_id;";
    }
    $optionCats = getDBresult($option_category_query);

    $resultArr = array();
    $index = 0;
    foreach ($optionCats as $optionCat) {
        $optionCat_id = $optionCat["product_option_category_id"];
        if ($product_id) {
            $query = "select p.option_id, o.name, o.price, o.description, o.availability_status
        from product_option p,  `option` o
        where o.product_option_category_id = $optionCat_id and p.product_id = $product_id
	      and o.option_id = p.option_id order by o.name;";
        } else {
            // it seems this is not needed anymore
            $query = "select p.option_id, o.name, o.price, o.description, o.availability_status
        from product_option p,  `option` o
        where o.product_option_category_id = $optionCat_id
	      and o.option_id = p.option_id order by o.name;";
            $product_id = 0;
        }
        $options = getDBresult($query);
        $resultArr[$index]["option_category_name"] =  $optionCat["name"];
        $resultArr[$index]["only_choose_one"] =  $optionCat["only_choose_one"];
        $resultArr[$index]["optionData"] =  $options;
        $index++;
    }

    // $result["status"] = 1;
    // $result["message"] = "";
    // $result["product_id"] = $product_id;
    // $result["data"] = $resultArr;

    return ($resultArr);
}

function get_notifications_for_consumer_in_business($consumer_id, $business_id) {
    $query = "select * from notification where consumer_id = $consumer_id and is_deleted = 0 and business_id = $business_id
    order by time_sent DESC;";

    return (getDBresult($query));
}

function get_average_wait_time_for_business($business_id) {
    $query = "select businessID, process_time from business_customers where businessID = $business_id;";
    return (getDBresult($query));
}


function save_notifications_for_consumer_in_business($request) {
    $conn = getDBConnection();

    $consumer_id = $request["consumer_id"];
    $business_id = $request["business_id"];
    // $deleteQuery = "DELETE from notification where consumer_id =  $consumer_id and business_id = $business_id;";
    // getDBresult($deleteQuery);

    // the client app, always has the notification ID, since it always gets its the list of notifications from the server, which includes notification_id
    $prepared_statement = "Insert into notification (notification_id, business_id, consumer_id, image, message, time_sent, time_read, notification_type_id, is_deleted)
      values(?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE time_read=?, is_deleted =?";
    foreach ($request["data"] as $notification) {
        if (empty($notification["is_deleted"]) ) {
            $is_deleted = 0;
        } else {
            $is_deleted = $notification["is_deleted"];
        }
        $prepared_query = $conn->prepare($prepared_statement);
        $rc = $prepared_query->bind_param('ssssssssisi',
            $notification["notification_id"], $business_id, $consumer_id, $notification["image"], $notification["message"], $notification["time_sent"],
            $notification["time_read"], $notification["notification_type_id"], $is_deleted, $notification["time_read"], $is_deleted);
        $rc = $prepared_query->execute();
    }

    return ($rc);
}

function get_all_notifications_for_consumer($consumer_id) {
    $query = "select * from notification where consumer_id = $consumer_id and is_deleted = 0
    order by time_sent DESC;";

    return (getDBresult($query));
}

function save_all_notifications_for_consumer($request) {
    // $conn = getDBConnection();

    // $consumer_id = $request["consumer_id"];
    // $deleteQuery = "DELETE from notification where consumer_id =  $consumer_id";
    // getDBresult($deleteQuery);

    // $prepared_statement = "Insert into notification (business_id, consumer_id, image, message, time_sent, time_read, notification_type_id)
    //   values(?,?,?,?,?,?,?);";
    // foreach ($request["data"] as $notification) {
    //   if (empty($notification["notification_type_id"]))
    //   {
    //     $notification_type = "";
    //   } else {
    //     $notification_type = $notification["notification_type_id"];
    //   }
    //   $prepared_query = $conn->prepare($prepared_statement);
    //   $rc = $prepared_query->bind_param('sssssss',
    //     $notification["business_id"], $consumer_id, $notification["image"], $notification["message"], $notification["time_sent"],
    //     $notification["time_read"],$notification_type);
    //   $rc = $prepared_query->execute();
    // }
    $conn = getDBConnection();

    $consumer_id = $request["consumer_id"];
    // $deleteQuery = "DELETE from notification where consumer_id =  $consumer_id and business_id = $business_id;";
    // getDBresult($deleteQuery);

    // the client app, always has the notification ID, since it always gets its the list of notifications from the server, which includes notification_id
    $prepared_statement = "Insert into notification (notification_id, business_id, consumer_id, image, message, time_sent, time_read, notification_type_id, is_deleted)
      values(?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE time_read=?, is_deleted =?";
    foreach ($request["data"] as $notification) {
        if (empty($notification["is_deleted"]) ) {
            $is_deleted = 0;
        } else {
            $is_deleted = $notification["is_deleted"];
        }
        $prepared_query = $conn->prepare($prepared_statement);
        $rc = $prepared_query->bind_param('ssssssssisi',
            $notification["notification_id"],  $notification["business_id"], $consumer_id, $notification["image"], $notification["message"],
            $notification["time_sent"], $notification["time_read"], $notification["notification_type_id"], $is_deleted,
            $notification["time_read"], $is_deleted);
        $rc = $prepared_query->execute();
    }

    return ($rc);
}

function get_consumer_all_cc_info($consumer_id) {
    $query = "select * from consumer_cc_info where consumer_id = $consumer_id;";

    return (getDBresult($query));
}

function get_consumer_default_cc($consumer_id) {
    $query = "select * from consumer_cc_info where consumer_id = $consumer_id and `default` = 1 order by `timestamp` desc limit 1;";

    return (getDBresult($query));
}

function remove_cc($consumer_id, $ccDataArr) {
    foreach ($ccDataArr as $ccData) {
        $cc_no = $ccData["cc_no"];
        $exp_date = $ccData["expiration_date"];
        $cvv = $ccData["cvv"];
        $zip = $ccData["zip_code"];

        $query = "delete from consumer_cc_info where consumer_id = $consumer_id and cc_no= $cc_no and expiration_date= \"$exp_date\"
        and cvv=$cvv and zip_code=$zip;";

        getDBresult($query);
    }

    return 1;
}


function get_business_delivery_info($business_id) {

    $query = "select delivery_section_id, section_name, title_information, section_map,  d.instruction, d.message
            ,d.note, COALESCE(NULLIF(section_name_abrv, ''), section_name) as section_location_name
            ,d.delivery_charge, d.delivery_time_interval_in_minutes, d.delivery_start_time, d.delivery_end_time
            from delivery_section d, business_delivery bd
            where bd.business_id = $business_id and d.business_delivery_id = bd.business_delivery_id order by delivery_section_id ASC;";

    $deliverySections = getDBresult($query);

    $index = 0;
    foreach ($deliverySections as $section) {
        $delivery_section_id = $section["delivery_section_id"];

        $query = "select delivery_section_location_id,  COALESCE( NULLIF(location_abrv,''), location_name) as location_name,
            note  from delivery_section_locations where
            delivery_section_id = $delivery_section_id order by delivery_section_location_id ASC;";

        $locations = getDBresult($query);

        $deliverySections[$index]["locations_in_section"]  =  $locations;
        $index++;
    }

    return ($deliverySections);
}


function save_consumer_delivery($request) {
    $consumer_id = $request["consumer_id"];
    $delivery_address = $request["delivery_address"];
    $delivery_address_name = $request["delivery_address_name"];
    $delivery_instruction = $request["delivery_instruction"];
    $default = $request["delivery_default"];
    $delivery_time = $request["delivery_time"];

    if (empty($default)) {
        $default = 0;
    }
    if (empty($delivery_instruction)) {
        $delivery_instruction = "";
    }
    if (empty($delivery_time)) {
        $delivery_time = date("Y-m-d H:i:s");
    }

    if (empty ($delivery_address)) {
        $delivery_address = $delivery_address_name;
    }
    if (empty ($delivery_address_name)) {
        $delivery_address_name = $delivery_address;
    }

    $conn = connectToDB();
    $conn->set_charset("utf8");

    $prepared_statement = "Insert into consumer_delivery
        (consumer_id, delivery_time, delivery_address, delivery_address_name, `default`, delivery_instruction)
      values(?,?,?,?,?,?)";
    $prepared_query = $conn->prepare($prepared_statement);
    $rc = $prepared_query->bind_param('ssssss',
        $consumer_id, $delivery_time, $delivery_address,$delivery_address_name, $default, $delivery_instruction);
    $rc = $prepared_query->execute();

//      $insert_query = "INSERT INTO consumer_delivery
//        (consumer_id, delivery_time, delivery_address, delivery_address_name, `default`, delivery_instruction)
//        VALUES($consumer_id, \"$delivery_time\", $delivery_address,$delivery_address_name, $default, $delivery_instruction);";
//
//      $conn->query($insert_query);

    $consumer_delivery_id = mysqli_insert_id($conn);

    return ($consumer_delivery_id);
}


function get_consumer_latest_delivery_info($consumer_id) {
    $query = "select delivery_address, delivery_address_name, delivery_instruction, delivery_time from consumer_delivery
      where consumer_id = $consumer_id order by TIMESTAMP desc limit 1;";

    return (getDBresult($query));
}


function get_business_overall_delivery_info($business_id) {
    $query = "select * from business_delivery
      where business_id = $business_id limit 1;";

    return (getDBresult($query));
}


function did_consumer_used_promotion($consumer_id, $business_id, $promotion_id, $promotion_code) {
    if (empty($promotion_id)) {
        $field_name = "promotion_code";
        $field_value = "'" . $promotion_code . "'";
    } else {
        $field_name = "promotion_id";
        $field_value = "'". $promotion_id . "'";
    }

    /*$query = "select p.promotion_code, p.business_promotion_id, o.promotion_discount_amount from  `order` o, business_promotion p
      where o.business_id = $business_id and o.$field_name = $field_value and o.consumer_id = $consumer_id 
      AND p.$field_name = o.$field_name; "; */

    $query = "select o.promotion_discount_amount, o.promotion_code, p.business_promotion_id from  `order` o
      left join business_promotion p on p.promotion_code = o.promotion_code and p.business_id = o.business_id    
      where o.business_id = $business_id and o.$field_name = $field_value 
      AND o.promotion_discount_amount > 0 and o.consumer_id = $consumer_id";

    return (getDBresult($query));
}

// main block
$cmd = $_REQUEST['cmd'];
$return_result = array();
header('Content-type: application/json');

// process loop
$cmdCounter = 0;
do {
    switch ($cmdCounter) {

        case 0:
            $pos = stripos($cmd, "products_for_business");
            if ($pos !== false) {
                $businessID = filter_input(INPUT_GET, 'businessID');
                $sub_businesses = filter_input(INPUT_GET, 'sub_businesses');
                $consumerID = filter_input(INPUT_GET, 'consumerID');
                $return_result = products_for_business($businessID, $sub_businesses, $consumerID);
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
                $order_id = save_order($request["business_id"], $request["consumer_id"], $request["total"]
                    ,$request["subtotal"], $request["tip_amount"], $request["points_dollar_amount"], $request["tax_amount"]
                    ,$request["cc_last_4_digits"], $request["data"], $request["note"], $request["consumer_delivery_id"]
                    ,$request["delivery_charge_amount"],$request["promotion_code"],$request["promotion_discount_amount"]);
                // for backward compatibility
                if (empty($request["subtotal"])) {
                    $amountForPoints = $request["total"];
                } else {
                    $amountForPoints = $request["subtotal"];
                }
//        $pointsToAdd = round($amountForPoints,0,PHP_ROUND_HALF_UP);
                $pointsToAdd = floor($amountForPoints);
                save_points_for_customer_in_business($request["business_id"], $request["consumer_id"], $order_id, $pointsToAdd, 1);
                if ($request["points_redeemed"] && $request["points_redeemed"] != 0 ) {
                    // making sure the points to redeem is always negative even if it is passed as a positive number
                    $pointsToRedeem = -1 * abs($request["points_redeemed"]);
                    save_points_for_customer_in_business($request["business_id"], $request["consumer_id"], $order_id, $pointsToRedeem, 1);
                }

                $final_result["message"] = "";
                if ($order_id > 0) {
                    $final_result["status"] = 1;
                } else {
                    $final_result["status"] = -1;
                }
                $final_result["data"]["order_id"] = $order_id;
                $final_result["data"]["points"] = $pointsToAdd;
                echo json_encode($final_result);

                break 2;
            }
        case 2:
            $request = json_decode(file_get_contents('php://input'), TRUE);
            $cmd_post = $request["cmd"];
            $pos = stripos($cmd_post, "save_cc_info");
            if ($pos !== false) {
                $status = save_cc_info($request);

                $final_result["message"] = "Success";
                $final_result["status"] = $status;
                echo json_encode($final_result);

                break 2;
            }
        case 3:
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
        case 4:
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
        case 5:
            $pos = stripos($cmd, "setRatings");
            if ($pos !== false) {
                $array = json_decode($_POST['songs']);
                $id = filter_input(INPUT_GET, 'id');
                $consumer_id = filter_input(INPUT_GET, 'consumer_id');
                $type = filter_input(INPUT_GET, 'type');
                $rating = filter_input(INPUT_GET, 'rating');
                $return_result = ti_setRating($type, $id, $rating, $consumer_id);
                echo json_encode($return_result);

                break 2;
            }
        case 6:
            $pos = stripos($cmd, "get_all_points");
            if ($pos !== false) {
                $businessID = filter_input(INPUT_GET, 'businessID');
                if (empty($businessID))
                    $businessID = filter_input(INPUT_GET, 'business_id');

                $consumerID = filter_input(INPUT_GET, 'consumerID');
                if (empty($consumerID))
                    $consumerID = filter_input(INPUT_GET, 'consumer_id');

                $return_result = get_all_points_for_customer($businessID, $consumerID);
                $final_result = [];
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
        case 7:
            $pos = stripos($cmd, "get_all_orders");
            if ($pos !== false) {
                $return_result = get_all_orders();
                echo json_encode($return_result);

                break 2;
            }
        case 8:
            $pos = stripos($cmd, "get_options_for_products");
            if ($pos !== false) {
                $final_result = [];
                $product_id = filter_input(INPUT_GET, 'product_id');
                $business_id = filter_input(INPUT_GET, 'business_id');
                $sub_businesses = filter_input(INPUT_GET, 'sub_businesses');
                $return_result = get_options_for_products($product_id, $business_id, $sub_businesses );

                $final_result["status"] = 1;
                $final_result["message"] = "";
                $final_result["product_id"] = $product_id;
                $final_result["data"] = $return_result;


                echo json_encode($final_result);

                break 2;
            }
        case 9:
            $request = json_decode(file_get_contents('php://input'), TRUE);
            $cmd_post = $request["cmd"];
            $pos = stripos($cmd_post, "save_notifications_for_consumer_in_business");
            if ($pos !== false) {
                $final_result = [];
                $return_code = save_notifications_for_consumer_in_business($request);
                $final_result["status"] = $return_code;
                $final_result["message"] = "";
                echo json_encode($final_result);

                break 2;
            }
        case 10:
            $pos = stripos($cmd, "get_notifications_for_consumer_in_business");
            if ($pos !== false) {
                $final_result = [];
                $consumer_id = filter_input(INPUT_GET, 'consumer_id');
                $business_id = filter_input(INPUT_GET, 'business_id');
                $return_result = get_notifications_for_consumer_in_business($consumer_id, $business_id);
                $final_result["data"] = $return_result;
                $final_result["status"] = 1;
                $final_result["message"] = "";
                echo json_encode($final_result);

                break 2;
            }
        case 11:
            $pos = stripos($cmd, "get_all_notifications_for_consumer");
            if ($pos !== false) {
                $final_result = [];
                $consumer_id = filter_input(INPUT_GET, 'consumer_id');
                $return_result = get_all_notifications_for_consumer($consumer_id);
                $final_result["data"] = $return_result;
                $final_result["status"] = 1;
                $final_result["message"] = "";

                echo json_encode($final_result);

                break 2;
            }
        case 12:
            $request = json_decode(file_get_contents('php://input'), TRUE);
            $cmd_post = $request["cmd"];
            $pos = stripos($cmd_post, "save_all_notifications_for_consumer");
            if ($pos !== false) {
                $final_result = [];
                $return_code = save_all_notifications_for_consumer($request);
                $final_result["status"] = $return_code;
                $final_result["message"] = "";
                echo json_encode($final_result);

                break 2;
            }
        case 13:
            $pos = stripos($cmd, "get_average_wait_time_for_business");
            if ($pos !== false) {
                $business_id = filter_input(INPUT_GET, 'business_id');
                $final_result = [];
                $return_code = get_average_wait_time_for_business($business_id);
                $final_result["status"] = 0;
                $final_result["data"] = $return_code[0];
                echo json_encode($final_result);

                break 2;
            }
        case 14:
            $pos = stripos($cmd, "get_all_businesses_info");
            if ($pos !== false) {
                $consumer_id = filter_input(INPUT_GET, 'consumer_id');
                $final_result = [];
                $result = get_all_businesses_info($consumer_id);
                $final_result["status"] = 0;
                $final_result["data"] = $result;
                if (!$result) {
                    $final_result["status"] = -10;
                }
                echo json_encode($final_result);

                break 2;
            }
        case 15:
            $request = json_decode(file_get_contents('php://input'), TRUE);
            $cmd_post = $request["cmd"];
            $pos = stripos($cmd_post, "remove_cc");
            if ($pos !== false) {
                $final_result = [];
                $return_code = remove_cc($request["consumer_id"], $request["data"]);
                $final_result["status"] = $return_code;
                $final_result["message"] = "";
                echo json_encode($final_result);

                break 2;
            }
        case 16:
            $pos = stripos($cmd, "get_consumer_default_cc");
            if ($pos !== false) {
                $consumerID = filter_input(INPUT_GET, 'consumerID');
                if (empty($consumerID))
                    $consumerID = filter_input(INPUT_GET, 'consumer_id');
                $final_result = [];
                $result = get_consumer_default_cc($consumerID);
                $final_result["status"] = 0;
                $final_result["data"] = $result;
                if (!$result) {
                    $final_result["status"] = -10;
                }
                echo json_encode($final_result);

                break 2;
            }
        case 17:
            $pos = stripos($cmd, "get_consumer_all_cc_info");
            if ($pos !== false) {
                $consumer_id = filter_input(INPUT_GET, 'consumer_id');
                $final_result = [];
                $result = get_consumer_all_cc_info($consumer_id);
                $final_result["status"] = 0;
                $final_result["data"] = $result;
                if (!$result) {
                    $final_result["status"] = -10;
                }
                echo json_encode($final_result);

                break 2;
            }
        case 18:
            $pos = stripos($cmd, "get_business_delivery_info");
            if ($pos !== false) {
                $business_id = filter_input(INPUT_GET, 'business_id');
                $final_result = [];
                $result = get_business_delivery_info($business_id);
                $final_result["status"] = 0;
                $final_result["data"] = $result;
                if (!$result) {
                    $final_result["status"] = -10;
                }
                echo json_encode($final_result);

                break 2;
            }
        case 19:
            $request = json_decode(file_get_contents('php://input'), TRUE);
            $cmd_post = $request["cmd"];
            $pos = stripos($cmd_post, "save_consumer_delivery");
            if ($pos !== false) {
                $final_result = [];
                $return_code = save_consumer_delivery($request);
                if ($return_code >0) {
                    $status = 1;
                }
                else {
                    $status = -1;
                }
                $final_result["status"] = $status;
                $final_result["consumer_delivery_id"] = $return_code;
                $final_result["message"] = "";
                echo json_encode($final_result);

                break 2;
            }
        case 20:
            $pos = stripos($cmd, "get_consumer_latest_delivery_info");
            if ($pos !== false) {
                $consumer_id = filter_input(INPUT_GET, 'consumer_id');
                $final_result = [];
                $result = get_consumer_latest_delivery_info($consumer_id);
                $final_result["status"] = 0;
                $final_result["data"] = $result;
                if (!$result) {
                    $final_result["status"] = -10;
                }
                echo json_encode($final_result);

                break 2;
            }
        case 21:
            /**
             * if discount_amount > 0 is found and pass, it indicates consumer has used this promotion
             */
            $pos = stripos($cmd, "did_consumer_used_promotion");
            if ($pos !== false) {
                $consumer_id = filter_input(INPUT_GET, 'consumer_id');
                $business_id = filter_input(INPUT_GET, 'business_id');
                $promotion_id = filter_input(INPUT_GET, 'promotion_id');
                $promotion_code = filter_input(INPUT_GET, 'promotion_code');
                $final_result = [];
                $result = did_consumer_used_promotion($consumer_id, $business_id, $promotion_id, $promotion_code);
                $final_result["status"] = 0;
                $final_result["data"] = $result;

                echo json_encode($final_result);

                break 2;
            }
        case 22:
            $pos = stripos($cmd, "get_business_overall_delivery_info");
            if ($pos !== false) {
                $business_id = filter_input(INPUT_GET, 'business_id');
                $final_result = [];
                $result = get_business_overall_delivery_info($business_id);
                $final_result["status"] = 0;
                $final_result["data"] = $result;
                if (!$result) {
                    $final_result["status"] = -10;
                }
                echo json_encode($final_result);

                break 2;
            }
        default:
            break 2;
    } // switch

    $cmdCounter++;
} while ($cmdCounter < 23) ;
?>