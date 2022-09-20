<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
require_once APPPATH . "/third_party/PHPExcel.php";
class Admin extends CI_Controller 
{
    public function __Construct()
    {
        parent::__Construct();
        if($this->session->userdata('user_id')=='')
        {
            redirect('login');  
        }
        $this->load->model("common");
        $this->load->library("statusintigration");
        $this->load->library("etrucknowquote");
        $this->load->library("shippeostatusintigration");
        $this->load->library("macdamstatusintigration");
        $this->load->library("knlogin");
        $this->load->library('Exchangerate');
		$this->load->library('email');
        $this->load->library('session');
    }  

    public function index(){
        $this->activeorders();
    }

    public function activeorders(){
        $data['page_title']=$this->lang->line('menu_active');
        $data['sub_title']=$this->lang->line('active_bookings');
        $data['ord_type'] = "";
        $data['orders'] = array();
        $inp = 0;
        $btype = $this->session->userdata('business_type');
        $branch_user = $data["branch_user"] = isset($_POST["branch_user"]) ? $_POST["branch_user"] : "-1";
        if(!in_array($btype, ['Country Admin'])){
            $cid = "(".$this->session->userdata("user_id").")";
        }else{
            $admids = $this->session->userdata('country_user_ids');
            if($branch_user != "-1"){
                $cid   = "($branch_user)";
            }else if(count($admids)>0){
                $users  = implode(",", $admids);
                $cid   = "($users)";
            }else{
                $cid   = "(-1)";
            }
        }
        $company_code = $this->session->userdata("company_code");
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $input = $this->input->post();
        $whr = "";
        if(!empty($input)){
            if(!empty($input['bookingid'])){
                $bookids = implode("','", $input['bookingid']);
                $inp = 1;
                $whr .= " AND o.order_id IN('".$bookids."')";
            }
            if(isset($input['pickup']) && !empty($input['pickup'])){
                $inp = 1;
                $whr .= " AND (o.pickup_country LIKE '%".$input['pickup']."%' OR o.pickup_city LIKE '%".$input['pickup']."%') ";
            }
            if(isset($input['drop']) && !empty($input['drop'])){
                $inp = 1;
                $whr .= " AND (o.delivery_country LIKE '%".$input['drop']."%' OR o.delivery_city LIKE '%".$input['drop']."%') ";
            }
            if(isset($input['fromdate_search'])){
                if($input['fromdate_search'] != ""){
                    $getactual = getdatetimebytimezone(DFLT_TZ,$input['fromdate_search'],$curtz);
                    $input['fromdate_search'] = $getactual['date'];
                    $inp = 1;
                    $whr .= " AND DATE(o.pickup_datetime)>='".$input['fromdate_search']."' ";
                }
            }
            
            if(isset($input['todate_search'])){
                if($input['todate_search'] != ""){
                    $getactual = getdatetimebytimezone(DFLT_TZ,$input['todate_search'],$curtz);
                    $input['todate_search'] = $getactual['date'];
                    $inp = 1;
                    $whr .= " AND DATE(o.delivery_datetime)<='".$input['todate_search']."' ";
                }
            }
            $getords = array();
            if(!empty($input['reference'])){
                $inp = 1;
                $reference = implode("','", $input['reference']);
                $getords = $this->getActiveOrdersbyRef($reference);
            }
            if(!empty($input['order_type'])){
                $inp = 1;
                $ordtye = implode("','", $input['order_type']);
                $getords1 = $this->getActiveOrdersbyRefOT("OT",$ordtye);
                if(!empty($getords1)){
                    $getords = array_merge($getords,$getords1);
                }else{
                    $getords2 = $this->getActiveOrdersdetailsType($ordtye);
                    if(!empty($getords2)){
                        $getords = array_merge($getords,$getords2);
                    }
                }
            }
            if(!empty($getords)){
                $gords = implode("','",$getords);
                $whr .= " AND o.id IN('$gords') ";
            }
        }
        if($inp == 1 && $whr == ""){
            $whr .= " AND o.id IN(0) ";
        }
        $mainqry = "SELECT IFNULL(COUNT(o.id),0) as cnt FROM tb_orders o WHERE o.user_id IN $cid AND o.trip_id!=0 AND o.status!=0 AND o.trip_sts=0 $whr";
        $custord1 = $this->db->query($mainqry);
        $pgcnt = $custord1->row()->cnt;
        $config = array();
        $config["base_url"] = base_url() . "admin/activeorders";
        $config["total_rows"] = $pgcnt;
        $config["per_page"] = 5;
        $config["uri_segment"] = 3;
        $config['display_pages'] = TRUE;
        $this->pagination->initialize($config);
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $mainqry = "SELECT o.id,o.order_id,o.pickup_datetime,o.delivery_datetime,o.pickup_country,o.delivery_country,o.pickup_city,o.delivery_city,o.plat,o.plng FROM tb_orders o WHERE o.user_id in $cid AND o.trip_id!=0 AND o.status!=0 AND o.trip_sts=0 $whr ORDER BY o.pickup_datetime DESC";
        $qry = $mainqry . ' LIMIT ' . $page . ',' . $config["per_page"];
        $custord = $this->db->query($qry);
        if($custord->num_rows()>0){
            $data['orders'] = $custord->result_array(); 
        }
        $this->newtemplate->dashboard('admin/activeorders',$data);
    }

    function getActiveOrdersbyRef($refid){
        $res = array();
        $cid = $this->session->userdata("user_id");
        if(!empty($refid)){
            $qry = $this->db->query("select r.order_id FROM tb_order_references r,tb_orders o WHERE r.order_id=o.id AND o.user_id=$cid AND o.trip_id != 0 AND o.trip_sts=0 AND r.ref_value IN('".$refid."') AND r.status=1");
            if($qry->num_rows()>0){
                foreach ($qry->result() as $res1) {
                    $res[] = $res1->order_id;
                }
            }
        }
        return $res;
    }

    function getActiveOrdersbyRefOT($refnum,$refval){
        $res = array();
        $cid = $this->session->userdata("user_id");
        if(!empty($refval)){
            $qry = $this->db->query("select r.order_id FROM tb_order_references r,tb_orders o WHERE r.order_id=o.id AND o.user_id=$cid AND o.trip_id != 0 AND o.trip_sts=0 AND r.reference_id='".$refnum."' AND r.ref_value IN('".$refval."') AND r.status=1");
            if($qry->num_rows()>0){
                foreach ($qry->result() as $res1) {
                    $res[] = $res1->order_id;
                }
            }
        }
        return $res;
    }
    public function getActiveOrdersdetailsType($otype){
        $res = array();
        $cid = $this->session->userdata("user_id");
        $company_code = $this->session->userdata("company_code");
        if(!empty($otype)){
            $ordertype_id = 0;
            $getordertype_id = $this->db->select('id')->get_where("tb_order_types",array('ordtype_code'=>$otype,'company_code'=>$company_code,'status'=>'1'));
            if($getordertype_id->num_rows()>0){
                $ordertype_id = $getordertype_id->row()->id;
            }else{
                $getordertype = $this->db->select('id')->get_where("tb_order_types",array('ordtype_code'=>$otype,'company_code'=>'SGKN','status'=>'1'));
                if($getordertype->num_rows()>0){
                    $ordertype_id = $getordertype->row()->id;
                }
            }
            if($ordertype_id != 0){
                $order_qry = $this->db->query("select o.id FROM tb_orders o, tb_order_details d where d.order_type='".$ordertype_id."' AND o.user_id= '".$cid."' AND o.id=d.order_row_id");
                if($order_qry->num_rows()>0){
                    foreach ($order_qry->result() as $res1) {
                        $res[] = $res1->id;
                    }
                }
            }
            
        }
        
        return $res;
    }
    function getPendingOrdersbyRef($refid){
        $res = array();
        $cid = $this->session->userdata("user_id");
        $whr = '';
        if($this->session->userdata('company_code') == 'NZKN'){
            $whr .= ' AND o.order_status != "READY"';
        }
        if(!empty($refid)){
            $qry = $this->db->query("select r.order_id FROM tb_order_references r,tb_orders o WHERE r.order_id=o.id AND o.user_id=$cid AND o.trip_id=0 AND r.ref_value IN('".$refid."') AND r.status=1 $whr");
            if($qry->num_rows()>0){
                foreach($qry->result() as $res1) {
                    $res[] = $res1->order_id;
                }
            }
        }
        return $res;
    }
    function getPendingOrdersbyRefOT($refnum,$refval){
        $res = array();
        $cid = $this->session->userdata("user_id");
        $whr = '';
        if($this->session->userdata('company_code') == 'NZKN'){
            $whr .= ' AND o.order_status != "READY" ';
        }
        if(!empty($refval)){
            $qry = $this->db->query("select r.order_id FROM tb_order_references r,tb_orders o WHERE r.order_id=o.id AND o.user_id=$cid AND o.trip_id=0 AND o.trip_sts=0 AND r.reference_id='".$refnum."' AND r.ref_value IN('".$refval."') AND r.status=1 $whr");
            if($qry->num_rows()>0){
                foreach($qry->result() as $res1) {
                    $res[] = $res1->order_id;
                }
            }
        }
        return $res;
    }

    function getReadyOrdersbyRef($refid){
        $res = array();
        $cid = $this->session->userdata("user_id");
        if(!empty($refid)){
            $qry = $this->db->query("select r.order_id FROM tb_order_references r,tb_orders o WHERE r.order_id=o.id AND o.user_id=$cid AND o.trip_id=0 AND r.ref_value IN('".$refid."') AND r.status=1 AND o.order_status ='READY' ");
            if($qry->num_rows()>0){
                foreach($qry->result() as $res1) {
                   $res[] = $res1->order_id;
               }
           }
       }
       return $res;
   }

