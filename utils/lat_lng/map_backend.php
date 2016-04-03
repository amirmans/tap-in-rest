<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>Purple Tie - map of delivery and pickup locations</title>
    <style type="text/css">
    </style>
    <link rel="stylesheet" href="./css/map_style.css">
    <script src="https://maps.google.com/maps/api/js?sensor=false"
            type="text/javascript"></script>
    <script type="text/javascript">
    //<![CDATA[
    var panorama; // street view
    var customIcons = {
      pickup: {
        icon: 'https://labs.google.com/ridefinder/images/mm_20_blue.png',
        shadow: 'https://labs.google.com/ridefinder/images/mm_20_shadow.png'
      },
      delivery: {
        icon: 'https://labs.google.com/ridefinder/images/mm_20_red.png',
        shadow: 'https://labs.google.com/ridefinder/images/mm_20_shadow.png'
      }
    };

    var iconColors = {
         pickup: {
            color: '|EE0000|000000'
      },
      delivery: {
            color: '|00688B|FFFFFF'
      }
    };

    function closeStreetView()
    {
        document.getElementById('street-view').style.display='none';
        document.getElementById('close_street_view').style.display='none';
        panorama.setVisible(false);
    }

    function showStreetView(lat, lng) {
        document.getElementById('street-view').style.display='block';
        document.getElementById('close_street_view').style.display='block';
        // var map = new google.maps.Map(document.getElementById("street-view"), {
        //      mapTypeId: google.maps.MapTypeId.roadmap
        // });

        var customerAddress = new google.maps.LatLng(lat, lng);
        // panorama = map.getStreetView();
        // panorama.setPosition(customerAddress);
        // panorama.setPov(
        //    { heading: 34, pitch:10 }
        // );
        //panorama.setVisible(true);

        var panoramaOptions = {
            position: customerAddress,
            visible: true
        };
        var panorama = new  google.maps.StreetViewPanorama(document.getElementById("street-view"), panoramaOptions);
        //map.setStreetView(panorama);

        google.maps.event.addListener(panorama, 'links_changed', function() {
            var streetViewHeading =  panorama.getLinks()[0].heading;

            panorama.setPov({
                heading: streetViewHeading,
                pitch: 0,
                zoom: 0
            });
        });
        // to get just the image, we can use the following  - this is one of the addresses we don't have an image for
        //https://maps.googleapis.com/maps/api/streetview?size=400x400&location=37.435764,-122.256477&key=AIzaSyDKxzgOqPEsn1sMI63LN9GRVZi9Ph__-24
    }

    function load() {
      var summaryString = ""; 
      var map = new google.maps.Map(document.getElementById("map"), {
    
          mapTypeId: google.maps.MapTypeId.roadmap
      });
      var bounds = new google.maps.LatLngBounds();
      var infoWindow = new google.maps.InfoWindow();

      // Change this depending on the name of your PHP file
      var passed_zipcode = "<?php echo urlencode($_POST["zipcode"]); ?>";
      var passed_date = "<?php echo urlencode($_POST["date"]); ?>";
      var urlstr = "map_persistence.php";
      var urlwithargs= urlstr.concat("?zipcode=", passed_zipcode, "&date=", passed_date);
      downloadUrl(urlwithargs, function(data) {
        var badMarkers = 0;
        var xml = data.responseXML;
        var markers = xml.documentElement.getElementsByTagName("marker");
        for (var i = 0; i < markers.length; i++) {
          //if (markers[i].getAttribute("lat") === undefined || markers[i].getAttribute("lng") === undefined)
          //  continue;

          var name = markers[i].getAttribute("name");
          var address = markers[i].getAttribute("address");
          var type = markers[i].getAttribute("type");
          if (!markers[i].getAttribute("lat") || !markers[i].getAttribute("lng") || markers[i].getAttribute("lat") == 0 || markers[i].getAttribute("lng") == 0) {
                      //summaryString += "Marker " + (i+1) + "" "" + type + " for " + address + "<br>";
                      badMarkers++;
                      summaryString += "<b>" + (i + 1 ) + " </b> - " + type + " for <i>" + address + "</ i><br><br>";
                      continue;
          }
          var lat = parseFloat(markers[i].getAttribute("lat"));
          var lng = parseFloat(markers[i].getAttribute("lng"));
          var point = new google.maps.LatLng(lat, lng);
          // parseFloat(markers[i].getAttribute("lat")),
          // parseFloat(markers[i].getAttribute("lng")));
          var html = "<b>" + name + "</ b> <br/>" + address + "<br/><button onclick= showStreetView(" + lat + "," + lng + ")>Street view</ button>";
          var icon = customIcons[type] || {};
          var iconColor = iconColors[type] || {};
          var marker = new google.maps.Marker({
            map: map,
            position: point,
 //           icon: icon.icon,
            icon: 'https://chart.googleapis.com/chart?chst=d_map_pin_letter&chld='+(i+1)+iconColor.color
            //shadow: icon.shadow
 //           labelContent: "h",
 //           labelInBackground: false
          });
          bounds.extend(point);
          bindInfoWindow(marker, map, infoWindow, html);
        }
        map.fitBounds(bounds);
        var summaryHeadingString = "<h3> Marker and addresses summary:</h3><br>";
        var totals = " <h4> There were a total of " + markers.length + " addresses. ";
        if (badMarkers > 0) {
            totals += badMarkers + " of them didn't make it to be a marker.  The list of incorrect addresses is: </ h4><BR>";
        }
        else {
            totals +=" All became markers.  No incorrect addresses.";
        }
        document.getElementById('map-summary').innerHTML =  summaryHeadingString + totals + summaryString;
      });

    }

    function bindInfoWindow(marker, map, infoWindow, html) {
      google.maps.event.addListener(marker, 'click', function() {
        infoWindow.setContent(html);
        infoWindow.open(map, marker);
      });
    }

    function downloadUrl(url, callback) {
      var request = window.ActiveXObject ?
          new ActiveXObject('Microsoft.XMLHTTP') :
          new XMLHttpRequest();

      request.onreadystatechange = function() {
        if (request.readyState == 4) {
          request.onreadystatechange = doNothing;
          callback(request, request.status);
        }
      };

      request.open('POST', url, true);
      request.send(null);
    }

    function doNothing() {}

    //]]>
  </script>

  </head>

  <body onload="load()">
    <div id="wrapper">
      <div id="header">
        ----- Map for <b><?php echo $_POST["zipcode"]; ?></b> on date of  <b><?php echo $_POST["date"]; ?> </b>
        &nbsp; - &nbsp;
        Deliveries are marked in <font color=#00688B> Blue </font>and pickups in <font color=#EE0000>Red</font>
      </div>
      <div id="content">
        <div id="map"> </div>
        <div id="map-summary"></div>
        <div id="street-view" style="display:none;position:absolute; left:800px;top: 200px; width: 580px; height: 500px;"> </div>
        <div id="close_street_view" style ="display:none; position:absolute; left: 1040px; top:715px; width: 100px; height = 20px;">
            <button style="background-color: white; width:100px; color:#8A2BE2" onclick= closeStreetView()>Close street view </button> 
        </div>
      </div>
      <div id="footer"> ----- End of Map for  <?php echo $_POST["zipcode"]; ?> on date of  <?php echo $_POST["date"]; ?> </div>
      <div id="bottom"></div>
    </div>
  </body>

</html> 
