<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<head> 
    <title>Map truck routes</title> 
    
    <link rel="stylesheet" href="./css/map_routes.css">
    <script type="text/javascript" src="../lib/external/scripts/jquery-1.11.1.min.js"></script>
     <script type="text/javascript">

        function findElement(arr, propName, propValue) {
             for (var i=0; i < arr.length; i++) {
                 if (arr[i][propName] == propValue)
                     return i;
             }
           return -1;
        }

        function addZipcodes() {
            var allRoutes = "";

            var loopLimit = document.getElementById("routesSelect").options.length;
            for (x=0; x< loopLimit;x++)
            {
                if (document.getElementById("routesSelect").options[x].selected)
                {   
                    if (allRoutes == "")
                        allRoutes += document.getElementById("routesSelect").options[x].value;
                    else
                        allRoutes += ", " + document.getElementById("routesSelect").options[x].value;
                }
            }
            var zipcodesSoFar = document.infoForMap.zipcodes_to_submit.value.trim();
            if (zipcodesSoFar.length > 1)
                document.infoForMap.zipcodes_to_submit.value = zipcodesSoFar + ", " + allRoutes;
            else 
                document.infoForMap.zipcodes_to_submit.value = allRoutes;
        }

        function showZipcodes() {
            var selectedIndex = document.getElementById("routesSelect").selectedIndex;
            if (selectedIndex <0) {
                alert("Have not chosen a route!");
            }
            else {
                document.getElementById("zipcodesInRoute").value =  document.getElementById("routesSelect").options[selectedIndex].value;
                document.getElementById("routeNameBox").value =  document.getElementById("routesSelect").options[selectedIndex].text;
            }
        }

        function modifyZipcodes() {
            var str = document.getElementById("routeNameBox").value;
            if (str.length <= 0) {
                alert ("Route name is empty!");
                return false;
            }
            if (document.getElementById("zipcodesInRoute").value.length <= 0) {
                alert ("No zip codes are given!");
                return false;
            }
            return TRUE;
        }

        function validateZipcodes_submit() {
            var status = false;
            var zip_codes_to_submit = document.getElementById("zipcodes_to_submit").value;
            if (zip_codes_to_submit == 0) {
                // special case means all zipcodes 
                document.getElementById("infoForMapForm").submit();
                return true;
            }

            //var input_zip_code_arr = zip_codes_to_submit.split(/[ ,]+/).filter(Boolean);
            var input_zip_code_arr = zip_codes_to_submit.split(/[ ,]+/);
            if (zip_codes_to_submit.length > 0) {
                
                $.ajax({
                       url: '../includes/model/pt_model.php',
                       data: 'cmd=list_operating_areas_zipcodes',
                       dataType: "json",
                       success: function (json_zipcodes) {
                            var zip_input_counter = 0;
                            for (zip_input_counter=0; zip_input_counter < input_zip_code_arr.length; zip_input_counter++)
                            {
                                var found = findElement(json_zipcodes, "zip_code",input_zip_code_arr[zip_input_counter]);
                                if (found >=0) {
                                    status = true;
                                    //continue;
                                }
                                else {
                                    //bad_zip_code = input_zip_code_arr[zip_input_counter];
                                    status = false;
                                    break;
                                }
                            }

                            if (status) {
                                document.getElementById("infoForMapForm").submit();
                                return status;
                            }
                            else {
                                alert('If you give me wrong zip_codes like ' +  '"' + input_zip_code_arr[zip_input_counter] + '"' + ', I will not map for you!');
                                return status;
                            }
                        }, // success
                        error: function (json_zipcodes) {
                            alert('What are you doing to me?!');
                            status = false;
                       }
                });


            } //if

            return false;
        }

     </script>
    
</head>