   function getReadyOrdersbyRefOT($refnum,$refval){
    $res = array();
    $cid = $this->session->userdata("user_id");
    if(!empty($refval)){
        $qry = $this->db->query("select r.order_id FROM tb_order_references r,tb_orders o WHERE r.order_id=o.id AND o.user_id=$cid AND o.trip_id=0 AND o.trip_sts=0 AND r.reference_id='".$refnum."' AND r.ref_value IN('".$refval."') AND r.status=1 AND (o.order_status ='READY' OR o.order_status IS NULL)");
        if($qry->num_rows()>0){
            foreach($qry->result() as $res1) {
                $res[] = $res1->order_id;
            }
        }
    }
    return $res;
}

function getCompletedOrdersbyRef($refid){
    $res = array();
    $cid = $this->session->userdata("user_id");
    if(!empty($refid)){
        $qry = $this->db->query("select r.order_id FROM tb_order_references r,tb_orders o WHERE r.order_id=o.id AND o.user_id=$cid AND o.trip_sts=1 AND r.ref_value IN('".$refid."') AND r.status=1");
        if($qry->num_rows()>0){
            foreach ($qry->result() as $res1) {
                $res[] = $res1->order_id;
            }
        }
    }
    return $res;
}

function getCompletedOrdersbyRefOT($refnum,$refval){
    $res = array();
    $cid = $this->session->userdata("user_id");
    if(!empty($refval)){
        $qry = $this->db->query("select r.order_id FROM tb_order_references r,tb_orders o WHERE r.order_id=o.id AND o.user_id=$cid AND o.trip_sts=1 AND r.reference_id='".$refnum."' AND r.ref_value IN('".$refval."') AND r.status=1");
        if($qry->num_rows()>0){
            foreach ($qry->result() as $res1) {
                $res[] = $res1->order_id;
            }
        }
    }
    return $res;
}

public function pendingorders(){
    $data['page_title']=$this->lang->line('menu_pending');
    $data['sub_title']=$this->lang->line('menu_pending')." ".$this->lang->line('bookings');
    $data['ord_type'] = "";
    $data['orders'] = array();
    $inp = 0;
    $btype = $this->session->userdata('business_type');
    $branch_user = $data["branch_user"] = isset($_POST["branch_user"]) ? $_POST["branch_user"] : "-1";
    if(!in_array($btype, ['Country Admin'])){
        $cid = "(".$this->session->userdata("user_id").")";
    }else{
        $admids = $this->session->userdata('country_user_ids');
        if($branch_user != "-1"){
            $cid   = "($branch_user)";
        }else if(count($admids)>0){
            $users  = implode(",", $admids);
            $cid   = "($users)";
        }else{
            $cid   = "(-1)";
        }
    }
    $company_code = $this->session->userdata("company_code");
    $curtz = $this->session->userdata("usr_tzone")['timezone'];
    $input = $this->input->post();
    $whr = "";
    if(!empty($input)){
        if(!empty($input['bookingid'])){
            $bookids = implode("','", $input['bookingid']);
            $inp = 1;
            $whr .= " AND o.order_id IN('".$bookids."')";
        }
        if(isset($input['pickup']) && !empty($input['pickup'])){
            $inp = 1;
            $whr .= " AND (o.pickup_country LIKE '%".$input['pickup']."%' OR o.pickup_city LIKE '%".$input['pickup']."%') ";
        }
        if(isset($input['drop']) && !empty($input['drop'])){ 
            $inp = 1;
            $whr .= " AND (o.delivery_country LIKE '%".$input['drop']."%' OR o.delivery_city LIKE '%".$input['drop']."%') ";
        }
        if(isset($input['fromdate_search'])){
            if($input['fromdate_search'] != ""){
                $inp = 1;
                $getactual = getdatetimebytimezone(DFLT_TZ,$input['fromdate_search'],$curtz);
                $input['fromdate_search'] = $getactual['date'];
                $whr .= " AND DATE(o.pickup_datetime)>='".$input['fromdate_search']."' ";
            }
        }
        if(isset($input['todate_search'])){
            if($input['todate_search'] != ""){
                $inp = 1;
                $getactual = getdatetimebytimezone(DFLT_TZ,$input['todate_search'],$curtz);
                $input['todate_search'] = $getactual['date'];
                $whr .= " AND DATE(o.delivery_datetime)<='".$input['todate_search']."' ";
            }
        }
        
        $getords = array();
        if(!empty($input['reference'])){
            $inp = 1;
            $reference = implode("','", $input['reference']);
            $getords = $this->getPendingOrdersbyRef($reference);
        }
        if(!empty($input['order_type'])){
            $inp = 1;
            $ordtye = implode("','", $input['order_type']);
            $getords1 = $this->getPendingOrdersbyRefOT("OT",$ordtye);
            if(!empty($getords1)){
                $getords = array_merge($getords,$getords1);
            }else{
                $getords2 = $this->getActiveOrdersdetailsType($ordtye);
                if(!empty($getords2)){
                    $getords = array_merge($getords,$getords2);
                }
            }
        }
        if(!empty($getords)){
            $gords = implode("','",$getords);
            $whr .= " AND o.id IN('$gords') ";
        }
    }
    if($inp == 1 && $whr == ""){
        $whr .= " AND o.id IN(0) ";
    }

    if($company_code == 'NZKN'){
        $whr .= ' AND o.order_status != "READY" ';
    }
    $mainqry = "SELECT count(o.id) as cnt FROM tb_orders o WHERE o.user_id IN $cid AND o.trip_id=0 AND o.status!=0 AND o.trip_sts=0 $whr";
    $custord1 = $this->db->query($mainqry);
    $pgcnt = $custord1->row()->cnt;
    $config = array();
    $config["base_url"] = base_url() . "admin/pendingorders";
    $config["total_rows"] = $pgcnt;
    $config["per_page"] = 5;
    $config["uri_segment"] = 3;
    $config['display_pages'] = TRUE;
    $this->pagination->initialize($config);
    $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

    $mainqry = "SELECT o.id,o.order_id,o.order_status,convertToClientTZ(o.pickup_datetime,'".$curtz."') as pickup_datetime,o.pickup_city,o.pickup_country,convertToClientTZ(o.delivery_datetime,'".$curtz."') as delivery_datetime,o.delivery_city,o.delivery_country FROM tb_orders o WHERE o.user_id IN $cid AND o.status!=0 AND o.trip_id=0 AND o.trip_sts=0 $whr ORDER BY o.pickup_datetime DESC";
    $qry = $mainqry . ' LIMIT ' . $page . ',' . $config["per_page"];
    $custord = $this->db->query($qry);

    if($custord->num_rows()>0){
        $data['orders'] = $custord->result_array(); 
    }
    $this->newtemplate->dashboard('admin/pendingorders',$data);
}

public function readyorders(){
    $data['page_title']=$this->lang->line('menu_ready');
    $data['sub_title']=$this->lang->line('ready_orders');
    $data['ord_type'] = "";
    $data['orders'] = array();
    $inp = 0;
    $btype = $this->session->userdata('business_type');
    $branch_user = $data["branch_user"] = isset($_POST["branch_user"]) ? $_POST["branch_user"] : "-1";
    if(!in_array($btype, ['Country Admin'])){
        $cid = "(".$this->session->userdata("user_id").")";
    }else{
        $admids = $this->session->userdata('country_user_ids');
        if($branch_user != "-1"){
            $cid   = "($branch_user)";
        }else if(count($admids)>0){
            $users  = implode(",", $admids);
            $cid   = "($users)";
        }else{
            $cid   = "(-1)";
        }
    }
    $company_code = $this->session->userdata("company_code");
    $curtz = $this->session->userdata("usr_tzone")['timezone'];
    $input = $this->input->post();
    $whr = "";
    if(!empty($input)){
        if(!empty($input['bookingid'])){
            $bookids = implode("','", $input['bookingid']);
            $inp = 1;
            $whr .= " AND o.order_id IN('".$bookids."')";
        }
        if(isset($input['pickup']) && !empty($input['pickup'])){
            $inp = 1;
            $whr .= " AND (o.pickup_country LIKE '%".$input['pickup']."%' OR o.pickup_city LIKE '%".$input['pickup']."%') ";
        }
        if(isset($input['drop']) && !empty($input['drop'])){ 
            $inp = 1;
            $whr .= " AND (o.delivery_country LIKE '%".$input['drop']."%' OR o.delivery_city LIKE '%".$input['drop']."%') ";
        }
        if(isset($input['fromdate_search'])){
            if($input['fromdate_search'] != ""){
                $inp = 1;
                $getactual = getdatetimebytimezone(DFLT_TZ,$input['fromdate_search'],$curtz);
                $input['fromdate_search'] = $getactual['date'];
                $whr .= " AND DATE(o.pickup_datetime)>='".$input['fromdate_search']."' ";
            }
        }
        if(isset($input['todate_search'])){
            if($input['todate_search'] != ""){
                $inp = 1;
                $getactual = getdatetimebytimezone(DFLT_TZ,$input['todate_search'],$curtz);
                $input['todate_search'] = $getactual['date'];
                $whr .= " AND DATE(o.delivery_datetime)<='".$input['todate_search']."' ";
            }
        }
        
        $getords = array();
        if(!empty($input['reference'])){
            $inp = 1;
            $reference = implode("','", $input['reference']);
            $getords = $this->getReadyOrdersbyRef($reference);
        }
        if(!empty($input['order_type'])){
            $inp = 1;
            $ordtye = implode("','", $input['order_type']);
            $getords1 = $this->getReadyOrdersbyRefOT("OT",$ordtye);
            if(!empty($getords1)){
                $getords = array_merge($getords,$getords1);
            }else{
                $getords2 = $this->getActiveOrdersdetailsType($ordtye);
                if(!empty($getords2)){
                    $getords = array_merge($getords,$getords2);
                }
            }
        }
        if(!empty($getords)){
            $gords = implode("','",$getords);
            $whr .= " AND o.id IN('$gords') ";
        }
    }
    if($inp == 1 && $whr == ""){
        $whr .= " AND o.id IN(0) ";
    }
    $whr .= " AND o.order_status ='READY' ";
    $mainqry = "SELECT count(o.id) as cnt FROM tb_orders o WHERE o.user_id IN $cid AND o.trip_id=0 AND o.status!=0 AND o.trip_sts=0 $whr";
    $custord1 = $this->db->query($mainqry);
    $pgcnt = $custord1->row()->cnt;
    $config = array();
    $config["base_url"] = base_url() . "admin/readyorders";
    $config["total_rows"] = $pgcnt;
    $config["per_page"] = 5;
    $config["uri_segment"] = 3;
    $config['display_pages'] = TRUE;
    $this->pagination->initialize($config);
    $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
    $mainqry = "SELECT o.id,o.order_id,o.order_status,convertToClientTZ(o.pickup_datetime,'".$curtz."') as pickup_datetime,o.pickup_city,o.pickup_country,convertToClientTZ(o.delivery_datetime,'".$curtz."') as delivery_datetime,o.delivery_city,o.delivery_country,o.order_status FROM tb_orders o WHERE o.user_id IN $cid AND o.status!=0 AND o.trip_id=0 AND o.trip_sts=0 $whr ORDER BY o.pickup_datetime,o.order_status DESC";
    $qry = $mainqry . ' LIMIT ' . $page . ',' . $config["per_page"];
    $custord = $this->db->query($qry);
    if($custord->num_rows()>0){
     $data['orders'] = $custord->result_array(); 
 }
 $this->newtemplate->dashboard('admin/readyorders',$data);
}

public function doneorders(){
    $data['page_title']=$this->lang->line('menu_completed');
    $data['sub_title']=$this->lang->line('menu_completed')." ".$this->lang->line('bookings');
    $data['orders'] = array();
    $inp = 0;
    $whusr1 = "";
    $lgusrid = $this->session->userdata("user_id");
    $btype = $this->session->userdata('business_type');
    $branch_user = $data["branch_user"] = isset($_POST["branch_user"]) ? $_POST["branch_user"] : "-1";
    if(!in_array($btype, ['Country Admin'])){
        $whusr1 = " AND o.user_id IN (".$lgusrid.") ";
    }else{
        $admids = $this->session->userdata('country_user_ids');
        if($branch_user != "-1"){
            $whusr1 = " AND o.user_id IN ($branch_user)";
        }else if(count($admids)>0){
            $users = implode(",", $admids);
            $whusr1 = " AND o.user_id IN ($users)";
        }else{
            $whusr1 = " AND o.user_id IN (-1)";
        }
    }
    $company_code = $this->session->userdata("company_code");
    $curtz = $this->session->userdata("usr_tzone")['timezone'];
    $input = $this->input->post();
    $whr = "";
    if(!empty($input)){
        if(!empty($input['bookingid'])){
            $bookids = implode("','", $input['bookingid']);
            $inp = 1;
            $whr .= " AND o.order_id IN('".$bookids."')";
        }
        if(isset($input['pickup']) && !empty($input['pickup'])){
            $inp = 1;
            $whr .= " AND (o.pickup_country LIKE '%".$input['pickup']."%' OR o.pickup_city LIKE '%".$input['pickup']."%') ";
        }
        if(isset($input['drop']) && !empty($input['drop'])){
            $inp = 1;
            $whr .= " AND (o.delivery_country LIKE '%".$input['drop']."%' OR o.delivery_city LIKE '%".$input['drop']."%') ";
        }
        if(isset($input['fromdate_search'])){
            if($input['fromdate_search'] != ""){
                $inp = 1;
                $getactual = getdatetimebytimezone(DFLT_TZ,$input['fromdate_search'],$curtz);
                $input['fromdate_search'] = $getactual['date'];
                $whr .= " AND DATE(o.pickup_datetime)>='".$input['fromdate_search']."' ";
            }
        }
        if(isset($input['todate_search'])){
            if($input['todate_search'] != ""){
                $inp = 1;
                $getactual = getdatetimebytimezone(DFLT_TZ,$input['todate_search'],$curtz);
                $input['todate_search'] = $getactual['date'];
                $whr .= " AND DATE(o.delivery_datetime)<='".$input['todate_search']."' ";
            }
        }
        
        $getords = array();
        if(!empty($input['reference'])){
            $inp = 1;
            $reference = implode("','", $input['reference']);
            $getords = $this->getCompletedOrdersbyRef($reference);
        }
        if(!empty($input['order_type'])){
            $inp = 1;
            $ordtye = implode("','", $input['order_type']);
            $getords1 = $this->getCompletedOrdersbyRefOT("OT",$ordtye);
            if(!empty($getords1)){
                $getords = array_merge($getords,$getords1);
            }else{
                $getords2 = $this->getActiveOrdersdetailsType($ordtye);
                if(!empty($getords2)){
                    $getords = array_merge($getords,$getords2);
                }
            }
        }
        if(!empty($getords)){
            $gords = implode("','",$getords);
            $whr .= " AND o.id IN('$gords') ";
        }
    }
    if($inp == 1 && $whr == ""){
        $whr .= " AND o.id IN(0) ";
    }
    $mainqry = "SELECT count(o.id) as cnt FROM tb_orders o WHERE o.trip_sts=1 AND o.status!=0 $whusr1 $whr";
    $custord = $this->db->query($mainqry);
    $pgcnt = $custord->row()->cnt;
    $config = array();
    $config["base_url"] = base_url() . "admin/doneorders";
    $config["total_rows"] = $pgcnt;
    $config["per_page"] = 5;
    $config["uri_segment"] = 3;
    $config['display_pages'] = TRUE;
    $this->pagination->initialize($config);
    $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
    $mainqry2 = "SELECT o.id,o.order_id,o.pickup_datetime,o.delivery_datetime,o.pickup_country,o.delivery_country,o.pickup_city,o.delivery_city,o.shift_id,o.trip_id FROM tb_orders o WHERE o.trip_sts=1 AND o.status!=0 $whusr1 $whr ORDER BY o.updatedon DESC";
    $qry = $mainqry2 . ' LIMIT ' . $page . ',' . $config["per_page"];
    $custord1 = $this->db->query($qry);
    if($custord1->num_rows()>0){
        $data['orders'] = $custord1->result_array(); 
    }
    $this->newtemplate->dashboard('admin/doneorders',$data);
}

public function downloadCompletedOrder($order_id='',$pdf = "0") {
    $data['ord_type'] = "done";
    $tb_orders = $this->db->query("SELECT id,order_id,pickup_datetime,delivery_datetime,pickup_address1,delivery_address1,shift_id FROM tb_orders WHERE id='$order_id'");
    if($tb_orders->num_rows()>0){
        $data["order"] = $tb_orders->row();
        $shift_id = $data["order"]->shift_id;
        $userid = $this->session->userdata('user_id');
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $data['order_id'] = $order_id;
        $data['userid'] = $userid;
        $data["pod"] = $this->db->query("SELECT ts.id,ts.latitude,ts.longitude,ts.stop_id,ts.stop_type,dt.type_name,ts.createdby,convertToClientTZ(ts.createdon,'".$curtz."') as createdon, ts.imgpath, e.address, e.pickup, e.drop, e.order_id from tb_pod_uploads ts LEFT JOIN tb_document_types dt ON dt.id=ts.doc_type LEFT JOIN tb_employee e ON e.id = ts.stop_detail_id WHERE ts.order_id = '".$order_id."' AND ts.status=1 GROUP BY ts.id ORDER BY ts.createdon ASC");
        $data["orderref"] = $this->db->query("SELECT group_concat(r.reference_id) as reference_id FROM tb_order_references r WHERE r.order_id=$order_id AND r.status=1");
        if($pdf=="1"){
            $this->load->view("admin/downloadCompletedOrder",$data);
        }else{
            $this->load->library('m_pdf');
            $html = $this->load->view('admin/downloadCompletedOrder',$data,true);
            $this->m_pdf->pdf->WriteHTML($html);
            $this->m_pdf->pdf->Output();
        }
    }
}

public function orderdetails(){
    $data['ord_type'] = "done";
    if($this->input->post("order_id")){
        $order_id = $this->input->post("order_id");
        $userid = $this->session->userdata('user_id');
        $company_code = $this->session->userdata('company_code');
        $branch_code = $this->session->userdata('branch_code');
        $data['ord_type'] = $this->input->post("type");
        $data['userid'] = $userid;
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $logdate = date('Y-m-d H:i:s');
        $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
        $curdt = $getactual['datetime'];
        $data["order"] = $this->db->where(array("id"=>$order_id))->get("tb_orders")->row();
        $party_types = array();
        $partytypes = $this->db->select("id,name")->get_where("tbl_party_types",array("user_id"=>$userid,'company_code'=>$company_code));
        if($partytypes->num_rows() == 0){
            $chk = $this->db->select("name,description")->get_where("tbl_party_types",array("user_id"=>1));
            if($chk->num_rows()>0){
                foreach($chk->result_array() as $res){
                    $ins = array('name'=>$res['name'], 'description'=>$res['description'], 'user_id'=>$userid, 'company_code'=>$company_code, 'branch_code'=>$branch_code, 'status'=>1, 'created_on'=>$curdt);
                    $insqry = $this->db->insert("tbl_party_types",$ins);
                }
            }
            $partytypes = $this->db->select("id,name")->get_where("tbl_party_types",array("user_id"=>$userid,'company_code'=>$company_code));
            $party_types = $partytypes->result_array();
        }else{
            $party_types = $partytypes->result_array();
        }
        $data['party_types'] = $party_types;
        $data["orderref"] = $this->db->query("SELECT r.id,r.reference_id,r.ref_value,t.description FROM tb_order_references r,tb_reference_master t WHERE r.reference_id=t.name AND r.order_id=$order_id AND r.status=1 GROUP BY r.id");
        echo $this->load->view("admin/vieworderdetails",$data,TRUE);
    }
}

public function trackviewmap($ord){
    $data["page_title"] = "MAP";
    if($ord != ""){
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $tripinfo = $this->common->gettblrowdata(array("order_id"=>$ord,"trip_id !="=>0),"trip_id","tb_orders",0,0);
        if(count($tripinfo)>0){
            $trip_id = $tripinfo['trip_id'];
            $data["trip_id"] = $trip_id;
            $chktrip = $this->db->select("id,shift_id,vehicle_id,driver_id,status,dlat as latitude,dlng as longitude")->get_where("tb_trips",array("id"=>$trip_id),1,0);
            if($chktrip->num_rows()>0){
                $data["trip"]= $trip = $chktrip->row();
                $driver_row = $this->db->select("id,name,track_type")->where("id",$trip->driver_id)->get("tb_truck_drivers")->row();
                $data["stops"] = $this->db->query("SELECT id,stopname,stopcity,address,stoptype,convertToClientTZ(startdate,'".$curtz."') as startdate,weight,volume,ship_units from tb_shiporder_stops where shipment_id = ".$trip->shift_id." AND status='1' ORDER BY ordernumber ASC");
                $data["details"] = [];
                foreach($data["stops"]->result() as $stop){
                    $det = $this->db->query("SELECT id,plat,plng,pickup,stop_id,convertToClientTZ(pickup_datetime,'".$curtz."') as pickup_datetime,dlat,dlng,`drop`,convertToClientTZ(drop_datetime,'".$curtz."') as drop_datetime, shipment_weight, shipment_volume, order_id from tb_employee where (stop_id = ".$stop->id." OR drop_stopid = ".$stop->id.") AND status = '1'");
                    foreach($det->result() as $row){
                        $details=[];
                        if($row->stop_id == $stop->id){
                            $details["lat"] = $row->plat;
                            $details["lng"] = $row->plng;
                            $details["name"] = $row->pickup;
                            $details["time"] = $row->pickup_datetime;
                            $details["type"] = "P";
                        }else{
                            $details["lat"] = $row->dlat;
                            $details["lng"] = $row->dlng;
                            $details["name"] = $row->drop;
                            $details["time"] = $row->drop_datetime;
                            $details["type"] = "D";
                        } 
                        $details["weight"] = $row->shipment_weight;
                        $details["volume"] = $row->shipment_volume;
                        
                        $details["stop_id"] = $stop->id;
                        $details["id"] = $row->id;
                        $details["order_id"] = $row->order_id;
                        $details["alphabet"] = getColName(count($data["details"])+1);
                        $details["status"] = "0";
                        $done = $this->db->query("SELECT ss.status_id,ss.latitude,ss.longitude,convertToClientTZ(ss.createdon,'".$curtz."') as createdon,sm.status_name from tb_stop_status ss LEFT JOIN tb_status_master sm ON sm.id=ss.status_id WHERE ss.shipment_id=".$trip->shift_id." AND ss.stop_id = ".$stop->id." AND ss.stop_detail_id = ".$row->id." AND ss.status='1' ORDER BY ss.createdon ASC");
                        $status_row=[];
                        if($done->num_rows()>0){
                            foreach($done->result() as $status){
                                $status_row["detail_id"] = $row->id;
                                $status_row["status_id"] = $status->status_id;
                                $status_row["status_name"] = $status->status_name;
                                $status_row["in_lat"] = $status->latitude;
                                $status_row["in_lng"] = $status->longitude;
                                $status_row["in_time"] = $status->createdon;
                                $details["statuses"][] = (Object) $status_row;
                            }
                            $details["status"] = "1";
                        }else{
                            $details["statuses"][] = (Object) $status_row;
                        }
                        $data["details"][] = (Object)$details;
                    }
                }
                $data["drivers"] = $this->db->query("SELECT d.id,d.name,d.contact_num,convertToClientTZ(td.createdon,'".$curtz."') as createdon,d.track_type,td.travelled_km from tb_trip_drivers td LEFT JOIN tb_truck_drivers d ON d.id = td.driver_id WHERE trip_id = $trip_id AND td.status ='1' GROUP BY d.id ORDER BY td.createdon ASC")->result_array();
                $data["drivers"][] = array("id"=>$driver_row->id,"name"=>$driver_row->name,"contact_num"=>$driver_row->name,"createdon"=>null,"track_type"=>$driver_row->track_type,"travelled_km"=>0);
                if($trip->status == 1){
                    $data["record"] = $this->db->query("SELECT latitude,longitude FROM tb_trucks_data WHERE id = '".$trip->vehicle_id."' LIMIT 1")->row();
                }else{
                    $data["record"] = $trip;
                }
                $data["isSimBased"] = "false";
                if (count($data["drivers"])>0){
                    if($data["drivers"][count($data["drivers"])-1]["track_type"] == "1"){
                        $data["isSimBased"] = "true";        
                    }
                }
                if($data["isSimBased"]){
                    $data["records"] = $this->db->query("SELECT latitude,longitude,speed,`timestamp` FROM tb_rtdrive_locations WHERE trip_id = '$trip_id' order by `timestamp`");
                }
                $this->settemplate->dashboardtemp('admin/tripMap',$data);
            }else{
                echo "";
            }
        }else{
            echo "";
        }
    }else{
        echo "";
    }
}

public function statusviewdetails(){
    $ord = $this->input->post("order_id");
    $tripinfo = $this->common->gettblrowdata(array("order_id"=>$ord),"id,plat,plng,dlat,dlng,shift_id,trip_id","tb_orders",0,0);
    if(count($tripinfo)>0){
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $shift_id = $tripinfo['shift_id'];
        $whr = " AND status = 1";
        $dlat = $tripinfo['dlat'];
        $dlng = $tripinfo['dlng'];
        $slat = $tripinfo['plat'];
        $slng = $tripinfo['plng'];
        $order_row_id = $tripinfo['id'];
        $data["drivers"] = array();
        $trip_id = $tripinfo['trip_id'];
        if($trip_id != 0){
            $qry = $this->db->query("SELECT id,vehicle_id,status,dlat as latitude,dlng as longitude FROM tb_trips WHERE id='$trip_id' ORDER BY id DESC LIMIT 1");
            if($qry->num_rows() > 0){
                $sts = $qry->row()->status;
                if($sts == 1){
                    $rec = $this->db->query("SELECT latitude,longitude from tb_trucks_data WHERE id = '".$qry->row()->vehicle_id."' LIMIT 1");
                    if($rec->num_rows()>0){
                        $slat = $rec->row()->latitude;
                        $slng = $rec->row()->longitude;
                    }
                }else{
                    $slat = $qry->row()->latitude;
                    $slng = $qry->row()->longitude;
                }
                $drivers = $this->db->query("SELECT d.id,d.name,d.contact_num,convertToClientTZ(td.createdon,'".$curtz."') as createdon from tb_trip_drivers td LEFT JOIN tb_truck_drivers d ON d.id = td.driver_id WHERE td.trip_id = $trip_id AND td.status =1 GROUP BY d.id ORDER BY td.createdon ASC");
                if($drivers->num_rows()>0){
                    $data["drivers"] = $drivers->result_array();
                }
            }
        }
        /*$sql = $this->db->query("SELECT ts.id,ts.latitude,ts.longitude,ts.stop_id,ts.stop_type,ts.status_code,convertToClientTZ(ts.createdon,'".$curtz."') as createdon,sm.status_name from tb_stop_status ts LEFT JOIN tb_status_master sm ON sm.id=ts.status_id WHERE ts.shipment_id = '$shift_id' GROUP BY ts.id ORDER BY ts.id ASC");*/
        $sql = $this->db->query("SELECT ts.id,ts.latitude,ts.longitude,ts.loc_name,ts.stop_id,ts.stop_type,ts.status_code,convertToClientTZ(ts.createdon,'".$curtz."') as createdon,sm.status_name from tb_stop_status ts,tb_status_master sm,tb_employee e WHERE sm.id=ts.status_id AND ts.shipment_id=e.shift_id AND e.order_id='".$ord."' AND ts.shipment_id = '$shift_id' AND (ts.stop_detail_id=0 OR ts.stop_detail_id=e.id) GROUP BY ts.id ORDER BY ts.id ASC");
        $data["history"] = $sql;
        $data["slat"] = $slat;
        $data["slng"] = $slng;
        $data["dlat"] = $dlat;
        $data["dlng"] = $dlng;
        $data['epod'] = array();
        $data['epodp'] = array();
        $docs = $this->db->select("id,stop_id,stop_type,latitude,longitude,convertToClientTZ(createdon,'".$curtz."') as createdon")->get_where("tb_pod_uploads",array("stop_type"=>"D","doc_type"=>3,"order_id"=>$order_row_id,"status"=>1),1,0);
        if($docs->num_rows()>0){
            $data['epod'] = array("status_code"=>"3060","status_name"=>"ePOD Available","stop_id"=>$docs->row()->stop_id,"stop_type"=>$docs->row()->stop_type,"location"=>getLocationName($docs->row()->latitude,$docs->row()->longitude),"createdon"=>$docs->row()->createdon);
        }
        $docs = $this->db->select("id,stop_id,stop_type,latitude,longitude,convertToClientTZ(createdon,'".$curtz."') as createdon")->get_where("tb_pod_uploads",array("stop_type"=>"P","doc_type"=>3,"order_id"=>$order_row_id,"status"=>1),1,0);
        if($docs->num_rows()>0){
            $data['epodp'] = array("status_code"=>"2490","status_name"=>"Goods Loaded for Delivery","stop_id"=>$docs->row()->stop_id,"stop_type"=>$docs->row()->stop_type,"location"=>getLocationName($docs->row()->latitude,$docs->row()->longitude),"createdon"=>$docs->row()->createdon);
        }
        echo $this->load->view("admin/statusview",$data,TRUE);
    }
}

public function orddocsdetails(){
    $ord = $this->input->post("order_id");
    $ordid = $this->input->post("id");
    $whr = "";
    if($ordid > 0){
        $whr = " AND ts.order_id=$ordid ";
    }
    $data['ord_type'] = "done";
    $tripinfo = $this->common->gettblrowdata(array("id"=>$ordid),"id,order_id,company_code,branch_code,shift_id,trip_id","tb_orders",0,0);
    if(count($tripinfo)>0){
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $data['ord_type'] = $this->input->post("type");
        $shift_id = $tripinfo['shift_id'];
        $data['ship_id'] = isset($tripinfo['shift_id']) ? $tripinfo['shift_id'] : 0;
        $data['trip_id'] = isset($tripinfo['trip_id']) ? $tripinfo['trip_id'] : 0;
        $sql1 = $this->db->query("SELECT ts.id,ts.latitude,ts.longitude,ts.stop_id,ts.stop_type,dt.type_name,ts.createdby,convertToClientTZ(ts.createdon,'".$curtz."') as createdon,ts.imgpath from tb_pod_uploads ts LEFT JOIN tb_document_types dt ON dt.id=ts.doc_type WHERE ts.shipment_id = $shift_id $whr GROUP BY ts.id");
        $data["pod"]=$sql1;
        $data['order_id'] = $ord;
        $data['document_types'] = $this->db->select("id,type_name")->get_where("tb_document_types",array("status"=>1))->result_array();
        $data['stops'] = array();
        $sql = "SELECT o.id,o.stopname FROM tb_shiporder_stops o,tb_employee e WHERE o.shipment_id=e.shift_id AND e.order_id='".$ord."' AND o.status=1 AND e.status=1 ORDER BY o.ordernumber ASC";
        $stops = $this->db->query($sql);
        if ($stops->num_rows() > 0) {
            $data['stops'] = $stops->result_array(); 
        }
        $data['order'] = (Object) $tripinfo;
        echo $this->load->view("admin/managedocuments",$data,TRUE);
    }
}

public function savenewreference(){
    $reference_id = $this->input->post("reference_id",true);
    $reference_value = $this->input->post("reference_value",true);
    $order_id = $this->input->post("order_id",true);
    $sts = "no";
    if($order_id != "" && $reference_id != "" && $reference_value != ""){
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        if($reference_id == "ETA"){
            $chkrtime = getdatetimebytimezone(DFLT_TZ,$reference_value,$curtz);
            $reference_value = $chkrtime['datetime'];
        }
        $logdate = date('Y-m-d H:i:s');
        $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
        $curdt = $getactual['datetime'];
        $chk = $this->db->select("id")->get_where("tb_order_references",array("order_id"=>$order_id,"ref_value"=>$reference_value),1,0);
        if($chk->num_rows() == 0){
            $sts = "1";
            $ins = array("order_id"=>$order_id,"reference_id"=>$reference_id,"ref_value"=>$reference_value,"status"=>1,"createdon"=>$curdt);
            $insdata = $this->db->insert("tb_order_references",$ins);
            $insdata = $this->db->insert("tb_order_references_history",$ins);
        }else{
            $sts = "2";
        }
    }
    echo $sts;
}

public function savenewparty(){
    $party_type = $this->input->post("party_type",true);
    $party_identifier = $this->input->post("party_identifier",true);
    $cust_identifier = $this->input->post("cust_identifier",true);
    $party_name = $this->input->post("party_name",true);
    $party_city = $this->input->post("party_city",true);
    $party_countrycode = $this->input->post("party_countrycode",true);
    $party_zipcode = $this->input->post("party_zipcode",true);
    $party_street = $this->input->post("party_street",true);
    $order_id = $this->input->post("order_id",true);
    $order_number = $this->input->post("order_number",true);
    $company_code = $this->input->post("company_code",true);
    $branch_code = $this->input->post("branch_code",true);
    $sts = "no";
    $userid = $this->session->userdata('user_id');
    if($order_id != "" && $party_type != "" && $party_name != ""){
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $logdate = date('Y-m-d H:i:s');
        $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
        $curdt = $getactual['datetime'];
        $chk = $this->db->select("id")->get_where("tbl_party_master",array("code"=>$cust_identifier),1,0);
        if($chk->num_rows() > 0){
            $pid = $chk->row()->id;
            $ins = array("name"=>$party_name,"location_id"=>$party_city,"address"=>$party_street,"pincode"=>$party_zipcode,"country"=>$party_countrycode,"code"=>$cust_identifier,"partyindetifier"=>$party_identifier,"customeridentifier"=>$cust_identifier,"status"=>1);
            $upddata = $this->db->where(array("id"=>$pid))->update("tbl_party_master",$ins);
        }else{
            $insdata = array("party_type_id"=>$party_type,"name"=>$party_name,"mobile"=>$cust_identifier,"location_id"=>$party_city,"address"=>$party_street,"pincode"=>$party_zipcode,"country"=>$party_countrycode,"user_id"=>$userid,"code"=>$cust_identifier,"partyindetifier"=>$party_identifier,"customeridentifier"=>$cust_identifier,"company_code"=>$company_code,"branch_code"=>$branch_code,"status"=>1,"created_on"=>$curdt);
            $insdata1 = $this->db->insert("tbl_party_master",$insdata);
            $pid = $this->db->insert_id();
        }
        $chk1 = $this->db->select("id")->get_where("tb_order_parties",array("party_id"=>$pid,"order_id"=>$order_id,"party_type"=>$party_type),1,0);
        if($chk1->num_rows() > 0){
            $sts = "2";
        }else{
            $insdata = array("order_id"=>$order_id,"order_number"=>$order_number,"party_id"=>$pid,"party_type"=>$party_type,"status"=>1,"createdon"=>$curdt);
            $insqry = $this->db->insert("tb_order_parties",$insdata);
            $sts = "1";
        }
    }
    echo $sts;
}

function getNumPagesPdf($filepath) {
    $fp = @fopen(preg_replace("/\[(.*?)\]/i", "", $filepath), "r");
    $max = 0;
    if (!$fp) {
        return "Could not open file: $filepath";
    } else {
        while (!@feof($fp)) {
            $line = @fgets($fp, 255);
            if (preg_match('/\/Count [0-9]+/', $line, $matches)) {
                preg_match('/[0-9]+/', $matches[0], $matches2);
                if ($max < $matches2[0]) {
                    $max = trim($matches2[0]);
                    break;
                }
            }
        }
        @fclose($fp);
    }
    return $max;
}

public function savenewpod(){
    $order_id = $this->input->post("order_id",true);
    $order_number = $this->input->post("order_number",true);
    $company_code = $this->input->post("company_code",true);
    $branch_code = $this->input->post("branch_code",true);
    $ship_id = $this->input->post("ship_id",true);
    $trip_id = $this->input->post("trip_id",true);
    $stop_id = $this->input->post("stop_id",true);
    $doc_type = $this->input->post("doc_type",true);
    $sts = "no";
    $pdfName = $fileName = "";
    $curtz = $this->session->userdata("usr_tzone")['timezone'];
    $logdate = date('Y-m-d H:i:s');
    $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
    $curdt = $getactual['datetime'];
    if (isset($_FILES)) {
        if (isset($_FILES['file_name']['name'])) {
            $config = array('upload_path'   => './assets/poduploads/',
                'allowed_types' => 'jpeg|jpg|gif|png|pdf|JPEG|JPG|PNG'
            );
            $file_name = $_FILES['file_name']['name'];
            $file_ext= @strtolower(end(explode('.',$_FILES['file_name']['name'])));
            $expensions= array("jpeg","jpg","png","gif","pdf");
            if(in_array($file_ext,$expensions)){
                if($file_ext == "pdf"){
                    $imgtype = "pdf";
                    $config['file_name'] = "RL".$order_number.".pdf";
                    $this->load->library('upload', $config);
                    if ($this->upload->do_upload('file_name')) {
                        $imgname = $this->upload->data();
                        $imgtype = $imgname['image_type'];
                        $fileName = $imgname['file_name'];
                        $filePath = './assets/poduploads/'.$fileName;
                    }
                }else{
                    $fileName = date('dmyhis').''.$file_name;
                    $filePath = './assets/poduploads/'.$fileName;
                    $imgsize = getimagesize($_FILES['file_name']['tmp_name']);
                    if($imgsize == FALSE){ }else{
                        /*log_message("error","imgupload ".json_encode($_FILES));*/
                        $top_width = $imgsize[0];
                        $top_height = $imgsize[1];
                        $quality = 100;
                        if($file_ext == "png"){
                            $top = @imagecreatefrompng($_FILES['file_name']['tmp_name']);
                            $imgtype = "png";
                        }else{
                            $top = @imagecreatefromjpeg($_FILES['file_name']['tmp_name']);
                            if($file_ext == "jpg" || $file_ext == "jpeg"){
                                $imgtype = "jpeg";
                            }else{
                                $imgtype = $file_ext;
                            }
                        } 
                        if($top){
                            header('Content-Type: image/jpeg');
                            @imagejpeg($top,$filePath,$quality);
                            @imagedestroy($top);
                        }else{
                            $fileName = "";
                            $sts = "2";
                        }
                    }
                }
                if($fileName != "" && $doc_type != ""){
                    $stoptype = $this->db->select("stoptype")->get_where("tb_shiporder_stops",array("id"=>$stop_id,"stoptype"=>"D","status"=>1),1,0);
                    $stype = "P";
                    if($stoptype->num_rows()>0){
                        $stype = "D";
                    }
                    if($stop_id == ""){ $stop_id = 0; }
                    $filedata = array('stop_id' => $stop_id, "shipment_id" => $ship_id, 'trip_id' => $trip_id, 'doc_type' => $doc_type, 'imgpath' => $fileName, 'stop_type' => $stype, 'order_id' => $order_id, 'status' => 1, 'createdon' => $curdt);
                    $res = $this->db->insert("tb_pod_uploads", $filedata);
                    if($order_id != "0"){
                        $postdata = array();
                        $postdata['ord_id'] = $order_id;
                        $getorderdetails = $this->common->gettblrowdata(array('id'=>$order_id),"user_id,created_source","tb_orders",0,0);
                        if(!empty($getorderdetails)){
                            $createdsource = $getorderdetails['created_source'];
                            if($createdsource == '9'){
                                $postdata['user_id'] = $getorderdetails['user_id'];
                                $postdata['status_code'] = '3060';
                                $postdata['hrs'] = $this->session->userdata("usr_tzone")['hrs'];
                                $updsts = $this->etrucknowquote->getstatusresponse($postdata);
                            }
                        }
                        if($stype == 'D' && $doc_type == 3){
                            $this->sendnotify('pod_note',$order_id,$filePath);
                        }
                    }
                    /*send mail to specific party*/
                        /*$mailwhr = "";
                        if($company_code != ""){
                            $mailwhr .= " AND company_code='".$company_code."'";
                        }
                        if($branch_code != ""){
                            $mailwhr .= " AND branch_code='".$branch_code."'";
                        }*/
                        $chkref = "XSR";
                        $whrin = " AND reference_id in('XSR','SD') ";
                        $chkordtypeqry = $this->db->query("SELECT reference_id,ref_value FROM tb_order_references WHERE order_id=$order_id $whrin AND ref_value!='' GROUP BY reference_id HAVING count(reference_id)>=1");
                        $refval = $refvalue = "";
                        $iofeof = "EOF";
                        if($chkordtypeqry->num_rows()>1){
                            /*$nums = $refs = array();*/
                            foreach($chkordtypeqry->result() as $oref){
                                /*$nums[] = $oref->reference_id;
                                $refs[] = $oref->ref_value;*/
                                if($oref->reference_id = "XSR"){
                                    $refvalue = $oref->ref_value;
                                }
                                if($oref->reference_id = "SD" && $oref->ref_value == "2"){
                                    $iofeof = "IOF";
                                }
                            }
                           /* if(!empty($nums)){
                                $reference = implode("','", $nums);
                                $refval = implode("','", $refs);
                                $mailwhr .= " AND ref_type IN('".$reference."')";
                            }*/
                            $chkparty = "SELECT party_id,partycontact_id,customer_id,email_note,emailid,party_name FROM tb_contact_notifys WHERE status=1 AND (pod_note=1 OR all_note=1) GROUP BY emailid";
                            $query = $this->db->query($chkparty);
                            if($query->num_rows()>0){
                                $this->load->library('email');
                                foreach($query->result() as $pmails){
                                   /*$parties = $this->db->where(array("code"=>$pmails->partycontact_id))->get("tbl_party_master");
                                   if($parties->num_rows()>0){*/
                                    $receivename = $pmails->party_name;
                                    $receivemail = $pmails->emailid;
                                    $orders = $this->db->get_where("tb_orders",array("id"=>$order_id),1,0);
                                    if($orders->num_rows()>0){
                                        $orddata = $orders->row_array();
                                        $data['order'] = $orddata;
                                        $data['page_title'] = "Booking ePOD";
                                        $data['receivemail'] = $receivemail;
                                        $data['receivename'] = $receivename;
                                        /*$sub = "eTrucknow::Booking ePOD ".$chkref.":".$refvalue."#746#".$iofeof);*/
                                        $sub = $refvalue."#746#".$iofeof;
                                        $this->email->from('etrucknow@kuehne-nagel.com', 'eTrucknow');
                                        $this->email->to($receivemail, $receivename);
                                        /*$this->email->cc('kambhamramachandra@gmail.com', 'RCREDDY K');*/
                                        /*$this->email->subject("eTrucknow :: Booking ePOD ".$chkref.":".$refval."#IOF/EOF".$orddata['order_id']);*/
                                        $this->email->subject($sub);
                                        $this->email->set_mailtype("html");
                                        $body = $this->load->view('mail_forms/bookepod', $data, true);
                                        $this->email->message($body);
                                        $epodpath = base_url()."assets/poduploads/".$fileName;
                                        $this->email->attach($epodpath);
                                        if(!$this->email->send()){
                                            foreach ( $this->email->get_debugger_messages() as $debugger_message ){
                                              log_message("error",$debugger_message);
                                          }
                                          $this->email->clear_debugger_messages();
                                      }
                                  }
                                  /*}*/
                              }
                          }
                      }
                      if($file_ext == "pdf" && $doc_type == "3"){
                        $pages = $this->getNumPagesPdf($filePath);
                        $unique_id = "RL".$order_number.".".date("Ymd").".".date("his");
                        $jplData = array("country"=>substr($company_code,0,-2),"branch"=>substr($branch_code,2),"order_id"=>$order_number,"time"=>date("hi"),"unique_id"=>$unique_id,"date"=>date("d.m.Y"),"pages"=>$pages);
                        $jplFilePath = $this->saveJPF($jplData);
                        $storeftipjpl = $this->uploadKNFile($jplFilePath);
                        $storeftippdf = $this->uploadKNFile($filePath);
                    }
                    $sts = "1";
                }
            }else{
                $sts = "format";
            }
        }
    } 
    echo $sts;
}

public function massstatus(){
    $data['page_title'] = $this->lang->line('menu_massstsupd');;
    $data['sub_title'] = $this->lang->line('general_search');;
    $uid = $this->session->userdata("user_id");
    $curtz = $this->session->userdata("usr_tzone")['timezone'];
    $chk_usrtype = $this->session->userdata("business_type");
    $carrierwhr = "";
    if($chk_usrtype == "Carrier"){
        $carrierwhr = " AND o.vendor_id =".$this->session->userdata("id");
    }else{
        $carrierwhr = " AND o.user_id =".$uid;
    }
    $config = array();
    $item = "";
    $post = $this->input->post(null,true);
        // ------New Code------------------------------------//
    if(isset($post['searchsubmit']) || isset($post['order_id'])){
        if(isset($post['searchsubmit'])){
            $item = trim($post['searchsubmit']);
        }else{
            $item = 1;
        }
    }
    if(isset($item) && $item != ""){
        $where1 = '';
        if (isset($post['order_id']) && $post['order_id'] != "") {
            $where1="AND o.order_id LIKE '%".$post['order_id']."%' ";
        }
        if(isset($post['trip_id'])  && $post['trip_id'] != ""){
            $where1 .="AND o.shipmentid LIKE '%".$post['trip_id']."%' ";
        }
        if (isset($post['container_no']) && $post['container_no'] != "") {
            $ref11 = $this->db->query("SELECT order_id FROM tb_order_references WHERE status=1 AND ref_value!='' AND ref_value LIKE '%".$post['container_no']."%' LIMIT 1");
            if($ref11->num_rows()>0){
                $oid11 = $ref11->row()->order_id;
                $where1 .= "AND o.id LIKE '%".$oid11."%' ";
            }
        }
        if(isset($post['driver']) && $post['driver'] != ""){
            $where1 .="AND (c.name LIKE '%".$post['driver']."%' OR c.mobile LIKE '%".$post['driver']."%' OR d.name LIKE '%".$post['driver']."%' OR d.contact_num LIKE '%".$post['driver']."%') ";
        }
        $searchwhr = " $where1";
        $mainqry = "SELECT o.id,o.order_id,o.shift_id,o.trip_id,o.shipmentid,v.vehicle_id,a.driver_id,a.imei,d.contact_num,d.track_type,d.track_vendor,d.sim_carrier FROM tb_orders o,tb_shft_veh v,tb_vendors c,tbl_assigned_drivers a,tb_truck_drivers d WHERE o.shift_id=v.shft_id AND v.carrier_id=c.id AND v.vehicle_id=a.vehicle_id AND a.driver_id=d.id AND v.status=1 AND a.status=1 AND d.status='Active' $searchwhr $carrierwhr AND o.status!=0 AND o.trip_sts=0 GROUP BY o.order_id ORDER BY o.id DESC";
        $custord1 = $this->db->query($mainqry);
    }elseif(isset($post['searchsubmita']) && $post['searchsubmita'] == "Search") {
        $where1 = '';
        if (isset($post['vehicle']) && $post['vehicle'] != "") {
            $where1= "AND v.register_number LIKE '%".$post['vehicle']."%' ";
        }
        if (isset($post['ref_id']) && $post['ref_id'] != "") {
            $ref = $this->db->query("SELECT order_id FROM tb_order_references WHERE status=1 AND ref_value!='' AND ref_value LIKE '%".$post['ref_id']."%' LIMIT 1");
            if($ref->num_rows()>0){
                $oid = $ref->row()->order_id;
                $where1 .= "AND o.id LIKE '%".$oid."%' ";
            }
        }
        $searchwhr = " $where1";
        $mainqry = "SELECT o.id,o.order_id,o.shift_id,o.trip_id,o.shipmentid,v.vehicle_id,a.driver_id,a.imei,d.contact_num,d.track_type,d.track_vendor,d.sim_carrier FROM tb_orders o,tb_shft_veh v,tb_vendors c,tbl_assigned_drivers a,tb_truck_drivers d WHERE o.shift_id=v.shft_id AND v.carrier_id=c.id AND v.vehicle_id=a.vehicle_id AND a.driver_id=d.id AND v.status=1 AND a.status=1 AND d.status='Active' $searchwhr $carrierwhr AND o.status!=0 AND o.trip_sts=0 GROUP BY o.order_id ORDER BY o.id DESC";
        $custord1 = $this->db->query($mainqry);
    }else{
        $mainqry = "SELECT o.id,o.order_id,o.shift_id,o.trip_id,o.shipmentid,v.vehicle_id,a.driver_id,a.imei,d.contact_num,d.track_type,d.track_vendor,d.sim_carrier FROM tb_orders o,tb_shft_veh v,tb_vendors c,tbl_assigned_drivers a,tb_truck_drivers d WHERE o.shift_id=v.shft_id AND v.carrier_id=c.id AND v.vehicle_id=a.vehicle_id AND a.driver_id=d.id AND v.status=1 AND a.status=1 AND d.status='Active' $carrierwhr AND o.status!=0 AND o.trip_sts=0 GROUP BY o.order_id ORDER BY o.id DESC";
        $custord1 = $this->db->query($mainqry);
    }
    $ordcount = $custord1->num_rows();
    $config["base_url"] = base_url() . "admin/massstatus";
    $config["total_rows"] = $ordcount;
    $config["per_page"] = 10;
    $config["uri_segment"] = 3;
    $config['display_pages'] = TRUE;
    $this->pagination->initialize($config);
    $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
    $mainqry = $mainqry.' LIMIT ' . $page . ',' . $config["per_page"];
        /*echo $mainqry;
        exit;*/
        $custord = $this->db->query($mainqry);
        $data['tripsdata'] = array();
        if($custord->num_rows()>0){
            foreach($custord->result() as $res){
                $ship_id = $res->shift_id;
                $multiship = 0;
                $tripid = $driver_id = 0;
                $vehicle_id = $res->vehicle_id;
                $contact_num = "";
                $shipmentid = $res->shipmentid;
                if($res->trip_id != 0 && $res->trip_id != ""){
                    $checktrip = $this->db->query("SELECT id,vehicle_id,driver_id,start_imei FROM tb_trips WHERE shift_id=$ship_id AND status=1");
                    if($checktrip->num_rows() > 0){
                        $contact_num = $checktrip->row()->start_imei;
                        $tripid = $checktrip->row()->id;
                        $vehicle_id = $checktrip->row()->vehicle_id;
                        $driver_id = $checktrip->row()->driver_id;
                    }
                }
                if($tripid == 0 && $vehicle_id != ""){
                    $driver_id = $res->driver_id;
                    $contact_num = $res->imei;
                }
                $tracktype = 0; $trackvendor = $simcarrier = $contact_num = "";
                if($res->track_type == 1){
                    $tracktype = 1;
                    $trackvendor = $res->track_vendor;
                    $simcarrier = $res->sim_carrier;
                    $contact_num = $res->contact_num;
                }
                $ordid = $res->id;
                $order_id = $res->order_id;
                $refers = array();
                $ref = $this->db->query("SELECT reference_id,ref_value FROM tb_order_references WHERE order_id=$ordid AND reference_id IN ('DQ','AAM','AWB','XSR','SD','INN','CTR') AND status=1 AND ref_value!='' ");
                if($ref->num_rows()>0){
                    $refers = $ref->result_array();
                }
                $saccept = $sgatein = $spickup = $sgateout = $intransit = $dgatein = $dpickup = $dgateout = 0;
                $s_saccept = $s_sgatein = $s_spickup = $s_sgateout = $s_intransit = $s_dgatein = $s_dpickup = $s_dgateout = 0;
                $s_sacceptcr = $s_sgateincr = $s_spickupcr = $s_sgateoutcr = $s_intransitcr = $s_dgateincr = $s_dpickupcr = $s_dgateoutcr = "";
                $pstpid = $dstpid = $pstodetailid = $dstodetailid = "";
                $chwhr = " AND ts.trip_id=$tripid AND ts.status=1";
               // $stopsts = $this->db->query("SELECT sm.id as sid,sm.status_name,ts.id,ts.stop_id,ts.stop_detail_id,ts.stop_type,ts.status_id,convertToClientTZ(ts.createdon,'".$curtz."') as createdon FROM tb_status_master sm LEFT JOIN tb_stop_status ts ON sm.id=ts.status_id AND ts.shipment_id=$ship_id $chwhr WHERE sm.id IN(1,2,3,4,10)");
                $stopsts = $this->db->query("SELECT sm.id as sid,sm.status_name,ts.id,ts.stop_id,ts.stop_detail_id,ts.stop_type,ts.status_id,ts.createdon as createdon FROM tb_status_master sm LEFT JOIN tb_stop_status ts ON sm.id=ts.status_id AND ts.shipment_id=$ship_id $chwhr WHERE sm.id IN(1,2,3,4,10)");
                if($stopsts->num_rows()>0){
                    foreach($stopsts->result() as $st){
                        if($st->sid == 10){
                            if($st->id != "" && $st->status_id == 10){
                                $pstpid = $st->stop_id;
                                $pstodetailid = $st->stop_detail_id;
                                $saccept = $st->status_id;
                                $s_saccept = 1;
                                $s_sacceptcr = $st->createdon;
                                if($pstpid == "0" || $pstpid == ""){
                                    $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.stop_id AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                    $chkq = $this->db->query($sql);
                                    if($chkq->num_rows()>0){
                                        $pstpid = $chkq->row()->id;
                                        $pstodetailid = $chkq->row()->stopdetailsid;
                                    }
                                }
                            }else{
                                $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.stop_id AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                $chkq = $this->db->query($sql);
                                if($chkq->num_rows()>0){
                                    $pstpid = $chkq->row()->id;
                                    $pstodetailid = $chkq->row()->stopdetailsid;
                                    $saccept = $st->sid;
                                }
                            }
                        }
                        if($st->sid == 2){
                            if($st->id != "" && $st->status_id == 2 && $st->stop_type=="P"){
                                $pstpid = $st->stop_id;
                                $pstodetailid = $st->stop_detail_id;
                                $sgatein = $st->status_id;
                                $s_sgatein = 1;
                                $s_sgateincr = $st->createdon;
                            }else{
                                $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND (s.id=e.stop_id OR s.id=e.drop_stopid) AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                $chkq = $this->db->query($sql);
                                if($chkq->num_rows()>0){
                                    $pstpid = $chkq->row()->id;
                                    $pstodetailid = $chkq->row()->stopdetailsid;
                                    $sgatein = $st->sid;
                                }
                            }
                            if($st->id != "" && $st->status_id == 2 && $st->stop_type=="D"){
                                $dstpid = $st->stop_id;
                                $dstodetailid = $st->stop_detail_id;
                                $dgatein = $st->status_id;
                                $s_dgatein = 1;
                                $s_dgateincr = $st->createdon;
                            }else{
                                $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.drop_stopid AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                $chkq = $this->db->query($sql);
                                if($chkq->num_rows()>0){
                                    $dstpid = $chkq->row()->id;
                                    $dstodetailid = $chkq->row()->stopdetailsid;
                                    $dgatein = $st->sid;
                                }
                            }
                        }
                        if($st->sid == 1){
                            if($st->id != "" && $st->status_id == 1 && $st->stop_type=="P"){
                                $pstpid = $st->stop_id;
                                $pstodetailid = $st->stop_detail_id;
                                $spickup = $st->status_id;
                                $s_spickup = 1;
                                $s_spickupcr = $st->createdon;
                            }else{
                                $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.stop_id AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                $chkq = $this->db->query($sql);
                                if($chkq->num_rows()>0){
                                    $pstpid = $chkq->row()->id;
                                    $pstodetailid = $chkq->row()->stopdetailsid;
                                    $spickup = $st->sid;
                                }
                            }
                            if($st->id != "" && $st->status_id == 1 && $st->stop_type=="D"){
                                $dstpid = $st->stop_id;
                                $dstodetailid = $st->stop_detail_id;
                                $dpickup = $st->status_id;
                                $s_dpickup = 1;
                                $s_dpickupcr = $st->createdon;
                            }else{
                                $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.drop_stopid AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                $chkq = $this->db->query($sql);
                                if($chkq->num_rows()>0){
                                    $dstpid = $chkq->row()->id;
                                    $dstodetailid = $chkq->row()->stopdetailsid;
                                    $dpickup = $st->sid;
                                }
                            }
                        }
                        if($st->sid == 3){
                            if($st->id != "" && $st->status_id == 3 && $st->stop_type=="P"){
                                $pstpid = $st->stop_id;
                                $pstodetailid = $st->stop_detail_id;
                                $sgateout = $st->status_id;
                                $s_sgateout = 1;
                                $s_sgateoutcr = $st->createdon;
                            }else{
                                $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.stop_id AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                $chkq = $this->db->query($sql);
                                if($chkq->num_rows()>0){
                                    $pstpid = $chkq->row()->id;
                                    $pstodetailid = $chkq->row()->stopdetailsid;
                                    $sgateout = $st->sid;
                                }
                            }
                            if($st->id != "" && $st->status_id == 3 && $st->stop_type=="D"){
                                $dstpid = $st->stop_id;
                                $dstodetailid = $st->stop_detail_id;
                                $dgateout = $st->status_id;
                                $s_dgateout = 1;
                                $s_dgateoutcr = $st->createdon;
                            }else{
                                $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.drop_stopid AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                $chkq = $this->db->query($sql);
                                if($chkq->num_rows()>0){
                                    $dstpid = $chkq->row()->id;
                                    $dstodetailid = $chkq->row()->stopdetailsid;
                                    $dgateout = $st->sid;
                                }
                            }
                        }
                        if($st->sid == 4){
                            if($st->id != "" && $st->status_id == 4){
                                $pstpid = $st->stop_id;
                                $pstodetailid = $st->stop_detail_id;
                                $intransit = $st->status_id;
                                $s_intransit = 1;
                                $s_intransitcr = $st->createdon;
                            }else{
                                $sql = "SELECT s.id,e.id as stopdetailsid FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.stop_id AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' LIMIT 1";
                                $chkq = $this->db->query($sql);
                                if($chkq->num_rows()>0){
                                    $pstpid = $chkq->row()->id;
                                    $pstodetailid = $chkq->row()->stopdetailsid;
                                    $intransit = $st->sid;
                                }
                            }
                        }
                    }
                }else{
                    $sql = "SELECT s.id,s.stoptype,s.ordernumber,e.id as stopdetailsid,s.status FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.stop_id AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' UNION SELECT s.id,s.stoptype,s.ordernumber,e.id as stopdetailsid,s.status FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$ship_id AND s.status=1 AND s.id=e.drop_stopid AND e.status=1 AND e.shift_id=$ship_id AND e.order_id='".$order_id."' ORDER BY ordernumber ASC ";
                    $emp = $this->db->query($sql);
                    /*$emp = $this->db->query("SELECT * FROM ($sql) P WHERE status = 1 ORDER BY ordernumber ASC");*/
                    if($emp->num_rows() > 0){
                        foreach($emp->result_array() as $res1){
                            if($res1['stoptype'] == "P"){
                                $saccept = 10;
                                $sgatein = 2;
                                $spickup = 1;
                                $sgateout = 3;
                                $intransit = 4;
                                $pstpid = $res1['id'];
                                $pstodetailid = $res1['stopdetailsid'];
                            }
                            if($res1['stoptype'] == "D"){
                                $dgatein = 2;
                                $dpickup = 1;
                                $dgateout = 3;
                                $dstpid = $res1['id'];
                                $dstodetailid = $res1['stopdetailsid'];
                            }
                        }
                    }
                }
                $data['tripsdata'][] = array("trip_id"=>$tripid,"ship_id"=>$ship_id,"ord_id"=>$ordid,"shipmentid"=>$shipmentid,"order_id"=>$res->order_id,"refers"=>$refers,"saccept"=>$saccept,"sgatein"=>$sgatein,"spickup"=>$spickup,"sgateout"=>$sgateout,"intransit"=>$intransit,"dgatein"=>$dgatein,"dpickup"=>$dpickup,"dgateout"=>$dgateout,"s_saccept"=>$s_saccept,"s_sgatein"=>$s_sgatein,"s_spickup"=>$s_spickup,"s_sgateout"=>$s_sgateout,"s_intransit"=>$s_intransit,"s_dgatein"=>$s_dgatein,"s_dpickup"=>$s_dpickup,"s_dgateout"=>$s_dgateout,"s_sacceptcr"=>$s_sacceptcr,"s_sgateincr"=>$s_sgateincr,"s_spickupcr"=>$s_spickupcr,"s_sgateoutcr"=>$s_sgateoutcr,"s_intransitcr"=>$s_intransitcr,"s_dgateincr"=>$s_dgateincr,"s_dpickupcr"=>$s_dpickupcr,"s_dgateoutcr"=>$s_dgateoutcr,"pstpid"=>$pstpid,"dstpid"=>$dstpid,"pstodetailid"=>$pstodetailid,"dstodetailid"=>$dstodetailid,"vehicle_id"=>$vehicle_id,"driver_id"=>$driver_id,"tracktype"=>$tracktype,"trackvendor"=>$trackvendor,"simcarrier"=>$simcarrier,"contact_num"=>$contact_num,"multiship"=>$multiship);
            }
        }
        $data['postdata'] = $post;
        $this->newtemplate->dashboard("admin/massstatus", $data); 
    }

