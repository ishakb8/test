<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Orders extends CI_Controller
{

    public function __Construct()
    {
        parent::__Construct();
        $this->load->library('session');
        if ($this->session->userdata('user_id') == '') {
            redirect('login');
        }
        $this->load->library('form_validation');
        $this->load->library('Ratemanagement');
        $this->load->model('Order');
        $this->load->model('common');
        $this->load->library('email');
        $this->load->library("statusintigration");
        $this->load->library("etrucknowquote");
    }

    public function index($id =null)
    {
        if($id != ""){
            $this->orderslist($id);
        }else{
            $this->orderslist();
        }
    }
   

    public function orderslist($id = null){
        $data['page_title'] = $this->lang->line('order_list');
        $data['sub_title']  = $this->lang->line('menu_orders');
        $order = $country_userids = array();
        $userid = $this->session->userdata("user_id");
        $custid = $this->session->userdata("cust_id");
        $country_userids = $this->session->userdata("country_user_ids");
        $whr = $searchids = array();
        $post = array();
        if($id != ""){
            $searchids = array($id);
            $post['bookingid'] = array();
            $getbooking_id = $this->common->gettblrowdata(array('id'=>$id),"order_id","tb_orders",0,0);
            if(!empty($getbooking_id)){
                $post['bookingid'] = array($getbooking_id['order_id']);
            }
            $data['getbookingid'] = $post['bookingid'];
        }
        $status_search ="";
        if (isset($_POST['searchsubmit']) && $_POST['searchsubmit'] == "Search") {
            $post = $_POST;
        }
        if (!empty($post)) {
            $whr = $this->searchorders($post);
            $order_status = isset($post['status']) ? $post['status'] :"";
            $ad_orderstatus = isset($post['order_status']) ? $post['order_status'] :"";
            $status_search = $order_status;
            if($status_search == ""){
               $status_search = $ad_orderstatus;
           }
           $searchids = isset($post['bookingid']) ? $post['bookingid'] : array();
           $company_code = $this->session->userdata('company_code');
           $branch_code = $this->session->userdata('branch_code');
           $subcusts = array();
           $role_id = $this->session->userdata('user_role_id');
           if($role_id == "4"){
                if($this->session->userdata('sub_cust')){
                    $subcusts = $this->session->userdata('sub_cust');
                    if(!empty($subcusts)){
                      array_push($subcusts, $custid);
                    }
                }
           }
      $orderdata = $this->Order->getorderdata($userid, $searchids, $status_search, $custid, $country_userids, $whr,$subcusts);
      if (!empty($orderdata)) {
        foreach ($orderdata as $res) {
            $delivery_note = $container_no =  "";
            $shipmentid = $res['shipment_id'];
            $trip_no = $res['shipmentid'];
            if($trip_no == '0'){
                $trip_no = "";
            }
            $shift_id = $res['shift_id'];
            $trip_sts = $res['trip_sts'];
            $otherstatus = $res['order_status'];
            $trip_id = $res['trip_id'];
            $order_status = 'PENDING';
            if($trip_id != 0 && $trip_sts == 0){
                $order_status = 'ACTIVE';
            }
            if($trip_id != 0 && $trip_sts == 1){
                $order_status = 'CLOSED';
            }
            $otherstatus = strtoupper($res['order_status']);
            if($otherstatus == ""){
                $chkdetails = $this->db->select("order_status")->get_where("tb_order_details",array('order_row_id'=>$res['id']));
                if($chkdetails->num_rows() >0){
                    $otherstatus = $chkdetails->row()->order_status;
                }
            }
            if($otherstatus == 'READY'){
                $otherstatus = 'READY';
            }else if($otherstatus == 'INVOICE'){
                $otherstatus = 'INVOICE';
            }else{
                $otherstatus = "";
            }
            $getdnote = $this->db->query("SELECT reference_id,ref_value FROM tb_order_references WHERE order_id ='".$res['id']."' AND reference_id IN ('DQ','CTR')");
            if($getdnote->num_rows() >0){
                foreach($getdnote->result() as $ref){
                    $ref_id = $ref->reference_id;
                    if($ref_id == "DQ"){
                        $delivery_note = $ref->ref_value;
                    }
                    if($ref_id == "CTR"){
                        $container_no = $ref->ref_value;
                    }
                    
                }
                
            }
            $chkdate = '2020-07-01 00:00:00';
            $createdon = $res['createdon'];
            $order_str = strtotime($createdon);
            $chk_str = strtotime($chkdate);
            $early_pickup = $res['pickup_datetime'];
            
            $early_delivery = $res['delivery_datetime'];
            $curtz = $this->session->userdata("usr_tzone")['timezone'];
            if($order_str > $chk_str){
                if($early_pickup != "" && $early_pickup != "0000-00-00 00:00:00"){
                    $epickup = getdatetimebytimezone($curtz,$early_pickup,DFLT_TZ);
                    $early_pickup = $epickup['datetime'];
                } 
                if($early_delivery != "" && $early_delivery != "0000-00-00 00:00:00"){
                    $edelivery = getdatetimebytimezone($curtz,$early_delivery,DFLT_TZ);
                    $early_delivery = $edelivery['datetime'];
                } 
            }
            $html = "";
            $getrevenue = $this->common->gettbldata(array('order_id' => $res['id'], 'status' => 1),'type,recipient_name,invoice_status','tb_reveneus',0,0);
            if(!empty($getrevenue)){
                foreach($getrevenue as $row){
                    $type = $row['type'];
                    if($type == '0'){
                        $invoice_status = $row['invoice_status'];
                        if($invoice_status == '0'){
                            $status = 'To be billed';
                        }
                        if($invoice_status == '1'){
                            $status = 'Ready To Invoice';
                        }
                        if($invoice_status == '2'){
                            $status = "Billed";
                        }
                        $html .= "<b>(Rev)</b> ".$row['recipient_name']." : ".$status."<br>";
                    }else{
                        $invoice_status = $row['invoice_status'];
                        if($invoice_status == '0'){
                            $status = 'To be billed';
                        }
                        if($invoice_status == '1'){
                            $status = 'Ready To Invoice';
                        }
                        if($invoice_status == '2'){
                            $status = "Invoiced";
                        }
                        $html .= "<b>(Cost)</b> ".$row['recipient_name'].":".$status.""."<br>";
                    }
                }
            }
            $manifestdoc = '';
            $doctype = $this->common->gettblrowdata(array('type_name'=> 'A8A Bond manifest'),'id','tb_document_types',0,0);
            if(!empty($doctype)){
                $manifestpod = $this->db->query(" select imgpath from tb_pod_uploads where order_id = ".$res['id']." AND doc_type =".$doctype['id']." AND imgpath like 'mnf%'");
                if($manifestpod->num_rows() > 0){
                    $mnfinfo = $manifestpod->row_array();
                    $manifestdoc = $mnfinfo['imgpath'];
                } else {
                    $manifestdoc = '';
                }
            }
            $order[] = array('order_row_id' => $res['id'], 'order_id' => $res['order_id'], 'delivery_note' => $delivery_note, 'pickup' => $res['pickup'], 'delivery' => $res['delivery'], 'trip_no' => $trip_no, 'order_status' => $order_status, 'transport_mode' => $res['transport_mode'], 'createdon' => $createdon, 'total_packages' => round($res['totqty']), 'weight' => $res['totwg'], 'volume' => $res['totvol'], 'company_code' => $res['company_code'], 'branch_code' => $res['branch_code'], 'department_code' => $res['department_code'],'otherstatus'=>$otherstatus,'delivery_date'=>$early_delivery,'pickup_date'=>$early_pickup,'html'=>$html,'manifestdoc'=>$manifestdoc,'container_no'=>$container_no);
        }
    }
}
$data['order'] = $order;
$data['bill_type']  = "Etrucknow";
$company_code = $this->session->userdata('company_code');
$branch_code = $this->session->userdata('branch_code');
$getbilltype = $this->db->select("bill_type")->get_where("tb_branch_master",array('branch_code'=>$branch_code,'company_code'=>$company_code));
if($getbilltype->num_rows() >0){
    $data['bill_type'] = $getbilltype->row()->bill_type;
}
$this->newtemplate->dashboard("orders/order", $data);
}
public function searchorders($post)
{
    $whr = array();
    if (isset($post['fromdate']) && $post['fromdate'] != "") {
        $fromdate                                      = date('Y-m-d', strtotime($post['fromdate']));
        $whr["DATE_FORMAT(o.createdon,'%Y-%m-%d') >="] = $fromdate;

    }
    if (isset($post['todate']) && $post['todate'] != "") {
        $todate                                        = date('Y-m-d', strtotime($post['todate']));
        $whr["DATE_FORMAT(o.createdon,'%Y-%m-%d') <="] = $todate;

    }
    
    if (isset($post['order_id']) && $post['order_id'] != "") {
        $whr['o.order_id'] = $post['order_id'];

    }
    if (isset($post['searchcustomer_id']) && $post['searchcustomer_id'] != "") {
        $getcustomer_id = $this->db->select("id")->get_where("tb_customers",array('code'=>$post['searchcustomer_id']));
        if($getcustomer_id->num_rows() >0){
            $whr['o.customer_id'] = $getcustomer_id->row()->id;
        }
        

    }
    if (isset($post['service']) && $post['service'] != "") {
        $whr['d.service'] = $post['service'];

    }
    
    if (isset($post['order_type']) && $post['order_type'] != "") {
        $whr['d.order_type'] = $post['order_type'];

    }
    if (isset($post['product']) && $post['product'] != "") {
        $whr['o.product'] = $post['product'];

    }
    if (isset($post['modeof_trasnport']) && $post['modeof_trasnport'] != "") {
        $whr['o.transport_mode'] = $post['modeof_trasnport'];

    }
    if (isset($post['searchshipper_id']) && $post['searchshipper_id'] != "") {
        $whr['o.pickup_custid'] = $post['searchshipper_id'];

    }
    if (isset($post['searchconsignee_id']) && $post['searchconsignee_id'] != "") {
        $whr['o.drop_custid'] = $post['searchconsignee_id'];

    }
    if (isset($post['delivery_note']) && $post['delivery_note'] != "") {
     /* $getdelivery_noteid = $this->db->select("id")->get_where("tb_shipments",array('shipid'=>$post['delivery_note']));*/
     $getdelivery_noteid = $this->db->select("order_id")->get_where("tb_order_references",array('ref_value'=>$post['delivery_note'],'status'=>'1','reference_id'=>'DQ'));
     if($getdelivery_noteid->num_rows() >0){
        $order_id = $getdelivery_noteid->row()->order_id;
        $whr['o.id'] = $order_id;
    }
}
if (isset($post['container_no']) && $post['container_no'] != "") {
     $getcontainer_no = $this->db->select("order_id")->get_where("tb_order_references",array('ref_value'=>$post['container_no'],'status'=>'1','reference_id'=>'CTR'));
     if($getcontainer_no->num_rows() >0){
        $order_id = $getcontainer_no->row()->order_id;
        $whr['o.id'] = $order_id;
    }else{
        $whr['o.id'] = '0';
    }
}
if (isset($post['purchase_order']) && $post['purchase_order'] != "") {
    $whr['d.purchase_order'] = $post['purchase_order'];

}
if (isset($post['company_code']) && $post['company_code'] != "") {
    $whr['o.company_code'] = $post['company_code'];

}
if (isset($post['branch_code']) && $post['branch_code'] != "") {
    $whr['o.branch_code'] = $post['branch_code'];

}
if (isset($post['from_date']) && $post['from_date'] != "") {
    $from_date                                     = date('Y-m-d', strtotime($post['from_date']));
    $whr["DATE_FORMAT(o.createdon,'%Y-%m-%d') >="] = $from_date;

}
if (isset($post['todate']) && $post['todate'] != "") {
    $todate                                        = date('Y-m-d', strtotime($post['todate']));
    $whr["DATE_FORMAT(o.createdon,'%Y-%m-%d') <="] = $todate;

}
return $whr;

}

public function checkpurchaseordervalue()
{
    $p_order  = $this->input->post('purchase_order');
    $chkorder = $this->db->select('id')->get_where("tb_order_details", array('purchase_order' => $p_order));
    if ($chkorder->num_rows() > 0) {
        echo '1';
    } else {
        echo '0';
    }
}

public function findcountrybyname()
{
    $name   = $this->input->post('name');
    $result = array();
    $chkqry = $this->db->select('id,company_name,company_code,description')->get_where("tb_company_master", array('company_code' => $name));
    if ($chkqry->num_rows() > 0) {
        foreach ($chkqry->result() as $res) {
            $check    = "<input type='radio' name='listcompany' id='listcompany_" . $res->id . "' class='listcompany' onchange='selectcompany(" . $res->id . ")' value='" . $res->company_code . "'>";
            $result[] = array('check' => $check, 'company_code' => $res->company_code, 'company_name' => $res->company_name, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}

public function findbranchbyname()
{
    $name   = $this->input->post('name');
    $result = array();
    $chkqry = $this->db->select('id,branch_name,company_code,description,branch_code')->get_where("tb_branch_master", array('branch_code' => $name));
    if ($chkqry->num_rows() > 0) {
        foreach ($chkqry->result() as $res) {
            $check    = "<input type='radio' name='listbranch' id='listbranch_" . $res->id . "' class='listbranch' onchange='selectbranch(" . $res->id . ")' value='" . $res->branch_code . "'>";
            $result[] = array('check' => $check, 'branch_code' => $res->branch_code, 'branch_name' => $res->branch_name, 'company_code' => $res->company_code, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}
public function viewcompanylist()
{
    $result = array();
    $check  = "";
    $popup  = isset($_POST['popup']) ? $_POST['popup'] : "";
    $chkqry = $this->db->select("id,company_name,company_code,description")->get_where("tb_company_master", array('status' => 1));
    if ($chkqry->num_rows() > 0) {
        foreach ($chkqry->result() as $res) {
            if ($popup == 'popup') {
                $check = "<input type='radio' name='listpopupcompany' id='listpopupcompany_" . $res->id . "' class='listpopupcompany' onchange='selectpopupcompany(" . $res->id . ")' value='" . $res->company_code . "'>";
            } else {
                $check = "<input type='radio' name='listcompany' id='listcompany_" . $res->id . "' class='listcompany' onchange='selectcompany(" . $res->id . ")' value='" . $res->company_code . "'>";
            }
            $result[] = array('check' => $check, 'id' => $res->id, 'company_code' => $res->company_code, 'company_name' => $res->company_name, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}
public function getcompanyname()
{
    $post   = $this->input->post();
    $term   = $post['term'];
    $result = array();
    $check  = "";
    $popup  = isset($_POST['popup']) ? $_POST['popup'] : "";
    $this->db->select("id,company_code,company_name,description");
    $this->db->from("tb_company_master");
    $this->db->like('company_code', $term);
    $this->db->order_by('createdon', 'DESC');
    $getcompanyname = $this->db->get();
    if ($getcompanyname->num_rows() > 0) {
        foreach ($getcompanyname->result() as $res) {
            if ($popup == 'popup') {
                $check = "<input type='radio' name='listpopupcompany' id='listpopupcompany_" . $res->id . "' class='listpopupcompany' onchange='selectpopupcompany(" . $res->id . ")' value='" . $res->company_code . "'>";
            } else {
                $check = "<input type='radio' name='listcompany' id='listcompany_" . $res->id . "' class='listcompany' onchange='selectcompany(" . $res->id . ")' value='" . $res->company_code . "'>";
            }
            $result[] = array('check' => $check, 'company_code' => $res->company_code, 'company_name' => $res->company_name, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}
public function addcompanydetails()
{
    $post   = $this->input->post();
    $cdate  = date('Y-m-d H:i:s');
    $ins_ar = array('company_name' => $post['company_name'], 'company_code' => $post['company_code'], 'description' => $post['description'], 'status' => 1, 'createdon' => $cdate);
    $chkqry = $this->db->select('id')->get_where("tb_company_master", array('company_name' => $post['company_name'], 'company_code' => $post['company_code']));
    if ($chkqry->num_rows() > 0) {
        echo json_encode(2);
    } else {
        $ins = $this->db->insert('tb_company_master', $ins_ar);
        if ($ins) {
            echo json_encode(1);
        } else {
            echo json_encode(0);
        }
    }

}
public function adddepartmentdetails()
{
    $post   = $this->input->post();
    $cdate  = date('Y-m-d H:i:s');
    $ins_ar = array('department_name' => $post['department_name'], 'department_code' => $post['department_code'], 'company_code' => $post['company_code'], 'branch_code' => $post['branch_code'], 'description' => $post['description'], 'status' => 1, 'createdon' => $cdate);
    $chkqry = $this->db->select('id')->get_where("tb_department_master", array('department_code' => $post['department_code'], 'department_name' => $post['department_name'], 'company_code' => $post['company_code'], 'branch_code' => $post['branch_code']));
    if ($chkqry->num_rows() > 0) {
        echo json_encode(2);
    } else {
        $ins = $this->db->insert('tb_department_master', $ins_ar);
        if ($ins) {
            echo json_encode(1);
        } else {
            echo json_encode(0);
        }
    }

}

public function getbranchbycompany()
{
    $cmp_id = $this->input->post('cmp_code');
    $id     = $this->input->post('term');
    $result = array();
    $this->db->select('id,branch_code');
    $this->db->from('tb_branch_master');
    $this->db->like('company_code', $cmp_id);
    $this->db->like('branch_code', $id);
    $qry = $this->db->get();
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            $result[] = array('id' => $res->id, 'branch_code' => $res->branch_code);
        }
    }
    echo json_encode($result);
}
public function getbranchbyname()
{
    $cmp_id = $this->input->post('name');
    $result = array();
    $this->db->select('id,branch_code,branch_name,company_code,description');
    $this->db->from('tb_branch_master');
    $this->db->like('branch_code', $cmp_id);
    $qry = $this->db->get();
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            $check    = "<input type='radio' name='listbranch' id='listbranch_" . $res->id . "' class='listbranch' onchange='selectbranch(" . $res->id . ")' value='" . $res->branch_code . "'>";
            $result[] = array('check' => $check, 'id' => $res->id, 'branch_code' => $res->branch_code, 'branch_name' => $res->branch_name, 'company_code' => $res->company_code, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}
public function viewbranchlist()
{
    $result       = array();
    $company_code = isset($_POST['company_code']) ? $_POST['company_code'] : "";
    $check        = "";
    $popup        = isset($_POST['popup']) ? $_POST['popup'] : "";

    $where = array('status' => 1);
    if ($company_code != "") {
        $where['company_code'] = $company_code;
    }
    $this->db->select('id,branch_code,branch_name,company_code,description');
    $this->db->from('tb_branch_master');
    $this->db->where($where);
    $qry = $this->db->get();
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            if ($popup == 'popup') {
                $check = "<input type='radio' name='listpopupbranch' id='listpopupbranch_" . $res->id . "' class='listpopupbranch' onchange='selectpopupbranch(" . $res->id . ")' value='" . $res->branch_code . "'>";
            } else {
                $check = "<input type='radio' name='listbranch' id='listbranch_" . $res->id . "' class='listbranch' onchange='selectbranch(" . $res->id . ")' value='" . $res->branch_code . "'>";
            }
            $result[] = array('check' => $check, 'id' => $res->id, 'branch_code' => $res->branch_code, 'branch_name' => $res->branch_name, 'company_code' => $res->company_code, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}

public function addbranchdetails()
{
    $post   = $this->input->post();
    $cdate  = date('Y-m-d H:i:s');
    $ins_ar = array('branch_name' => $post['branch_name'], 'branch_code' => $post['branch_code'], 'company_code' => $post['company_code'], 'description' => $post['description'], 'status' => 1, 'createdon' => $cdate);
    $chkqry = $this->db->select("id")->get_where("tb_branch_master", array('branch_code' => $post['branch_code'], 'branch_name' => $post['branch_name']));
    if ($chkqry->num_rows() > 0) {
        echo json_encode(2);
    } else {
        $ins = $this->db->insert("tb_branch_master", $ins_ar);
        if ($ins) {
            echo json_encode(1);
        } else {
            echo json_encode(0);
        }
    }

}

public function getdepartmentbybranch()
{
    $cmp_id = $this->input->post('branch_code');
    $id     = $this->input->post('term');
    $result = array();
    $qry    = $this->db->select("id,department_code");
    $this->db->from("tb_department_master");
    $this->db->where(array('branch_code' => $cmp_id));
    $this->db->like('department_code', $id);
    $qry = $this->db->get();
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            $result[] = array('id' => $res->id, 'department_code' => $res->department_code);
        }
    }
    echo json_encode($result);
}

public function finddepartmentlist()
{
    $name   = $this->input->post('name');
    $result = array();
    $qry    = $this->db->select("id,department_code,department_name,company_code,branch_code,description")->get_where("tb_department_master", array('department_code' => $name));
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            $check    = "<input type='radio' name='listdepartment' id='listdepartment_" . $res->id . "' class='listdepartment' onchange='selectdepartment(" . $res->id . ")' value='" . $res->department_code . "'>";
            $result[] = array('check' => $check, 'id' => $res->id, 'department_code' => $res->department_code, 'department_name' => $res->department_name, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}
public function finddepartmentlikelist()
{
    $name   = $this->input->post('name');
    $result = array();
    $qry    = $this->db->select("id,department_code,department_name,company_code,branch_code,description")->get_where("tb_department_master", array('department_code' => $name));
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            $check    = "<input type='radio' name='listdepartment' id='listdepartment_" . $res->id . "' class='listdepartment' onchange='selectdepartment(" . $res->id . ")' value='" . $res->department_code . "'>";
            $result[] = array('check' => $check, 'id' => $res->id, 'department_code' => $res->department_code, 'department_name' => $res->department_name, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}
public function viewdepartmentlist()
{
    $result       = array();
    $company_code = isset($_POST['company_code']) ? $_POST['company_code'] : "";
    $branch_code  = isset($_POST['branch_code']) ? $_POST['branch_code'] : "";
    $popup        = isset($_POST['popup']) ? $_POST['popup'] : "";
    $whr          = array('status' => 1);
    if ($company_code != "") {
        $whr['company_code'] = $company_code;
    }
    if ($branch_code != "") {
        $whr['branch_code'] = $branch_code;
    }
    $this->db->select("id,department_code,department_name,company_code,branch_code,description");
    $this->db->from("tb_department_master");
    $this->db->where($whr);
    $qry = $this->db->get();
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            if ($popup == 'popup') {
                $check = "<input type='radio' name='listpopupdepartment' id='listpopupdepartment_" . $res->id . "' class='listpopupdepartment' onchange='selectpopupdepartment(" . $res->id . ")' value='" . $res->department_code . "'>";
            } else {
                $check = "<input type='radio' name='listdepartment' id='listdepartment_" . $res->id . "' class='listdepartment' onchange='selectdepartment(" . $res->id . ")' value='" . $res->department_code . "'>";
            }
            $result[] = array('check' => $check, 'id' => $res->id, 'department_code' => $res->department_code, 'department_name' => $res->department_name, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'description' => $res->description);
        }
    }
    echo json_encode($result);
}

public function checkcompanycode()
{
    $companys = array();
    $c_code   = isset($_POST['data']) ? $_POST['data'] : "";
    if (!empty($c_code)) {
        $this->db->select('company_code');
        $this->db->from("tb_company_master");
        $this->db->where_in('company_code', $c_code);
        $chk = $this->db->get();
        if ($chk->num_rows() > 0) {
            foreach ($chk->result() as $res) {
                $companys[] = $res->company_code;
            }
        }
    }
    $diff_companys = array();
    if (!empty($companys)) {
        $diff_companys = array_diff($c_code, $companys);

    }
    echo json_encode($diff_companys);
}
public function neworder()
{
    $data             = $transport             = array();
    $user_id          = $this->session->userdata('user_id');
    $company_code     = $this->session->userdata('company_code');
    $branch_code      = $this->session->userdata('branch_code');
    if($company_code == 'RUKN'){
        $gettrasnportmode = $this->db->query("SELECT code,name FROM tb_transportmode WHERE code IN ('LTL','FTL','GRP')");
        if ($gettrasnportmode->num_rows() > 0) {
            foreach ($gettrasnportmode->result() as $res) {
                $transport[] = array('code' => $res->code, 'name' => $res->name);
            }
        }
    }else{
       $gettrasnportmode = $this->db->select("code,name")->get_where("tb_transportmode", array('status' => 1));
       if ($gettrasnportmode->num_rows() > 0) {
        foreach ($gettrasnportmode->result() as $res) {
            $transport[] = array('code' => $res->code, 'name' => $res->name);
        }
    }
}
$data['transport']    = $transport;
$data['company_code'] = $company_code;
$data['branch_code']  = $branch_code;
$pickup_details       = array();
$custid               = $this->session->userdata('cust_id');
$ordertypes             = array();
if ($custid != "") {
    $getpickupdetails = $this->Order->getpickupdetails($custid);
    if ($getpickupdetails->num_rows() > 0) {
        $pickup_details = array('id' => $getpickupdetails->row()->id, 'name' => $getpickupdetails->row()->name, 'party_id' => $getpickupdetails->row()->code, 'address' => $getpickupdetails->row()->address, 'pincode' => $getpickupdetails->row()->pincode, 'country' => $getpickupdetails->row()->country);
    }
    $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types", array('customer_id' => $custid,'company_code'=>$company_code,'status'=>'1'));
    if ($getorders->num_rows() > 0) {
        foreach ($getorders->result() as $res) {
            $ordertypes[] = array('type_id' => $res->id, 'type_name' => $res->type_name);
        }
    }else{
        $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types",array('company_code'=>$company_code,"status"=>'1'));
        if($getorders->num_rows() >0){
          foreach($getorders->result() as $res){
            $ordertypes[] = array('type_id'=>$res->id,'type_name'=>$res->type_name);
        }
    }else{
      $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types",array('company_code'=>"SGKN","status"=>'1'));
      if($getorders->num_rows() >0){
        foreach($getorders->result() as $res){
          $ordertypes[] = array('type_id'=>$res->id,'type_name'=>$res->type_name);
      }
  }
}
}
}else{

    $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types",array('company_code'=>$company_code,"status"=>'1'));
    if($getorders->num_rows() >0){
      foreach($getorders->result() as $res){
        $ordertypes[] = array('type_id'=>$res->id,'type_name'=>$res->type_name);
    }
}else{
  $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types",array('company_code'=>"SGKN","status"=>'1'));
  if($getorders->num_rows() >0){
    foreach($getorders->result() as $res){
      $ordertypes[] = array('type_id'=>$res->id,'type_name'=>$res->type_name);
  }
}
}

}
$roles = array();
$qyery=$this->db->query("SELECT id,name FROM tbl_party_types WHERE user_id= '".$user_id."' AND status=1 GROUP BY name");
if($qyery->num_rows() >0){
    foreach($qyery->result() as $res){
        $roles[] = array('id'=>$res->id,'name'=>$res->name);
    }
}
$getchargecodes = $this->db->select("id,charge_code")->get_where("tb_charge_codes", array('status' => '1'));
if ($getchargecodes->num_rows() > 0) {
    foreach ($getchargecodes->result() as $res) {
        $chargecodes[] = array('charge_id' => $res->id, 'charge_code' => $res->charge_code);
    }
}
$data['chargecodes'] = $chargecodes;
$data['pickup_details'] = $pickup_details;
$data['ordertypes'] = $ordertypes;
$data['roles'] = $roles;
$this->newtemplate->dashboard('orders/neworder', $data);
}

public function getdeliverytermvalue()
{
    $incoterm = $this->input->post('incoterm');
    $terms    = array();
    $terms = getDeliverytermsbyIncoterm($incoterm);
    
    echo json_encode($terms);
}
public function copyorder($id = null)
{
    $data          = $order_types          = array();
    $order_details = $shipper_details = $drop_details = $pickup_details = $delivery_array = $chargecodes = array();

    $order_details['type_name'] = $order_details['ordtype_code'] = "";
    if ($id != "") {
        $chkorder = $this->Order->getordertoedit($id);
        if ($chkorder->num_rows() > 0) {
            $incoterm    = $chkorder->row()->incoterm;
            $shipment_id = $pickup_inst = $delivery_inst = $container_no = "";
            $getdnote = $this->db->query("SELECT reference_id,ref_value FROM tb_order_references WHERE order_id ='".$id."' AND reference_id IN ('DQ','ORD_DLVINST','ORD_PIKINST','CTR')");
            if($getdnote->num_rows() >0){
                foreach($getdnote->result() as $ref){
                    $ref_id = $ref->reference_id;
                    if($ref_id == 'DQ'){
                        $shipment_id = $ref->ref_value;
                    }
                    if($ref_id == 'ORD_DLVINST'){
                        $delivery_inst = $ref->ref_value;
                    }
                    if($ref_id == 'ORD_PIKINST'){
                        $pickup_inst = $ref->ref_value;
                    } 
                    if($ref_id == 'CTR'){
                        $container_no = $ref->ref_value;
                    }
                    
                }
            }
            $pickup_custid = $chkorder->row()->pickup_custid;
            $status        = $chkorder->row()->status;
            $trip_id = $chkorder->row()->trip_id;
            $trip_sts = $chkorder->row()->trip_sts;
            $order_status = "PENDING";
            /*if($trip_id != 0 && $trip_sts == 0){
                $order_status = 'ACTIVE';
            }
            if($trip_id != 0 && $trip_sts == 1){
                $order_status = 'CLOSED';
            }*/
            $chkdate = '2020-07-01 00:00:00';
            $createdon = $chkorder->row()->createdon;
            $order_str = strtotime($createdon);
            $chk_str = strtotime($chkdate);
            $early_pickup = $chkorder->row()->pickup_datetime;
            
            $early_delivery = $chkorder->row()->delivery_datetime;
            $late_pickup = $chkorder->row()->pickup_endtime;
            $late_delivery = $chkorder->row()->drop_endtime;
            $curtz = $this->session->userdata("usr_tzone")['timezone'];
            if($order_str > $chk_str){
                if($early_pickup != "" && $early_pickup != "0000-00-00 00:00:00"){
                    $epickup = getdatetimebytimezone($curtz,$early_pickup,DFLT_TZ);
                    $early_pickup = $epickup['datetime'];
                } 
                if($early_delivery != "" && $early_delivery != "0000-00-00 00:00:00"){
                    $edelivery = getdatetimebytimezone($curtz,$early_delivery,DFLT_TZ);
                    $early_delivery = $edelivery['datetime'];
                } 
                if($late_pickup != "" && $late_pickup != "0000-00-00 00:00:00"){
                    $lpickup = getdatetimebytimezone($curtz,$late_pickup,DFLT_TZ);
                    $late_pickup = $lpickup['datetime'];
                }
                if($late_delivery != "" && $late_delivery != "0000-00-00 00:00:00"){
                    $ldelivery = getdatetimebytimezone($curtz,$late_delivery,DFLT_TZ);
                    $late_delivery = $ldelivery['datetime'];
                } 

            }
            $order_details = array('id' => $chkorder->row()->id, 'order_id' => $chkorder->row()->order_id, 'shipment_id' => $shipment_id, 'early_pickup' => $early_pickup, 'early_delivery' => $early_delivery, 'late_pickup' => $late_pickup, 'late_delivery' => $late_delivery, 'product' => $chkorder->row()->product, 'service' => $chkorder->row()->service, 'delivery_term' => $chkorder->row()->delivery_term, 'incoterm' => $chkorder->row()->incoterm, 'delivery_note' => $chkorder->row()->delivery_note, 'purchase_order' => $chkorder->row()->purchase_order, 'notify_party' => $chkorder->row()->notify_party, 'goods_value' => $chkorder->row()->goods_value, 'lane_reference' => $chkorder->row()->lane_reference, 'distance' => $chkorder->row()->distance, 'customs_required' => $chkorder->row()->customs_required, 'high_cargo_value' => $chkorder->row()->high_cargo_value, 'valorance_insurance' => $chkorder->row()->valorance_insurance, 'temperature_control' => $chkorder->row()->temperature_control, 'company_code' => $chkorder->row()->company_code, 'branch_code' => $chkorder->row()->branch_code, 'department_code' => $chkorder->row()->department_code, 'createdon' => $chkorder->row()->createdon, 'order_type' => $chkorder->row()->order_type, 'transport_mode' => $chkorder->row()->transport_mode,'pickup_inst'=>$pickup_inst,'delivery_inst'=>$delivery_inst,'container_no'=>$container_no);
            $order_details['order_status'] = $order_status;
            if ($incoterm != '') {
                $delivery_array = getDeliverytermsbyIncoterm($incoterm);
            }
            $pickup_id    = $chkorder->row()->customer_id;
            $company_code = $this->session->userdata('company_code');
            if ($company_code != "") {
                $company_code = $chkorder->row()->company_code;
            }
            $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types", array('customer_id' => $pickup_id,'company_code'=>$company_code,'status'=>'1'));
            if ($getorders->num_rows() > 0) {
                foreach ($getorders->result() as $res) {
                    $order_types[] = array('type_id' => $res->id, 'type_name' => $res->type_name);
                }
            }else{
                $getorder_types = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types", array('company_code' => $company_code, 'status' => 1));
                if ($getorder_types->num_rows() > 0) {
                   foreach ($getorder_types->result() as $res) {
                       $order_types[] = array('type_name' => $res->type_name, 'type_id' => $res->id);
                   }
               }else{
                  $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types",array('company_code'=>"SGKN","status"=>1));
                  if($getorders->num_rows() >0){
                      foreach($getorders->result() as $res){
                       $order_types[] = array('type_id'=>$res->id,'type_name'=>$res->type_name);
                   }
               }
           }
       }
       $getpickupdetails = $this->Order->getpickupdetails($pickup_id);
       if ($getpickupdetails->num_rows() > 0) {
        $pickup_details = array('id' => $getpickupdetails->row()->id, 'name' => $getpickupdetails->row()->name, 'party_id' => $getpickupdetails->row()->code, 'address' => $getpickupdetails->row()->address, 'pincode' => $getpickupdetails->row()->pincode, 'country' => $getpickupdetails->row()->country);
    }
    $drop_id        = $chkorder->row()->drop_custid;
    $drop_row_id    = 0;
    $party_row_ids = array();
    $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name, p.mobile, p.email,p.code,p.fax,o.party_type FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1  WHERE p.status=1 AND o.order_id='$id' GROUP BY o.party_type");
    if($chekparty->num_rows() >0){
      foreach($chekparty->result() as $rr){

        $ptype = $rr->party_type;
        $chktype = $this->db->select("name")->get_where("tbl_party_types",array("id"=>$ptype),1,0);
        if($chktype->num_rows()>0){
          if($chktype->row()->name == "Consignee"){
            $drop_details = array('id'=>$rr->id,'name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
        }else if($chktype->row()->name == "Shipper"){
         $shipper_details = array('id'=>$rr->id,'name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
     }else{
         $party_row_ids[] = $rr->id;
     }
 }
}
}
$shipper_details['name'] = $chkorder->row()->pickup;
$shipper_details['street'] = $chkorder->row()->pickup_address1;
$shipper_details['state'] = $chkorder->row()->pickup_address2;
$shipper_details['city'] = $chkorder->row()->pickup_city;
$shipper_details['country'] = $chkorder->row()->pickup_country;
$shipper_details['pincode'] = $chkorder->row()->pickup_pincode;

$drop_details['name'] = $chkorder->row()->delivery;
$drop_details['street'] = $chkorder->row()->delivery_address1;
$drop_details['state'] = $chkorder->row()->delivery_address2;
$drop_details['city'] = $chkorder->row()->delivery_city;
$drop_details['country'] = $chkorder->row()->delivery_country;
$drop_details['pincode'] = $chkorder->row()->delivery_pincode;
$drop_id = $chkorder->row()->drop_custid;
}

$user_id = $this->session->userdata('user_id');
$roles = array();
$qyery=$this->db->query("SELECT id,name FROM tbl_party_types WHERE user_id= '".$user_id."' AND status=1 GROUP BY name");
if($qyery->num_rows() >0){
    foreach($qyery->result() as $res){
        $roles[] = array('id'=>$res->id,'name'=>$res->name);
    }
}
$getchargecodes = $this->db->select("id,charge_code")->get_where("tb_charge_codes", array('status' => '1'));
if ($getchargecodes->num_rows() > 0) {
    foreach ($getchargecodes->result() as $res) {
        $chargecodes[] = array('charge_id' => $res->id, 'charge_code' => $res->charge_code);
    }
}
$cdate = date('Y-m-d H:i:s');
$cargo_row_ids = array();
$qry = $this->db->query("SELECT c.* FROM tb_cargo_details c,tb_order_cargodetails o WHERE o.order_id ='" . $id . "' AND o.cargo_id=c.id AND o.status=1 GROUP BY c.id ORDER BY c.id DESC");
if ($qry->num_rows() > 0) {
    foreach ($qry->result() as $res) {
        $cargo_ins = array('cargo_type' => $res->cargo_type, 'goods_description' => $res->goods_description, 'quantity' => $res->quantity, 'length' => $res->length, 'length_unit' => $res->length_unit, 'width' => $res->width, 'width_unit' => $res->width_unit, 'height' => $res->height, 'height_unit' => $res->height_unit, 'weight' => $res->weight, 'weight_unit' => $res->weight_unit, 'volume' => $res->volume, 'volume_unit' => $res->volume_unit, 'stackable' => $res->stackable,'grounded' => $res->grounded,'splittable' => $res->splittable, 'createdby' => $user_id,'volumetric_weight'=>$res->volumetric_weight,'volweight_uom'=>$res->volweight_uom, 'createdon' => $cdate,'ldm'=>$res->ldm);
        $ins_cargo = $this->db->insert("tb_cargo_details",$cargo_ins);
        $cargo_row_ids[] = $this->db->insert_id();
        /*   array_push($ids,$cargo_row_id);*/
    }
}
}
$transport        = array();
if($company_code == 'RUKN'){
    $gettrasnportmode = $this->db->query("SELECT code,name FROM tb_transportmode WHERE code IN ('LTL','FTL','GRP')");
    if ($gettrasnportmode->num_rows() > 0) {
        foreach ($gettrasnportmode->result() as $res) {
            $transport[] = array('code' => $res->code, 'name' => $res->name);
        }
    }
}else{
    $gettrasnportmode = $this->db->select("code,name")->get_where("tb_transportmode", array('status' => 1));
    if ($gettrasnportmode->num_rows() > 0) {
        foreach ($gettrasnportmode->result() as $res) {
            $transport[] = array('code' => $res->code, 'name' => $res->name);
        }
    }
}
$cargo_id = "";
if(!empty($cargo_row_ids)){
    $cargo_id = implode(',', $cargo_row_ids);
} $party_id = "";
if(!empty($party_row_ids)){
    $party_id = implode(',', $party_row_ids);
}
$order_details['cargo_id'] = $cargo_id;
$order_details['party_id'] = $party_id;
$data['transport']         = $transport;
$data['order_details']     = $order_details;
$data['pickup_details']    = $pickup_details;
$data['drop_details']      = $drop_details;
$data['shipper_details']   = $shipper_details;
$data['order_types']       = $order_types;
$data['delivery_array']    = $delivery_array;
$data['chargecodes']       = $chargecodes;
$data['roles']             = $roles;

$this->newtemplate->dashboard('orders/copyorder', $data);
}

public function reverseorder($id = null)
{
    $data          = $order_types          = array();
    $order_details = $shipper_details = $drop_details = $pickup_details = $delivery_array = $chargecodes = array();

    $order_details['type_name'] = $order_details['ordtype_code'] = "";
    if ($id != "") {
        $chkorder = $this->Order->getordertoedit($id);
        if ($chkorder->num_rows() > 0) {
            $incoterm    = $chkorder->row()->incoterm;
            $shipment_id = $pickup_inst = $delivery_inst = $container_no = "";
            $getdnote = $this->db->query("SELECT reference_id,ref_value FROM tb_order_references WHERE order_id ='".$id."' AND reference_id IN ('DQ','ORD_DLVINST','ORD_PIKINST','CTR')");
            if($getdnote->num_rows() >0){
                foreach($getdnote->result() as $ref){
                    $ref_id = $ref->reference_id;
                    if($ref_id == 'DQ'){
                        $shipment_id = $ref->ref_value;
                    }
                    if($ref_id == 'ORD_DLVINST'){
                        $delivery_inst = $ref->ref_value;
                    }
                    if($ref_id == 'ORD_PIKINST'){
                        $pickup_inst = $ref->ref_value;
                    }
                    if($ref_id == 'CTR'){
                        $container_no = $ref->ref_value;
                    }
                    
                }
            }
            $pickup_custid = $chkorder->row()->pickup_custid;
            $status        = $chkorder->row()->status;
            $trip_id = $chkorder->row()->trip_id;
            $trip_sts = $chkorder->row()->trip_sts;
            $order_status = "PENDING";
            /*if($trip_id != 0 && $trip_sts == 0){
                $order_status = 'ACTIVE';
            }
            if($trip_id != 0 && $trip_sts == 1){
                $order_status = 'CLOSED';
            }*/
            $chkdate = '2020-07-01 00:00:00';
            $createdon = $chkorder->row()->createdon;
            $order_str = strtotime($createdon);
            $chk_str = strtotime($chkdate);
            $early_pickup = $chkorder->row()->pickup_datetime;
            
            $early_delivery = $chkorder->row()->delivery_datetime;
            $late_pickup = $chkorder->row()->pickup_endtime;
            $late_delivery = $chkorder->row()->drop_endtime;
            $curtz = $this->session->userdata("usr_tzone")['timezone'];
            if($order_str > $chk_str){
                if($early_pickup != "" && $early_pickup != "0000-00-00 00:00:00"){
                    $epickup = getdatetimebytimezone($curtz,$early_pickup,DFLT_TZ);
                    $early_pickup = $epickup['datetime'];
                } 
                if($early_delivery != "" && $early_delivery != "0000-00-00 00:00:00"){
                    $edelivery = getdatetimebytimezone($curtz,$early_delivery,DFLT_TZ);
                    $early_delivery = $edelivery['datetime'];
                } 
                if($late_pickup != "" && $late_pickup != "0000-00-00 00:00:00"){
                    $lpickup = getdatetimebytimezone($curtz,$late_pickup,DFLT_TZ);
                    $late_pickup = $lpickup['datetime'];
                }
                if($late_delivery != "" && $late_delivery !== "0000-00-00 00:00:00"){
                    $ldelivery = getdatetimebytimezone($curtz,$late_delivery,DFLT_TZ);
                    $late_delivery = $ldelivery['datetime'];
                } 

            }
            $order_details = array('id' => $chkorder->row()->id, 'order_id' => $chkorder->row()->order_id, 'shipment_id' => $shipment_id, 'early_pickup' => $early_pickup, 'early_delivery' => $early_delivery, 'late_pickup' => $late_pickup, 'late_delivery' => $late_delivery, 'product' => $chkorder->row()->product, 'service' => $chkorder->row()->service, 'delivery_term' => $chkorder->row()->delivery_term, 'incoterm' => $chkorder->row()->incoterm, 'delivery_note' => $chkorder->row()->delivery_note, 'purchase_order' => $chkorder->row()->purchase_order, 'notify_party' => $chkorder->row()->notify_party, 'goods_value' => $chkorder->row()->goods_value, 'lane_reference' => $chkorder->row()->lane_reference, 'distance' => $chkorder->row()->distance, 'customs_required' => $chkorder->row()->customs_required, 'high_cargo_value' => $chkorder->row()->high_cargo_value, 'valorance_insurance' => $chkorder->row()->valorance_insurance, 'temperature_control' => $chkorder->row()->temperature_control, 'company_code' => $chkorder->row()->company_code, 'branch_code' => $chkorder->row()->branch_code, 'department_code' => $chkorder->row()->department_code, 'createdon' => $chkorder->row()->createdon, 'order_type' => $chkorder->row()->order_type, 'transport_mode' => $chkorder->row()->transport_mode,'pickup_inst'=>$pickup_inst,'delivery_inst'=>$delivery_inst,'container_no'=>$container_no);
            $order_details['order_status'] = $order_status;
            if ($incoterm != '') {
                $delivery_array = getDeliverytermsbyIncoterm($incoterm);
            }
            $pickup_id    = $chkorder->row()->customer_id;
            $company_code = $this->session->userdata('company_code');
            if ($company_code != "") {
                $company_code = $chkorder->row()->company_code;
            }
            $getorder_types = $this->db->select("id,type_name")->get_where("tb_order_types", array('company_code' => $company_code, 'status' => 1));
            if ($getorder_types->num_rows() > 0) {
                foreach ($getorder_types->result() as $res) {
                    $order_types[] = array('type_name' => $res->type_name, 'type_id' => $res->id);
                }
            }
            $getpickupdetails = $this->Order->getpickupdetails($pickup_id);
            if ($getpickupdetails->num_rows() > 0) {
                $pickup_details = array('id' => $getpickupdetails->row()->id, 'name' => $getpickupdetails->row()->name, 'party_id' => $getpickupdetails->row()->code, 'address' => $getpickupdetails->row()->address, 'pincode' => $getpickupdetails->row()->pincode, 'country' => $getpickupdetails->row()->country);
            }
            $drop_id        = $chkorder->row()->drop_custid;
            $drop_row_id    = 0;
            $party_row_ids = array();
            $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name, p.mobile, p.email,p.code,p.fax,o.party_type FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1  WHERE p.status=1 AND o.order_id='$id' GROUP BY o.party_type");
            if($chekparty->num_rows() >0){
              foreach($chekparty->result() as $rr){

                $ptype = $rr->party_type;
                $chktype = $this->db->select("name")->get_where("tbl_party_types",array("id"=>$ptype),1,0);
                if($chktype->num_rows()>0){
                  if($chktype->row()->name == "Consignee"){
                    $shipper_details = array('id'=>$rr->id,'name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
                }else if($chktype->row()->name == "Shipper"){
                 $drop_details = array('id'=>$rr->id,'name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
             }else{
                $party_row_ids[] = $rr->id;
            }
        }
    }
}
$drop_details['name'] = $chkorder->row()->pickup;
$drop_details['street'] = $chkorder->row()->pickup_address1;
$drop_details['state'] = $chkorder->row()->pickup_address2;
$drop_details['city'] = $chkorder->row()->pickup_city;
$drop_details['country'] = $chkorder->row()->pickup_country;
$drop_details['pincode'] = $chkorder->row()->pickup_pincode;

$shipper_details['name'] = $chkorder->row()->delivery;
$shipper_details['street'] = $chkorder->row()->delivery_address1;
$shipper_details['state'] = $chkorder->row()->delivery_address2;
$shipper_details['city'] = $chkorder->row()->delivery_city;
$shipper_details['country'] = $chkorder->row()->delivery_country;
$shipper_details['pincode'] = $chkorder->row()->delivery_pincode;
$drop_id = $chkorder->row()->drop_custid;
}

$user_id = $this->session->userdata('user_id');
$roles = array();
$qyery=$this->db->query("SELECT id,name FROM tbl_party_types WHERE user_id= '".$user_id."' AND status=1 GROUP BY name");
if($qyery->num_rows() >0){
    foreach($qyery->result() as $res){
        $roles[] = array('id'=>$res->id,'name'=>$res->name);
    }
}
$getchargecodes = $this->db->select("id,charge_code")->get_where("tb_charge_codes", array('status' => '1'));
if ($getchargecodes->num_rows() > 0) {
    foreach ($getchargecodes->result() as $res) {
        $chargecodes[] = array('charge_id' => $res->id, 'charge_code' => $res->charge_code);
    }
}
$cdate = date('Y-m-d H:i:s');
$cargo_row_ids = array();
$qry = $this->db->query("SELECT c.* FROM tb_cargo_details c,tb_order_cargodetails o WHERE o.order_id ='" . $id . "' AND o.cargo_id=c.id AND o.status=1 GROUP BY c.id ORDER BY c.id DESC");
if ($qry->num_rows() > 0) {
    foreach ($qry->result() as $res) {
        
        $cargo_ins = array('cargo_type' => $res->cargo_type, 'goods_description' => $res->goods_description, 'quantity' => $res->quantity, 'length' => $res->length, 'length_unit' => $res->length_unit, 'width' => $res->width, 'width_unit' => $res->width_unit, 'height' => $res->height, 'height_unit' => $res->height_unit, 'weight' => $res->weight, 'weight_unit' => $res->weight_unit, 'volume' => $res->volume, 'volume_unit' => $res->volume_unit, 'stackable' => $res->stackable,'grounded' => $res->grounded,'splittable' => $res->splittable,'volumetric_weight'=>$res->volumetric_weight,'volweight_uom'=>$res->volweight_uom, 'createdby' => $user_id, 'createdon' => $cdate,'ldm'=>$res->ldm);
        $ins_cargo = $this->db->insert("tb_cargo_details",$cargo_ins);
        $cargo_row_ids[] = $this->db->insert_id();
        /*   array_push($ids,$cargo_row_id);*/
    }
}

}
$transport        = array();
if($company_code == 'RUKN'){
    $gettrasnportmode = $this->db->query("SELECT code,name FROM tb_transportmode WHERE code IN ('LTL','FTL','GRP')");
    if ($gettrasnportmode->num_rows() > 0) {
        foreach ($gettrasnportmode->result() as $res) {
            $transport[] = array('code' => $res->code, 'name' => $res->name);
        }
    }
}else{
    $gettrasnportmode = $this->db->select("code,name")->get_where("tb_transportmode", array('status' => 1));
    if ($gettrasnportmode->num_rows() > 0) {
        foreach ($gettrasnportmode->result() as $res) {
            $transport[] = array('code' => $res->code, 'name' => $res->name);
        }
    }
}
$cargo_id = "";
if(!empty($cargo_row_ids)){
    $cargo_id = implode(',', $cargo_row_ids);
} $party_id = "";
if(!empty($party_row_ids)){
    $party_id = implode(',', $party_row_ids);
}
$order_details['cargo_id'] = $cargo_id;
$order_details['party_id'] = $party_id;
$data['transport']         = $transport;
$data['order_details']     = $order_details;
$data['pickup_details']    = $pickup_details;
$data['drop_details']      = $drop_details;
$data['shipper_details']   = $shipper_details;
$data['order_types']       = $order_types;
$data['delivery_array']    = $delivery_array;
$data['chargecodes']       = $chargecodes;
$data['roles']             = $roles;

$this->newtemplate->dashboard('orders/copyorder', $data);
}


public function editorder($id = null)
{
    $data          = $order_types          = array();
    $order_details = $shipper_details = $drop_details = $pickup_details = $delivery_array = $chargecodes = array();

    $order_details['type_name'] = $order_details['ordtype_code'] = "";
    if ($id != "") {
        $chkorder = $this->Order->getordertoedit($id);
        if ($chkorder->num_rows() > 0) {
            $incoterm    = $chkorder->row()->incoterm;
            $shipment_id = $pickup_inst = $delivery_inst = $container_no = "";
            $getdnote = $this->db->query("SELECT reference_id,ref_value FROM tb_order_references WHERE order_id ='".$id."' AND reference_id IN ('DQ','ORD_DLVINST','ORD_PIKINST','CTR')");
            if($getdnote->num_rows() >0){
                foreach($getdnote->result() as $ref){
                    $ref_id = $ref->reference_id;
                    if($ref_id == 'DQ'){
                        $shipment_id = $ref->ref_value;
                    }
                    if($ref_id == 'ORD_DLVINST'){
                        $delivery_inst = $ref->ref_value;
                    }
                    if($ref_id == 'ORD_PIKINST'){
                        $pickup_inst = $ref->ref_value;
                    }
                    if($ref_id == 'CTR'){
                        $container_no = $ref->ref_value;
                    }
                    
                }
            }
            $pickup_custid = $chkorder->row()->pickup_custid;
            $status        = $chkorder->row()->status;
            $trip_id = $chkorder->row()->trip_id;
            $trip_sts = $chkorder->row()->trip_sts;
            $order_status = "PENDING";
            if($trip_id != 0 && $trip_sts == 0){
                $order_status = 'ACTIVE';
            }
            if($trip_id != 0 && $trip_sts == 1){
                $order_status = 'CLOSED';
            }
            $chkdate = '2020-07-01 00:00:00';
            $createdon = $chkorder->row()->createdon;
            $order_str = strtotime($createdon);
            $chk_str = strtotime($chkdate);
            $early_pickup = $chkorder->row()->pickup_datetime;
            
            $early_delivery = $chkorder->row()->delivery_datetime;
            $late_pickup = $chkorder->row()->pickup_endtime;
            $late_delivery = $chkorder->row()->drop_endtime;
            $curtz = $this->session->userdata("usr_tzone")['timezone'];
            if($order_str > $chk_str){
                if($early_pickup != "" && $early_pickup != "0000-00-00 00:00:00"){
                    $epickup = getdatetimebytimezone($curtz,$early_pickup,DFLT_TZ);
                    $early_pickup = $epickup['datetime'];
                } 
                if($early_delivery != "" && $early_delivery != "0000-00-00 00:00:00"){
                    $edelivery = getdatetimebytimezone($curtz,$early_delivery,DFLT_TZ);
                    $early_delivery = $edelivery['datetime'];
                } 
                if($late_pickup != "" && $late_pickup != "0000-00-00 00:00:00"){
                    $lpickup = getdatetimebytimezone($curtz,$late_pickup,DFLT_TZ);
                    $late_pickup = $lpickup['datetime'];
                }
                if($late_delivery != "" && $late_delivery != "0000-00-00 00:00:00"){
                    $ldelivery = getdatetimebytimezone($curtz,$late_delivery,DFLT_TZ);
                    $late_delivery = $ldelivery['datetime'];
                } 

            }
            $order_details = array('id' => $chkorder->row()->id, 'order_id' => $chkorder->row()->order_id, 'shipment_id' => $shipment_id, 'early_pickup' => $early_pickup, 'early_delivery' => $early_delivery, 'late_pickup' => $late_pickup, 'late_delivery' => $late_delivery, 'product' => $chkorder->row()->product, 'service' => $chkorder->row()->service, 'delivery_term' => $chkorder->row()->delivery_term, 'incoterm' => $chkorder->row()->incoterm, 'delivery_note' => $chkorder->row()->delivery_note, 'purchase_order' => $chkorder->row()->purchase_order, 'notify_party' => $chkorder->row()->notify_party, 'goods_value' => $chkorder->row()->goods_value, 'lane_reference' => $chkorder->row()->lane_reference, 'distance' => $chkorder->row()->distance, 'customs_required' => $chkorder->row()->customs_required, 'high_cargo_value' => $chkorder->row()->high_cargo_value, 'valorance_insurance' => $chkorder->row()->valorance_insurance, 'temperature_control' => $chkorder->row()->temperature_control, 'company_code' => $chkorder->row()->company_code, 'branch_code' => $chkorder->row()->branch_code, 'department_code' => $chkorder->row()->department_code, 'createdon' => $chkorder->row()->createdon, 'order_type' => $chkorder->row()->order_type, 'transport_mode' => $chkorder->row()->transport_mode,'pickup_inst'=>$pickup_inst,'delivery_inst'=>$delivery_inst,'container_no'=>$container_no);
            $order_details['order_status'] = $order_status;
            if ($incoterm != '') {
                $delivery_array = getDeliverytermsbyIncoterm($incoterm);
            }
            $pickup_id    = $chkorder->row()->customer_id;
            $vendor_id    = $chkorder->row()->vendor_id;
            $user_id = $this->session->userdata('user_id');
            $pickup_location = array('country'=>$chkorder->row()->pickup_country,'zipcode'=>$chkorder->row()->pickup_pincode,'user_id'=>$user_id);
            $delivery_location = array('country'=>$chkorder->row()->delivery_country,'zipcode'=>$chkorder->row()->delivery_pincode,'user_id'=>$user_id);
            $info = array('order_id'=>$id,'product'=>$chkorder->row()->product);
            $data['rates'] = $this->ratemanagement->getcustomerprofiledetailsbyid($pickup_id,$chkorder->row()->service,$pickup_location,$delivery_location,$info);
            $data['vendor_rates'] = $this->ratemanagement->getvendorprofiledetailsbyid($vendor_id,$chkorder->row()->service,$info);
            $company_code = $this->session->userdata('company_code');
            if ($company_code != "") {
                $company_code = $chkorder->row()->company_code;
            }
            $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types", array('customer_id' => $pickup_id,'company_code'=>$company_code,'status'=>'1'));
            if ($getorders->num_rows() > 0) {
                foreach ($getorders->result() as $res) {
                    $order_types[] = array('type_id' => $res->id, 'type_name' => $res->type_name);
                }
            }else{
                $getorder_types = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types", array('company_code' => $company_code, 'status' => 1));
                if ($getorder_types->num_rows() > 0) {
                   foreach ($getorder_types->result() as $res) {
                       $order_types[] = array('type_name' => $res->type_name, 'type_id' => $res->id);
                   }
               }else{
                  $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types",array('company_code'=>"SGKN","status"=>1));
                  if($getorders->num_rows() >0){
                      foreach($getorders->result() as $res){
                       $order_types[] = array('type_id'=>$res->id,'type_name'=>$res->type_name);
                   }
               }
           }
       }
       
       $getpickupdetails = $this->Order->getpickupdetails($pickup_id);
       if ($getpickupdetails->num_rows() > 0) {
        $pickup_details = array('id' => $getpickupdetails->row()->id, 'name' => $getpickupdetails->row()->name, 'party_id' => $getpickupdetails->row()->code, 'address' => $getpickupdetails->row()->address, 'pincode' => $getpickupdetails->row()->pincode, 'country' => $getpickupdetails->row()->country);
    }
    $drop_id        = $chkorder->row()->drop_custid;
    $drop_row_id    = 0;
    $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name, p.mobile, p.email,p.code,p.fax,o.party_type FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1  WHERE p.status=1 AND o.order_id='$id' GROUP BY o.party_type");
    if($chekparty->num_rows() >0){
      foreach($chekparty->result() as $rr){
        $ptype = $rr->party_type;
        $chktype = $this->db->select("name")->get_where("tbl_party_types",array("id"=>$ptype),1,0);
        if($chktype->num_rows()>0){
          if($chktype->row()->name == "Consignee"){
            $drop_details = array('id'=>$rr->id,'name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
        }else if($chktype->row()->name == "Shipper"){
           $shipper_details = array('id'=>$rr->id,'name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
       }
   }
}
}
$shipper_details['name'] = $chkorder->row()->pickup;
$shipper_details['street'] = $chkorder->row()->pickup_address1;
$shipper_details['state'] = $chkorder->row()->pickup_address2;
$shipper_details['city'] = $chkorder->row()->pickup_city;
$shipper_details['country'] = $chkorder->row()->pickup_country;
$shipper_details['pincode'] = $chkorder->row()->pickup_pincode;

$drop_details['name'] = $chkorder->row()->delivery;
$drop_details['street'] = $chkorder->row()->delivery_address1;
$drop_details['state'] = $chkorder->row()->delivery_address2;
$drop_details['city'] = $chkorder->row()->delivery_city;
$drop_details['country'] = $chkorder->row()->delivery_country;
$drop_details['pincode'] = $chkorder->row()->delivery_pincode;
$drop_id = $chkorder->row()->drop_custid;
}

$roles = array();
$qyery=$this->db->query("SELECT id,name FROM tbl_party_types WHERE user_id= '".$user_id."' AND status=1 GROUP BY name");
if($qyery->num_rows() >0){
    foreach($qyery->result() as $res){
        $roles[] = array('id'=>$res->id,'name'=>$res->name);
    }
}
$getchargecodes = $this->db->select("id,charge_code")->get_where("tb_charge_codes", array('status' => '1'));
if ($getchargecodes->num_rows() > 0) {
    foreach ($getchargecodes->result() as $res) {
        $chargecodes[] = array('charge_id' => $res->id, 'charge_code' => $res->charge_code);
    }
}
}
$vas_ids = array();
$getvas_ids = $this->db->select("id,vas_id,vas_name")->get_where("tb_vas_master", array('status' => '1'));
if ($getvas_ids->num_rows() > 0) {
    foreach ($getvas_ids->result() as $res) {
        $vas_ids[] = array('vas_row_id' => $res->id, 'vas_id' => $res->vas_id."-".$res->vas_name);
    }
}
$transport        = array();
if($company_code == 'RUKN'){
    $gettrasnportmode = $this->db->query("SELECT code,name FROM tb_transportmode WHERE code IN ('LTL','FTL','GRP')");
    if ($gettrasnportmode->num_rows() > 0) {
        foreach ($gettrasnportmode->result() as $res) {
            $transport[] = array('code' => $res->code, 'name' => $res->name);
        }
    }
}else{
    $gettrasnportmode = $this->db->select("code,name")->get_where("tb_transportmode", array('status' => 1));
    if ($gettrasnportmode->num_rows() > 0) {
        foreach ($gettrasnportmode->result() as $res) {
            $transport[] = array('code' => $res->code, 'name' => $res->name);
        }
    }
}
$data['stoppagecodes'] = $data['resolutioncodes'] = array();
$select = "id,code";
$getstoppage = $this->Order->getmasters('tbl_stoppage_master',$select);          
if($getstoppage->num_rows() > 0){
    foreach ($getstoppage->result()  as $res) {
      $data['stoppagecodes'][]  = array('id'=>$res->id,'code'=>$res->code); 
  }  
}
$select = "id,name";
$getresolution = $this->Order->getmasters('tbl_resolution_master',$select);
if($getresolution->num_rows() > 0){
    foreach ($getresolution->result()  as $res) {
        $data['resolutioncodes'][]  = array('id'=>$res->id,'name'=>$res->name); 
    }  
}
$data['transport']         = $transport;
$data['order_details']     = $order_details;
$data['pickup_details']    = $pickup_details;
$data['drop_details']      = $drop_details;
$data['shipper_details']   = $shipper_details;
$data['order_types']       = $order_types;
$data['delivery_array']    = $delivery_array;
$data['chargecodes']       = $chargecodes;
$data['roles']             = $roles;
$data['vas_ids']         = $vas_ids;
        //  $data['cargos'] = $cargos;

$this->newtemplate->dashboard('orders/editorder', $data);
$this->getorderdetails($id);
}

public function viewroletypelist()
{
    $data = array();
    $type = isset($_POST['type']) ? $_POST['type'] : "";
    if ($type != "") {
        $user_id = $this->session->userdata('user_id');
        if ($user_id != '0') {
            if($type == "Vendor"){
                $type = "Carrier";
            }

            $this->db->select("m.id,m.name,m.email,m.code,m.company_code,m.branch_code,m.country,m.location_id,m.street");
            $this->db->from("tbl_party_master m");
            $this->db->join("tbl_party_types p", "p.id=m.party_type_id", "LEFT");
            if($type == "Overseas OL"){
                $this->db->where('m.category_type','Overseas OL');
            } else if($type == "Internal BU"){
                $this->db->where('m.category_type','KN Office');
            } else {
                $this->db->like("p.name", $type);
            }
            $this->db->where("m.user_id", $user_id);
            $where = "m.acon_debitor_code is  NOT NULL";
            $this->db->where($where);
            $getroles = $this->db->get();
            if ($getroles->num_rows() > 0) {
                foreach ($getroles->result() as $res) {
                    $check = "";
                    if($type == "Customer"){
                        $check = "<input class='rolelist' type='radio' name='selectrole' id='rolelist_" . $res->id . "' value='" . $res->code . "' onchange=selectrolebyid(" . $res->id . ")>";
                    }
                    if($type == "Carrier"){
                        $check = "<input class='vendorlist' type='radio' name='selectvendor' id='vendorlist_" . $res->id . "' value='" . $res->code . "' onchange=selectvendorbyid(" . $res->id . ")>";
                    }
                    if($type == "Overseas OL" || $type == "Internal BU"){
                        $check = "<input class='twopartieslist' type='radio' name='selectparties' id='twopartieslist_" . $res->id . "' value='" . $res->code . "' onchange=selectpartiesbyid(" . $res->id . ")>";
                    }
                    $data[] = array('check' => $check, 'id' => $res->id, 'code' => $res->code, 'name' => $res->name, 'email_id' => $res->email, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code,'country'=>$res->country,'street'=>$res->street,'city'=>$res->location_id);
                }
            }

        }
    }
    echo json_encode($data);
}

public function getorderstatusdetails()
{


    $status   = array();
    $order_id = $this->input->post('order_id');
    if ($order_id != "") {
        $getshiftid = $this->db->select("order_id,shift_id")->get_where("tb_orders", array('id' => $order_id));
        if ($getshiftid->num_rows() > 0) {
         $getorderstatus = $this->db->query("select o.id,o.latitude,o.longitude,o.status_code,o.status_date,o.createdon,s.status_name from tb_order_status o,tb_status_master s where order_id='".$order_id."' AND o.status_id=s.id AND o.status ='1'");
         if($getorderstatus->num_rows() >0){
            foreach($getorderstatus->result() as $res){
                $location_name = getLocationName($res->latitude,$res->longitude);
                $location = '"'.$location_name.'"';
                $code = $res->status_code."-".$res->status_name;
                $status_code = '"'.$res->status_code.'"';
                $status_name = '"'.$res->status_name.'"';
                $date = '"'.$res->status_date.'"';
                $type = "order_sts";
                $stop_type = "P";
                $sts_value = '"'.$res->status_code.'_'.$stop_type.'_'.$status_name.'"';
                $sts_name = '"'.$stop_type.'-'.$status_name.'"';
                $action   = "<ul class='nav nav-tabs'><li class='dropdown tablebtnrleft'> <a class='dropdown-toggle' data-toggle='dropdown' href='#'><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'><li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowAddstatus(this);'><span class='glyphicon glyphicon-plus' > </span>Add Status</a></li><li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowEditStatus(this,".$res->id.",".$status_code.",".$location.",".$date.",".$sts_value.",".$sts_name.");'><span class='glyphicon glyphicon-pencil' > </span>Edit</a></li></ul></li></ul>";
                $status[] = array('id' => $res->id, 'status_name' => $res->status_name, 'date' => $res->createdon, 'action' => $action,'stop_id'=>$res->status_code,'status_type'=>$stop_type,'location'=>$location_name);

            }
        }
        $bookingid = $getshiftid->row()->order_id;
        $shift_id = $getshiftid->row()->shift_id;
        if ($shift_id != "0") {
            $curtz     = $this->session->userdata("usr_tzone")['timezone'];
            $getstatus = $this->db->query("SELECT ts.id,ts.latitude,ts.longitude,ts.loc_name,ts.stop_id,ts.stop_type,ts.status_code,convertToClientTZ(ts.createdon,'".$curtz."') as createdon,sm.status_name from tb_stop_status ts,tb_status_master sm,tb_employee e WHERE sm.id=ts.status_id AND ts.shipment_id=e.shift_id AND e.order_id='".$bookingid."' AND ts.shipment_id = '$shift_id' AND (ts.stop_detail_id=0 OR ts.stop_detail_id=e.id) GROUP BY ts.id ORDER BY ts.id ASC");
            if ($getstatus->num_rows() > 0) {
                foreach ($getstatus->result() as $res) {
                    $location_name = $res->loc_name;
                    if($location_name == ""){
                        $location_name = getLocationName($res->latitude,$res->longitude);
                    }
                    $location = '"'.$location_name.'"';
                    $status_code = '"'.$res->status_code.'"';
                    $status_name = '"'.$res->status_name.'"';
                    $stop_type = $res->stop_type;
                    if($stop_type == ""){
                        $stop_type = "P";
                    }
                    if($status_code == 'KN007'){
                        $name = 'PickUp'; 
                    }else{
                        $name = $res->status_name;
                    }
                    $sts_value = '"'.$res->status_code.'_'.$stop_type.'_'.$name.'"';
                    $date = '"'.$res->createdon.'"';
                    $sts_name = '"'.$stop_type.'-'.$name.'"';
                    $action   = "<ul class='nav nav-tabs'><li class='dropdown tablebtnrleft'> <a class='dropdown-toggle' data-toggle='dropdown' href='#'><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'><li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowAddstatus(this);'><span class='glyphicon glyphicon-plus' > </span>Add Status</a></li><li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowEditStatus(this,".$res->id.",".$status_code.",".$location.",".$date.",".$sts_value.",".$sts_name.");'><span class='glyphicon glyphicon-pencil' > </span>Edit</a></li></ul></li></ul>";
                    $status[] = array('id' => $res->id, 'lattitude' => $res->latitude, 'longitude' => $res->longitude, 'stop_id' => $res->status_code, 'status_name' => $res->status_name, 'date' => $res->createdon, 'action' => $action,'location'=>$location_name,'status_type'=>$stop_type);
                }

            }
        }
    }
}
echo json_encode($status);
}

public function orddocsdetails(){
    $status = array();
    $order_id = $this->input->post('order_id');
    if ($order_id != "") {
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $sql1 = $this->db->query("SELECT ts.id,ts.latitude,ts.longitude,ts.stop_id,ts.stop_type,dt.type_name,ts.createdby,convertToClientTZ(ts.createdon,'".$curtz."') as createdon,ts.imgpath from tb_pod_uploads ts LEFT JOIN tb_document_types dt ON dt.id=ts.doc_type WHERE ts.order_id = $order_id AND ts.status='1' GROUP BY ts.id");
        if($sql1->num_rows()>0){
            foreach($sql1->result() as $res){
                $location_name = getLocationName($res->latitude,$res->longitude);
                $imgpath = "";
                $allowed =  array('pdf');
                if($res->imgpath != ""){
                    $ext = pathinfo($res->imgpath, PATHINFO_EXTENSION); 
                    $imglink = checkimageintmsorknlmv($res->imgpath);
                    if($imglink != ""){
                        if(in_array($ext,$allowed)){ 
                            $path = '<a target="_blank" href="'.$imglink.'"> <img src="'.base_url('assets/img/docstore.png').'" class="img-responsive" style="width: 10%;" type="application/pdf"></a>';
                        }else{
                            $path = '<a target="_blank" href="'.$imglink.'"><img src="'.$imglink.'" class="img-responsive" style="width: 10%;"></a>';
                        }
                    }
                    $driver = getDrivernameById($res->createdby)["name"];
                    $action = "";
                    $action     = "<ul class='nav nav-tabs'><li class='dropdown tablebtnrleft '> <a class='dropdown-toggle' data-toggle='dropdown' href='#''><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'><li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowdocAdd(this,".$order_id.");'><span class='glyphicon glyphicon-plus' > </span>Add Doc</a></li></li></ul>";
                    $status[] = array('id' => $res->id, 'type_name' => $res->type_name, 'imgpath' => $path,'stop_id' => $res->stop_id, 'stop_type' => $res->stop_type, 'date' => date("d M,y h:i A",strtotime($res->createdon)), 'action' => $action,'location'=>$location_name,'driver'=>$driver);
                }
                
            }
        }
    }
    echo json_encode($status);
}

public function addorderdoc(){
    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : "0";
    $data['stops'] = $data['document_types'] = array();
    $data['booking_id'] = $data['shift_id'] = $data['trip_id'] = $booking_id =  0;
    $data['company_code'] = $data['branch_code'] = "";
    if($order_id != "" && $order_id != '0'){
        $ord = $order_id;
        $getorderdetails = $this->db->select("id,order_id,company_code,branch_code,shift_id,trip_id")->get_where("tb_orders",array('id'=>$order_id));
        if($getorderdetails->num_rows()>0){
            $data['order_row_id'] = $getorderdetails->row()->id;
            $data['booking_id'] = $booking_id = $getorderdetails->row()->order_id;
            $data['company_code'] = $getorderdetails->row()->company_code;
            $data['branch_code'] = $getorderdetails->row()->branch_code;
            $data['shift_id'] = $getorderdetails->row()->shift_id;
            $data['trip_id'] = $getorderdetails->row()->trip_id;
        }
        $sql = "SELECT o.id,o.stopname FROM tb_shiporder_stops o,tb_employee e WHERE o.shipment_id=e.shift_id AND e.order_id='".$booking_id."' AND o.status=1 AND e.status=1 ORDER BY o.ordernumber ASC";
        $stops = $this->db->query($sql);
        if ($stops->num_rows() > 0) {
            $data['stops'] = $stops->result_array(); 
            $data['document_types'] = $this->db->select("id,type_name")->get_where("tb_document_types",array("status"=>1))->result_array();
        }else{
            $data['document_types'] = $this->db->select("id,type_name")->get_where("tb_document_types",array("status"=>1,"type_name"=>'Others'))->result_array();
        }        
    }
    echo json_encode($data);
}

public  function checktripfororder()
{
    $company_code = $this->session->userdata('company_code');
    $order_id = $this->input->post('order_id');
    $trip_id = $shift_id = $booking_id = 0;
    $chkorder = $this->db->select("order_id,shift_id,trip_id")->get_where("tb_orders",array('id'=>$order_id));
    if($chkorder->num_rows() >0){
        $shift_id = $chkorder->row()->shift_id;
        $trip_id = $chkorder->row()->trip_id;
        $booking_id = $chkorder->row()->order_id; 
    }
    $empid = 0;
    $getempid= $this->db->select("id")->get_where("tb_employee",array('order_id'=>$booking_id));
    if($getempid->num_rows() >0){
        $empid = $getempid->row()->id;
    }
    $dshipid = $pshipid = $dstopdetailsid = $pstopdetailsid = 0;
    $select = '<select name="status_name" onchange="getstatusnamebyid(this);" class="form-control" id="status_name"><option value="">Select</option><option value="1012_P_Custom Clearence">Custom Clearence</option></select>';
    if($shift_id != '0' && $empid != '0'){
        $accept_sts = 0;
        $chekaccept = $this->db->select("id")->get_where("tb_stop_status",array('shipment_id'=>$shift_id,'status_id'=>'10'));
        if($chekaccept->num_rows()>0){
            $accept_sts = '1';
        }
        $custom_sts = 0;
        $chkorderstatus = $this->db->select('id')->get_where("tb_order_status",array('order_id'=>$order_id,'status_id'=>'12'));
        if($chkorderstatus->num_rows()>0){
            $custom_sts = '1';
        }
        $chkstops = $this->db->query("SELECT s.id,s.stoptype,s.ordernumber,e.id as stopdetailsid,s.status FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$shift_id AND s.status=1 AND s.id=e.stop_id AND e.status=1 AND e.id=$empid UNION SELECT s.id,s.stoptype,s.ordernumber,e.id as stopdetailsid,s.status FROM tb_shiporder_stops s,tb_employee e WHERE s.shipment_id=$shift_id AND s.status=1 AND s.id=e.drop_stopid AND e.status=1 AND e.id=$empid");
        if($chkstops->num_rows() >0){
            $select = '<select name="status_name" onchange="getstatusnamebyid(this);" class="form-control" id="status_name"><option value="">Select</option>';
            foreach ($chkstops->result() as $res) {
                if($res->stoptype == "P"){
                    $pshipid = $res->id;
                    $pstopdetailsid = $res->stopdetailsid;
                    $pickups = array();
                    $chktype = $this->db->select('status_code')->get_where("tb_stop_status",array('shipment_id'=>$shift_id,'status'=>'1','stop_type'=>'P'));
                    if($chktype->num_rows() >0){
                        foreach ($chktype->result() as $row) {
                            $pickups[] = $row->status_code; 
                        }
                    }
                    if($company_code != "UKKN" && $company_code != "AUKN"){
                        if($custom_sts == '1'){
                            $custom = "disabled";
                        }else{
                            $custom = "";
                        }
                        if($accept_sts == '1'){
                            $accept = "disabled";
                        }else{
                            $accept = "";
                        }
                        if(in_array('KN002',$pickups)){
                            $gatein = "disabled";
                        }else{
                            $gatein = "";
                        }
                        if(in_array('KN007',$pickups)){
                            $pickup = "disabled";
                        }else{
                            $pickup = "";
                        }
                        if(in_array('KN001',$pickups)){
                            $gateout = "disabled";
                        }else{
                            $gateout = "";
                        }if(in_array('KN005',$pickups)){
                            $it = "disabled";
                        }else{
                            $it = "";
                        }if(in_array('RY',$pickups)){
                            $carrived = "disabled";
                        }else{
                            $carrived = "";
                        }if(in_array('ON',$pickups)){
                            $copened = "disabled";
                        }else{
                            $copened = "";
                        }if(in_array('ED',$pickups)){
                            $cclosed = "disabled";
                        }else{
                            $cclosed = "";
                        }if(in_array('ER',$pickups)){
                            $cdeparted = "disabled";
                        }else{
                            $cdeparted = "";
                        }if(in_array('TL',$pickups)){
                            $outbount_started = "disabled";
                        }else{
                            $outbount_started = "";
                        }if(in_array('CL',$pickups)){
                            $outbount_ended = "disabled";
                        }else{
                            $outbount_ended = "";
                        }if(in_array('LY',$pickups)){
                            $outbount_departed = "disabled";
                        }else{
                            $outbount_departed = "";
                        }
                        $select .= '<option value="1012_P_Custom" '.$custom.'>Custom Clearence</option>';
                        $select .= '<option value="0212_P_Accept" '.$accept.'>O-Accept</option>';
                        $select .= '<option value="KN002_P_Gate IN" '.$gatein.'>O-Gate IN</option>';
                        $select .= '<option value="KN007_P_Pickup" '.$pickup.'>O-Pickup</option>';
                        $select .= '<option value="KN001_P_Gate Out" '.$gateout.'>O-Gate Out</option>';
                        $select .= '<option value="KN005_P_In-Transit" '.$it.'>O-In-Transit</option>';
                        $select .= '<option value="RY_P_Container Arrived" '.$carrived.'>P-Container Arrived</option>';
                        $select .= '<option value="ON_P_Container Opened" '.$copened.'>P-Container Opened</option>';
                        $select .= '<option value="ED_P_Container Closed" '.$cclosed.'>P-Container Closed</option>';
                        $select .= '<option value="ER_P_Container Departed" '.$cdeparted.'>P-Container Departed</option>';
                        $select .= '<option value="TL_P_Outbound Trailer Started" '.$outbount_started.'>P-Outbound Trailer Started</option>';
                        $select .= '<option value="CL_P_Outbound Tailer Ended" '.$outbount_ended.'>P-Outbound Tailer Ended</option>';
                        $select .= '<option value="LY_P_Outbound Trailer Departed" '.$outbount_departed.'>P-Outbound Trailer Departed</option>';
                    }else{
                        if(in_array('RY',$pickups)){
                            $carrived = "disabled";
                        }else{
                            $carrived = "";
                        }if(in_array('ON',$pickups)){
                            $copened = "disabled";
                        }else{
                            $copened = "";
                        }if(in_array('ED',$pickups)){
                            $cclosed = "disabled";
                        }else{
                            $cclosed = "";
                        }if(in_array('ER',$pickups)){
                            $cdeparted = "disabled";
                        }else{
                            $cdeparted = "";
                        }if(in_array('TL',$pickups)){
                            $outbount_started = "disabled";
                        }else{
                            $outbount_started = "";
                        }if(in_array('CL',$pickups)){
                            $outbount_ended = "disabled";
                        }else{
                            $outbount_ended = "";
                        }if(in_array('LY',$pickups)){
                            $outbount_departed = "disabled";
                        }else{
                            $outbount_departed = "";
                        }
                        $cmwhr = "UKKN";
                        if($company_code == "AUKN"){
                            $cmwhr = "UKKN";
                        }
                        $stsqry = $this->db->select('id,status_name,status_code')->get_where("tb_status_master",array("company_code"=>$cmwhr,"status"=>1));
                        if($stsqry->num_rows()>0){
                            foreach($stsqry->result_array() as $stsrow){
                                $getsts = "";
                                if($stsrow['status_code'] == "RY"){
                                    $getsts = $carrived;
                                }else if($stsrow['status_code'] == "ON"){
                                    $getsts = $copened;
                                }else if($stsrow['status_code'] == "ON"){
                                    $getsts = $copened;
                                }else if($stsrow['status_code'] == "ED"){
                                    $getsts = $cclosed;
                                }else if($stsrow['status_code'] == "ER"){
                                    $getsts = $cdeparted;
                                }else if($stsrow['status_code'] == "TL"){
                                    $getsts = $outbount_started;
                                }else if($stsrow['status_code'] == "CL"){
                                    $getsts = $outbount_ended;
                                }else if($stsrow['status_code'] == "LY"){
                                    $getsts = $outbount_departed;
                                }
                                $select .= '<option value="'.$stsrow['status_code'].'" '.$getsts.'>'.$stsrow['status_name'].'</option>';
                            }
                        }
                        /*$select .= '<option value="1012_P_Custom Clearence" '.$custom.'>Custom Clearence</option>';
                        $select .= '<option value="0212_P_Accept" '.$accept.'>O-Accept</option>';
                        $select .= '<option value="KN002_P_Gate IN" '.$gatein.'>O-Gate IN</option>';
                        $select .= '<option value="KN007_P_Pickup" '.$pickup.'>O-Pickup</option>';
                        $select .= '<option value="KN001_P_Gate Out" '.$gateout.'>O-Gate Out</option>';
                        $select .= '<option value="KN005_P_In-Transit" '.$it.'>O-In-Transit</option>';
                        $selec
                        $select .= '<option value="RY_P_Container Arrived" '.$carrived.'>P-Container Arrived</option>';
                        $select .= '<option value="ON_P_Container Opened" '.$copened.'>P-Container Opened</option>';
                        $select .= '<option value="ED_P_Container Closed" '.$cclosed.'>P-Container Closed</option>';
                        $select .= '<option value="ER_P_Container Departed" '.$cdeparted.'>P-Container Departed</option>';
                        $select .= '<option value="TL_P_Outbound Trailer Started" '.$outbount_started.'>P-Outbound Trailer Started</option>';
                        $select .= '<option value="CL_P_Outbound Tailer Ended" '.$outbount_ended.'>P-Outbound Tailer Ended</option>';
                        $select .= '<option value="LY_P_Outbound Trailer Departed" '.$outbount_departed.'>P-Outbound Trailer Departed</option>';*/
                    }
                }
                if($company_code != 'UKKN' && $company_code != "AUKN"){
                    if($res->stoptype == "D"){
                        $drops = array();
                        $chktype = $this->db->select('status_code')->get_where("tb_stop_status",array('shipment_id'=>$shift_id,'status'=>'1','stop_type'=>'D'));
                        if($chktype->num_rows() >0){
                            foreach ($chktype->result() as $row) {
                                $drops[] = $row->status_code; 
                            }
                        }
                        $dshipid = $res->id;
                        $dstopdetailsid = $res->stopdetailsid;
                        if(in_array('KN002',$drops)){
                            $gatein = "disabled";
                        }else{
                            $gatein = "";
                        }
                        if(in_array('KN007',$drops)){
                            $pickup = "disabled";
                        }else{
                            $pickup = "";
                        }
                        if(in_array('KN001',$drops)){
                            $gateout = "disabled";
                        }else{
                            $gateout = "";
                        }
                        $cmwhr = "UKKN";
                        if($company_code == "AUKN"){
                            $cmwhr = "UKKN";
                        }
                        $stsqry = $this->db->query("select id,status_name,status_code from tb_status_master where company_code='".$cmwhr."' AND status_code IN('TL','CL','LY') AND status=1 ");
                        if($stsqry->num_rows()>0){
                            foreach($stsqry->result_array() as $stsrow1){
                                $getsts = "";
                                if($stsrow1['status_code'] == "TL"){
                                    $getsts = $outbount_started;
                                }else if($stsrow1['status_code'] == "CL"){
                                    $getsts = $outbount_ended;
                                }else if($stsrow1['status_code'] == "LY"){
                                    $getsts = $outbount_departed;
                                }
                                $select .= '<option value="'.$stsrow1['status_code'].'" '.$getsts.'>'.$stsrow1['status_name'].'</option>';
                            }
                        }
                    }
                }
                
            }
        }
        $select .= "</select>";
    }
    $response = array('select'=>$select,'pshipid'=>$pshipid,'dshipid'=>$dshipid,'dstopdetailsid'=>$dstopdetailsid,'pstopdetailsid'=>$pstopdetailsid);
    echo json_encode($response);
}

public function addstatus($id = null){
    $curtz = $this->session->userdata("usr_tzone")['timezone'];
    $logdate = date('Y-m-d H:i:s');
    $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
    $curdt = $getactual['datetime'];
    $hrs = $this->session->userdata("usr_tzone")['hrs'];
    $order_id = isset($_POST['order_status_id']) ? $_POST['order_status_id'] : "0";
    $lattitude = $longitude = "";
    $booking_id = 0;
    if($order_id != "0"){
        $user_id = $this->session->userdata('user_id');
        if($user_id != ""){
            $getloc = $this->db->select('lat,lng')->get_where("tb_users",array('id'=>$user_id));
            if($getloc->num_rows() >0){
                $lattitude = $getloc->row()->lat;
                $longitude = $getloc->row()->lng;
            }
        }
        $shift_id = $trip_id = $ord_id = 0;
        $createdsource = "";
        $chkshft = $this->db->select("id,order_id,shift_id,trip_id,created_source")->get_where("tb_orders",array('id'=>$order_id));
        if($chkshft->num_rows() >0){
            $booking_id = $chkshft->row()->order_id;
            $shift_id = $chkshft->row()->shift_id;
            $trip_id = $chkshft->row()->trip_id;
            $createdsource = $chkshft->row()->created_source;
            $ord_id = $chkshft->row()->id;
        }
        $status_code = isset($_POST['status_code']) ? $_POST['status_code'] : "";
        $status_date = isset($_POST['status_date']) ? $_POST['status_date'] : "";
        if($status_date == ""){
            $status_date = date('Y-m-d H:i:s');
        }
        $pshipid = isset($_POST['pshipid']) ? $_POST['pshipid'] : "0";
        $dshipid = isset($_POST['dshipid']) ? $_POST['dshipid'] : "0";
        $pstopdetailsid = isset($_POST['pstopdetailsid']) ? $_POST['pstopdetailsid'] : "0";
        $dstopdetailsid = isset($_POST['dstopdetailsid']) ? $_POST['dstopdetailsid'] : "0";

        $sname = isset($_POST['status_name']) ? $_POST['status_name'] : "";
        $status_name = "";
        if($sname != ""){
            $sts = explode('_', $sname);
            $stop_type = 'P';
            if(!empty($sts)){
                $stop_type = isset($sts[1]) ? $sts[1] : "P";
                $status_name = isset($sts[2]) ? $sts[2] : "";
            }
        }            
        $getstatusid = $this->db->select("id")->get_where("tb_status_master",array('status_code'=>$status_code));
        $status_id = 0;
        if($getstatusid->num_rows() >0){
            $status_id = $getstatusid->row()->id;
        }
        if($this->session->userdata('company_code') == "UKKN" || $this->session->userdata('company_code') == "AUKN"){
            if($status_code != "TL" && $status_code != "CL" && $status_code != "LY"){
                if($id == ""){
                    $status_ar = array('order_id'=>$order_id,'status_id'=>$status_id,'latitude'=>$lattitude,'longitude'=>$longitude,'status_code'=>$status_code,'status'=>'1','status_date'=>$status_date);
                    /*$chksts = $this->db->select("id")->get_where("tb_order_status",array('order_id'=>$order_id,'status_id'=>$status_id,'status_code'=>$status_code));
                    if($chksts->num_rows() == 0){*/
                        $ins = $this->db->insert("tb_order_status",$status_ar);
                        if($ins){
                            /*send status xml to amazon*/
                            $this->load->library("amazonstatusintegration");
                            $postdata = array(
                                    "shipment_id" => $shift_id,
                                    "trip_id"     => $trip_id,
                                    "driver_id"   => 0,
                                    "vehicle_id"  => 0,
                                    "order_id"    => $booking_id,
                                    "user_id"    => $user_id,
                                    "stop_id"     => '',
                                    "latitude"    => $lattitude,
                                    "longitude"   => $longitude,
                                    "curtz"   => $curtz,
                                    "hrs" => $hrs,
                                    "web"  => $status_date,
                                    "status_code"  => $status_code,
                                    "ord_id"  => $order_id
                                );
                            $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                            echo "1";
                        }
                    /*}*/
                }else{
                    $upd = $this->db->where(array('id'=>$id))->update("tb_order_status",array('status_date'=>$status_date));
                    echo "1";
                }
            }
        }
        if($status_id == '12'){
            if($id == ""){
                $status_ar = array('order_id'=>$order_id,'status_id'=>$status_id,'latitude'=>$lattitude,'longitude'=>$longitude,'status_code'=>$status_code,'status'=>'1','status_date'=>$status_date);
                $ins = $this->db->insert("tb_order_status",$status_ar);
                if($ins){
                    echo "1";
                }
            }else{
                $upd = $this->db->where(array('id'=>$id))->update("tb_order_status",array('status_date'=>$status_date));

                echo "1";
            }
        }else{
            if($id == ""){
                $vehicle_id = 0;
                $contact_num = ""; 
                $driver_id = 0;
                $stop_id = $stopdetailid = 0;
                if($stop_type == 'P'){
                    $stop_id = $pshipid;
                    $stopdetailid = $pstopdetailsid;
                }else{
                    $stop_id = $dshipid;
                    $stopdetailid = $dstopdetailsid;
                }
                $gentime = getdatetimebytimezone(DFLT_TZ,$status_date,$curtz);
                $stsdate = $gentime['datetime'];
                $getvehicleid = $this->db->select("vehicle_id")->get_where("tb_shft_veh",array('shft_id'=>$shift_id));
                if($getvehicleid->num_rows() >0){
                    $vehicle_id = $getvehicleid->row()->vehicle_id;
                }
                $checktrip = $this->db->query("SELECT id,vehicle_id,driver_id,start_imei FROM tb_trips WHERE shift_id=$shift_id AND status=1");
                if($checktrip->num_rows()>0){
                    $contact_num = $checktrip->row()->start_imei;
                    $trip_id = $checktrip->row()->id;
                    $vehicle_id = $checktrip->row()->vehicle_id;
                    $driver_id = $checktrip->row()->driver_id;
                }
                if($trip_id == 0 && $vehicle_id != ""){
                    $checktrip1 = $this->db->query("SELECT d.vehicle_id,d.driver_id,d.imei FROM tbl_assigned_drivers d WHERE d.vehicle_id=$vehicle_id AND d.status=1 LIMIT 1");
                    if($checktrip1->num_rows() > 0){
                        $vehicle_id = $checktrip1->row()->vehicle_id;
                        $driver_id = $checktrip1->row()->driver_id;
                        $contact_num = $checktrip1->row()->imei;
                    }
                }
                if($this->session->userdata('company_code') == "UKKN" || $this->session->userdata('company_code') == "AUKN"){
                    if($status_code == "TL" || $status_code == "CL" || $status_code == "LY"){
                        if($status_code == "TL"){
                            $chqry1 = $this->db->select("id,trip_id")->get_where("tb_stop_status",array('order_id'=>$order_id,'shipment_id'=>$shift_id,"status_id"=>'10',"status"=>1),1,0);
                            if($chqry1->num_rows() == 0){
                                if($shift_id != ""){
                                    $status_id = '10';
                                    $trip_id == '0';
                                }
                            }else{
                                $trip_id = $chqry1->row()->trip_id;
                                $status_id = 2;
                                $stop_type == "P";
                            }
                        }else if($status_code == "CL"){
                            $chqry1 = $this->db->select("id,trip_id")->get_where("tb_stop_status",array('order_id'=>$order_id,'shipment_id'=>$shift_id,"status_id"=>'2',"status"=>1),1,0);
                            if($chqry1->num_rows() > 0){
                                $status_id = 1;
                                $stop_type == "P";
                            }
                        }else if($status_code == "LY"){
                            $chqry1 = $this->db->select("id,trip_id")->get_where("tb_stop_status",array('order_id'=>$order_id,'shipment_id'=>$shift_id,"status_id"=>'1',"status"=>1),1,0);
                            if($chqry1->num_rows() > 0){
                                $status_id = 3;
                                $stop_type == "P";
                            }
                        }
                    }
                }
                if($status_id == '10' && $trip_id == '0'){
                    $chqry = $this->db->select("id")->get_where("tb_trips",array('shift_id'=>$shift_id, 'vehicle_id'=>$vehicle_id, 'driver_id'=>$driver_id),1,0);
                    if($chqry->num_rows() == 0){
                        if($contact_num == ""){
                            $newimei = $this->db->select("imei")->get_where("tbl_assigned_drivers",array('vehicle_id'=>$vehicle_id,'driver_id'=>$driver_id,'status'=>1),1,0);
                            if($newimei->num_rows()>0){
                                $contact_num = $newimei->row()->imei;
                            }
                        }
                        $triparr = array('shift_id'=>$shift_id, 'vehicle_id'=>$vehicle_id, 'driver_id'=>$driver_id, 'stime'=>$stsdate, 'start_imei'=>$contact_num, 'splace'=>"", 'eplace'=>"", 'start_reading'=>0, 'end_reading'=>0, 'created_on'=>$stsdate, 'updated_on'=>$curdt, 'status'=>1, 'trip_type'=>0, 'transit_status'=>0);
                        $trip_id = $this->common->insertTableData('tb_trips', $triparr);
                        $insarry = array('order_id'=>$order_id,"shipment_id"=>$shift_id,"stop_id"=>0,"stop_detail_id"=>0,"stop_type"=>"","trip_id"=>$trip_id,"status_id"=>$status_id,"latitude"=>$lattitude,"longitude"=>$longitude,"status"=>1,"reason"=>"From Admin","vehicle_id"=>$vehicle_id,"driver_id"=>$driver_id,"status_code"=>$status_code,"createdon"=>$stsdate);
                        $ins = $this->db->insert("tb_stop_status",$insarry);
                        /* update orders table */
                        $ordwhr = array("shift_id"=>$shift_id);
                        $ordset = array("trip_id"=>$trip_id);
                        $upd = $this->db->set($ordset)->where($ordwhr)->update("tb_orders");

                        $postdata = array(
                            "shipment_id" => $shift_id,
                            "trip_id"     => $trip_id,
                            "driver_id"   => $driver_id,
                            "vehicle_id"    => $vehicle_id,
                            "order_id"    => $booking_id,
                            "user_id"    => $user_id,
                            "stop_id"     => '',
                            "latitude"    => $lattitude,
                            "longitude"   => $longitude,
                            "curtz"   => $curtz,
                            "hrs" => $hrs,
                            "web"  => '',
                            "status_code"=>$status_code,
                            "ord_id"  => $order_id,
                            
                        );
                        if($createdsource == "0"){
                            $sts = $this->statusintigration->roadlogshipmentconfirm($postdata);
                        }else if($createdsource == '9'){
                            $postdata['status_code'] = '0212';
                            $sts = $this->etrucknowquote->getstatusresponse($postdata);
                        }else if($createdsource == '13'){
                            $this->load->library("amazonstatusintegration");
                            $sts = $this->amazonstatusintegration->outboundTrailerASN($postdata);
                        }
                    }
                    echo '1';
                }else{
                    if($trip_id != "0" && $status_id != "11"){
                        $chqry = $this->db->select("id")->get_where("tb_stop_status",array("shipment_id"=>$shift_id,"stop_id"=>$stop_id,"stop_detail_id"=>$stopdetailid,"stop_type"=>$stop_type,"trip_id"=>$trip_id,"status_id"=>$status_id),1,0);
                        if($chqry->num_rows() == 0){
                            if($status_id == "2" && $stop_type == "P"){
                                $ttdata = array("id"=>$trip_id);
                                $data2["updated_on"] = $curdt;
                                $data2["transit_status"] = '1';
                                $res = $this->db->set($data2)->where($ttdata)->update("tb_trips");
                            }
                            $insarry = array("shipment_id"=>$shift_id,'order_id'=>$order_id,"stop_id"=>$stop_id,"stop_detail_id"=>$stopdetailid,"stop_type"=>$stop_type,"trip_id"=>$trip_id,"status_id"=>$status_id,"latitude"=>$lattitude,"longitude"=>$longitude,"status"=>1,"reason"=>"From Admin","vehicle_id"=>$vehicle_id,"driver_id"=>$driver_id,"status_code"=>$status_code,"createdon"=>$stsdate);
                            $ins = $this->db->insert("tb_stop_status",$insarry);
                            /*}*/
                            $chqry1 = $this->db->select("id")->get_where("tb_trip_employee",array("employee_id"=>$stopdetailid,"stop_id"=>$stop_id,"trip_id"=>$trip_id,"status"=>1),1,0);
                            if($chqry1->num_rows() == 0){
                                $insarr = array("employee_id"=>$stopdetailid,"stop_id"=>$stop_id,"trip_id"=>$trip_id,"status"=>1, 'driver_late'=>0, 'emp_late'=>0, 'stime'=>$curdt, 'check_in'=>$curdt, 'absent_reason'=>'Closed', 'created_on'=>$curdt, 'updated_on'=>$curdt, 'pd_status'=>1);
                                $ins = $this->db->insert("tb_trip_employee",$insarr);
                            }

                            $postdata = array(
                                "shipment_id" => $shift_id,
                                "trip_id"     => $trip_id,
                                "driver_id"   => $driver_id,
                                "stop_id"     => $stop_id,
                                "order_id"    => $booking_id,
                                "inc_id"      => 0,
                                "pod_type"    => '',
                                "latitude"    => $lattitude,
                                "longitude"   => $longitude,
                                "stop_type" => $stop_type,
                                "vehicle_id" => $vehicle_id,
                                "curtz" => $curtz,
                                "hrs" => $hrs,
                                "web"  => '',
                                "status_code"  => $status_code,
                                "ord_id"  => $order_id,
                                "user_id"    => $user_id,
                            );
                            if($createdsource == "0"){
                                if($status_id == "4"){
                                    /*$sts = $this->statusintigration->shipmentintransit($postdata);*/
                                    /*send to roadlog*/
                                    $sts = $this->statusintigration->roadlogshipmentintransit($postdata);
                                }
                                if($status_id == "2" && $stop_type == "P"){
                                    /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                                    $sts = $this->statusintigration->roadlogshipmentpgatein($postdata);
                                }
                                if($status_id == "1" && $stop_type == "P"){
                                    /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                                    $sts = $this->statusintigration->roadlogshipmentpicked($postdata);
                                }
                                if($status_id == "3" && $stop_type == "P"){
                                    /*send to roadlog*/
                                    $sts = $this->statusintigration->roadlogshipmentpgateout($postdata);
                                }
                                if($status_id == "2" && $stop_type == "D"){
                                    /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                                    $sts = $this->statusintigration->roadlogshipmentdgatein($postdata);
                                }
                                if($status_id == "1" && $stop_type == "D"){
                                    /*$sts = $this->statusintigration->shipmentdelivered($postdata);*/
                                    /*send to roadlog*/
                                    $sts = $this->statusintigration->roadlogshipmentdelivered($postdata);
                                }
                                if($status_id == "3" && $stop_type == "D"){
                                    /*send to roadlog*/
                                    $sts = $this->statusintigration->roadlogshipmentdgateout($postdata);
                                    log_message("error","web_gateout");
                                }
                            }else if($createdsource == "13"){
                                $this->load->library("amazonstatusintegration");
                                if($status_id == "2" && $stop_type == "P"){
                                    /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                                    $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                                }
                                if($status_id == "1" && $stop_type == "P"){
                                    /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                                    $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                                }
                                if($status_id == "3" && $stop_type == "P"){
                                    /*send to roadlog*/
                                    $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                                }
                                if($status_id == "2" && $stop_type == "D"){
                                    /*$sts = $this->statusintigration->shipmentorderpicked($postdata);*/
                                    $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                                }
                                if($status_id == "1" && $stop_type == "D"){
                                    /*$sts = $this->statusintigration->shipmentdelivered($postdata);*/
                                    /*send to roadlog*/
                                    $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                                }
                                if($status_id == "3" && $stop_type == "D"){
                                    /*send to roadlog*/
                                    $sts = $this->amazonstatusintegration->updateOrderStatus($postdata);
                                }
                            }else if($createdsource == '9'){
                                if($status_id == '4' || ($status_id == "1" && $stop_type == "P") || ($status_id == "3" && $stop_type == "D")){
                                    $postdata['status_code'] = "";
                                    if($status_id == '4'){
                                        $postdata['status_code'] = '1550';
                                    }
                                    if($status_id == '1' && $stop_type == 'P'){
                                        $postdata['status_code'] = '0500';
                                    }
                                    if($status_id == '3' && $stop_type == 'D'){
                                        $postdata['status_code'] = '3000';
                                    }
                                    $sts = $this->etrucknowquote->getstatusresponse($postdata);
                                }
                            }
                            else if($createdsource == '5'){
                               $postdata['status_code'] = $postdata['ord_id'] = "";
                               if($status_id == '1' && $stop_type == 'P'){
                                $postdata['status_code'] = '0500';
                            }
                            if($status_id == '3' && $stop_type == 'D'){
                                $postdata['status_code'] = '3000';
                            }
                            if($status_id == '1' && $stop_type == 'D'){
                                $postdata['status_code'] = '2300';
                            }
                            if(($postdata['status_code'] == "0500") || ($postdata['status_code'] == "2300" )|| ($postdata['status_code'] == "3000")){
                              $postdata['ord_id'] = $ord_id;
                              $sts = $this->statusintigration->salogshipmentstatus($postdata);
                          }
                          
                          
                      }
                  }
              }
              echo "1";
          }
      }else{
        $this->db->where(array('id'=>$id))->update("tb_stop_status",array('createdon'=>$status_date));
        echo "1";
    }

}


}

}


public function vieworder($id = null)
{
    $data          = array();
    $order_details = $shipper_details = $drop_details = $pickup_details = array();

    if ($id != "") {
        $chkorder = $this->Order->getordertoedit($id);
        if ($chkorder->num_rows() > 0) {
            $transport      = $chkorder->row()->transport_mode;
            $transport_mode = "";
            if ($transport != "") {

                $getmode = $this->db->select("name")->get_where("tb_transportmode", array('code' => $transport));
                if ($getmode->num_rows() > 0) {
                    $transport_mode = $getmode->row()->name;
                }
            }
            $shipment_id = $pickup_inst = $delivery_inst = $container_no ="";
            $getdnote = $this->db->query("SELECT reference_id,ref_value FROM tb_order_references WHERE order_id ='".$id."' AND reference_id IN ('DQ','ORD_DLVINST','ORD_PIKINST','CTR')");
            if($getdnote->num_rows() >0){
                foreach($getdnote->result() as $ref){
                    $ref_id = $ref->reference_id;
                    if($ref_id == 'DQ'){
                        $shipment_id = $ref->ref_value;
                    }
                    if($ref_id == 'ORD_DLVINST'){
                        $delivery_inst = $ref->ref_value;
                    }
                    if($ref_id == 'ORD_PIKINST'){
                        $pickup_inst = $ref->ref_value;
                    }
                    if($ref_id == 'CTR'){
                        $container_no = $ref->ref_value;
                    }
                    
                }
            }
            $pickup_custid               = $chkorder->row()->pickup_custid;
            $trip_id = $chkorder->row()->trip_id;
            $trip_sts = $chkorder->row()->trip_sts;
            $order_status = "PENDING";
            if($trip_id != 0 && $trip_sts == 0){
                $order_status = 'ACTIVE';
            }
            if($trip_id != 0 && $trip_sts == 1){
                $order_status = 'CLOSED';
            }
            $chkdate = '2020-07-01 00:00:00';
            $createdon = $chkorder->row()->createdon;
            $order_str = strtotime($createdon);
            $chk_str = strtotime($chkdate);
            $early_pickup = $chkorder->row()->pickup_datetime;
            
            $early_delivery = $chkorder->row()->delivery_datetime;
            $late_pickup = $chkorder->row()->pickup_endtime;
            $late_delivery = $chkorder->row()->drop_endtime;
            $curtz = $this->session->userdata("usr_tzone")['timezone'];
            if($order_str > $chk_str){
                if($early_pickup != "" && $early_pickup != "0000-00-00 00:00:00"){
                    $epickup = getdatetimebytimezone($curtz,$early_pickup,DFLT_TZ);
                    $early_pickup = $epickup['datetime'];
                } 
                if($early_delivery != "" && $early_delivery != "0000-00-00 00:00:00"){
                    $edelivery = getdatetimebytimezone($curtz,$early_delivery,DFLT_TZ);
                    $early_delivery = $edelivery['datetime'];
                } 
                if($late_pickup != "" && $late_pickup != "0000-00-00 00:00:00"){
                    $lpickup = getdatetimebytimezone($curtz,$late_pickup,DFLT_TZ);
                    $late_pickup = $lpickup['datetime'];
                }
                if($late_delivery != "" && $late_delivery != "0000-00-00 00:00:00"){
                    $ldelivery = getdatetimebytimezone($curtz,$late_delivery,DFLT_TZ);
                    $late_delivery = $ldelivery['datetime'];
                } 

            }
            $order_details               = array('id' => $chkorder->row()->id, 'order_id' => $chkorder->row()->order_id, 'shipment_id' => $shipment_id, 'order_status' => $order_status, 'early_pickup' => $early_pickup, 'early_delivery' => $early_delivery, 'late_pickup' => $late_pickup, 'late_delivery' => $late_delivery, 'product' => $chkorder->row()->product, 'incoterm' => $chkorder->row()->incoterm, 'delivery_note' => $chkorder->row()->delivery_note, 'purchase_order' => $chkorder->row()->purchase_order, 'notify_party' => $chkorder->row()->notify_party, 'goods_value' => $chkorder->row()->goods_value, 'lane_reference' => $chkorder->row()->lane_reference, 'distance' => $chkorder->row()->distance, 'customs_required' => $chkorder->row()->customs_required, 'high_cargo_value' => $chkorder->row()->high_cargo_value, 'valorance_insurance' => $chkorder->row()->valorance_insurance, 'temperature_control' => $chkorder->row()->temperature_control, 'company_code' => $chkorder->row()->company_code, 'branch_code' => $chkorder->row()->branch_code, 'department_code' => $chkorder->row()->department_code, 'createdon' => $chkorder->row()->createdon, 'transport_mode' => $transport_mode,'pickup_inst'=>$pickup_inst,'delivery_inst'=>$delivery_inst,'container_no'=>$container_no);
            $delivery_term               = "";
            $pickup_id                   = $chkorder->row()->customer_id;
            $ord_type                    = $chkorder->row()->order_type;
            $order_details['order_type'] = "";

            $getordertype = $this->db->select("type_name")->get_where("tb_order_types", array('id' => $ord_type, 'status' => '1','company_code'=>$chkorder->row()->company_code));
            if ($getordertype->num_rows() > 0) {
                $order_details['order_type'] = $getordertype->row()->type_name;
            }
            $delivery_term_id = $chkorder->row()->delivery_term;
            if ($delivery_term_id != "") {

                $getdelivery_term = $this->db->select("term_id,name")->get_where("tb_delivery_terms", array('term_id' => $delivery_term_id));
                if ($getdelivery_term->num_rows() > 0) {
                    $delivery_term = $getdelivery_term->row()->term_id . "-" . $getdelivery_term->row()->name;
                }
            }
            $service    = "";
            $service_id = $chkorder->row()->service;
            if ($service_id != "") {
                $getservice = $this->db->select("service_id,name")->get_where("tb_service_master", array('id' => $service_id));
                if ($getservice->num_rows() > 0) {
                    $service = $getservice->row()->service_id . "-" . $getservice->row()->name;
                }
            }
            $order_details['service']       = $service;
            $order_details['delivery_term'] = $delivery_term;

            $getpickupdetails = $this->db->select("id,name,address,pincode,code,country")->get_where("tb_customers", array('status' => 1, 'id' => $pickup_id));
            if ($getpickupdetails->num_rows() > 0) {
                $pickup_details = array('id' => $getpickupdetails->row()->id, 'name' => $getpickupdetails->row()->name, 'party_id' => $getpickupdetails->row()->code, 'address' => $getpickupdetails->row()->address, 'pincode' => $getpickupdetails->row()->pincode, 'country' => $getpickupdetails->row()->country);
            }
            $drop_id        = $chkorder->row()->drop_custid;
            $drop_row_id    = 0;
            $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name, p.mobile, p.email,p.code,p.fax,o.party_type FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1  WHERE p.status=1 AND o.order_id='$id' GROUP BY o.party_type");
            if($chekparty->num_rows() >0){
              foreach($chekparty->result() as $rr){
                $ptype = $rr->party_type;
                $chktype = $this->db->select("name")->get_where("tbl_party_types",array("id"=>$ptype),1,0);
                if($chktype->num_rows()>0){
                  if($chktype->row()->name == "Consignee"){
                    $drop_details = array('name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
                }else if($chktype->row()->name == "Shipper"){
                 $shipper_details = array('name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
             }
         }
     }
 }
 $shipper_details['name'] = $chkorder->row()->pickup;
 $shipper_details['street'] = $chkorder->row()->pickup_address1;
 $shipper_details['state'] = $chkorder->row()->pickup_address2;
 $shipper_details['city'] = $chkorder->row()->pickup_city;
 $shipper_details['country'] = $chkorder->row()->pickup_country;
 $shipper_details['pincode'] = $chkorder->row()->pickup_pincode;

 $drop_details['name'] = $chkorder->row()->delivery;
 $drop_details['street'] = $chkorder->row()->delivery_address1;
 $drop_details['state'] = $chkorder->row()->delivery_address2;
 $drop_details['city'] = $chkorder->row()->delivery_city;
 $drop_details['country'] = $chkorder->row()->delivery_country;
 $drop_details['pincode'] = $chkorder->row()->delivery_pincode;
 $drop_id = $chkorder->row()->drop_custid;
}
}
$data['order_details']     = $order_details;
$data['pickup_details']    = $pickup_details;
$data['drop_details']      = $drop_details;
$data['shipper_details']   = $shipper_details;

$this->newtemplate->dashboard('orders/vieworder', $data);
}

public function getshipperID()
{
    $parties      = array();
    $partytype_id = $this->input->post('partytype_id');
    $user_id      = $this->session->userdata('user_id');
    $company_code = $this->session->userdata('company_code');

    $where = "status = 1 AND code != '' AND company_code != '' AND company_code IS NOT NULL AND code is NOT NULL AND code != '0' AND user_id = '" . $user_id . "' AND code LIKE '%" . $partytype_id . "%'";
    $this->db->select("id,name,phone,code,email_id,company_code,branch_code");
    $this->db->from("tb_customers");
    $this->db->where($where);
    $this->db->group_by('id');
    $this->db->order_by('createdon', 'DESC');
    $chkqry = $this->db->get();

    if ($chkqry->num_rows() > 0) {
        foreach ($chkqry->result() as $res) {
            $parties[] = array('id' => $res->id, 'party_id' => $res->code, 'name' => $res->name, 'phone' => $res->phone, 'email' => $res->email_id, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code);
        }
    }
    if ($company_code != "") {
        $getorder_types = $this->db->select("id,type_name")->get_where("tb_order_types", array('company_code' => $company_code,'status'=>'1'));
        if ($getorder_types->num_rows() > 0) {
            foreach ($getorder_types->result() as $res) {
                $parties['ordparties'][] = array('type_id' => $res->id, 'type_name' => $res->type_name);
            }
        }
    }
    echo json_encode($parties);
}
public function getvendordetailsbyID()
{
    $list = array();
    $code = $this->input->post('id');
    if ($code != "") {
        $getvendordetails = $this->db->query("SELECT name,code FROM tb_vendors WHERE code LIKE '" . $code . "' AND status ='1'");

        if ($getvendordetails->num_rows() > 0) {
            $list = array('name' => $getvendordetails->row()->name, 'code' => $getvendordetails->row()->code);
        }
    }
    echo json_encode($list);
}

public function getshipperdetailsbyID()
{
    $parties      = array();
    $code         = $this->input->post('id');
    $customer_id  = "";
    $user_id      = $this->session->userdata('user_id');
    $company_code = $this->session->userdata('company_code');

    $chkqry = $this->db->select("id,name,phone,location,address,street,location,state,pincode,code,country,email_id,fax,company_code,branch_code")->get_where("tb_customers", array('code' => $code, 'user_id' => $user_id));
    if ($chkqry->num_rows() > 0) {
        $customer_id                   = $chkqry->row()->id;
        $parties['customer_details'][] = array('id' => $chkqry->row()->id, 'name' => $chkqry->row()->name, 'phone' => $chkqry->row()->phone, 'street' => $chkqry->row()->street, 'city' => $chkqry->row()->location, 'pincode' => $chkqry->row()->pincode, 'code' => $chkqry->row()->code, 'country' => $chkqry->row()->country, 'email_id' => $chkqry->row()->email_id, 'fax' => $chkqry->row()->fax, 'state' => $chkqry->row()->state, 'location' => $chkqry->row()->location, 'address' => $chkqry->row()->address, 'company_code' => $chkqry->row()->company_code, 'branch_code' => $chkqry->row()->branch_code);
    }

    $parties['ordparties'] = array();
    if ($customer_id != "") {
        $getorder_types = $this->db->select("id,type_name")->group_by('type_name')->get_where("tb_order_types", array('customer_id' => $customer_id,'status'=>'1','company_code'=>$company_code));
        if ($getorder_types->num_rows() > 0) {
            foreach ($getorder_types->result() as $res) {
                $parties['ordparties'][] = array('type_id' => $res->id, 'type_name' => $res->type_name);
            }
        }else{
            $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types",array('company_code'=>$company_code,"status"=>1));
            if($getorders->num_rows() >0){
              foreach($getorders->result() as $res){
                 $parties['ordparties'][]= array('type_id'=>$res->id,'type_name'=>$res->type_name);
             }
         }else{
          $getorders = $this->db->select("id,type_name")->group_by("type_name")->get_where("tb_order_types",array('company_code'=>"SGKN","status"=>1));
          if($getorders->num_rows() >0){
            foreach($getorders->result() as $res){
               $parties['ordparties'][] = array('type_id'=>$res->id,'type_name'=>$res->type_name);
           }
       }
   }
}
}

echo json_encode($parties);
}

public function saveshipper()
{
    $post         = $this->input->post();
    $master_id    = "";
    $user_id      = $this->session->userdata('user_id');
    $cust_id      = isset($post['shipper_row_id']) ? $post['shipper_row_id'] : "";
    $customer_code      = isset($post['scustomer_code']) ? $post['scustomer_code'] : "";
    $order_id     = isset($post['shipper_orderrow_id']) ? $post['shipper_orderrow_id'] : "";
    $company_code = isset($post['shipper_company_code']) ? $post['shipper_company_code'] : "";
    $branch_code  = isset($post['shipper_branch_code']) ? $post['shipper_branch_code'] : "";
    $cdate        = date('Y-m-d H:i:s');
    $status = '1';    
    $address      = $post['shipper_street'] . ',' . $post['shipper_city'] . ',' . $post['shipper_state'];
    if($company_code == ""){
        $company_code = $this->session->userdata('company_code');
    }
    if($branch_code == ""){
        $branch_code = $this->session->userdata('branch_code');
    }
    $master_id                = 0;
    $chktype                  = $this->db->select("id")->order_by('created_on', 'DESC')->get_where("tbl_party_types", array('name' => 'Shipper', 'user_id' => $user_id));
    if ($chktype->num_rows() > 0) {
        $party_id = $chktype->row()->id;
    } else {
        $party    = array('name' => 'Shipper', 'description' => 'Shipper', 'company_code' => $company_code, 'branch_code' => $branch_code, 'created_on' => $cdate, 'user_id' => $user_id);
        $ins      = $this->db->insert("tbl_party_types", $party);
        $party_id = $this->db->insert_id();
    }
    $code = $post['shipper_id'];
    if($code == ""){
        $code = 0;
    }
    $master    = array('party_type_id' => $party_id, 'name' => $post['shipper_name'], 'email' => $post['shipper_email'], 'street' => $post['shipper_street'], 'state' => $post['shipper_state'], 'mobile' => $post['shipper_phone'], 'pincode' => $post['shipper_zipcode'], 'country' => $post['shipper_country'], 'user_id' => $user_id, 'code' => $code, 'customeridentifier' => $code, 'company_code' => $company_code, 'branch_code' => $branch_code, 'status' => '1', 'fax' => $post['shipper_fax'], 'address' => $address, 'location_id' => $post['shipper_city']);
    if($code != '0'){
        $shipper_id = $code;
        $chkmaster = $this->db->select('id,customer_code')->get_where("tbl_party_master", array('code' => $post['shipper_id']));
        if ($chkmaster->num_rows() > 0) {
            $master_id = $chkmaster->row()->id;
            $shipper_customer_code = $chkmaster->row()->customer_code;
            if($order_id != ""){
                if($shipper_customer_code == "" || $shipper_customer_code == '0'){
                    $upd = $this->db->where(array('id'=>$master_id))->update("tbl_party_master",array('mobile'=>$post['shipper_phone'],'email'=>$post['shipper_email'],'fax'=>$post['shipper_fax'],'customer_code'=>$customer_code));
                }else{
                    $upd = $this->db->where(array('id'=>$master_id))->update("tbl_party_master",array('mobile'=>$post['shipper_phone'],'email'=>$post['shipper_email'],'fax'=>$post['shipper_fax']));
                }
                
                $status = '1';
            }else{
                if($shipper_customer_code == "" || $shipper_customer_code == '0'){
                    $master['customer_code'] = $customer_code;
                }
                $upd = $this->db->where(array('id' => $master_id))->update("tbl_party_master", $master);
                $status = '1';
            }
            
        } else {
            $conisgnee_id = "";
            $chktype                  = $this->db->select("id")->order_by('created_on', 'DESC')->get_where("tbl_party_types", array('name' => 'Consignee', 'company_code' => $company_code));
            if ($chktype->num_rows() > 0) {
                $conisgnee_id = $chktype->row()->id;
            }
            $master['party_types'] = $conisgnee_id;
            $master['created_on'] = $cdate;
            $master['customer_code'] = $customer_code;
            $ins                  = $this->db->insert("tbl_party_master", $master);
            $master_id            = $this->db->insert_id();
            $status = '0';
        }
    }else{
        $conisgnee_id = "";
        $chktype                  = $this->db->select("id")->order_by('created_on', 'DESC')->get_where("tbl_party_types", array('name' => 'Consignee', 'user_id' => $user_id));
        if ($chktype->num_rows() > 0) {
            $conisgnee_id = $chktype->row()->id;
        }
        $master['party_types'] = $conisgnee_id;
        $master['created_on'] = $cdate;
        $master['customer_code'] = $customer_code;
        $ins                  = $this->db->insert("tbl_party_master", $master);
        $master_id            = $this->db->insert_id();
        $country_code = $this->session->userdata("usr_tzone")['phone_code'];
        $year              = date('y');
        $week              = date('W');
        $shipper_id = $country_code.$year.$week.$master_id;
        $upd = $this->db->where(array('id'=>$master_id))->update("tbl_party_master",array('code'=>$shipper_id,'customeridentifier'=>$shipper_id));
        $status = '0';
    }
    if ($order_id != "") {
        if ($master_id != '0') {
            $chk_address = $this->db->select("id")->get_where("tbl_orderparty_address", array('order_id' => $order_id, 'party_master_id' => $master_id,'status'=>'1'));
            if ($chk_address->num_rows() > 0) {
                $address_id = $chk_address->row()->id;
                $upd_ar     = array('order_id' => $order_id, 'party_master_id' => $master_id, 'location_id' => $post['shipper_city'], 'street' => $post['shipper_street'], 'state' => $post['shipper_state'], 'address' => $address, 'pincode' => $post['shipper_zipcode'], 'country' => $post['shipper_country'], 'user_id' => $user_id);
                $updaddress = $this->db->where(array('id' => $address_id))->update("tbl_orderparty_address", $upd_ar);
            }else{
                $insadd_ar     = array('order_id' => $order_id, 'party_master_id' => $master_id, 'location_id' => $post['shipper_city'], 'street' => $post['shipper_street'], 'state' => $post['shipper_state'], 'address' => $address, 'pincode' => $post['shipper_zipcode'], 'country' => $post['shipper_country'], 'user_id' => $user_id, 'status'=>'1', 'createdon'=>$cdate);
                $updaddress = $this->db->insert("tbl_orderparty_address",$insadd_ar);
            }
        }
        $status = '1';
    }
    $arr = array('master_id'=>$master_id,'status'=>$status,'shipper_id'=>$shipper_id);
    echo json_encode($arr);
}

public function getconsigneeID()
{

    $user_id = 1;
    $parties = array();
    $code    = $this->input->post('partytype_id');
    $user_id = $this->session->userdata('user_id');
    $custid = "0";
    $party_type = isset($_POST['type']) ? $_POST['type'] : "";
    $whr = "";
    $chkcompanycode = $this->session->userdata('company_code');
    if($chkcompanycode != 'NZKN'){
       if($this->session->userdata('cust_id') !== FALSE){
        $custid =$this->session->userdata('cust_id');
    }
    $subcusts = array();
    if($custid != 0){
        if($this->session->userdata('sub_cust') !== FALSE){
            $subcusts = $this->session->userdata('sub_cust');
            if(count($subcusts)>0){
                array_push($subcusts, $custid);
            }else{
                $subcusts = $custid;
                           // array_push($subcusts, $custid);
            }
        }else{
            $subcusts  =$custid;
                       // array_push($subcusts, $custid);
        }
    }
    $customer_code= array();
    if(!empty($subcusts)){
        $select = "code";
        $table = "tb_customers";
        $customerdetails = $this->Order->getcustomercodebyids($select,$table,$subcusts);
        if(!empty($customerdetails)){
            foreach($customerdetails as $cust){
                $customer_code[] = $cust['code'];
            }
        }
    }
    if(!empty($customer_code)){
        $whr = "AND m.customer_code IN ('" . implode("','", $customer_code) . "') ";
    } 
}
$party_type_whr = "";
if($party_type != ""){
    $party_type_whr = " AND p.name LIKE '".$party_type."' ";
}
$master_ids = array();
$where   = "m.code LIKE '%" . $code . "%' AND m.user_id='" . $user_id . "' AND m.company_code != '' AND m.company_code IS NOT NULL AND m.branch_code != '' AND m.code IS NOT NULL AND m.code != '' AND m.code !='0' AND m.branch_code IS NOT NULL  AND m.status='1' ".$party_type_whr." ".$whr;

$this->db->select("m.id,m.code");
$this->db->from("tbl_party_master m");
$this->db->join("tbl_party_types p", "p.id=m.party_type_id", "LEFT");
$this->db->where($where);
$this->db->group_by('m.id');
$this->db->order_by('m.id', 'DESC');
$chkqry = $this->db->get();
if ($chkqry->num_rows() > 0) {
    foreach ($chkqry->result() as $res) {
        $master_ids[] = $res->id;
        $parties[] = array('id' => $res->id, 'party_id' => $res->code);
    }
}
$additional_ids = array();
if(!empty($master_ids)){
    $mwhr = "m.id NOT IN (".implode(',', $master_ids).") AND ";
}else{
    $mwhr = "";
}
$getmultipleparties = $this->db->query("SELECT m.id as master_id,m.party_types FROM tbl_party_master m WHERE ".$mwhr." m.code LIKE '%" . $code . "%'  AND m.user_id ='".$user_id."' AND m.party_types IS NOT NULL");
if($getmultipleparties->num_rows() >0){
    foreach($getmultipleparties->result() as $res){
        $party_types = explode(',', $res->party_types);
        if(!empty($party_types)){
            $chkshipper = $this->db->query("SELECT id FROM tbl_party_types WHERE id IN (".implode(',', $party_types).") AND name LIKE '".$party_type."'");
            if($chkshipper->num_rows()>0){
                $additional_ids[] = $res->master_id;
            }
        }
    }
}
if(!empty($additional_ids)){
    $get_addids = $this->db->query("SELECT m.id as master_id,m.code FROM tbl_party_master m WHERE m.id IN (".implode(',', $additional_ids).") AND m.company_code != '' AND m.company_code IS NOT NULL AND m.branch_code != '' AND m.branch_code IS NOT NULL AND m.user_id='" . $user_id . "' AND m.parent_id ='0'  AND m.status=1 ANd m.code != '' AND m.code is NOT NULL AND m.code != '0' GROUP BY m.id ORDER BY m.id DESC");
    if ($get_addids->num_rows() > 0) {
        foreach ($get_addids->result() as $res) {
            $master_ids[] = $res->master_id;
            $parties[] = array('id' => $res->master_id, 'party_id' => $res->code);
        }
    }
}

echo json_encode($parties);

}
public function getconsigneedetailsbyID()
{
    $user_id = 1;
    $parties = array();
    $code    = $this->input->post('id');
    $this->db->select("id,name,email,street,location_id as city,state,mobile,pincode,country,code,fax");
    $this->db->from("tbl_party_master");
    $this->db->like("code", $code);
    $this->db->order_by("id", "DESC");
    $chkqry = $this->db->get();
    if ($chkqry->num_rows() > 0) {
        $parties[] = array('id' => $chkqry->row()->id, 'name' => $chkqry->row()->name, 'phone' => $chkqry->row()->mobile, 'street' => $chkqry->row()->street, 'city' => $chkqry->row()->city, 'pincode' => $chkqry->row()->pincode, 'code' => $chkqry->row()->code, 'country' => $chkqry->row()->country, 'email_id' => $chkqry->row()->email, 'fax' => $chkqry->row()->fax, 'state' => $chkqry->row()->state);
    }

    echo json_encode($parties);
}
public function saveconsignee()
{
    
    $post         = $this->input->post();
    $master_id    = "";
    $c_id         = isset($post['consignee_row_id']) ? $post['consignee_row_id'] : "";
    $customer_code         = isset($post['ccustomer_code']) ? $post['ccustomer_code'] : "0";
    $order_id     = isset($post['consignee_orderrow_id']) ? $post['consignee_orderrow_id'] : "";
    $company_code = isset($post['consignee_company_code']) ? $post['consignee_company_code'] : "";
    $branch_code  = isset($post['consignee_branch_code']) ? $post['consignee_branch_code'] : "";
    $user_id      = $this->session->userdata('user_id');
    $cdate        = date('Y-m-d H:i:s');
    $status = '1';    
    if($company_code == ""){
        $company_code = $this->session->userdata('company_code');
    }
    if($branch_code == ""){
        $branch_code = $this->session->userdata('branch_code');
    }
    $user_id = $this->session->userdata('user_id');
    $chktype = $this->db->query("SELECT id FROM tbl_party_types WHERE name='Consignee' AND company_code LIKE '" . $company_code . "' ORDER BY created_on DESC");
    if ($chktype->num_rows() > 0) {
        $party_id = $chktype->row()->id;
    } else {
        $party    = array('name' => 'Consignee', 'description' => 'Consignee', 'company_code' => $company_code, 'branch_code' => $branch_code, 'created_on' => $cdate, 'user_id' => '1');
        $ins      = $this->db->insert("tbl_party_types", $party);
        $party_id = $this->db->insert_id();
    }
    $code = $post['consignee_id'];
    if($code == ""){
        $code = 0;
    }
    $address = $post['consignee_street'] . ',' . $post['consignee_city'] . ',' . $post['consignee_state'];
    $master  = array('party_type_id' => $party_id, 'name' => $post['consignee_name'], 'email' => $post['consignee_email'], 'street' => $post['consignee_street'], 'state' => $post['consignee_state'], 'mobile' => $post['consignee_phone'], 'pincode' => $post['consignee_zipcode'], 'country' => $post['consignee_country'], 'code' => $code, 'customeridentifier' => $code, 'status' => '1', 'fax' => $post['consignee_fax'], 'address' => $address, 'user_id' => $user_id, 'location_id' => $post['consignee_city']);
    if($code != 0){
        $consignee_id = $code;
        $chkmaster = $this->db->select('id,customer_code')->get_where("tbl_party_master", array('code' => $code));
        if ($chkmaster->num_rows() > 0) {
            $master_id = $chkmaster->row()->id;
            $consginee_customer_code = $chkmaster->row()->customer_code;
            if($order_id != ""){
                if($consginee_customer_code != "" && $consginee_customer_code != '0'){
                    $upd = $this->db->where(array('id'=>$master_id))->update("tbl_party_master",array('mobile'=>$post['consignee_phone'],'email'=>$post['consignee_email'],'fax'=>$post['consignee_fax'],'customer_code'=>$consginee_customer_code));
                }else{
                    $upd = $this->db->where(array('id'=>$master_id))->update("tbl_party_master",array('mobile'=>$post['consignee_phone'],'email'=>$post['consignee_email'],'fax'=>$post['consignee_fax']));
                }
                
                $status = '1';
            }else{
                if($consginee_customer_code != "" && $consginee_customer_code != '0'){
                    $master['customer_code'] = $consginee_customer_code;
                }
                $upd = $this->db->where(array('id' => $master_id))->update("tbl_party_master", $master);
                $status = '1';
            }
        } else {
            if ($company_code != "") {
                $master['company_code'] = $company_code;
            }
            if ($branch_code != "") {
                $master['branch_code'] = $branch_code;
            }
            $custid = "0";
            if($this->session->userdata('cust_id') !== FALSE){
                $custid =$this->session->userdata('cust_id');
            }

            $shipper_id = "";
            $chktype                  = $this->db->select("id")->order_by('created_on', 'DESC')->get_where("tbl_party_types", array('name' => 'Shipper', 'user_id' => $user_id));
            if ($chktype->num_rows() > 0) {
                $shipper_id = $chktype->row()->id;
            }
            $master['party_types'] = $shipper_id;
            $master['created_on'] = $cdate;
            $master['customer_code'] = $customer_code;
            $ins                  = $this->db->insert("tbl_party_master", $master);
            $master_id            = $this->db->insert_id();
            $status = '0';
        }
    }else{
        if ($company_code != "") {
            $master['company_code'] = $company_code;
        }
        if ($branch_code != "") {
            $master['branch_code'] = $branch_code;
        }
        $shipper_id = "";
        $chktype                  = $this->db->select("id")->order_by('created_on', 'DESC')->get_where("tbl_party_types", array('name' => 'Shipper', 'user_id' => $user_id));
        if ($chktype->num_rows() > 0) {
            $shipper_id = $chktype->row()->id;
        }
        $master['party_types'] = $shipper_id;
        $master['created_on'] = $cdate;
        $master['customer_code'] = $customer_code;
        $ins                  = $this->db->insert("tbl_party_master", $master);
        $master_id            = $this->db->insert_id();
        $country_code = $this->session->userdata("usr_tzone")['phone_code'];
        $year              = date('y');
        $week              = date('W');
        $consignee_id = $country_code.$year.$week.$master_id;
        $upd = $this->db->where(array('id'=>$master_id))->update("tbl_party_master",array('code'=>$consignee_id,'customeridentifier'=>$consignee_id));
        $status = '0';

    }
    
    if ($order_id != "") {
        if ($master_id != '0') {
            $chk_address = $this->db->select("id")->get_where("tbl_orderparty_address", array('order_id' => $order_id, 'party_master_id' => $master_id,'status'=>'1'));
            if ($chk_address->num_rows() > 0) {
                $address_id = $chk_address->row()->id;
                $upd_ar     = array('order_id' => $order_id, 'party_master_id' => $master_id, 'location_id' => $post['consignee_city'], 'street' => $post['consignee_street'], 'state' => $post['consignee_state'], 'address' => $address, 'pincode' => $post['consignee_zipcode'], 'country' => $post['consignee_country'], 'user_id' => $user_id);
                $updaddress = $this->db->where(array('id' => $address_id))->update("tbl_orderparty_address", $upd_ar);
            }else{
                $insadd_ar     =  array('order_id' => $order_id, 'party_master_id' => $master_id, 'location_id' => $post['consignee_city'], 'street' => $post['consignee_street'], 'state' => $post['consignee_state'], 'address' => $address, 'pincode' => $post['consignee_zipcode'], 'country' => $post['consignee_country'], 'status'=>'1', 'user_id' => $user_id, 'createdon'=>$cdate);
                $updaddress = $this->db->insert("tbl_orderparty_address",$insadd_ar);
            }
        }
        $status = '1';
    } 
    $arr = array('master_id'=>$master_id,'status'=>$status,'consignee_id'=>$consignee_id);
    echo json_encode($arr);
    
}

public function getinvolvedpartyId()
{

    $user_id = $this->session->userdata('user_id');
    $parties = array();
    $code    = $this->input->post('code');
    if ($user_id != "") {
        $custid = "0";
        $whr = "";
        $chkcompanycode = $this->session->userdata('company_code');
        if($chkcompanycode != 'NZKN'){
            if($this->session->userdata('cust_id') !== FALSE){
                $custid =$this->session->userdata('cust_id');
            }
            $subcusts = array();
            if($custid != 0){
                if($this->session->userdata('sub_cust') !== FALSE){
                    $subcusts = $this->session->userdata('sub_cust');
                    if(count($subcusts)>0){
                        array_push($subcusts, $custid);
                    }else{
                        $subcusts = $custid;
                               // array_push($subcusts, $custid);
                    }
                }else{
                    $subcusts  =$custid;
                           // array_push($subcusts, $custid);
                }
            }
            $customer_code= array();
            if(!empty($subcusts)){
                $select = "code";
                $table = "tb_customers";
                $customerdetails = $this->Order->getcustomercodebyids($select,$table,$subcusts);
                if(!empty($customerdetails)){
                    foreach($customerdetails as $cust){
                        $customer_code[] = $cust['code'];
                    }
                }
            }
            if(!empty($customer_code)){
                $whr = "AND m.customer_code IN ('" . implode("','", $customer_code) . "') ";
            } 
        }
        $chkqry = $this->db->query("SELECT m.id,m.code FROM tbl_party_master m,tbl_party_types t WHERE t.id=m.party_type_id AND m.user_id='" . $user_id . "' AND m.code LIKE '%" . $code . "%' AND m.code IS NOT NULL AND m.code != '' AND m.code !='0' AND m.company_code != '' AND m.company_code IS NOT NULL AND m.branch_code != '' AND m.branch_code IS NOT NULL ".$whr." GROUP BY m.id ORDER BY m.created_on DESC");
        if ($chkqry->num_rows() > 0) {
            foreach ($chkqry->result() as $res) {
                $parties[] = array('id' => $res->id, 'customeridentifier' => $res->code);
            }
        }

    }

    echo json_encode($parties);
}

public function getpartydetailsbyID()
{
    $parties = array();
    $code    = $this->input->post('id');
    $chkqry  = $this->db->query("SELECT m.id,m.name,m.email,m.street,m.location_id as city,m.state,m.mobile,m.address,m.country,m.pincode,m.country,m.code,m.fax,t.id as partytype_id,t.name as role FROM tbl_party_master m,tbl_party_types t WHERE m.code LIKE '%" . $code . "%' AND t.id=m.party_type_id ORDER BY m.id DESC");
    if ($chkqry->num_rows() > 0) {
        $parties[] = array('id' => $chkqry->row()->id, 'name' => $chkqry->row()->name, 'phone' => $chkqry->row()->mobile, 'street' => $chkqry->row()->street, 'city' => $chkqry->row()->city, 'pincode' => $chkqry->row()->pincode, 'code' => $chkqry->row()->code, 'country' => $chkqry->row()->country, 'email_id' => $chkqry->row()->email, 'fax' => $chkqry->row()->fax, 'state' => $chkqry->row()->state, 'role' => $chkqry->row()->role, 'partytype_id' => $chkqry->row()->partytype_id, 'address' => $chkqry->row()->address, 'country' => $chkqry->row()->country);
    }
    echo json_encode($parties);
}

public function addinvolvedpartyfororder($order_id = null)
{

    $post = $this->input->post();
    $cdate        = date('Y-m-d H:i:s');
    $party_id     = 0;
    $inner_id     = $data     = array();
    $user_id      = $this->session->userdata('user_id');
    $company_code = isset($_POST['party_company_id']) ? $_POST['party_company_id'] : "";
    $branch_code  = isset($_POST['party_branch_id']) ? $_POST['party_branch_id'] : "";
    if ($user_id != "") {
        $company_code = $this->session->userdata('company_code');
        $branch_code  = $this->session->userdata('branch_code');
        $address      = $post['street'] . ',' . $post['city'] . ',' . $post['city'];
        $party        = array('customeridentifier' => $post['party_id'], 'code' => $post['party_id'], 'name' => $post['party_name'], 'street' => $post['street'], 'pincode' => $post['zipcode'], 'country' => $post['country'], 'state' => $post['state'], 'mobile' => $post['mobile'], 'fax' => $post['fax'], 'email' => $post['email'], 'created_on' => $cdate, 'address' => $address, 'company_code' => $company_code, 'branch_code' => $branch_code, 'location_id' => $post['city']);
        $role_id      = 0;
        if ($post['role'] != "") {
            $getroleid = $this->db->select("id")->get_where("tbl_party_types", array('name' => $post['role'], 'company_code' => $company_code, 'branch_code' => $branch_code, 'status' => 1, 'user_id' => $user_id));
            if ($getroleid->num_rows() > 0) {
                $role_id = $getroleid->row()->id;
            } else {
                $getroleid_nobranch = $this->db->select("id")->get_where("tbl_party_types", array('name' => $post['role'], 'company_code' => $company_code, 'status' => 1, 'user_id' => $user_id));
                if ($getroleid_nobranch->num_rows() > 0) {
                    $role_id = $getroleid_nobranch->row()->id;
                } else {
                    $ins_role = array('name' => $post['role'], 'description' => $post['role'], 'company_code' => $company_code, 'branch_code' => $branch_code, 'user_id' => $user_id, 'status' => '1', 'created_on' => $cdate);
                    $insqry   = $this->db->insert("tbl_party_types", $ins_role);
                    $role_id  = $this->db->insert_id();
                }

            }
        }

        $party['party_type_id'] = $role_id;
        $party_type             = "";
        $getpartytype           = $this->db->select("name")->get_where("tbl_party_types", array('id' => $party_id));
        if ($getpartytype->num_rows() > 0) {
            $party_type = $getpartytype->row()->name;
        }
        $parties = array('party_id' => $post['party_id'], 'party_type' => $party_type, 'name' => $post['party_name'], 'street' => $post['street'], 'zipcode' => $post['zipcode'], 'city' => $post['city'], 'country' => $post['country'], 'state' => $post['state'], 'mobile' => $post['mobile'], 'fax' => $post['fax'], 'emailid' => $post['email'], 'action' => "<button id=" . $post['party_id'] . " class='btn btn-primary btn-xs editparties' onclick='editpartydetails(" . $post['party_id'] . ",event)'><small><i class='glyphicon glyphicon-pencil'></i></small></button> <button id=" . $post['party_id'] . " class='btn btn-primary btn-xs deleteround' onclick='deletepartydetails(" . $post['party_id'] . ",event)'><small><i class='glyphicon glyphicon-trash'></i></small></button>");

        $chk = $this->db->select('id')->get_where('tbl_party_master', array('code' => $post['party_id']));
        if ($chk->num_rows() > 0) {
            $party_id = $chk->row()->id;
            $upd      = $this->db->where(array('id' => $party_id))->update("tbl_party_master", $party);
        } else {
            $ins      = $this->db->insert("tbl_party_master", $party);
            $party_id = $this->db->insert_id();
        }
        if ($order_id != null) {
            $chk = $this->db->select('id,status')->get_where('tb_order_parties', array('party_id' => $party_id, 'order_id' => $order_id));
            if ($chk->num_rows() > 0) {
                $id  = $chk->row()->id;
                $status = $chk->row()->status;
                if ($status == '1') {
                    echo "2";
                } else if ($status == '0') {
                  
                    $upd = $this->db->where(array('id' => $id))->update('tb_order_parties', array('status' => '1'));
                    if ($upd) {
                        echo "1";
                    }
                }
                if($role_id != "0"){
                  $upd = $this->db->where(array('id'=>$id))->update("tb_order_parties",array('party_type'=>$role_id));  
              }
          } else {
            $getorder_number = $this->db->select("order_id")->get_where("tb_orders", array('id' => $order_id));
            $order_number    = $getorder_number->row()->order_id;
            $party_type      = 1;
            if($role_id != "0"){
                $party_type = $role_id;
            }else{
                $getpartytype    = $this->db->query("SELECT party_type_id FROM tbl_party_master WHERE id='" . $party_id . "'");
                if ($getpartytype->num_rows() > 0) {
                    $party_type = $getpartytype->row()->party_type_id;
                }
            }
            $order_ins = array('party_id' => $party_id, 'order_id' => $order_id, 'createdon' => $cdate, 'status' => '1', 'party_type' => $party_type, 'order_number' => $order_number);
            $ins       = $this->db->insert("tb_order_parties", $order_ins);
            if ($ins) {
                echo "1";
            } else {
                echo "0";
            }
        }

    }
}
}

public function updateorder()
{
    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : '0';
    if ($order_id != "0") {
        $cdate            = date('Y-m-d H:i:s');
        $user_id          = $this->session->userdata('user_id');
        $booking_id       = isset($_POST['booking_id']) ? $_POST['booking_id'] : "";
        $company_code     = isset($_POST['company_code']) ? $_POST['company_code'] : "";
        $branch_code      = isset($_POST['branch_code']) ? $_POST['branch_code'] : "";
        $department_code  = isset($_POST['department_code']) ? $_POST['department_code'] : "";
        $product          = isset($_POST['product']) ? $_POST['product'] : "";
        $service          = isset($_POST['service']) ? $_POST['service'] : "";
        $delivery_terms   = isset($_POST['delivery_terms']) ? $_POST['delivery_terms'] : "";
        $modeof_trasnport = isset($_POST['modeof_trasnport']) ? $_POST['modeof_trasnport'] : "TL";
        $order_type       = isset($_POST['order_type']) ? $_POST['order_type'] : "";
        $incoterm         = isset($_POST['incoterm']) ? $_POST['incoterm'] : "";
        $shipment_id      = isset($_POST['delivery_note']) ? $_POST['delivery_note'] : "";
        $container_no     = isset($_POST['container_num']) ? $_POST['container_num'] : "";
        $porder           = isset($_POST['purchase_order']) ? $_POST['purchase_order'] : "";
        $order_shipper_id = isset($_POST['order_shipper_id']) ? $_POST['order_shipper_id'] : "0";
        $customer_id      = isset($_POST['customer_id']) ? $_POST['customer_id'] : "0";
        $pickup           = isset($_POST['order_pickup_id']) ? $_POST['order_pickup_id'] : "0";
        if($pickup == "" || $pickup == "0"){
            if($customer_id != "" && $customer_id != "0"){
                $getcustomerid = $this->db->select("id")->get_where("tb_customers",array('code'=>$customer_id,'user_id'=>$user_id,'status'=>'1'));
                if($getcustomerid->num_rows() >0){
                    $pickup = $getcustomerid->row()->id;
                }
            }
        }
        $notify_party = isset($_POST['notify_party']) ? $_POST['notify_party'] : "";
        $driver_pickup_instructions       = isset($_POST['driver_pickup_instructions']) ? $_POST['driver_pickup_instructions'] : "";
        $driver_delivery_instructions       = isset($_POST['driver_delivery_instructions']) ? $_POST['driver_delivery_instructions'] : "";

        if ($shipment_id != "") {
            $upddq = $this->db->query("SELECT o.id FROM tb_order_references o,tb_reference_master r WHERE r.name LIKE 'DQ' AND r.name=o.reference_id AND o.order_id=" . $order_id);
            if ($upddq->num_rows() > 0) {
                $this->db->where(array('id' => $upddq->row()->id))->update('tb_order_references', array('ref_value' => $shipment_id));
            } else {
                $arr = array('order_id' => $order_id, 'reference_id' => 'DQ', 'ref_value' => $shipment_id,'createdon'=>$cdate);
                $this->db->insert('tb_order_references', $arr);
            }
        }
        if($company_code == 'AUKN' || $company_code == 'UKKN'){
            if ($container_no != "") {
                $upddq = $this->db->query("SELECT o.id FROM tb_order_references o,tb_reference_master r WHERE r.name LIKE 'CTR' AND r.name=o.reference_id AND o.order_id=" . $order_id);
                if ($upddq->num_rows() > 0) {
                    $this->db->where(array('id' => $upddq->row()->id))->update('tb_order_references', array('ref_value' => $container_no));
                } else {
                    $arr = array('order_id' => $order_id, 'reference_id' => 'CTR', 'ref_value' => $container_no,'createdon'=>$cdate);
                    $this->db->insert('tb_order_references', $arr);
                }
            }
        }
        if ($driver_pickup_instructions != "") {
            $updporder = $this->db->query("SELECT o.id FROM tb_order_references o,tb_reference_master r WHERE r.name LIKE 'ORD_PIKINST' AND r.name=o.reference_id AND o.order_id=" . $order_id);
            if ($updporder->num_rows() > 0) {
                $this->db->where(array('id' => $updporder->row()->id))->update('tb_order_references', array('ref_value' => $driver_pickup_instructions));
            } else {
                $arr = array('order_id' => $order_id, 'reference_id' => 'ORD_PIKINST', 'ref_value' => $driver_pickup_instructions,'createdon'=>$cdate);
                $this->db->insert('tb_order_references', $arr);
            }
        }
        if ($driver_delivery_instructions != "") {
            $updporder = $this->db->query("SELECT o.id FROM tb_order_references o,tb_reference_master r WHERE r.name LIKE 'ORD_DLVINST' AND r.name=o.reference_id AND o.order_id=" . $order_id);
            if ($updporder->num_rows() > 0) {
                $this->db->where(array('id' => $updporder->row()->id))->update('tb_order_references', array('ref_value' => $driver_delivery_instructions));
            } else {
                $arr = array('order_id' => $order_id, 'reference_id' => 'ORD_DLVINST', 'ref_value' => $driver_delivery_instructions,'createdon'=>$cdate);
                $this->db->insert('tb_order_references', $arr);
            }
        }
        if ($porder != "") {
            $updporder = $this->db->query("SELECT o.id FROM tb_order_references o,tb_reference_master r WHERE r.name LIKE 'PO' AND r.name=o.reference_id AND o.order_id=" . $order_id);
            if ($updporder->num_rows() > 0) {
                $this->db->where(array('id' => $updporder->row()->id))->update('tb_order_references', array('ref_value' => $shipment_id));
            } else {
                $arr = array('order_id' => $order_id, 'reference_id' => 'PO', 'ref_value' => $porder,'createdon'=>$cdate);
                $this->db->insert('tb_order_references', $arr);
            }
        }


        $goods_value = isset($_POST['goods_value']) ? $_POST['goods_value'] : "0.00";
        if ($goods_value == "") {
            $goods_value = 0.00;
        }
        $party_row_id     = isset($_POST['order_party_row_id']) ? $_POST['order_party_row_id'] : "0";
        $reference_ids    = isset($_POST['reference_ids']) ? $_POST['reference_ids'] : "0";
        $order_inv_row_id = isset($_POST['order_inv_row_id']) ? $_POST['order_inv_row_id'] : '0';
        $order_cargo_id   = isset($_POST['order_cargo_id']) ? $_POST['order_cargo_id'] : "";
        $pickup           = isset($_POST['order_pickup_id']) ? $_POST['order_pickup_id'] : "";
        $delivery         = isset($_POST['order_drop_id']) ? $_POST['order_drop_id'] : "";
        $early_pickup     = isset($_POST['early_pickup']) ? $_POST['early_pickup'] : "";
        $late_pickup      = isset($_POST['late_pickup']) ? $_POST['late_pickup'] : "";
        $early_delivery   = isset($_POST['early_delivery']) ? $_POST['early_delivery'] : "";
        $late_delivery    = isset($_POST['late_delivery']) ? $_POST['late_delivery'] : "";
        $e_pickup         = date('Y-m-d H:i:s');
        if ($early_pickup != "") {
            $e_pickup = date('Y-m-d H:i:s', strtotime($early_pickup));
        }
        if ($late_pickup != "") {
            $l_pickup = date('Y-m-d H:i:s', strtotime($late_pickup));
        } else {
            $l_pickup = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($e_pickup)));
        }
        $e_delivery = date('Y-m-d H:i:s');
        if ($early_delivery != "") {
            $e_delivery = date('Y-m-d H:i:s', strtotime($early_delivery));
        }
        if ($late_delivery != "") {
            $l_delivery = date('Y-m-d H:i:s', strtotime($late_delivery));
        } else {
            $l_delivery = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($e_delivery)));
        }
        $pickup_name       = $pickup_country       = $pickup_street       = $pickup_pincode       = $pickup_city       = "";
        $drop_id           = $drop_name           = $drop_country           = $drop_street           = $drop_pincode           = $drop_city           = $drop_state           = $pickup_state = $drop_state = $pickup_address = $drop_address = "";
        $pickup_custid     = $drop_custid     = $pickup_id     = 0;
        
        $drop_row_id    = $shipper_party_id =  $consignee_party_id = 0;
        $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name,p.code,o.id as order_party_id,o.party_type FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1  WHERE p.status=1 AND o.order_id='$order_id' GROUP BY o.party_type");
        if($chekparty->num_rows() >0){
            foreach($chekparty->result() as $rr){
                $ptype = $rr->party_type;
                $chktype = $this->db->select("name")->get_where("tbl_party_types",array("id"=>$ptype),1,0);
                if($chktype->num_rows()>0){
                    if($chktype->row()->name == "Consignee"){
                        $drop_row_id = $rr->id;
                        $drop_id     = $rr->code;
                        $drop_name   = $rr->name;
                        $drop_custid = $rr->code;
                        $consignee_party_id = $rr->order_party_id;
                    }else if($chktype->row()->name == "Shipper"){
                        $pickup_id     = $rr->id;
                        $pickup_custid = $rr->code;
                        $pickup_name   = $rr->name;
                        $shipper_party_id = $rr->order_party_id;
                    }
                }
            }
        }
        if($pickup_id != $order_shipper_id) {
            if($order_shipper_id != "0"){
                $getshippercustid = $this->db->query("SELECT name,code,location_id as city,street,state,address,country,pincode FROM tbl_party_master WHERE id='" . $order_shipper_id . "'");
                if ($getshippercustid->num_rows() > 0) {
                    $pickup_custid  = $getshippercustid->row()->code;
                    $pickup_name    = $getshippercustid->row()->name;
                    $pickup_state   = $getshippercustid->row()->state;
                    $pickup_address = $getshippercustid->row()->address;
                    $pickup_country = $getshippercustid->row()->country;
                    $pickup_street  = $getshippercustid->row()->street;
                    $pickup_pincode = $getshippercustid->row()->pincode;
                    $pickup_city    = $getshippercustid->row()->city;
                    if($pickup_id != 0){
                        if($shipper_party_id != 0){
                            $chkorder_sparty = $this->db->select("id")->get_where("tb_order_parties",array('id'=>$shipper_party_id));
                            if($chkorder_sparty->num_rows()>0){
                                $upd_sparty_address = $this->db->where(array('id'=>$shipper_party_id))->update("tb_order_parties",array('status'=>'0'));
                            }
                        }
                        $chkprevious_shipperaddress = $this->db->select('id')->get_where("tbl_orderparty_address",array('order_id'=>$order_id,'party_master_id'=>$pickup_id,'status'=>'1'));
                        if($chkprevious_shipperaddress->num_rows() >0){
                            $upd_oldsaddress = $this->db->where(array('id'=>$chkprevious_shipperaddress->row()->id))->update("tbl_orderparty_address",array('status'=>'0'));
                        }
                    }
                    $chkpartyaddress = $this->db->select("id")->get_where("tbl_orderparty_address",array('order_id'=>$order_id,'party_master_id'=>$order_shipper_id,'status'=>'1'));
                    $shipper_address    = array('order_id' => $order_id, 'party_master_id' => $order_shipper_id, 'location_id' => $pickup_city, 'street' => $pickup_street, 'state' => $pickup_state, 'address' => $pickup_address, 'pincode' => $pickup_pincode, 'country' => $pickup_country, 'user_id' => $user_id,'status'=>'1');
                    if($chkpartyaddress->num_rows() >0){
                        $pickup_addressid = $chkpartyaddress->row()->id;
                        $upd = $this->db->where(array('id'=>$pickup_addressid))->update("tbl_orderparty_address",$shipper_address);
                    }else{
                        $shipper_address['createdon'] = $cdate;
                        $this->db->insert("tbl_orderparty_address",$shipper_address);
                    }
                }
            }
        }else if($order_shipper_id == $pickup_id){
            if($order_shipper_id != 0){
                $chk_shipperaddress = $this->db->select("location_id,street,state,address,pincode,country")->get_where("tbl_orderparty_address", array('order_id' => $order_id, 'party_master_id' => $order_shipper_id,'status'=>'1'));
                if ($chk_shipperaddress->num_rows() > 0) {
                    $pickup_city    = $chk_shipperaddress->row()->location_id;
                    $pickup_country = $chk_shipperaddress->row()->country;
                    $pickup_street  = $chk_shipperaddress->row()->street;
                    $pickup_pincode = $chk_shipperaddress->row()->pincode;
                    $pickup_state   = $chk_shipperaddress->row()->state;
                    $pickup_address = $chk_shipperaddress->row()->address;
                }
            }
        }
        if($drop_row_id != $delivery){
            if($delivery != "0"){
                $getdropcustid = $this->db->query("SELECT name,code,location_id as city,street,state,address,country,pincode FROM tbl_party_master WHERE id='" . $delivery . "'");
                if ($getdropcustid->num_rows() > 0) {
                    $drop_id      = $delivery;
                    $drop_custid  = $getdropcustid->row()->code;
                    $drop_name    = $getdropcustid->row()->name;
                    $drop_state   = $getdropcustid->row()->state;
                    $drop_address = $getdropcustid->row()->address;
                    $drop_country = $getdropcustid->row()->country;
                    $drop_street  = $getdropcustid->row()->street;
                    $drop_pincode = $getdropcustid->row()->pincode;
                    $drop_city    = $getdropcustid->row()->city;
                    if($drop_row_id != 0){
                        $chkprevious_dropaddress = $this->db->select('id')->get_where("tbl_orderparty_address",array('order_id'=>$order_id,'party_master_id'=>$drop_row_id,'status'=>'1'));
                        if($chkprevious_dropaddress->num_rows() >0){
                            $upd_olddaddress = $this->db->where(array('id'=>$chkprevious_dropaddress->row()->id))->update("tbl_orderparty_address",array('status'=>'0'));
                        }
                    }
                    if($consignee_party_id != '0'){
                     $chkorder_cparty = $this->db->select("id")->get_where("tb_order_parties",array('id'=>$shipper_party_id));
                     if($chkorder_cparty->num_rows()>0){
                        $upd_cparty_address = $this->db->where(array('id'=>$consignee_party_id))->update("tb_order_parties",array('status'=>'0'));
                    } 
                }
                $chkpartyaddress = $this->db->select("id")->get_where("tbl_orderparty_address",array('order_id'=>$order_id,'party_master_id'=>$delivery,'status'=>'1'));
                $drop_address    = array('order_id' => $order_id, 'party_master_id' => $delivery, 'location_id' => $drop_city, 'street' => $drop_street, 'state' => $drop_state, 'address' => $drop_address, 'pincode' => $drop_pincode, 'country' => $drop_country, 'user_id' => $user_id,'status'=>'1');
                if($chkpartyaddress->num_rows() >0){
                    $drop_addressid = $chkpartyaddress->row()->id;
                    $upd = $this->db->where(array('id'=>$drop_addressid))->update("tbl_orderparty_address",$drop_address);
                }else{
                    $drop_address['createdon'] = $cdate;
                    $this->db->insert("tbl_orderparty_address",$drop_address);
                }
            }
        }
    }else if($drop_row_id == $delivery){
        if($delivery != 0){
            $chkdrop = $this->db->select("location_id,street,state,address,pincode,country")->get_where("tbl_orderparty_address", array('order_id' => $order_id, 'party_master_id' => $delivery,'status'=>'1'));
            if ($chkdrop->num_rows() > 0) {
                $drop_city    = $chkdrop->row()->location_id;
                $drop_state   = $chkdrop->row()->state;
                $drop_country = $chkdrop->row()->country;
                $drop_street  = $chkdrop->row()->street;
                $drop_pincode = $chkdrop->row()->pincode;
                $drop_address = $chkdrop->row()->address;
            }
        }
    }
    if($order_shipper_id != "" || $order_shipper_id != 0){
        $party_type = 0;
        $chk = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Shipper','company_code'=>$company_code,'branch_code'=>$branch_code,'user_id'=>$user_id));
        if($chk->num_rows() >0){
            $party_type = $chk->row()->id;
        }else{
         $chk1 = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Shipper','company_code'=>$company_code,'user_id'=>$user_id));
         if($chk1->num_rows()>0)
             $party_type = $chk1->row()->id;
     }
     $chkparty = $this->db->select("id")->get_where("tb_order_parties",array('order_id'=>$order_id, 'party_type' => $party_type,'party_id' => $order_shipper_id,'status' => '1'));
     if($chkparty->num_rows() == 0){
        $party     = array('order_id' => $order_id, 'party_id' => $order_shipper_id, 'status' => '1', 'createdon' => $cdate, 'status' => '1', 'party_type' => $party_type, 'order_number' => $booking_id);
        $ins_party = $this->db->insert("tb_order_parties", $party);
    }
    
} 
if($delivery != "" || $delivery != 0){
    $party_type = 0;
    $chk = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Consignee','company_code'=>$company_code,'branch_code'=>$branch_code,'user_id'=>$user_id));
    if($chk->num_rows() >0){
        $party_type = $chk->row()->id;
    }else{
        $chk1 = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Consignee','company_code'=>$company_code,'user_id'=>$user_id));
        if($chk1->num_rows()>0){
         $party_type = $chk1->row()->id;
     }
 }
 $chkparty = $this->db->select("id")->get_where("tb_order_parties",array('order_id'=>$order_id, 'party_type' => $party_type,'party_id' => $delivery,'status' => '1'));
 if($chkparty->num_rows() == 0){
     $party = array('order_id' => $order_id, 'party_id' => $delivery, 'status' => '1', 'createdon' => $cdate, 'status' => '1', 'party_type' => $party_type, 'order_number' => $booking_id);
     $ins_party = $this->db->insert("tb_order_parties", $party);
 }
}
$ship_row_id      = 0;
$enddate          = date('Y-m-d H:i:s', strtotime("+1 day"));
$tid              = $tname              = "";
$gettrasnportmode = $this->db->query("SELECT id,name FROM tb_transportmode WHERE code LIKE '" . $modeof_trasnport . "'");
if ($gettrasnportmode->num_rows() > 0) {
    $tid   = $gettrasnportmode->row()->id;
    $tname = $gettrasnportmode->row()->name;
}
$ship_arr   = array('unitspec' => 1, 'shipid' => $shipment_id, 'txnid' => $shipment_id, 'trucktype' => $tname, 'pickupcnt' => '1', 'dropcnt' => '1', 'insertusr' => $pickup_custid, 'carrier' => '0', 'insertuserdate' => $cdate, 'enddate' => $enddate, 'insdate' => $cdate, 'upddate' => $cdate, 'reason' => 'SHIPMENT', 'purpose' => 'SEND INTEGRATION', 'ship_object' => 'SHIPMENT', 'logdate' => $cdate, 'transport_mode' => $modeof_trasnport, 'domainname' => $branch_code, 'company_code' => $company_code, 'branch_code' => $branch_code, 'product' => $product, 'freight_term' => '60', 'freight_termname' => 'Free of Charge', 'incoterm' => $incoterm, 'modeoftransport' => $tid);
$chk_shipid = $this->db->query("SELECT id FROM tb_shipments WHERE shipid LIKE '" . $shipment_id . "'");
if ($chk_shipid->num_rows() > 0) {
    $ship_row_id = $chk_shipid->row()->id;
    $this->db->where(array('id' => $ship_row_id))->update("tb_shipments", $ship_arr);
} else {

    $ship_arr['createdon'] = $cdate;
    $ship_ins              = $this->db->insert("tb_shipments", $ship_arr);
    $ship_row_id           = $this->db->insert_id();
}
$curtz = $this->session->userdata("usr_tzone")['timezone'];
$getpickup = getdatetimebytimezone(DFLT_TZ,$e_pickup,$curtz);
$e_pickup = $getpickup['datetime'];
$getlpickup = getdatetimebytimezone(DFLT_TZ,$l_pickup,$curtz);
$l_pickup = $getlpickup['datetime'];
$getdelivery = getdatetimebytimezone(DFLT_TZ,$e_delivery,$curtz);
$e_delivery = $getdelivery['datetime'];
$getldelivery = getdatetimebytimezone(DFLT_TZ,$l_delivery,$curtz);
$l_delivery = $getldelivery['datetime'];
$ins = array('shipment_id' => $ship_row_id, 'product' => $product, 'pickup_datetime' => $e_pickup, 'delivery_datetime' => $e_delivery, 'pickup_endtime' => $l_pickup, 'drop_endtime' => $l_delivery, 'goods_value' => $goods_value, 'company_code' => $company_code, 'branch_code' => $branch_code, 'transport_mode' => $modeof_trasnport);
if($pickup_name != ""){
    $ins['pickup_company'] = $pickup_name;
}
if($pickup_country != ""){
    $ins['pickup_country'] = $pickup_country;
}
if($drop_name != ""){
    $ins['delivery_company'] = $drop_name;
}
if($drop_country != ""){
    $ins['delivery_country'] = $drop_country;
}
if($pickup_street != ""){
    $ins['pickup_address1'] = $pickup_street;
}
if($pickup_city != ""){
    $ins['pickup_city'] = $pickup_city;
} 
if($pickup_pincode != ""){
    $loc = [];
    $add1             = implode(",", [$pickup_street, $pickup_city, $pickup_country, $pickup_pincode]);
    $loc             = getlatlngsbyplace($add1);
    $ins['plat']             = @$loc[0];
    $ins['plng']             = @$loc[1];
    $ins['pickup_pincode'] = $pickup_pincode;
}
if($pickup_state != ""){
    $ins['pickup_address2'] = $pickup_state; 
}
if($drop_street != ""){
    $ins['delivery_address1']  = $drop_street;
}
if($drop_state != ""){
    $ins['delivery_address2'] = $drop_state;
} 
if($drop_city != ""){
    $ins['delivery_city'] = $drop_city;
}
if($pickup != "" && $pickup != ""){
    $ins['customer_id'] = $pickup;
}
if($drop_pincode != ""){
    $loc             = [];
    $add2             = implode(",", [$drop_street, $drop_city, $drop_country, $drop_pincode]);
    $loc             = getlatlngsbyplace($add2);
    $ins['dlat']             = @$loc[0];
    $ins['dlng']             = @$loc[1];
    $ins['delivery_pincode'] = $drop_pincode;
    $ins['delivery_pincode'] = $drop_pincode;
}
$this->db->where(array('id' => $order_id))->update('tb_orders', $ins);
$details_ins  = array( 'service' => $service, 'delivery_term' => $delivery_terms, 'incoterm' => $incoterm, 'purchase_order' => $porder, 'notify_party' => $notify_party, 'lane_reference' => "LR", 'distance' => '0', 'department_code' => $department_code, 'temperature_control' => '0', 'valorance_insurance' => '0', 'high_cargo_value' => '0', 'customs_required' => '0', 'order_type' => $order_type);
$chk = $this->db->select("id")->get_where("tb_order_details",array('order_row_id'=>$order_id));
if($chk->num_rows()>0){
 $upd_details  = $this->db->where(array('order_row_id' => $order_id))->update("tb_order_details", $details_ins); 
}else{
    $details_ins['createdon'] = $cdate;
    $details_ins['order_row_id'] = $order_id;
    $details_ins['order_id'] = $booking_id;
    $ins  = $this->db->insert("tb_order_details",$details_ins);
}
$total_weight = $total_volume = $total_quantity = 0;
$gettotal     = $this->db->query("SELECT sum(weight) as total_weight,sum(volume) as total_volume,sum(quantity) as total_quantity FROM tb_order_cargodetails WHERE order_id='" . $order_id . "' AND status ='1'");
if ($gettotal->num_rows() > 0) {
    $total_volume   = $gettotal->row()->total_volume;
    $total_weight   = $gettotal->row()->total_weight;
    $total_quantity = $gettotal->row()->total_quantity;
}
$upd_order = $this->db->where(array('id' => $order_id))->update("tb_orders", array('volume' => $total_volume, 'weight' => $total_weight, 'quantity' => $total_quantity));
$this->ordernotify('booking_edit',$order_id);
}
if($order_id != "" && $order_id !='0'){
    redirect('orders/orderslist/'.$order_id);
}else{
    redirect('orders');
}


}

public function insertinvolvedparties($id = null)
{

    $post     = $this->input->post();
    $cdate    = date('Y-m-d H:i:s');
    $party_id = 0;
    $inner_id = $data = array();
    $user_id  = $this->session->userdata('user_id');
    $order_id = isset($_POST['party_order_id']) ? $_POST['party_order_id'] : "0";
    if ($user_id != "") {
        $custid = "0";
        if($this->session->userdata('cust_id') !== FALSE){
            $custid =$this->session->userdata('cust_id');
        }
        $company_code = $this->session->userdata('company_code');
        $branch_code  = $this->session->userdata('branch_code');

        $address      = $post['street'] . ',' . $post['city'] . $post['state'];
        $party        = array('customeridentifier' => $post['party_id'], 'code' => $post['party_id'], 'name' => $post['party_name'], 'street' => $post['street'], 'pincode' => $post['zipcode'], 'country' => $post['country'], 'state' => $post['state'], 'mobile' => $post['mobile'], 'fax' => $post['fax'], 'email' => $post['email'], 'created_on' => $cdate, 'address' => $address, 'company_code' => $company_code, 'branch_code' => $branch_code, 'user_id' => $user_id, 'location_id' => $post['city']);
        $party_type   = "";
        $getpartyname = $this->db->select("name")->get_where("tbl_party_types", array('id' => $post['party_id']));
        if ($getpartyname->num_rows() > 0) {
            $party_type = $getpartyname->row()->name;
        }

        $parties = array('party_id' => $post['party_id'], 'party_type' => $post['role'], 'name' => $post['party_name'], 'street' => $post['street'], 'zipcode' => $post['zipcode'], 'city' => $post['city'], 'country' => $post['country'], 'state' => $post['state'], 'mobile' => $post['mobile'], 'fax' => $post['fax'], 'emailid' => $post['email'], 'action' => "<button id=" . $post['party_id'] . " class='btn btn-primary btn-xs editparties' onclick='editpartydetails(" . $post['party_id'] . ",event)'><small><i class='glyphicon glyphicon-pencil'></i></small></button> <button id=" . $post['party_id'] . " class='btn btn-primary btn-xs deleteround' onclick='deletepartydetails(" . $post['party_id'] . ",event)'><small><i class='glyphicon glyphicon-trash'></i></small></button>");
        $role_id = 0;
        if ($post['role'] != '') {
            $getroleid = $this->db->select("id")->get_where("tbl_party_types", array('name' => $post['role'], 'company_code' => $company_code, 'branch_code' => $branch_code, 'status' => 1, 'user_id' => $user_id));
            if ($getroleid->num_rows() > 0) {
                $role_id = $getroleid->row()->id;
            } else {
                $getroleid_nobranch = $this->db->select("id")->get_where("tbl_party_types", array('name' => $post['role'], 'company_code' => $company_code, 'status' => 1, 'user_id' => $user_id));
                if ($getroleid_nobranch->num_rows() > 0) {
                    $role_id = $getroleid_nobranch->row()->id;
                } else {
                    $ins_role = array('name' => $post['role'], 'description' => $post['role'], 'company_code' => $company_code, 'branch_code' => $branch_code, 'user_id' => $user_id, 'status' => '1', 'created_on' => $cdate);
                    $insqry   = $this->db->insert("tbl_party_types", $ins_ar);
                    $role_id  = $this->db->insert_id();
                }
            }
        }
        if ($id == null) {
            if($custid != "0"){
                $getcustomer_code = $this->db->select("code")->get_where("tb_customers",array('id'=>$custid));
                if($getcustomer_code->num_rows() >0){
                    $party['customer_code'] = $getcustomer_code->row()->code;
                }
            }
            $chk = $this->db->select('id')->get_where('tbl_party_master', array('code' => $post['party_id']));
            if ($chk->num_rows() > 0) {
                $party_id = $chk->row()->id;
                $upd      = $this->db->where(array('id' => $party_id))->update("tbl_party_master", $party);
            } else {
             
                $party['party_type_id'] = $role_id;
                $ins                    = $this->db->insert("tbl_party_master", $party);
                $party_id               = $this->db->insert_id();
            }

            $data = array('party_id' => $party_id);
        } else {
            $party_id = $id;
            $upd      = $this->db->where(array('id' => $party_id))->update("tbl_party_master", $party);
            if($order_id != "0"){
                $chkorderparty = $this->db->select("id")->get_where("tb_order_parties",array('order_id'=>$order_id,'party_id'=>$party_id));
                if($chkorderparty->num_rows() >0){
                    $rowid = $chkorderparty->row()->id;
                    if($role_id != "0"){
                        $upd = $this->db->where(array('id'=>$rowid))->update("tb_order_parties",array('party_type'=>$role_id));
                    }
                }
            }
            $data = array('party_id' => $party_id);
        }
        echo json_encode($data);
    }
}
public function getorderinvolvedparties()
{

    $user_id  = $this->session->userdata('user_id');
    $parties  = array();
    $order_id = isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : "";
    $cust_id =  $this->session->userdata("cust_id");
    if ($order_id != "") {
        $getparties = $this->db->query("SELECT m.id as party_master_id,m.name as username,m.email as emailid,m.mobile as mobile,m.location_id as city,m.state,m.street as street,m.pincode as zipcode,m.country,m.partyindetifier,m.code,m.fax,p.name,p.id as party_type_id,p.name as party_name,p.company_code,p.branch_code,t.id as party_id FROM tbl_party_master m,tbl_party_types p,tb_order_parties t WHERE t.order_id='" . $order_id . "' AND t.status =1 AND m.id=t.party_id AND t.party_type=p.id GROUP BY t.party_type ORDER BY m.id DESC");
        if ($getparties->num_rows() > 0) {
            foreach ($getparties->result() as $res) {
                $id      = '"' . $res->party_master_id . '"';
                $code    = '"' . $res->code . '"';
                $name    = '"' . $res->username . '"';
                $street  = '"' . $res->street . '"';
                $emailid = '"' . $res->emailid . '"';
                $state   = '"' . $res->state . '"';
                $country = '"' . $res->country . '"';
                $fax     = '"' . $res->fax . '"';
                $city    = '"' . $res->city . '"';
                $role    = '"' . $res->party_name . '"';
                $action  = "<ul class='nav nav-tabs'><li class='dropdown tablebtnrleft'> <a class='dropdown-toggle' data-toggle='dropdown' href='#'><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'><li><a id='bEdit' type='button' class='btn btn-sm btn-default'  onclick='rowPartyEdit(this," . $id . "," . $code . "," . $name . "," . $street . "," . $emailid . "," . $res->mobile . "," . $state . "," . $country . "," . $res->zipcode . "," . $fax . "," . $city . "," . $role . ",".$order_id.");'><span class='glyphicon glyphicon-pencil' > </span>Edit</a></li><li><a id='bElim' type='button' class='btn btn-sm btn-default' onclick='deletepartydetailswithorder(" . $id . ");'><span class='glyphicon glyphicon-trash'> </span>Remove<li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowAddParty(this);'><span class='glyphicon glyphicon-plus' > </span>Add Parties</a></li></ul></li></ul>";
                if($cust_id == ""){
                 $parties[] = array('id' => $res->party_master_id, 'party_id' => $res->code, 'street' => $res->street, 'party_type' => $res->party_name, 'name' => $res->name, 'username' => $res->username, 'email' => $res->emailid, 'mobile' => $res->mobile, 'zipcode' => $res->zipcode, 'street' => $res->street, 'customeridentifier' => $res->code, 'partyindetifier' => $res->partyindetifier, 'fax' => $res->fax, 'code' => $res->code, 'name' => $res->name, 'city' => $res->city, 'state' => $res->state, 'country' => $res->country, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'action' => $action); 
             }else{
                if(strtoupper($res->party_name) != 'CARRIER'){
                    $parties[] = array('id' => $res->party_master_id, 'party_id' => $res->code, 'street' => $res->street, 'party_type' => $res->party_name, 'name' => $res->name, 'username' => $res->username, 'email' => $res->emailid, 'mobile' => $res->mobile, 'zipcode' => $res->zipcode, 'street' => $res->street, 'customeridentifier' => $res->code, 'partyindetifier' => $res->partyindetifier, 'fax' => $res->fax, 'code' => $res->code, 'name' => $res->name, 'city' => $res->city, 'state' => $res->state, 'country' => $res->country, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'action' => $action); 
                }
            }
            
        }
    }
}

echo json_encode($parties);
}
public function showinvolvedparties()
{

    $user_id      = $this->session->userdata('user_id');
    $parties      = $ids      = array();
    $party_row_id = isset($_POST['party_row_id']) ? $_POST['party_row_id'] : "";
    if ($party_row_id != "") {
        $ids = implode(',', $party_row_id);
        if (!empty($ids)) {
            $getparties = $this->db->query("SELECT m.id as party_master_id,m.name as username,m.email as emailid,m.mobile as mobile,m.location_id as city,m.state,m.street as street,m.pincode as zipcode,m.country,m.partyindetifier,m.code,m.fax,m.code,p.id as party_type_id,p.name as party_type,p.company_code,p.branch_code FROM tbl_party_master m,tbl_party_types p WHERE m.id IN (" . $ids . ") AND m.party_type_id=p.id GROUP BY m.id ORDER BY m.id DESC");
            if ($getparties->num_rows() > 0) {
                foreach ($getparties->result() as $res) {
                    $id      = '"' . $res->party_master_id . '"';
                    $code    = '"' . $res->code . '"';
                    $name    = '"' . $res->username . '"';
                    $street  = '"' . $res->street . '"';
                    $emailid = '"' . $res->emailid . '"';
                    $state   = '"' . $res->state . '"';
                    $country = '"' . $res->country . '"';
                    $fax     = '"' . $res->fax . '"';
                    $city    = '"' . $res->city . '"';
                    $role    = '"' . $res->party_type . '"';
                    $action  = "<ul class='nav nav-tabs'><li class='dropdown tablebtnrleft'> <a class='dropdown-toggle' data-toggle='dropdown' href='#'><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'><li><a id='bEdit' type='button' class='btn btn-sm btn-default'  onclick='rowPartyEdit(this," . $id . "," . $code . "," . $name . "," . $street . "," . $emailid . "," . $res->mobile . "," . $state . "," . $country . "," . $res->zipcode . "," . $fax . "," . $city . "," . $role . ");'><span class='glyphicon glyphicon-pencil' > </span>Edit</a></li><li><a id='bElim' type='button' class='btn btn-sm btn-default' onclick='rowPartyElim(this," . $id . ");'><span class='glyphicon glyphicon-trash'> </span>Remove<li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowAddParty(this);'><span class='glyphicon glyphicon-plus' > </span>Add Parties</a></li></ul></li></ul>";

                    $parties[] = array('party_id' => $res->code, 'party_type' => $res->party_type, 'party_type_id' => $res->party_type_id, 'street' => $res->street, 'username' => $res->username, 'email' => $res->emailid, 'mobile' => $res->mobile, 'zipcode' => $res->zipcode, 'street' => $res->street, 'customeridentifier' => $res->code, 'partyindetifier' => $res->partyindetifier, 'fax' => $res->fax, 'code' => $res->code, 'city' => $res->city, 'state' => $res->state, 'country' => $res->country, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'action' => $action);
                }
            }
        }
    }
    echo json_encode($parties);
}
public function editpartydetails()
{
    $id      = $this->input->post('id');
    $parties = array();
    $chk     = $this->db->select('id')->get_where("tbl_party_master", array('id' => $id));
    if ($chk->num_rows() > 0) {
        $get_parties = $this->db->query("SELECT m.id,m.party_type_id,m.name,m.email,m.mobile,m.location_id,m.street,m.state,m.fax,m.address,m.pincode,m.country,m.user_id,m.customeridentifier,p.id as party_type_id FROM tbl_party_master m,tbl_party_types p WHERE m.id='" . $id . "' AND p.id=m.party_type_id ORDER BY m.id DESC");
        if ($get_parties->num_rows() > 0) {
            $parties = array('name' => $get_parties->row()->name, 'street' => $get_parties->row()->street, 'zipcode' => $get_parties->row()->pincode, 'city' => $get_parties->row()->location_id, 'country' => $get_parties->row()->country, 'state' => $get_parties->row()->state, 'phone' => $get_parties->row()->mobile, 'email' => $get_parties->row()->email, 'fax' => $get_parties->row()->fax, 'code' => $get_parties->row()->customeridentifier, 'type_id' => $get_parties->row()->party_type_id);
        }
    }
    echo json_encode($parties);
}

public function additem()
{
    $cdate       = date('y-m-d H:i:s');
    $post        = $this->input->post();
    $item_id     = $post['item_id'];
    $item_name   = $post['item_name'];
    $description = $post['description'];
    $user_id     = $this->session->userdata('user_id');
    $length = isset($post['length']) ? $post['length'] : "0";
    $width = isset($post['width']) ? $post['width'] : "0";
    $weight = isset($post['weight']) ? $post['weight'] : "0";
    $height = isset($post['height']) ? $post['height'] : "0";
    $volume = isset($post['volume']) ? $post['volume'] : "0";
    $volumentric_weight = isset($post['volumentric_weight']) ? $post['volumentric_weight'] : "0";
    if($length == ""){
        $length = 0;
    }
    if($width == ""){
        $width = 0;
    }
    if($weight == ""){
        $weight = 0;
    }
    if($height == ""){
        $height = 0;
    }
    if($volume == ""){
        $volume = 0;
    }
    if($volumentric_weight == ""){
        $volumentric_weight = 0;
    }
    $ins_ar      = array('item_id' => $item_id, 'item_name' => $item_name, 'description' => $description, 'status' => '1', 'createdby' => $user_id, 'length' => $length, 'length_unit' => $post['length_uom'], 'width' => $width, 'width_unit' => $post['width_uom'], 'weight' => $weight, 'weight_unit' => $post['weight_uom'], 'height' => $height, 'height_unit' => $post['height_uom'], 'volume' => $volume, 'volume_unit' => $post['volume_uom'],'volumetric_weight'=>$volumentric_weight,'volweight_uom'=>$post['volumeticweight_uom']);
    $chk         = $this->db->select('id')->get_where('tb_items', $ins_ar);
    if ($chk->num_rows() == 0) {
        $ins = $this->db->insert('tb_items', $ins_ar);
        if ($ins) {
            $ins_id   = $this->db->insert_id();
            $ins_type = 'ins';
        } else {
            $ins_id   = 0;
            $ins_type = '';
        }
    } else {
        $ins_id   = $chk->row()->id;
        $ins_type = 'upd';
    }
    $res = array('id' => $ins_id, 'ins' => $ins_type);
    echo json_encode($res);
}

public function getitemid()
{

    $post        = $this->input->post();
    $term        = $post['term'];
    $result      = array();
    $whr         = "";
    $outer_cargo = isset($_POST['outer_cargo']) ? $_POST['outer_cargo'] : "";
    if ($outer_cargo != "") {
        $whr .= " AND item_id NOT LIKE '" . $outer_cargo . "' ";
    }
    $whr1 =  $whr2 = "";
    if($this->session->userdata('cust_id') !== FALSE){
        $custid =$this->session->userdata('cust_id');
        $ironmountain_ids = array('415','187','188','192','226','428','431','430','426','427','418','417','429','419','423','416','422','425','420','424','489','421');
        if($custid != "" && $custid != '0'){
            if(in_array($custid, $ironmountain_ids)){
              $whr1 = "AND item_id IN ('Docs','Non-Docs') ";
          }
          $company_code = $this->session->userdata('company_code');
          if($company_code == "NZKN"){
            $whr2 = " AND description LIKE 'APPLIANCES' ";
        }
    }
}
$getitemname = $this->db->query("SELECT id,item_id FROM tb_items WHERE item_id LIKE '%" . $term . "%' " . $whr . " ".$whr1." ".$whr2." ORDER BY createdon DESC");
if ($getitemname->num_rows() > 0) {
    foreach ($getitemname->result() as $res) {
        $result[] = array('id' => $res->id, 'item_id' => $res->item_id);
    }
}
echo json_encode($result);

}
public function searchviewitem()
{

    $post        = $this->input->post();
    $term        = $post['term'];
    $result      = array();
    $getitemname = $this->db->query("SELECT * FROM tb_items WHERE item_id LIKE '%" . $term . "%' GROUP BY id ORDER BY createdon DESC");
    if ($getitemname->num_rows() > 0) {
        foreach ($getitemname->result() as $res) {
            $check    = "<input type='radio' name='listitem' id='listitem_" . $res->id . "' class='listitem' onchange='selectitem(" . $res->id . ")' value='" . $res->id . "'>";
            $result[] = array('check' => $check, 'id' => $res->id, 'item_id' => $res->item_id, 'item_name' => $res->item_name, 'description' => $res->description, 'length' => $res->length . $res->length_unit, 'width' => $res->width . $res->width_unit, 'height' => $res->height . $res->height_unit, 'weight' => $res->weight . $res->weight_unit, 'volume' => $res->volume . $res->volume_unit,'volumetric_weight'=>$res->volumetric_weight.$res->volweight_uom);
        }
    }
    echo json_encode($result);

}

public function finditembyid()
{
    $post        = $this->input->post();
    $term        = $post['item'];
    $result      = array();
    $getitemname = $this->db->query("SELECT * FROM tb_items WHERE item_id LIKE '%" . $term . "%' GROUP BY id ORDER BY createdon DESC");
    if ($getitemname->num_rows() > 0) {
        foreach ($getitemname->result() as $res) {
            $check    = "<input type='radio' name='listitem' id='listitem_" . $res->id . "' class='listitem' onchange='selectitem(" . $res->id . ")' value='" . $res->item_id . "'>";
            $result[] = array('check' => $check, 'id' => $res->id, 'item_id' => $res->item_id, 'item_name' => $res->item_name, 'description' => $res->description, 'length' => $res->length . $res->length_unit, 'width' => $res->width . $res->width_unit, 'height' => $res->height . $res->height_unit, 'weight' => $res->weight . $res->weight_unit, 'volume' => $res->volume . $res->volume_unit);
        }
    }
    echo json_encode($result);
}

public function viewitemslist()
{
    $items = array();
    $type  = isset($_POST['ctype']) ? $_POST['ctype'] : "";
    $popup = isset($_POST['popup']) ? $_POST['popup'] : "";
    $whr   = "";
    if ($type != "") {
        $whr .= " AND item_id NOT LIKE '%" . $type . "%'";
    }
    $check = $whr1 =  $whr2 = "";
    if($this->session->userdata('cust_id') !== FALSE){
        $custid =$this->session->userdata('cust_id');
        $ironmountain_ids = array('415','187','188','192','226','428','431','430','426','427','418','417','429','419','423','416','422','425','420','424','489','421');
        if($custid != "" && $custid != '0'){
            if(in_array($custid, $ironmountain_ids)){
                $whr1 = "AND item_id IN ('Docs','Non-Docs') ";
            }
            $company_code = $this->session->userdata('company_code');
            if($company_code == 'NZKN'){
                $whr2 = " AND description LIKE 'APPLIANCES' ";
            }
        }
    }
    
    $qry   = $this->db->query("SELECT * FROM tb_items WHERE status='1'" . $whr . " ".$whr1." ".$whr2."  GROUP BY id ORDER BY id DESC");
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            $check   = "<input type='radio' name='listitem' id='listitem_" . $res->id . "' class='listitem' onchange='selectitem(" . $res->id . ")' value='" . $res->id . "'>";
            $items[] = array('check' => $check, 'item_name' => $res->item_name, 'item_id' => $res->item_id, 'description' => $res->description, 'length' => $res->length . $res->length_unit, 'width' => $res->width . $res->width_unit, 'height' => $res->height . $res->height_unit, 'weight' => $res->weight . $res->weight_unit, 'volume' => $res->volume . $res->volume_unit,'volumentric_weight'=>$res->volumetric_weight.$res->volweight_uom);
        }
    }
    echo json_encode($items);
}

public function getitemdetailsbyId()
{

    $items   = array();
    $item_id = $_POST['item_id'];
    $qry     = $this->db->query("SELECT * FROM tb_items WHERE status='1' and item_id LIKE '" . $item_id . "' ORDER BY createdon DESC");
    if ($qry->num_rows() > 0) {
        foreach ($qry->result() as $res) {
            $items[] = array('item_name' => $res->item_name, 'item_id' => $res->item_id, 'description' => $res->description, 'length' => $res->length, 'length_uom' => $res->length_unit, 'width' => $res->width, 'width_uom' => $res->width_unit, 'height' => $res->height, 'height_uom' => $res->height_unit, 'weight' => $res->weight, 'weight_uom' => $res->weight_unit, 'volume' => $res->volume, 'volume_uom' => $res->volume_unit);
        }
    }
    echo json_encode($items);

}
public function getitemdetailslist()
{

    $items   = array();
    $item_id = isset($_POST['item_id']) ? $_POST['item_id'] : "";
    if($item_id != ""){
        $qry     = $this->db->query("SELECT * FROM tb_items WHERE status='1' and id = '" . $item_id . "' ORDER BY createdon DESC");
        if ($qry->num_rows() > 0) {
            foreach ($qry->result() as $res) {
                $items[] = array('item_name' => $res->item_name, 'item_id' => $res->item_id, 'description' => $res->description, 'length' => $res->length, 'length_uom' => ucfirst($res->length_unit), 'width' => $res->width, 'width_uom' => ucfirst($res->width_unit), 'height' => $res->height, 'height_uom' => ucfirst($res->height_unit), 'weight' => $res->weight, 'weight_uom' => ucfirst($res->weight_unit), 'volume' => $res->volume, 'volume_uom' => ucfirst($res->volume_unit),'volumetric_weight'=>$res->volumetric_weight,'volweight_uom'=> ucfirst($res->volweight_uom));
            }
        }
    }
    
    echo json_encode($items);

}

public function getordercargodetails()
{
    $cargos   = array();
    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : "";
    if ($order_id != "") {
        $qry = $this->db->query("SELECT c.id,c.cargo_type,c.length_unit,c.width_unit,c.height_unit,c.weight_unit,c.volume_unit,c.goods_description,c.stackable,c.grounded,c.splittable,c.dg_goods,o.length,o.width,o.height,o.weight,o.volume,o.volumetric_weight,o.volweight_uom,c.ldm,o.quantity,o.scanned_quantity  FROM tb_cargo_details c,tb_order_cargodetails o WHERE o.order_id ='" . $order_id . "' AND o.cargo_id=c.id AND o.status=1 GROUP BY c.id ORDER BY c.id DESC");

        if ($qry->num_rows() > 0) {
            foreach ($qry->result() as $res) {
                $stackable = 'Off';
                if ($res->stackable == 0) {
                    $stackable = 'Off';
                } else if ($res->stackable == 1) {
                    $stackable = 'On';
                }
                $grounded = 'Off';
                if ($res->grounded == 0) {
                    $grounded = 'Off';
                } else if ($res->grounded == 1) {
                    $grounded = 'On';
                }
                $splittable = 'Off';
                if ($res->splittable == 0) {
                    $splittable = 'Off';
                } else if ($res->splittable == 1) {
                    $splittable = 'On';
                }
                $dg_goods = 'Off';
                if ($res->dg_goods == 0) {
                    $dg_goods = 'Off';
                } else if ($res->dg_goods == 1) {
                    $dg_goods = 'On';
                }
                $cargo_type = '"' . $res->cargo_type . '"';
                $goods_desc = '"' . $res->goods_description . '"';
                $length_unit = '"' . $res->length_unit . '"';
                $width_unit = '"' . $res->width_unit . '"';
                $height_unit = '"' . $res->height_unit . '"';
                $weight_unit = '"' . $res->weight_unit . '"';
                $volume_unit = '"' . $res->volume_unit . '"';
                $volweight_uom = '"' . $res->volweight_uom . '"';
                $scanned_quantity = $res->scanned_quantity;
                if($scanned_quantity == ""){
                    $scanned_quantity = 0;
                }
                $action     = "<ul class='nav nav-tabs'><li class='dropdown tablebtnrleft '> <a class='dropdown-toggle' data-toggle='dropdown' href='#''><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'>" . "<li><a id='bEdit' type='button' class='btn btn-sm btn-default'  onclick='rowcargoEdit(this," . $res->id . "," . $cargo_type . "," . $goods_desc . "," . $res->quantity . "," . $res->length . "," . $res->width . "," . $res->height . "," . $res->weight . "," . $res->volume .",".$res->volumetric_weight. ",".$res->stackable.",".$res->grounded.",".$res->splittable.",".$res->dg_goods.",".$length_unit.",".$width_unit.",".$height_unit.",".$weight_unit.",".$volume_unit.",".$volweight_uom.",".$res->ldm.",".$scanned_quantity.");'><span class='glyphicon glyphicon-pencil' ></span>Edit</a></li><li><a id='bElim' type='button' class='btn btn-sm btn-default' onclick='deleteordercargodetails(" . $res->id . ");'><span class='glyphicon glyphicon-trash' > </span>Remove</a></li><li><a id='bAcep' type='button' class='btn btn-sm btn-default' style='display:none;' onclick='rowAcep(this);'><span class='glyphicon glyphicon-ok' > </span>Update</a></li><li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowAdd(this);'><span class='glyphicon glyphicon-plus' > </span>Add Cargo Details</a></li><li><a id='innerpacking' type='button' class='btn btn-sm btn-default' onclick='getinnercargo(this," . $res->id . ");'><span class='fa fa-archive' > </span>Get Inner Cargos</a></li>";
                $empty      = "";
                $cargos[]   = array('id' => $res->id, 'cargo_type' => $res->cargo_type, 'goods_desc' => $res->goods_description, 'quantity' => $res->quantity, 'length' => $res->length . " " . $res->length_unit, 'width' => $res->width . " " . $res->width_unit, 'height' => $res->height . " " . $res->height_unit, 'weight' => $res->weight . " " . $res->weight_unit, 'volume' => $res->volume . " " . $res->volume_unit,'volumetric_weight' => $res->volumetric_weight . " " . $res->volweight_uom, 'stackable' => $stackable,'splittable'=>$splittable,'grounded'=>$grounded, 'action' => $action, 'empty' => $empty,'dg_goods'=>$dg_goods,"ldm"=>$res->ldm,'scanned_quantity'=>$scanned_quantity);
            }
        }

    }
    echo json_encode($cargos);
}

public function deleteorder($id)
{
    if ($id != '' || $id != 0) {
        $chk = $this->db->select('id')->get_where("tb_orders", array('id' => $id));
        if ($chk->num_rows() > 0) {
            $upd = $this->db->where(array('id' => $id))->update('tb_orders', array('status' => 0));
        } 
        $this->ordernotify('booking_delete',$id);
    }
    echo "1";
}

public function deleteordercargodetails()
{
    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : '0';
    $cargo_id = isset($_POST['cargo_id']) ? $_POST['cargo_id'] : '0';
    if ($order_id != '0' && $cargo_id != '0') {
        $chk = $this->db->select('id')->get_where('tb_order_cargodetails', array('order_id' => $order_id, 'cargo_id' => $cargo_id));
        if ($chk->num_rows() > 0) {
            $row_id = $chk->row()->id;
            $upd    = $this->db->where(array('id' => $row_id))->update('tb_order_cargodetails', array('status' => '0'));
            if ($upd) {
                echo "1";
            } else {
                echo "0";
            }
        } else {
            echo "0";
        }
    } else {
        echo "0";
    }

}

public function showcargodetails()
{
    $user_id = $this->session->userdata('user_id');
    $cargos  = array();
    $ids     = isset($_POST['cargo_id']) ? $_POST['cargo_id'] : "";
    $type    = isset($_POST['type']) ? $_POST['type'] : "";
    if ($ids != "") {
        $cargo_ids = implode(',', $ids);
        if (!empty($cargo_ids)) {
            $qry = $this->db->query("SELECT c.* FROM tb_cargo_details c WHERE c.id IN (" . $cargo_ids . ") AND c.status='1' GROUP BY c.id ORDER BY c.id DESC");
            if ($qry->num_rows() > 0) {
                if ($type == 'popup') {
                    foreach ($qry->result() as $res) {
                        $stackable = 'Off';
                        if ($res->stackable == 0) {
                            $stackable = 'Off';
                        } else if ($res->stackable == 1) {
                            $stackable = 'On';
                        }
                        $grounded = 'Off';
                        if ($res->grounded == 0) {
                            $grounded = 'Off';
                        } else if ($res->grounded == 1) {
                            $grounded = 'On';
                        }
                        $splittable = 'Off';
                        if ($res->splittable == 0) {
                            $splittable = 'Off';
                        } else if ($res->splittable == 1) {
                            $splittable = 'On';
                        }
                        $dg_goods = 'Off';
                        if ($res->dg_goods == 0) {
                            $dg_goods = 'Off';
                        } else if ($res->dg_goods == 1) {
                            $dg_goods = 'On';
                        }
                        $action = "<button id=".$res->id." class='btn btn-xs' onclick='editpopupcargodetails(".$res->id.",event)'><span class='icon tru-icon-edit' aria-hidden='true'></span></button> <button id=".$res->id." class='btn btn-xs' onclick='deletepopupcargodetails(".$res->id.",event)'><small><i class='glyphicon glyphicon-trash'></i></small></button>";
                        $cargos[] = array('cargo_type' => $res->cargo_type, 'goods_desc' => $res->goods_description, 'quantity' => $res->quantity, 'length' => $res->length . " " . $res->length_unit, 'width' => $res->width . " " . $res->width_unit, 'height' => $res->height . " " . $res->height_unit, 'weight' => $res->weight . " " . $res->weight_unit, 'volume' => $res->volume . " " . $res->volume_unit,'volumentric_weight'=>$res->volumetric_weight." ".$res->volweight_uom, 'stackable' => $stackable,'grounded'=>$grounded,'splittable'=>$splittable,'dg_goods'=>$dg_goods,'ldm'=>$res->ldm, 'action' => $action);
                    }
                } else {
                    foreach ($qry->result() as $res) {
                        $stackable = 'Off';
                        if ($res->stackable == 0) {
                            $stackable = 'Off';
                        } else if ($res->stackable == 1) {
                            $stackable = 'On';
                        }
                        $grounded = 'Off';
                        if ($res->grounded == 0) {
                            $grounded = 'Off';
                        } else if ($res->grounded == 1) {
                            $grounded = 'On';
                        }
                        $splittable = 'Off';
                        if ($res->splittable == 0) {
                            $splittable = 'Off';
                        } else if ($res->splittable == 1) {
                            $splittable = 'On';
                        }
                        $dg_goods = 'Off';
                        if ($res->dg_goods == 0) {
                            $dg_goods = 'Off';
                        } else if ($res->dg_goods == 1) {
                            $dg_goods = 'On';
                        }
                        
                        $cargos_ar  = array('id' => $res->id, 'cargo_type' => $res->cargo_type, 'goods_desc' => $res->goods_description, 'quantity' => $res->quantity, 'length' => $res->length . " " . $res->length_unit, 'width' => $res->width . " " . $res->width_unit, 'height' => $res->height . " " . $res->height_unit, 'weight' => $res->weight . " " . $res->weight_unit, 'volume' => $res->volume . " " . $res->volume_unit, 'stackable' => $stackable,'dg_goods'=>$dg_goods);
                        $cargo_type = '"' . $res->cargo_type . '"';
                        $goods_desc = '"' . $res->goods_description . '"';
                        $length_unit = '"' . $res->length_unit . '"';
                        $width_unit = '"' . $res->width_unit . '"';
                        $height_unit = '"' . $res->height_unit . '"';
                        $weight_unit = '"' . $res->weight_unit . '"';
                        $volume_unit = '"' . $res->volume_unit . '"';
                        $volweight_uom = '"' . $res->volweight_uom . '"';
                        $scanned_quantity = 0;
                        $action     = "<ul class='nav nav-tabs'><li class='dropdown tablebtnrleft'> <a class='dropdown-toggle' data-toggle='dropdown' href='#''><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'>" . "<li><a id='bEdit' type='button' class='btn btn-sm btn-default'  onclick='rowcargoEdit(this," . $res->id . "," . $cargo_type . "," . $goods_desc . "," . $res->quantity . "," . $res->length . "," . $res->width . "," . $res->height . "," . $res->weight . "," . $res->volume . ",".$res->volumetric_weight. ",".$res->stackable.",".$res->grounded.",".$res->splittable.",".$res->dg_goods.",".$length_unit.",".$width_unit.",".$height_unit.",".$weight_unit.",".$volume_unit.",".$volweight_uom.",".$res->ldm.",".$scanned_quantity.");'><span class='glyphicon glyphicon-pencil' ></span>Edit</a></li><li><a id='bElim' type='button' class='btn btn-sm btn-default' onclick='rowCargoElim(this," . $res->id . ");'><span class='glyphicon glyphicon-trash' > </span>Remove</a></li><li><a id='bAcep' type='button' class='btn btn-sm btn-default' style='display:none;' onclick='rowAcep(this);'><span class='glyphicon glyphicon-ok' > </span>Update</a></li><li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowAdd(this);'><span class='glyphicon glyphicon-plus' > </span>Add Cargo Details</a></li><li><a id='innerpacking' type='button' class='btn btn-sm btn-default' onclick='getinnercargo(this," . $res->id . ");'><span class='fa fa-archive' > </span>Get Inner Cargo</a></li>";

                        $cargos[] = array('id' => $res->id, 'cargo_type' => $res->cargo_type, 'goods_desc' => $res->goods_description, 'quantity' => $res->quantity, 'length' => $res->length . " " . $res->length_unit, 'width' => $res->width . " " . $res->width_unit, 'height' => $res->height . " " . $res->height_unit, 'weight' => $res->weight . " " . $res->weight_unit, 'volume' => $res->volume . " " . $res->volume_unit, 'volumentric_weight'=>$res->volumetric_weight." ".$res->volweight_uom, 'stackable' => $stackable,'splittable'=>$splittable,'grounded'=>$grounded,'dg_goods'=>$dg_goods,'ldm'=>$res->ldm, 'action' => $action,'scanned_quantity'=>$scanned_quantity);
                    }
                }
            }
        }
    }
    echo json_encode($cargos);
}

public function getinnercargo()
{
    $cargos   = array();
    $cargo_id = $this->input->post('cargo_id');
    if ($cargo_id != "" || $cargo_id != 0) {
        $inner_qry = $this->db->query("SELECT i.id as inner_id,i.cargo_type as inner_cargo,i.goods_description as inner_gd,i.quantity as inner_quantity,i.length as inner_length,i.length_unit as inner_lum,i.width as inner_width,i.width_unit as inner_wum,i.height as inner_height,i.height_unit as inner_hum,i.weight as inner_weight,i.weight_unit as inner_weum,i.volume as inner_volume,i.volume_unit as inner_vum,i.stackable as inner_stackable FROM tb_inner_cargo i WHERE i.cargo_id='" . $cargo_id . "' AND i.status='1' GROUP BY i.id ORDER BY i.id DESC");

        if ($inner_qry->num_rows() > 0) {
            foreach ($inner_qry->result() as $inner) {
                $stackable = 'Off';
                if ($inner->inner_stackable == 0) {
                    $stackable = 'Off';
                } else if ($inner->inner_stackable == 1) {
                    $stackable = 'On';
                }
                $cargo_type = '"' . $inner->inner_cargo . '"';
                $goods_desc = '"' . $inner->inner_gd . '"';
                /* $action     = "<ul class='nav nav-tabs'><li class='dropdown'> <a class='dropdown-toggle' data-toggle='dropdown' href='#''><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'>" . "<li><a id='bEdit' type='button' class='btn btn-sm btn-default'  onclick='rowinnercargoEdit(this," . $inner->inner_id . "," . $cargo_type . "," . $goods_desc . "," . $inner->inner_quantity . "," . $inner->inner_length . "," . $inner->inner_width . "," . $inner->inner_height . "," . $inner->inner_weight . "," . $inner->inner_volume . ");'><span class='glyphicon glyphicon-pencil' ></span>Edit</a></li><li><a id='bElim' type='button' class='btn btn-sm btn-default'  onclick='deleteinnercargopackage(" . $inner->inner_id . ")'><span class='glyphicon glyphicon-trash' > </span>Remove</a></li><li><a id='bAcep' type='button' class='btn btn-sm btn-default' style='display:none;' onclick='rowAcep(this);'><span class='glyphicon glyphicon-ok' > </span>Update</a></li><li><a id='innerpacking' type='button' class='btn btn-sm btn-default' onclick='addinnercargo(this," . $cargo_id . ");'><span class='fa fa-archive' > </span>Inner Cargo</a></li>";*/
                $action = "";
                $cargos[] = array('inner_id' => $inner->inner_id, 'main_cargo_id' => $cargo_id, 'inner_cargo' => $inner->inner_cargo, 'inner_gd' => $inner->inner_gd, 'inner_quantity' => $inner->inner_quantity, 'inner_width' => $inner->inner_width . " " . $inner->inner_wum, 'inner_height' => $inner->inner_height . " " . $inner->inner_hum, 'inner_length' => $inner->inner_length . " " . $inner->inner_lum, 'inner_weight' => $inner->inner_weight . " " . $inner->inner_weum, 'inner_volume' => $inner->inner_volume . " " . $inner->inner_vum, 'inner_stackable' => $stackable, 'action' => $action);
            }
        }
    }
    echo json_encode($cargos);
}

public function deleteorderpartydetails()
{
    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : "0";
    $party_id = isset($_POST['party_id']) ? $_POST['party_id'] : "0";
    if ($order_id != '0' && $party_id != '0') {
        $getdata = $this->db->query("SELECT id FROM tb_order_parties WHERE status='1' AND party_id='" . $party_id . "' AND order_id ='" . $order_id . "'");
        if ($getdata->num_rows() > 0) {
            $id  = $getdata->row()->id;
            $upd = $this->db->where(array('id' => $id))->update("tb_order_parties", array('status' => 0));
            if ($upd) {
                echo "1";
            } else {
                echo "0";
            }
        } else {
            echo "0";
        }
    }
}

public function savecargo($id = null)
{
    $post      = $this->input->post();
    $cdate     = date('Y-m-d H:i:s');
    $cargo_id  = 0;
    $inner_id  = $data  = $inner  = array();
    $user_id   = $this->session->userdata('user_id');
    $stackable = isset($_POST['stackable']) ? $_POST['stackable'] : "0";
    $grounded = isset($_POST['grounded']) ? $_POST['grounded'] : "0";
    $splittable = isset($_POST['splittable']) ? $_POST['splittable'] : "0";
    $dg_goods = isset($_POST['dg_goods']) ? $_POST['dg_goods'] : "0";
    $length    = isset($post['length']) ? $post['length'] : "0.00";
    $width     = isset($post['width']) ? $post['width'] : "0.00";
    $height    = isset($post['height']) ? $post['height'] : "0.00";
    $weight    = isset($post['weight']) ? $post['weight'] : "0.00";
    $volume    = isset($post['volume']) ? $post['volume'] : "0.00";
    $ldm    = isset($post['ldm']) ? $post['ldm'] : "0.00";
    
    if($ldm == "" || !(is_numeric($ldm))){
        $ldm = "0.00";
    }
    $volumetric_weight    = isset($post['volumetric_weight']) ? $post['volumetric_weight'] : "0.00";
    if($length == ""){
        $length = '0.00';
    }
    if($width == ""){
        $width = '0.00';
    } 
    if($height == ""){
        $height = '0.00';
    }
    if($weight == ""){
        $weight = '0.00';
    } 
    if($volume == ""){
        $volume = '0.00';
    }
    if($volumetric_weight == ""){
        $volumetric_weight = '0.00';
    }

    $cargo = array('cargo_type' => $post['cargo_type'], 'goods_description' => $post['goods_desc'], 'quantity' => $post['quantity'], 'length' => $length, 'length_unit' => $post['length_uom'], 'width' => $width, 'width_unit' => $post['width_uom'], 'height' => $height, 'height_unit' => $post['height_uom'], 'weight' => $weight, 'weight_unit' => $post['weight_uom'], 'volume' => $volume, 'volume_unit' => $post['volume_uom'],'volumetric_weight'=>$volumetric_weight,'volweight_uom'=>$post['volweight_uom'], 'stackable' => $stackable,'grounded' => $grounded,'splittable' => $splittable,'dg_goods'=>$dg_goods, 'createdby' => $user_id, 'createdon' => $cdate,'ldm'=>$ldm);
    if ($id == null) {
        $ins      = $this->db->insert("tb_cargo_details", $cargo);
        $cargo_id = $this->db->insert_id();
        $data = array('cargo_id' => $cargo_id, 'inner_cargo_id' => $inner_id);
    } else {
        $cargo_id = $id;
        $upd      = $this->db->where(array('id' => $cargo_id))->update("tb_cargo_details", $cargo);
        $upd = $this->db->where(array('cargo_id'=>$cargo_id))->update("tb_order_cargodetails",array('length' => $length, 'width' => $width, 'height' => $height, 'weight' => $weight, 'volume' => $volume, 'quantity' => $post['quantity'], 'quantity_type' => $post['cargo_type'], 'cargo_content' => $post['goods_desc'],'volumetric_weight'=>$volumetric_weight,'volweight_uom'=>$post['volweight_uom'],'ldm'=>$ldm));
        $data = array('cargo_id' => $cargo_id, 'inner_cargo_id' => $inner_id);
    }
    $order_id = isset($_POST['order_forcargo']) ? $_POST['order_forcargo'] : "0";
    if ($order_id != '0') {
        $chk = $this->db->select('id')->get_where('tb_order_cargodetails', array('order_id' => $order_id, 'cargo_id' => $cargo_id, 'status' => '1'));
        $gethandling_unit = $this->db->query("SELECT id FROM tbl_shipunit_types WHERE unit_name LIKE '" . $post['cargo_type'] . "'");
        $handling_unit    = "";
        if ($gethandling_unit->num_rows() > 0) {
            $handling_unit = $gethandling_unit->row()->id;
        } else {
            $handlingunit_ar = array('unit_name' => $post['cargo_type'],'description'=>$post['cargo_type'], 'user_id' => $user_id, 'created_at' => $cdate, 'status' => '1');
            $this->db->insert("tbl_shipunit_types", $handlingunit_ar);
            $handling_unit = $this->db->insert_id();
        }
        if ($chk->num_rows() == 0) {
            $ins = $this->db->insert('tb_order_cargodetails', array('order_id' => $order_id, 'cargo_id' => $cargo_id, 'status' => '1', 'createdon' => $cdate, 'length' => $length, 'width' => $width, 'height' => $height, 'weight' => $weight, 'volume' => $volume, 'quantity' => $post['quantity'], 'quantity_type' => $post['cargo_type'], 'cargo_content' => $post['goods_desc'], 'handling_unit' => $handling_unit,'volumetric_weight'=>$volumetric_weight,'volweight_uom'=>$post['volweight_uom'],'ldm'=>$ldm));
        }else{
            $upd = $this->db->where(array('order_id'=>$order_id,'cargo_id'=>$cargo_id))->update("tb_order_cargodetails",array('length' => $length, 'width' => $width, 'height' => $height, 'weight' => $weight, 'volume' => $volume, 'quantity' => $post['quantity'], 'quantity_type' => $post['cargo_type'], 'cargo_content' => $post['goods_desc'], 'handling_unit' => $handling_unit,'volumetric_weight'=>$volumetric_weight,'volweight_uom'=>$post['volweight_uom'],'ldm'=>$ldm));
        }

    }
    echo json_encode($data);
}
public function saveinnercargo($id = null)
{
    $post          = $this->input->post();
    $cdate         = date('Y-m-d H:i:s');
    $cargo_id      = 0;
    $inner_id      = $data      = $inner      = array();
    $main_cargo_id = isset($_POST['main_cargo_id']) ? $_POST['main_cargo_id'] : "0";
    $user_id       = $this->session->userdata('user_id');
    $stackable     = isset($_POST['stackable']) ? $_POST['stackable'] : "0";
    $length        = isset($post['length']) ? $post['length'] : "0.00";
    $width         = isset($post['width']) ? $post['width'] : "0.00";
    $height        = isset($post['height']) ? $post['height'] : "0.00";
    $weight        = isset($post['weight']) ? $post['weight'] : "0.00";
    $volume        = isset($post['volume']) ? $post['volume'] : "0.00";
        //   if($main_cargo_id != "0"){
    if($length == ""){
        $length = '0.00';
    }
    if($width == ""){
        $width = '0.00';
    } 
    if($height == ""){
        $height = '0.00';
    }
    if($weight == ""){
        $weight = '0.00';
    } 
    if($volume == ""){
        $volume = '0.00';
    }
    if ($id == null) {
        $inner_cargo   = array('cargo_id' => $main_cargo_id, 'cargo_type' => $post['cargo_type'], 'goods_description' => $post['goods_desc'], 'quantity' => $post['quantity'], 'length' => $length, 'length_unit' => $post['length_uom'], 'width' => $width, 'width_unit' => $post['width_uom'], 'height' => $height, 'height_unit' => $post['height_uom'], 'weight' => $weight, 'weight_unit' => $post['weight_uom'], 'volume' => $volume, 'volume_unit' => $post['volume_uom'], 'stackable' => $stackable, 'createdby' => $user_id, 'createdon' => $cdate);
        $ins           = $this->db->insert("tb_inner_cargo", $inner_cargo);
        $innercargo_id = $this->db->insert_id();
        $data          = array('main_cargo_id' => $main_cargo_id);
    } else {
        $innercargo_id = $id;
        if ($main_cargo_id == "" && $main_cargo_id == 0) {
            $getmaincargo = $this->db->select("cargo_id")->get_where("tb_inner_cargo", array('id' => $innercargo_id));
            if ($getmaincargo->num_rows() > 0) {
                $main_cargo_id = $getmaincargo->row()->cargo_id;
            }
        }
        $inner_cargo = array('cargo_id' => $main_cargo_id, 'cargo_type' => $post['cargo_type'], 'goods_description' => $post['goods_desc'], 'quantity' => $post['quantity'], 'length' => $length, 'length_unit' => $post['length_uom'], 'width' => $width, 'width_unit' => $post['width_uom'], 'height' => $height, 'height_unit' => $post['height_uom'], 'weight' => $weight, 'weight_unit' => $post['weight_uom'], 'volume' => $volume, 'volume_unit' => $post['volume_uom'], 'stackable' => $stackable);
        $upd         = $this->db->where(array('id' => $innercargo_id))->update("tb_inner_cargo", $inner_cargo);
        $data        = array('main_cargo_id' => $main_cargo_id);
    }
    echo json_encode($data);
}
public function savepopupcargo($id = null)
{
    $post      = $this->input->post();
    $cdate     = date('Y-m-d H:i:s');
    $cargo_id  = 0;
    $inner_id  = $data  = $inner  = array();
    $user_id   = $this->session->userdata('user_id');
    $stackable = isset($_POST['popupstackable']) ? $_POST['popupstackable'] : "0";
    $grounded = isset($_POST['popupgroundable']) ? $_POST['popupgroundable'] : "0";
    $splittable = isset($_POST['popupsplittable']) ? $_POST['popupsplittable'] : "0";
    $popuplength = isset($_POST['popuplength']) ? $_POST['popuplength'] : "0";
    $popupwidth = isset($_POST['popupwidth']) ? $_POST['popupwidth'] : "0";
    $popupheight = isset($_POST['popupheight']) ? $_POST['popupheight'] : "0";
    $popupweight = isset($_POST['popupweight']) ? $_POST['popupweight'] : "0";
    $popupvolume = isset($_POST['popupvolume']) ? $_POST['popupvolume'] : "0";
    $popupdg_goods = isset($_POST['popupdg_goods']) ? $_POST['popupdg_goods'] : "0";
    $popupldm = isset($_POST['popupldm']) ? $_POST['popupldm'] : "0";
    if($popupldm == "" || !(is_numeric($popupldm))){
        $popupldm = "0.00";
    }
    $popupvolumetric_weight = isset($_POST['popupvolumetric_weight']) ? $_POST['popupvolumetric_weight'] : "0";
    if($popuplength == ""){
        $popuplength = '0.00';
    }
    if($popupwidth == ""){
        $popupwidth = '0.00';
    } 
    if($popupheight == ""){
        $popupheight = '0.00';
    }
    if($popupweight == ""){
        $popupweight = '0.00';
    } 
    if($popupvolume == ""){
        $popupvolume = '0.00';
    }
    if($popupvolumetric_weight == ""){
        $popupvolumetric_weight = '0.00';
    }

    $cargo     = array('cargo_type' => $post['popupcargo_type'], 'goods_description' => $post['popupgoods_desc'], 'quantity' => $post['popupquantity'], 'length' => $popuplength, 'length_unit' => $post['popuplength_uom'], 'width' => $popupwidth, 'width_unit' => $post['popupwidth_uom'], 'height' => $popupheight, 'height_unit' => $post['popupheight_uom'], 'weight' => $popupweight, 'weight_unit' => $post['popupweight_uom'], 'volume' => $popupvolume, 'volume_unit' => $post['popupvolume_uom'],'volumetric_weight'=>$popupvolumetric_weight,'volweight_uom'=>$post['popupvow_uom'], 'stackable' => $stackable,'grounded'=>$grounded,'splittable'=>$splittable, 'createdby' => $user_id, 'createdon' => $cdate,'dg_goods'=>$popupdg_goods,'ldm'=>$popupldm);
    if ($id == null) {
        $ins      = $this->db->insert("tb_cargo_details", $cargo);
        $cargo_id = $this->db->insert_id();

        $data = array('cargo_id' => $cargo_id);
    } else {
        $cargo_id = $id;
        $upd      = $this->db->where(array('id' => $cargo_id))->update("tb_cargo_details", $cargo);
        $data     = array('cargo_id' => $cargo_id);
    }
    echo json_encode($data);
}

public function editcargodetails()
{
    $id    = $this->input->post('id');
    $cargo = $inner = array();
    if ($id != "") {
        $get_cargo = $this->db->query("SELECT * FROM tb_cargo_details WHERE id='" . $id . "' GROUP BY id");
        if ($get_cargo->num_rows() > 0) {
            $get_inner = $this->db->query("SELECT * FROM tb_inner_cargo WHERE cargo_id='" . $id . "' AND status='1' GROUP BY id");
            if ($get_inner->num_rows() > 0) {
                foreach ($get_inner->result() as $res) {
                    $inner[] = array('inner_id' => $res->id, 'inner_cargo_type' => $res->cargo_type, 'inner_goods_description' => $res->goods_description, 'inner_quantity' => $res->quantity, 'inner_length' => $res->length, 'inner_length_uom' => $res->length_unit, 'inner_width' => $res->width, 'inner_width_uom' => $res->width_unit, 'inner_height' => $res->height, 'inner_height_uom' => $res->height_unit, 'inner_weight' => $res->weight, 'inner_weight_uom' => $res->weight_unit, 'inner_volume' => $res->volume, 'inner_volume_uom' => $res->volume_unit, 'inner_stackable' => $res->stackable);
                }
            }
            $cargo = array('id' => $get_cargo->row()->id, 'cargo_type' => $get_cargo->row()->cargo_type, 'goods_description' => $get_cargo->row()->goods_description, 'quantity' => $get_cargo->row()->quantity, 'length' => $get_cargo->row()->length, 'length_uom' => $get_cargo->row()->length_unit, 'width' => $get_cargo->row()->width, 'width_uom' => $get_cargo->row()->width_unit, 'height' => $get_cargo->row()->height, 'height_uom' => $get_cargo->row()->height_unit, 'weight' => $get_cargo->row()->weight, 'weight_uom' => $get_cargo->row()->weight_unit, 'volume' => $get_cargo->row()->volume, 'volume_uom' => $get_cargo->row()->volume_unit, 'volumetric_weight'=>$get_cargo->row()->volumetric_weight, 'volweight_uom'=>$get_cargo->row()->volweight_uom,'stackable' => $get_cargo->row()->stackable,'grounded' => $get_cargo->row()->grounded,'splittable' => $get_cargo->row()->splittable,'dg_goods'=>$get_cargo->row()->dg_goods,'ldm'=>$get_cargo->row()->ldm, 'inner' => $inner);
        }
    }
    echo json_encode($cargo);
}

public function deleteinnercargopackage()
{
    $inner_id = isset($_POST['inner_id']) ? $_POST['inner_id'] : '0';
    $cargo_id = 0;
    if ($inner_id != 0) {
        $chk = $this->db->select('id,cargo_id')->get_where('tb_inner_cargo', array('id' => $inner_id));
        if ($chk->num_rows() > 0) {
            $cargo_id = $chk->row()->cargo_id;
            $upd      = $this->db->where(array('id' => $inner_id))->update('tb_inner_cargo', array('status' => '0'));

        }
    }
    echo json_encode($cargo_id);
}

public function insertorder()
{

    $cdate            = date('Y-m-d H:i:s');
    $user_id          = $this->session->userdata('user_id');
    $company_code     = isset($_POST['company_code']) ? $_POST['company_code'] : "";
    $branch_code      = isset($_POST['branch_code']) ? $_POST['branch_code'] : "";
    $department_code  = isset($_POST['department_code']) ? $_POST['department_code'] : "";
    $product          = isset($_POST['product']) ? $_POST['product'] : "";
    $service          = isset($_POST['service']) ? $_POST['service'] : "";
    $order_shipper_id = isset($_POST['order_shipper_id']) ? $_POST['order_shipper_id'] : "";
    /*$order_status     = isset($_POST['order_status']) ? $_POST['order_status'] : "";
    if ($order_status == "") {
        $order_status = "OPEN";
    }*/
    $delivery_terms = isset($_POST['delivery_terms']) ? $_POST['delivery_terms'] : "";
    $incoterm       = isset($_POST['incoterm']) ? $_POST['incoterm'] : "";
    $shipment_id    = isset($_POST['delivery_note']) ? $_POST['delivery_note'] : "";
    $container_no    = isset($_POST['container_num']) ? $_POST['container_num'] : "";
    $porder         = isset($_POST['purchase_order']) ? $_POST['purchase_order'] : "";
    $notify_party   = isset($_POST['notify_party']) ? $_POST['notify_party'] : "";
    $goods_value    = isset($_POST['goods_value']) ? $_POST['goods_value'] : "0.00";
    if ($goods_value == "") {
        $goods_value = 0.00;
    }
    $party_row_id     = isset($_POST['order_party_row_id']) ? $_POST['order_party_row_id'] : "0";
    $order_inv_row_id = isset($_POST['order_inv_row_id']) ? $_POST['order_inv_row_id'] : '0';
    $order_cargo_id   = isset($_POST['order_cargo_id']) ? $_POST['order_cargo_id'] : "";
    $pickup           = isset($_POST['order_pickup_id']) ? $_POST['order_pickup_id'] : "0";
    $delivery         = isset($_POST['order_drop_id']) ? $_POST['order_drop_id'] : "";
    $early_pickup     = isset($_POST['early_pickup']) ? $_POST['early_pickup'] : "";
    $late_pickup      = isset($_POST['late_pickup']) ? $_POST['late_pickup'] : "";
    $early_delivery   = isset($_POST['early_delivery']) ? $_POST['early_delivery'] : "";
    $late_delivery    = isset($_POST['late_delivery']) ? $_POST['late_delivery'] : "";
    $modeof_trasnport = isset($_POST['modeof_trasnport']) ? $_POST['modeof_trasnport'] : "LTL";
    $order_type       = isset($_POST['order_type']) ? $_POST['order_type'] : "";
    $rev_row_id       = isset($_POST['rev_row_id']) ? $_POST['rev_row_id'] : "";
    $ordcost_row_id       = isset($_POST['ordcost_row_id']) ? $_POST['ordcost_row_id'] : "";
    $customer_code       = isset($_POST['customer_id']) ? $_POST['customer_id'] : "";
    $driver_pickup_instructions       = isset($_POST['driver_pickup_instructions']) ? $_POST['driver_pickup_instructions'] : "";
    $driver_delivery_instructions       = isset($_POST['driver_delivery_instructions']) ? $_POST['driver_delivery_instructions'] : "";
    $e_pickup         = date('Y-m-d H:i:s');
    if ($early_pickup != "") {
        $e_pickup = date('Y-m-d H:i:s', strtotime($early_pickup));
    }
    if ($late_pickup != "") {
        $l_pickup = date('Y-m-d H:i:s', strtotime($late_pickup));
    } else {
        $l_pickup = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($e_pickup)));
    }
    $e_delivery = date('Y-m-d H:i:s');
    if ($early_delivery != "") {
        $e_delivery = date('Y-m-d H:i:s', strtotime($early_delivery));
    }
    if ($late_delivery != "") {
        $l_delivery = date('Y-m-d H:i:s', strtotime($late_delivery));
    } else {
        $l_delivery = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($e_delivery)));
    }
    $same_porder = "0";
    if ($porder != "") {
        $chkporder = $this->db->query("SELECT id FROM tb_order_details WHERE purchase_order='" . $porder . "' AND status='1'");
        if ($chkporder->num_rows() > 0) {
            $same_porder = "1";
        }
    }
    if ($same_porder == 1) {
        $this->session->set_flashdata('error_msg', 'Purchase Order "' . $porder . '" already exists with One ORDER');
    } else {
        $drop_id        = $pickup_custid        = 0;
        $pickup_name    = $pickup_country    = $pickup_street    = $pickup_pincode    = $pickup_city    = $drop_name    = $drop_country    = $drop_street    = $drop_pincode    = $drop_city    = $pickup_address    = $pickup_state    = $drop_address    = $drop_state = "";
        $getdrop_custid = $this->db->query("SELECT name,location_id as city,street,state,address,country,customeridentifier,pincode FROM tbl_party_master WHERE id='" . $delivery . "'");
        if ($getdrop_custid->num_rows() > 0) {
            $drop_id      = $getdrop_custid->row()->customeridentifier;
            $drop_name    = $getdrop_custid->row()->name;
            $drop_state   = $getdrop_custid->row()->state;
            $drop_address = $getdrop_custid->row()->address;
            $drop_country = $getdrop_custid->row()->country;
            $drop_street  = $getdrop_custid->row()->street;
            $drop_pincode = $getdrop_custid->row()->pincode;
            $drop_city    = $getdrop_custid->row()->city;
        }

        $getshippercustid = $this->db->query("SELECT name,location_id as city,street,state,address,country,customeridentifier,pincode FROM tbl_party_master WHERE id='" . $order_shipper_id . "'");
        if ($getshippercustid->num_rows() > 0) {
            $pickup_custid  = $getshippercustid->row()->customeridentifier;
            $pickup_name    = $getshippercustid->row()->name;
            $pickup_state   = $getshippercustid->row()->state;
            $pickup_address = $getshippercustid->row()->address;
            $pickup_country = $getshippercustid->row()->country;
            $pickup_street  = $getshippercustid->row()->street;
            $pickup_pincode = $getshippercustid->row()->pincode;
            $pickup_city    = $getshippercustid->row()->city;

        }
        $add1             = implode(",", [$pickup_street, $pickup_city, $pickup_country, $pickup_pincode]);
        $add2             = implode(",", [$drop_street, $drop_city, $drop_country, $drop_pincode]);
        $data             = getlatlngsbyplace($add1);
        $lat1             = @$data[0];
        $lng1             = @$data[1];
        $data             = [];
        $data             = getlatlngsbyplace($add2);
        $lat2             = @$data[0];
        $lng2             = @$data[1];
        $ship_row_id      = 0;
        $enddate          = date('Y-m-d H:i:s', strtotime("+1 day"));
        $tid              = $tname              = "";
        $gettrasnportmode = $this->db->query("SELECT id,name FROM tb_transportmode WHERE code LIKE '" . $modeof_trasnport . "'");
        if ($gettrasnportmode->num_rows() > 0) {
            $tid   = $gettrasnportmode->row()->id;
            $tname = $gettrasnportmode->row()->name;
        }
        if ($shipment_id == "") {
            $shipment_id = "KN" . time();
        }

        $ship_arr   = array('shipid' => $shipment_id, 'txnid' => $shipment_id, 'trucktype' => $tname, 'pickupcnt' => '1', 'dropcnt' => '1', 'unitspec' => 1, 'insertusr' => $pickup_custid, 'carrier' => '0', 'insertuserdate' => $cdate, 'enddate' => $enddate, 'insdate' => $cdate, 'upddate' => $cdate, 'reason' => 'SHIPMENT', 'purpose' => 'SEND INTEGRATION', 'ship_object' => 'SHIPMENT', 'logdate' => $cdate, 'transport_mode' => $modeof_trasnport, 'domainname' => $branch_code, 'company_code' => $company_code, 'branch_code' => $branch_code, 'product' => $product, 'freight_term' => '60', 'freight_termname' => 'Free of Charge', 'incoterm' => $incoterm, 'modeoftransport' => $tid);
        $chk_shipid = $this->db->query("SELECT id FROM tb_shipments WHERE shipid LIKE '" . $shipment_id . "'");
        if ($chk_shipid->num_rows() > 0) {
            $ship_row_id = $chk_shipid->row()->id;
            $this->db->where(array('id' => $ship_row_id))->update("tb_shipments", $ship_arr);
        } else {
            $ship_arr['createdon'] = $cdate;
            $ship_ins              = $this->db->insert("tb_shipments", $ship_arr);
            $ship_row_id           = $this->db->insert_id();
        }
        $customer_id = 0;
        $cust_id     = $this->session->userdata('cust_id');
        if($pickup == ""){
            $pickup = 0;
        }
        $customer_id = $pickup;
        if($customer_id == "" || $customer_id == "0"){
            if($customer_code != "" && $customer_code != "0"){
                $getcustomerid = $this->db->select("id")->get_where("tb_customers",array('code'=>$customer_code,'user_id'=>$user_id,'status'=>'1'));
                if($getcustomerid->num_rows() >0){
                    $customer_id = $getcustomerid->row()->id;
                }
            }
        }
        if ($this->session->userdata('company_code') == 'NZKN') {
            if ($product == "") {
                $product = "KN AsiaLink";
            }
            if ($modeof_trasnport == "") {
                $modeof_trasnport = "LTL";
            }
            if ($service == "") {
                $service = "19";
            }
        }
        $curtz = $this->session->userdata("usr_tzone")['timezone'];
        $logdate = date('Y-m-d H:i:s');
        $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
        $logdate = $getactual['datetime'];
        $getpickup = getdatetimebytimezone(DFLT_TZ,$e_pickup,$curtz);
        $e_pickup = $getpickup['datetime'];
        $getlpickup = getdatetimebytimezone(DFLT_TZ,$l_pickup,$curtz);
        $l_pickup = $getlpickup['datetime'];
        $getdelivery = getdatetimebytimezone(DFLT_TZ,$e_delivery,$curtz);
        $e_delivery = $getdelivery['datetime'];
        $getldelivery = getdatetimebytimezone(DFLT_TZ,$l_delivery,$curtz);
        $l_delivery = $getldelivery['datetime'];
        $orderinfo       = array('shipment_id' => $ship_row_id, 'customer_id' => $customer_id, 'product' => $product, 'pickup_datetime' => $e_pickup, 'delivery_datetime' => $e_delivery, 'pickup_endtime' => $l_pickup, 'drop_endtime' => $l_delivery, 'goods_value' => $goods_value, 'company_code' => $company_code, 'branch_code' => $branch_code, 'createdon' => $cdate, 'drop_custid' => $drop_id, 'drop_partyid' => $drop_id, 'user_id' => $user_id, 'pickup_custid' => $pickup_custid, 'pickup_partyid' => $pickup_custid, 'pickup_country' => $pickup_country, 'pickup_city' => $pickup_city, 'pickup_pincode' => $pickup_pincode, 'pickup_company' => $pickup_name, 'pickup_address1' => $pickup_street,'pickup_address2'=>$pickup_state, 'delivery_country' => $drop_country, 'delivery_city' => $drop_city, 'delivery_pincode' => $drop_pincode, 'delivery_company' => $drop_name, 'delivery_address1' => $drop_street,'delivery_address2'=>$drop_state, 'is_created' => '1', 'plat' => $lat1, 'plng' => $lng1, 'dlat' => $lat2, 'dlng' => $lng2, 'transport_mode' => $modeof_trasnport,'created_source'=>'4','createdon'=>$logdate);
        $ins_order = $this->db->insert("tb_orders", $orderinfo);
        $order_id  = $this->db->insert_id();
        $get_country = $this->db->select('country_code,company_code')->get_where("tb_users",array('id'=>$user_id));
        $country_code  = $get_country->row()->country_code;
        $company_code  = $get_country->row()->company_code;
        $genord = array("user_id"=>$user_id,"order_id"=>$order_id,"country_code"=>$country_code,"company_code"=>$company_code);
        $booking_id = generatebookingid($genord);
        $upd     = $this->db->where(array('id' => $order_id))->update("tb_orders", array('order_id' => $booking_id));
        $details = array('service' => $service, 'delivery_term' => $delivery_terms, 'incoterm' => $incoterm, 'purchase_order' => $porder, 'notify_party' => $notify_party, 'department_code' => $department_code, 'temperature_control' => '0', 'valorance_insurance' => '0', 'high_cargo_value' => '0', 'customs_required' => '0', 'order_row_id' => $order_id, 'order_id' => $booking_id, 'createdon' => $cdate, 'shipper_id' => $order_shipper_id, 'order_type' => $order_type);
        $this->db->insert("tb_order_details", $details);
        $shipper_address    = array('order_id' => $order_id, 'party_master_id' => $order_shipper_id, 'location_id' => $pickup_city, 'street' => $pickup_street, 'state' => $pickup_state, 'address' => $pickup_address, 'pincode' => $pickup_pincode, 'country' => $pickup_country, 'user_id' => $user_id);
        $chk_shipperaddress = $this->db->select("id")->get_where("tbl_orderparty_address", array('order_id' => $order_id, 'party_master_id' => $order_shipper_id,'status'=>'1'));
        if ($chk_shipperaddress->num_rows() > 0) {
            $shipperadd_id = $chk_shipperaddress->row()->id;
            $upd_add       = $this->db->where(array('id' => $shipperadd_id))->update("tbl_orderparty_address", $shipper_address);
        } else {
            $shipper_address['createdon'] = $cdate;
            $this->db->insert("tbl_orderparty_address", $shipper_address);
            $shipperadd_id = $this->db->insert_id();
        }
        $delivery_address    = array('order_id' => $order_id, 'party_master_id' => $delivery, 'location_id' => $drop_city, 'street' => $drop_street, 'state' => $drop_state, 'address' => $drop_address, 'pincode' => $drop_pincode, 'country' => $drop_country, 'user_id' => $user_id);
        $chk_deliveryaddress = $this->db->select("id")->get_where("tbl_orderparty_address", array('order_id' => $order_id, 'party_master_id' => $delivery,'status'=>'1'));
        if ($chk_deliveryaddress->num_rows() > 0) {
            $dropadd_id = $chk_deliveryaddress->row()->id;
            $upd_add    = $this->db->where(array('id' => $dropadd_id))->update("tbl_orderparty_address", $delivery_address);
        } else {
            $delivery_address['createdon'] = $cdate;
            $this->db->insert("tbl_orderparty_address", $delivery_address);
            $dropadd_id = $this->db->insert_id();
        }
        $cargo_forship = array();
        if ($order_cargo_id != "") {
            $cargo_ids = array();
            $cargo_ids = explode(',', $order_cargo_id);
            for ($i = 0; $i < count($cargo_ids); $i++) {
                $length           = $width           = $height           = $weight           = $volume           = 0;
                $quantity         = 1;
                $getcargo_details = $this->db->query("SELECT cargo_type,length,width,height,weight,volumetric_weight,volume,quantity,goods_description FROM tb_cargo_details WHERE id='" . $cargo_ids[$i] . "'");
                $cargo_type       = $description       = "";
                if ($getcargo_details->num_rows() > 0) {
                    $length            = $getcargo_details->row()->length;
                    $width             = $getcargo_details->row()->width;
                    $height            = $getcargo_details->row()->height;
                    $weight            = $getcargo_details->row()->weight;
                    $volume            = $getcargo_details->row()->volume;
                    $quantity          = $getcargo_details->row()->quantity;
                    $cargo_type        = $getcargo_details->row()->cargo_type;
                    $description       = $getcargo_details->row()->goods_description;
                    $volumetric_weight = $getcargo_details->row()->volumetric_weight;
                    $cargo_forship[$i] = $getcargo_details->row()->cargo_type;
                }
                $gethandling_unit = $this->db->query("SELECT id FROM tbl_shipunit_types WHERE unit_name LIKE '" . $cargo_type . "'");
                $handling_unit    = "";
                if ($gethandling_unit->num_rows() > 0) {
                    $handling_unit = $gethandling_unit->row()->id;
                } else {
                    $handlingunit_ar = array('unit_name' => $cargo_type,'description'=>$cargo_type, 'user_id' => $user_id, 'created_at' => $cdate, 'status' => '1');
                    $this->db->insert("tbl_shipunit_types", $handlingunit_ar);
                    $handling_unit = $this->db->insert_id();
                }
                $cargo     = array('order_id' => $order_id, 'cargo_id' => $cargo_ids[$i], 'status' => '1', 'length' => $length, 'width' => $width, 'height' => $height, 'weight' => $weight,'volumetric_weight'=>$volumetric_weight,'volweight_uom'=>'kg', 'volume' => $volume, 'quantity' => $quantity, 'cargo_content' => $description, 'quantity_type' => $cargo_type, 'handling_unit' => $handling_unit);
                $ins_cargo = $this->db->insert("tb_order_cargodetails", $cargo);
            }
        }
        $unitspec = "1";
        if (!empty($cargo_forship)) {
            $unitspec = implode(',', $cargo_forship);
        }
        $updship      = $this->db->where(array('id' => $ship_row_id))->update("tb_shipments", array('unitspec' => $unitspec, 'txncode' => $booking_id));
        $total_weight = $total_volume = $total_quantity = 0;
        $gettotal     = $this->db->query("SELECT sum(weight) as total_weight,sum(volume) as total_volume,sum(quantity) as total_quantity FROM tb_order_cargodetails WHERE order_id='" . $order_id . "' AND status='1'");
        if ($gettotal->num_rows() > 0) {
            $total_volume   = $gettotal->row()->total_volume;
            $total_weight   = $gettotal->row()->total_weight;
            $total_quantity = $gettotal->row()->total_quantity;
        }
        $upd_order = $this->db->where(array('id' => $order_id))->update("tb_orders", array('volume' => $total_volume, 'weight' => $total_weight, 'quantity' => $total_quantity));
        $ids       = array();
        if ($shipment_id != "") {
            $ins_ref = array('order_id' => $order_id, 'reference_id' => 'DQ', 'ref_value' => $shipment_id, 'createdon' => $cdate);
            $ins     = $this->db->insert('tb_order_references', $ins_ref);

        }
        if($company_code == 'UKKN' || $company_code == 'AUKN'){
            if ($container_no != "") {
                $ins_ref = array('order_id' => $order_id, 'reference_id' => 'CTR', 'ref_value' => $container_no, 'createdon' => $cdate);
                $ins     = $this->db->insert('tb_order_references', $ins_ref);

            }
        }
        if ($porder != "") {
            $ins_ref = array('order_id' => $order_id, 'reference_id' => 'PO', 'ref_value' => $porder, 'createdon' => $cdate);
            $ins     = $this->db->insert('tb_order_references', $ins_ref);
        }
        if($driver_pickup_instructions != ""){
            $ins_ref = array('order_id' => $order_id, 'reference_id' => 'ORD_PIKINST', 'ref_value' => $driver_pickup_instructions, 'createdon' => $cdate);
            $ins     = $this->db->insert('tb_order_references', $ins_ref);
        }
        if($driver_delivery_instructions != ""){
            $ins_ref = array('order_id' => $order_id, 'reference_id' => 'ORD_DLVINST', 'ref_value' => $driver_delivery_instructions, 'createdon' => $cdate);
            $ins     = $this->db->insert('tb_order_references', $ins_ref);
        }
        if ($party_row_id != "0") {
            $ids = explode(',', $party_row_id);
        }
        if ($order_inv_row_id != 0 || $order_inv_row_id != "") {
            array_push($ids, $order_inv_row_id);
        }
        if (!empty($ids)) {
            for ($i = 0; $i < count($ids); $i++) {
                if ($ids[$i] != "") {
                    $getpartytype = $this->db->query("SELECT party_type_id FROM tbl_party_master WHERE id='" . $ids[$i] . "'");
                    $party_type   = 1;
                    if ($getpartytype->num_rows() > 0) {
                        $party_type = $getpartytype->row()->party_type_id;
                    }
                    $party     = array('order_id' => $order_id, 'party_id' => $ids[$i], 'status' => '1', 'createdon' => $cdate, 'status' => '1', 'party_type' => $party_type, 'order_number' => $booking_id);
                    $ins_party = $this->db->insert("tb_order_parties", $party);
                }
            }
        }
        if($order_shipper_id != "" || $order_shipper_id != 0){
            $party_type = 0;
            $chk = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Shipper','company_code'=>$company_code,'branch_code'=>$branch_code,'user_id'=>$user_id));
            if($chk->num_rows() >0){
                $party_type = $chk->row()->id;
            }else{
             $chk1 = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Shipper','company_code'=>$company_code,'user_id'=>$user_id));
             if($chk1->num_rows()>0)
                 $party_type = $chk1->row()->id;
         }

         $party     = array('order_id' => $order_id, 'party_id' => $order_shipper_id, 'status' => '1', 'createdon' => $cdate, 'status' => '1', 'party_type' => $party_type, 'order_number' => $booking_id);
         $ins_party = $this->db->insert("tb_order_parties", $party);
     } 
     if($delivery != "" || $delivery != 0){
        $party_type = 0;
        $chk = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Consignee','company_code'=>$company_code,'branch_code'=>$branch_code,'user_id'=>$user_id));
        if($chk->num_rows() >0){
            $party_type = $chk->row()->id;
        }else{
            $chk1 = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Consignee','company_code'=>$company_code,'user_id'=>$user_id));
            if($chk1->num_rows()>0){
             $party_type = $chk1->row()->id;
         }
     }
     $party = array('order_id' => $order_id, 'party_id' => $delivery, 'status' => '1', 'createdon' => $cdate, 'status' => '1', 'party_type' => $party_type, 'order_number' => $booking_id);
     $ins_party = $this->db->insert("tb_order_parties", $party);
 }
 $rev_ids = array();
 if($rev_row_id != "" && $rev_row_id != "0"){
    $rev_ids = explode(',', $rev_row_id);
    if($ordcost_row_id != ""){
        $cost_ids = array();
        $cost_ids = explode(',', $ordcost_row_id);
        foreach($cost_ids as $ids){
            array_push($rev_ids, $ids);
        }
    }
    if(!empty($rev_ids)){
        $upd = $this->db->query("UPDATE tb_reveneus set order_id ='".$order_id."' WHERE id IN (".implode(',', $rev_ids).")");
    }
}
$shipment_name = "BOXES";
$total_weight = $total_volume = $total_quantity = 0;
$gettotal = $this->db->query("SELECT sum(weight) as total_weight,sum(volume) as total_volume,sum(quantity) as total_quantity FROM tb_order_cargodetails WHERE order_id='".$order_id."'");
if($gettotal->num_rows() >0){
    $total_volume = $gettotal->row()->total_volume;
    $total_weight = $gettotal->row()->total_weight;
    $total_quantity = $gettotal->row()->total_quantity;
}
$getcust_details = $this->common->gettblrowdata(array('id'=>$customer_id),"name,phone,email_id",'tb_customers',0,0);
if(!empty($getcust_details)){
    $customer_email = $getcust_details['email_id'];
    $customer_phone = $getcust_details['phone'];
}
/*if($this->session->userdata('usr_tzone')['country'] == "RU"){*/
    if($order_id != "" && $customer_id != ""){
        $pickupinfo['country'] = trim($pickup_country);
        $pickupinfo['state'] = trim($pickup_state);
        $pickupinfo['city'] = trim($pickup_city);
        $pickupinfo['region'] = trim($pickup_street);
        $pickupinfo['zipcode'] = trim($pickup_pincode);
        $pickupinfo['stoptype'] = "P";
        $dropinfo['country'] = trim($drop_country);
        $dropinfo['state'] = trim($drop_state);
        $dropinfo['city'] = trim($drop_city);
        $dropinfo['region'] = trim($drop_street);
        $dropinfo['zipcode'] = trim($drop_pincode);
        $dropinfo['stoptype'] = "D";
        $pickupgeocode = checkgeocode($pickupinfo);
        $dropgeocode = checkgeocode($pickupinfo);
        if(!empty($pickupgeocode) && !empty($dropgeocode)){
            $pickupgeocode['stoptype'] = "P";
            $dropgeocode['stoptype'] = "D";
            $pickuproute = getcust_routeautomate($customer_id,$pickupgeocode);
            $droproute = getcust_routeautomate($customer_id,$dropgeocode);
            if(!empty($pickuproute) && !empty($droproute)){
                $orderinfo['id']=$order_id;
                $orderinfo['order_id']=$booking_id;
                $orderinfo['shipment_name']=$shipment_name;
                $orderinfo['customer_phone']=$customer_phone;
                $orderinfo['customer_email']=$customer_email;
                $orderinfo['volume']=$total_volume;
                $orderinfo['weight']=$total_weight;
                $orderinfo['quantity']=$total_quantity;
                $orderinfo['quantity']=$total_quantity;
                $shipment = createshipmentbyorder($pickuproute,$orderinfo);
            }else{
                $pickuproute1 = getcust_routeautomate($customer_id,$pickupinfo);
                $droproute1 = getcust_routeautomate($customer_id,$dropinfo);
                if(!empty($pickuproute1) && !empty($droproute1)){
                    $orderinfo['id']=$order_id;
                    $orderinfo['order_id']=$booking_id;
                    $orderinfo['shipment_name']=$shipment_name;
                    $orderinfo['customer_phone']=$customer_phone;
                    $orderinfo['customer_email']=$customer_email;
                    $orderinfo['volume']=$total_volume;
                    $orderinfo['weight']=$total_weight;
                    $orderinfo['quantity']=$total_quantity;
                    $orderinfo['quantity']=$total_quantity;
                    $shipment = createshipmentbyorder($pickuproute1,$orderinfo);
                }
            }
        }else{
            $pickuproute1 = getcust_routeautomate($customer_id,$pickupinfo);
            $droproute1 = getcust_routeautomate($customer_id,$dropinfo);
            if(!empty($pickuproute1) && !empty($droproute1)){
                $orderinfo['id']=$order_id;
                $orderinfo['order_id']=$booking_id;
                $orderinfo['shipment_name']=$shipment_name;
                $orderinfo['customer_phone']=$customer_phone;
                $orderinfo['customer_email']=$customer_email;
                $orderinfo['volume']=$total_volume;
                $orderinfo['weight']=$total_weight;
                $orderinfo['quantity']=$total_quantity;
                $orderinfo['quantity']=$total_quantity;
                $shipment = createshipmentbyorder($pickuproute1,$orderinfo);
            }
        }
    }
/*}*/
$pref_arr = array('pickup'=>$pickup_country,'drop'=>$drop_country,'customer_id'=>$customer_id,'service'=>$service,'product'=>$product,'user_id'=>$user_id,'company_code'=>$company_code,'order_id'=>$order_id);
/*if($company_code == 'PLKN'){*/
    $this->ratemanagement->addrecodfororderinsertion($pref_arr);
/*}*/
$this->ordernotify('booking_create',$order_id);
$this->getorderdetails($order_id);
}


redirect("orders");

}

public function savequickbook()
{
    $cdate = date('Y-m-d H:i:s');
    $user_id = $this->session->userdata('user_id');
    $cust_id = $this->session->userdata('cust_id');
    $company_code = isset($_POST['popupcompany_code']) ? $_POST['popupcompany_code'] : "";
    $branch_code = isset($_POST['popupbranch_code']) ? $_POST['popupbranch_code'] : "";
    $department_code = isset($_POST['popupdepartment_code']) ? $_POST['popupdepartment_code'] : "";
    $product = isset($_POST['productpopup']) ? $_POST['productpopup'] : "";
    $service = isset($_POST['servicepopup']) ? $_POST['servicepopup'] : "";
    $delivery_terms = isset($_POST['popupdelivery_terms']) ? $_POST['popupdelivery_terms'] : "";
    $incoterm = isset($_POST['incoterm_popup']) ? $_POST['incoterm_popup'] : "";
    $shipment_id = isset($_POST['popupdelivery_note']) ? $_POST['popupdelivery_note'] : "";
    $porder = isset($_POST['purchaseorder_popup']) ? $_POST['purchaseorder_popup'] : "";
    $notify_party = isset($_POST['popupnotifyparty']) ? $_POST['popupnotifyparty'] : "";
    $popuppickup = isset($_POST['popuppickup']) ? $_POST['popuppickup'] : "";
    $popupdelivery = isset($_POST['popupdelivery']) ? $_POST['popupdelivery'] : "";
    $customer_phone = isset($_POST['popupshipper_phone']) ? $_POST['popupshipper_phone'] : "";
    $customer_email = isset($_POST['popupshipper_email']) ? $_POST['popupshipper_email'] : "";
    $popupcustomer_id = isset($_POST['popupcustomer_id']) ? $_POST['popupcustomer_id'] : "";
    $driverdelivery_popup = isset($_POST['driverdelivery_popup']) ? $_POST['driverdelivery_popup'] : "";
    $driverpickup_popup = isset($_POST['driverpickup_popup']) ? $_POST['driverpickup_popup'] : "";

    $earlyquickbook_delivery = $latequickbook_delivery = "";
    if($popupdelivery != ""){
      $earlyquickbook_delivery = date('Y-m-d H:i:s',strtotime($popupdelivery));
      $latequickbook_delivery = date('Y-m-d H:i:s',strtotime('+1 hour',strtotime($earlyquickbook_delivery)));
  }
  $earlyquickbook_pickup = $latequickbook_pickup = "";
  if($popuppickup != ""){
      $earlyquickbook_pickup = date('Y-m-d H:i:s',strtotime($popuppickup));
      $latequickbook_pickup = date('Y-m-d H:i:s',strtotime('+1 hour',strtotime($earlyquickbook_pickup)));
  }
  $popuporder_type = isset($_POST['popuporder_type']) ? $_POST['popuporder_type'] : "";
  $popupmodeof_trasnport = isset($_POST['popupmodeof_trasnport']) ? $_POST['popupmodeof_trasnport'] : "LTL";

  $reference_ids = isset($_POST['quickbookreference_ids']) ? $_POST['quickbookreference_ids'] : "0";
  $quickbook_order_reference_ids = isset($_POST['quickbook_order_reference_ids']) ? $_POST['quickbook_order_reference_ids'] : "0";
  $order_cargo_id = isset($_POST['quickbookorder_cargo_id']) ? $_POST['quickbookorder_cargo_id'] : "";
  $inv_parties = array($_POST['quickbookshipper_id'],$_POST['quickboookconsignee_id']);    
  $pickup = isset($_POST['quickbookpickup_id']) ? $_POST['quickbookpickup_id'] : "0";
  if($pickup == ""){
    $pickup = 0;
  }
  $delivery = $_POST['quickboookconsignee_id']; 
  $drop_id = $pickup_custid = 0;
  $pickup_name = $pickup_country = $pickup_street = $pickup_state = $drop_state = $pickup_pincode = $pickup_city =$drop_name = $drop_country = $drop_street = $drop_pincode = $drop_city = "";
  $getdrop_custid= $this->db->query("SELECT name,country,state,street,customeridentifier,pincode,location_id as city FROM tbl_party_master WHERE id='".$delivery."'");
  if($getdrop_custid->num_rows() >0){
      $drop_id = $getdrop_custid->row()->customeridentifier; 
      $drop_name = $getdrop_custid->row()->name;
      $drop_country = $getdrop_custid->row()->country;
      $drop_state = $getdrop_custid->row()->state;
      $drop_street = $getdrop_custid->row()->street;
      $drop_pincode = $getdrop_custid->row()->pincode;
      $drop_city = $getdrop_custid->row()->city;
  }
  $getshippercustid= $this->db->query("SELECT name,country,state,street,customeridentifier,pincode,location_id as city FROM tbl_party_master WHERE id='".$_POST['quickbookshipper_id']."'");
  if($getshippercustid->num_rows() >0){
    $pickup_custid = $getshippercustid->row()->customeridentifier;
    $pickup_name = $getshippercustid->row()->name;
    $pickup_country = $getshippercustid->row()->country;
    $pickup_state = $getshippercustid->row()->state;
    $pickup_street = $getshippercustid->row()->street;
    $pickup_pincode = $getshippercustid->row()->pincode;
    $pickup_city = $getshippercustid->row()->city;
}
$tname = "Full Truck Load";
$tid ="FTL";
$enddate = date('Y-m-d H:i:s',strtotime("+1 day"));
$tid = 1; $tname = "";
$gettrasnportmode = $this->db->query("SELECT id,name FROM tb_transportmode WHERE code LIKE '".$popupmodeof_trasnport."'");
if($gettrasnportmode->num_rows() >0){
  $tid = $gettrasnportmode->row()->id;
  $tname= $gettrasnportmode->row()->name;
}
if($shipment_id == ""){
    $shipment_id = "KN".time();
}
$ship_row_id = 0;
$ship_arr = array('shipid'=>$shipment_id,'txnid'=>$shipment_id,'trucktype'=>$tname,'pickupcnt'=>'1','dropcnt'=>'1','insertusr'=>$pickup_custid,'carrier'=>'0','insertuserdate'=>$cdate,'enddate'=>$enddate,'insdate'=>$cdate,'upddate'=>$cdate,'reason'=>'SHIPMENT','purpose'=>'SEND INTEGRATION','ship_object'=>'SHIPMENT','logdate'=>$cdate,'transport_mode'=>$popupmodeof_trasnport,'domainname'=>$branch_code,'company_code'=>$company_code,'branch_code'=>$branch_code,'product'=>$product,'freight_term'=>'60','freight_termname'=>'Free of Charge','incoterm'=>$incoterm,'modeoftransport'=>$tid,'unitspec'=>1);
$chk_shipid= $this->db->query("SELECT id FROM tb_shipments WHERE shipid LIKE '".$shipment_id."'");

if($chk_shipid->num_rows() >0){
    $ship_row_id = $chk_shipid->row()->id;
    $this->db->where(array('id'=>$ship_row_id))->update("tb_shipments",$ship_arr);
}else{
    
 $ship_arr['createdon'] = $cdate;
 $ship_ins = $this->db->insert("tb_shipments",$ship_arr);
 $ship_row_id = $this->db->insert_id();
}
$add1 = implode(",",[$pickup_street,$pickup_city,$pickup_country,$pickup_pincode]);
$add2 = implode(",",[$drop_street,$drop_city,$drop_country,$drop_pincode]);
$data = getlatlngsbyplace($add1);
$lat1 = @$data[0];
$lng1 = @$data[1];
$data = [];
$data = getlatlngsbyplace($add2);
$lat2 = @$data[0];
$lng2 = @$data[1];
$customer_id = $pickup;
if($customer_id == "0" || $customer_id == ""){
    if($popupcustomer_id != "" && $popupcustomer_id != "0"){
        $getcustomerid = $this->db->select("id")->get_where("tb_customers",array('code'=>$popupcustomer_id,'user_id'=>$user_id,'status'=>'1'));
        if($getcustomerid->num_rows() >0){
            $customer_id = $getcustomerid->row()->id;
        }
    }
}
if($this->session->userdata('company_code') == 'NZKN'){
    if($product == ""){
      $product = "KN AsiaLink";
  }
  if($popupmodeof_trasnport == ""){
      $popupmodeof_trasnport = "LTL";
  }
  if($service == ""){
      $service = "19";
  }
}
$curtz = $this->session->userdata("usr_tzone")['timezone'];
$logdate = date('Y-m-d H:i:s');
$getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
$logdate = $getactual['datetime'];
$getpickup = getdatetimebytimezone(DFLT_TZ,$earlyquickbook_pickup,$curtz);
$earlyquickbook_pickup = $getpickup['datetime'];
$getlpickup = getdatetimebytimezone(DFLT_TZ,$latequickbook_pickup,$curtz);
$latequickbook_pickup = $getlpickup['datetime'];
$getdelivery = getdatetimebytimezone(DFLT_TZ,$earlyquickbook_delivery,$curtz);
$earlyquickbook_delivery = $getdelivery['datetime'];
$getldelivery = getdatetimebytimezone(DFLT_TZ,$latequickbook_delivery,$curtz);
$latequickbook_delivery = $getldelivery['datetime'];
$orderinfo = array('goods_value'=>'0.00','shipment_id'=>$ship_row_id,'customer_id'=>$customer_id,'product'=>$product,'company_code'=>$company_code,'branch_code'=>$branch_code,'createdon'=>$cdate,'pickup_datetime'=>$earlyquickbook_pickup,'delivery_datetime'=>$earlyquickbook_delivery,'pickup_endtime'=>$latequickbook_pickup,'drop_endtime'=>$latequickbook_delivery,'drop_custid'=>$drop_id,'drop_partyid'=>$drop_id,'user_id'=>$user_id,'pickup_custid'=>$pickup_custid,'pickup_partyid'=>$pickup_custid,'pickup_country'=>$pickup_country,'pickup_city'=>$pickup_city,'pickup_pincode'=>$pickup_pincode,'pickup_company'=>$pickup_name,'pickup_address1'=>$pickup_street,'pickup_address2'=>$pickup_state,'delivery_country'=>$drop_country,'delivery_city'=>$drop_city,'delivery_pincode'=>$drop_pincode,'delivery_company'=>$drop_name,'delivery_address1'=>$drop_street,'delivery_address2'=>$drop_state,'transport_mode'=>$popupmodeof_trasnport,'plat'=>$lat1,'plng'=>$lng1,'dlat'=>$lat2,'dlng'=>$lng2,'is_created'=>'1','modeoftransport'=>$tid,'created_source'=>'3','createdon'=>$logdate);
$ins_order = $this->db->insert("tb_orders",$orderinfo);
$order_id = $this->db->insert_id();
$get_country = $this->db->select('country_code,company_code')->get_where("tb_users",array('id'=>$user_id));
$country_code  = $get_country->row()->country_code;
$company_code  = $get_country->row()->company_code;
$genord = array("user_id"=>$user_id,"order_id"=>$order_id,"country_code"=>$country_code,"company_code"=>$company_code);
$booking_id = generatebookingid($genord);
$upd = $this->db->where(array('id'=>$order_id))->update("tb_orders",array('order_id'=>$booking_id));  
$details = array('shipper_id'=>$_POST['quickbookshipper_id'],'service'=>$service,'delivery_term'=>$delivery_terms,'incoterm'=>$incoterm,'purchase_order'=>$porder,'notify_party'=>$notify_party,'department_code'=>$department_code,'createdon'=>$cdate,'order_row_id'=>$order_id,'order_id'=>$booking_id,'order_type'=>$popuporder_type);
$this->db->insert('tb_order_details',$details);

$order_shipper_id  = isset($_POST['quickbookshipper_id']) ? $_POST['quickbookshipper_id'] : "";
$pickup_address = $pickup_street.",".$pickup_city.",".$pickup_state;
$shipper_address    = array('order_id' => $order_id, 'party_master_id' => $order_shipper_id, 'location_id' => $pickup_city, 'street' => $pickup_street, 'state' => $pickup_state, 'address' => $pickup_address, 'pincode' => $pickup_pincode, 'country' => $pickup_country, 'user_id' => $user_id);
$chk_shipperaddress = $this->db->select("id")->get_where("tbl_orderparty_address", array('order_id' => $order_id, 'party_master_id' => $order_shipper_id,'status'=>'1'));
if ($chk_shipperaddress->num_rows() > 0) {
  $shipperadd_id = $chk_shipperaddress->row()->id;
  $upd_add       = $this->db->where(array('id' => $shipperadd_id))->update("tbl_orderparty_address", $shipper_address);
} else {
  $shipper_address['createdon'] = $cdate;
  $this->db->insert("tbl_orderparty_address", $shipper_address);
  $shipperadd_id = $this->db->insert_id();
}
$drop_address = $drop_street.",".$drop_city.",".$drop_state;
$delivery_address    = array('order_id' => $order_id, 'party_master_id' => $delivery, 'location_id' => $drop_city, 'street' => $drop_street, 'state' => $drop_state, 'address' => $drop_address, 'pincode' => $drop_pincode, 'country' => $drop_country, 'user_id' => $user_id);
$chk_deliveryaddress = $this->db->select("id")->get_where("tbl_orderparty_address", array('order_id' => $order_id, 'party_master_id' => $delivery,'status'=>'1'));
if ($chk_deliveryaddress->num_rows() > 0) {
  $dropadd_id = $chk_deliveryaddress->row()->id;
  $upd_add    = $this->db->where(array('id' => $dropadd_id))->update("tbl_orderparty_address", $delivery_address);
} else {
  $delivery_address['createdon'] = $cdate;
  $this->db->insert("tbl_orderparty_address", $delivery_address);
  $dropadd_id = $this->db->insert_id();
}
$shipment_name = "BOXES";
if($order_cargo_id != ""){
  $cargo_ids = array();
  $cargo_ids = explode(',', $order_cargo_id);

  for($i=0;$i<count($cargo_ids);$i++){
     $length = 
     $width =  $height = $weight = $volume = 0;
     $quantity = 1;
     if($cargo_ids[$i] != ""){
        $getcargo_details = $this->db->query("SELECT cargo_type,goods_description,length,width,height,weight,volumetric_weight,volume,quantity FROM tb_cargo_details WHERE id='".$cargo_ids[$i]."'");
        $length = $width = $height = $weight = $volume = $quantity = $cargo_type = $description= "";
        if($getcargo_details->num_rows() >0){
          $length = $getcargo_details->row()->length;
          $width = $getcargo_details->row()->width;
          $height = $getcargo_details->row()->height;
          $weight = $getcargo_details->row()->weight;
          $volumetric_weight = $getcargo_details->row()->volumetric_weight;
          $volume = $getcargo_details->row()->volume;
          $quantity = $getcargo_details->row()->quantity;
          $shipment_name = $cargo_type = $getcargo_details->row()->cargo_type;
          $description = $getcargo_details->row()->goods_description;
      }
      $gethandling_unit = $this->db->query("SELECT id FROM tbl_shipunit_types WHERE unit_name LIKE '".$cargo_type."'");
      $handling_unit = "";
      if($gethandling_unit->num_rows() >0){
        $handling_unit = $gethandling_unit->row()->id;
    }else{
        $handlingunit_ar = array('unit_name'=>$cargo_type,'description'=>$cargo_type,'user_id'=>$user_id,'created_at'=>$cdate,'status'=>'1');
        $this->db->insert("tbl_shipunit_types",$handlingunit_ar);
        $handling_unit = $this->db->insert_id();
    }
    $cargo = array('order_id'=>$order_id,'cargo_id'=>$cargo_ids[$i],'status'=>'1','length'=>$length,'width'=>$width,'height'=>$height,'weight'=>$weight,'volume'=>$volume,'quantity'=>$quantity,'cargo_content'=>$description,'quantity_type'=>$cargo_type,'handling_unit'=>$handling_unit,'volumetric_weight'=>$volumetric_weight,'volweight_uom'=>'kg');
    $this->db->insert("tb_order_cargodetails",$cargo);
}
}
}
$total_weight = $total_volume = $total_quantity = 0;
$gettotal = $this->db->query("SELECT sum(weight) as total_weight,sum(volume) as total_volume,sum(quantity) as total_quantity FROM tb_order_cargodetails WHERE order_id='".$order_id."'");
if($gettotal->num_rows() >0){
    $total_volume = $gettotal->row()->total_volume;
    $total_weight = $gettotal->row()->total_weight;
    $total_quantity = $gettotal->row()->total_quantity;
}
$cargo_forship = array();
$getcargos  = $this->db->query("SELECT quantity_type FROM tb_order_cargodetails WHERE order_id ='".$order_id."'");
if($getcargos->num_rows() >0){
    foreach($getcargos->result() as $res){
      $cargo_forship[] = $res->quantity_type;
  }
}
$unitspec = "";
if(!empty($cargo_forship)){
    $unitspec = implode(',', $cargo_forship);
}
$updship = $this->db->where(array('id'=>$ship_row_id))->update("tb_shipments",array('unitspec'=>$unitspec,'txncode'=>$booking_id));
$upd_order = $this->db->where(array('id'=>$order_id))->update("tb_orders",array('volume'=>$total_volume,'weight'=>$total_weight,'quantity'=>$total_quantity));
$ids = array();
if($reference_ids != "0"){
  $refids = explode(',', $reference_ids);
}
$order_ref_ids = array();
if($quickbook_order_reference_ids != "0"){
  $order_ref_ids = explode(',', $quickbook_order_reference_ids);
}
if(!empty($order_ref_ids)){
  for($i=0;$i<count($order_ref_ids);$i++){
      if($order_ref_ids[$i] != ""){
          $upd = $this->db->where(array('id'=>$order_ref_ids[$i]))->update("tb_order_references",array('order_id'=>$order_id));
      }
  }
}
if($shipment_id != ""){
    $ins_ref = array('order_id'=>$order_id,'reference_id'=>'DQ','ref_value'=>$shipment_id,'createdon'=>$cdate);
    $ins = $this->db->insert('tb_order_references',$ins_ref);
    
}
if($driverdelivery_popup != ""){
    $ins_ref = array('order_id'=>$order_id,'reference_id'=>'ORD_DLVINST','ref_value'=>$driverdelivery_popup,'createdon'=>$cdate);
    $ins = $this->db->insert('tb_order_references',$ins_ref);
    
}
if($driverpickup_popup != ""){
    $ins_ref = array('order_id'=>$order_id,'reference_id'=>'ORD_PIKINST','ref_value'=>$driverpickup_popup,'createdon'=>$cdate);
    $ins = $this->db->insert('tb_order_references',$ins_ref);
    
}
if($porder != ""){
    $ins_ref = array('order_id'=>$order_id,'reference_id'=>'PO','ref_value'=>$porder,'createdon'=>$cdate);
    $ins = $this->db->insert('tb_order_references',$ins_ref);
    
}
    /*if(!empty($inv_parties)){
      foreach($inv_parties as $res){
          if($res != ""){
                $getpartytype = $this->db->query("SELECT party_type_id FROM tbl_party_master WHERE id='".$res."'");
                $party_type = 1;
                if($getpartytype->num_rows() >0){
                    $party_type = $getpartytype->row()->party_type_id;
                }
                $parties = array('order_id'=>$order_id,'party_id'=>$res,'status'=>'1','createdon'=>$cdate,'party_type'=>$party_type);
                $this->db->insert("tb_order_parties",$parties);
          }
      }
  }*/
  if($order_shipper_id != "" || $order_shipper_id != 0){

    $party_type = 0;
    $chk = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Shipper','company_code'=>$company_code,'branch_code'=>$branch_code,'user_id'=>$user_id));
    if($chk->num_rows() >0){
        $party_type = $chk->row()->id;
    }else{
       $chk1 = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Shipper','company_code'=>$company_code,'user_id'=>$user_id));
       if($chk1->num_rows()>0)
           $party_type = $chk1->row()->id;
   }

   $party     = array('order_id' => $order_id, 'party_id' => $order_shipper_id, 'status' => '1', 'createdon' => $cdate, 'status' => '1', 'party_type' => $party_type, 'order_number' => $booking_id);
   $ins_party = $this->db->insert("tb_order_parties", $party);
   
}
if($delivery != "" || $delivery != 0){
    $party_type = 0;
    $chk = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Consignee','company_code'=>$company_code,'branch_code'=>$branch_code,'user_id'=>$user_id));
    if($chk->num_rows() >0){
        $party_type = $chk->row()->id;
    }else{
        $chk1 = $this->db->select("id")->get_where("tbl_party_types",array('name'=>'Consignee','company_code'=>$company_code,'user_id'=>$user_id));
        if($chk1->num_rows()>0){
           $party_type = $chk1->row()->id;
       }
   }
   $party = array('order_id' => $order_id, 'party_id' => $delivery, 'status' => '1', 'createdon' => $cdate, 'status' => '1', 'party_type' => $party_type, 'order_number' => $booking_id);
   $ins_party = $this->db->insert("tb_order_parties", $party);
}
$pref_arr = array('pickup'=>$pickup_country,'drop'=>$drop_country,'customer_id'=>$popupcustomer_id,'service'=>$service,'product'=>$product,'user_id'=>$user_id,'company_code'=>$company_code,'order_id'=>$order_id);
/*if($company_code == 'PLKN'){*/
    $this->ratemanagement->addrecodfororderinsertion($pref_arr);
/*}*/

/*if($this->session->userdata('usr_tzone')['country'] == "RU"){*/

    if($order_id != "" && $customer_id != ""){
        $pickupinfo['country'] = trim($pickup_country);
        $pickupinfo['state'] = trim($pickup_state);
        $pickupinfo['city'] = trim($pickup_city);
        $pickupinfo['region'] = trim($pickup_street);
        $pickupinfo['zipcode'] = trim($pickup_pincode);
        $pickupinfo['stoptype'] = "P";
        $dropinfo['country'] = trim($drop_country);
        $dropinfo['state'] = trim($drop_state);
        $dropinfo['city'] = trim($drop_city);
        $dropinfo['region'] = trim($drop_street);
        $dropinfo['zipcode'] = trim($drop_pincode);
        $dropinfo['stoptype'] = "D";
        $pickupgeocode = checkgeocode($pickupinfo);
        $dropgeocode = checkgeocode($pickupinfo);
        if(!empty($pickupgeocode) && !empty($dropgeocode)){
            $pickupgeocode['stoptype'] = "P";
            $dropgeocode['stoptype'] = "D";
            $pickuproute = getcust_routeautomate($customer_id,$pickupgeocode);
            $droproute = getcust_routeautomate($customer_id,$dropgeocode);
            if(!empty($pickuproute) && !empty($droproute)){
                $orderinfo['id']=$order_id;
                $orderinfo['order_id']=$booking_id;
                $orderinfo['shipment_name']=$shipment_name;
                $orderinfo['customer_phone']=$customer_phone;
                $orderinfo['customer_email']=$customer_email;
                $orderinfo['volume']=$total_volume;
                $orderinfo['weight']=$total_weight;
                $orderinfo['quantity']=$total_quantity;
                $orderinfo['quantity']=$total_quantity;
                $shipment = createshipmentbyorder($pickuproute,$orderinfo);
            }else{
                $pickuproute1 = getcust_routeautomate($customer_id,$pickupinfo);
                $droproute1 = getcust_routeautomate($customer_id,$dropinfo);
                if(!empty($pickuproute1) && !empty($droproute1)){
                    $orderinfo['id']=$order_id;
                    $orderinfo['order_id']=$booking_id;
                    $orderinfo['shipment_name']=$shipment_name;
                    $orderinfo['customer_phone']=$customer_phone;
                    $orderinfo['customer_email']=$customer_email;
                    $orderinfo['volume']=$total_volume;
                    $orderinfo['weight']=$total_weight;
                    $orderinfo['quantity']=$total_quantity;
                    $orderinfo['quantity']=$total_quantity;
                    $shipment = createshipmentbyorder($pickuproute1,$orderinfo);
                }
            }
        }else{
            $pickuproute1 = getcust_routeautomate($customer_id,$pickupinfo);
            $droproute1 = getcust_routeautomate($customer_id,$dropinfo);
            if(!empty($pickuproute1) && !empty($droproute1)){
                $orderinfo['id']=$order_id;
                $orderinfo['order_id']=$booking_id;
                $orderinfo['shipment_name']=$shipment_name;
                $orderinfo['customer_phone']=$customer_phone;
                $orderinfo['customer_email']=$customer_email;
                $orderinfo['volume']=$total_volume;
                $orderinfo['weight']=$total_weight;
                $orderinfo['quantity']=$total_quantity;
                $orderinfo['quantity']=$total_quantity;
                $shipment = createshipmentbyorder($pickuproute1,$orderinfo);
            }
        }
    }
/*}*/
$this->ordernotify('booking_create',$order_id);
redirect("orders");

}

public function addreferencedetails()
{
    $id           = $this->input->post('reference_id');
    $name         = $this->input->post('reference_name');
    $value        = $this->input->post('reference_value');
    if($id == "ETA"){
     $chkrtime = getdatetimebytimezone(DFLT_TZ,$value,$this->session->userdata('usr_tzone')['timezone']);
     $value = $chkrtime['datetime'];
 }
 $row_id       = isset($_POST['ref_row_id']) ? $_POST['ref_row_id'] : "";
 $order_id     = isset($_POST['order_id']) ? $_POST['order_id'] : "0";
 $order_ref_id = isset($_POST['order_ref_id']) ? $_POST['order_ref_id'] : "0";
 $cdate        = date('Y-m-d H:i:s');
 $ins_id       = $order_ins_id       = 0;
 if ($row_id == "") {
    $ins_arr = array('name' => $id, 'description' => $name, 'createdon' => $cdate);
    $chk_ar  = $this->db->select('id')->get_where('tb_reference_master', array('name' => $id, 'status' => '1'));
    if ($chk_ar->num_rows() == 0) {
        $ins    = $this->db->insert("tb_reference_master", $ins_arr);
        $ins_id = $this->db->insert_id();
    } else {
        $ins_id = $chk_ar->row()->id;
    }

    if ($ins_id != "") {
        $ins_order    = array('reference_id' => $id, 'ref_value' => $value, 'createdon' => $cdate, 'order_id' => $order_id);
        $ins          = $this->db->insert("tb_order_references", $ins_order);
        $order_ins_id = $this->db->insert_id();
    }
} else {
    $upd_ar = array('name' => $id, 'description' => $name);
    if ($id == 'PO') {
        $po_val = $value;
        $upd    = $this->db->where(array('order_row_id' => $order_id))->update("tb_order_details", array('purchase_order' => $po_val));
    }
    if ($id == 'DQ') {
        $dq_val      = $value;
        $chkshipment = $this->db->query("SELECT s.id FROM tb_shipments s,tb_orders o WHERE o.id='" . $order_id . "' AND o.shipment_id=s.id");
        if ($chkshipment->num_rows() > 0) {
            $shipment_id = $chkshipment->row()->id;
            $upd         = $this->db->where(array('id' => $shipment_id))->update("tb_shipments", array('shipid' => $dq_val, 'txnid' => $dq_val));
        }
    }
    $this->db->where(array('id' => $row_id))->update("tb_reference_master", $upd_ar);
    if ($order_id != "") {
        $ins_ar = array('order_id' => $order_id, 'reference_id' => $id, 'ref_value' => $value,'createdon'=>$cdate);
        $chk = $this->db->select('id')->get_where('tb_order_references', array('order_id' => $order_id, 'reference_id' => $id, 'status' => '1'));
        if ($chk->num_rows() == 0) {
            $ins_ar['createdon'] = $cdate;
            $order_ins           = $this->db->insert("tb_order_references", $ins_ar);
            $order_ins_id        = $this->db->insert_id();
        } else {
            $this->db->where(array('id' => $chk->row()->id))->update("tb_order_references", $ins_ar);
        }
    } else {
        $chk    = $this->db->select('id')->get_where('tb_order_references', array('reference_id' => $id, 'status' => '1'));
        $ins_ar = array('order_id' => '0', 'reference_id' => $id, 'ref_value' => $value,'createdon'=>$cdate);
        if ($chk->num_rows() == 0) {
            $order_ins    = $this->db->insert("tb_order_references", $ins_ar);
            $order_ins_id = $this->db->insert_id();
        } else {
            $order_ins_id = $chk->row()->id;
            $upd          = $this->db->where(array('id' => $order_ins_id))->update("tb_order_references", $ins_ar);
        }
    }
    $ins_id = $row_id;

}
$arr = array('ins_id' => $ins_id, 'order_ins_id' => $order_ins_id);
echo json_encode($arr);
}

public function addpopupreferencedetails()
{
    $id           = $this->input->post('reference_id');
    $name         = $this->input->post('reference_name');
    $value        = $this->input->post('reference_value');
    $row_id       = isset($_POST['ref_row_id']) ? $_POST['ref_row_id'] : "";
    $order_id     = isset($_POST['order_id']) ? $_POST['order_id'] : "0";
    $order_ref_id = isset($_POST['order_ref_id']) ? $_POST['order_ref_id'] : "0";
    $cdate        = date('Y-m-d H:i:s');
    $ins_id       = $order_ins_id       = 0;
    if ($row_id == "") {
        $ins_arr = array('name' => $id, 'description' => $name, 'createdon' => $cdate);
        $chk_ar  = $this->db->select('id')->get_where('tb_reference_master', array('name' => $id, 'description' => $name, 'status' => '1'));
        if ($chk_ar->num_rows() == 0) {
            $ins    = $this->db->insert("tb_reference_master", $ins_arr);
            $ins_id = $this->db->insert_id();
        } else {
            $ins_id = $chk_ar->row()->id;
        }
        if ($ins_id != "") {
            $ins_order    = array('reference_id' => $id, 'ref_value' => $value, 'createdon' => $cdate, 'order_id' => $order_id);
            $ins          = $this->db->insert("tb_order_references", $ins_order);
            $order_ins_id = $this->db->insert_id();
        }
    } else {
        $upd_ar = array('name' => $id, 'description' => $name);

        $this->db->where(array('id' => $row_id))->update("tb_reference_master", $upd_ar);
        if ($order_id != "") {
            $ins_ar = array('order_id' => $order_id, 'reference_id' => $id, 'ref_value' => $value,'createdon'=>$cdate);
            $chk    = $this->db->select('id')->get_where('tb_order_references', array('order_id' => $order_id, 'reference_id' => $id, 'status' => '1', 'id' => $order_ref_id));
            if ($chk->num_rows() == 0) {
                $ins_ar['createdon'] = $cdate;
                $order_ins           = $this->db->insert("tb_order_references", $ins_ar);
                $order_ins_id        = $this->db->insert_id();
            } else {
                $this->db->where(array('id' => $chk->row()->id))->update("tb_order_references", $ins_ar);
            }
        } else {
            $chk    = $this->db->select('id')->get_where('tb_order_references', array('reference_id' => $id, 'status' => '1', 'id' => $order_ref_id));
            $ins_ar = array('order_id' => '0', 'reference_id' => $id, 'ref_value' => $value,'createdon'=>$cdate);
            if ($chk->num_rows() == 0) {
                $order_ins    = $this->db->insert("tb_order_references", $ins_ar);
                $order_ins_id = $this->db->insert_id();
            } else {
                $order_ins_id = $chk->row()->id;
                $upd          = $this->db->where(array('id' => $order_ins_id))->update("tb_order_references", $ins_ar);
            }
        }
        $ins_id = $row_id;

    }
    $arr = array('ins_id' => $ins_id, 'order_ins_id' => $order_ins_id);
    echo json_encode($arr);
}

public function deleteorderreferencedetails()
{
    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : "0";
    $ref_id   = isset($_POST['ref_id']) ? $_POST['ref_id'] : "0";
    if ($order_id != '0' && $ref_id != '0') {
        $getref_name = $this->db->select('name')->get_where("tb_reference_master", array('id' => $ref_id));
        if ($getref_name->num_rows() > 0) {
            $ref_id = $getref_name->row()->name;
        }
        $chkdetails = $this->db->select('id')->get_where("tb_order_references", array('order_id' => $order_id, 'reference_id' => $ref_id));
        if ($chkdetails->num_rows() > 0) {
            $upd = $this->db->where(array('id' => $chkdetails->row()->id))->update("tb_order_references", array('status' => '0'));
            if ($upd) {
                echo "1";
            } else {
                echo "0";
            }
        } else {
            echo "0";
        }
    }
}

public function getotherreferencedetails()
{

    $order_id      = isset($_POST['order_id']) ? $_POST['order_id'] : "";
    $type          = isset($_POST['type']) ? $_POST['type'] : "";
    $ref_ids       = isset($_POST['ref_ids']) ? $_POST['ref_ids'] : array();
    $getrefer_rows = 0;
    $refer         = array();
    $whr           = "";
    $action        = "";
    if ($order_id != "") {
        $getrefer      = $this->db->query("SELECT r.*,o.id as order_ref_id,o.reference_id,o.ref_value as order_value FROM tb_reference_master r,tb_order_references o WHERE o.order_id='" . $order_id . "' AND o.reference_id =r.name  AND r.status='1' AND o.status='1' GROUP BY o.id ORDER BY r.createdon DESC");
        $getrefer_rows = $getrefer->num_rows();

    } else {
        $whr = "";
        if ($ref_ids != "") {
            $refids = implode(',', $ref_ids);
            if (!empty($refids)) {
                $whr .= " AND r.id IN (" . $refids . ") ";
            }
        }
        $getrefer      = $this->db->query("SELECT r.*,o.id as order_ref_id,o.ref_value as order_value FROM tb_reference_master r,tb_order_references o WHERE r.name=o.reference_id  AND  o.status='1' AND r.status ='1' " . $whr . " ORDER BY r.createdon DESC");
        $getrefer_rows = $getrefer->num_rows();
    }
    if ($getrefer_rows > 0) {
        foreach ($getrefer->result() as $res) {
            $ref_name  = '"' . $res->name . '"';
            $ref_desc  = '"' . $res->description . '"';
            $ref_value = '"' . $res->order_value . '"';
            if ($type == 'popup') {
                $action = "<button id=" . $res->id . " class='btn btn-primary btn-xs' onclick='editpopupreferencedetails(" . $res->id . "," . $res->order_ref_id . ",event)'><small><i class='glyphicon glyphicon-pencil'></i></small></button> <button id=" . $res->id . " class='btn btn-danger btn-xs' onclick='deletepopupreferencedetails(" . $res->id . ",event)'><small><i class='glyphicon glyphicon-trash'></i></small></button>";
            } else {
                if ($order_id != "") {

                    $action = "<ul class='nav nav-tabs'><li class='dropdown tablebtnrleft'> <a class='dropdown-toggle' data-toggle='dropdown' href='#''><span class='icon  tru-icon-action-setting'></span></a><ul class='dropdown-menu' role='menu'>" . "<li><a id='bEdit' type='button' class='btn btn-sm btn-default'  onclick='editreferencedetails(this," . $res->id . "," . $ref_name . "," . $ref_desc . "," . $ref_value . ");'><span class='glyphicon glyphicon-pencil' ></span>Edit</a></li><li><a id='bElim' type='button' class='btn btn-sm btn-default' onclick='deletereferencedetailsbyorder(" . $res->id . ")'><span class='glyphicon glyphicon-trash' > </span>Remove</a></li><li><a id='bAcep' type='button' class='btn btn-sm btn-default' style='display:none;' onclick='rowAcep(this);'><span class='glyphicon glyphicon-ok' > </span>Update</a></li><li><a id='bAdd' type='button' class='btn btn-sm btn-default' onclick='rowRefAdd(this);'><span class='glyphicon glyphicon-plus' > </span>Add Reference</a></li>";
                } else {
                    $action = "<button id=" . $res->id . " class='btn btn-primary btn-xs editparties' onclick='editreferencedetails(" . $res->id . ",event)'><small><i class='glyphicon glyphicon-pencil'></i></small></button> <button id=" . $res->id . " class='btn btn-danger btn-xs deleteround' onclick='deletereferencedetails(" . $res->id . ",event)'><small><i class='glyphicon glyphicon-trash'></i></small></button>";
                }

            }
            $res_val= $res->order_value;
            if($res_val != ""){
                if (DateTime::createFromFormat('Y-m-d H:i:s', $res_val) !== FALSE) {
                    $chkrtime = getdatetimebytimezone($this->session->userdata('usr_tzone')['timezone'],$res_val,DFLT_TZ);
                    $res_val = $chkrtime['datetime'];
                }else{
                    $res_val = $res_val;
                }
            }
            $refer[] = array('id' => $res->name, 'name' => $res->description, 'value' => $res_val, 'action' => $action);
        }
    }
    echo json_encode($refer);
}
public function getotherpopupreferencedetails()
{
    $order_id          = isset($_POST['order_id']) ? $_POST['order_id'] : "";
    $type              = isset($_POST['type']) ? $_POST['type'] : "";
    $ref_ids           = isset($_POST['ref_ids']) ? $_POST['ref_ids'] : "";
    $popupref_order_id = isset($_POST['popupref_order_id']) ? $_POST['popupref_order_id'] : "";
    $getrefer_rows     = 0;
    $refer             = $popupref_order_ids             = array();
    $whr               = "";
    $action            = "";
    if ($order_id != "") {
        $getrefer      = $this->db->query("SELECT r.*,o.reference_id,o.ref_value as order_value FROM tb_reference_master r,tb_order_references o WHERE o.order_id='" . $order_id . "' AND o.reference_id =r.name AND r.status='1' AND o.status='1' ORDER BY r.createdon DESC");
        $getrefer_rows = $getrefer->num_rows();

    } else {
        if ($ref_ids != "") {
            $refids = implode(',', $ref_ids);
            if ($popupref_order_id != "") {
                $popupref_order_ids = implode(',', $popupref_order_id);
                if (!empty($popupref_order_ids)) {
                    $whr = " AND o.id IN (" . $popupref_order_ids . ") ";
                }
            }

            if (!empty($refids)) {
                $whr .= " AND r.id IN (" . $refids . ") ";
                $getrefer      = $this->db->query("SELECT r.*,o.id as order_ref_id,o.ref_value as order_value FROM tb_reference_master r,tb_order_references o WHERE r.name=o.reference_id AND o.status='1' AND r.status ='1' " . $whr . " ORDER BY r.createdon DESC");
                $getrefer_rows = $getrefer->num_rows();
            }
        }
    }
    if ($getrefer_rows > 0) {
        foreach ($getrefer->result() as $res) {
            if ($type == 'popup') {
                $action = "<button id=" . $res->id . " class='btn btn-primary btn-xs' onclick='editpopupreferencedetails(" . $res->id . "," . $res->order_ref_id . ",event)'><small><i class='glyphicon glyphicon-pencil'></i></small></button> <button id=" . $res->id . " class='btn btn-danger btn-xs' onclick='deletepopupreferencedetails(" . $res->id . ",event)'><small><i class='glyphicon glyphicon-trash'></i></small></button>";
            } else {
                if ($order_id != "") {
                    $action = "<button id=" . $res->id . " class='btn btn-primary btn-xs editparties' onclick='editreferencedetails(" . $res->id . ",event)'><small><i class='glyphicon glyphicon-pencil'></i></small></button> <button id=" . $res->id . " class='btn btn-danger btn-xs deleteround' onclick='deletereferencedetailsbyorder(" . $res->id . ",event)'><small><i class='glyphicon glyphicon-trash'></i></small></button>";
                } else {
                    $action = "<button id=" . $res->id . " class='btn btn-primary btn-xs editparties' onclick='editreferencedetails(" . $res->id . ",event)'><small><i class='glyphicon glyphicon-pencil'></i></small></button> <button id=" . $res->id . " class='btn btn-danger btn-xs deleteround' onclick='deletereferencedetails(" . $res->id . ",event)'><small><i class='glyphicon glyphicon-trash'></i></small></button>";
                }

            }
            $refer[] = array('id' => $res->name, 'name' => $res->description, 'value' => $res->order_value, 'action' => $action);
        }
    }
    echo json_encode($refer);
}

public function editreferencedetails()
{
    $id          = $this->input->post('id');
    $refer       = array();
    $get_details = $this->db->query("SELECT r.*,o.ref_value as order_value FROM tb_reference_master r,tb_order_references o WHERE r.id='" . $id . "' AND r.name=o.reference_id");
    if ($get_details->num_rows() > 0) {
        $refer = array('id' => $get_details->row()->id, 'ref_id' => $get_details->row()->name, 'name' => $get_details->row()->description, 'value' => $get_details->row()->order_value);
    }
    echo json_encode($refer);
}
public function editpopupreferencedetails()
{
    $id           = $this->input->post('id');
    $order_ref_id = isset($_POST['order_ref_id']) ? $_POST['order_ref_id'] : "";
    $refer        = array();
    $whr          = "";
    if ($order_ref_id != "") {
        $whr .= " AND o.id='" . $order_ref_id . "'";
    }
    $get_details = $this->db->query("SELECT r.*,o.ref_value as order_value FROM tb_reference_master r,tb_order_references o WHERE r.id='" . $id . "' AND r.name=o.reference_id " . $whr);
    if ($get_details->num_rows() > 0) {
        $refer = array('order_ref_id' => $order_ref_id, 'id' => $get_details->row()->id, 'ref_id' => $get_details->row()->name, 'name' => $get_details->row()->description, 'value' => $get_details->row()->order_value);
    }
    echo json_encode($refer);
}

public function viewpartylist()
{
    
    $parties = array();
    $user_id = $this->session->userdata('user_id');
    $type    = isset($_POST['type']) ? $_POST['type'] : "0";
    $party_type    = isset($_POST['party']) ? $_POST['party'] : "";
    $customer_id    = isset($_POST['customer_id']) ? $_POST['customer_id'] : "0";
    $check   = "";
    $custid = "0";
    $whr = $usrwhr = "";
    if($customer_id == ""){
        $customer_id = 0;
    }
    $chkcompanycode = $this->session->userdata('company_code');
    if($chkcompanycode == 'RUKN'){
        $usrwhr = "";
    }else{
        $usrwhr = " AND m.user_id='".$user_id."' ";
    }
    if($chkcompanycode != 'NZKN'){
       if($this->session->userdata('cust_id') !== FALSE){
        $custid =$this->session->userdata('cust_id');
    }
    $subcusts = array();
    if($custid != 0){
        if($this->session->userdata('sub_cust') !== FALSE){
            $subcusts = $this->session->userdata('sub_cust');
            if(count($subcusts)>0){
                array_push($subcusts, $custid);
            }else{
                $subcusts = $custid;
                       // array_push($subcusts, $custid);
            }
        }else{
            $subcusts  =$custid;
                   // array_push($subcusts, $custid);
        }
    }
    $customer_code= array();
    if(!empty($subcusts)){
        $select = "code";
        $table = "tb_customers";
        $customerdetails = $this->Order->getcustomercodebyids($select,$table,$subcusts);
        if(!empty($customerdetails)){
            foreach($customerdetails as $cust){
                $customer_code[] = $cust['code'];
            }
        }
    }
    if(!empty($customer_code)){
        $whr = "AND m.customer_code IN ('" . implode("','", $customer_code) . "') ";
    } 
}
$party_type_whr = " ";
if($party_type != ""){
    $party_type_whr = " AND t.name LIKE '".$party_type."' ";
}


$contact_ids = $master_ids = array();
if($customer_id != '0'){
    $getid = $this->common->gettblrowdata(array('code'=>$customer_id),"id",'tbl_party_master',0,0);
    if(!empty($getid)){
        $customer_row_id = $getid['id'];
        $get_custparties = $this->db->query("SELECT m.id as master_id,m.name as master_name,m.email as master_email_id,m.mobile as master_mobile,m.code,m.company_code,m.branch_code,m.location_id as city,m.country,m.street,t.name as party_name FROM tbl_party_master m,tbl_party_types t WHERE  m.company_code != '' AND m.company_code IS NOT NULL AND m.company_code LIKE '".$chkcompanycode."' AND m.branch_code != '' AND m.branch_code IS NOT NULL ".$usrwhr." AND m.parent_id ='".$customer_row_id."' AND m.status=1 ANd m.code != '' AND m.code is NOT NULL AND m.code != '0' AND t.id=m.party_type_id  GROUP BY m.id ORDER BY m.id DESC");

        if($get_custparties->num_rows()>0){
            foreach ($get_custparties->result() as $res) {
             if ($type == '1') {
                $check = "<input type='radio' name='consigneelist' id='consigneelist_" . $res->master_id . "' class='consigneelist' onchange='selectparty(" . $res->master_id . ")' value='" . $res->code . "'>";
            } else {
                $check = "<input type='radio' name='consigneelist' id='consigneelist_" . $res->master_id . "' class='consigneelist' onchange='selectparty(" . $res->master_id . ")' value='" . $res->code . "'>";
            }
            $contact_ids[] = $res->master_id;
            $master_ids[] = $res->master_id;
            $parties[] = array('check' => $check, 'id' => $res->code, 'name' => $res->master_name, 'email' => $res->master_email_id, 'mobile' => $res->master_mobile, 'party_name' => $res->party_name, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'party_name' => $party_type,'city'=>$res->city,'country'=>$res->country,'street'=>$res->street); 
        }
    }
}
}

$get_parties = $this->db->query("SELECT m.id as master_id,m.name as master_name,m.email as master_email_id,m.mobile as master_mobile,m.code,m.company_code,m.branch_code,m.location_id as city,m.country,m.street,t.name as party_name FROM tbl_party_master m,tbl_party_types t WHERE m.company_code != '' AND m.company_code IS NOT NULL AND m.company_code LIKE '".$chkcompanycode."' AND m.branch_code != '' AND m.branch_code IS NOT NULL ".$whr." ".$usrwhr." AND m.parent_id ='0'  AND m.status=1 ANd m.code != '' AND m.code is NOT NULL AND m.code != '0' AND t.id=m.party_type_id ".$party_type_whr." GROUP BY m.id ORDER BY m.id DESC");
if ($get_parties->num_rows() > 0) {
    foreach ($get_parties->result() as $res) {
        if ($type == '1') {
            $check = "<input type='radio' name='consigneelist' id='consigneelist_" . $res->master_id . "' class='consigneelist' onchange='selectparty(" . $res->master_id . ")' value='" . $res->code . "'>";
        } else {
            $check = "<input type='radio' name='consigneelist' id='consigneelist_" . $res->master_id . "' class='consigneelist' onchange='selectparty(" . $res->master_id . ")' value='" . $res->code . "'>";
        }
        $master_ids[] = $res->master_id;
        $parties[] = array('check' => $check, 'id' => $res->code, 'name' => $res->master_name, 'email' => $res->master_email_id, 'mobile' => $res->master_mobile, 'party_name' => $res->party_name, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'party_name' => $res->party_name,'city'=>$res->city,'country'=>$res->country,'street'=>$res->street);
    }
}
$master_whr = "";
$additional_ids = $party_types = array();
if(!empty($master_ids)){
    $master_whr ="AND m.id NOT IN (".implode(',', $master_ids).")  ";
}
$getmultipleparties = $this->db->query("SELECT m.id as master_id,m.party_types FROM tbl_party_master m WHERE m.code IS NOT NULL ".$master_whr." ".$whr." ".$usrwhr." AND m.party_types IS NOT NULL");
if($getmultipleparties->num_rows() >0){
    foreach($getmultipleparties->result() as $res){
        if($res->party_types != "" && $res->party_types != '0'){
            $party_types = explode(',', $res->party_types);
            if(is_array($party_types)){
                if(!empty($party_types)){
                    $chkshipper = $this->db->query("SELECT id FROM tbl_party_types WHERE id IN (".implode(',', $party_types).") AND name LIKE '".$party_type."'");
                    if($chkshipper->num_rows()>0){
                        $additional_ids[] = $res->master_id;
                    } 
                }
            }
        }
    }
}

if(!empty($additional_ids)){
    $get_addids = $this->db->query("SELECT m.id as master_id,m.name as master_name,m.email as master_email_id,m.mobile as master_mobile,m.code,m.company_code,m.branch_code,m.location_id as city,m.country,m.street FROM tbl_party_master m WHERE m.id IN (".implode(',', $additional_ids).") AND m.company_code != '' AND m.company_code IS NOT NULL AND m.company_code LIKE '".$chkcompanycode."' AND m.branch_code != '' AND m.branch_code IS NOT NULL  ".$usrwhr." AND m.parent_id ='0'  AND m.status=1 ANd m.code != '' AND m.code is NOT NULL AND m.code != '0' GROUP BY m.id ORDER BY m.id DESC");
    if ($get_addids->num_rows() > 0) {
        foreach ($get_addids->result() as $res) {
            if ($type == '1') {
                $check = "<input type='radio' name='consigneelist' id='consigneelist_" . $res->master_id . "' class='consigneelist' onchange='selectparty(" . $res->master_id . ")' value='" . $res->code . "'>";
            } else {
                $check = "<input type='radio' name='consigneelist' id='consigneelist_" . $res->master_id . "' class='consigneelist' onchange='selectparty(" . $res->master_id . ")' value='" . $res->code . "'>";
            }
            $master_ids[] = $res->master_id;
            $parties[] = array('check' => $check, 'id' => $res->code, 'name' => $res->master_name, 'email' => $res->master_email_id, 'mobile' => $res->master_mobile, 'party_name' => $party_type, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code, 'party_name' => $party_type,'city'=>$res->city,'country'=>$res->country,'street'=>$res->street);
        }
    }
}

echo json_encode($parties);

}
public function getshipperdetailslistbyID()
{
    $parties = array();
    $code    = $this->input->post('id');
    $chkqry  = $this->db->query("SELECT c.id,c.name,c.phone,c.address,c.street,c.location as city,c.state,c.pincode,c.code,c.country,c.email_id,c.fax,c.company_code,c.branch_code FROM tb_customers c WHERE c.code LIKE '%" . $code . "%' GROUP BY c.id ORDER BY c.id DESC");
    if ($chkqry->num_rows() > 0) {
        foreach ($chkqry->result() as $res) {
            $parties[] = array('check' => "<input class='shipperlist' type='radio' name='selectshipper' id='shipperlist_" . $res->id . "' value='" . $res->code . "' onchange=selectshipper(" . $res->id . ")>", 'id' => $res->id, 'party_id' => $res->code, 'name' => $res->name, 'phone' => $res->phone, 'email' => $res->email_id, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code,'city'=>$res->city,'country'=>$res->country,'street'=>$res->street);
        }
    }
    echo json_encode($parties);
}
public function getshipperListID()
{
    $parties      = array();
    $user_id      = $this->session->userdata('user_id');
    $company_code = $this->session->userdata('company_code');
    $chkqry       = $this->db->query("SELECT c.id,c.name,c.phone,c.code,c.country,c.street,c.location as city,c.email_id,c.company_code,c.branch_code FROM tb_customers c WHERE  c.status ='1' AND  c.company_code != '' AND c.company_code IS NOT NULL AND c.user_id='" . $user_id . "'  GROUP BY c.id ORDER BY c.createdon DESC");
    if ($chkqry->num_rows() > 0) {
        foreach ($chkqry->result() as $res) {
            $parties[] = array('check' => "<input class='shipperlist' type='radio' name='selectshipper' id='shipperlist_" . $res->id . "' value='" . $res->code . "' onchange=selectshipper(" . $res->id . ")>", 'id' => $res->id, 'party_id' => $res->code, 'name' => $res->name, 'phone' => $res->phone, 'email' => wordwrap($res->email_id, 25, "<br />\n"), 'company_code' => $res->company_code, 'branch_code' => $res->branch_code,'city'=>$res->city,'country'=>$res->country,'street'=>$res->street);

        }
    }
    echo json_encode($parties);
}
public function getconsigneedetailsListbyID()
{
    $user_id = 1;
    $parties = array();
    $code    = $this->input->post('id');
    $chkqry  = $this->db->query("SELECT p.id,p.party_type_id,p.name,p.email,p.street,p.location_id as city,p.state,p.mobile,p.address,p.pincode,p.country,p.code,p.fax FROM tbl_party_master p WHERE p.code LIKE '%" . $code . "%' ORDER BY p.id DESC");
    if ($chkqry->num_rows() > 0) {
        $role_name   = "";
        $getrolename = $this->db->select('name')->get_where("tbl_party_types", array('id' => $chkqry->row()->party_type_id));
        if ($getrolename->num_rows() > 0) {
            $role_name = $getrolename->row()->name;
        }
        $parties[] = array('id' => $chkqry->row()->id, 'name' => $chkqry->row()->name, 'phone' => $chkqry->row()->mobile, 'street' => $chkqry->row()->street, 'city' => $chkqry->row()->city, 'pincode' => $chkqry->row()->pincode, 'code' => $chkqry->row()->code, 'country' => $chkqry->row()->country, 'email_id' => $chkqry->row()->email, 'fax' => $chkqry->row()->fax, 'state' => $chkqry->row()->state, 'address' => $chkqry->row()->address, 'party_type_id' => $chkqry->row()->party_type_id, 'role_name' => $role_name);
    }
    echo json_encode($parties);
}
public function getpartydetailsListbyID()
{
    $parties      = array();
    $code         = $this->input->post('id');
    $type         = isset($_POST['type']) ? $_POST['type'] : "";
    $user_id      = $this->session->userdata('user_id');
    $company_code = $branch_code = $whr = "";
    $company_code = $this->session->userdata('company_code');
    $branch_code  = $this->session->userdata('branch_code');
    $whr          = "";
    if ($company_code != "") {
        $whr .= " AND m.company_code LIKE '" . $company_code . "'";
    }
    $custid = "0";
    $whr1 = "";
    if($company_code != 'NZKN'){
       if($this->session->userdata('cust_id') !== FALSE){
        $custid =$this->session->userdata('cust_id');
    }
    $subcusts = array();
    if($custid != 0){
        if($this->session->userdata('sub_cust') !== FALSE){
            $subcusts = $this->session->userdata('sub_cust');
            if(count($subcusts)>0){
                array_push($subcusts, $custid);
            }else{
                $subcusts = $custid;
                       // array_push($subcusts, $custid);
            }
        }else{
            $subcusts  =$custid;
                   // array_push($subcusts, $custid);
        }
    }
    $customer_code= array();
    if(!empty($subcusts)){
        $select = "code";
        $table = "tb_customers";
        $customerdetails = $this->Order->getcustomercodebyids($select,$table,$subcusts);
        if(!empty($customerdetails)){
            foreach($customerdetails as $cust){
                $customer_code[] = $cust['code'];
            }
        }
    }
    if(!empty($customer_code)){
        $whr1 = "AND m.customer_code IN ('" . implode("','", $customer_code) . "') ";
    } 
}
$chkqry = $this->db->query("SELECT m.id,m.name,m.email,m.street,m.location_id as city,m.state,m.mobile,m.address,m.country,m.pincode,m.country,m.code,m.company_code,m.branch_code,m.location_id as city,m.country,m.street,m.fax,t.id as partytype_id,t.name as role FROM tbl_party_master m,tbl_party_types t WHERE m.code LIKE '%" . $code . "%' " . $whr . " AND m.code Is NOT NULL AND m.code != '0' AND m.code != '' ".$whr1." AND t.id=m.party_type_id AND m.company_code != '' AND m.company_code IS NOT NULL AND m.branch_code != '' AND m.branch_code IS NOT NULL GROUP BY m.id ORDER BY m.id DESC");

if ($chkqry->num_rows() > 0) {
    foreach ($chkqry->result() as $res) {
        $check = "<input type='radio' name='consigneelist' id='consigneelist_" . $res->id . "' class='consigneelist' onchange='selectparty(" . $res->id . ")' value='" . $res->code . "'>";
        if ($type == 'inv') {
            $check = "<input type='radio' name='partylist' id='partylist_" . $res->id . "' class='partylist' onchange='selectparty(" . $res->id . ")' value='" . $res->code . "'>";
        } else {
            $check = "<input type='radio' name='consigneelist' id='consigneelist_" . $res->id . "' class='consigneelist' onchange='selectparty(" . $res->id . ")' value='" . $res->code . "'>";
        }
        $parties[] = array('check' => $check, 'id' => $res->code, 'name' => $res->name, 'email' => $res->email, 'mobile' => $res->mobile, 'party_name' => $res->role, 'company_code' => $res->company_code, 'branch_code' => $res->branch_code,'city'=>$res->city,'country'=>$res->country,'street'=>$res->street);
    }

}
echo json_encode($parties);
}
public function getchargedesc($id)
{
    $data['desc'] = "";
    if ($id != "") {
        $getdesc = $this->db->query("SELECT description FROM tb_charge_codes WHERE id=$id AND status=1");
        if ($getdesc->num_rows() > 0) {
            $data['desc'] = $getdesc->row()->description;
        }
    }
    echo json_encode($data);
}

/* code for trip management */
public function gettripcarinfo($id)
{
    $result = array();
    if ($id != "") {
        $vendor_list = $this->db->select("code")->get_where("tb_vendors", array('id' => $id));
        if ($vendor_list->num_rows() > 0) {
            $result['carrier_name'] = $vendor_list->row()->code;
        }
        $drivers = '<select class="add_company Txtfld tripcar_id form-control" name="vehicle_id" id="tdri_name" onchange="getdrid()"
        form="tripcreation" style="margin-top:4px;">
        <option value="">--Select--</option>';
        if($this->session->userdata('business_type') == "Carrier"){
            $drivers_list = $this->db->select("id,name")->get_where("tb_truck_drivers", array('vendor_id' => $id, 'status'=>'Active'));
        }else{
            $drivers_list = $this->db->select("id,name")->get_where("tb_truck_drivers", array('vendor_id' => $id, 'status'=>'Active'));
            if($drivers_list->num_rows() == 0){
                $user_id = $this->session->userdata('user_id');
                $drivers_list = $this->db->select("id,name")->get_where("tb_truck_drivers", array('user_id' => $user_id, 'status'=>'Active'));
            }
        }
        if ($drivers_list->num_rows() > 0) {
            foreach ($drivers_list->result() as $row) {
                $drivers .= '<option value="' . $row->id . '">' . $row->name . '</option>';
            }
        }
        $drivers .= '</select>';
        $result['drivers'] = $drivers;
    }
    echo json_encode($result);
}
public function gettripvelinfo()
{
    $result    = array();
    $post      = $this->input->post();
    $id        = $post['vtype'];
    $carrierid = $post['carrierid'];
    $res       = '';
    $vtypename = $this->db->select("description")->get_where("tb_trucktypes",
        array('id' => $id));
    if ($vtypename->num_rows() > 0) {
        $res = $vtypename->row()->description;
    }
    $result['typename'] = $res;
    $vehres             = '<select name="tvehnum" id="tvehnum" onchange="myfun()"
    form="tripcreation" class="Txtfld tripcar_id form-control" style="margin-top:4px;"><option value="">--Select--</option>';
    $vehile_list = $this->db->select("id,truck_number,register_number")->get_where("tb_trucks_data", array('truck_type' => $id, 'vendor_id' => $carrierid));
    /*$vehile_list =  $this->db->select("select td.id,td.truck_number,td.register_number from tb_trips tp,`tb_trucks_data` td where tp.vehicle_id=td.id and tp.transit_status=0 and td.truck_type='".$id."' AND td.vendor_id='".$carrierid."'");*/
    if ($vehile_list->num_rows() > 0) {
        foreach ($vehile_list->result() as $vehi) {
            $vehres .= '<option value="' . $vehi->id . '">' . $vehi->truck_number . '</option>';
        }
    }
    $vehres .= '</select>';
    $result['vehiclenum'] = $vehres;
    echo json_encode($result);
}
public function getvelinfo($id)
{
    $result            = array();
    $result['transit'] = 0;
    if ($id != "") {
        $vendor_list = $this->db->select("truck_number")->get_where("tb_trucks_data", array('id' => $id));
        if ($vendor_list->num_rows() > 0) {
            $result['vehid'] = $vendor_list->row()->truck_number;
        }
        $trip_transit = $this->db->select("id")->get_where("tb_trips", array('vehicle_id' => $id, 'status' => 1));
        if ($trip_transit->num_rows() > 0) {
            $result['vehid']   = '';
            $result['transit'] = 1;
        }
    }
    echo json_encode($result);

}
public function getdriverid($id)
{
    $res = '';
    if ($id != "") {
        $driver_list = $this->db->select("contact_num")->get_where("tb_truck_drivers", array('id' => $id), 1, 0);
        if ($driver_list->num_rows() > 0) {
            $res = $driver_list->row()->contact_num;
        }
    }
    echo $res;
}

public function tripcreatemultiorder($input){
    $response = 0;
    $userid = $input['user_id'];
    $curtz = $input['curtz'];
    $logdate = date('Y-m-d H:i:s');
    $curdt = $input['curdt'];
    $year=date('y');
    $week=date('W');
	$coun_sess=$input['company_code'];
	$count_code=substr($coun_sess, 0, 2);
	$seq_num=000001;
    $ordersnw=[];
	$company_code = $this->session->userdata('company_code');
    $branch_code  = $this->session->userdata('branch_code');
	$consignee_mobile = $consignor_mobile = $shipper_mobile = $carrier_mobile = "";
	$getordersnw = $this->db->query("SELECT `o`.`id`, `o`.`order_id`, `o`.`pickup_datetime`, `o`.`pickup_endtime`, `o`.`shipment_id`, `o`.`delivery_datetime`, `o`.`drop_endtime`, `o`.`pickup_company` AS `pickup`, `o`.`delivery_company` AS `delivery`, `o`.`pickup_address1`, `o`.`delivery_address1`, `o`.`pickup_address2`, `o`.`delivery_address2`, `o`.`pickup_city`, `o`.`delivery_city`, `o`.`pickup_country`, `o`.`delivery_country`, `o`.`pickup_pincode`, `o`.`delivery_pincode`, `o`.`company_code`, `o`.`branch_code`, `o`.`product`,`o`.`plat`,`o`.`plng`,`o`.`dlat`,`o`.`dlng`, `o`.`goods_value`, `o`.`transport_mode`, `o`.`vehicle_type`, `o`.`quantity`, `o`.`volume`, `o`.`weight`, `o`.`customer_id`, `o`.`vendor_id`, `o`.`pickup_custid`, `o`.`pickup_partyid`, `o`.`drop_custid`, `o`.`drop_partyid`, `o`.`trip_sts`, `o`.`shift_id`, `o`.`trip_id`, `o`.`status`, `o`.`order_status`, `o`.`createdon`, `o`.`updatedon`, `o`.`shipmentid`, `d`.`order_type`, `d`.`shipper_id`, `d`.`service`, `d`.`delivery_term`, `d`.`incoterm`, `d`.`delivery_note`, `d`.`purchase_order`, `d`.`notify_party`, `d`.`lane_reference`, `d`.`distance`, `d`.`customs_required`, `d`.`high_cargo_value`, `d`.`valorance_insurance`, `d`.`temperature_control`, `d`.`department_code` 
	FROM `tb_orders` `o` LEFT JOIN `tb_order_details` `d` ON `o`.`id`=`d`.`order_row_id` 
	WHERE `o`.`id` IN (".$input['ordid'].")");
	 
     if($getordersnw->num_rows()>0){
		$where = " id IN(".$input['ordid'].")";
		log_message("error",$where);
		$select = "order_id";
		$table = "tb_orders";
		$ordersId = $this->common->gettbldata($where,$select,$table,0,0);  
		$ordcnt = count($ordersId);
		if($ordcnt>0){			
		$order_nm=$ordersId[0]['order_id'];		
		}
        $seq_num=substr($order_nm, -6);	
        $shipid = "T".$count_code.$week.$year.$seq_num;
        $vendor_id=$input['carrierid'];
        $vehicle_id = isset($input['tvehnum']) ? $input['tvehnum'] : "";
        $driver_id = isset($input['tdrivernum']) ? $input['tdrivernum'] : "";
        $tpvehicle_type=isset($input['vehicle_type']) ? $input['vehicle_type'] : 0;
        $carrier_instructions = isset($input['carrier_instructions']) ? $input['carrier_instructions'] : "";
        $vehicle_type = "";
        if($shipid != "" && $vendor_id > 0){
            $i = $j = 0;
            $weight = 0;
            $volume = 0; 
            $quantity = 0; 
            foreach($getordersnw->result() as $order){
				
				/* echo $pickup_id = $order->customer_id;
				echo "<br>";
				echo $order_id = $order->order_id;
				echo "<br>";
                exit; */				
                $j++;
                $i++;
				$ordersnw[] = array('order_id'=>$order->order_id,'order_type'=>$order->order_type,'product'=>$order->product,'service'=>$order->service,'incoterm'=>$order->incoterm,'pickup_company'=>$order->pickup,'pickup_country'=>$order->pickup_country,'pickup_city'=>$order->pickup_city,'pickup_pincode'=>$order->pickup_pincode,'pickup_address1'=>$order->pickup_address1,'pickup_address2'=>$order->pickup_address2,'pickup_datetime'=>$order->pickup_datetime,'pickup_endtime'=>$order->pickup_endtime,'delivery_company'=>$order->delivery,'delivery_country'=>$order->delivery_country,'delivery_city'=>$order->delivery_city,'delivery_pincode'=>$order->delivery_pincode,'delivery_address1'=>$order->delivery_address1,'delivery_address2'=>$order->delivery_address2,'delivery_datetime'=>$order->delivery_datetime,'delivery_endtime'=>$order->drop_endtime,'quantity'=>$order->quantity,'volume'=>$order->volume,'weight'=>$order->weight);
                $ordid = $order->id;
                $pickup = $order->pickup_address1;
                if($pickup == ""){ $pickup = $order->pickup_city; }
                $delivery = $order->delivery_address1;
                if($delivery == ""){ $delivery = $order->delivery_city; }
                $address = $dropaddr = "";
				$pickup_address1=$order->pickup_address1;
                if($pickup_address1 != ""){
                    $address .= $order->pickup_address1;
                    $dropaddr .= $order->delivery_address1;
                }
				$pickup_company=$order->pickup;
                if($pickup_company != ""){
                    $address .= ", ".$order->pickup;
                    $dropaddr .= ", ".$order->delivery;
                }
				$pickup_country=$order->pickup_country;
                if($pickup_country != ""){
                    $address .= ", ".$order->pickup_country;
                    $dropaddr .= ", ".$order->delivery_country;
                }
                if($order->pickup_city != ""){
                    $address .= ", ".$order->pickup_city;
                    $dropaddr .= ", ".$order->delivery_city;
                }
                if($order->pickup_pincode != ""){
                    $address .= ", ".$order->pickup_pincode;
                    $dropaddr .= ", ".$order->delivery_pincode;
                }
				 
				
                if($order->weight == ""){ $order->weight = 1; }
                if($order->volume == ""){ $order->volume = 1; }
                if($order->quantity == ""){ $order->quantity = 1; }
                $weight += $order->weight;
                $volume += $order->volume;
                $quantity += $order->quantity;
                $plat = $order->plat;
                $plng = $order->plng;
                $dlat = $order->dlat;
                $dlng = $order->dlng;
				if($customer_phone){
					$customer_phone = $order->customer_phone;
				}else{
					$customer_phone = "";
				}
                if($customer_email){
					$customer_email = $order->customer_email;
				}else{
					$customer_email = "";
				}
				if($customer_comments){
				    $customer_comments = $order->customer_comments;
				}else{
				    $customer_comments = "";
				}				
				$pickup_id = $order->customer_id; 
        $getpickupdetails = $this->db->query("SELECT name,phone,state,street,location,pincode, address, email_id, code,country FROM tb_customers WHERE id IN ('".$pickup_id."') AND status=1");
		/* echo $this->db->last_query();
		exit; */
        if($getpickupdetails->num_rows() >0){
          $customerdetails = array('name'=>$getpickupdetails->row()->name,'party_id'=>$getpickupdetails->row()->code,'address'=>$getpickupdetails->row()->address,'pincode'=>$getpickupdetails->row()->pincode,'country'=>$getpickupdetails->row()->country,'street'=>$getpickupdetails->row()->street,'city'=>$getpickupdetails->row()->location,'state'=>$getpickupdetails->row()->state,'phone'=>$getpickupdetails->row()->phone,'email'=>$getpickupdetails->row()->email_id);
		   $costomerId=$customerdetails['party_id'];
	  $customerName=$customerdetails['name'];
	  $customerAddress=$customerdetails['street'];
	  $customerCity=$customerdetails['city'];
	  $customerPin=$customerdetails['pincode'];
	  $customerAddr2=$customerdetails['address'];
	  $customerEmail=$customerdetails['email'];
	  $customerCountry=$customerdetails['country'];
	  $customerPhone=$customerdetails['phone'];
	  $customerstate=$customerdetails['state'];
      }
                $where = array("shipmentid"=>$shipid);
                $select = "id,splace,slat,slng,eplace,elat,elng,vendor_id,weight,volume,units,vehicle_type";
                $table = "tb_shifts";
                $shipment = $this->common->gettblrowdata($where,$select,$table,0,0);
                /*log_message("error","2nd ".$this->db->last_query());*/
                if(count($shipment)==0){
                    $customer_id = $order->customer_id;
                    $transport_mode= $order->transport_mode;
                    $txnid = $shipid;
                    if($tpvehicle_type!='' && $tpvehicle_type != 0){
                        $vtwhere = array("id"=>$tpvehicle_type);
                        $vtselect = "trucktype";
                        $vttable = "tb_trucktypes";
                        $vtorder = $this->common->gettblrowdata($vtwhere,$vtselect,$vttable,0,0);
                        if(count($vtorder)>0){
                            $vehicle_type = $vtorder['trucktype'];
                        }else{
                            $vehicle_type = $order->vehicle_type;
                        }
                    }else{
                        $vehicle_type = $order->vehicle_type;
                    }
                    $startdate = $order->pickup_datetime;
                    $starttime = date('H:i',strtotime($startdate));
                    $enddate = $order->delivery_datetime;
                    $endtime = date('H:i',strtotime($enddate));
                    $insarr = array('user_id'=>$userid, 'stime'=>$starttime, 'etime'=>$endtime, 'splace'=>$order->pickup_city, 'slat'=>$plat, 'slng'=>$plng, 'eplace'=>$order->delivery_city, 'elat'=>$dlat, 'elng'=>$dlng, 'scity'=>$order->pickup_city, 'dcity'=>$order->delivery_city, 'zone_id'=>1, 'empshift_start'=>$starttime, 'empshift_end'=>$endtime, 'trip_type'=>0, 'startdate'=>$startdate, 'enddate'=>$enddate, 'shipment_name'=>'Boxes', 'shipment_id'=>0, 'transport_mode'=>$transport_mode, 'customer_id'=>$customer_id, 'vendor_id'=>$vendor_id, 'carrier_type'=>0, 'shipmentid'=>$shipid, 'txnid'=>$txnid,'weight'=>$weight, 'volume'=>$volume, 'units'=>$quantity,'domainname'=>'INFD', 'vehicle_type'=>$vehicle_type, 'company_code'=>$order->company_code,'branch_code'=>$order->branch_code,'carrier_instructions'=>$carrier_instructions, 'status'=>1);
                    $ship_id = $this->common->insertTableData("tb_shifts", $insarr);
                }else{
                    if($ordcnt == $j){
                        $enddate = $order->delivery_datetime;
                        $endtime = date('H:i',strtotime($enddate));
                        $updarr = array('etime'=>$endtime,'eplace'=>$order->delivery_city, 'elat'=>$dlat, 'elng'=>$dlng,'dcity'=>$order->delivery_city,'empshift_end'=>$endtime,'enddate'=>$enddate,'weight'=>$weight, 'volume'=>$volume, 'units'=>$quantity,'carrier_instructions'=>$carrier_instructions);
                        $upd = $this->common->updatetbledata("tb_shifts", $updarr,array("id"=>$shipment['id']));
                    }
                    $ship_id = $shipment['id'];
                }
                /*update order table*/
                $upddt = array('vendor_id'=>$vendor_id,'shift_id'=>$ship_id,"shipmentid"=>$shipid,'status'=>2);
                $updwhr = array("id"=>$ordid);
                $upd = $this->common->updatetbledata("tb_orders",$upddt,$updwhr);
                $this->ordernotify('trip_create',$ordid);
                $capacity = $order->weight;
                if($capacity == ""){
                    $capacity = "0";
                }
                /*insert stops records */
                $where = array("shipment_id"=>$ship_id,'plat'=>$plat,'plng'=>$plng,"stopcity"=>$order->pickup_city,"stoptype"=>"P");
                $select = "id";
                $table = "tb_shiporder_stops";
                $chkstop = $this->common->gettblrowdata($where,$select,$table,0,0);
                if(count($chkstop) == 0){
                    $insarr = array('stopname'=>$order->pickup_city, 'plat'=>$plat, 'plng'=>$plng, 'stopcity'=>$order->pickup_city, 'address'=>$address, 'stoptype'=>'P', 'stopstatus'=>0, 'shipment_id'=>$ship_id, 'ordernumber'=>$j, 'startdate'=>$order->pickup_datetime, 'enddate'=>$order->delivery_datetime, 'weight'=>$order->weight, 'volume'=>$order->volume, 'shipmentstopid'=>0, 'ship_units'=>$order->quantity, 'txncode'=>'NP', 'status'=>1, 'created_on'=>$curdt);
                    $ins = $this->db->insert("tb_shiporder_stops",$insarr);
                    $pickupstop_id = $this->db->insert_id();
                }else{
                    $pickupstop_id = $chkstop['id'];
                }
                $where = array("shipment_id"=>$ship_id,'plat'=>$dlat,'plng'=>$dlng,"stopcity"=>$order->delivery_city,"stoptype"=>"D");
                $select = "id";
                $table = "tb_shiporder_stops";
                $chkstop = $this->common->gettblrowdata($where,$select,$table,0,0);
                if(count($chkstop) == 0){
                    $insarr = array('stopname'=>$order->delivery_city, 'plat'=>$dlat, 'plng'=>$dlng, 'stopcity'=>$order->delivery_city, 'address'=>$dropaddr, 'stoptype'=>'D', 'stopstatus'=>0, 'shipment_id'=>$ship_id, 'ordernumber'=>$i+1, 'startdate'=>$order->delivery_datetime, 'enddate'=>$order->delivery_datetime, 'weight'=>$order->weight, 'volume'=>$order->volume, 'shipmentstopid'=>0, 'ship_units'=>$order->quantity, 'txncode'=>'NP', 'status'=>1, 'created_on'=>$curdt);
                    $ins = $this->db->insert("tb_shiporder_stops",$insarr);
                    $dropstop_id = $this->db->insert_id();
                }else{
                    $dropstop_id = $chkstop['id'];
                }
                /*add pickupstop and drop stop id into details/tb_employee table */

                $where = array('stop_id'=>$pickupstop_id, 'drop_stopid'=>$dropstop_id,'order_id'=>$order->order_id,'shift_id'=>$ship_id);
                $select = "id";
                $table = "tb_employee";
                $chkemp = $this->common->gettblrowdata($where,$select,$table,0,0);
                if(count($chkemp) == 0){
                    $insarr = array('assoc_id'=>$shipid, 'pickup'=>$pickup, 'plat'=>$plat,
                        'plng'=>$plng, 'drop'=>$delivery,'dlat'=>$dlat, 'dlng'=>$dlng, 'pickup_city'=>$order->pickup_city, 'drop_city'=>$order->delivery_city, 'pickup_datetime'=>$order->pickup_datetime, 'drop_datetime'=>$order->delivery_datetime, 'name'=>'Boxes', 'phone'=>$customer_phone, 'address'=>$address,'emailid'=>$customer_email, 'user_id'=>$userid, 'status'=>1, 'createdon'=>$curdt, 'material_id'=>0, 'capacity'=>$capacity, 'information'=>$customer_comments, 'shipment_weight'=>$order->weight, 'shipment_volume'=>$order->volume, 'ship_type'=>'P', 'customer_id'=>$order->customer_id, 'vendor_id'=>$vendor_id, 'shipment_id'=>0, 'startdate'=>$order->pickup_datetime, 'enddate'=>$order->delivery_datetime, 'shift_id'=>$ship_id, 'stop_order'=>1, 'drop_order'=>0, 'basic_stop'=>0, 'stop_id'=>$pickupstop_id, 'drop_stopid'=>$dropstop_id, 'order_id'=>$order->order_id,'pkgitemid'=>'BOXES', 'no_of_pkgs'=>$order->quantity, 'domainname'=>'INFD');
                    $detail_id = $this->common->insertTableData("tb_employee",$insarr);
                }else{
                    $detail_id = $chkemp['id'];
                }
                $stops_units = $this->db->query("SELECT oc.id,oc.quantity,oc.width,oc.height,oc.length,oc.weight,im.unit_name FROM tb_order_cargodetails oc,tbl_shipunit_types im WHERE oc.order_id = '$ordid' AND im.id = oc.handling_unit AND oc.status='1'");
                if($stops_units->num_rows()>1){
                    $odata=$stops_units->row();
                    $upd = $this->db->query("UPDATE tb_order_cargodetails set stop_detail_id='$detail_id' WHERE id = '".$odata->id."'");
                }
                $chk1 = $this->db->select("id")->get_where("tb_shft_veh",array("shft_id"=>$ship_id),1,0);
                if($chk1->num_rows() > 0){
                    $shftvehid = $chk1->row()->id;
                    $chk = $this->db->select("id")->get_where("tb_shft_veh_emp",array("shft_veh_id"=>$shftvehid,"status"=>1),1,0);
                    if($chk->num_rows() == 0){
                      $getemp = $this->db->select("id,pickup_datetime,drop_datetime")->get_where("tb_employee",array("shift_id"=>$ship_id));
                      if($getemp->num_rows()>0){
                        $pri = 1;
                        foreach($getemp->result() as $gt){
                            $insveh1 = array("user_id"=>$userid,"shft_veh_id"=>$shftvehid,"emp_id"=>$gt->id,"priority"=>$pri,"pickup_time"=>$gt->pickup_datetime, 'created_on'=>$curdt,'updated_on'=>$curdt,"status"=>1,"drop_time"=>$gt->drop_datetime);
                            $ins = $this->common->insertTableData("tb_shft_veh_emp",$insveh1);
                            $pri++;
                        }
                    }
                }else{
                    $chkemp = $this->db->select("id")->get_where("tb_shft_veh_emp",array("shft_veh_id"=>$shftvehid,"emp_id"=>$detail_id,"status"=>1),1,0);
                    if($chkemp->num_rows() == 0){
                        $insveh1 = array("user_id"=>$userid,"shft_veh_id"=>$shftvehid,"emp_id"=>$detail_id,"priority"=>3,"pickup_time"=>$order->pickup_datetime, 'created_on'=>$curdt,'updated_on'=>$curdt,"status"=>1,"drop_time"=>$order->delivery_datetime);
                        $ins = $this->common->insertTableData("tb_shft_veh_emp",$insveh1);
                    }
                }
            }else{
                if($vehicle_id != ""){
                    $veh_num = isset($input['vehicle_id']) ? $input['vehicle_id'] : "";
                    $arr = array('user_id'=>$userid, 'route_id'=>0, 'shft_id'=>$ship_id, 'carrier_id'=>$vendor_id, 'vehicle_id'=>$vehicle_id, 'register_number'=>$veh_num, 'created_on'=>$curdt,'updated_on'=>$curdt, 'status'=>1);
                    $shftvehid = $this->common->insertTableData("tb_shft_veh",$arr);
                    $getemp = $this->db->select("id,pickup_datetime,drop_datetime")->get_where("tb_employee",array("shift_id"=>$ship_id));
                    if($getemp->num_rows()>0){
                        $pri = 1;
                        foreach($getemp->result() as $gt){
                          $insveh1 = array("user_id"=>$userid,"shft_veh_id"=>$shftvehid,"emp_id"=>$gt->id,"priority"=>$pri,"pickup_time"=>$gt->pickup_datetime, 'created_on'=>$curdt,'updated_on'=>$curdt,"status"=>1,"drop_time"=>$gt->drop_datetime);
                          $ins = $this->common->insertTableData("tb_shft_veh_emp",$insveh1);
                          $pri++;
                      }
                  }
              }
          }
          if($vehicle_id != "" && $driver_id != ""){
            $chk = $this->db->select("id,driver_id,status")->order_by("id","DESC")->get_where("tb_vehicles_drivers",array("vehicle_id"=>$vehicle_id));
            if($chk->num_rows()>0){
                if($driver_id == $chk->row()->driver_id){
                    $upd = $this->db->where(array("id"=>$chk->row()->id))->update("tb_vehicles_drivers",array("status"=>1));
                }else{
                    $arr = array('vehicle_id'=>$vehicle_id, 'driver_id'=>$driver_id, 'imei'=>"", 'status'=>1, 'createdon'=>$curdt, 'updatedon'=>$curdt);
                    $ins = $this->common->insertTableData("tb_vehicles_drivers",$arr);
                }
            }else{
                $arr = array('vehicle_id'=>$vehicle_id, 'driver_id'=>$driver_id, 'imei'=>"", 'status'=>1, 'createdon'=>$curdt, 'updatedon'=>$curdt);
                $ins = $this->common->insertTableData("tb_vehicles_drivers",$arr);
            }
            $chk11 = $this->db->select("id")->get_where("tbl_assigned_drivers",array("driver_id"=>$driver_id,"vehicle_id"=>$vehicle_id),1,0);
            if($chk11->num_rows()>0){
                $upd = $this->db->where(array("id"=>$chk11->row()->id))->update("tbl_assigned_drivers",array("status"=>"1",'updated_on'=>$curdt));
            }else{
                $chk111 = $this->db->select("id")->get_where("tbl_assigned_drivers",array("driver_id <>"=>$driver_id,"vehicle_id"=>$vehicle_id,"status"=>1),1,0);
                if($chk111->num_rows() == 0){
                  $ins = $this->common->insertTableData("tbl_assigned_drivers",array("vehicle_id"=>$vehicle_id,"user_id"=>$userid,"driver_id"=>$driver_id,"mobile_no"=>$driver_id,"imei"=>$imei,"from_time"=>$curdt,"to_time"=>$curdt,"status"=>"1",'created_on'=>$curdt));
              }else{
                  $upd = $this->db->where(array("id"=>$chk111->row()->id))->update("tbl_assigned_drivers",array("driver_id"=>$driver_id,'updated_on'=>$curdt));
              }
          }
      }
      $response = 1;
      /* update status */
      $chk = $this->db->select("id")->get_where("tb_stop_status",array("shipment_id"=>$ship_id,"status_id"=>9),1,0);
      if($chk->num_rows() == 0){
        $ins = array("shipment_id"=>$ship_id,"stop_id"=>0,"stop_detail_id"=>0,"stop_type"=>"","trip_id"=>0,"status_id"=>9,"status"=>1,"status_code"=>"0100","reason"=>"Coming from E-Booking","createdon"=>$curdt);
        $insqry = $this->common->insertTableData("tb_stop_status",$ins);
    }
	$chkCarrier = $this->db->select("*")->get_where("tb_vendors",array("id"=>$input['carrierid']),1,0);
        if($chkCarrier->num_rows()>0){
                $carName = $chkCarrier->row()->name;
                $carMail = $chkCarrier->row()->email;
                $carmobile = $chkCarrier->row()->mobile;
                $caraddress = $chkCarrier->row()->address;
                $carpincode = $chkCarrier->row()->pincode;
                $carcountry = $chkCarrier->row()->country;
                $carcode= $chkCarrier->row()->code;
			}else{
				$carpincode='';
			}
		if($input['tvehnum']!=''){
			 $chkTruckdata = $this->db->select("*")->get_where("tb_trucks_data",array("id"=>$input['tvehnum']),1,0);
        if($chkTruckdata->num_rows()>0){
                $truck_weight = $chkTruckdata->row()->truck_weight;
                $weight_unit = $chkTruckdata->row()->weight_unit;
                $truck_volume = $chkTruckdata->row()->truck_volume;
                $volume_unit = $chkTruckdata->row()->volume_unit;
                $truckheight = $chkTruckdata->row()->height;
                $height_unit = $chkTruckdata->row()->height_unit;
                $breadth = $chkTruckdata->row()->breadth;
                $breadth_unit = $chkTruckdata->row()->breadth_unit;
                $length = $chkTruckdata->row()->length;
                $length_unit = $chkTruckdata->row()->length_unit;
   
			}
		}else{
			$truck_weight='';
			$weight_unit='';
			$truck_volume='';
			$volume_unit = '';
			$truckheight = '';
			$height_unit = '';
			$breadth = '';
			$breadth_unit = '';
			$length = '';
			$length_unit = '';
			$veh_num = '';
		}	
	   if($driver_id!=''){
		 $chkDriver = $this->db->select("*")->get_where("tb_truck_drivers",array("id"=>$input['vehicle_id']),1,0);
        if($chkDriver->num_rows()>0){
                $driverName = $chkDriver->row()->name;
                $drivermobile = $chkDriver->row()->contact_num;
                $drivercity = $chkDriver->row()->city;
                $address_proof_type = $chkDriver->row()->address_proof_type;
                $address_as_on_proof = $chkDriver->row()->address_as_on_proof;
                $address_proof_name = $chkDriver->row()->address_proof_name;

			}  
	   }else{
		   $driverName='';
		   $drivermobile='';
		   $drivercity='';
		   $address_proof_type='';
		   $address_as_on_proof='';
		   $address_proof_name='';
	   }
		 
	$country_code            = $this->session->userdata( "usr_tzone" )['phone_code'];
	
	$consignee_mobile = $consignor_mobile = $shipper_mobile = $carrier_mobile = "";
      $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name, p.mobile, p.email,p.code, p.location_id, p.address, p.country,p.state, p.street, p.pincode, o.party_type, a.party_master_id, a.location_id as plocation_id,a.street as pstreet,a.state as pstate,a.address as paddress,a.pincode as ppincode,a.country as pcountry FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1 LEFT JOIN tbl_orderparty_address a ON o.party_id=a.party_master_id AND o.order_id=a.order_id AND a.status=1 WHERE p.status=1 AND o.order_id='".$input['ordid']."' GROUP BY o.party_type");
      if($chekparty->num_rows()>0){
          foreach($chekparty->result() as $rr){
            $pdetail = array();
            $ptype = $rr->party_type;
            $chktype = $this->db->select("name")->get_where("tbl_party_types",array("id"=>$ptype),1,0);
            if($chktype->num_rows()>0){
              if($rr->party_master_id != ""){
                $pdetail = array('name'=>$rr->name,'party_id'=>$rr->code,'address'=>$rr->paddress,'pincode'=>$rr->ppincode,'country'=>$rr->pcountry,'street'=>$rr->pstreet,'city'=>$rr->plocation_id,'state'=>$rr->pstate,'phone'=>$rr->mobile,'email'=>$rr->email);
            }else{
                $pdetail = array('name'=>$rr->name,'party_id'=>$rr->code,'address'=>$rr->address,'pincode'=>$rr->pincode,'country'=>$rr->country,'street'=>$rr->street,'city'=>$rr->location_id,'state'=>$rr->state,'phone'=>$rr->mobile,'email'=>$rr->email);
            }
            if($chktype->row()->name == "Consignee"){
                $pdetail['type'] = "Consignee";
                $consignee_mobile = $rr->mobile;
            }
              /*if($chktype->row()->name == "CUSTOMER"){
                $pdetail['type'] = "Customer";
            }*/
            if($chktype->row()->name == "Consignor"){
                $pdetail['type'] = "Consignor";
                $consignor_mobile = $rr->mobile;
            }
              /*if($chktype->row()->name == "FREIGHT_PAYER"){
                $pdetail['type'] = "FREIGHT_PAYER";
            }*/
            if($chktype->row()->name == "Shipper"){
                $pdetail['type'] = "Shipper";
                $shipper_mobile = $rr->mobile;
            }
            if($chktype->row()->name == "Carrier"){
                $pdetail['type'] = "Carrier";
                $carrier_mobile = $rr->mobile;
            }
            $parties[] = $pdetail;
        }
    }
}
	$cargos=array();
	$getcargos = $this->db->query("select handling_unit,length,width,height,weight,volume,quantity,quantity_type,cargo_content from tb_order_cargodetails where order_id ='".$input['ordid']."'");
if($getcargos->num_rows() >0){
  foreach($getcargos->result() as $res){
    $volume = $res->volume;
    $weight = $res->weight;
    if($volume == ""){ $volume = 1; }
    if($weight == ""){ $weight = 1; }
    $cargos[] = array('cargo_type'=>$res->quantity_type,'content'=>$res->cargo_content,'length'=>$res->length,'width'=>$res->width,'height'=>$res->height,'weight'=>$weight,'volume'=>$volume,'quantity'=>$res->quantity);
    $total_volume += $volume;
    $total_weight += $weight;
}
}
	
    /*send email notification*/
    if($vendor_id != ""){
                    //spoton code starts here
        $compnay_code = $coun_sess;  
        if($compnay_code == 'INKN' && $vendor_id=='164'){
            $this->getconinfo($ordid); 
        }
                    //spoton code ends here
         /*border code starts here*/
         
        /*border code ends here*/
    }
}
$data = array(
					'SenderTransmissionNo' => $input['ordid'],
					"ordersnw"         => $ordersnw,
					"trip_id"           => $shipid,
					"carrier_id"           => $carcode,
					"carrier_name"           => $carName,
					"carrier_email"           => $carMail,
					"carrier_mobile"           => $carmobile,
					"carrier_address"           => $caraddress,
					"carrier_pincode"           => $carpincode,
					"carrier_country"           => $carcountry,
					"carrier_code"           => $carcode,
					"vehiclereg_number"           => $veh_num,
					"vehicle_type"           => $vehicle_type,
					"truck_weight"           => $truck_weight,
					"weight_unit"           => $weight_unit,
					"truck_volume"           => $truck_volume,
					"volume_unit"           => $volume_unit,
					"truckheight"           => $truckheight,
					"height_unit"           => $height_unit,
					"length"           => $length,
					"length_unit"           => $length_unit,
					"width"                 => $breadth,
					"width_unit"           => $breadth_unit,
					"driverName"           => $driverName,
					"drivermobile"           => $drivermobile,
					"drivercity"           => $drivercity,
					"address_proof_name"           => $address_proof_name,
					"address_as_on_proof"           => $address_as_on_proof,
					"address_proof_type"           => $address_proof_type,
					"country_code"            => $country_code,
					"weight"            => $weight,
					"volume"            => $volume,
					"quantity"            => $quantity,
					"total_weight"            => $total_weight,
					"total_volume"            => $total_volume,
					"company_code"            => $company_code,
					"branch_code"            => $branch_code,
					"cargos"            => $cargos,
					"parties"            => $parties,
					'customer_name'=>$customerName,
					'customer_add1'=>$customerAddress,
					'customer_add2'=>$customerAddr2,
					'customer_city'=>$customerCity,
					'customer_pin'=>$customerPin,
					'customer_email'=>$customerEmail,
					'customer_country'=>$customerCountry,
					'customer_phone'=>$customerPhone,
					'customer_state'=>$customerstate,
					'customer_id'=>$costomerId
					
	

				);
				$this->generateordermultitripxml( $data );
if(!empty($ship_id)){
    $this->load->helper('manifest_helper');
    $manifest = generatemanifestdoc($ship_id);
}
}
}
return $response;
}    

/* create XML for MultiTrip */
public function generateordermultitripxml($data)
{
    $date = date("Ymdhis");
    $request = '';
    $request .= '<eTNEDIMessage>';
    $request .= '<eTNEDITripHeader>';
    $request .= '<Version>1.0</Version>';
    $request .= '<UserName>eTrucknow</UserName>';
    $request .= '<Password>eTrucknow</Password>';
    $request.='<SenderTransmissionNo>'.$data['SenderTransmissionNo'].'_'.$date.'</SenderTransmissionNo>';
    $request .= '<AckSpec>';
    $request .= '<ComMethodGid>transmission</ComMethodGid>';
    $request .= '<ComType>';
    $request .= '<EmailAddress>dummy@email.com</EmailAddress>';
    $request .= '</ComType>';
    $request .= '<AckOption>SUCCESS</AckOption>';
    $request .= '</AckSpec>';
    $request .= '<SourceApp>eTrucknow</SourceApp>';
    $request .= '<DestinationApp>'.$data['branch_code'].'</DestinationApp>';
    $request .= '<ReferenceId>'.$data['SenderTransmissionNo'].'</ReferenceId>';
	$request .= '<Action>TripDetails</Action>';
  $request .= '</eTNEDITripHeader>'; 
  $request .= '<eTNEDIOrderBody>'; 
  $request .= '<EL3OrgDetails>'; 
  $request .= '<Companycode>' . $data['company_code'] . '</Companycode>';
  $request .= '<Branchcode>' . $data['branch_code'] . '</Branchcode>';
  $request .= '<Departmentcode></Departmentcode>';
  $request .= '<PhysicalReceiver/>'; 
  $request .= '<LogicalReceiver/>'; 
  $request .= '<PhysicalSender/>'; 
  $request .= '<LogicalSender/>'; 
  $request .= '</EL3OrgDetails>'; 
  $request .= '<TripHeader>'; 
  $request .= '<TripID>'.$data['trip_id'].'</TripID>'; 
  $request .= '<ExternalTripID/>'; 
  $request .= '<CarrierDetails>';  
  $request .= '<ID>'.$data['carcode'].'</ID>'; 
  $request .= '<Company>'; 
  $request .= '<Name>'.$data['carrier_name'].'</Name>'; 
  $request .= '<RegistrationNumber></RegistrationNumber>'; 
  $request .= '</Company>'; 
  $request .= '<Address>'; 
  $request .= '<FirstName>'.$data['carrier_name'].'</FirstName>'; 
  $request .= '<LastName>'.$data['carrier_name'].'</LastName>'; 
  $request .= '<Address1>'.$data['carrier_address'].'</Address1>'; 
  $request .= '<Address2>'.$data['carrier_address'].'</Address2>'; 
  $request .= '<Street></Street>'; 
  $request .= '<City></City>'; 
  $request .= '<State></State>'; 
  $request .= '<Postal>'.$data['carpincode'].'</Postal>'; 
  $request .= '<Country></Country>'; 
  $request .= '<ContactNo>'; 
  $request .= '<CountryCode>'.$data['country_code'].'</CountryCode>'; 
  $request .= '<ContactNo>'.$data['carrier_mobile'].'</ContactNo>'; 
  $request .= '<EmailAddress>'.$data['carrier_email'].'</EmailAddress>'; 
  $request .= '</ContactNo>'; 
  $request .= '</Address>';
  $request .= '</CarrierDetails>';  
  $request .= '</TripHeader>';  
  $request .= '<VehicleDetails>';  
  $request .= '<VehicleTypeCode>'.$data['vehicle_type'].'</VehicleTypeCode>';   
  $request .= '<VehicleModelCode>'.$data['vehicle_type'].'</VehicleModelCode>';   
  $request .= '<RegistrationNumber>'.$data['vehiclereg_number'].'</RegistrationNumber>';   
  $request .= '<License></License>';   
  $request .= '<ApplicableForDangerousGoods></ApplicableForDangerousGoods>';   
  $request .= '<Properties>';  
  $request .= '<Weight>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['truck_weight'].'</Max>';  
  $request .= '<UOM>'.$data['weight_unit'].'</UOM>';  
  $request .= '</Weight>'; 
  $request .= '<Volume>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['truck_volume'].'</Max>';  
  $request .= '<UOM>'.$data['truck_volume'].'</UOM>';  
  $request .= '</Volume>'; 
  $request .= '<Length>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['length'].'</Max>';  
  $request .= '<UOM>'.$data['length_unit'].'</UOM>';  
  $request .= '</Length>';
  $request .= '<Width>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['width'].'</Max>';  
  $request .= '<UOM>'.$data['width_unit'].'</UOM>';  
  $request .= '</Width>'; 
 $request .= '<Height>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['truckheight'].'</Max>';  
  $request .= '<UOM>'.$data['height_unit'].'</UOM>';  
  $request .= '</Height>';
 $request .= '<Distance>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</Distance>';
 $request .= '<DimensionGirth>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</DimensionGirth>';
 $request .= '<ShipmentUnit>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</ShipmentUnit>'; 
 $request .= '<PacketWeight>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</PacketWeight>';
 $request .= '<PacketVolume>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</PacketVolume>';  
  $request .= '</Properties>';  
  $request .= '</VehicleDetails>';  
  $request .= '<DriverDetails>';
  $request .= '<IDProof>';
  $request .= '<IDType>'.$data['address_proof_type'].'</IDType>';
  $request .= '<IDNumber>'.$data['address_proof_name'].'</IDNumber>';
  $request .= '</IDProof>';
  $request .= '<Address>';
  $request .= '<CompanyName>'.$data['driverName'].'</CompanyName>';
  $request .= '<Address1>'.$data['address_as_on_proof'].'</Address1>';
  $request .= '<Address2></Address2>';
  $request .= '<Street></Street>'; 
  $request .= '<City>'.$data['drivercity'].'</City>'; 
  $request .= '<State></State>';
  $request .= '<Postal></Postal>'; 
  $request .= '<Country></Country>'; 
  $request .= '<ContactNo>'; 
  $request .= '<CountryCode>'.$data['country_code'].'</CountryCode>'; 
  $request .= '<ContactNo>'.$data['drivermobile'].'</ContactNo>'; 
  $request .= '<EmailAddress></EmailAddress>'; 
  $request .= '</ContactNo>';  
  $request .= '</Address>'; 
  $request .= '</DriverDetails>'; 
	$request .= '<TripOrderDetails>';
	$i = 0;
        foreach ($data['ordersnw'] as $ordersdata){
			$i++;
	$request .= '<Orders>';
	 $request .= '<OrderSequence>'.$i.'</OrderSequence>';
	 $request .= '<OrderID>' . $ordersdata['order_id'] . '</OrderID>';
  $request .= '<EXTOrderID></EXTOrderID>';
  $request .= '<OrderType>' . $ordersdata['order_type'] . '</OrderType>';
  $modetrans = "FTL";
		if ( isset( $ordersdata['transport_mode'] ) ) {
			if ( $ordersdata['transport_mode'] == "TL" ) {
				$modetrans = "FTL";
			} else {
				$modetrans = "LTL";
			}
		}
		$request .= '<ModeOfTransport>' . $modetrans . '</ModeOfTransport>';
		$request .= '<Product>' . $ordersdata['product'] . '</Product>';
		$request .= '<ServiceType>' . $ordersdata['service'] . '</ServiceType>';
		$request .= '<TypeOfBusiness>DOMESTIC</TypeOfBusiness>';
  $request .= '<TermsOfTrade>'; 
  $request .= '<Incoterm>' . $ordersdata['incoterm'] . '</Incoterm>';
  $request .= '</TermsOfTrade>'; 
  $request .= '<LocationInfo>'; 
		$request .= '<PickUp>';
		$request .= '<Company>';
		$request .= '<Name>' . $ordersdata['pickup_company'] . '</Name>';
		$request .= '<ContactNo>' . $ordersdata['pickup_contact'] . '</ContactNo>';
		$request .= '</Company>';
		$request .= '<Address>';
		$request .= '<Country>' . $ordersdata['pickup_country'] . '</Country>';
		$request .= '<City>' . $ordersdata['pickup_city'] . '</City>';
		$request .= '<Postal>' . $ordersdata['pickup_pincode'] . '</Postal>';
		$request .= '<Address1>' . $ordersdata['pickup_address1'] . '</Address1>';
		$request .= '<Address2>' . $ordersdata['pickup_address2'] . '</Address2>';
		$request .= '</Address>';
		$request .= '<DateTime>';
		$request .= '<From>' . date( "Y-m-d", strtotime( $ordersdata['pickup_datetime'] ) ) . 'T' . date( "H:i:s", strtotime( $ordersdata['pickup_datetime'] ) ) . '.000</From>';
		$request .= '<To>' . date( "Y-m-d", strtotime( $ordersdata['pickup_endtime'] ) ) . 'T' . date( "H:i:s", strtotime( $ordersdata['pickup_endtime'] ) ) . '.000</To>';
		$request .= '</DateTime>';
		$request .= '</PickUp>';
		$request .= '<DropOff>';
		$request .= '<Company>';
		$request .= '<Name>' . $ordersdata['delivery_company'] . '</Name>';
		$request .= '<ContactNo>' . $ordersdata['delivery_contact'] . '</ContactNo>';
		$request .= '</Company>';
		$request .= '<Address>';
		$request .= '<Country>' . $ordersdata['delivery_country'] . '</Country>';
		$request .= '<City>' . $ordersdata['delivery_city'] . '</City>';
		$request .= '<Postal>' . $ordersdata['delivery_pincode'] . '</Postal>';
		$request .= '<Address1>' . $ordersdata['delivery_address1'] . '</Address1>';
		$request .= '<Address2>' . $ordersdata['delivery_address2'] . '</Address2>';
		$request .= '</Address>';
		$request .= '<DateTime>';
		$request .= '<From>' . date( "Y-m-d", strtotime( $ordersdata['delivery_datetime'] ) ) . 'T' . date( "H:i:s", strtotime( $ordersdata['delivery_datetime'] ) ) . '.000</From>';
		$request .= '<To>' . date( "Y-m-d", strtotime( $ordersdata['delivery_endtime'] ) ) . 'T' . date( "H:i:s", strtotime( $ordersdata['delivery_endtime'] ) ) . '.000</To>';
		$request .= '</DateTime>';
		$request .= '</DropOff>';
$request .= '</LocationInfo>'; 
      $request .= '<CargoSummary>';
      $request .= '<TotalQuantity>';
      $request .= '<Value>' . $ordersdata['quantity'] . '</Value>';
      $request .= '<UOM>Numbers</UOM>';  
      $request .= '</TotalQuantity>';
      $request .= '<TotalVolume>';
      $request .= '<Value>' . $ordersdata['volume'] . '</Value>';
      $request .= '<UOM>cbm</UOM>';  
      $request .= '</TotalVolume>';	
      $request .= '<TotalWeight>';
      $request .= '<Value>' . $ordersdata['weight'] . '</Value>';
      $request .= '<UOM>cbm</UOM>';  
      $request .= '</TotalWeight>';	  
      $request .= '</CargoSummary>';
  $request .= '<ValueAddedServices>';
  $request .= '<Addon>';
  $request .= '<AddonName></AddonName>';
  $request .= '<AddonCode></AddonCode>';
  $request .= '<Currency></Currency>';
  $request .= '<RateUnit></RateUnit>';
  $request .= '<AddonAmount></AddonAmount>';
  $request .= '<AddonQuantity></AddonQuantity>';
  $request .= '</Addon>';
  $request .= '</ValueAddedServices>';
  
		$request .= '<ManageReferences>';
		$request .= '<References>';
		$request .= '<RefType>';
		$request .= '<Code></Code>';
		$request .= '<Value></Value>';
		$request .= '</RefType>';
		$request .= '</References>';
		$request .= '</ManageReferences>';
		$request .= '<Remarks>';
		$request .= '<RemarkType>';
		$request .= '<Code></Code>';
		$request .= '<Value></Value>';
		$request .= '</RemarkType>';    
        $request .= '</Remarks>';
		$request .= '</Orders>';
		}

	$request .= '</TripOrderDetails>'; 	
  $request .= '</eTNEDIOrderBody>'; 
  $request .= '</eTNEDIMessage>';
  $resname=date("Ymdhis");
  $dom = new DOMDocument;
  $dom->preserveWhiteSpace = FALSE;
  $dom->loadXML($request);
  $dom->save('xml/TRIPKN'.$resname.'.xml');
  log_message("error","request-order ".$request);
  $serviceurl = BOOKING_ETRA_URL;
  $username = BOOKING_ETRA_USRNAME;
  $password = BOOKING_ETRA_PWD;
    /*$username = "ws_etra";
    $password = "4fh2drGs3n";*/
    $auth = base64_encode($username.':'.$password);
    $headers = array(
      'Content-Type: application/xml',
      'Authorization: Basic '. base64_encode("$username:$password")
  );
    $output = thirdpartyservicecurl($serviceurl,$headers,$request);
    log_message('error', "orderslogresponsexml " . json_encode($output));
}

public function triporderintoshipment()
{
    $response = 0;
    $userid = $this->session->userdata('user_id');
    $curtz = $this->session->userdata("usr_tzone")['timezone'];
    $logdate = date('Y-m-d H:i:s');
    $getactual = getdatetimebytimezone(DFLT_TZ,$logdate,$curtz);
    $curdt = $getactual['datetime'];
    $input = $this->input->post(null,true);
    /*$shipid = $input['ship_id'];*/
    /*trip id generation*/
    $input['user_id'] = $userid;
    $input['curtz'] = $curtz;
    $input['curdt'] = $curdt;
    $input['company_code'] = $this->session->userdata('company_code');
    $input['branch_code'] = $this->session->userdata('branch_code');
    $ordeschk = explode(",", $input['ordid']);
    if($input['carrierid'] ==  277 && !empty($input['vehicle_type']) && $input['company_code'] == 'AUKN'){
        $this->load->helper('bonds_helper');
        foreach($ordeschk as $ordid){
            $edi_res = bondsbooking(array('ordid'=>$ordid,'vehicle_type'=>$input['vehicle_type']));
            if($edi_res['status'] == 0){
                $orderinfo = $this->common->gettblrowdata(array('id'=>$ordid),'order_id','tb_orders',0,0);
                $error_msg = "Bonds Couriers Errors for order #".$orderinfo['order_id'];
                foreach($edi_res['errors'] as $error){
                    if(is_array($error)){
                        foreach($error as $er){
                            $error_msg .= '<p>'.$er.'</p>';
                        }
                    } else {
                        $error_msg .='<p>'.$error.'</p>';
                    }
                }
                $this->session->set_flashdata('edierror_msg',$error_msg);
                redirect('orders'); 
            }
        }
    }
    if(count($ordeschk)>1){
        if($input['trip_type'] == 'multi'){
            $response = $this->tripcreatemultiorder($input);
        }else{
             $getref = $this->db->query("SELECT GROUP_CONCAT(order_id) as orders FROM tb_order_references WHERE order_id IN(".$input['ordid'].") AND reference_id='ROT' AND status=1 GROUP BY ref_value");
            /*log_message("error","0th ".$this->db->last_query());*/
            if($getref->num_rows()>0){
                foreach($getref->result_array() as $reford){
                    $input['ordid'] = $reford['orders'];
                    $response = $this->tripcreatemultiorder($input);
                }
            }else{
                $response = $this->eachordercreatetrip($input);
            }
        }
    }else{
        $response = $this->eachordercreatetrip($input);
    }
    if($response == 1){
        $this->session->set_flashdata('success_msg','Trip Created Successfully.');
    }else if($response == 2){
        $this->session->set_flashdata('error_msg','Your selected orders are not in same root.(example: Ref. ID (ROT))');
    }else{
        $this->session->set_flashdata('error_msg','Oops..Something Went Wrong.');
    }
    redirect('orders');
}

public function eachordercreatetrip($ordinput){
    $response = 0;
    $userid = $ordinput['user_id'];
    $year=date('y');
    $week=date('W');
    $curtz = $ordinput['curtz'];
    $curdt = date('Y-m-d H:i:s');
    $count_code=substr($ordinput['company_code'], 0, 2);
    $ordeschk = explode(",", $ordinput['ordid']);
    foreach($ordeschk as $chkord){
        $input['ordid'] = $chkord;
        $where = array("id"=>$input['ordid']);
        $select = "*";
        $table = "tb_orders";
        $order = $this->common->gettblrowdata($where,$select,$table,0,0);
        $seq_num=000001;
        if(count($order)>0){
            if($order['shift_id']!=0){
             $this->session->set_flashdata('error_msg','Trip already created for this order. Please check details.');
             redirect('orders');
         }
         $order_nm=$order['order_id'];
         $seq_num=substr($order_nm, -6);
     }
     $shipid = "T".$count_code.$week.$year.$seq_num;
        $vendor_id=$ordinput['carrierid'];
        $vehicle_id = isset($ordinput['tvehnum']) ? $ordinput['tvehnum'] : "";
        $driver_id = isset($ordinput['tdrivernum']) ? $ordinput['tdrivernum'] : "";
        $driver_trip_id = isset($ordinput['vehicle_id']) ? $ordinput['vehicle_id'] : "";
        $tpvehicle_type=isset($ordinput['vehicle_type']) ? $ordinput['vehicle_type'] : 0;
        $carrier_instructions = isset($ordinput['carrier_instructions']) ? $ordinput['carrier_instructions'] : "";
        $vehicle_type = "";
        if($shipid != "" && $vendor_id > 0 && $input['ordid'] >0){
            $sid = $input['ordid'];
            if(count($order)>0){
                $shift_idchk = $order['shift_id'];
                if($shift_idchk == 0){
                    $pickup = $order['pickup_address1'];
                    if($pickup == ""){ $pickup = $order['pickup_city']; }
                    $delivery = $order['delivery_address1'];
                    if($delivery == ""){ $delivery = $order['delivery_city']; }
                    $address = $dropaddr = "";
                    if($order['pickup_address1'] != ""){
                        $address .= $order['pickup_address1'];
                        $dropaddr .= $order['delivery_address1'];
                    }
                    if($order['pickup_company'] != ""){
                        $address .= ", ".$order['pickup_company'];
                        $dropaddr .= ", ".$order['delivery_company'];
                    }
                    if($order['pickup_country'] != ""){
                        $address .= ", ".$order['pickup_country'];
                        $dropaddr .= ", ".$order['delivery_country'];
                    }
                    if($order['pickup_city'] != ""){
                        $address .= ", ".$order['pickup_city'];
                        $dropaddr .= ", ".$order['delivery_city'];
                    }
                    if($order['pickup_pincode'] != ""){
                        $address .= ", ".$order['pickup_pincode'];
                        $dropaddr .= ", ".$order['delivery_pincode'];
                    }
                    $plat = $order['plat'];
                    $plng = $order['plng'];
                    $dlat = $order['dlat'];
                    $dlng = $order['dlng'];
                    $where = array("shipmentid"=>$shipid);
                    $select = "id,splace,slat,slng,eplace,elat,elng,vendor_id,weight,volume,units,vehicle_type";
                    $table = "tb_shifts";
                    $shipment = $this->common->gettblrowdata($where,$select,$table,0,0);
                    if(count($shipment)==0){
                        $splace = $order['pickup_address1'];
                        $eplace = $order['delivery_address1'];
                        $pickup_city = $order['pickup_city'];
                        $delivery_city = $order['delivery_city'];
                        $customer_id = $order['customer_id'];
                        $transport_mode= $order['transport_mode'];
                        $weight= $order['weight'];
                        $volume= $order['volume'];
                        $txnid = $shipid;
                        if($tpvehicle_type!='' && $tpvehicle_type != 0){
                            $vtwhere = array("id"=>$tpvehicle_type);
                            $vtselect = "trucktype";
                            $vttable = "tb_trucktypes";
                            $vtorder = $this->common->gettblrowdata($vtwhere,$vtselect,$vttable,0,0);
                            if(count($vtorder)>0){
                                $vehicle_type = $vtorder['trucktype'];
                            }else{
                                $vehicle_type = $order['vehicle_type'];
                            }
                        }else{
                            $vehicle_type = $order['vehicle_type'];
                        }
                        $startdate = $order['pickup_datetime'];
                        $starttime = date('H:i',strtotime($startdate));
                        $enddate = $order['delivery_datetime'];
                        $endtime = date('H:i',strtotime($enddate));
                       /* $getactual = getdatetimebytimezone(DFLT_TZ,$enddate,$curtz);
                       $enddate = $getactual['datetime'];*/
                       $insarr = array('user_id'=>$userid, 'stime'=>$starttime, 'etime'=>$endtime, 'splace'=>$order['pickup_city'], 'slat'=>$plat, 'slng'=>$plng, 'eplace'=>$order['delivery_city'], 'elat'=>$dlat, 'elng'=>$dlng, 'scity'=>$order['pickup_city'], 'dcity'=>$order['delivery_city'], 'zone_id'=>1, 'empshift_start'=>$starttime, 'empshift_end'=>$endtime, 'trip_type'=>0, 'startdate'=>$startdate, 'enddate'=>$enddate, 'shipment_name'=>'Boxes', 'shipment_id'=>0, 'transport_mode'=>$transport_mode, 'customer_id'=>$customer_id, 'vendor_id'=>$vendor_id, 'carrier_type'=>0, 'shipmentid'=>$shipid, 'txnid'=>$txnid,'weight'=>$weight, 'volume'=>$volume, 'units'=>$order['quantity'],'domainname'=>'INFD', 'vehicle_type'=>$vehicle_type,'company_code'=>$order['company_code'],'branch_code'=>$order['branch_code'],'carrier_instructions'=>$carrier_instructions, 'status'=>1);
                       $ship_id = $this->common->insertTableData("tb_shifts", $insarr);
                   }else{
                      $ship_id = $shipment['id'];
                     
                  }
                  /*update order table*/
                  $upddt = array('vendor_id'=>$vendor_id,'shift_id'=>$ship_id,"shipmentid"=>$shipid);
                  $updwhr = array("id"=>$input['ordid']);
                  $upd = $this->common->updatetbledata("tb_orders",$upddt,$updwhr);
                  $this->ordernotify('trip_create',$input['ordid']);
                  $this->load->helper('manifest_helper');
                    $manifest = generatemanifestdoc($ship_id);
                  $capacity = $order['weight'];
                  if($capacity == ""){
                    $capacity = "0";
                }
                /*update orders table*/
                $updwhr = array('id'=>$input['ordid']);
                $setwhr = array('status'=>2);
                $ins = $this->db->where($updwhr)->update("tb_orders",$setwhr);
                /*insert stops records */
                $where = array("shipment_id"=>$ship_id,"stopcity"=>$order['pickup_city'],"stoptype"=>"P");
                $select = "id";
                $table = "tb_shiporder_stops";
                $chkstop = $this->common->gettblrowdata($where,$select,$table,0,0);
                if(count($chkstop) == 0){
                    $insarr = array('stopname'=>$order['pickup_city'], 'plat'=>$plat, 'plng'=>$plng, 'stopcity'=>$order['pickup_city'], 'address'=>$address, 'stoptype'=>'P', 'stopstatus'=>0, 'shipment_id'=>$ship_id, 'ordernumber'=>1, 'startdate'=>$order['pickup_datetime'], 'enddate'=>$order['delivery_datetime'], 'weight'=>$order['weight'], 'volume'=>$order['volume'], 'shipmentstopid'=>0, 'ship_units'=>$order['quantity'], 'txncode'=>'NP', 'status'=>1, 'created_on'=>$curdt);
                    $ins = $this->db->insert("tb_shiporder_stops",$insarr);
                    $pickupstop_id = $this->db->insert_id();
                }else{
                    $pickupstop_id = $chkstop['id'];
                }
                $where = array("shipment_id"=>$ship_id,"stopcity"=>$order['delivery_city'],"stoptype"=>"D");
                $select = "id";
                $table = "tb_shiporder_stops";
                $chkstop = $this->common->gettblrowdata($where,$select,$table,0,0);
                if(count($chkstop) == 0){
                    $insarr = array('stopname'=>$order['delivery_city'], 'plat'=>$dlat, 'plng'=>$dlng, 'stopcity'=>$order['delivery_city'], 'address'=>$dropaddr, 'stoptype'=>'D', 'stopstatus'=>0, 'shipment_id'=>$ship_id, 'ordernumber'=>2, 'startdate'=>$order['delivery_datetime'], 'enddate'=>$order['delivery_datetime'], 'weight'=>$order['weight'], 'volume'=>$order['volume'], 'shipmentstopid'=>0, 'ship_units'=>$order['quantity'], 'txncode'=>'NP', 'status'=>1, 'created_on'=>$curdt);
                    $ins = $this->db->insert("tb_shiporder_stops",$insarr);
                    $dropstop_id = $this->db->insert_id();
                }else{
                    $dropstop_id = $chkstop['id'];
                }
                /*add pickupstop and drop stop id into details/tb_employee table */
                $where = array('stop_id'=>$pickupstop_id, 'drop_stopid'=>$dropstop_id,'order_id'=>$order['order_id'], 'shift_id'=>$ship_id);
                $select = "id";
                $table = "tb_employee";
                $chkemp = $this->common->gettblrowdata($where,$select,$table,0,0);
                if(count($chkstop) == 0){
                    $insarr = array('assoc_id'=>$shipid, 'pickup'=>$pickup, 'plat'=>$plat,
                        'plng'=>$plng, 'drop'=>$delivery,'dlat'=>$dlat, 'dlng'=>$dlng, 'pickup_city'=>$order['pickup_city'], 'drop_city'=>$order['delivery_city'], 'pickup_datetime'=>$order['pickup_datetime'], 'drop_datetime'=>$order['delivery_datetime'], 'name'=>'Boxes', 'phone'=>$order['customer_phone'], 'address'=>$address,'emailid'=>$order['customer_email'], 'user_id'=>$userid, 'status'=>1, 'createdon'=>$curdt, 'material_id'=>0,
                        'capacity'=>$capacity, 'information'=>$order['customer_comments'], 'shipment_weight'=>$order['weight'],
                        'shipment_volume'=>$order['volume'], 'ship_type'=>'P', 'customer_id'=>$order['customer_id'], 'vendor_id'=>$vendor_id, 'shipment_id'=>0, 'startdate'=>$order['pickup_datetime'], 'enddate'=>$order['delivery_datetime'], 'shift_id'=>$ship_id, 'stop_order'=>1, 'drop_order'=>0, 'basic_stop'=>0, 'stop_id'=>$pickupstop_id, 'drop_stopid'=>$dropstop_id, 'order_id'=>$order['order_id'],'pkgitemid'=>'BOXES', 'no_of_pkgs'=>$order['quantity'], 'domainname'=>'INFD');
                    $detail_id = $this->common->insertTableData("tb_employee",$insarr);
                }else{
                    $detail_id = $chkemp['id'];
                }
                $stops_units = $this->db->query("SELECT oc.id,oc.quantity,oc.width,oc.height,oc.length,oc.weight,im.unit_name FROM tb_order_cargodetails oc,tbl_shipunit_types im WHERE oc.order_id = '$sid' AND im.id = oc.handling_unit AND oc.status='1'");
                if($stops_units->num_rows()>1){
                    $odata=$stops_units->row();
                    $upd = $this->db->query("UPDATE tb_order_cargodetails set stop_detail_id='$detail_id' WHERE id = '".$odata->id."'");
                }
                $chk1 = $this->db->select("id")->get_where("tb_shft_veh",array("shft_id"=>$ship_id),1,0);
                if($chk1->num_rows() > 0){
                    $shftvehid = $chk1->row()->id;
                    $chk = $this->db->select("id")->get_where("tb_shft_veh_emp",array("shft_veh_id"=>$shftvehid,"status"=>1),1,0);
                    if($chk->num_rows() == 0){
                      $getemp = $this->db->select("id,pickup_datetime,drop_datetime")->get_where("tb_employee",array("shift_id"=>$ship_id));
                      if($getemp->num_rows()>0){
                        $pri = 1;
                        foreach($getemp->result() as $gt){
                            $insveh1 = array("user_id"=>$userid,"shft_veh_id"=>$shftvehid,"emp_id"=>$gt->id,"priority"=>$pri,"pickup_time"=>$gt->pickup_datetime, 'created_on'=>$curdt,'updated_on'=>$curdt,"status"=>1,"drop_time"=>$gt->drop_datetime);
                            $ins = $this->common->insertTableData("tb_shft_veh_emp",$insveh1);
                            $pri++;
                        }
                    }
                }else{
                    $chkemp = $this->db->select("id")->get_where("tb_shft_veh_emp",array("shft_veh_id"=>$shftvehid,"emp_id"=>$detail_id,"status"=>1),1,0);
                    if($chkemp->num_rows() == 0){
                        $insveh1 = array("user_id"=>$userid,"shft_veh_id"=>$shftvehid,"emp_id"=>$detail_id,"priority"=>3,"pickup_time"=>$order['pickup_datetime'], 'created_on'=>$curdt,'updated_on'=>$curdt,"status"=>1,"drop_time"=>$order['delivery_datetime']);
                        $ins = $this->common->insertTableData("tb_shft_veh_emp",$insveh1);
                    }
                }
            }else{
                if($vehicle_id != ""){
                    $veh_num = isset($ordinput['vehicle_id']) ? $ordinput['vehicle_id'] : "";
                    $arr = array('user_id'=>$userid, 'route_id'=>0, 'shft_id'=>$ship_id, 'carrier_id'=>$vendor_id, 'vehicle_id'=>$vehicle_id, 'register_number'=>$veh_num, 'created_on'=>$curdt,'updated_on'=>$curdt, 'status'=>1);
                    $shftvehid = $this->common->insertTableData("tb_shft_veh",$arr);
                    $getemp = $this->db->select("id,pickup_datetime,drop_datetime")->get_where("tb_employee",array("shift_id"=>$ship_id));
                    if($getemp->num_rows()>0){
                        $pri = 1;
                        foreach($getemp->result() as $gt){
                          $insveh1 = array("user_id"=>$userid,"shft_veh_id"=>$shftvehid,"emp_id"=>$gt->id,"priority"=>$pri,"pickup_time"=>$gt->pickup_datetime, 'created_on'=>$curdt,'updated_on'=>$curdt,"status"=>1,"drop_time"=>$gt->drop_datetime);
                          $ins = $this->common->insertTableData("tb_shft_veh_emp",$insveh1);
                          $pri++;
                      }
                  }
              }
          }
          if($vehicle_id != "" && $driver_id != ""){
            $chk = $this->db->select("id,driver_id,status")->order_by("id","DESC")->get_where("tb_vehicles_drivers",array("vehicle_id"=>$vehicle_id));
            if($chk->num_rows()>0){
                if($driver_id == $chk->row()->driver_id){
                    $upd = $this->db->where(array("id"=>$chk->row()->id))->update("tb_vehicles_drivers",array("status"=>1));
                }else{
                    $arr = array('vehicle_id'=>$vehicle_id, 'driver_id'=>$driver_id, 'imei'=>"", 'status'=>1, 'createdon'=>$curdt, 'updatedon'=>$curdt);
                    $ins = $this->common->insertTableData("tb_vehicles_drivers",$arr);
                }
            }else{
                $arr = array('vehicle_id'=>$vehicle_id, 'driver_id'=>$driver_id, 'imei'=>"", 'status'=>1, 'createdon'=>$curdt, 'updatedon'=>$curdt);
                $ins = $this->common->insertTableData("tb_vehicles_drivers",$arr);
            }
            $chk11 = $this->db->select("id")->get_where("tbl_assigned_drivers",array("driver_id"=>$driver_id,"vehicle_id"=>$vehicle_id),1,0);
            if($chk11->num_rows()>0){
                $upd = $this->db->where(array("id"=>$chk11->row()->id))->update("tbl_assigned_drivers",array("status"=>"1",'updated_on'=>$curdt));
            }else{
                $chk111 = $this->db->select("id")->get_where("tbl_assigned_drivers",array("driver_id <>"=>$driver_id,"vehicle_id"=>$vehicle_id,"status"=>1),1,0);
                if($chk111->num_rows() == 0){
                  $ins = $this->common->insertTableData("tbl_assigned_drivers",array("vehicle_id"=>$vehicle_id,"user_id"=>$userid,"driver_id"=>$driver_id,"mobile_no"=>$driver_id,"imei"=>$imei,"from_time"=>$curdt,"to_time"=>$curdt,"status"=>"1",'created_on'=>$curdt));
              }else{
                  $upd = $this->db->where(array("id"=>$chk111->row()->id))->update("tbl_assigned_drivers",array("driver_id"=>$driver_id,'updated_on'=>$curdt));
              }
          }
      }
      $response = 1;
      /* update status */
      $chk = $this->db->select("id")->get_where("tb_stop_status",array("shipment_id"=>$ship_id,"status_id"=>9),1,0);
      if($chk->num_rows() == 0){
        $curdt1 = $ordinput['curdt'];
        $ins = array("shipment_id"=>$ship_id,"stop_id"=>0,"stop_detail_id"=>0,"stop_type"=>"","trip_id"=>0,"status_id"=>9,"status"=>1,"status_code"=>"0100","reason"=>"Coming from E-Booking","createdon"=>$curdt1);
        $insqry = $this->common->insertTableData("tb_stop_status",$ins);
    }
	 $chkCarrier = $this->db->select("*")->get_where("tb_vendors",array("id"=>$ordinput['carrierid']),1,0);
        if($chkCarrier->num_rows()>0){
                $carName = $chkCarrier->row()->name;
                $carMail = $chkCarrier->row()->email;
                $carmobile = $chkCarrier->row()->mobile;
                $caraddress = $chkCarrier->row()->address;
                $carpincode = $chkCarrier->row()->pincode;
                $carcountry = $chkCarrier->row()->country;
                $carcode= $chkCarrier->row()->code;
			}else{
				$carpincode="";
				$carcode= "";
			}
		if($ordinput['tvehnum']!=''){
			 $chkTruckdata = $this->db->select("*")->get_where("tb_trucks_data",array("id"=>$ordinput['tvehnum']),1,0);
        if($chkTruckdata->num_rows()>0){
                $truck_weight = $chkTruckdata->row()->truck_weight;
                $weight_unit = $chkTruckdata->row()->weight_unit;
                $truck_volume = $chkTruckdata->row()->truck_volume;
                $volume_unit = $chkTruckdata->row()->volume_unit;
                $truckheight = $chkTruckdata->row()->height;
                $height_unit = $chkTruckdata->row()->height_unit;
                $breadth = $chkTruckdata->row()->breadth;
                $breadth_unit = $chkTruckdata->row()->breadth_unit;
                $length = $chkTruckdata->row()->length;
                $length_unit = $chkTruckdata->row()->length_unit;
   
			}
		}else{
			$truck_weight='';
			$weight_unit='';
			$truck_volume='';
			$volume_unit = '';
			$truckheight = '';
			$height_unit = '';
			$breadth = '';
			$breadth_unit = '';
			$length = '';
			$length_unit = '';
			$veh_num = '';
		}	
	   if($driver_trip_id!=''){
		 $chkDriver = $this->db->select("*")->get_where("tb_truck_drivers",array("id"=>$driver_trip_id),1,0);
        if($chkDriver->num_rows()>0){
                $driverName = $chkDriver->row()->name;
                $drivermobile = $chkDriver->row()->contact_num;
                $driving_licence_num = $chkDriver->row()->driving_licence_num;
                $drivercity = $chkDriver->row()->city;
                $address_proof_type = $chkDriver->row()->address_proof_type;
                $address_as_on_proof = $chkDriver->row()->address_as_on_proof;
                $address_proof_name = $chkDriver->row()->address_proof_name;

			}  
	   }else{
		   $driverName='';
		   $drivermobile='';
		   $driving_licence_num='';
		   $drivercity='';
		   $address_proof_type='';
		   $address_as_on_proof='';
		   $address_proof_name='';
	   }
		 
	$country_code            = $this->session->userdata( "usr_tzone" )['phone_code'];
	$chkorder     = $this->Order->getordertoedit( $input['ordid'] );
	if ( $chkorder->num_rows() > 0 ) {
		$department_code = $chkorder->row()->department_code;
		$oquantity = $chkorder->row()->quantity;
		$ovolume= $chkorder->row()->volume;
		$owight=$chkorder->row()->weight;
		$tripsts = "Pending";
        $order_status = "PENDING";
     
            $status = $chkorder->row()->status;
            $trip_sts = $chkorder->row()->trip_sts;
            $trip_id = $chkorder->row()->trip_id;
            if($trip_id != 0 && $trip_sts == 0){
                $order_status = 'ACTIVE';
                $tripsts = 'ACTIVE';
            }
            if($trip_id != 0 && $trip_sts == 1){
                $order_status = 'ACTIVE';
                $tripsts = 'ACTIVE';
            } 
            $curtz = $this->session->userdata("usr_tzone")['timezone'];
            $createdon = $chkorder->row()->createdon;
            $updatedon = $chkorder->row()->updatedon;
			$cdate = date('Y-m-d H:i:s');
        //  $getactual = getdatetimebytimezone($curtz,$createdon,DFLT_TZ);
            $curdt = $cdate;
            /* $curdt = $getactual['datetime'];*/
        //  $getactual = getdatetimebytimezone($curtz,$updatedon,DFLT_TZ);
            $upddt = $cdate;
            /* $upddt = $getactual['datetime'];*/
            $parties = array();
            $vendor_id = $chkorder->row()->vendor_id;
            if($vendor_id != 0){
              $getvendor = $this->db->query("SELECT name,mobile,location,address,pincode,country,email,code FROM tb_vendors where id ='".$vendor_id."'");
              if($getvendor->num_rows() >0){
                $vendordetails = array('name'=>$getvendor->row()->name,'party_id'=>$getvendor->row()->code,'address'=>$getvendor->row()->address,'pincode'=>$getvendor->row()->pincode,'country'=>$getvendor->row()->country,'street'=>"",'city'=>$getvendor->row()->location,'state'=>"",'phone'=>$getvendor->row()->mobile,'email'=>$getvendor->row()->email);
            }
        }
        $pickup_id = $chkorder->row()->customer_id; 
        $getpickupdetails = $this->db->query("SELECT name,phone,state,street,location,pincode, address, email_id, code,country FROM tb_customers WHERE id='".$pickup_id."' AND status=1 LIMIT 1");
        if($getpickupdetails->num_rows() >0){
          $customerdetails = array('name'=>$getpickupdetails->row()->name,'party_id'=>$getpickupdetails->row()->code,'address'=>$getpickupdetails->row()->address,'pincode'=>$getpickupdetails->row()->pincode,'country'=>$getpickupdetails->row()->country,'street'=>$getpickupdetails->row()->street,'city'=>$getpickupdetails->row()->location,'state'=>$getpickupdetails->row()->state,'phone'=>$getpickupdetails->row()->phone,'email'=>$getpickupdetails->row()->email_id);
		   $costomerId=$customerdetails['party_id'];
	  $customerName=$customerdetails['name'];
	  $customerAddress=$customerdetails['street'];
	  $customerCity=$customerdetails['city'];
	  $customerPin=$customerdetails['pincode'];
	  $customerAddr2=$customerdetails['address'];
	  $customerEmail=$customerdetails['email'];
	  $customerCountry=$customerdetails['country'];
	  $customerPhone=$customerdetails['phone'];
	  $customerstate=$customerdetails['state'];
      }
      $consignee_mobile = $consignor_mobile = $shipper_mobile = $carrier_mobile = "";
      $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name, p.mobile, p.email,p.code, p.location_id, p.address, p.country,p.state, p.street, p.pincode, o.party_type, a.party_master_id, a.location_id as plocation_id,a.street as pstreet,a.state as pstate,a.address as paddress,a.pincode as ppincode,a.country as pcountry FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1 LEFT JOIN tbl_orderparty_address a ON o.party_id=a.party_master_id AND o.order_id=a.order_id AND a.status=1 WHERE p.status=1 AND o.order_id='".$input['ordid']."' GROUP BY o.party_type");
      if($chekparty->num_rows()>0){
          foreach($chekparty->result() as $rr){
            $pdetail = array();
            $ptype = $rr->party_type;
            $chktype = $this->db->select("name")->get_where("tbl_party_types",array("id"=>$ptype),1,0);
            if($chktype->num_rows()>0){
              if($rr->party_master_id != ""){
                $pdetail = array('name'=>$rr->name,'party_id'=>$rr->code,'address'=>$rr->paddress,'pincode'=>$rr->ppincode,'country'=>$rr->pcountry,'street'=>$rr->pstreet,'city'=>$rr->plocation_id,'state'=>$rr->pstate,'phone'=>$rr->mobile,'email'=>$rr->email);
            }else{
                $pdetail = array('name'=>$rr->name,'party_id'=>$rr->code,'address'=>$rr->address,'pincode'=>$rr->pincode,'country'=>$rr->country,'street'=>$rr->street,'city'=>$rr->location_id,'state'=>$rr->state,'phone'=>$rr->mobile,'email'=>$rr->email);
            }
            if($chktype->row()->name == "Consignee"){
                $pdetail['type'] = "Consignee";
                $consignee_mobile = $rr->mobile;
            }
              /*if($chktype->row()->name == "CUSTOMER"){
                $pdetail['type'] = "Customer";
            }*/
            if($chktype->row()->name == "Consignor"){
                $pdetail['type'] = "Consignor";
                $consignor_mobile = $rr->mobile;
            }
              /*if($chktype->row()->name == "FREIGHT_PAYER"){
                $pdetail['type'] = "FREIGHT_PAYER";
            }*/
            if($chktype->row()->name == "Shipper"){
                $pdetail['type'] = "Shipper";
                $shipper_mobile = $rr->mobile;
            }
            if($chktype->row()->name == "Carrier"){
                $pdetail['type'] = "Carrier";
                $carrier_mobile = $rr->mobile;
            }
            $parties[] = $pdetail;
        }
    }
}
$code = "";
$areacodes = array("1","2","3","10","11","12","673","653","645","644","635","629","628","627","624","6","5","4","29","28","27","26","25","24","23","22","21","20","19","18","17","16","15","14","13","675","676","677","678","7","8","9");
$zonecode = $this->session->userdata("usr_tzone");
$currency = isset($zonecode['currency']) ? $zonecode['currency'] : "SGD";
$company_code = $this->session->userdata("company_code");
$branch_code = $this->session->userdata("branch_code");
if(isset($zonecode['phone_code'])){
  $code = $zonecode['phone_code'];
}else{
  if($company_code == "THKN"){
    $code = "66";
}
if($company_code == "SGKN"){
    $code = "65";
}
if($company_code == "INKN"){
    $code = "91";
}
}
$cargos = array();
$total_volume = $total_weight = 0;
$getrefrnceid = $this->db->query("select reference_id,ref_value from tb_order_references where order_id ='".$input['ordid']."'");
$refrenceId=$getrefrnceid->row()->reference_id;
$ref_value=$getrefrnceid->row()->ref_value;

$valueAddser = $this->db->query("SELECT oc.quantity,im.vas_id,im.vas_name FROM tb_order_vas oc,tb_vas_master im WHERE oc.order_id = '".$input['ordid']."' AND im.id = oc.vas_id");
if($valueAddser->num_rows()>0){
	$Vasquantity = $res->quantity;
	$vas_name = $res->vas_name;
	$vas_id = $res->vas_id;
}else{
	$Vasquantity = "";
	$vas_name = "";
	$vas_id = "";
}

$getcargos = $this->db->query("select handling_unit,length,width,height,weight,volume,quantity,quantity_type,cargo_content from tb_order_cargodetails where order_id ='".$input['ordid']."'");
if($getcargos->num_rows() >0){
  foreach($getcargos->result() as $res){
    $volume = $res->volume;
    $weight = $res->weight;
    if($volume == ""){ $volume = 1; }
    if($weight == ""){ $weight = 1; }
    $cargos[] = array('cargo_type'=>$res->quantity_type,'content'=>$res->cargo_content,'length'=>$res->length,'width'=>$res->width,'height'=>$res->height,'weight'=>$weight,'volume'=>$volume,'quantity'=>$res->quantity);
    $total_volume += $volume;
    $total_weight += $weight;
}
}
if($chkorder->row()->company_code != ""){
  $company_code = $chkorder->row()->company_code;
}
if($chkorder->row()->branch_code != ""){
  $branch_code = $chkorder->row()->branch_code;
}
$shipperdetail = $vendordetails = $consignedetail = $consignordetail = $pfdetails = $customerdetails = array();
$cncontact = $consignee_mobile;
$cacontact = $consignor_mobile;
if($cacontact == ""){
  $cacontact = $shipper_mobile;
}
	$order_type=$chkorder->row()->order_type;
	$product=$chkorder->row()->product;
	$service=$chkorder->row()->service;
	$incoterm=$chkorder->row()->incoterm;
$getOrdertype = $this->db->query("SELECT type_name FROM tb_order_types WHERE status ='1' and id='".$order_type."'");
$orderType=$getOrdertype->row()->type_name;

$getServicetype = $this->db->query("SELECT name FROM tb_service_master WHERE status ='1' and id='".$service."'");
$orderService=$getServicetype->row()->name;
		
	}
	$curtz = $this->session->userdata("usr_tzone")['timezone'];
$hrs = $this->session->userdata("usr_tzone")['hrs'];
	$data = array(
					'SenderTransmissionNo' => $order_nm,
					'order_id' => $input['ordid'],
					'pickup_datetime'      => $order['pickup_datetime'],
					'delivery_datetime'    => $order['delivery_datetime'],
					'pickup_endtime'       => $order['pickup_endtime'],
					'delivery_endtime'     => $order['drop_endtime'],
					'createdon'            => $curdt,
					"delivery_company"     => $order['delivery_company'],
					'delivery_city'        => $order['delivery_city'],
					'delivery_country'     => $order['delivery_country'],
					'delivery_address1'    => $order['delivery_address1'],
					'delivery_address2'    => $order['delivery_address2'],
					'delivery_pincode'     => $order['delivery_pincode'],
					'branch_code'          => $order['branch_code'],
					'company_code'         => $order['company_code'],
					'department_code'      => $department_code,
					'transport_mode'       => $order['transport_mode'],
					'pickup_country'       => $order['pickup_country'],
					'pickup_pincode'       => $order['pickup_pincode'],					
					"pickup_company"       => $order['pickup_company'],				
					'pickup_city'          => $order['pickup_city'],
					'pickup_address1'      => $order['pickup_address1'],
					'pickup_address2'      => $order['pickup_address2'],					
					"goods_value"          => $order['goods_value'],
					"trip_id"           => $txnid,
					"carrier_id"           => $carcode,
					"carrier_name"           => $carName,
					"carrier_email"           => $carMail,
					"carrier_mobile"           => $carmobile,
					"carrier_address"           => $caraddress,
					"carrier_pincode"           => $carpincode,
					"carrier_country"           => $carcountry,
					"carrier_code"           => $carcode,
					"vehiclereg_number"           => $veh_num,
					"vehicle_type"           => $vehicle_type,
					"truck_weight"           => $truck_weight,
					"weight_unit"           => $weight_unit,
					"truck_volume"           => $truck_volume,
					"volume_unit"           => $volume_unit,
					"truckheight"           => $truckheight,
					"height_unit"           => $height_unit,
					"length"           => $length,
					"length_unit"           => $length_unit,
					"width"                 => $breadth,
					"width_unit"           => $breadth_unit,
					"driverName"           => $driverName,
					"drivermobile"           => $drivermobile,
					"driving_licence_num"           => $driving_licence_num,
					"drivercity"           => $drivercity,
					"address_proof_name"           => $address_proof_name,
					"address_as_on_proof"           => $address_as_on_proof,
					"address_proof_type"           => $address_proof_type,
					"country_code"            => $country_code,
					"owight"            => $owight,
					"ovolume"            => $ovolume,
					"oquantity"            => $oquantity,
					"cargos"            => $cargos,
					"parties"            => $parties,
					"refrenceId"            => $refrenceId,
					"ref_value"            => $ref_value,
					"total_weight"            => $total_weight,
					"total_volume"            => $total_volume,
					"ststime"            => $upddt,
					"area_code"            => $areacodes[1],
					"currency"            => $currency,
					"pickup_contact"            => $cacontact,
					"delivery_contact"            => $cncontact,
					"order_type"            => $orderType,
					"product"            => $product,
					"service"            => $orderService,
					"incoterm"            => $incoterm,
					"vasquantity"            => $Vasquantity,
					"vas_name"            => $vas_name,
					"vas_id"            => $vas_id,
					'customer_name'=>$customerName,
					'customer_add1'=>$customerAddress,
					'customer_add2'=>$customerAddr2,
					'customer_city'=>$customerCity,
					'customer_pin'=>$customerPin,
					'customer_email'=>$customerEmail,
					'customer_country'=>$customerCountry,
					'customer_phone'=>$customerPhone,
					'customer_state'=>$customerstate,
					'customer_id'=>$costomerId,
					'curtz'=>$curtz,
					'hrs'=>$hrs,
					

				);
				$this->generateordertripxml( $data );
				
	
    /*send email notification*/
    if($vendor_id != ""){
      
        /*spoton code starts here*/
        if($ordinput['company_code'] == 'INKN' && $vendor_id=='164'){
            $this->getconinfo($input['ordid']); 
        }
        /*spoton code ends here*/
        /*Vendor Maruthi Api Condition */
        if($ordinput['company_code'] == 'INKN' && $vendor_id == 194) {
            $this->GetOrderIntoapi($order['order_id']);
        }  
       
        /*end  Vendor Maruthi Api Condition*/
    }
   
  }
}
}
}
return $response;
}
/* create XML for Trip */
public function generateordertripxml($data)
{
    $date = date("Ymdhis");
    $request .= '<eTNEDIMessage>';
    $request .= '<eTNEDITripHeader>';
    $request .= '<Version>1.0</Version>';
    $request .= '<UserName>eTrucknow</UserName>';
    $request .= '<Password>eTrucknow</Password>';
    $request.='<SenderTransmissionNo>'.$data['SenderTransmissionNo'].'_'.$date.'</SenderTransmissionNo>';
    $request .= '<AckSpec>';
    $request .= '<ComMethodGid>transmission</ComMethodGid>';
    $request .= '<ComType>';
    $request .= '<EmailAddress>dummy@email.com</EmailAddress>';
    $request .= '</ComType>';
    $request .= '<AckOption>SUCCESS</AckOption>';
    $request .= '</AckSpec>';
    $request .= '<SourceApp>eTrucknow</SourceApp>';
    $request .= '<DestinationApp>'.$data['branch_code'].'</DestinationApp>';
    $request .= '<ReferenceId>'.$data['SenderTransmissionNo'].'</ReferenceId>';
	$request .= '<Action>TripDetails</Action>';
  $request .= '</eTNEDITripHeader>'; 
  $request .= '<eTNEDITripBody>'; 
  $request .= '<EL3OrgDetails>'; 
  $request .= '<Companycode>' . $data['company_code'] . '</Companycode>';
  $request .= '<Branchcode>' . $data['branch_code'] . '</Branchcode>';
  $request .= '<Departmentcode>' . $data['department_code'] . '</Departmentcode>';
  $request .= '<PhysicalReceiver/>'; 
  $request .= '<LogicalReceiver/>'; 
  $request .= '<PhysicalSender/>'; 
  $request .= '<LogicalSender/>'; 
  $request .= '</EL3OrgDetails>'; 
  $request .= '<TripDetails>'; 
  $request .= '<TripHeader>'; 
  $request .= '<TripID>'.$data['trip_id'].'</TripID>'; 
  $request .= '<ExternalTripID/>'; 
  $request .= '<CarrierDetails>';  
  $request .= '<SCAC>'.$data['carcode'].'</SCAC>';
  $request .= '<Address>';   
  $request .= '<CompanyName>'.$data['carrier_name'].'</CompanyName>';
  $request .= '<Address1>'.$data['carrier_address'].'</Address1>'; 
  $request .= '<Address2>'.$data['carrier_address'].'</Address2>';    
  $request .= '<Street></Street>'; 
  $request .= '<City></City>'; 
  $request .= '<State></State>'; 
  $request .= '<Postal>'.$data['carpincode'].'</Postal>'; 
  $request .= '<Country></Country>'; 
  $request .= '<ContactNo>'; 
  $request .= '<CountryCode>'.$data['country_code'].'</CountryCode>'; 
  $request .= '<ContactNo>'.$data['carrier_mobile'].'</ContactNo>'; 
  $request .= '<EmailAddress>'.$data['carrier_email'].'</EmailAddress>'; 
  $request .= '</ContactNo>'; 
  $request .= '</Address>';
  $request .= '</CarrierDetails>';
  $request .= '<DriverDetails>';
  $request .= '<DriverId></DriverId>';
  $request .= '<DriverName>'.$data['driverName'].'</DriverName>';
  $request .= '<DriverLicence>'.$data['driving_licence_num'].'</DriverLicence>';
  $request .= '<Address>';
  $request .= '<Address1>'.$data['address_as_on_proof'].'</Address1>';
  $request .= '<Address2></Address2>';
  $request .= '<Street></Street>'; 
  $request .= '<City>'.$data['drivercity'].'</City>'; 
  $request .= '<State></State>';
  $request .= '<Postal></Postal>'; 
  $request .= '<Country></Country>'; 
  $request .= '<ContactNo>'; 
  $request .= '<CountryCode>'.$data['country_code'].'</CountryCode>'; 
  $request .= '<ContactNo>'.$data['drivermobile'].'</ContactNo>'; 
  $request .= '<EmailAddress></EmailAddress>'; 
  $request .= '</ContactNo>';  
  $request .= '</Address>'; 
  $request .= '</DriverDetails>';  
  $request .= '<VehicleDetails>';  
  $request .= '<VehicleTypeCode>'.$data['vehicle_type'].'</VehicleTypeCode>';   
  $request .= '<VehicleModelCode>'.$data['vehicle_type'].'</VehicleModelCode>';   
  $request .= '<RegistrationNumber>'.$data['vehiclereg_number'].'</RegistrationNumber>';   
  $request .= '<License></License>';   
  $request .= '<ApplicableForDangerousGoods></ApplicableForDangerousGoods>';   
  $request .= '<Properties>';  
  $request .= '<Weight>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['truck_weight'].'</Max>';  
  $request .= '<UOM>'.$data['weight_unit'].'</UOM>';  
  $request .= '</Weight>'; 
  $request .= '<Volume>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['truck_volume'].'</Max>';  
  $request .= '<UOM>'.$data['truck_volume'].'</UOM>';  
  $request .= '</Volume>'; 
  $request .= '<Length>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['length'].'</Max>';  
  $request .= '<UOM>'.$data['length_unit'].'</UOM>';  
  $request .= '</Length>';
  $request .= '<Width>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['width'].'</Max>';  
  $request .= '<UOM>'.$data['width_unit'].'</UOM>';  
  $request .= '</Width>'; 
 $request .= '<Height>';  
  $request .= '<Min>0.0</Min>';  
  $request .= '<Max>'.$data['truckheight'].'</Max>';  
  $request .= '<UOM>'.$data['height_unit'].'</UOM>';  
  $request .= '</Height>';
 $request .= '<Distance>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</Distance>';
 $request .= '<DimensionGirth>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</DimensionGirth>';
 $request .= '<ShipmentUnit>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</ShipmentUnit>'; 
 $request .= '<PacketWeight>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</PacketWeight>';
 $request .= '<PacketVolume>';  
  $request .= '<Min></Min>';  
  $request .= '<Max></Max>';  
  $request .= '<UOM></UOM>';  
  $request .= '</PacketVolume>';  
  $request .= '</Properties>';  
  $request .= '</VehicleDetails>';   
  $request .= '<TripLocation>';   
  $request .= '<TripStartLocation>';
  $request .= '<Address>';
  $request .= '<CompanyName>' . $data['pickup_company'] . '</CompanyName>';
  $request .= '<Address1>' . $data['pickup_address1'] . '</Address1>';
  $request .= '<Address2>' . $data['pickup_address2'] . '</Address2>';
  $request .= '<Street></Street>'; 
  $request .= '<City>' . $data['pickup_city'] . '</City>'; 
  $request .= '<State></State>';
  $request .= '<Postal>' . $data['pickup_pincode'] . '</Postal>'; 
  $request .= '<Country>' . $data['pickup_country'] . '</Country>'; 
  $request .= '<ContactNo>'; 
  $request .= '<CountryCode>'.$data['country_code'].'</CountryCode>'; 
  $request .= '<ContactNo>'.$data['pickup_contact'].'</ContactNo>'; 
  $request .= '<EmailAddress></EmailAddress>'; 
  $request .= '</ContactNo>';  
  $request .= '</Address>';  
  $request .= '</TripStartLocation>';   
  $request .= '<TripEndLocation>';
  $request .= '<Address>';
  $request .= '<CompanyName>' . $data['delivery_company'] . '</CompanyName>';
  $request .= '<Address1>' . $data['delivery_address1'] . '</Address1>';
  $request .= '<Address2>' . $data['delivery_address2'] . '</Address2>';
  $request .= '<Street></Street>'; 
  $request .= '<City>' . $data['delivery_city'] . '</City>'; 
  $request .= '<State></State>';
  $request .= '<Postal>' . $data['delivery_pincode'] . '</Postal>'; 
  $request .= '<Country>' . $data['delivery_country'] . '</Country>'; 
  $request .= '<ContactNo>'; 
  $request .= '<CountryCode>'.$data['country_code'].'</CountryCode>'; 
  $request .= '<ContactNo>'.$data['delivery_contact'].'</ContactNo>'; 
  $request .= '<EmailAddress></EmailAddress>'; 
  $request .= '</ContactNo>';  
  $request .= '</Address>';  
  $request .= '</TripEndLocation>';   
  $request .= '<EstimatedDateTime>';   
  $request .= '<From>';   
  $request .= '<DateTime>' . date( "Y-m-d", strtotime( $data['pickup_datetime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['pickup_datetime'] ) ) . '.000</DateTime>';   
  $request .= '<TimeZone></TimeZone>';   
  $request .= '<UTC>';
  $request .= '<Time></Time>';  
  $request .= '</UTC>';   
  $request .= '</From>';
  $request .= '<To>';   
  $request .= '<DateTime>' . date( "Y-m-d", strtotime( $data['delivery_datetime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['delivery_datetime'] ) ) . '.000</DateTime>';   
  $request .= '<TimeZone></TimeZone>';   
  $request .= '<UTC>';
  $request .= '<Time></Time>';  
  $request .= '</UTC>';   
  $request .= '</To>';   
  $request .= '</EstimatedDateTime>';   
  $request .= '</TripLocation>';
	$request .= '<CargoSummary>';
	$request .= '<TotalQuantity>';
	$request .= '<Value>' . $data['oquantity'] . '</Value>';
	$request .= '<UOM>Numbers</UOM>';  
	$request .= '</TotalQuantity>';
	$request .= '<TotalVolume>';
	$request .= '<Value>' . $data['ovolume'] . '</Value>';
	$request .= '<UOM>cbm</UOM>';  
	$request .= '</TotalVolume>';	
	$request .= '<TotalWeight>';
	$request .= '<Value>' . $data['owight'] . '</Value>';
	$request .= '<UOM>cbm</UOM>';  
	$request .= '</TotalWeight>';	  
	$request .= '</CargoSummary>';
	$request .= '</TripHeader>'; 
	$request .= '<TripOrderDetails>';
	$request .= '<Orders>';
	 $request .= '<OrderSequence>1</OrderSequence>';
	 $request .= '<OrderID>' . $data['order_id'] . '</OrderID>';
  $request .= '<EXTOrderID></EXTOrderID>';
  $request .= '<OrderType>' . $data['order_type'] . '</OrderType>';
  $modetrans = "FTL";
		if ( isset( $data['transport_mode'] ) ) {
			if ( $data['transport_mode'] == "TL" ) {
				$modetrans = "FTL";
			} else {
				$modetrans = "LTL";
			}
		}
		$request .= '<ModeOfTransport>' . $modetrans . '</ModeOfTransport>';
		$request .= '<Product>' . $data['product'] . '</Product>';
		$request .= '<ServiceType>' . $data['service'] . '</ServiceType>';
		$request .= '<TypeOfBusiness>DOMESTIC</TypeOfBusiness>';
  $request .= '<TermsOfTrade>'; 
  $request .= '<Incoterm>' . $data['incoterm'] . '</Incoterm>';
  $request .= '<FreightName>';
  $request .= '<Term></Term>';
  $request .= '<Name></Name>';
  $request .= '</FreightName>';
  $request .= '</TermsOfTrade>';
    $request .= '<CustomerDetails>'; 
  		$request .= '<Company>';
		$request .= '<ID>' . $data['customer_id'] . '</ID>';
		$request .= '<Name>' . $data['customer_name'] . '</Name>';
		$request .= '<RegistrationNumber></RegistrationNumber>';
		$request .= '</Company>';
		$request .= '<Address>';
		$request .= '<FirstName>' . $data['customer_name'] . '</FirstName>';
		$request .= '<LastName>' . $data['customer_name'] . '</LastName>';
		$request .= '<Address1>' . $data['customer_add2'] . '</Address1>';
		$request .= '<Address2>' . $data['customer_add1'] . '</Address2>';
		$request .= '<City>' . $data['customer_city'] . '</City>';
		$request .= '<Province>' . $data['customer_state'] . '</Province>';
		$request .= '<Country>' . $data['customer_country'] . '</Country>';
		$request .= '<Postal>' . $data['customer_pin'] . '</Postal>';
		$request .= '<ContactNo>';
		$request .= '<CountryCode>' . $data['country_code'] . '</CountryCode>';
		$request .= '<ContactNo>' . $data['customer_phone'] . '</ContactNo>';
		$request .= '<EmailAddress>' . $data['customer_email'] . '</EmailAddress>';
		$request .= '</ContactNo>';
		$request .= '</Address>';
  $request .= '</CustomerDetails>'; 
  $request .= '<LocationInfo>'; 
		$request .= '<PickUp>';
		$request .= '<Company>';
		$request .= '<Name>' . $data['pickup_company'] . '</Name>';
		$request .= '<ContactNo>' . $data['pickup_contact'] . '</ContactNo>';
		$request .= '</Company>';
		$request .= '<Address>';
		$request .= '<Country>' . $data['pickup_country'] . '</Country>';
		$request .= '<City>' . $data['pickup_city'] . '</City>';
		$request .= '<Postal>' . $data['pickup_pincode'] . '</Postal>';
		$request .= '<Address1>' . $data['pickup_address1'] . '</Address1>';
		$request .= '<Address2>' . $data['pickup_address2'] . '</Address2>';
		$request .= '</Address>';
		$request .= '<DateTime>';
	$request .= '<From>';
		$request .= '<DateTime>' . date( "Y-m-d", strtotime( $data['pickup_datetime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['pickup_datetime'] ) ) . '.000</DateTime>';
		$request .= '<TimeZone>' . $data['hrs'] . '/' . $data['curtz'] . '</TimeZone>';
		$request .= '<UTC>';
		$request .= '<Time>' . date( "Y-m-d", strtotime( $data['pickup_datetime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['pickup_datetime'] ) ) . '.000</Time>';
		$request .= '</UTC>';
		$request .= '</From>';
		$request .= '<To>';
		$request .= '<DateTime>' . date( "Y-m-d", strtotime( $data['pickup_endtime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['pickup_endtime'] ) ) . '.000</DateTime>';
		$request .= '<TimeZone>' . $data['hrs'] . '/' . $data['curtz'] . '</TimeZone>';
		$request .= '<UTC>';
		$request .= '<Time>' . date( "Y-m-d", strtotime( $data['pickup_endtime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['pickup_endtime'] ) ) . '.000</Time>';
		$request .= '</UTC>';
		$request .= '</To>';
		$request .= '</DateTime>';
		$request .= '</PickUp>';
		$request .= '<DropOff>';
		$request .= '<Company>';
		$request .= '<Name>' . $data['delivery_company'] . '</Name>';
		$request .= '<ContactNo>' . $data['delivery_contact'] . '</ContactNo>';
		$request .= '</Company>';
		$request .= '<Address>';
		$request .= '<Country>' . $data['delivery_country'] . '</Country>';
		$request .= '<City>' . $data['delivery_city'] . '</City>';
		$request .= '<Postal>' . $data['delivery_pincode'] . '</Postal>';
		$request .= '<Address1>' . $data['delivery_address1'] . '</Address1>';
		$request .= '<Address2>' . $data['delivery_address2'] . '</Address2>';
		$request .= '</Address>';
		$request .= '<DateTime>';
		$request .= '<From>';
		$request .= '<DateTime>' . date( "Y-m-d", strtotime( $data['delivery_datetime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['delivery_datetime'] ) ) . '.000</DateTime>';
		$request .= '<TimeZone>' . $data['hrs'] . '/' . $data['curtz'] . '</TimeZone>';
		$request .= '<UTC>';
		$request .= '<Time>' . date( "Y-m-d", strtotime( $data['delivery_datetime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['delivery_datetime'] ) ) . '.000</Time>';
		$request .= '</UTC>';
		$request .= '</From>';
		$request .= '<To>';
		$request .= '<DateTime>' . date( "Y-m-d", strtotime( $data['delivery_endtime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['delivery_endtime'] ) ) . '.000</DateTime>';
		$request .= '<TimeZone>' . $data['hrs'] . '/' . $data['curtz'] . '</TimeZone>';
		$request .= '<UTC>';
		$request .= '<Time>' . date( "Y-m-d", strtotime( $data['delivery_endtime'] ) ) . 'T' . date( "H:i:s", strtotime( $data['delivery_endtime'] ) ) . '.000</Time>';
		$request .= '</UTC>';
		$request .= '</To>';
		$request .= '</DateTime>';
		$request .= '</DropOff>';
$request .= '</LocationInfo>'; 
      $request .= '<CargoSummary>';
      $request .= '<TotalQuantity>';
      $request .= '<Value>' . $data['oquantity'] . '</Value>';
      $request .= '<UOM>Numbers</UOM>';  
      $request .= '</TotalQuantity>';
      $request .= '<TotalVolume>';
      $request .= '<Value>' . $data['ovolume'] . '</Value>';
      $request .= '<UOM>cbm</UOM>';  
      $request .= '</TotalVolume>';	
      $request .= '<TotalWeight>';
      $request .= '<Value>' . $data['owight'] . '</Value>';
      $request .= '<UOM>cbm</UOM>';  
      $request .= '</TotalWeight>';	  
      $request .= '</CargoSummary>';
      $request .= '<CargoDetails>';
      $request .= '<CargoType>GENERAL CATEGORY</CargoType>';
      $request .= '<ValueOfGoods>'.$data['goods_value'].'</ValueOfGoods>';
      $request .= '<Items>';
        foreach ($data['cargos'] as $goods){
          $request .= '<Item>';
          $request .= '<HandlingUnit>'.$goods['cargo_type'].'</HandlingUnit>';
          $request .= '<Length>';
          $request .= '<Value>'.$goods['length'].'</Value>';
          $request .= '<UOM>m</UOM>';
          $request .= '</Length>';
          $request .= '<Width>';
          $request .= '<Value>'.$goods['width'].'</Value>';
          $request .= '<UOM>m</UOM>';
          $request .= '</Width>';
          $request .= '<Height>';
          $request .= '<Value>'.$goods['height'].'</Value>';
          $request .= '<UOM>m</UOM>';
          $request .= '</Height>';
          $request .= '<Weight>';
          $request .= '<Value>'.$goods['weight'].'</Value>';
          $request .= '<UOM>m</UOM>';
          $request .= '</Weight>';
          $request .= '<TotalVolume>';
          $request .= '<Value>'.$data['total_volume'].'</Value>';
          $request .= '<UOM>cbm</UOM>';
          $request .= '</TotalVolume>';
          $request .= '<TotalWeight>';
          $request .= '<Value>'.$data['total_weight'].'</Value>';
          $request .= '<UOM>kg</UOM>';
          $request .= '</TotalWeight>';
          $request .= '<Quantity>'.$goods['quantity'].'</Quantity>';
          $request .= '</Item>';
      }
  $request .= '</Items>';
  $request .= '</CargoDetails>';
  $request .= '<ValueAddedServices>';
  $request .= '<Addon>';
  $request .= '<AddonName>'.$data['vas_name'].'</AddonName>';
  $request .= '<AddonCode>'.$data['vas_id'].'</AddonCode>';
  $request .= '<Currency></Currency>';
  $request .= '<RateUnit></RateUnit>';
  $request .= '<AddonAmount></AddonAmount>';
  $request .= '<AddonQuantity>'.$data['Vasquantity'].'</AddonQuantity>';
  $request .= '</Addon>';
  $request .= '</ValueAddedServices>';
  $request .= '<InvolvedParties>';
		foreach ( $data['parties'] as $ptype ) {
			if ( isset( $ptype['type'] ) ) {
				if ( $ptype['type'] == "Shipper" ) {
					$request .= '<PartyType type="Shipper">';
				}
				if ( $ptype['type'] == "Carrier" ) {
					$request .= '<PartyType type="Carrier">';
				}
				if ( $ptype['type'] == "Consignor" ) {
					$request .= '<PartyType type="Consignor">';
				}
				if ( $ptype['type'] == "Consignee" ) {
					$request .= '<PartyType type="Consignee">';
				}
				$request .= '<ID>' . $ptype['party_id'] . '</ID>';
				$request .= '<FirstName>' . $ptype['name'] . '</FirstName>';
				$request .= '<LastName>' . $ptype['name'] . '</LastName>';
				$request .= '<FullName>' . $ptype['name'] . '</FullName>';
				if ( $ptype['type'] == "Shipper" ) {
					$request .= '<UserName>ratchanee.limwatthana@kuehne-nagel.com</UserName>';
					$request .= '<Password>$2a$11$7xOpu6cePv2sR4HrzWGyOui1evc5GyHE2/72UQjYK9OJOvIEyKZGW</Password>';
				}
				if ( $ptype['type'] == "Carrier" ) {
					$request .= '<UserName>thananyas@jtclogistics.com</UserName>';
					$request .= '<Password>$2a$11$NrwvCZ2E9RTMdQJSlUGCT.Jeb13bKWkJP.HQ6pOdcSWjV74Y.18c2</Password>';
				}
				$request .= '<ContactNo>';
				$request .= '<CountryCode>' . $data['country_code'] . '</CountryCode>';
				$request .= '<AreaCode>' . $data['area_code'] . '</AreaCode>';
				$request .= '<ContactNo>' . $ptype['phone'] . '</ContactNo>';
				$request .= '</ContactNo>';
				$request .= '<Company>';
				$request .= '<Name>' . $data['pickup_company'] . '</Name>';
				$request .= '<ContactNo>+' . $data['country_code'] . '' . $data['area_code'] . '' . $ptype['phone'] . '</ContactNo>';
				$request .= '<RegistrationNumber>' . $ptype['party_id'] . '</RegistrationNumber>';
				$request .= '<EmailAddress>' . $ptype['email'] . '</EmailAddress>';
				$request .= '</Company>';
				$request .= '<Address>';
				$request .= '<Country>' . $ptype['country'] . '</Country>';
				$request .= '<City>' . $ptype['city'] . '</City>';
				$request .= '<Postal>' . $ptype['pincode'] . '</Postal>';
				$request .= '<Address1>' . $ptype['address'] . '</Address1>';
				$request .= '<Address2>' . $ptype['street'] . '</Address2>';
				$request .= '</Address>';
				if ( $ptype['type'] == "Consignor" ) {
					$request .= '<Comments></Comments>';
				}
				if ( $ptype['type'] == "Consignee" ) {
					$request .= '<Comments></Comments>';
				}
				
					$request .= '</PartyType>';
				
				
			}
		}
		$request .= '</InvolvedParties>';
		$request .= '<ManageReferences>';
		$request .= '<References>';
		$request .= '<RefType>';
		$request .= '<Code>' . $data['refrenceId'] . '</Code>';
		$request .= '<Value>' . $data['ref_value'] . '</Value>';
		$request .= '</RefType>';
		$request .= '</References>';
		$request .= '</ManageReferences>';
		$request .= '<Remarks>';
		$request .= '<RemarkType>';
		$request .= '<Code></Code>';
		$request .= '<Value></Value>';
		$request .= '</RemarkType>';    
        $request .= '</Remarks>';
		$request .= '</Orders>';
	$request .= '</TripOrderDetails>'; 	
	$request .= '</TripDetails>'; 	
  $request .= '</eTNEDITripBody>'; 
  $request .= '</eTNEDIMessage>';
  $resname=date("Ymdhis");
  $dom = new DOMDocument;
  $dom->preserveWhiteSpace = FALSE;
  $dom->loadXML($request);
  $dom->save('xml/TRIPKN'.$data['trip_id'].''.$resname.'.xml');
  log_message("error","request-order ".$request);
  $serviceurl = BOOKING_ETRA_URL;
  $username = BOOKING_ETRA_USRNAME;
  $password = BOOKING_ETRA_PWD;
    /*$username = "ws_etra";
    $password = "4fh2drGs3n";*/
    $auth = base64_encode($username.':'.$password);
    $headers = array(
      'Content-Type: application/xml',
      'Authorization: Basic '. base64_encode("$username:$password")
  );
    $output = thirdpartyservicecurl($serviceurl,$headers,$request);
    log_message('error', "orderslogresponsexml " . json_encode($output));
}
/* code for trip management */
public function orderlabel($order_id)
{
    $data = $order_types = array();
    require 'vendor/autoload.php';
    $reference = $shipmentnumber = $communication_reference = $date = "";
    $drop_details['name'] = $drop_details['street'] = $drop_details['country'] = $drop_details['pincode'] = $shipper_details['name'] = $shipper_details['street'] = $shipper_details['country'] = $shipper_details['pincode'] = $shipper_details['city'] = $data['count'] = $data['weight'] = "";
    $cargos = $drop_details  = $shipper_details = array();
    if ($order_id != "") {
        if ($this->session->userdata('cust_id') == true) {
            $upd_orderstatus = $this->db->where(array('id' => $order_id))->update("tb_orders", array('order_status' => 'READY'));
        }
        $chkorder = $this->Order->getordertoedit($order_id);
        if ($chkorder->num_rows() > 0) {
            $date      = $chkorder->row()->pickup_datetime;
            $reference = $chkorder->row()->shipment_id;
            if ($reference != "" && $reference != 0) {

                $getshipment_number = $this->db->select("shipid")->get_where("tb_shipments", array('id' => $reference));
                if ($getshipment_number->num_rows() > 0) {
                    $reference = $getshipment_number->row()->shipid;
                }
            }
            $shipmentnumber          = $chkorder->row()->order_id;
            $communication_reference = $chkorder->row()->purchase_order;

            $drop_id        = $chkorder->row()->drop_custid;
            $drop_row_id    = 0;
            $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name, p.mobile, p.email,p.code,p.fax,o.party_type FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1  WHERE p.status=1 AND o.order_id='$order_id' GROUP BY o.party_type");
            if($chekparty->num_rows() >0){
              foreach($chekparty->result() as $rr){
                $ptype = $rr->party_type;
                $chktype = $this->db->select("name")->get_where("tbl_party_types",array("id"=>$ptype),1,0);
                if($chktype->num_rows()>0){
                  if($chktype->row()->name == "Consignee"){
                    $drop_details = array('id'=>$rr->id,'name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
                }else if($chktype->row()->name == "Shipper"){
                   $shipper_details = array('id'=>$rr->id,'name'=>$rr->name,'phone'=>$rr->mobile,'email'=>$rr->email,'fax'=>$rr->fax,'party_id'=>$rr->code);
               }
           }
       }
   }
   $shipper_details['name'] = $chkorder->row()->pickup;
   $shipper_details['street'] = $chkorder->row()->pickup_address1;
   $shipper_details['state'] = $chkorder->row()->pickup_address2;
   $shipper_details['city'] = $chkorder->row()->pickup_city;
   $shipper_details['country'] = $chkorder->row()->pickup_country;
   $shipper_details['pincode'] = $chkorder->row()->pickup_pincode;

   $drop_details['name'] = $chkorder->row()->delivery;
   $drop_details['street'] = $chkorder->row()->delivery_address1;
   $drop_details['state'] = $chkorder->row()->delivery_address2;
   $drop_details['city'] = $chkorder->row()->delivery_city;
   $drop_details['country'] = $chkorder->row()->delivery_country;
   $drop_details['pincode'] = $chkorder->row()->delivery_pincode;

   $qry = $this->db->query("SELECT c.* FROM tb_cargo_details c,tb_order_cargodetails o WHERE o.order_id ='" . $order_id . "' AND o.cargo_id=c.id AND o.status='1' GROUP BY c.id ORDER BY c.id DESC");
   if ($qry->num_rows() > 0) {
    foreach ($qry->result() as $res) {
        $units = $res->weight_unit;
        if ($units == "") {
            $units = "Kg";
        }
        $qty = $res->quantity;
        $wt  = @round(($res->weight / $qty), 2);
        for ($i = 1; $i <= $qty; $i++) {
            $cargos[] = array('id' => $res->id, 'weight' => $wt . " " . $units);
        }
    }
}
}
$data['drop_details']            = $drop_details;
$data['shipper_details']         = $shipper_details;
$data['reference']               = $reference;
$data['shipmentnumber']          = $shipmentnumber;
$data['communication_reference'] = $communication_reference;
if ($date != "") {
    $timestamp    = strtotime($date);
    $data['date'] = date('d M Y', $timestamp);
}
$data['cargos'] = $cargos;
$ordernumber    = $chkorder->row()->order_id;
$mpdf           = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => [100, 110],
    'margin_left'   => 0,
    'margin_right'  => 0,
    'margin_top'    => 0,
    'margin_bottom' => 0,
    'margin_header' => 0,
    'margin_footer' => 0,
]);
$html = "";
$i = 1;
$data['count'] = count($cargos);
foreach ($cargos as $cargo) {
    $data['weight'] = $cargo['weight'];
    $data['no']     = $i;
    $html .= $this->load->view('orders/orderlabel',$data,true);
    /*$mpdf->WriteHTML($html);*/
    $i++;
}
$data1['labelcontent'] = $html;
$output = $this->load->view('orders/order_label',$data1,true);
$mpdf->WriteHTML($output);
$mpdf->Output($ordernumber . "_" . date('YmdHis') . ".pdf", 'D');
}
}

    public function getorderdetails($order_id)
    {
        if ($order_id != "") {
            $cdate = date('Y-m-d H:i:s');
            $chkorder = $this->Order->getordertoedit($order_id);
            $tripsts = "Pending";
            $order_status = "PENDING";
            if ($chkorder->num_rows() > 0) {
                $status = $chkorder->row()->status;
                $trip_sts = $chkorder->row()->trip_sts;
                $trip_id = $chkorder->row()->trip_id;
                if ($trip_id != 0 && $trip_sts == 0) {
                    $order_status = 'ACTIVE';
                    $tripsts = 'ACTIVE';
                }
                if ($trip_id != 0 && $trip_sts == 1) {
                    $order_status = 'ACTIVE';
                    $tripsts = 'ACTIVE';
                }
                $curtz = $this->session->userdata("usr_tzone")['timezone'];
                $createdon = $chkorder->row()->createdon;
                $updatedon = $chkorder->row()->updatedon;

                //  $getactual = getdatetimebytimezone($curtz,$createdon,DFLT_TZ);
                $curdt = $cdate;
                /* $curdt = $getactual['datetime'];*/
                //  $getactual = getdatetimebytimezone($curtz,$updatedon,DFLT_TZ);
                $upddt = $cdate;
                /* $upddt = $getactual['datetime'];*/
                $parties = array();
                $vendor_id = $chkorder->row()->vendor_id;
                if ($vendor_id != 0) {
                    $getvendor = $this->db->query("SELECT name,mobile,location,address,pincode,country,email,code FROM tb_vendors where id ='" . $vendor_id . "'");
                    if ($getvendor->num_rows() > 0) {
                        $vendordetails = array('name' => $getvendor->row()->name, 'party_id' => $getvendor->row()->code, 'address' => $getvendor->row()->address, 'pincode' => $getvendor->row()->pincode, 'country' => $getvendor->row()->country, 'street' => "", 'city' => $getvendor->row()->location, 'state' => "", 'phone' => $getvendor->row()->mobile, 'email' => $getvendor->row()->email);
                    }
                }
                $pickup_id = $chkorder->row()->customer_id;
                $getpickupdetails = $this->db->query("SELECT name,phone,state,street,location,pincode, address, email_id, code,country FROM tb_customers WHERE id='" . $pickup_id . "' AND status=1 LIMIT 1");
                if ($getpickupdetails->num_rows() > 0) {
                    $customerdetails = array('name' => $getpickupdetails->row()->name, 'party_id' => $getpickupdetails->row()->code, 'address' => $getpickupdetails->row()->address, 'pincode' => $getpickupdetails->row()->pincode, 'country' => $getpickupdetails->row()->country, 'street' => $getpickupdetails->row()->street, 'city' => $getpickupdetails->row()->location, 'state' => $getpickupdetails->row()->state, 'phone' => $getpickupdetails->row()->phone, 'email' => $getpickupdetails->row()->email_id);
                }
                $costomerId = $customerdetails['party_id'];
                $customerName = $customerdetails['name'];
                $customerAddress = $customerdetails['street'];
                $customerCity = $customerdetails['city'];
                $customerPin = $customerdetails['pincode'];
                $customerAddr2 = $customerdetails['address'];
                $customerEmail = $customerdetails['email'];
                $customerCountry = $customerdetails['country'];
                $customerPhone = $customerdetails['phone'];
                $customerstate = $customerdetails['state'];
                $consignee_mobile = $consignor_mobile = $shipper_mobile = $carrier_mobile = $consignee_id = $shipper_id = $pickup_state = $delivery_state = $pickup_email = $delivery_email = "";
                $chekparty = $this->db->query("SELECT p.id,p.party_type_id, p.name, p.mobile, p.email,p.code, p.location_id, p.address, p.country,p.state, p.street, p.pincode, o.party_type,p.latitude,p.longitude, a.party_master_id, a.location_id as plocation_id,a.street as pstreet,a.address as paddress,a.pincode as ppincode,a.country as pcountry FROM tbl_party_master p INNER JOIN tb_order_parties o ON p.id=o.party_id AND o.status=1 LEFT JOIN tbl_orderparty_address a ON o.party_id=a.party_master_id AND o.order_id=a.order_id AND a.status=1 WHERE p.status=1 AND o.order_id='$order_id' GROUP BY o.party_type");
                if ($chekparty->num_rows() > 0) {
                    foreach ($chekparty->result() as $rr) {
                        $pdetail = array();
                        $ptype = $rr->party_type;
                        $chktype = $this->db->select("name")->get_where("tbl_party_types", array("id" => $ptype), 1, 0);
                        if ($chktype->num_rows() > 0) {
                            if ($rr->party_master_id != "") {
                                $pdetail = array('name' => $rr->name, 'party_id' => $rr->code, 'address' => $rr->paddress, 'pincode' => $rr->ppincode, 'country' => $rr->pcountry, 'street' => $rr->pstreet, 'city' => $rr->plocation_id, 'state' => $rr->state, 'phone' => $rr->mobile, 'email' => $rr->email,'latitude' => $rr->latitude,'longitude' => $rr->longitude);
                            } else {
                                $pdetail = array('name' => $rr->name, 'party_id' => $rr->code, 'address' => $rr->address, 'pincode' => $rr->pincode, 'country' => $rr->country, 'street' => $rr->street, 'city' => $rr->location_id, 'state' => $rr->state, 'phone' => $rr->mobile, 'email' => $rr->email,'latitude' => $rr->latitude,'longitude' => $rr->longitude);
                            }
                            if ($chktype->row()->name == "Consignee") {
                                $pdetail['type'] = "Consignee";
                                $consignee_mobile = $rr->mobile;
                                $consignee_id = $rr->code;
                                $delivery_state = $rr->state;
                                $delivery_email = $rr->email;
                            }
                            if($chktype->row()->name == "CUSTOMER"){
                              $pdetail['type'] = "Customer";
                            }
                            if ($chktype->row()->name == "Consignor") {
                                $pdetail['type'] = "Consignor";
                                $consignor_mobile = $rr->mobile;
                                $consigneer_id = $rr->code;
                                $delivery_states = $rr->state;
                            }
                            /*if($chktype->row()->name == "FREIGHT_PAYER"){
                              $pdetail['type'] = "FREIGHT_PAYER";
                          }*/
                            if ($chktype->row()->name == "Shipper") {
                                $pdetail['type'] = "Shipper";
                                $shipper_mobile = $rr->mobile;
                                $shipper_id = $rr->code;
                                $pickup_state = $rr->state;
                                $pickup_email = $rr->email;
                            }
                            if ($chktype->row()->name == "Carrier") {
                                $pdetail['type'] = "Carrier";
                                $carrier_mobile = $rr->mobile;
                            }
                            $parties[] = $pdetail;
                        }
                    }
                }
                $code = "";
                $areacodes = array("1", "2", "3", "10", "11", "12", "673", "653", "645", "644", "635", "629", "628", "627", "624", "6", "5", "4", "29", "28", "27", "26", "25", "24", "23", "22", "21", "20", "19", "18", "17", "16", "15", "14", "13", "675", "676", "677", "678", "7", "8", "9");
                $zonecode = $this->session->userdata("usr_tzone");
                $currency = isset($zonecode['currency']) ? $zonecode['currency'] : "SGD";
                $company_code = $this->session->userdata("company_code");
                $branch_code = $this->session->userdata("branch_code");
                if (isset($zonecode['phone_code'])) {
                    $code = $zonecode['phone_code'];
                } else {
                    if ($company_code == "THKN") {
                        $code = "66";
                    }
                    if ($company_code == "SGKN") {
                        $code = "65";
                    }
                    if ($company_code == "INKN") {
                        $code = "91";
                    }
                }
                      // Refrence ids 
                $total_volume = $total_weight = 0;
             				

				$getcargos = $this->db->query("SELECT p.handling_unit,p.length,p.cargo_id,p.width,p.height,p.weight,p.volume,p.quantity,p.quantity_type,p.cargo_content,o.cargo_type,o.`goods_description`,o.`grounded`,o.`stackable`,o.`splittable`,o.`dg_goods`
				FROM tb_order_cargodetails p 
				LEFT OUTER JOIN `tb_cargo_details` o ON o.id=p.cargo_id
				WHERE p.status=1 AND p.order_id='" . $order_id . "'");			
                if ($getcargos->num_rows() > 0) {
                    foreach ($getcargos->result() as $res) {
                        $cargosdetail = array();
                        $volume = $res->volume;
                        $weight = $res->weight;
                        $cargo_id = $res->cargo_id;	
                        if ($volume == "") {
                            $volume = 1;
                        }
                        if ($weight == "") {
                            $weight = 1;
                        }
                        $cargosdetail = array('cargo_type' => $res->quantity_type, 'content' => $res->cargo_content, 'length' => $res->length, 'width' => $res->width, 'height' => $res->height, 'weight' => $weight, 'volume' => $volume, 'quantity' => $res->quantity,'goods_description' => $res->goods_description,'grounded' => $res->grounded,'stackable' => $res->stackable,'splittable' => $res->splittable,'dg_goods' => $res->dg_goods);
                        $total_volume += $volume;
                        $total_weight += $weight;
                        $cargos[] = $cargosdetail;
                    }
                }
                if ($chkorder->row()->company_code != "") {
                    $company_code = $chkorder->row()->company_code;

                }
                if ($chkorder->row()->branch_code != "") {
                    $branch_code = $chkorder->row()->branch_code;
                }
                $order_type = $chkorder->row()->order_type;
                $service = $chkorder->row()->service;
                $getOrdertype = $this->db->query("SELECT type_name FROM tb_order_types WHERE status ='1' and id='" . $order_type . "'");
                $orderType = $getOrdertype->row()->type_name;

                $getServicetype = $this->db->query("SELECT name FROM tb_service_master WHERE status ='1' and id='" . $service . "'");
                $orderService = $getServicetype->row()->name;

                $shipperdetail = $vendordetails = $consignedetail = $consignordetail = $pfdetails = $customerdetails = array();
                $cncontact = $consignee_mobile;
                $cargoid = $cargo_id;
                $cacontact = $consignor_mobile;
                $consigneeId = $consignee_id;
                $deliveryState = $delivery_state;
                $pickupState = $pickup_state;
                $shipper_id = $shipper_id;
                $delivery_email = $delivery_email;
                $pickup_email = $pickup_email;
                if ($cacontact == "") {
                    $cacontact = $shipper_mobile;
                }
				// Value added services 
				
					$getValueaddSer = $this->db->query("SELECT oc.quantity,im.vas_id,im.vas_name FROM tb_order_vas oc,tb_vas_master im WHERE oc.order_id = '" . $order_id . "' AND im.id = oc.vas_id");
					// echo $this->db->last_query();
					if($getValueaddSer->num_rows()>0){
					foreach($getValueaddSer->result() as $rese){
					$ValueAddondetail = array();
					$vas_name = $rese->vas_name;
					$vas_id = $rese->vas_id;
					$quantity = $rese->quantity;
					$ValueAddondetail = array('quantity'=>$quantity,'vas_id'=>$vas_id,'vas_name'=>$vas_name);
					echo $valueAddser[] = $ValueAddondetail;
					}
					}else{
						echo $valueAddser[] = 0;
					}
				// Refrence Ids
                 $getrefrnceid = $this->db->query("select reference_id,ref_value from tb_order_references where order_id ='" . $order_id . "'");
				 if($getrefrnceid->num_rows()>0){
					 foreach($getrefrnceid->result() as $res){
						$refiddetails = array();
                        $refiddetails = array('reference_id' => $res->reference_id,'ref_value' => $res->ref_value);
					    $refrences[] = $refiddetails;						
					 }
				 }
				 //Inner CargoDetails
				  $getinnerCargo = $this->db->query(" SELECT i.id as inner_id,i.cargo_type as inner_cargo,i.goods_description as inner_gd,i.quantity as inner_quantity,i.length as inner_length,i.length_unit as inner_lum,i.width as inner_width,i.width_unit as inner_wum,i.height as inner_height,i.height_unit as inner_hum,i.weight as inner_weight,i.weight_unit as inner_weum,i.volume as inner_volume,i.volume_unit as inner_vum,i.stackable as inner_stackable FROM tb_inner_cargo i WHERE i.cargo_id='" . $cargoid . "' AND i.status='1' GROUP BY i.id ORDER BY i.id DESC");
				  if($getinnerCargo->num_rows()>0){
				   $cargo_type = $getinnerCargo->row()->inner_cargo;
				   $goods_description = $getinnerCargo->row()->inner_gd;
				   $length = $getinnerCargo->row()->inner_length;
				   $width = $getinnerCargo->row()->inner_width;
				   $height = $getinnerCargo->row()->inner_height;
				   $weight = $getinnerCargo->row()->inner_weight;
				   $volume = $getinnerCargo->row()->inner_volume;
				   $quantity = $getinnerCargo->row()->inner_quantity;
				   $inn_total_volume += $volume;
                   $inn_total_weight += $weight;
				  }else{
				   $cargo_type ='';
				   $goods_description = '';
				   $length ='';
				   $width = '';
				   $height = '';
				   $weight = '';
				   $volume = '';
				   $quantity = '';
				  }
				
                $curtz = $this->session->userdata("usr_tzone")['timezone'];
                $hrs = $this->session->userdata("usr_tzone")['hrs'];
                $data = array('SenderTransmissionNo' => $chkorder->row()->order_id, 'order_status' => $order_status, 'pickup_datetime' => $chkorder->row()->pickup_datetime, 'delivery_datetime' => $chkorder->row()->delivery_datetime, 'pickup_endtime' => $chkorder->row()->pickup_endtime, 'delivery_endtime' => $chkorder->row()->drop_endtime, 'createdon' => $curdt, "delivery_company" => $chkorder->row()->delivery, 'delivery_contact' => $cncontact, 'shipper_id' => $shipper_id, 'delivery_city' => $chkorder->row()->delivery_city, 'delivery_country' => $chkorder->row()->delivery_country, 'delivery_address1' => $chkorder->row()->delivery_address1, 'delivery_address2' => $chkorder->row()->delivery_address2, 'delivery_pincode' => $chkorder->row()->delivery_pincode, 'branch_code' => $branch_code, 'company_code' => $company_code, 'transport_mode' => $chkorder->row()->transport_mode, 'pickup_country' => $chkorder->row()->pickup_country, 'pickup_pincode' => $chkorder->row()->pickup_pincode,'plat' => $chkorder->row()->plat,'plng' => $chkorder->row()->plng,'dlat' => $chkorder->row()->dlat,'dlng' => $chkorder->row()->dlng, 'logicalreceiver' => $chkorder->row()->logicalreceiver, 'physicalreceiver' => $chkorder->row()->physicalreceiver, 'physicalsender' => $chkorder->row()->physicalsender, 'logicalsender' => $chkorder->row()->logicalsender, 'country_code' => $code, "pickup_company" => $chkorder->row()->pickup, 'pickup_contact' => $cacontact, 'consignee_id' => $consigneeId, 'pickup_email' => $pickup_email, 'delivery_email' => $delivery_email, 'customer_name' => $customerName, 'customer_add1' => $customerAddress, 'customer_add2' => $customerAddr2, 'customer_city' => $customerCity, 'customer_pin' => $customerPin, 'customer_email' => $customerEmail, 'customer_country' => $customerCountry, 'customer_phone' => $customerPhone, 'customer_state' => $customerstate, 'customer_id' => $costomerId, 'pickup_state' => $pickupState, 'delivery_state' => $deliveryState, 'pickup_city' => $chkorder->row()->pickup_city, 'pickup_address1' => $chkorder->row()->pickup_address1, 'pickup_address2' => $chkorder->row()->pickup_address2, 'order_type' => $orderType, 'product' => $chkorder->row()->product, 'service' => $orderService, 'incoterm' => $chkorder->row()->incoterm, 'oquantity' => $chkorder->row()->quantity, 'ovolume' => $chkorder->row()->volume, 'owight' => $chkorder->row()->weight, 'refrences' => $refrences,'cargos' => $cargos,'valueAddser' => $valueAddser, 'total_weight' => $total_weight, 'total_volume' => $total_volume, 'inn_total_weight' => $inn_total_weight, 'inn_total_volume' => $inn_total_volume,'department_code' => $chkorder->row()->department_code, 'tripsts' => $tripsts, 'ststime' => $upddt, "area_code" => $areacodes[1], "goods_value" => $chkorder->row()->goods_value, "currency" => $currency, "parties" => $parties, "hrs" => $hrs, "curtz" => $curtz,"cargo_type" => $cargo_type,"goods_description" => $goods_description,"length" => $length,"width" => $width,"height" => $height,"weight" => $weight,"volume" => $volume,"quantity" => $quantity);
                $this->generateorderxml($data);
            }
        }
    }

    public function generateorderxml($data)
    {
        $order_id = $data['SenderTransmissionNo'];
        $date = date("Ymdhis");
        $request = '';
        $request .= '<eTNEDIMessage>';
        $request .= '<eTNEDIOrderHeader>';
        $request .= '<Version>1.0</Version>';
        $request .= '<UserName>eTrucknow</UserName>';
        $request .= '<Password>eTrucknow</Password>';
        $request .= '<SenderTransmissionNo>' . $data['SenderTransmissionNo'] . '_' . $date . '</SenderTransmissionNo>';
        $request .= '<AckSpec>';
        $request .= '<EmailAddress>dummy@email.com</EmailAddress>';
        $request .= '<AckOption>SUCCESS</AckOption>';
        $request .= '</AckSpec>';
        $request .= '<SourceApp>eTrucknow</SourceApp>';
        $request .= '<DestinationApp>ETRA</DestinationApp>';
        $request .= '<ReferenceId>' . $data['SenderTransmissionNo'] . '</ReferenceId>';
        $request .= '<Action>BookingDetails</Action>';
        $request .= '</eTNEDIOrderHeader>';
        $request .= '<eTNEDIOrderBody>';
        $request .= '<eTNOrgDetails>';
        $request .= '<Companycode>' . $data['company_code'] . '</Companycode>';
        $request .= '<Branchcode>' . $data['branch_code'] . '</Branchcode>';
        $request .= '<Departmentcode>' . $data['department_code'] . '</Departmentcode>';
        $request .= '<PhysicalReceiver/>';
        $request .= '<LogicalReceiver/>';
        $request .= '<PhysicalSender/>';
        $request .= '<LogicalSender/>';
        $request .= '</eTNOrgDetails>';
        $request .= '<OrderID>' . $data['SenderTransmissionNo'] . '</OrderID>';
        $request .= '<EXTOrderID>' . $data['SenderTransmissionNo'] . '</EXTOrderID>';
        $request .= '<OrderType>' . $data['order_type'] . '</OrderType>';
        $modetrans = "FTL";
        if (isset($data['transport_mode'])) {
            if ($data['transport_mode'] == "TL") {
                $modetrans = "FTL";
            } else {
                $modetrans = "LTL";
            }
        }
        $request .= '<ModeOfTransport>' . $modetrans . '</ModeOfTransport>';
        $request .= '<Product>' . $data['product'] . '</Product>';
        $request .= '<ServiceType>' . $data['service'] . '</ServiceType>';
        $request .= '<TypeOfBusiness>DOMESTIC</TypeOfBusiness>';
        $request .= '<TermsOfTrade>';
        $request .= '<Incoterm>' . $data['incoterm'] . '</Incoterm>';
        $request .= '<FreightName/>';
        $request .= '</TermsOfTrade>';
        $request .= '<Customer>';
        $request .= '<Company>';
        $request .= '<ID>' . $data['customer_id'] . '</ID>';
        $request .= '<Name>' . $data['customer_name'] . '</Name>';
        $request .= '<RegistrationNumber>' . $data['customer_id'] . '</RegistrationNumber>';
        $request .= '</Company>';
        $request .= '<Address>';
        $request .= '<FirstName>' . $data['customer_name'] . '</FirstName>';
        $request .= '<LastName>' . $data['customer_name'] . '</LastName>';
        $request .= '<Address1>' . $data['customer_add2'] . '</Address1>';
        $request .= '<Address2>' . $data['customer_add1'] . '</Address2>';
        $request .= '<Street>' . $data['customer_add1'] . '</Street>';
        $request .= '<City>' . $data['customer_city'] . '</City>';
        $request .= '<Province>' . $data['customer_state'] . '</Province>';
        $request .= '<State>' . $data['customer_state'] . '</State>';
        $request .= '<Postal>' . $data['customer_pin'] . '</Postal>';
		$request .= '<Country>' . $data['customer_country'] . '</Country>';
        $request .= '<ContactNo>';
        $request .= '<CountryCode>' . $data['country_code'] . '</CountryCode>';
        $request .= '<ContactNo>' . $data['customer_phone'] . '</ContactNo>';
        $request .= '<EmailAddress>' . $data['customer_email'] . '</EmailAddress>';
        $request .= '</ContactNo>';
        $request .= '</Address>';
        $request .= '</Customer>';
        $request .= '<LocationInfo>';
        $request .= '<Source>';
        $request .= '<ID>' . $data['shipper_id'] . '</ID>';
        $request .= '<Address>';
		$request .= '<CompanyName>' . $data['pickup_company'] . '</CompanyName>';
        $request .= '<Address1>' . $data['pickup_address1'] . '</Address1>';
        $request .= '<Address2>' . $data['pickup_address2'] . '</Address2>';
        $request .= '<Street>' . $data['pickup_address1'] . '</Street>';
        $request .= '<City>' . $data['pickup_city'] . '</City>';
        $request .= '<Province>' . $data['pickup_state'] . '</Province>';
		$request .= '<State>' . $data['pickup_state'] . '</State>';
		$request .= '<Postal>' . $data['pickup_pincode'] . '</Postal>';
        $request .= '<Country>' . $data['pickup_country'] . '</Country>';
		$request .= '<Latitude>' . $data['plat'] . '</Latitude>';
		$request .= '<Longitude>' . $data['plng'] . '</Longitude>';
		$request .= '<ContactNo>';
        $request .= '<CountryCode>' . $data['country_code'] . '</CountryCode>';
        $request .= '<ContactNo>' . $data['pickup_contact'] . '</ContactNo>';
        $request .= '<EmailAddress>' . $data['pickup_email'] . '</EmailAddress>';
        $request .= '</ContactNo>';
        $request .= '</Address>';
        $request .= '<EstimatedDateTime>';
        $request .= '<From>';
        $request .= '<DateTime>' . date("Y-m-d", strtotime($data['pickup_datetime'])) . 'T' . date("H:i:s", strtotime($data['pickup_datetime'])) . '.000</DateTime>';
        $request .= '<TimeZone>' . $data['hrs'] . '/' . $data['curtz'] . '</TimeZone>';
        $request .= '<UTC>';
        $request .= '<Time>' . date("Y-m-d", strtotime($data['pickup_datetime'])) . 'T' . date("H:i:s", strtotime($data['pickup_datetime'])) . '.000</Time>';
        $request .= '</UTC>';
        $request .= '</From>';
        $request .= '<To>';
        $request .= '<DateTime>' . date("Y-m-d", strtotime($data['pickup_endtime'])) . 'T' . date("H:i:s", strtotime($data['pickup_endtime'])) . '.000</DateTime>';
        $request .= '<TimeZone>' . $data['hrs'] . '/' . $data['curtz'] . '</TimeZone>';
        $request .= '<UTC>';
        $request .= '<Time>' . date("Y-m-d", strtotime($data['pickup_endtime'])) . 'T' . date("H:i:s", strtotime($data['pickup_endtime'])) . '.000</Time>';
        $request .= '</UTC>';
        $request .= '</To>';
        $request .= '</EstimatedDateTime>';
        $request .= '</Source>';
        $request .= '<Destination>';
		$request .= '<ID>' . $data['consignee_id'] . '</ID>';
        $request .= '<Address>';
		$request .= '<CompanyName>' . $data['delivery_company'] . '</CompanyName>';
        $request .= '<Address1>' . $data['delivery_address1'] . '</Address1>';
        $request .= '<Address2>' . $data['delivery_address2'] . '</Address2>';
		$request .= '<Street>' . $data['delivery_address1'] . '</Street>';
        $request .= '<City>' . $data['delivery_city'] . '</City>';
        $request .= '<Province>' . $data['delivery_state'] . '</Province>';
		$request .= '<State>' . $data['delivery_state'] . '</State>';
        $request .= '<Postal>' . $data['delivery_pincode'] . '</Postal>';
		$request .= '<Country>' . $data['delivery_country'] . '</Country>';
		$request .= '<Latitude>' . $data['dlat'] . '</Latitude>';
		$request .= '<Longitude>' . $data['dlng'] . '</Longitude>';
		$request .= '<ContactNo>';
        $request .= '<CountryCode>' . $data['country_code'] . '</CountryCode>';
        $request .= '<ContactNo>' . $data['delivery_contact'] . '</ContactNo>';
        $request .= '<EmailAddress>' . $data['delivery_email'] . '</EmailAddress>';
        $request .= '</ContactNo>';
        $request .= '</Address>';
        $request .= '<EstimatedDateTime>';
        $request .= '<From>';
        $request .= '<DateTime>' . date("Y-m-d", strtotime($data['delivery_datetime'])) . 'T' . date("H:i:s", strtotime($data['delivery_datetime'])) . '.000</DateTime>';
        $request .= '<TimeZone>' . $data['hrs'] . '/' . $data['curtz'] . '</TimeZone>';
        $request .= '<UTC>';
        $request .= '<Time>' . date("Y-m-d", strtotime($data['delivery_datetime'])) . 'T' . date("H:i:s", strtotime($data['delivery_datetime'])) . '.000</Time>';
        $request .= '</UTC>';
        $request .= '</From>';
        $request .= '<To>';
        $request .= '<DateTime>' . date("Y-m-d", strtotime($data['delivery_endtime'])) . 'T' . date("H:i:s", strtotime($data['delivery_endtime'])) . '.000</DateTime>';
        $request .= '<TimeZone>' . $data['hrs'] . '/' . $data['curtz'] . '</TimeZone>';
        $request .= '<UTC>';
        $request .= '<Time>' . date("Y-m-d", strtotime($data['delivery_endtime'])) . 'T' . date("H:i:s", strtotime($data['delivery_endtime'])) . '.000</Time>';
        $request .= '</UTC>';
        $request .= '</To>';
        $request .= '</EstimatedDateTime>';
        $request .= '</Destination>';
        $request .= '</LocationInfo>';
        $request .= '<CargoSummary>';
        $request .= '<TotalQuantity>';
        $request .= '<Value>' . $data['oquantity'] . '</Value>';
        $request .= '<UOM>NUMBERS</UOM>';
        $request .= '</TotalQuantity>';
        $request .= '<TotalVolume>';
        $request .= '<Value>' . $data['ovolume'] . '</Value>';
        $request .= '<UOM>CBM</UOM>';
        $request .= '</TotalVolume>';
        $request .= '<TotalWeight>';
        $request .= '<Value>' . $data['owight'] . '</Value>';
        $request .= '<UOM>KG</UOM>';
        $request .= '</TotalWeight>';
        $request .= '</CargoSummary>';
        $request .= '<CargoDetails>';
	    foreach ($data['cargos'] as $goods) {
		$request .= '<Items>';
        $request .= '<CargoType>' . $goods['cargo_type'] . '</CargoType>';
        $request .= '<GoodsDescription>' . $goods['goods_description'] . '</GoodsDescription>';
        $request .= '<MarksandNumbers/>';
        $request .= '<ValueOfGoods/>';
        $request .= '<GroundedFlag>' . $goods['grounded'] . '</GroundedFlag>';
        $request .= '<StackableFlag>' . $goods['stackable'] . '</StackableFlag>';
        $request .= '<SplittableFlag>' . $goods['splittable'] . '</SplittableFlag>';
        $request .= '<DangerousGoodsFlag>' . $goods['dg_goods'] . '</DangerousGoodsFlag>';
        $request .= '<TotalPackagesOfDangerousGoods>0</TotalPackagesOfDangerousGoods>';
            $request .= '<Item>';
            $request .= '<HandlingUnit>' . $goods['cargo_type'] . '</HandlingUnit>';
            $request .= '<Length>';
            $request .= '<Value>' . $goods['length'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Length>';
            $request .= '<Width>';
            $request .= '<Value>' . $goods['width'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Width>';
            $request .= '<Height>';
            $request .= '<Value>' . $goods['height'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Height>';
            $request .= '<Weight>';
            $request .= '<Value>' . $goods['weight'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Weight>';
			$request .= '<Volume>';
            $request .= '<Value>' . $goods['volume'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Volume>';			
            $request .= '<TotalVolume>';
            $request .= '<Value>' . $data['total_volume'] . '</Value>';
            $request .= '<UOM>cbm</UOM>';
            $request .= '</TotalVolume>';
            $request .= '<TotalWeight>';
            $request .= '<Value>' . $data['total_weight'] . '</Value>';
            $request .= '<UOM>kg</UOM>';
            $request .= '</TotalWeight>';
            $request .= '<Quantity>' . $goods['quantity'] . '</Quantity>';
			$request .= '<ScannedQuantity/>';
            $request .= '</Item>'; 
            if($data['cargo_type']!=''){		
			$request .= '<Item>';
			$request .= '<HandlingUnit>' . $data['cargo_type'] . '</HandlingUnit>';
			 $request .= '<Length>';
            $request .= '<Value>' . $data['length'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Length>';
            $request .= '<Width>';
            $request .= '<Value>' . $data['width'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Width>';
            $request .= '<Height>';
            $request .= '<Value>' . $data['height'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Height>';
            $request .= '<Weight>';
            $request .= '<Value>' . $data['weight'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Weight>';
			$request .= '<Volume>';
            $request .= '<Value>' . $data['volume'] . '</Value>';
            $request .= '<UOM>m</UOM>';
            $request .= '</Volume>';			
            $request .= '<TotalVolume>';
            $request .= '<Value>' . $data['inn_total_volume'] . '</Value>';
            $request .= '<UOM>cbm</UOM>';
            $request .= '</TotalVolume>';
            $request .= '<TotalWeight>';
            $request .= '<Value>' . $data['inn_total_weight'] . '</Value>';
            $request .= '<UOM>kg</UOM>';
            $request .= '</TotalWeight>';
            $request .= '<Quantity>' . $data['quantity'] . '</Quantity>';
			$request .= '<ScannedQuantity/>';
			$request .= '</Item>'; 	
			}
            $request .= '</Items>';
		 }
        $request .= '</CargoDetails>';
		if ($data['valueAddser'] != 0) {
		 $request .= '<ValueAddedServices>';
		foreach ($data['valueAddser'] as $addons) {
        $request .= '<Addon>';
        $request .= '<AddonName>' . $addons['vas_name'] . '</AddonName>';
        $request .= '<AddonCode>' . $addons['vas_id'] . '</AddonCode>';
        $request .= '<Currency></Currency>';
        $request .= '<RateUnit></RateUnit>';
        $request .= '<AddonAmount></AddonAmount>';
        $request .= '<AddonQuantity>' . $addons['quantity'] . '</AddonQuantity>';
        $request .= '</Addon>';
		}
        $request .= '</ValueAddedServices>';
		}else{
		  $request .= '<ValueAddedServices/>'; 
		}
        $request .= '<InvolvedParties>';
        foreach ($data['parties'] as $ptype) {
            if (isset($ptype['type'])) {
                if ($ptype['type'] == "Shipper") {
                    $request .= '<PartyType type="Shipper">';
                }
                if ($ptype['type'] == "Carrier") {
                    $request .= '<PartyType type="Carrier">';
                }
                if ($ptype['type'] == "Consignor") {
                    $request .= '<PartyType type="Consignor">';
                }
                if ($ptype['type'] == "Consignee") {
                    $request .= '<PartyType type="Consignee">';
                }
				if ($ptype['type'] == "Customer") {
                    $request .= '<PartyType type="Customer">';
                }
                $request .= '<ID>' . $ptype['party_id'] . '</ID>';
				$request .= '<Company>';
                $request .= '<Name>' . $data['pickup_company'] . '</Name>';
                $request .= '<RegistrationNumber>' . $ptype['party_id'] . '</RegistrationNumber>';             
                $request .= '</Company>';
				$request .= '<Address>';
                $request .= '<FirstName>' . $ptype['name'] . '</FirstName>';
                $request .= '<LastName>' . $ptype['name'] . '</LastName>';
                $request .= '<FullName>' . $ptype['name'] . '</FullName>';
                if ($ptype['type'] == "Shipper") {
                    $request .= '<UserName>ratchanee.limwatthana@kuehne-nagel.com</UserName>';
                    $request .= '<Password>$2a$11$7xOpu6cePv2sR4HrzWGyOui1evc5GyHE2/72UQjYK9OJOvIEyKZGW</Password>';
                }
                if ($ptype['type'] == "Carrier") {
                    $request .= '<UserName>thananyas@jtclogistics.com</UserName>';
                    $request .= '<Password>$2a$11$NrwvCZ2E9RTMdQJSlUGCT.Jeb13bKWkJP.HQ6pOdcSWjV74Y.18c2</Password>';
                }        
                $request .= '<Address1>' . $ptype['address'] . '</Address1>';
                $request .= '<Address2>' . $ptype['street'] . '</Address2>';
                $request .= '<Street>' . $ptype['street'] . '</Street>';
                $request .= '<City>' . $ptype['city'] . '</City>';
                $request .= '<Province>' . $ptype['state'] . '</Province>';
				$request .= '<State>' . $ptype['state'] . '</State>';              
                $request .= '<Postal>' . $ptype['pincode'] . '</Postal>';
				$request .= '<Country>' . $ptype['country'] . '</Country>';
				$request .= '<Latitude>' . $ptype['latitude'] . '</Latitude>';
				$request .= '<Longitude>' . $ptype['longitude'] . '</Longitude>';
                $request .= '<ContactNo>';
                $request .= '<CountryCode>' . $data['country_code'] . '</CountryCode>';
                $request .= '<ContactNo>+' . $data['country_code'] . '' . $data['area_code'] . '' . $ptype['phone'] . '</ContactNo>';
				$request .= '<EmailAddress>' . $ptype['email'] . '</EmailAddress>';
                $request .= '</ContactNo>';
				$request .= '</Address>';
                if ($ptype['type'] == "Consignor") {
                    $request .= '<Comments></Comments>';
                }
                if ($ptype['type'] == "Consignee") {
                    $request .= '<Comments></Comments>';
                }

                $request .= '</PartyType>';
            }
        }
        $request .= '</InvolvedParties>';	
        $request .= '<ManageReferences>';	
		foreach ($data['refrences'] as $refreid) {
        $request .= '<References>';
        $request .= '<RefType>';
        $request .= '<Code>' . $refreid['reference_id'] . '</Code>';
        $request .= '<Value>' . $refreid['ref_value'] . '</Value>';
        $request .= '</RefType>';
        $request .= '</References>';
		}
        $request .= '</ManageReferences>';		
        $request .= '<Remarks/>';
        $request .= '</eTNEDIOrderBody>';
        $request .= '</eTNEDIMessage>';
        $resname = date("Ymdhis");
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->loadXML($request);
        $dom->save('xml/ORDKN' . $order_id . $resname . '.xml');
         // log_message("error","request-order ".$request);
          /* $url = "http://hktkoms130.corp.int.kn:4646/service/ETN_ELOG";

           $ch = curl_init();
           if (!$ch) {
           die("Couldn't initialize a cURL handle");
           }
           curl_setopt($ch, CURLOPT_URL, $url);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($ch, CURLOPT_TIMEOUT, 30);
           curl_setopt($ch, CURLOPT_POST, true);
           curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
           //curl_setopt($ch, CURLOPT_POSTFIELDS,array('inputFiles: '.$request.''));
		  // curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
		   $data = 'inputFiles="'.$request.'"';
           curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
           curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
           curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
           curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
           $result = curl_exec($ch); // execute
           echo $result;             //show response
           log_message("error","result-order ".$result);
           curl_close($ch); */ 
			/* $url = "http://hktkoms130.corp.int.kn:4646/service/ETN_ELOG";

			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

			$headers = array(
			"Content-Type: application/x-www-form-urlencoded"
			);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

            $data = 'inputFiles="'.$request.'"';
			log_message("error","result-data ".$data);
			
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

			//for debug only!
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

			$resp = curl_exec($curl);
			curl_close($curl);
			var_dump($resp); */
		    //log_message("error","result-data ".json.stringify($resp));
		   
		   
    }

public function creditblock(){
  $post = $this->input->post();
  $uid = $this->session->userdata('user_id');
  $data['status'] = 0;
  $curdt = date('Y-m-d H:i:s');
  $cb_orderid = isset($post['cb_orderid']) ? $post['cb_orderid'] : 0;
  $creditid = isset($post['creditid']) ? $post['creditid'] : 0;
  $cb_companycode = isset($post['cb_companycode']) ? $post['cb_companycode'] : "";
  $cb_branchcode = isset($post['cb_branchcode']) ? $post['cb_branchcode'] : "";
  $cb_departcode = isset($post['cb_departcode']) ? $post['cb_departcode'] : "";
  $stoppage_id = isset($post['stoppage_id']) ? $post['stoppage_id'] : 0;
  $stoppage_name = isset($post['stoppage_name']) ? $post['stoppage_name'] : "";
  $stoppage_remark = isset($post['stoppage_remark']) ? $post['stoppage_remark'] : "";
  $remainder_date = isset($post['remainder_date']) ? $post['remainder_date'] : date('Y-m-d');
  $return_date = isset($post['return_date']) ? $post['return_date'] : date('Y-m-d');
  $resolution_id = isset($post['resolution_id']) ? $post['resolution_id'] : 0;
  $resolution_date = isset($post['resolution_date']) ? $post['resolution_date'] : date('Y-m-d');
  $extra_cost = isset($post['extra_cost']) ? $post['extra_cost'] : 0;
  $extracost_id = isset($post['extracost_id']) ? $post['extracost_id'] : "";
  $status_id = isset($post['status_id']) ? $post['status_id'] : 0;
  $status_dttime = isset($post['status_dttime']) ? $post['status_dttime'] : date('Y-m-d H:i:s');
  $status_remark = isset($post['status_remark']) ? $post['status_remark'] : "";
  $remainder_date = date('Y-m-d',strtotime($remainder_date));
  $return_date = date('Y-m-d',strtotime($return_date));
  $resolution_date = date('Y-m-d',strtotime($resolution_date));
  if($creditid == 0){
    $insdata = array('order_id'=>$cb_orderid, 'stoppage_id'=>$stoppage_id, 'stoppage_name'=>$stoppage_name, 'stoppage_remarks'=>$stoppage_remark, 'remainder_date'=>$remainder_date, 'return_date'=>$return_date, 'resolution_id'=>$resolution_id, 'resolution_date'=>$resolution_date, 'extra_cost'=>$extra_cost, 'extracost_id'=>$extracost_id, 'status_id'=>$status_id, 'status_datetime'=>$status_dttime, 'status_remark'=>$status_remark, 'company_code'=>$cb_companycode, 'branch_code'=>$cb_branchcode, 'department_code'=>$cb_departcode, 'user_id'=>$uid, 'status'=>1, 'created_on'=>$curdt, 'updated_on'=>$curdt);
    $ins = $this->db->insert("tbl_credit_blocked",$insdata);
    $creditblockid = $this->db->insert_id();
    $data['status'] = 1;
}else{
    $updata = array('order_id'=>$cb_orderid, 'stoppage_id'=>$stoppage_id, 'stoppage_name'=>$stoppage_name, 'stoppage_remarks'=>$stoppage_remark, 'remainder_date'=>$remainder_date, 'return_date'=>$return_date, 'resolution_id'=>$resolution_id, 'resolution_date'=>$resolution_date, 'extra_cost'=>$extra_cost, 'extracost_id'=>$extracost_id, 'status_id'=>$status_id, 'status_datetime'=>$status_dttime, 'status_remark'=>$status_remark, 'company_code'=>$cb_companycode, 'branch_code'=>$cb_branchcode, 'department_code'=>$cb_departcode, 'user_id'=>$uid, 'updated_on'=>$curdt);
    $updatecredit =  $this->db->where_in("id",$creditid)->update("tbl_credit_blocked",$updata);
    $data['status'] = 1;
}
echo json_encode($data);
}
public function checkcreditblocked(){
  $post = $this->input->post();
  $order_id = isset($post['order_id']) ? $post['order_id'] : 0;
  $data = array();
  if($order_id > 0){
    $creditdata = $this->Order->getcreditdata("tbl_credit_blocked",$order_id);
    if($creditdata->num_rows() > 0){
      $data = array('creditid'=>$creditdata->row()->id,'cb_orderid'=>$creditdata->row()->order_id, 'stoppage_id'=>$creditdata->row()->stoppage_id, 'stoppage_name'=>$creditdata->row()->stoppage_name, 'stoppage_remark'=>$creditdata->row()->stoppage_remarks, 'remainder_date'=>$creditdata->row()->remainder_date, 'return_date'=>$creditdata->row()->return_date, 'resolution_id'=>$creditdata->row()->resolution_id, 'resolution_date'=>$creditdata->row()->resolution_date, 'extra_cost'=>$creditdata->row()->extra_cost, 'extracost_id'=>$creditdata->row()->extracost_id, 'status_id'=>$creditdata->row()->status_id, 'status_dttime'=>$creditdata->row()->status_datetime, 'status_remark'=>$creditdata->row()->status_remark, 'cb_companycode'=>$creditdata->row()->company_code, 'cb_branchcode'=>$creditdata->row()->branch_code, 'cb_departcode'=>$creditdata->row()->department_code);
  }

}
echo json_encode($data);    
}
public function getstoppagename($id)
{
  $data['name'] = "";
  if ($id != "") {
    $getdesc = $this->db->query("SELECT name FROM tbl_stoppage_master WHERE id=$id AND status=1");
    if ($getdesc->num_rows() > 0) {
      $data['name'] = $getdesc->row()->name;
  }
}
echo json_encode($data);
}

public function getconinfo($order_row_id){
    $this->load->model('truckwaybillmodel');
    $serviceurl = SPOTON_URL;
    $username = SPOTON_USRNAME;
    $password = SPOTON_PWD;
    $headers = array(
      'Content-Type: application/json',
      'Authorization: Basic '. base64_encode("$username:$password")
  );
    $requestinfo=$req_in_arr=array();
    $where = array("id"=>$order_row_id);
    $select = "order_id,weight,delivery_city,pickup_city,pickup_datetime";
    $table = "tb_orders";
    $order = $this->common->gettblrowdata($where,$select,$table,0,0);
    $user_id = $this->session->userdata('user_id');
    $company_code = $this->session->userdata('company_code');
    $branch_code = $this->session->userdata('branch_code');
    $order_number='';
    $request_id=0;
    if(count($order)>0){
      $reference_val='';
      $where = array("order_id"=>$order_row_id);
      $select = "length,width,height,quantity";
      $table = "tb_order_cargodetails";
      $dimensions=array();
      $order_cargo = $this->common->gettbldata($where,$select,$table,0,0);
      $pickupinfo=$this->truckwaybillmodel->getshipper($order_row_id,'Shipper');
      $receiverinfo=$this->truckwaybillmodel->getshipper($order_row_id,'Consignee');
      $order_number=$order['order_id'];
      $order_reference=$this->truckwaybillmodel->orderrefernce($order_number);
      if(count($order_reference)>0){
       foreach($order_reference as $res){
           $reference_val=$res['ref_value'].",";
       }
   }
   $reference_val=trim($reference_val,",");
   $total_packages=0;
   if(count($order_cargo)>0){
      foreach($order_cargo as $info){
          $dimensions[]=array('Length'=>round($info['length']),'Breadth'=>round($info['width']),'height'=>round($info['height']),'Pieces'=>round($info['quantity']));
          $total_packages=$total_packages+$info['quantity'];
      }
  }
  $picklocationname = isset($pickupinfo['location_id']) ? $pickupinfo['location_id'] : "";
  if($picklocationname==''){
      $picklocationname=$order['pickup_city'];
  }
  $pickpincode = isset($pickupinfo['pincode']) ? $pickupinfo['pincode'] : "";

  $pickaddress = isset($pickupinfo['address']) ? $pickupinfo['address'] : "";

  $pickmobile = isset($pickupinfo['mobile']) ? $pickupinfo['mobile'] : "";
  $pickemail = isset($pickupinfo['email']) ? $pickupinfo['email'] : "";

  $pickup_datetime=date("Y-m-d",strtotime($order['pickup_datetime']));
  $requestinfo['UniqueHashValue']='#@64@!$%3';
  $requestinfo['CustomerCode']=$username;
  $requestinfo['PickupPincode']=substr($pickpincode, 0, 6);
  $requestinfo['PickupDateTime']=$pickup_datetime;
  $requestinfo['ReceiverPincode']=substr($receiverinfo['pincode'], 0, 6);
  $requestinfo['PaymentMode']='credit';
  $requestinfo['PickupLocationName']=$picklocationname;
  $requestinfo['PickupAddress']=$pickaddress;
  $requestinfo['PickupCity']=$order['pickup_city'];
  $requestinfo['PickupContactPhone']=$pickmobile;
  $requestinfo['PickupContactMail']=$pickemail;

  $requestinfo['ReceiverName']=$receiverinfo['name'];
  $requestinfo['ReceiverAddress']=$receiverinfo['address'];
  $requestinfo['ReceiverCity']=$order['delivery_city'];
  $requestinfo['ReceiverContactPerson']=$receiverinfo['name'];
  $requestinfo['ReceiverContactPhone']=$receiverinfo['mobile'];
  $requestinfo['ReceiverMail']=$receiverinfo['email'];
  $requestinfo['TotalPackages']=$total_packages;
  $requestinfo['TotalActualWeight']=$order['weight'];
  $requestinfo['Remarks']='Pickup and delivery notes';
  $requestinfo['SpecialInstruction']='On time';
  $requestinfo['VTCApplicable']='N';
  $requestinfo['VTCAmount']='0';
  $requestinfo['ReferenceNumber']=$reference_val;
  $requestinfo['TINNumber']='';
  $requestinfo['AWBConsignmentValue']='0';
  $requestinfo['GstIn']='';
  $requestinfo['ProductId']='';
  $requestinfo['SplInstruction']='';
  $requestinfo['Dimensions']=$dimensions;

         //store value to con request table
  $req_in_arr=array('customer_code'=>$requestinfo['CustomerCode'],'unique_value'=>$requestinfo['UniqueHashValue'],'order_id'=>$order_number,'order_rowid'=>$order_row_id,'user_id'=>$user_id,'company_code'=>$company_code,'branch_code'=>$branch_code,'pickup_pincode'=>$requestinfo['PickupPincode'],'pickupdatetime'=>$requestinfo['PickupDateTime'],'pickupreadytime'=>'','receiverpincode'=>$requestinfo['ReceiverPincode'],'paymentmode'=>$requestinfo['PaymentMode'],'pic_loc_name'=>$requestinfo['PickupLocationName'],'pickupaddress'=>$requestinfo['PickupAddress'],'pickupcity'=>$requestinfo['PickupCity'],'pic_contactphone'=>$requestinfo['PickupContactPhone'],'pic_contactmail'=>$requestinfo['PickupContactMail'],'receivername'=>$requestinfo['ReceiverName'],'receiveraddress'=>$requestinfo['ReceiverAddress'],'receivercontactperson'=>$requestinfo['ReceiverContactPerson'],'receiver_cont_phone'=>$requestinfo['ReceiverContactPhone'],'receivermail'=>$requestinfo['ReceiverMail'],'totalpackages'=>$requestinfo['TotalPackages'],'tot_actual_weight'=>$requestinfo['TotalActualWeight'],'remarks'=>$requestinfo['Remarks'],'special_instruction'=>$requestinfo['SpecialInstruction'],'vtc_applicable'=>$requestinfo['VTCApplicable'],'vtc_amount'=>$requestinfo['VTCAmount'],
     'refe_number'=>$requestinfo['ReferenceNumber'],'tin_number'=>$requestinfo['TINNumber'],'awb_consig_value'=>$requestinfo['AWBConsignmentValue'],'gst_in'=>$requestinfo['GstIn'],'product_id'=>$requestinfo['ProductId'],'spl_instruction'=>$requestinfo['SplInstruction'],'eway_bill'=>'','invoice_no'=>'','invoice_amt'=>'0','dimensions'=>json_encode($dimensions));
  $request_id=$this->common->insertTableData('tb_getcon_request',$req_in_arr);
  
}
$json_request = json_encode($requestinfo);
log_message('error', "spoton request " . $json_request);
$response=thirdpartyservicecurl($serviceurl,$headers,$json_request);
log_message('error', "spoton response " . json_encode($response));
curl_close($ch);
if($response){
    $startNo=(isset($response->Pieces[0]->StartNo) && !empty($response->Pieces[0]->StartNo)) ? (string) ($response->Pieces[0]->StartNo) : "";
    $endNo=(isset($response->Pieces[0]->EndNo) && !empty($response->Pieces[0]->EndNo)) ? (string) ($response->Pieces[0]->EndNo) : "";
    $res_array=array('request_id'=>$request_id,'order_id'=>$order_number,'order_rowid'=>$order_row_id,'company_code'=>$company_code,'branch_code'=>$branch_code,'res_status'=>$response->Status,'error_remarks'=>$response->ErrorRemarks,'unique_value'=>$response->UniqueValue,'customer_refno'=>$response->CustomerRefNo,'con_num'=>$response->ConNo,'pickup_sccode'=>$response->PickupScCode,'delivery_sccode'=>$response->DeliveryScCode,'pickup_order_no'=>$response->PickupOrderNo,'pie_startno'=>$startNo,'pie_endno'=>$endNo,'error_code'=>$response->ErrorCode,'user_id'=>$user_id);
    $this->common->insertTableData('tb_getcon_response',$res_array);

    $ins_ar=array('order_id'=>$order_row_id,'reference_id'=>'BN',
        'ref_value' => $response->ConNo,'status'=>1);
    $chkqry = $this->db->select('id')->get_where("tb_order_references", array('order_id' => $order_row_id, 'reference_id' => 'BN', 'ref_value' => $response->ConNo));
    if ($chkqry->num_rows() > 0) {
    } else {
        $ins = $this->db->insert('tb_order_references', $ins_ar);
    }

    /* code for ABH - Pickuporder number */
    if($response->PickupOrderNo!=''){
        $picins_ar=array('order_id'=>$order_row_id,'reference_id'=>'ABH',
            'ref_value' => $response->PickupOrderNo,'status'=>1);
        $chkqry = $this->db->select('id')->get_where("tb_order_references", array('order_id' => $order_row_id, 'reference_id' => 'ABH', 'ref_value' => $response->PickupOrderNo));
        if ($chkqry->num_rows() == 0) {
          $ins = $this->db->insert('tb_order_references', $picins_ar);
      }
  }
  /* code for ABH - Pickuporder number */


}
}
public function check_trip()
{
    $id = $this->input->post('order_id');
    $result = 0;
    if(!is_array($id)){
        $where = array("id"=>$id);
        $select = "shift_id";
        $table = "tb_orders";
        $order = $this->common->gettblrowdata($where,$select,$table,0,0);
        if(count($order)>0){
         if($order['shift_id']>0){
          $result = 1;
      }
  }
}else{
    $bookids = implode(",", $id);
    $where = " id IN($bookids) AND shift_id != 0";
    $select = "id";
    $table = "tb_orders";
    $order = $this->common->gettblrowdata($where,$select,$table,0,0);
    if(count($order)>0){
        $result = 1;
    }
}
echo $result;
}

public function GetOrderIntoapi($order_id)
{
    $url = "https://www.sevasetu.in/sp/index.php/api/tracking/track_v2";
    $headers = array(
        'Content-Type: application/json',
        'Username: maruticourier',
        'Password: 17322463b3e529f1ee3184444b7e0d61',
        'TOKEN: ABX78952-081E-41D4-8C86-FB410EF83123'
    );
    $where = array(
        "order_id" => $order_id
    );
    $select = "order_id,pickup_datetime,pickup_endtime,delivery_datetime,drop_endtime,pickup_company,delivery_company,pickup_country,delivery_country,pickup_city,delivery_city,pickup_pincode,delivery_pincode,pickup_address1,delivery_address1,pickup_address2,delivery_address2,quantity,weight,volume,goods_value,transport_mode,customer_name,customer_phone,customer_email,company_code,branch_code";

    $table = "tb_orders";
    $data_order = $this->common->gettblrowdata($where, $select, $table, 0, 0);
    if (count($data_order) > 0) {
        $postdata = array(
            'data' => $data_order
        );
        $getdata = thirdpartyservicecurl($url, $headers, $postdata);
            // assumption response array
            // {"success":"1","message":"Barcode successfully fetched.","data":{"barcode":{"order_id":"123","barcode_no":"589"}}}
        $json = json_decode($getdata, true);
        $get_order_id = $json['data']['barcode']['order_id'];
        $get_barcode_no = $json['data']['barcode']['barcode_no'];
        $where = array(
            "order_id" => $get_order_id
        );
        $select = "order_id,ref_value";
        $table = "tb_order_references";
        $data_ref = $this->common->gettblrowdata($where, $select, $table, 0, 0);
        if (count($data_ref) == 0) {
            $insdata = array(
                'order_id' => $get_order_id,
                'reference_id' => 'DQ',
                'ref_value' => $get_barcode_no,
                'status' => 1
            );
            $this->db->insert("tbl_credit_blocked", $insdata);
        }
    }
}
public function ordernotify($action,$orderid){
    $this->load->library('notifytrigger');
    $info['page_title'] = 'Booking Notification';
    $info['subject'] = 'Booking Notification';
    $info['order_id'] = $orderid;
    $info['action'] = $action;
    $orderinfo = $this->common->gettblrowdata(array('id'=>$orderid),'order_id,shift_id','tb_orders',0,0);
        if($orderinfo){
            $info['orderid'] = $orderinfo['order_id'];
            $info['cargos'] = $this->common->gettbldata(array('order_id'=>$orderid),'quantity_type,quantity','tb_order_cargodetails',0,0);
            if($action != 'trip_create'){
                $info['body'] = $this->load->view('mail_forms/notifytrigger/'.$action,$info,true);
                $this->notifytrigger->sendordernotify($info);
            } else {
                $info['shift_id'] = $orderinfo['shift_id'];
                $shipinfo = $this->common->gettblrowdata(array('id'=>$info['shift_id']),'shipmentid','tb_shifts',0,0);
                $info['shiftid'] = (!empty($shipinfo['shipmentid']))?$shipinfo['shipmentid']:'';
                $info['body'] = $this->load->view('mail_forms/notifytrigger/'.$action,$info,true);
                $this->notifytrigger->sendtripnotify($info);
            }
        }
}

  /*  public function testprefer(){
        $order_id = '1';
        $company_code = 'PLKN';
        $pref_arr = array('pickup'=>'GERMANY','drop'=>'POLAND','customer_id'=>'1000739642','service'=>'11','product'=>'KN PharmaChain','user_id'=>'7','company_code'=>$company_code,'order_id'=>$order_id);
        if($company_code == 'PLKN'){
            $this->ratemanagement->addrecodfororderinsertion($pref_arr);
        }
    }*/


}

?>