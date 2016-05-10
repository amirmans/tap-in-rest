<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Site extends CI_Controller {

    public function __construct() {
        parent::__construct();
        ////////////DEFAULT LOAD BELOW FUNCTIONLITY WHEN CALL V1 CONTROLLER
        /////// LOAD LIBRARY VALIDATION CLASS
        $this->load->library('validation');
        ///// LOAD MODEL CLASS
        $this->load->model('m_site');
        ////// RESONSE HEADER CONTEN TYPRE SET FROM DEFAULT(TEXT/HTML) TO APPLICATION/JSON
    }

    function index() {
        ///// DEFULT SITE CONTROLLER MEHOD CALL 
        $data['business_list'] = $this->m_site->get_business_list();
        $this->load->view('v_home', $data);
    }

    function orderlist() {
        $param = $_REQUEST;
        $this->validation->is_parameter_blank('businessID', $param['businessID']);
        $data['orderlist'] = $this->m_site->get_business_order_list($param);
        $data['order_detail'] = $this->m_site->get_order_detail($data['orderlist'][0]['order_id']);
        $this->load->view('v_orderlist', $data);
    }

    function order_view() {
        $param = $_REQUEST;
        $order_id = $param['order_id'];
        $data['order_detail'] = $this->m_site->get_order_detail($order_id);
        $data['orderlist'] = $this->m_site->get_ordelist_order($order_id);
        $return['order_view'] = $this->load->view('v_order_view', $data, TRUE);
        echo json_encode($return);
    }

    function payment() {
        $param = $_REQUEST;
        $this->validation->is_parameter_blank('order_id', $param['order_id']);
        $order_id = decrypt_string($param['order_id']);
        $order_payment_detail = $this->m_site->get_order_payment_detail($order_id);
        if ($order_payment_detail['total'] > 0) {
            $amount = $order_payment_detail['total'] * 100;
            $secret_key = $this->get_stripe_secret_key($business_id);
            if ($secret_key == "") {
                $response = error_res("Something went wrong");
            } else {
                require_once('lib/stripe-php-master/init.php');
                \Stripe\Stripe::setApiKey($secret_key);
                $myCard = array('number' => '4242424242424242', 'exp_month' => 8, 'exp_year' => 2018);
                $charge = \Stripe\Charge::create(array('card' => $myCard, 'amount' => $amount, 'currency' => 'usd'));
                $response = success_res("your payment has been successfully processed");
                $response['amount']=$amount/100;
            }
        } else {
              $response = error_res("Something went wrong");
        }
        echo json_encode($response);
    }

    function get_stripe_secret_key($business_id) {

        if ($business_id == "1") {
            return "sk_test_HLQ9NIFofiiRukm1AZnjCfOe";
        } elseif ($business_id == "2") {
            return "sk_test_3bhryjsin3JBzGHZ22LocClC";
        } elseif ($business_id == "3") {
            return "sk_test_2nrGH34wH0ni9tXH7K73MsO9 ";
        }
        return "sk_test_HLQ9NIFofiiRukm1AZnjCfOe";
    }

    function test_paymen() {
        require_once('lib/stripe-php-master/init.php');
        \Stripe\Stripe::setApiKey('sk_test_JQCcDe4RIqIq1IcmVfvLPyay ');
        $myCard = array('number' => '4242424242424242', 'exp_month' => 8, 'exp_year' => 2018);

        $charge = \Stripe\Charge::create(array('card' => $myCard, 'amount' => 10000, 'currency' => 'usd'));
        print_r($charge);
        die;
        echo '<pre>';
        print_r($charge);
    }

    function get_table_list() {
        echo '<pre>';
        $tables = $this->m_site->get_table_list($order_id);
        print_r($tables);
    }

    function create_cusomer() {
        require_once('lib/stripe-php-master/init.php');
        \Stripe\Stripe::setApiKey('sk_test_JQCcDe4RIqIq1IcmVfvLPyay ');
        $myCard = array('number' => '4242424242424242', 'exp_month' => 8, 'exp_year' => 2018);

        $charge = \Stripe\Charge::create(array('card' => $myCard, 'amount' => 10000, 'currency' => 'usd'));
        print_r($charge);
        print_r($charge);
    }

}

//////////// HERE DO NOT END PHP TAG  