    public function update_order_status()
    {
        //  log_message("error","update_order_status");

        $arr = array();
        $trip_id = $arr['trip'] = $this->input->post("trip_id", true);
        $company_code = $this->session->userdata('company_code');
        $branch_code = $this->session->userdata('branch_code');
        $ship_id = $arr['ship'] = $this->input->post("ship_id", true);
        $ord_id = $arr['ord_id'] = $this->input->post("ord_id", true);
        $orderid = $arr['orderid'] = $this->input->post("orderid", true);
        $stopid = $arr['stopid'] = $this->input->post("stopid", true);
        $stopdetailid = $arr['stopdetailid'] = $this->input->post("stopdetid", true);
        $vehicle_id = $arr['vehicle_id'] = $this->input->post("vehicle_id", true);
        $driver_id = $arr['driver_id'] = $this->input->post("driver_id", true);
        $tracktype = $arr['tracktype'] = $this->input->post("tracktype", true);
        $trackvendor = $arr['trackvendor'] = $this->input->post("trackvendor", true);
        $contact_num = $arr['contact_num'] = $this->input->post("contact_num", true);
        $stype = $this->input->post("stop_type", true);
        $stsid = $this->input->post("stsid", true);
        $sts = $this->input->post("status", true);
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $seldt = $stsdate = $this->input->post("sts_date", true);
        if (checkstsvaliddate($seldt)) {
            $gentime = getdatetimebytimezone(DFLT_TZ, $seldt, $curtz);
            $stsdate = $gentime['datetime'];
        } else {
            $stsdate = "";
        }
        $logdate = date('Y-m-d H:i:s');
        $getactual = getdatetimebytimezone(DFLT_TZ, $logdate, $curtz);
        $curdt = $getactual['datetime'];
        $hrs = $this->session->userdata("usr_tzone")['hrs'];
        $lgusrid = $this->session->userdata("user_id");
        $latitude = $longitude = "";
        $createdsource = "";
        $plat = $plng = $dlat = $dlng = "";
        $chkcs = $this->db->select("plat,plng,dlat,dlng,pickup_city,created_source,shipmentid")->get_where("tb_orders", array("id" => $ord_id), 1, 0);
        if ($chkcs->num_rows() > 0) {
            $createdsource = $chkcs->row()->created_source;
            $pickup_city = $chkcs->row()->pickup_city;
            $shipmentid = $chkcs->row()->shipmentid;
            if ($createdsource == "0") {
                $createdsource = 1;
            }
            if ($chkcs->row()->plat != "") {
                $plat = $chkcs->row()->plat;
                $plng = $chkcs->row()->plng;
            } else {
                $chklatlng = $this->db->select("latitude,longitude")->get_where("tb_site_settings", array("user_id" => $lgusrid), 1, 0);
                if ($chklatlng->num_rows() > 0) {
                    $plat = $chklatlng->row()->latitude;
                    $plng = $chklatlng->row()->longitude;
                }
            }
            if ($chkcs->row()->dlat != "") {
                $dlat = $chkcs->row()->dlat;
                $dlng = $chkcs->row()->dlng;
            } else {
                $chklatlng = $this->db->select("latitude,longitude")->get_where("tb_site_settings", array("user_id" => $lgusrid), 1, 0);
                if ($chklatlng->num_rows() > 0) {
                    $dlat = $chklatlng->row()->latitude;
                    $dlng = $chklatlng->row()->longitude;
                }
            }
        }
        $chkuserName = $this->db->select("name")->get_where("tb_users", array("id" => $lgusrid), 1, 0);
        if ($chkuserName->num_rows() > 0) {
            $Username = $chkuserName->row()->name;
        }
        $getrefrnceid = $this->db->query("select reference_id,ref_value from tb_order_references where order_id ='" . $ord_id . "'");
        if ($getrefrnceid->num_rows() > 0) {
            $refrenceId = $getrefrnceid->row()->reference_id;
            $ref_value = $getrefrnceid->row()->ref_value;
        } else {
            $refrenceId = '';
            $ref_value = '';
        }
        $tripid = $trip_id;
        $chkarr = array();
        $resultsts = 2;
        /* log_message("error","stsdate ".json_encode($stsdate));
        log_message("error","sts ".json_encode($sts));*/
        if ($stsdate != "" && $sts == "0") {
            $stid = $stsid;
            if ($stsid == "10") {
                $stcode = "0212";
            }
            if ($stsid == "2" && $stype == "P") {
                $stcode = "0420";
            }
            if ($stsid == "1" && $stype == "P") {
                $stcode = "0500";
            }
            if ($stsid == "3" && $stype == "P") {
                $stcode = "0191";
            }
            if ($stsid == "4") {
                $stcode = "1550";
            }
            if ($stsid == "2" && $stype == "D") {
                $stcode = "0192";
            }
            if ($stsid == "1" && $stype == "D") {
                $stcode = "2300";
            }
            if ($stsid == "3" && $stype == "D") {
                $stcode = "3000";
            }
            if ($stsid == "11") {
                $stcode = "0218";
            }
            /* log_message("error","stsid ".json_encode($stsid));
           log_message("error","trip ".json_encode($arr['trip'])); */
            if ($stsid == "10" && $arr['trip'] == "0") {

                $stcode = "0212";
                /*start trip*/
                $chqry = $this->db->select("id")->get_where("tb_trips", array('shift_id' => $arr['ship'], 'vehicle_id' => $arr['vehicle_id'], 'driver_id' => $arr['driver_id']), 1, 0);
                if ($chqry->num_rows() == 0) {
                    if ($arr['contact_num'] == "") {
                        $newimei = $this->db->select("imei")->get_where("tbl_assigned_drivers", array('vehicle_id' => $arr['vehicle_id'], 'driver_id' => $arr['driver_id'], 'status' => 1), 1, 0);
                        if ($newimei->num_rows() > 0) {
                            $arr['contact_num'] = $newimei->row()->imei;
                        }
                    }
                    $latitude = $plat;
                    $longitude = $plng;
                    $triparr = array('shift_id' => $arr['ship'], 'vehicle_id' => $arr['vehicle_id'], 'driver_id' => $arr['driver_id'], 'stime' => $stsdate, 'start_imei' => $arr['contact_num'], 'splace' => "", 'eplace' => "", 'start_reading' => 0, 'end_reading' => 0, 'created_on' => $stsdate, 'updated_on' => $curdt, 'status' => 1, 'trip_type' => 0, 'transit_status' => 0, "plat" => $latitude, "plng" => $longitude);
                    $arr['trip'] = $this->common->insertTableData('tb_trips', $triparr);
                    $insarry = array("order_id" => $ord_id, "shipment_id" => $arr['ship'], "stop_id" => 0, "stop_detail_id" => 0, "stop_type" => "", "trip_id" => $arr['trip'], "status_id" => $stid, "latitude" => $latitude, "longitude" => $longitude, "status" => 1, "reason" => "From Admin", "vehicle_id" => $arr['vehicle_id'], "driver_id" => $arr['driver_id'], "status_code" => $stcode, "createdon" => $stsdate);
                    $ins = $this->db->insert("tb_stop_status", $insarry);
                    /* update orders table */
                    $ordwhr = array("shift_id" => $arr['ship']);
                    $ordset = array("trip_id" => $arr['trip']);
                    $upd = $this->db->set($ordset)->where($ordwhr)->update("tb_orders");

                    $postdata = array(
                        "shipment_id" => $arr['ship'],
                        "trip_id" => $arr['trip'],
                        "driver_id" => $arr['driver_id'],
                        "vehicle_id" => $arr['vehicle_id'],
                        "order_id" => $arr['orderid'],
                        "user_id" => $lgusrid,
                        "stop_id" => '',
                        "latitude" => $latitude,
                        "longitude" => $longitude,
                        "curtz" => $curtz,
                        "hrs" => $hrs,
                        "web" => $seldt,
                        "status_code" => $stcode,
                        "ord_id" => $ord_id,
                        "branch_code" => $branch_code,
                        "company_code" => $company_code,
                        "pickup_city" => $pickup_city,
                        "Username" => $Username,
                        "refrenceId" => $refrenceId,
                        "ref_value" => $ref_value,
                        "shipmentid" => $shipmentid

                    );


                    /*$sts = $this->statusintigration->shipmentconfirm($postdata);*/
                    /*send to roadlog*/
                    if ($stid == "10") {
                        // Driver Accept
                        $postdata['stop_type'] = 'Accept';
						$postdata['status_value'] = 'Driver Accept';
                        $this->generatestatusxml($postdata);

                    }
                    if ($createdsource == "0") {
                        $sts = $this->statusintigration->roadlogshipmentconfirm($postdata);
                    } else if ($createdsource == "5") {
                        $sts = $this->statusintigration->salogshipmentstatus($postdata);
                    } else if ($createdsource == "9") {
                        $sts = $this->etrucknowquote->getstatusresponse($postdata);
                    } else if ($createdsource == "13") {
                        $this->load->library("amazonstatusintegration");
                        $sts = $this->amazonstatusintegration->outboundTrailerASN($postdata);
                    } else if ($createdsource == "8") {
                        $curtz = $this->session->userdata("usr_tzone")['timezone'];
                        $logdate = date('Y-m-d H:i:s');
                        $getactual = getdatetimebytimezone(DFLT_TZ, $logdate, $curtz);
                        $cur_date = $getactual['datetime'];
                        $postdata['date_transmission'] = $cur_date;
                        $postdata["truck_number"] = null;
                        $chktruck = $this->db->query("SELECT truck_number FROM tb_trucks_data WHERE id = '" . $arr['vehicle_id'] . "' LIMIT 1");
                        if ($chktruck->num_rows() > 0) {
                            $postdata["truck_number"] = $chktruck->row()->truck_number;
                        }
                        $postdata["edi_reference"] = null;
                        $chkreference = $this->common->gettblrowdata(array('order_id' => $ord_id, 'reference_id' => 'EDI'), 'ref_value', 'tb_order_references', 0, 0);
                        if (!empty($chkreference)) {
                            $postdata["edi_reference"] = $chkreference['ref_value'];
                        }
                        if ($stid == "10") {
                            // Driver Accept
                            $postdata['situation_code'] = 'EML';
                            $postdata['justification_code'] = 'CFM';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);


                        }
                        if ($stid == "2" && $stype == "P") {
                            // GATEIN
                            $postdata['situation_code'] = 'EML';
                            $postdata['justification_code'] = 'ARS';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);


                        }
                        if ($stid == "1" && $stype == "P") {
                            // GATEIN PICKUP
                            $postdata['situation_code'] = 'ECH';
                            $postdata['justification_code'] = 'CFM';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);


                        }

                        if ($stid == "1" && $stype == "D") {
                            // Destination - delivery
                            $postdata['situation_code'] = 'LIV';
                            $postdata['justification_code'] = 'CFM';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);
                        }
                        if ($stid == "3" && $stype == "D") {
                            // Destination - Gateout
                            $session_ccode = $this->session->userdata('company_code');
                            $postdata['situation_code'] = 'LIV';
                            $postdata['justification_code'] = 'DES';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);
                            if ($session_ccode == "PLKN") {
                                $postdata['company_code'] = $session_ccode;
                                $session_currency = $this->session->userdata("usr_tzone")['currency'];
                                $post['currency'] = $session_currency;
                            }
                        }
                    } else if ($createdsource == "12") {
                        $sts = $this->macdamstatusintigration->macdamshipmentconfirm($postdata);
                    } else if ($createdsource == "11") {
                        $sts = $this->knlogin->knloginshipmentstatus($postdata);
                    }

                    log_message('error', "driver accept before calling id :" . $arr['ord_id']);

                    $this->sendnotify('driver_accept', $arr['ord_id']);
                    /* send notify mail to admin */
                    /*if($lgusrid == "12"){
                        $sndmail = $this->loadconfirmmail($postdata);
                    }*/
                    if ($lgusrid == "17") {
                        $chkven = $this->db->select("vendor_id")->get_where("tb_shifts", array("id" => $arr['ship']), 1, 0);
                        if ($chkven->num_rows() > 0) {
                            if ($chkven->row()->vendor_id == "143") {
                                $getcsvfile = $this->getcsvfile($arr['orderid']);
                            }
                        }
                    }
                }
                $resultsts = 1;
            } else if ($arr['trip'] != "0" && $stid != "11") {

                log_message('error', "Get IN Case 2");

                $chqry = $this->db->select("id")->get_where("tb_stop_status", array("shipment_id" => $arr['ship'], "stop_id" => $stopid, "stop_detail_id" => $stopdetailid, "stop_type" => $stype, "trip_id" => $arr['trip'], "status_id" => $stid), 1, 0);
                if ($chqry->num_rows() == 0) {
                    if ($stid == "2" && $stype == "P") {
                        $ttdata = array("id" => $arr['trip']);
                        $data2["updated_on"] = $curdt;
                        $data2["transit_status"] = '1';
                        $res = $this->db->set($data2)->where($ttdata)->update("tb_trips");
                    }
                    if ($stype == "P" || $stid == "4") {
                        $latitude = $plat;
                        $longitude = $plng;
                    }
                    if ($stype == "D") {
                        $latitude = $dlat;
                        $longitude = $dlng;
                    }
                    /*if($this->session->userdata('company_code') == "UKKN" || $this->session->userdata('company_code') == "AUKN"){
                        if($stid == 2){
                            $stcode = "TL";
                        }else if($stid == 1){
                            $stcode = "CL";
                        }else if($stid == 3){
                            $stcode = "LY";
                        }
                    }*/
                    $insarry = array("order_id" => $ord_id, "shipment_id" => $arr['ship'], "stop_id" => $stopid, "stop_detail_id" => $stopdetailid, "stop_type" => $stype, "trip_id" => $arr['trip'], "status_id" => $stid, "latitude" => $latitude, "longitude" => $longitude, "status" => 1, "reason" => "From Admin", "vehicle_id" => $arr['vehicle_id'], "driver_id" => $arr['driver_id'], "status_code" => $stcode, "createdon" => $stsdate);
                    $ins = $this->db->insert("tb_stop_status", $insarry);
                    $chqry1 = $this->db->select("id")->get_where("tb_trip_employee", array("employee_id" => $stopdetailid, "stop_id" => $stopid, "trip_id" => $arr['trip'], "status" => 1), 1, 0);
                    if ($chqry1->num_rows() == 0) {
                        $insarr = array("employee_id" => $stopdetailid, "stop_id" => $stopid, "trip_id" => $arr['trip'], "status" => 1, 'driver_late' => 0, 'emp_late' => 0, 'stime' => $curdt, 'check_in' => $curdt, 'absent_reason' => 'Closed', 'created_on' => $curdt, 'updated_on' => $curdt, 'pd_status' => 1);
                        $ins = $this->db->insert("tb_trip_employee", $insarr);
                    }


                    $postdata = array(
                        "shipment_id" => $arr['ship'],
                        "trip_id" => $arr['trip'],
                        "driver_id" => $arr['driver_id'],
                        "stop_id" => $stopid,
                        "order_id" => $arr['orderid'],
                        "inc_id" => 0,
                        "pod_type" => '',
                        "latitude" => $latitude,
                        "longitude" => $longitude,
                        "stop_type" => $stype,
                        "vehicle_id" => $arr['vehicle_id'],
                        "curtz" => $curtz,
                        "hrs" => $hrs,
                        "web" => $seldt,
                        "status_code" => $stcode,
                        "ord_id" => $ord_id,
                        "branch_code" => $branch_code,
                        "company_code" => $company_code,
                        "pickup_city" => $pickup_city,
                        "Username" => $Username,
                        "refrenceId" => $refrenceId,
                        "ref_value" => $ref_value,
                        "shipmentid" => $shipmentid
                    );
                    if ($stid == "1" && $stype == "P") {
                        $this->sendnotify('pickup_note', $arr['ord_id']);

                    }
                    if ($stid == "10" && $stype == "P") {
                        // Driver Accept
                        $postdata['stop_type'] = 'Accept';
                        $postdata['status_value'] = 'Driver Accept';

                        $this->generatestatusxml($postdata);
                    }
                    if ($stid == "2" && $stype == "P") {
                        // GATEIN 
                        $postdata['stop_type'] = 'Pickup';
						$postdata['status_value'] = 'Origin - Pickup';

                        $this->generatestatusxml($postdata);

                    }
                    if ($stid == "1" && $stype == "P") {
                        // GATEIN PICKUP
                        $postdata['stop_type'] = 'Pickup';
						$postdata['status_value'] = 'Origin - GateIn';

                        $this->generatestatusxml($postdata);
                    }
                    if ($stid == "4" && $stype == "P") {
                        // In-Transit
                        $postdata['stop_type'] = 'In-Transit';
						$postdata['status_value'] = 'In-Transit';

                        $this->generatestatusxml($postdata);
                    }
                    if ($stid == "3" && $stype == "P") {
                        // In-Transit
                        $postdata['stop_type'] = 'Border';
						$postdata['status_value'] = 'Origin - Gateout';

                        $this->generatestatusxml($postdata);
                    }
                    if ($stid == "1" && $stype == "D") {
                        // Destination - delivery
                        $postdata['stop_type'] = 'Drop';
						$postdata['status_value'] = 'Destination - delivery';
                        $this->generatestatusxml($postdata);
                    }
                    if ($stid == "2" && $stype == "D") {
                        // Destination - delivery
                        $postdata['stop_type'] = 'Drop';
						$postdata['status_value'] = 'Destination - delivery';
                        $this->generatestatusxml($postdata);
                    }
                    if ($stid == "3" && $stype == "D") {
                        // Destination - Gateout
                        $postdata['stop_type'] = 'Border';
						$postdata['status_value'] = 'Destination - Gateout';
                        $this->generatestatusxml($postdata);
                    }
                    if ($createdsource == "0") {
                        if ($stid == "4") {
                            /*$sts = $this->statusintigration->shipmentintransit($postdata);*/
                            /*send to roadlog*/
                            $sts = $this->statusintigration->roadlogshipmentintransit($postdata);

                        }
                        if ($stid == "2" && $stype == "P") {
                            /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                            $sts = $this->statusintigration->roadlogshipmentpgatein($postdata);


                        }
                        if ($stid == "1" && $stype == "P") {
                            /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                            $sts = $this->statusintigration->roadlogshipmentpicked($postdata);
                        }
                        if ($stid == "3" && $stype == "P") {
                            /*send to roadlog*/
                            $sts = $this->statusintigration->roadlogshipmentpgateout($postdata);
                        }
                        if ($stid == "2" && $stype == "D") {
                            /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                            $sts = $this->statusintigration->roadlogshipmentdgatein($postdata);
                        }
                        if ($stid == "1" && $stype == "D") {
                            /*$sts = $this->statusintigration->shipmentdelivered($postdata);*/
                            /*send to roadlog*/
                            /*  $this->sendnotify('delivery_note',$arr['ord_id']);*/
                            $sts = $this->statusintigration->roadlogshipmentdelivered($postdata);
                        }
                    } else if ($createdsource == "5") {
                        if ($stid == "1" && $stype == "D") {
                            $sts = $this->statusintigration->salogshipmentstatus($postdata);
                            /*   $this->sendnotify('delivery_note',$arr['ord_id']);*/
                        }
                        if ($stid == "1" && $stype == "P") {
                            $sts = $this->statusintigration->salogshipmentstatus($postdata);
                        }
                    } else if ($createdsource == '9') {
                        if ($stid == '4' || ($stid == "1" && $stype == "P") || ($stid == "3" && $stype == "D")) {
                            $postdata['user_id'] = $lgusrid;
                            if ($stid == "1" && $stype == "P") {
                            }
                            /*if($stid == "1" && $stype == "D"){
                                $this->sendnotify('delivery_note',$arr['ord_id']);
                            }*/
                            $sts = $this->etrucknowquote->getstatusresponse($postdata);
                        }
                    } else if ($createdsource == "8") {
                        $curtz = $this->session->userdata("usr_tzone")['timezone'];
                        $logdate = date('Y-m-d H:i:s');
                        $getactual = getdatetimebytimezone(DFLT_TZ, $logdate, $curtz);
                        $cur_date = $getactual['datetime'];
                        $postdata['date_transmission'] = $cur_date;
                        $postdata["truck_number"] = null;
                        $chktruck = $this->db->query("SELECT truck_number FROM tb_trucks_data WHERE id = '" . $arr['vehicle_id'] . "' LIMIT 1");
                        if ($chktruck->num_rows() > 0) {
                            $postdata["truck_number"] = $chktruck->row()->truck_number;
                        }
                        $postdata["edi_reference"] = null;
                        $chkreference = $this->common->gettblrowdata(array('order_id' => $ord_id, 'reference_id' => 'EDI'), 'ref_value', 'tb_order_references', 0, 0);
                        if (!empty($chkreference)) {
                            $postdata["edi_reference"] = $chkreference['ref_value'];
                        }
                        if ($stid == "10") {
                            // Driver Accept
                            $postdata['situation_code'] = 'EML';
                            $postdata['justification_code'] = 'CFM';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);

                        }
                        if ($stid == "2" && $stype == "P") {
                            // GATEIN
                            $postdata['situation_code'] = 'EML';
                            $postdata['justification_code'] = 'ARS';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);

                        }
                        if ($stid == "1" && $stype == "P") {
                            // GATEIN PICKUP
                            $postdata['situation_code'] = 'ECH';
                            $postdata['justification_code'] = 'CFM';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);
                        }
                        if ($stid == "4" && $stype == "P") {
                            // In-Transit
                            $postdata['situation_code'] = 'MLV';
                            $postdata['justification_code'] = 'CFM';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);
                        }
                        if ($stid == "1" && $stype == "D") {
                            // Destination - delivery
                            $postdata['situation_code'] = 'LIV';
                            $postdata['justification_code'] = 'CFM';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);
                        }
                        if ($stid == "3" && $stype == "D") {
                            // Destination - Gateout
                            $postdata['situation_code'] = 'LIV';
                            $postdata['justification_code'] = 'DES';
                            $sts = $this->shippeostatusintigration->shippeoStatusUpdate($postdata);
                        }
                    } else if ($createdsource == "12") {
                        if ($stid == "4") {
                            $sts = $this->macdamstatusintigration->macdamshipmentintransit($postdata);
                        }
                        if ($stid == "2" && $stype == "P") {
                            $sts = $this->macdamstatusintigration->macdamshipmentpgatein($postdata);
                        }
                        if ($stid == "1" && $stype == "P") {
                            $sts = $this->macdamstatusintigration->macdamshipmentpicked($postdata);
                        }
                        if ($stid == "3" && $stype == "P") {
                            /*send to macdamstatusintigration*/
                            $sts = $this->macdamstatusintigration->macdamshipmentpgateout($postdata);
                        }
                        if ($stid == "2" && $stype == "D") {
                            $sts = $this->macdamstatusintigration->macdamshipmentdgatein($postdata);
                        }
                        if ($stid == "1" && $stype == "D") {
                            $sts = $this->macdamstatusintigration->macdamshipmentdelivered($postdata);
                        }
                    } else if ($createdsource == "11") {
                        $sts = $this->knlogin->knloginshipmentstatus($postdata);
                    } else if ($createdsource == "13") {
                        $this->load->library("amazonstatusintegration");
                        if ($stid == "2" && $stype == "P") {
                            $postdata['status_code'] = "TL";
                            $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                        }
                        if ($stid == "1" && $stype == "P") {
                            $postdata['status_code'] = "CL";
                            $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                        }
                        if ($stid == "3" && $stype == "P") {
                            $postdata['status_code'] = "LY";
                            $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                        }
                        if ($stid == "2" && $stype == "D") {
                            $postdata['status_code'] = "TL";
                            $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                        }
                        if ($stid == "1" && $stype == "D") {
                            $postdata['status_code'] = "CL";
                            $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                        }
                        if ($stid == "3" && $stype == "D") {
                            $postdata['status_code'] = "LY";
                            $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                        }
                    }
                    if ($stid == "3" && $stype == "D") {
                        /*send to roadlog*/
                        if ($createdsource == "0") {
                            $sts = $this->statusintigration->roadlogshipmentdgateout($postdata);
                        } else if ($createdsource == "5") {
                            $sts = $this->statusintigration->salogshipmentstatus($postdata);
                        } else if ($createdsource == "11") {
                            $sts = $this->knlogin->knloginshipmentstatus($postdata);
                        }
                        log_message("error", "web_gateout");
                        $sts = $this->sendepodgateoutnotify($postdata);
                        /*   $this->sendnotify('delivery_note',$arr['ord_id']);*/
                        $session_ccode = $this->session->userdata('company_code');
                        if ($session_ccode == "PLKN") {
                            $postdata['company_code'] = $session_ccode;
                            $session_currency = $this->session->userdata("usr_tzone")['currency'];
                            $post['currency'] = $session_currency;
                            $exchangerate = $this->exchangerate->updateexchangerate_byorderid($postdata);
                        }

                    }
                    $resultsts = 1;
                }
            } else if ($stsid == "11" && $arr['trip'] != 0 && $arr['trip'] != "") {

                log_message('error', "Get IN case 3");

                $latitude = $dlat;
                $longitude = $dlng;
                $postdata = array(
                    "shipment_id" => $arr['ship'],
                    "trip_id" => $arr['trip'],
                    "driver_id" => $arr["driver_id"],
                    "stop_id" => '',
                    "order_id" => $arr['orderid'],
                    "latitude" => $latitude,
                    "longitude" => $longitude,
                    "curtz" => $curtz,
                    "hrs" => $hrs,
                    "web" => $seldt
                );
                log_message("error", "close_statusmass " . $lgusrid);
                /*send to roadlog*/
                if ($createdsource == "0") {
                    $sts = $this->statusintigration->roadlogshipmenttripdelivered($postdata);
                }
                if ($createdsource == "12") {
                    $sts = $this->macdamstatusintigration->macdamshipmenttripdelivered($postdata);
                } else if ($createdsource == "11") {
                    $sts = $this->knlogin->knloginshipmentstatus($postdata);
                }
                $this->sendnotify('delivery_note', $arr['ord_id']);
                $chkmuliti = $this->db->select("id")->get_where("tb_orders", array("shift_id" => $arr['ship'], "trip_sts" => '0'));
                if ($chkmuliti->num_rows() > 1) {
                    $upd = $this->db->where(array("id" => $ord_id, "trip_id !=" => 0))->update("tb_orders", array("trip_sts" => '1'));
                } else {
                    $updwhr = array("id" => $arr['ship']);
                    $upddata = array("status" => '0', "updated_on" => $curdt);
                    $upd = $this->db->set($upddata)->where($updwhr)->update("tb_shifts");
                    $data1 = array();
                    $tdata = array("id" => $arr['trip']);
                    $data1["end_imei"] = $arr['contact_num'];
                    $data1["end_reading"] = '0';
                    $data1["etime"] = $data1["updated_on"] = $curdt;
                    $data1["status"] = '0';
                    $data1["transit_status"] = '1';
                    $res = $this->db->set($data1)->where($tdata)->update("tb_trips");
                    $upd = $this->db->where(array("shift_id" => $arr['ship']))->update("tb_orders", array("trip_sts" => '1'));
                    $gensum = $this->generatesummary($arr['trip'], $curtz);
                }
                $resultsts = 1;
            }
        }
        echo $resultsts;
    }

    public function generatestatusxml($postdata)
    {

        $timestamp = date("Ymdhis");
        $request = '';
        $request .= '<EL3EDIMessage>';
        $request .= '<EL3EDIStatusHeader>';
        $request .= '<Version>1.0</Version>';
        $request .= '<UserName>eTrucknow</UserName>';
        $request .= '<Password>eTrucknow</Password>';
        $request .= '<SenderTransmissionNo>' . $postdata['order_id'] . '_' . $timestamp . '</SenderTransmissionNo>';
        $request .= '<AckSpec>';
        $request .= '<EmailAddress>dummy@email.com</EmailAddress>';
        $request .= '<AckOption>SUCCESS</AckOption>';
        $request .= '</AckSpec>';
        $request .= '<SourceApp>XLOG</SourceApp>';
        $request .= '<DestinationApp>' . $postdata['branch_code'] . '</DestinationApp>';
        $request .= '<ReferenceId>' . $postdata['refrenceId'] . '</ReferenceId>';
        $request .= '<Action>BookingStatusUpdate</Action>';
        $request .= '</EL3EDIStatusHeader>';
        $request .= '<EL3EDIStatusBody>';
        $request .= '<EL3OrgDetails>';
        $request .= '<Companycode>' . $postdata['company_code'] . '</Companycode>';
        $request .= '<Branchcode>' . $postdata['branch_code'] . '</Branchcode>';
        $request .= '<Departmentcode></Departmentcode>';
        $request .= '<PhysicalReceiver/>';
        $request .= '<LogicalReceiver/>';
        $request .= '<PhysicalSender/>';
        $request .= '<LogicalSender/>';
        $request .= '</EL3OrgDetails>';
        $request .= '<Order>';
        $request .= '<OrderID>' . $postdata['order_id'] . '</OrderID>';
        $request .= '<TripID>' . $postdata['shipmentid'] . '</TripID>';
        $request .= '<ExternalReferenceId></ExternalReferenceId>';
        $request .= '<Status>';
        $request .= '<StatusCode>' . $postdata['status_code'] . '</StatusCode>';
        $request .= '<StatusValue>' . $postdata['status_value'] . '</StatusValue>';
        $request .= '<StatusType>' . $postdata['stop_type'] . '</StatusType>';
        $request .= '<DateTime>' . $postdata['web'] . '</DateTime>';
        $request .= '<TimeZone>' . $postdata['hrs'] . '/' . $postdata['curtz'] . '</TimeZone>';
        $request .= '<UTC>' . $postdata['hrs'] . '</UTC>';
        $request .= '<Lat>' . $postdata['latitude'] . '</Lat>';
        $request .= '<Long>' . $postdata['longitude'] . '</Long>';
        $request .= '<Location>' . $postdata['pickup_city'] . '</Location>';
        $request .= '<ActionUser>' . $postdata['Username'] . '</ActionUser>';
        $request .= '<Message></Message>';
        $request .= '<References>';
        $request .= '<RefType>';
        $request .= '<Code>' . $postdata['refrenceId'] . '</Code>';
        $request .= '<Value>' . $postdata['ref_value'] . '</Value>';
        $request .= '</RefType>';
        $request .= '</References>';
        $request .= '<Remark>';
        $request .= '<RemarkType/>';
        $request .= '</Remark>';
        $request .= '</Status>';
        $request .= '</Order>';
        $request .= '</EL3EDIStatusBody>';
        $request .= '</EL3EDIMessage>';
        $resname = date("Ymdhis");
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->loadXML($request);
        $dom->save('xml/ORDSTATUSKN' . $resname . '.xml');
        log_message("error", "request-order " . $request);
        $serviceurl = BOOKING_ETRA_URL;
        $username = BOOKING_ETRA_USRNAME;
        $password = BOOKING_ETRA_PWD;
        /*$username = "ws_etra";
        $password = "4fh2drGs3n";*/
        $auth = base64_encode($username . ':' . $password);
        $headers = array(
            'Content-Type: application/xml',
            'Authorization: Basic ' . base64_encode("$username:$password")
        );
        $output = thirdpartyservicecurl($serviceurl, $headers, $request);
        log_message('error', "statuslogresponsexml " . json_encode($output));
    }
    
    public function getcsvfile($order_id)
    {
        if($order_id != ""){
            $result = array();
            $orderfile = "KNO_".date('Ymdhi_s')."6501".'.csv';
            $filename = './assets/csvfiles/'.$orderfile; 
            $file = null;
            if(file_exists($filename)){
              $file = fopen($filename, 'w');
          }else{
              $file = fopen($filename, 'x');
          }
          $consignee_id = 0;
          $this->db->select("o.id as order_row_id,o.order_id,o.pickup_custid,s.shipid,m.id as consignee_id,m.name as consignee_name");
          $this->db->from("tb_orders o");
          $this->db->join("tb_shipments s","o.shipment_id=s.id","LEFT");
          $this->db->join("tbl_party_master m","o.drop_custid=m.code","LEFT");
          $this->db->where("o.order_id",$order_id);
          $this->db->limit(1);
          $userdata = $this->db->get();
          if($userdata->num_rows()>0){
            $consignee_id = $userdata->row()->consignee_id;
            $order_row_id = $userdata->row()->order_row_id;
            $order_id = $userdata->row()->order_id;
            $consignee_name = $userdata->row()->consignee_name;
            $shipment_number = $userdata->row()->shipid."00";
            $shipper_id = 0;
            $getconsignordetails = $this->db->select("shipper_id")->get_where("tb_order_details",array('order_row_id'=>$order_row_id),1,0);
            if($getconsignordetails->num_rows() >0){
                $shipper_id = $getconsignordetails->row()->shipper_id;
            }
            $shipper_city = $shipper_street = $shipper_state =  $consignee_city = $consignee_street = $consignee_state = $shipper_name = "";
            if($shipper_id != 0){
              $getshipper_name = $this->db->select('name')->get_where("tbl_party_master",array('id'=>$shipper_id));
              if($getshipper_name->num_rows() >0){
                $shipper_name = $getshipper_name->row()->name;
            }
            $getshipper_address = $this->db->select('location_id,street,state')->get_where("tbl_orderparty_address",array('order_id'=>$order_row_id,'party_master_id'=>$shipper_id));
            if($getshipper_address->num_rows()>0){
                $shipper_city = $getshipper_address->row()->location_id;
                $shipper_street = $getshipper_address->row()->street;
                $shipper_state = $getshipper_address->row()->state;
            }
        }
        if($consignee_id != 0){
          $getconsignee_address = $this->db->select('location_id,street,state')->get_where("tbl_orderparty_address",array('order_id'=>$order_row_id,'party_master_id'=>$consignee_id));
          if($getconsignee_address->num_rows()>0){
            $consignee_city = $getconsignee_address->row()->location_id;
            $consignee_street = $getconsignee_address->row()->street;
            $consignee_state = $getconsignee_address->row()->state;
        }
    }
    $cargo = array();
    $getcargodetails = $this->db->query("SELECT c.cargo_type,c.goods_description,o.weight,o.volume,o.quantity FROM tb_cargo_details c,tb_order_cargodetails o WHERE o.order_id ='".$order_row_id."' AND o.status='1' AND o.cargo_id=c.id GROUP BY c.id ORDER BY c.id DESC");
    if($getcargodetails->num_rows()>0){
      foreach ($getcargodetails->result() as $row) {
        $qty = $row->quantity; 
        $cargo_type = $row->cargo_type;
        $description = $row->goods_description; 
        $weight = $row->weight; 
        $volume = $row->volume;  
        $empty = "";
        $cargo[] = array('G','quantity'=>round($qty),'cargo_type'=>$cargo_type,'description'=>$description,'DG','weight'=>$weight,'volume'=>$volume);
    }
}

$result = array('H',$order_id,'KU','',$shipper_name,$shipper_street,$shipper_state,$shipper_city,$consignee_name,$consignee_street,$consignee_state,$consignee_city,$shipment_number);
fputcsv($file,$result); 
foreach ($cargo as $key => $value) {
   fputcsv($file,$value); 
}
}
fclose($file); 
$new_file = $orderfile;
           /* $url = "ftp.bascik.co.nz";
            $username = "KNagel";
            $password = 'Hjk$7529';*/
           /* $url = "ftp://ftp.kuehne-nagel.com/pub/source/EL3/source/prod/";
            $username = "tstetra";
            $password = 'RU5bUPbGif';*/
            $ftp = @ftp_connect(BASICK_URL);
            if (false === $ftp) {
                log_message("error", 'Unable to connect Bascik FTP');
            }else{
                log_message("error", 'Bascik FTP Connected');
                $loggedIn = @ftp_login($ftp,BASICK_USER_NAME,BASICK_PASSWORD);
                if (true === $loggedIn) {
                    ftp_pasv($ftp, true) or die("Passive mode failed");
                    $d  = @ftp_nb_put($ftp, $new_file, $filename, FTP_ASCII);
                    while ($d == FTP_MOREDATA){
                      $d = ftp_nb_continue($ftp);
                  }
              } else {
                log_message("error",'Unable to login Bascik FTP');
            }
        }
    }
}

