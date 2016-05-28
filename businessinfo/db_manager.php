<?php

function removeslashes($string)
{
  $string=implode("",explode("\\",$string));
  return stripslashes(trim($string));
}

function stripslashes_deep($value)
{
  $value = is_array($value) ?
  array_map('stripslashes_deep', $value) :
  stripslashes($value);

  return $value;
}


/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
// connect to database
function connect() {
  static $dbh = resource;
  $dbh = 0;
  if (!$dbh) {
    $dbh = mysql_connect ("localhost", "artdoost_admin", "id0ntknow") or die ('I cannot connect to the database because: ' . mysql_error());
    mysql_select_db("artdoost_taptalk", $dbh);
  }
  return $dbh;
}

function isThisBusinessCustomer($businessName) {
  $dbh = connect();
  $query = mysql_query("SELECT * FROM business_customers WHERE name = '$businessName' and active = 1;") or die("Error: " . mysql_error());;

  if (mysql_num_rows ($query) > 0) {
    $returnVal =  TRUE;

//	$returncode = array('code'=>'0', 'message'=>'Success');
//	$rows['status'] = $returncode;
    $row['status'] =0;
    $row['data'] = mysql_fetch_assoc($query);
    echo json_encode($row);
  }
  else {
    $returnVal = FALSE;
  }

  /*
   *
  $rows = array();
  while($r = mysql_fetch_assoc($query)) {
    $rows[] = $r;
  }
  echo "{Businesses:".json_encode($rows).'}';
   *
   */
  //mysql_close();

  return $returnVal;
}



function products_for_business($businessID, $consumer_id) {
    if (empty($consumer_id)) {
        $consumer_id = -1;
    }
    $dbh = connect();
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

  $product_result = mysql_query($product_query) or die("Error: " . mysql_error());
        // $product_result = $conn->query($product_query);
  $resultArr = array();
  $category_name = "";
  while ($row = mysql_fetch_assoc($product_result)) {
    if (!empty($row["pictures"])) {
      $row["pictures"] = removeslashes($row["pictures"]);
    }
    if ($row["category_name"] <> $category_name) {
      $category_name = $row["category_name"];
    }
    $product_id = $row["product_id"];
    $option_query = "select name, price, description from product_option where product_id = $product_id and availability_status = 1;";
//            $option_result = $conn->query($option_query);
    $option_result = mysql_query($option_query) or die("Error: " . mysql_error());
    $option_resultArr["options"] = array();
    while ($option_row = mysql_fetch_assoc($option_result)) {
      $option_resultArr["options"][]  = $option_row;
    }
    $row["options"] = $option_resultArr["options"];
    $resultArr[$category_name][] = $row;
  }
  $final_result["message"] = "";
  $final_result["status"] = 1;
  $final_result["data"] = $resultArr;
  echo json_encode($final_result);
  return 1;
}


function getBusinessProducts($businessID, $consumer_id) {
  $dbh = connect();
  $query = mysql_query("SELECT products FROM businessProducts WHERE businessID = '$businessID';") or die("Error: " . mysql_error());;

  if (mysql_num_rows ($query) > 0) {
    $returnVal =  TRUE;

	// The return value issupposed to be plist (nsaaray and nsdictionary) - still we have to json encode it
    $row = mysql_fetch_assoc($query);
    $products = $row["products"];
//	echo json_encode($products);
    echo $products;
  }
  else {
    $returnVal = FALSE;
  }
  //mysql_close();

  return $returnVal;
}

function sendListOfAllBusinesses($consumer_id, $tableName = null) {
  $dbh = connect();
  if ($tableName == null)
    $tableName = 'business_customers';
  // $select_statement = "select distinct a.*, b.opening_time, b.closing_time
  // from business_customers a left join  opening_hours b
  // on (b.businessID = a.businessID) and b.weekday_id = WEEKDAY(now()) where a.active = 1;";

  if ($consumer_id) {
    $select_statement = "select distinct a.*, b.opening_time, b.closing_time, if (r.avg is null, 0, r.avg)
      as ti_rating from business_customers a
      left join  opening_hours b on (b.businessID = a.businessID and b.weekday_id = WEEKDAY(now()) )
      left join (select id, avg, consumer_id from rating where type = 1 and consumer_id = $consumer_id) r
      on r.id = a.businessID  where a.active = 1;";
  } else {
    // passing 0 as as ti_rating for now.  Deleting this field in the businessCustomer table
    $select_statement = "select distinct a.*, b.opening_time, b.closing_time , (0) as ti_rating from business_customers a
      left join  opening_hours b on (b.businessID = a.businessID and b.weekday_id = WEEKDAY(now()) )
      left join (select id, avg, consumer_id from rating where type = 1) r on r.id = a.businessID where a.active = 1;";
  }

  $query = mysql_query($select_statement) or die("Error: " . mysql_error());
  if (mysql_num_rows ($query) > 0) {
    $returnVal =  True;

    $rows['status'] = 0;

    $db_rows = array();
    while ($db_rows[] = mysql_fetch_assoc($query));
    if (end($db_rows) == 0)
      array_pop($db_rows);

    $rows['data'] = $db_rows;
    header('Content-type: application/json');
    echo json_encode($rows);
  }
  else {
    $returnVal = FALSE;
  }

  return $returnVal;
}

?>
