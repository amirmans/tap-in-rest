<?php

/*
class definition

 */
class Emailer
{
    var $recipients = array();
    var $EmailTemplate;
    var $emailContents;

    public function __construct($to = false)
    {
        if($to !== false)
        {
            if(is_array($to))
            {
                foreach($to as $_to){ $this->recipients[$_to] = $_to; }
            }else
            {
                $this->recipients[$to] = $to; //1 Recip
            }
        }
    }

    function SetTemplate(EmailTemplate $EmailTemplate)
    {
        $this->EmailTemplate = $EmailTemplate;
    }

    function send()
    {
        $emailContents = $this->EmailTemplate->compile();
        //your email send code.


        require 'vendor/autoload.php';

        // Load our `.env` variables
        //$dotenv = Dotenv\Dotenv::create(__DIR__);
        //$dotenv->load();
        // Declare a new SendGrid Mail object
        $email = new \SendGrid\Mail\Mail();

        // Set the email parameters
        $email->setFrom("amir@tap-in.co", "Marcus Battle");
        $email->setSubject("Sending with SendGrid is Fun");

        $email->addContent("text/plain", $emailContents);
        $email->addContent("text/html", $emailContents);

        foreach($this->recipients as $_to) {
            $email->addTo($_to);


            $sendgrid = new \SendGrid(getenv('SendGridApiKey'));

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
         if(!file_exists($path_to_file))
         {
             trigger_error('Template File not found!',E_USER_ERROR);
             return;
         }
         $this->path_to_file = $path_to_file;
    }

    public function __set($key,$val)
    {
        $this->variables[$key] = $val;
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
$emails = array(
    'alanashirk@hotmail.com',
    'you@yoursite.com'
);

$Emailer = new Emailer($emails);
 //More code here

$Template = new EmailTemplate('./templates/template1');
    $Template->Firstname = 'Robert';
    $Template->Lastname = 'Pitt';
    $Template->LoginUrl= 'http://stackoverflow.com/questions/3706855/send-email-with-a-template-using-php';
    //...

$Emailer->SetTemplate($Template); //Email runs the compile
$Emailer->send();
?>