public function loadconfirmmail($post){
    $data['page_title'] = "Load Confirmed by Driver";
    $data['order_id'] = $post['order_id'];
    $driver_id = $post['driver_id'];
    $vehicle_id = $post['vehicle_id'];
    $data['user_id'] = $post['user_id'];
    $data['driver'] = $data['drivermobile'] = $data['register_number'] = "";
    $chk = $this->db->select("name,contact_num,user_id")->get_where("tb_truck_drivers",array("id"=>$driver_id),1,0);
    if($chk->num_rows()>0){
        $data['driver'] = $chk->row()->name;
        $data['user_id'] = $chk->row()->user_id;
        $data['drivermobile'] = $chk->row()->contact_num;
    }
    $chk1 = $this->db->select("register_number")->get_where("tb_trucks_data",array("id"=>$vehicle_id),1,0);
    if($chk1->num_rows()>0){
        $data['register_number'] = $chk1->row()->register_number;
    }
    $this->load->library('email');
    $chkven = $this->db->select("name,emailid")->get_where("tb_users",array("id"=>$data['user_id']),1,0);
    if($chkven->num_rows()>0){
        $receivename = $chkven->row()->name;
        $receivemail = $chkven->row()->emailid;
        $newsub = "eTrucknow: Load Confirmed by Driver for Order#".$data['order_id'];
        $data['receivename'] = $receivename;
        if($receivemail != ""){
            $body = $this->load->view('mail_forms/confirmbydriver', $data, true);
            $this->email->from('etrucknow@kuehne-nagel.com', 'eTrucknow');
            /*$this->email->from('info@returntrucks.com', "Return Trucks");*/
            $this->email->to($receivemail, $receivename);
            /*$this->email->cc('rcreddyk84@gmail.com', "RCREDDY");*/
                /*if(!empty($ccmail)){
                    $ccmail = array_unique($ccmail);
                    $cc_mail = implode(", ",$ccmail);
                    $cc_mail = '"'.$cc_mail.'"';
                    $this->email->cc($cc_mail);
                }*/
                $this->email->subject($newsub);
                $this->email->set_mailtype("html");
                $this->email->message($body);
                if(!$this->email->send()){
                    foreach ( $this->email->get_debugger_messages() as $debugger_message ){
                      log_message("error",$debugger_message);
                  }
                  $this->email->clear_debugger_messages();
              }
          }
      }
  }

  public function sendepodgateoutnotify($postdata){
    $order_id = $postdata['order_id'];
    $ship_id = $postdata['shipment_id'];
    $trip_id = $postdata['trip_id'];
    $stop_id = $postdata['stop_id'];
    $driver_id = $postdata['driver_id'];
    $vehicle_id = $postdata['vehicle_id'];
    $curtz = $postdata['curtz'];
        /*$order_id = "ORDER_1003291578026009";
        $ship_id = 454;
        $trip_id = 398;
        $stop_id = "";
        $driver_id = 62;
        $vehicle_id = 10825;
        $curtz = "Asia/Kolkata";*/
        $createdsource = 0;
        $chkcust = $this->db->query("SELECT o.id,o.customer_id,o.user_id,o.created_source,c.name,c.gcm_id,c.company_code,c.branch_code FROM tb_orders o,tb_customers c WHERE o.customer_id=c.id AND o.order_id='".$order_id."' LIMIT 1");
        if($chkcust->num_rows()>0){
            $ordid = $chkcust->row()->id;
            $cust_id = $chkcust->row()->customer_id;
            $user_id = $chkcust->row()->user_id;
            $cust_name = $chkcust->row()->name;
            $company_code = $chkcust->row()->company_code;
            $branch_code = $chkcust->row()->branch_code;
            $createdsource = $chkcust->row()->created_source;
           /* $mailwhr = "";
            if($company_code != ""){
                $mailwhr .= " AND company_code='".$company_code."'";
            }
            if($branch_code != ""){
                $mailwhr .= " AND branch_code='".$branch_code."'";
            }*/
            $chkadminusr = $this->db->query("SELECT name,emailid,cc_mails FROM tb_users WHERE id='$user_id' AND emailid!='' LIMIT 1");
            if($chkadminusr->num_rows()>0){
                $this->load->library('email');
                $receivename = $chkadminusr->row()->name;
                $receivemail = $chkadminusr->row()->emailid;
                $ccmail = array();
                if($chkadminusr->row()->cc_mails != ""){
                    $ccmail[] = $chkadminusr->row()->cc_mails;
                }
                /*$receivename = "RCREDDY";
                $receivemail = "kambhamramachandra@gmail.com";*/
                $orders = $this->db->get_where("tb_orders",array("id"=>$ordid),1,0);
                if($orders->num_rows()>0){
                    $orddata = $orders->row_array();
                    $data['order'] = $orddata;
                    $data['userid'] = $user_id;
                    $epodpath = "./assets/trippods/RL".$order_id.".pdf";
                    $data["pod"] = $this->db->query("SELECT ts.id,ts.latitude,ts.longitude,ts.stop_id,ts.stop_type,dt.type_name,ts.createdby,convertToClientTZ(ts.createdon,'".$curtz."') as createdon,ts.imgpath,e.address,e.pickup,e.drop,e.order_id from tb_pod_uploads ts LEFT JOIN tb_document_types dt ON dt.id=ts.doc_type LEFT JOIN tb_employee e ON e.id = ts.stop_detail_id WHERE ts.order_id = '".$ordid."' AND ts.status=1 GROUP BY ts.id ORDER BY ts.createdon ASC");
                    $data["orderref"] = $this->db->query("SELECT group_concat(r.reference_id) as reference_id FROM tb_order_references r WHERE r.order_id=$ordid AND r.status=1");
                    $this->load->library('m_pdf');
                    $sub = "eTrucknow::Shipment ePOD & Milestone Status #".$order_id." ";
                    $data['page_title'] = "Trip ePOD";
                    $data['receivemail'] = $receivemail;
                    $data['receivename'] = $receivename;
                    $chkref = "XSR";
                    $whrin = " AND reference_id in('XSR','SD') ";
                    $chkordtypeqry = $this->db->query("SELECT reference_id,ref_value FROM tb_order_references WHERE order_id=$ordid $whrin AND ref_value!='' GROUP BY reference_id HAVING count(reference_id)>=1");
                    $refvalue = $newsub = "";
                    $iofeof = "EOF";
                    if($chkordtypeqry->num_rows()>1){
                        $refs = array();
                        foreach($chkordtypeqry->result() as $oref){
                            $refs[] = $oref->ref_value;
                            if($oref->reference_id = "XSR"){
                                $refvalue = $oref->ref_value;
                            }
                            if($oref->reference_id = "SD" && $oref->ref_value == "2"){
                                $iofeof = "IOF";
                            }
                        }
                        $newsub = $refvalue."#746#".$iofeof;
                        $sub .= $chkref.":".$refvalue."#746#".$iofeof."";
                        $chkparty = "SELECT emailid,party_name FROM tb_contact_notifys WHERE status=1 AND (pod_note=1 OR all_note=1) GROUP BY emailid";
                        $query = $this->db->query($chkparty);
                        if($query->num_rows()>0){
                            foreach($query->result() as $pmails){
                                $ccmail[] = $pmails->emailid;
                            }
                        }
                    }
                    if($newsub == ""){
                        $newsub = $sub;
                    }
                    log_message("error","ePODWeb ".$newsub);
                    $this->email->from('etrucknow@kuehne-nagel.com', 'eTrucknow');
                    $this->email->to($receivemail, $receivename);
                    if(!empty($ccmail)){
                        $ccmail = array_unique($ccmail);
                        $cc_mail = implode(", ",$ccmail);
                        $cc_mail = '"'.$cc_mail.'"';
                        $this->email->cc($cc_mail);
                    }
                    /*$this->email->bcc('kambhamramachandra@gmail.com', 'RCREDDY K');*/
                    $this->email->subject($newsub);
                    $this->email->set_mailtype("html");
                    $data['mailtype'] = "ePOD";
                    $data['stops'] = array();
                    $data['pickup_datetime'] = "";
                    $data['delivery_datetime'] = "";
                    $stops = $this->db->query("SELECT ss.stop_type,ss.status_id,ss.latitude,ss.longitude,convertToClientTZ(ss.createdon,'".$curtz."') as createdon,sm.status_name,t.register_number,d.name from tb_stop_status ss LEFT JOIN tb_status_master sm ON sm.id=ss.status_id LEFT JOIN tb_trucks_data t ON ss.vehicle_id=t.id LEFT JOIN tb_truck_drivers d ON ss.driver_id=d.id WHERE ss.shipment_id=".$ship_id." AND ss.status='1' ORDER BY ss.createdon ASC");
                    if($stops->num_rows()>0){
                        foreach($stops->result() as $sres){
                            $stsname = $sres->status_name;
                            if($sres->status_id=="2" && $sres->stop_type == "P"){
                                $stsname = "Pickup Gate In";
                            }
                            if($sres->status_id=="1" && $sres->stop_type == "P"){
                                $data['pickup_datetime'] = $sres->createdon;
                                $stsname = "Pickup Done";
                            }
                            if($sres->status_id=="3" && $sres->stop_type == "P"){
                                $stsname = "Pickup Gate Out";
                            }
                            if($sres->status_id=="2" && $sres->stop_type == "D"){
                                $stsname = "Delivery Gate In";
                            }
                            if($sres->status_id=="1" && $sres->stop_type == "D"){
                                $data['delivery_datetime'] = $sres->createdon;
                                $stsname = "Delivery Done";
                            }
                            if($sres->status_id=="3" && $sres->stop_type == "D"){
                                $stsname = "Delivery Gate Out";
                            }
                            $locname = "";
                            if($sres->latitude != ""){
                                $locname = getLocationName($sres->latitude,$sres->longitude);
                            }
                            $data["stops"][] = array("datetime"=>$sres->createdon,"status"=>$stsname,"truck_no"=>$sres->register_number,"driver"=>$sres->name,"location"=>$locname);
                        }
                    }
                    $html = $this->load->view('settings/bulkepod',$data,true);
                    $chk = @$this->m_pdf->pdf->WriteHTML($html);
                    $chk = @$this->m_pdf->pdf->Output($epodpath,"F");
                    $body = $this->load->view('mail_forms/deliveredshipment', $data, true);
                    $unique_id = "RL".$order_id.".".date("Ymd").".".date("his");
                    $pagescnt = $this->m_pdf->pdf->pages;
                    $pages = count($pagescnt);
                    $jplData = array("country"=>substr($company_code,0,-2),"branch"=>substr($branch_code,2),"order_id"=>$order_id,"time"=>date("hi"),"unique_id"=>$unique_id,"date"=>date("d.m.Y"),"pages"=>$pages);
                    $jplFilePath = $this->saveJPF($jplData);
                    $storeftipjpl = $this->uploadKNFile($jplFilePath);
                    $storeftippdf = $this->uploadKNFile($epodpath);
                    $this->email->message($body);
                    $this->email->attach($epodpath);
                    if(!$this->email->send()){
                        foreach ( $this->email->get_debugger_messages() as $debugger_message ){
                          log_message("error",$debugger_message);
                        }
                        $this->email->clear_debugger_messages();
                    }
                    if($createdsource == '9'){
                        $this->sendpodgetouttoetrucknow($data,$ordid);
                    }
                }
                
            }
        }   
    }
    public  function sendpodgetouttoetrucknow($data,$ordid)
    {
        require 'vendor/autoload.php';
        $mpdf           = new \Mpdf\Mpdf([
            'mode'          => 'utf-8',
            'format'        => [190, 236],
            'margin_left'   => 0,
            'margin_right'  => 0,
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
        ]);
        $html = "";
        $name = "";
        if(!empty($data)){
            $etruckhtml = "";
            $getref = $this->common->gettblrowdata(array('order_id'=>$ordid,'reference_id'=>'DQ','status'=>'1'),"ref_value","tb_order_references",0,0);
            if(!empty($getref)){
                $data['order_id'] = $data['order']['order_id'] = $getref['ref_value'];
            }
            $etrucknowpath = "./assets/trippods/".$data['order_id'].".pdf";
            $etrunknowhtml = $this->load->view('settings/bulkepod',$data,true);
            $mpdf->WriteHTML($etrunknowhtml);
            $mpdf->Output($etrucknowpath, 'F');
            if($etrucknowpath != ""){
                $company_code = strtolower($this->session->userdata('company_code'));
                $count_code=substr($company_code, 0, 2);
                $folder_name = "outbound_".$count_code;
                $ftp = @ftp_connect(NZ_URL);
                if (false === $ftp) {
                }else{
                    $loggedIn = @ftp_login($ftp,NZ_USERNAME,NZ_PASSWORD);
                    if (true === $loggedIn) {
                        ftp_pasv($ftp, true) or die("Passive mode failed");
                        $name = basename($etrucknowpath);
                        $new_file = "./pub/".$folder_name."/".$name;
                        $d  = @ftp_nb_put($ftp, $new_file, $etrucknowpath,  FTP_BINARY, FTP_AUTORESUME);
                        while ($d == FTP_MOREDATA){
                          $d = ftp_nb_continue($ftp);
                      }
                  }
              }
          }
      }
  }

  public function saveJPF($jplData){
    $content = $this->load->view("jpl_template",$jplData,TRUE);
    $order_id = $jplData["order_id"];
    $file_path = "./assets/jpl_files/RL$order_id.jpl";
    $fp = fopen($file_path,"wb");
    fwrite($fp,$content);
    fclose($fp);
    return $file_path;
}

