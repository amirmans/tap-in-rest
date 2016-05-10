<?php

class M_site extends CI_Model {

    public function __construct() {
        parent::__construct();
        date_default_timezone_set("GMT");
    }

    function get_table_list() {
        $query = "select * from information_schema.tables";
        $result = $this->db->query($query);
        $row = $result->result_array();
        return $row;
    }

    function get_business_list() {
        $this->db->select('businessID,name');
        $this->db->from('businessCustomers');
        $this->db->order_by("businessID", "asc");
        $result = $this->db->get();
        $row = $result->result_array();
        return $row;
    }

    function get_business_order_list($param) {
        $this->db->select('o.order_id,o.payment_id,o.total,o.date,cp.nickname');
        $this->db->from('order as o');
        $this->db->join('consumer_profile as cp', 'o.consumer_id = cp.uid', 'left');
        $this->db->where('o.business_id', decrypt_string($param['businessID']));
        $this->db->order_by("o.order_id", "desc");
        //$this->db->limit(10);
        $result = $this->db->get();
        $row = $result->result_array();
        return $row;
    }

    function get_ordelist_order($order_id) {
        $this->db->select('o.order_id,o.payment_id,o.total,o.date,cp.nickname');
        $this->db->from('order as o');
        $this->db->join('consumer_profile as cp', 'o.consumer_id = cp.uid', 'left');
        $this->db->where('o.order_id', $order_id);
        $this->db->limit(1);
        $result = $this->db->get();
        $row = $result->result_array();
        return $row;
    }

    function get_order_detail($order_id) {

        $this->db->select('o.order_item_id,o.price,o.quantity,p.name');
        $this->db->from('order_item as o');
        $this->db->join('product as p', 'o.product_id = p.product_id', 'left');
        $this->db->where('o.order_id', $order_id);
        $result = $this->db->get();
        $row = $result->result_array();
        return $row;
    }

    function get_order_payment_detail($order_id) {
        $this->db->select('o.total');
        $this->db->from('order as o');
        //$this->db->join('product as p', 'o.product_id = p.product_id', 'left');
        $this->db->where('o.order_id', $order_id);
        $this->db->limit(1);
        $result = $this->db->get();
        $row = $result->result_array();
        $return['total'] = $row[0]['total'];
        return $return;
    }

}
