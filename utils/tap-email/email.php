<?php

$base_url = getenv('APP_HOSTNAME');
include_once(dirname(dirname(__FILE__)) . '../../include/config_db.inc.php');


/*--------- database functions -----------------*/
function connectToDB()
{
//    global $db_host, $db_user, $db_pass, $db_name;

    // echo APPLICATION_ENV." ";
    global $config;
    $model_config = $config[getenv('EnvMode')];
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



/*
class definition

 */
class Emailer
{
    var $resreserved_words = array();
    var $EmailTemplate;
    var $emailContents;

    public function __construct($to = false)
    {
        if($to !== false)
        {
            if(is_array($to)) {
                $this->resreserved_words = array_merge($this->resreserved_words, $to);
            }
        }
    }

    function SetTemplate(EmailTemplate $EmailTemplate)
    {
        $this->EmailTemplate = $EmailTemplate;
    }

    function send()
    {

        //your email send code.


        require 'vendor/autoload.php';

        // Load our `.env` variables
        //$dotenv = Dotenv\Dotenv::create(__DIR__);
        //$dotenv->load();
        // Declare a new SendGrid Mail object
        $email = new \SendGrid\Mail\Mail();
        $sendgrid = new \SendGrid(getenv('SendGridApiKey'));

        // Set the email parameters
        $email->setFrom("amir@tap-in.co", "MMM-Tap4Markets");
        $email->setSubject("MMM & Tap4Markets");

        foreach($this->resreserved_words as $info) {
            $email->addTo($info['biz_email']);
            $this->EmailTemplate->vendor_link = getenv('APP_HOSTNAME').$info['username'];
//            $this->EmailTemplate->surveyLink= 'https://www.surveymonkey.com/r/F5LCTMC';
//            $this->EmailTemplate->corp_name = 'WalnutCreek Market';
//            $this->EmailTemplate->pickup_date = 'Sunday at 10:00 AM';
            $this->EmailTemplate->firstname = $info['firstname'];



            $this->emailContents = $this->EmailTemplate->compile();

            $email->addContent("text/plain", $this->emailContents);
            $email->addContent("text/html", $this->emailContents);

            // Send the email
            try {
                $response = $sendgrid->send($email);
                print $response->statusCode() . "\n";
                print_r($response->headers());
                print $response->body() . "\n";
                echo "email sent!\n";
            } catch (Exception $e) {
                echo 'Caught exception: ' . $e->getMessage() . "\n";
            }
        }

    }
}

/*
class definition
 */
class EmailTemplate
{
    var $variables = array();
    var $path_to_file= array();
    function __construct($path_to_file)
    {
        if (!empty($path_to_file)) {
            if (!file_exists($path_to_file)) {
                trigger_error('Template File not found!', E_USER_ERROR);
            }
            $this->path_to_file = $path_to_file;
        }
    }

    public function __set($key,$val)
    {
        $this->variables[$key] = $val;
    }

    public function setTemplate($path_to_file) {
        if (!file_exists($path_to_file)) {
                trigger_error('Template File not found!', E_USER_ERROR);
        }
        $this->path_to_file = $path_to_file;
    }

    public function compile()
    {
        ob_start();

        extract($this->variables);
        include $this->path_to_file;

        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }
}


/*
email main body
 */
$template="./templates/template2";

$market_name = "CCCFM Walnut Creek Market";
$market_parent_name = "cccfm";
$surveyLink= 'https://www.surveymonkey.com/r/F5LCTMC';
$pickup_date_time = 'Sundays at 10:00 AM';
$cutoff_date_time = 'Thursdays at 12:00 AM';

$reservedWordsQuery  = "Select cp.parent_corp, bc.contact_firstname as firstname, bc.email as biz_email, bc.username 
    from business_customers  bc 
	left join corp cp on FIND_IN_SET(bc.businessID, cp.merchant_ids)  
	where corp_name = '$market_name' order by bc.name desc;";
$reswords = getDBresult($reservedWordsQuery);

//$reservedWords = array(
//    array("biz_email" => 'amirmans@yahoo.com', "Firstname" => "Amir", "Lastname" => "Amirmansoury"),
//    array("biz_email" => 'alanashirk@hotmail.com', "Firstname" => "Banana", "Lastname" => "Shirk")
//);

$emailer = new Emailer($reswords);
 //More code here

$template = new EmailTemplate($template);
//    $template->setTemplate('./templates/template2.html');
    $template->surveyLink= $surveyLink;
    $template->corp_name = $market_name;
    $template->market_name = $market_name;
    $template->pickup_date = $pickup_date_time;
    $template->cutoff_date = $cutoff_date_time;
    //...

$emailer->SetTemplate($template); //Email runs the compile
$emailer->send();
?>