public function uploadKNFile($localfile){
    $remotefile = basename($localfile);
    /*log_message("error","$remotefile file uploaded to KN FTP");*/
    $ftp = ftp_connect(KN_FTP_URL) or die("Could not connect");
    ftp_login($ftp,KN_FTP_USER,KN_FTP_PASSWORD) or die("invalid username or password");
    $ret = @ftp_nb_put($ftp, "./pub/inbound/$remotefile", $localfile, FTP_BINARY, FTP_AUTORESUME);
    while (FTP_MOREDATA == $ret)
    {
        $ret = ftp_nb_continue($ftp);
    }
    ftp_close($ftp);
}

public function getdriverlatlongs($sim){
    $res = array("","");
    if($sim['trip_id'] > 0){
        $apiqry = $this->db->query("SELECT v.authkey,a.apiurl,a.requesttype,a.requestdata,a.response FROM tb_tracking_vendors v,tb_vendor_apis a WHERE v.id=a.vendor_id AND v.id='".$sim['vendor_id']."' AND a.apiname='Fetch Location' AND a.status=1 LIMIT 1");
        if($apiqry->num_rows()>0){
            $post['authkey'] = $apiqry->row()->authkey;
            $post['url'] = $apiqry->row()->apiurl;
            $post['reqtype'] = $apiqry->row()->requesttype;
            $post['data'] = array("phoneNumber"=>$sim['mobile']);
            $getdriverlocation = getdriverlocationbysms($post);
            /*log_message("error","resp sms".json_encode($getdriverlocation));*/
            if(!empty($getdriverlocation)){
                if($getdriverlocation['success'] == "true"){
                    if(!empty($getdriverlocation['data'])){
                        $latitude = $getdriverlocation['data']['location'][1];
                        $longitude = $getdriverlocation['data']['location'][0];
                        if($latitude != "" && $longitude != ""){
                            $res = array($latitude,$longitude);
                            $timestamp = $getdriverlocation['data']['timestamp'];
                            $getactual = getdatetimebytimezone(DFLT_TZ,$timestamp,$sim['curtz']);
                            $curdt = $getactual['datetime'];
                            $tripid = $sim['trip_id'];
                            $driver_id = $sim['driver_id'];
                            $vehicle_id = $sim['vehicle_id'];
                            $imei = $sim['mobile'];
                            $insdata = array("driver_id"=>$driver_id,"vehicle_id"=>$vehicle_id,"trip_id"=>$tripid,"latitude"=>$latitude,"longitude"=>$longitude,"accuracy"=>15.00,"speed"=>50,"battery"=>50.0,"bearing"=>50.00,"mobileimei"=>$imei,"timestamp"=>$curdt);
                            $ins = $this->common->insertTableData("tb_rtdrive_locations",$insdata);
                            /*update in vehicle table*/
                            if($vehicle_id != ""){
                                $vehwhr = array("id"=>$vehicle_id);
                                $vehset = array("imei"=>$imei,"latitude"=>$latitude,"longitude"=>$longitude,"speed"=>'50',"battery"=>'50',"bearing"=>'50',"receivedon"=>$curdt);
                                $upd = $this->db->where($vehwhr)->update("tb_trucks_data",$vehset);
                            }
                        }
                    }
                }
            }
        }
    }
    return $res;
}

