<!DOCTYPE html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="With tapforall notify your customers" content="">
    <meta name="TapForAll" content="">
    <title>TapForAll notification server</title>
    <!-- Bootstrap core CSS -->
    <link href="bootstrap/css/bootstrap.css" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="style.css" rel="stylesheet">
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
    <style>
        .error
        {
          color: #FF0000;
        }
      .message
      {
          width: 30%;
          max-width: 30%;
      }
    </style>
</head>

<?php
  $nickNameErr = $messageErr = $senderErr = "";
  $valid = true;
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // $nickName = test_input($_POST["nickName"]);
    // $sender = test_input($_POST["sender"]);
    // $notificationMessage = test_input($_POST["notificationMessage"]);

    if (empty($_POST["nickName"])) {
      $nickNameErr = "nickName is required";
        $valid = false;
    } else {
      $nickName = test_input($_POST["nickName"]);
    }
    if (empty($_POST["sender"])) {
      $senderErr = "Sender is required";
        $valid = false;
    } else {
      $sender = test_input($_POST["sender"]);
    }
    if (empty($_POST["notificationMessage"])) {
      $messageErr = "Message is required";
        $valid = false;
    } else {
      $notificationMessage = test_input($_POST["notificationMessage"]);
    }

    if($valid){
      //ob_end_flush();
       //$action = "notification_backend.php";
        header("Location: notification_backend.php");
        exit;
        //exit();
      } else {
       // $action = "./notification_backend.php";
        //$action = $_SERVER["php_self"];
      }
  }

  function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
  }
?>
<script>
    function validateForm() {
        var x = document.forms["form"]["nickName"].value;
        if (x == null || x == "") {
//                alert("Name must be filled out");
            $nickNameErr = "nickName is required";
            return false;
        }
        return true;
    }
</script>

<body class="body">
    <img src="./bgimage.jpg" class="masthead">
    <div class="container">
        <h1>Notify customers!</h1>
        <h6>Type the nickname, the message, choose your icon and submit</h6>
        <p><br></p>
        <form name = "form" role="form" method="post" action="<?php echo htmlspecialchars("notification_backend.php");?>" target="_blank">
            <div>
                <label>
                    <h4>Nickname:</h4>
                </label>
                <input type="text" placeholder="Nickname" name="nickName">
                <span class="error"><?php echo $nickNameErr;?></span>
            </div>
            <div>
                <label>
                    <label>
                        <h4>Sender (business):</h4>
                    </label>
                </label>
                <input type="text" name="sender" id="formInput117" placeholder="Business name" required>
                <span class="error"><?php echo $senderErr;?></span>
            </div>
            <label>
                <h4>Icon:</h4>
            </label>
            <select name="imageName" id="formInput42">
                <option>starbucks_logo.png</option>
                <option>abc_jewelry_logo.png</option>
                <option>TopPotDoughnuts.png</option>
                <option>Pubic_TapForAll_Chat.jpg</option>
            </select>
            <h4>Message:</h4>
            <textarea name="notificationMessage" rows="3" required class="message"></textarea>
            <span class="error"><?php echo $messageErr;?></span>
            <input type="hidden" name="businessID" value="1">
            <p><br></p>
            <button type="submit" class="btn submitbutton">Submit</button>
        </form>
        <footer class="pg-empty-placeholder footer">
            Copyright 2016 Tap-In.co, Inc. All rights reserved.
            <br>
            All trademarks and service marks are the properties of their respective owners.
        </footer>
    </div>
    <!-- Bootstrap core JavaScript
================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
    <!-- IE10 viewport hack for Surface/desktop Windows 8 bug -->
    <script src="assets/js/ie10-viewport-bug-workaround.js"></script>
</body>