<body>
  <!--<div id="content" > -->
    <div id = "instruction">
        Please enter zip code(s) and the date to get the map of your stops
    </div>

    <?php
        date_default_timezone_set('America/Los_Angeles');
        require("../includes/config_db.inc.php");

        static $connection = null;
        if ($connection == null) {
            $connection = new mysqli("localhost", $db_user, $db_pass, $db_name);
            if (!$connection) {
               pt_error_log("Fetal - Database Error: ".$connection->connect_error. "\n");
               die('Not connected : ' . $connection->connect_errno . " :". $connection->connect_error);
            }
        }

        if ( isset($_POST['routeNameBox']) && isset($_POST['zipcodeInRoute']) )
        {
            $routeName = $_POST['routeNameBox'];
            $zipcodes = $_POST['zipcodeInRoute'];
            $updateOrInsertQuery = "INSERT INTO truck_routes (name,zipcodes) VALUES ( '$routeName', '$zipcodes') ON DUPLICATE KEY UPDATE zipcodes = '$zipcodes';";
            $insertOrUpdateResult = $connection->query($updateOrInsertQuery);
            if (!$insertOrUpdateResult) {
              pt_error_log("Fatal - invalid query in map-routes subsystem: ". $connection->connect_error. "\n");  
              die('Invalid query: ' . $connection->connect_error);
            }
            //$insertOrUpdateResult->close();
        }
  
        $query = "SELECT name, zipcodes FROM truck_routes;";
        $result = $connection->query($query);
        if (!$result) {
            pt_error_log("Fatal - invalid query in Map subsystem: ". $connection->connect_error. "\n");  
            die('Invalid query: ' . $connection->connect_error);
        }
    ?>

    <form id= "routesAndZipCodes" method="POST" action="<?php echo htmlentities($_SERVER['PHP_SELF']) ?>" onsubmit="return modifyZipcodes()">
            <!-- <label><br>You may also choose a route, click on the add button, and zip codes will be added to "zip code" box below.
            // <br>Use ">>" to view the zip codes in the route and "<<" to save your modifications</label> -->
            <label><br>To easily add multiple zip codes to the Zip Codes field, choose a route from the drop down list and click "Submit"</label>
            <br>
            <select multiple="multiple" id="routesSelect">
                <?php
                    $rows = array();
                    while ( $row = $result->fetch_assoc() ) {
                        $rows[] = $row;
                ?> 
                        <option value= "<?php echo $row['zipcodes']; ?>"> <?php echo $row['name'];?> </option>
                <?php
                    }
                ?>
            </select><br>
            <p id ="routesClicks"> </p>
            <input id = "showZipcodesInRoute"   type ="button" value ="View >>" onclick="showZipcodes()">
            <input id = "modifyZipcodesInRoute" type ="submit" value ="<< Save">
            </div>
            <div id ="changeRoute">
            Route name: <input type="text" id="routeNameBox"  name ="routeNameBox"><br>
            <textarea id = "zipcodesInRoute" rows="2" cols="58" name="zipcodeInRoute" wrap="physical"></textarea>
            </div>

            <input id = "routesSubmit" type="button" name="routesSubmit" value="Submit" onClick="addZipcodes()" >
      </form>      

    <form name ="infoForMap" id="infoForMapForm" method="POST" action= "<?php echo "./map_backend.php" ?>"> 
        <div id = "infoForMap">
          Zip Codes (separated by commas or "0" for all) <br/>
          <textarea id="zipcodes_to_submit" rows="5" cols="58" name="zipcode" wrap="physical"></textarea><br /><br/>

          Date: (Two digit month and four digit year. i.e. 09/24/2014) <br/>
          <input type="text" size="10" maxlength="10" name="date" value="<?php echo date('m/d/Y');?>" ><br/>

          <br/><input type="button" value="submit" name="mapSubmit" onclick="validateZipcodes_submit()"><br/>
      </div>
  </form>

  <div id="footer">
    <p>Copyright 2015 Purple Tie, Inc. All rights reserved. </p>
    <P>All trademarks and service marks are the properties of their respective owners.</p>
  </div>
  <?php  mysqli_free_result($result); mysqli_close($connection); ?>
  </body>
  </html>