public function generatesummary($tripid = null,$curtz = null)
{
    if ($tripid != "") {
        $dist =  $trip_type =  $totalemp  = $uid = 0;
        if($curtz == ""){ $curtz = date_default_timezone_get(); }
        $logdate = date('Y-m-d H:i:s');
        $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
        $curdt = $getactual['datetime'];
        $reg = "";
        $sql = $this->db->query("SELECT shift_id,vehicle_id,driver_id,convertToClientTZ(stime,'".$curtz."') as stime,convertToClientTZ(etime,'".$curtz."') as etime,etime as tetime, start_imei, end_imei, start_reading,end_reading,trip_type,plat,plng FROM tb_trips WHERE id=$tripid AND status=0 LIMIT 1");
        if ($sql->num_rows() > 0) {
            $data = array();
            $data['driver_name'] = $data['driver_num'] = "";
            $shift = $sql->row()->shift_id;
            $sql1 = $this->db->query("SELECT user_id,stime as setime,convertToClientTZ(startdate,'".$curtz."') as startdate, convertToClientTZ(enddate,'".$curtz."') as enddate,splace,eplace,elat,elng FROM tb_shifts WHERE id=".$shift." LIMIT 1");
            $data["trip"] = $sql1->row();
            $emp1 = $this->db->query("select e.name,convertToClientTZ(te.stime,'".$curtz."') as in_time,te.status as emp_status,te.driver_late,te.emp_late,te.absent_reason,m.material,e.shipment_volume,e.shipment_weight,e.order_id from tb_employee e,tb_trip_employee te,tb_materials m WHERE te.employee_id=e.id AND m.id=e.material_id AND te.trip_id=".$tripid);
            $emp = $this->db->query("SELECT IFNULL(SUM(IF(e.status='1',1,0)),0) AS attcnt, count(e.employee_id) AS totcnt FROM  tb_trip_employee e WHERE e.trip_id=" . $tripid);
            $start          = $sql->row()->stime;
            $end            = $sql->row()->etime;
            $imei           = $sql->row()->start_imei;
            $eimei          = $sql->row()->end_imei;
            $driverid       = $sql->row()->driver_id;
            $vehicleid      = $sql->row()->vehicle_id;
            $stime          = $sql1->row()->startdate;
            $etime          = $sql1->row()->enddate;
            $uid = $sql1->row()->user_id;
            $odometer_start = trim($sql->row()->start_reading);
            $odometer_end   = trim($sql->row()->end_reading);
            $trip_type      = $sql->row()->trip_type;
            $vendor_id = 0;
            $dlat = $dlng = "";
            $trucks_query = $this->db->query("select truck_capacity,register_number,vendor_id,truck_weight,truck_volume,latitude,longitude from tb_trucks_data WHERE id=$vehicleid LIMIT 1");
            $cab_capacity = $truck_weight = $truck_volume = 0;
            if($trucks_query->num_rows() > 0){
                $cab_capacity = $trucks_query->row()->truck_capacity;
                $reg = $trucks_query->row()->register_number;
                $vendor_id = $trucks_query->row()->vendor_id;
                $truck_weight = $trucks_query->row()->truck_weight;
                $truck_volume = $trucks_query->row()->truck_volume;
                $dlat = $trucks_query->row()->latitude;
                $dlng = $trucks_query->row()->longitude;
            }
            if($dlat == ""){
                $dlat = $sql->row()->plat;
                $dlng = $sql->row()->plng;
            }
            if($dlat == $sql->row()->plat){
                $dlat = $sql1->row()->elat;
                $dlng = $sql1->row()->elng;
            }
            /*update end lat lng in trips*/
            $whrtrip = array("id"=>$tripid);
            $settrip = array("dlat"=>$dlat,"dlng"=>$dlng);
            $upd = $this->db->where($whrtrip)->update("tb_trips",$settrip);
            $vtripinfo = array("plat"=>$sql->row()->plat,"plng"=>$sql->row()->plng,"dlat"=>$dlat,"dlng"=>$dlng);
            $data['tripinfo'] = (Object)$vtripinfo;
            if($cab_capacity == ""){
                $cab_capacity = 0;
            }
            $regg = $this->db->query("select name,contact_num,vendor_id from tb_truck_drivers WHERE id=$driverid LIMIT 1");
            if ($regg->num_rows() > 0) {
                $data['driver_name'] = $regg->row()->name;
                $data['driver_num']  = $regg->row()->contact_num;
                $vnid = $regg->row()->vendor_id;
                if($vendor_id == 0 && $vnid != 0){
                    $vendor_id  = $vnid;
                }
            }
            $sql2 = $this->db->query("select latitude,longitude,convertToClientTZ(`timestamp`,'".$curtz."') as timestamp FROM tb_rtdrive_locations WHERE mobileimei='" . $imei . "' and timestamp<='$end' and timestamp>='$start' order by timestamp asc");
            $flag = 0;
            $lat = $lng = 0;
            $wflag = 0;
            $dist = 0;
            if ($sql2->num_rows() > 0) {
                foreach ($sql2->result() as $row) {
                    if ($flag == 0) {
                        $flag = 1;
                    } else {
                        $a = calculateDistance12($lat, $lng, $row->latitude, $row->longitude);
                        $dist = $dist + $a;
                    }
                    $lat = $row->latitude;
                    $lng = $row->longitude;
                }
            }
            $attendemp = 0;
            $totalemp = getempcount($tripid);
            if ($emp->num_rows() > 0) {
                $attendemp = $emp->row()->attcnt;
            }
            $data["noofemp"] = $totalemp;
            $driver_late = 0.00;
            $setime = date("Y-m-d H:i:s", strtotime($sql1->row()->setime));
            $tetime = date("Y-m-d H:i:s", strtotime(date("H:i:s", strtotime($sql->row()->tetime))));
            $to_time = strtotime($setime);
            $from_time = strtotime($tetime);
            if ($from_time > $to_time) {
                $driver_late = round(abs($from_time - $to_time) / 60, 2);
            }
            $arr = array('trip_id' => $tripid, 'vehicle_id' => $vehicleid, 'driver_id' => $driverid, 'vendor_id' => $vendor_id, 'trip_distance' => $dist, 'no_of_emp' => $totalemp, 'attended_emp' => $attendemp, 'start_imei' => $imei, 'end_imei' => $eimei, 'start_time' => $stime, 'end_time' => $etime, 'user_id' => $uid, 'cab_capacity' => $cab_capacity, 'trip_status' => 1, 'trip_type' => $trip_type, 'ship_delay' => $driver_late, 'createdon' => $curdt);
            $chktrip = $this->db->select("id")->get_where("tb_trip_summary", array("trip_id" => $tripid), 1, 0);
            if ($chktrip->num_rows() == 0) {
                $res = $this->db->insert("tb_trip_summary", $arr);
            }
            $data["trip_id"] = $tripid;
            $shifttime = 0;
            if ($trip_type != 2) {
                $shti = $sql1->row()->startdate;
                $shifttime = date('H:i A', strtotime($shti));
            } else {
                $shifttime = "Empty";
            }
            $data["driver_late"]  = $driver_late;
            $data["empshifttime"] = $shifttime;
            $data["distance"]     = $dist;
            $data["stime"]        = $stime;
            $data["etime"]        = $etime;
            $data["simei"]        = $imei;
            $data["eimei"]        = $eimei;
            if ($odometer_end == "") {$odometer_end = 0;}
            if ($odometer_start == "") {$odometer_start = 0;}
            $data["odometer"]        = ($odometer_end - $odometer_start);
            $data["page_title"]      = "Trip Report";
            $data["capacity"]        = $cab_capacity;
            $data["employees"]       = $emp1;
            $data["trip_type"]       = $trip_type;
            $data["register_number"] = $reg;
            $data["truck_weight"]    = $truck_weight;
            $data["truck_volume"]    = $truck_volume;
            $data["ep"] = "";
            $getusermail = $this->db->select("name,emailid,cc_mails,address")->get_where("tb_users", array("id" => $uid, "emailid !=" => ""), 1, 0);
            if ($getusermail->num_rows() > 0) {
                $receivemail = $getusermail->row()->emailid;
                $receivename = $getusermail->row()->name;
                $receivecc   = $getusermail->row()->cc_mails;
                $data["ep"] = $getusermail->row()->address;
                $insertdata = @array('trip_id' => $tripid, 'shift_id' => $shift, 'splace' => $data["trip"]->splace, 'eplace' => $data["trip"]->eplace, 'stime' => $stime, 'etime' => $etime, 'capacity' => $cab_capacity, 'simei' => $data['simei'], 'eimei' => $data['eimei'], 'totcnt' => $totalemp, 'attcnt' => $attendemp, 'distance' => $data['distance'], 'odometer' => $data['odometer'], 'driver_late' => $data['driver_late'], 'receivemail' => $receivemail, 'receivename' => $receivename, 'user_id' => $uid, 'createdon' => $curdt);
                $repotmaildata = $this->db->insert('tb_trips_mails',$insertdata);
                $this->load->library('email');
                $this->email->to($receivemail, $receivename);
                if ($receivecc != "") {
                    $this->email->cc($receivecc);
                }
                $this->email->from('etrucknow@kuehne-nagel.com', 'eTrucknow');
                if ($trip_type == 1) {
                    $this->email->subject('eTrucknow: Return Trip Status');
                } else if ($trip_type == 2) {
                    $this->email->subject('eTrucknow: Empty Trip Status');
                } else {
                    $this->email->subject('eTrucknow: Trip Status');
                }
                $this->email->set_mailtype('html');
                $body = $this->load->view('mail_forms/basicreport', $data, true);
                $this->email->message($body);
                $sendmail = $this->email->send();
            }
            /*echo json_encode(array("status" => 1, "data" => $data));*/
        }
    }
}

public function massrefnum(){
    $data['page_title'] = "Mass RefNum Update";
    $data['sub_title'] = "Order Status";
    $uid = $this->session->userdata("user_id");
    $mainqry = "SELECT o.id,o.order_id,o.trip_id FROM tb_orders o WHERE o.user_id=$uid AND o.trip_id!=0 AND o.trip_sts=0 GROUP BY o.order_id ORDER BY o.trip_id DESC";
    $custord = $this->db->query($mainqry);
    $data['tripsdata'] = array();
    $data['ravailable'] = $ravailable = $pos = array();
    if($custord->num_rows()>0){
        foreach($custord->result() as $res){
            $pos[] = $res->id;
            $refs = array();
            $refqry = $this->db->query("SELECT r.reference_id,GROUP_CONCAT(r.ref_value) as refvalue FROM tb_order_references r WHERE r.order_id='$res->id' AND r.status=1 GROUP BY r.reference_id");
            foreach($refqry->result() as $rf){
                $refid = $rf->reference_id;
                if(!in_array($refid, $ravailable)){
                    $ravailable[] = $refid;
                }
                $refs[$refid] = $rf->refvalue;
            }
            $data['tripsdata'][] = array('ordid'=>$res->id,'trackingid'=>$res->order_id,"refdata"=>$refs);
        }
    }
    $mainqry = "SELECT o.id,o.order_id FROM tb_orders o WHERE o.user_id=$uid AND o.trip_id=0 AND o.trip_sts=0 AND o.status!=0";
    $custord1 = $this->db->query($mainqry);
    if($custord1->num_rows()>0){
        foreach($custord1->result() as $res1){
            $refs = array();
            $refqry = $this->db->query("SELECT r.reference_id,GROUP_CONCAT(r.ref_value) as refvalue FROM tb_order_references r WHERE r.order_id='$res1->id' AND r.status=1 AND r.ref_value != '' GROUP BY r.reference_id");
            foreach($refqry->result() as $rf1){
                $refid = $rf1->reference_id;
                if(!in_array($refid, $ravailable)){
                    $ravailable[] = $refid;
                }
                $refs[$refid] = $rf1->refvalue;
            }
            $data['tripsdata'][] = array('ordid'=>$res1->id,'trackingid'=>$res1->order_id,"refdata"=>$refs);
        }
    }
    $data['ravailable'] = $ravailable;
    $this->settemplate->dashboard('admin/massrefnum',$data);
}

public function saverefnum(){
    $post = $this->input->post();
    $order_id = $this->input->post("order_id",true);
    $availrefs = $this->input->post("availrefs",true);
    $curtz = $this->session->userdata("usr_tzone")['timezone'];
    $logdate = date('Y-m-d H:i:s');
    $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
    $curdt = $getactual['datetime'];
    if(count($order_id)>0){
        for($i=0;$i<count($order_id);$i++){
            if($availrefs != ""){
                $ord_id = $order_id[$i];
                $avref = explode(",", $availrefs);
                foreach($avref as $ar){
                    $chk1 = isset($post[$ar."_".$order_id[$i]]) ? $post[$ar."_".$order_id[$i]] : array();
                    if(count($chk1)>0){
                        foreach($chk1 as $chk){
                            /*log_message("error","avail ord ref".$ord_id." - ".$ar."- ".$chk);*/
                            $multichk = explode(",", $chk);
                            foreach($multichk as $mc){
                                if($mc != ""){
                                    $qrychk = $this->db->select("id")->get_where("tb_order_references",array("order_id"=>$ord_id,"reference_id"=>$ar,"ref_value"=>$mc),1,0);
                                    if($qrychk->num_rows() == 0){
                                        $insarr = array("order_id"=>$ord_id,"reference_id"=>$ar,"ref_value"=>$mc,"status"=>1,"createdon"=>$curdt,"updatedon"=>$curdt);
                                        $ins = $this->db->insert("tb_order_references",$insarr);
                                        $ins = $this->db->insert("tb_order_references_history",$insarr);
                                    }
                                }else{
                                    $qrychk = $this->db->select("id")->get_where("tb_order_references",array("order_id"=>$ord_id,"reference_id"=>$ar),1,0);
                                    if($qrychk->num_rows() > 0){
                                        $updarr = array("order_id"=>$ord_id,"reference_id"=>$ar);
                                        $upd = $this->db->delete("tb_order_references",$updarr);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->session->set_flashdata('success_msg',"Updated Successfully");
    }else{
        $this->session->set_flashdata('error_msg',"Not valid input!");
    }
    redirect("admin/massrefnum");
}

function getImage($path) {
   /*$file = 'folder/directory/file.html';*/
   $extension = pathinfo($path, PATHINFO_EXTENSION);
   switch($extension) {
      case 'png':
      /*$img = imagecreatefrompng($path);*/
      $img = imagecreatefromstring(file_get_contents($path));

      break;
      case 'jpg':
      $img = imagecreatefromjpeg($path);
      break;
      case 'jpeg':
      $img = imagecreatefromjpeg($path);
      break;
      case 'gif':
      $img = imagecreatefromgif($path);
      break;
      case 'bmp':
      $img = imagecreatefrombmp($path);
      break;
      default:
      $img = null; 
  }
  return $img;
}

function downloadexcel() {
    ini_set("memory_limit","256M");
    $data['page_title']='Completed';
    $data['sub_title']='Completed Bookings';
    $data['orders'] = array();
    $inputdt = $this->input->post();
    if(isset($inputdt['excel_export'])){
        $ords = array();
        for($i=0;$i<count($inputdt['excel_export']);$i++){
            $ords[] = $inputdt['excel_export'][$i];
        }
        if(!empty($ords)){
            $whr = "AND o.id IN(".implode(',', $ords).")";
            $d = $s= $t = 0;
            $cid = $this->session->userdata("user_id");
            $curtz = $this->session->userdata("usr_tzone")['timezone'];
            $mainqry = "SELECT o.id,o.order_id,o.pickup_company,o.pickup_country,o.pickup_city,o.pickup_pincode,o.pickup_address1,o.delivery_company,o.delivery_country,o.delivery_city,o.delivery_pincode,o.delivery_address1,convertToClientTZ(o.pickup_datetime,'".$curtz."') as pickup_datetime, o.shift_id, convertToClientTZ(t.etime,'".$curtz."') as act_etime,c.phone as custid FROM tb_trips t,tb_orders o,tb_customers c WHERE t.id=o.trip_id AND t.status=0 AND o.user_id=$cid AND o.trip_sts=1 $whr AND o.customer_id=c.id GROUP BY o.order_id ORDER BY o.trip_id DESC";
            $result = $this->db->query($mainqry);
            $totcnt = $result->num_rows();
            log_message("error","totcnt ".json_encode($totcnt));
            if($totcnt > 0 && $totcnt<=50){
                foreach($result->result() as $res){
                    $epods['pod'] = $epods['signature'] = $epods['others'] = array();
                    $pods = $this->db->query("SELECT ts.id,ts.latitude,ts.longitude,ts.stop_id,ts.stop_type,ts.doc_type,dt.type_name,ts.createdby,convertToClientTZ(ts.createdon,'".$curtz."') as createdon,ts.imgpath,e.stopname from tb_pod_uploads ts LEFT JOIN tb_document_types dt ON dt.id=ts.doc_type LEFT JOIN tb_shiporder_stops e ON e.id = ts.stop_id WHERE ts.order_id = '".$res->id."' AND ts.status=1 GROUP BY ts.id ORDER BY ts.createdon ASC");
                    if($pods->num_rows()>0){
                        foreach($pods->result() as $pres){
                            if($pres->doc_type == '3'){
                                $epods['pod'][] = array("id"=>$pres->id,"stopname"=>$pres->stopname,"createdon"=>$pres->createdon,"type_name"=>$pres->type_name,"imgpath"=>$pres->imgpath,"latitude"=>$pres->latitude,"longitude"=>$pres->longitude);
                            }
                            if($pres->doc_type == '1'){
                                $epods['signature'][] = array("id"=>$pres->id,"stopname"=>$pres->stopname,"createdon"=>$pres->createdon,"type_name"=>$pres->type_name,"imgpath"=>$pres->imgpath,"latitude"=>$pres->latitude,"longitude"=>$pres->longitude);
                            }
                            if($pres->doc_type == '2'){ 
                                $epods['others'][] = array("id"=>$pres->id,"stopname"=>$pres->stopname,"createdon"=>$pres->createdon,"type_name"=>$pres->type_name,"imgpath"=>$pres->imgpath,"latitude"=>$pres->latitude,"longitude"=>$pres->longitude);
                            } 
                        }  
                    }

                    $data['orders'][] = array("id"=>$res->id,"order_id"=>$res->order_id,"ref_num"=>getQRrefsbyorderId($res->order_id),"custid"=>$res->custid,"pickup_company"=>$res->pickup_company,"pickup_country"=>$res->pickup_country,"pickup_city"=>$res->pickup_city,"pickup_pincode"=>$res->pickup_pincode,"pickup_address"=>$res->pickup_address1,"delivery_company"=>$res->delivery_company,"delivery_country"=>$res->delivery_country,"delivery_city"=>$res->delivery_city,"delivery_pincode"=>$res->delivery_pincode,"delivery_address"=>$res->delivery_address1,"pickup_datetime"=>$res->pickup_datetime,"delivery_datetime"=>$res->act_etime,"epods"=>$epods);
                }
                $styleArray = array(
                    'font'  => array(
                        'bold'  => true,
                        'color' => array('rgb' => 'FFFFFF'),
                        'size'  => 10,
                        'name'  => 'Verdana'
                    ));
                $objPHPExcel = new PHPExcel();
                $objPHPExcel->setActiveSheetIndex(0);
                $objPHPExcel->getActiveSheet()
                ->getStyle('A1:M1')
                ->applyFromArray(
                    array(
                        'fill' => array(
                            'type' => PHPExcel_Style_Fill::FILL_SOLID,
                            'color' => array('rgb' => '0c3b81')
                        )
                    )
                );
                $objPHPExcel->getActiveSheet()->SetCellValue('A1', 'ID');
                $objPHPExcel->getActiveSheet()->getStyle('A1')->applyFromArray($styleArray);
                $objPHPExcel->getActiveSheet()->SetCellValue('B1', 'CUSTOMER ID');
                $objPHPExcel->getActiveSheet()->getStyle('B1')->applyFromArray($styleArray);
                $objPHPExcel->getActiveSheet()->SetCellValue('C1', 'BOOKING ID');
                $objPHPExcel->getActiveSheet()->getStyle('C1')->applyFromArray($styleArray);
                $objPHPExcel->getActiveSheet()->SetCellValue('D1', 'Ref Num');
                $objPHPExcel->getActiveSheet()->getStyle('D1')->applyFromArray($styleArray);
                $objPHPExcel->getActiveSheet()->SetCellValue('E1', 'Pickup Location'); 
                $objPHPExcel->getActiveSheet()->getStyle('E1')->applyFromArray($styleArray);      
                $objPHPExcel->getActiveSheet()->SetCellValue('F1', 'Pickup Address');   
                $objPHPExcel->getActiveSheet()->getStyle('F1')->applyFromArray($styleArray);    
                $objPHPExcel->getActiveSheet()->SetCellValue('G1', 'Pickup Date');  
                $objPHPExcel->getActiveSheet()->getStyle('G1')->applyFromArray($styleArray);     
                $objPHPExcel->getActiveSheet()->SetCellValue('H1', 'Delivery Location'); 
                $objPHPExcel->getActiveSheet()->getStyle('H1')->applyFromArray($styleArray);      
                $objPHPExcel->getActiveSheet()->SetCellValue('I1', 'Delivery Address');  
                $objPHPExcel->getActiveSheet()->getStyle('I1')->applyFromArray($styleArray);     
                $objPHPExcel->getActiveSheet()->SetCellValue('J1', 'Delivered Date');   
                $objPHPExcel->getActiveSheet()->getStyle('J1')->applyFromArray($styleArray);    
                $objPHPExcel->getActiveSheet()->SetCellValue('K1', 'ePOD');       
                $objPHPExcel->getActiveSheet()->getStyle('K1')->applyFromArray($styleArray);
                $objPHPExcel->getActiveSheet()->SetCellValue('L1', 'Signature');   
                $objPHPExcel->getActiveSheet()->getStyle('L1')->applyFromArray($styleArray);    
                $objPHPExcel->getActiveSheet()->SetCellValue('M1', 'Others');  
                $objPHPExcel->getActiveSheet()->getStyle('M1')->applyFromArray($styleArray);
                $rowCount = 2;
                if(count($data['orders'])>0){
                    $i = 1;
                    foreach($data['orders'] as $s){
                        $objPHPExcel->getActiveSheet()->SetCellValue('A' . $rowCount, $i);
                        $objPHPExcel->getActiveSheet()->SetCellValue('B' . $rowCount, $s['custid']);
                        $objPHPExcel->getActiveSheet()->SetCellValue('C' . $rowCount, $s['order_id']);
                        $objPHPExcel->getActiveSheet()->SetCellValue('D' . $rowCount, $s['ref_num']);
                        $objPHPExcel->getActiveSheet()->SetCellValue('E' . $rowCount, $s['pickup_city']);
                        $objPHPExcel->getActiveSheet()->SetCellValue('F' . $rowCount, $s['pickup_company'].", ".$s['pickup_country'].", ".$s['pickup_city'].", ".$s['pickup_pincode'].", ".$s['pickup_address']);
                        $objPHPExcel->getActiveSheet()->SetCellValue('G' . $rowCount, $s['pickup_datetime']);
                        $objPHPExcel->getActiveSheet()->SetCellValue('H' . $rowCount, $s['delivery_city']);
                        $objPHPExcel->getActiveSheet()->SetCellValue('I' . $rowCount, $s['delivery_company'].", ".$s['delivery_country'].", ".$s['delivery_city'].", ".$s['delivery_pincode'].", ".$s['delivery_address']);
                        $objPHPExcel->getActiveSheet()->SetCellValue('J' . $rowCount, $s['delivery_datetime']);
                        if(count($s['epods']['pod'])>0){ 
                            $imageTmp = "";
                            $si = $s['epods']['pod'][0];
                            log_message("error","si-2618 ".json_encode($si));
                         /*   $file = base_url().'assets/poduploads/'.$si['imgpath'];*/
                            $file = checkimageintmsorknlmv($si['imgpath']);
                        /*    if(file_exists("assets/poduploads/".$si['imgpath'])){*/
                          /*  if(file_exists($file)){*/
                               if($file != ""){
                                $imageTmp = @$this->getImage($file);
                               }
                            if($imageTmp != ""){
                                $objDrawing = new PHPExcel_Worksheet_MemoryDrawing();
                                $objDrawing->setName("Stop: ".$si['stopname']);
                                $objDrawing->setDescription("Document: ".$si['type_name'].", Date: ".$si['createdon'].", Latitude: ".$si['latitude'].", Longitude: ".$si['longitude']);
                                $objDrawing->setImageResource($imageTmp);
                                $objDrawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_JPEG);
                                $objDrawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                                $objDrawing->setHeight(80);
                                $objDrawing->setCoordinates('K' . $rowCount);
                                $objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
                                $url = base_url()."sheet/epod/".knencrypt($si['id']);
                                $objPHPExcel->getActiveSheet()->getCell('K' . $rowCount)->getHyperlink()->setUrl($url)->setTooltip("Stop: ".$si['stopname'].", Document: ".$si['type_name'].", Date: ".$si['createdon'].", Latitude: ".$si['latitude'].", Longitude: ".$si['longitude']);
                            }else{
                                $objPHPExcel->getActiveSheet()->SetCellValue('K' . $rowCount, "");
                            }
                        }else{
                            $objPHPExcel->getActiveSheet()->SetCellValue('K' . $rowCount, "");
                        }
                        if(count($s['epods']['signature'])>0){ 
                            $imageTmp = "";
                            $si1 = $s['epods']['signature'][0];
                          /*  $file = base_url().'assets/poduploads/'.$si1['imgpath'];*/
                            $file = checkimageintmsorknlmv($si1['imgpath']);
                            if($file != ""){
                                $imageTmp = $this->getImage($file);
                            }
                            if($imageTmp != ""){
                                $objDrawing = new PHPExcel_Worksheet_MemoryDrawing();
                                $objDrawing->setName("Stop: ".$si1['stopname']);
                                $objDrawing->setDescription("Document: ".$si1['type_name'].", Date: ".$si1['createdon'].", Latitude: ".$si1['latitude'].", Longitude: ".$si1['longitude']);
                                $objDrawing->setImageResource($imageTmp);
                                $objDrawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_JPEG);
                                $objDrawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                                $objDrawing->setHeight(80);
                                $objDrawing->setCoordinates('L' . $rowCount);
                                $objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
                                $url = base_url()."sheet/epod/".knencrypt($si1['id']);
                                $objPHPExcel->getActiveSheet()->getCell('L' . $rowCount)->getHyperlink()->setUrl($url)->setTooltip("Stop: ".$si1['stopname'].", Document: ".$si1['type_name'].", Date: ".$si1['createdon'].", Latitude: ".$si1['latitude'].", Longitude: ".$si1['longitude']);
                            }else{
                                $objPHPExcel->getActiveSheet()->SetCellValue('L' . $rowCount, "");
                            }
                        }else{
                            $objPHPExcel->getActiveSheet()->SetCellValue('L' . $rowCount, "");
                        }

                        if(count($s['epods']['others'])>0){ 
                            $imageTmp = "";
                            $si2 = $s['epods']['others'][0];
                          /*  $file = base_url().'assets/poduploads/'.$si2['imgpath'];*/
                            $file = checkimageintmsorknlmv($si2['imgpath']);
                            if($file != ""){
                                $imageTmp = $this->getImage($file);
                            }
                            if($imageTmp != ""){
                                $objDrawing = new PHPExcel_Worksheet_MemoryDrawing();
                                $objDrawing->setName("Stop: ".$si2['stopname']);
                                $objDrawing->setDescription("Document: ".$si2['type_name'].", Date: ".$si2['createdon'].", Latitude: ".$si2['latitude'].", Longitude: ".$si2['longitude']);
                                $objDrawing->setImageResource($imageTmp);
                                $objDrawing->setRenderingFunction(PHPExcel_Worksheet_MemoryDrawing::RENDERING_JPEG);
                                $objDrawing->setMimeType(PHPExcel_Worksheet_MemoryDrawing::MIMETYPE_DEFAULT);
                                $objDrawing->setHeight(80);
                                $objDrawing->setCoordinates('M' . $rowCount);
                                $objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
                                $url = base_url()."sheet/epod/".knencrypt($si2['id']);
                                $objPHPExcel->getActiveSheet()->getCell('M' . $rowCount)->getHyperlink()->setUrl($url)->setTooltip("Stop: ".$si2['stopname'].", Document: ".$si2['type_name'].", Date: ".$si2['createdon'].", Latitude: ".$si2['latitude'].", Longitude: ".$si2['longitude']);
                            }else{
                                $objPHPExcel->getActiveSheet()->SetCellValue('M' . $rowCount, "");
                            }
                        }else{
                            $objPHPExcel->getActiveSheet()->SetCellValue('M' . $rowCount, "");
                        }
                        $rowCount++;
                        $i++;
                    }
                }
                $fileName = 'ORDERS-'.date("Ymdhis").'.xlsx';
                header('Content-Type: application/vnd.ms-excel'); //mime type
                header('Content-Disposition: attachment;filename="'.$fileName.'"'); //tell browser what's the file name
                header('Cache-Control: max-age=0'); //no cache
                $objWriter = new PHPExcel_Writer_Excel2007($objPHPExcel);
                $objWriter->save('php://output');
            }else{
                $this->session->set_flashdata('error_msg',"Max. records should not be more than 50, Please search for required data and download");
                redirect("admin/doneorders");
            }
        }else{
            redirect("admin/doneorders");
        }
    }else{
        redirect("admin/doneorders");
    }
}

    //new code by team for ajax loading dropdowns.
public function getrefinfo($type){
    $btype = $this->session->userdata('business_type');
    $branch_user = isset($_POST["branch_user"]) ? $_POST["branch_user"] : "-1";
    if(!in_array($btype, ['Country Admin'])){
        $cid = "(".$this->session->userdata("user_id").")";
    }else{
        $admids = $this->session->userdata('country_user_ids');
        if($branch_user != "-1"){
            $cid   = "($branch_user)";
        }else if(count($admids)>0){
            $users  = implode(",", $admids);
            $cid   = "($users)";
        }else{
            $cid   = "(-1)";
        }
    }
    $searchTerm = isset($_POST['searchTerm']) ? $_POST['searchTerm'] : "";
    $whr = "";
    if($searchTerm != ""){
        $whr = "r.ref_value LIKE '%".$searchTerm."%' AND ";
    }
    $data = array();
    if($type=='pending'){
        if($this->session->userdata('company_code') == 'NZKN'){
            $whr .= ' o.order_status != "READY" AND ';
        }
        $orderref = $this->db->query("SELECT r.id,r.reference_id,r.ref_value FROM tb_order_references r,tb_orders o WHERE ".$whr." r.order_id=o.id AND o.user_id IN $cid AND o.trip_id=0 AND o.trip_sts=0 AND r.status=1 GROUP BY r.id");
    }else if($type=='ready'){
        $whr .= ' o.order_status ="READY" AND';
        $orderref = $this->db->query("SELECT r.id,r.reference_id,r.ref_value FROM tb_order_references r,tb_orders o WHERE $whr r.order_id=o.id AND o.user_id IN $cid AND o.trip_id=0 AND o.trip_sts=0 AND r.status=1 GROUP BY r.id");
    }else if($type=='active'){
        $orderref = $this->db->query("SELECT r.id,r.reference_id,r.ref_value FROM tb_order_references r,tb_orders o WHERE ".$whr." r.order_id=o.id AND o.trip_id!=0 AND o.trip_sts=0 AND o.user_id in $cid AND r.status=1 GROUP BY r.id");
    }else if($type=='done'){
        $orderref = $this->db->query("SELECT r.id,r.reference_id,r.ref_value FROM tb_order_references r,tb_orders o WHERE ".$whr." r.order_id=o.id AND o.user_id in $cid AND o.trip_sts=1 AND r.status=1 GROUP BY r.id ORDER BY r.updatedon");
    }
    if($orderref->num_rows() >0){
        foreach($orderref->result_array() as $ores){ 
            $data[] = array("id"=>$ores['ref_value'], "text"=>$ores['ref_value']);
        }
    }
    echo json_encode($data);
}
public function getbookinfo($type){
    $cid='';
    $btype = $this->session->userdata('business_type');
    $branch_user = isset($_POST["branch_user"]) ? $_POST["branch_user"] : "-1";
    if(!in_array($btype, ['Country Admin'])){
        $cid = "(".$this->session->userdata("user_id").")";
    }else{
        $admids = $this->session->userdata('country_user_ids');
        if($branch_user != "-1"){
            $cid   = "($branch_user)";
        }else if(count($admids)>0){
            $users  = implode(",", $admids);
            $cid   = "($users)";
        }else{
            $cid   = "(-1)";
        }
    }
    $data = array();
    $searchTerm = isset($_POST['searchTerm']) ? $_POST['searchTerm'] : "";
    $whr = "";
    if($searchTerm != ""){
        $whr = "o.order_id LIKE '%".$searchTerm."%' AND ";
    }
    if($type=='active'){
        $orderref = $this->db->query("SELECT o.order_id FROM tb_orders o WHERE $whr o.status!=0 AND o.user_id IN $cid AND o.trip_id!=0 AND o.trip_sts=0 GROUP BY o.order_id");
    }else if($type=='pending'){
        if($this->session->userdata('company_code') == 'NZKN'){
            $whr .= ' o.order_status != "READY" AND';
        }
        $orderref = $this->db->query("SELECT o.order_id FROM tb_orders o WHERE $whr o.status!=0 AND o.user_id IN $cid AND o.trip_id=0 AND o.trip_sts=0 GROUP BY o.order_id");
    }else if($type=='ready'){
        $whr .= ' o.order_status = "READY" AND ';
        $orderref = $this->db->query("SELECT o.order_id FROM tb_orders o WHERE $whr o.status!=0 AND o.user_id IN $cid AND o.trip_id=0 AND o.trip_sts=0 GROUP BY o.order_id");
    }else if($type=='done'){
        $orderref = $this->db->query("SELECT o.id,o.order_id FROM tb_orders o WHERE $whr o.status!=0 AND o.user_id IN $cid AND o.trip_sts=1 GROUP BY o.order_id ORDER BY o.updatedon DESC");
    }
    if($orderref->num_rows() >0){ 
        foreach($orderref->result_array() as $ores){ 
            $data[] = array("id"=>$ores['order_id'], "text"=>$ores['order_id']);
        }
    }
    echo json_encode($data);
}
public function getordertypeinfo(){
    $data = array();
    $btype = $this->session->userdata('business_type');
    $company_code = $this->session->userdata('company_code');
    if($btype != "Country Admin"){
        $ordertypes = $this->db->select("type_name,ordtype_code")->group_by("type_name")->get_where("tb_order_types",array("company_code"=>$company_code,"status"=>1));
    }else{
        $ordertypes = $this->db->select("type_name,ordtype_code")->group_by("type_name")->get_where("tb_order_types",array("status"=>1));
    } 
    if($ordertypes->num_rows() >0){ 
        foreach($ordertypes->result_array() as $ores){ 
         $data[] = array("id"=>$ores['ordtype_code'], "text"=>$ores['type_name']);
     }
 }else{
    $ordertypes = $this->db->select("type_name,ordtype_code")->group_by("type_name")->get_where("tb_order_types",array("status"=>1));
    foreach($ordertypes->result_array() as $ores){ 
     $data[] = array("id"=>$ores['ordtype_code'], "text"=>$ores['type_name']);
 }
}
echo json_encode($data);
}
public function sendnotify($action,$orderid,$attach=null){
    if(!empty($orderid)){
        $this->load->library('notifytrigger');
        if($attach != null){
            $info['attachment'] = $attach;
        }
        $info['page_title'] = 'Booking Notification';
        $info['subject'] = 'Booking Notification';
        $info['order_id'] = $orderid;
        $info['action'] = $action;
        $orderinfo = $this->common->gettblrowdata(array('id'=>$orderid),'order_id','tb_orders',0,0);
        if(count($orderinfo)>0){
            $info['orderid'] = $orderinfo['order_id'];
            $info['cargos'] = $this->common->gettbldata(array('order_id'=>$orderid),'quantity_type,quantity','tb_order_cargodetails',0,0);
            $info['body'] = $this->load->view('mail_forms/notifytrigger/'.$action,$info,true);
            $this->notifytrigger->sendordernotify($info);
        } else {
            log_message('error','No Order data for id : '.$orderid);
        }
    } else {
        log_message('error','Empty Order ');
    }
}

}
