<?php 
class Order extends CI_Model
{

	function __construct() 
	{
      parent::__construct();
	}
	
 public function getorderdata($userid,$searchids,$searchsts,$custid,$countryuids,$whr,$subcusts)
	{
		$result = array();
		$this->db->select("o.id,o.order_id,o.pickup_datetime,o.delivery_datetime,o.pickup_company as pickup,o.delivery_company as delivery,o.transport_mode,o.company_code,o.branch_code,o.shipment_id,o.status,o.order_status,o.trip_sts,o.shift_id,o.trip_id,o.shipmentid,o.createdon,d.delivery_note,d.department_code, Sum(Case When c.status = '1' Then c.weight Else 0 End) as totwg,SUM(Case When c.status = '1' Then c.volume Else 0 End) as totvol,SUM(Case When c.status = '1' Then c.quantity Else 0 End) as totqty");
		$this->db->from("tb_orders o");
		$this->db->join("tb_order_details d","o.id=d.order_row_id","LEFT");
		$this->db->join("tb_order_cargodetails c","o.id=c.order_id","LEFT");
		$this->db->where("o.status !=",0);
		if(!empty($searchids)){
			$this->db->where_in("o.order_id",$searchids);
		}
		if($userid != '0'){
			if(!empty($countryuids)){
			$this->db->where_in("o.user_id",$countryuids);
			}else{
				$this->db->where("o.user_id",$userid);
			}
		}
		if($searchsts == 'PENDING'){
			$this->db->where("o.trip_id",0);
		}
		if($searchsts == 'ACTIVE'){
			$this->db->where("o.trip_id !=",0);
			$this->db->where("o.trip_sts",0);
		}
		if($searchsts == 'CLOSED'){
			$this->db->where("o.trip_id !=",0);
			$this->db->where("o.trip_sts",1);
		}
		if(!empty($subcusts)){
		    $this->db->where_in("o.customer_id",$subcusts);
		}else{
		    if($custid != ""){
			    $this->db->where('o.customer_id',$custid);
		    }
		}
		$this->db->where($whr);
		$this->db->group_by("o.id");
		$this->db->order_by("o.createdon",'DESC');
		$getorder = $this->db->get();
		if($getorder->num_rows() >0){
		 	$result = $getorder->result_array();
		}
		return $result;
	}
	public function getcustomercodebyids($select,$table,$ids){
		$result = array();
		$this->db->select($select);
		$this->db->from($table);
		$this->db->where_in('id',$ids);
		$res = $this->db->get();
		if($res->num_rows() > 0){
			$result = $res->result_array();
		}
		return $result;
	}


	public function getordertoedit($id)
	{
		$this->db->select("o.id,o.order_id,o.pickup_datetime,o.logicalreceiver,o.physicalreceiver,o.physicalsender,o.logicalsender,o.pickup_endtime,o.shipment_id,o.delivery_datetime,o.drop_endtime,o.pickup_company as pickup,o.delivery_company as delivery,o.pickup_address1,o.delivery_address1,o.pickup_address2,o.delivery_address2,o.pickup_city,o.delivery_city,o.pickup_country,o.delivery_country,o.pickup_pincode,o.delivery_pincode,o.company_code,o.branch_code,o.product,o.goods_value,o.transport_mode,o.vehicle_type,o.quantity,o.volume,o.weight,o.customer_id,o.vendor_id,o.pickup_custid,o.pickup_partyid,o.drop_custid,o.drop_partyid,o.trip_sts,o.shift_id,o.trip_id,o.status,o.order_status,o.createdon,o.updatedon,o.shipmentid,d.order_type,d.shipper_id,d.service,d.delivery_term,d.incoterm,d.delivery_note,d.purchase_order,d.notify_party,d.lane_reference,d.distance,d.customs_required,d.high_cargo_value,d.valorance_insurance,d.temperature_control,d.department_code");
		$this->db->from("tb_orders o");
		$this->db->join("tb_order_details d","o.id=d.order_row_id","LEFT");
		$this->db->where("o.id",$id);
		$this->db->limit(1);
		$chkorder = $this->db->get();
		return $chkorder;
	}

	public  function getpickupdetails($id){
		 $getpickupdetails = $this->db->query("SELECT id,name,address,pincode,code,country FROM tb_customers WHERE status='1' AND id='".$id."' LIMIT 1");
		 return $getpickupdetails;
	}

	public function getpartydetailsbycid($id){
		$getdetails = $this->db->query("SELECT id,name,email,street,location_id as city,state,mobile,pincode,country,customeridentifier,fax,partyindetifier FROM tbl_party_master WHERE status='1' AND customeridentifier='".$id."' ORDER BY id DESC LIMIT 1");
		return $getdetails;
	}
	public function getpartydetailsbyid($id){
		$getdetails = $this->db->query("SELECT id,name,email,street,location_id as city,state,mobile,pincode,country,customeridentifier,fax,partyindetifier FROM tbl_party_master WHERE status='1' AND id='".$id."' ORDER BY id DESC LIMIT 1");
		return $getdetails;
	}

	public function getreferencebyorder($id){
		$getreference = $this->db->query("SELECT o.ref_value,r.id,r.name FROM tb_order_references o,tb_reference_master r WHERE o.order_id='".$id."' AND o.reference_id=r.name AND o.status='1' AND r.status='1' ORDER BY o.id DESC");
		return $getreference->result_array();
	}
	public function getcreditdata($table,$order_id){
		$result = array();
		$this->db->select("*");
		$this->db->from($table);
		$this->db->where("order_id", $order_id);
		$this->db->where("status", 1);
		$result = $this->db->get();
		return $result;
	}
	public function getmasters($table,$select){
    	$result = array();
    	$this->db->select($select);
        $this->db->from($table);
        $this->db->where("status", 1);
        $result = $this->db->get();
		return $result;
    }
    public function getchargesforrevenuemodel($revenue_id){
    	$getcharges = $this->db->select("*")->get_where("tb_charges", array('revenue_id' => $revenue_id, 'status' => 1));
        $this->db->select("c.*,c1.charge_code as chargecode");
        $this->db->from("tb_charges c");
        $this->db->join("tb_charge_codes c1", "c.charge_code=c1.id", "LEFT");
        $this->db->where("c.revenue_id", $revenue_id);
        $this->db->where("c.status", 1);
        $getcharges = $this->db->get();
        return $getcharges;
    }
    public function getchargecodes($code){

    	$this->db->select("id,name");
        $this->db->from("tb_charge_codes");
        $this->db->where('status', 1);
        $this->db->like('name', $code);
        $chkqry = $this->db->get();
        return $chkqry;
    }
    public function getorderids($user_id,$custid,$searchTerm){
    	$this->db->select("order_id");
    	$this->db->from("tb_orders");
    	$this->db->where("status !=",0);
    	$this->db->where("user_id",$user_id);
    	$this->db->like("order_id",$searchTerm);
    	if(!empty($custid)){
    		$this->db->where_in("customer_id",$custid);
    	}
    	$this->db->order_by("createdon",'DESC');
    	$chkqry = $this->db->get();
    	return $chkqry;
    }
    public function getcustomerbasedservices($cust_code,$service_id){
    	$this->db->select('rs.id,rs.service_id,rs.service_name,rs.product');
        $this->db->from("tb_rate_services rs");
        $this->db->join("tb_rate_offerings ro","rs.id=ro.rate_service_id","LEFT");
        $this->db->join("tb_customer_profile cp","cp.id=ro.cust_profile_id","LEFT");
        $this->db->join("tb_customer_profile_list cl","cp.id=cl.cp_id","LEFT");
        $this->db->where("cl.profile_id",$cust_code);
        $this->db->where("rs.service_type",$service_id);
        $this->db->where('rs.status',1);
        $this->db->where('cp.status',1);
        $this->db->where('cl.status',1);
        $this->db->where('ro.status',1);
        $this->db->group_by('rs.id');
        $this->db->order_by("rs.id","DESC");
        $chkqry = $this->db->get();

        return $chkqry;

    }
      public function getlanes_byservice($source,$service_id){
        $whr = "";
        if(!empty($source)){
            $pickup_country = $source['pickup_country'];
            $delivery_country = $source['delivery_country'];
            $pickup_city = $source['pickup_city'];
            $delivery_city = $source['delivery_city'];
            $pickup_zcode = $source['pickup_zcode'];
            $delivery_zcode = $source['delivery_zcode'];
            if(($pickup_country != "" && $delivery_country != "") || ($pickup_city != "" && $delivery_city != "") || ($pickup_zcode != "" && $delivery_zcode != "")){
                $whr = "(";
                $whr .= "(l.source_geo ='2' AND l.source LIKE '".$pickup_country."' AND l.destination_geo='2' AND l.destination LIKE '".$delivery_country."' )";
                if($pickup_city != "" && $delivery_city != ""){
                    $whr .=" OR (l.source_geo ='5' AND l.source LIKE '".$pickup_city."' AND l.destination_geo ='5' AND l.destination LIKE '".$delivery_city."' ) ";
                }
                if($pickup_zcode != "" && $delivery_zcode != ""){
                    $whr .= " OR ( l.source_geo='6' AND l.source LIKE  '".$pickup_zcode."' AND l.destination_geo ='6' AND l.destination LIKE '".$delivery_zcode."' ) ";
                }
                $whr .= ")";
            }
        }
        $this->db->select("l.id,l.lane_id,l.lane_name");
        $this->db->from("tb_lanes_master l");
        $this->db->join("tb_rateservice_lanes rl","l.id=rl.lane_id","LEFT");
        if($whr != ""){
            $this->db->where($whr);
        }
        
        $this->db->where("rl.rate_id",$service_id);
        $this->db->where("rl.status",1);
        $this->db->where("l.status",1);
        $this->db->group_by('l.id');
        $this->db->order_by("l.id","DESC");
        $chkqry = $this->db->get();
        /*log_message("error","191 ".$this->db->last_query());*/
        return $chkqry;
    
      }
    public function getlanes_byservicerange($service_id){

    	$this->db->select("l.id,l.lane_id,l.lane_name");
    	$this->db->from("tb_lanes_master l");
    	$this->db->join("tb_rateservice_lanes rl","l.id=rl.lane_id","LEFT");
    	$this->db->where("rl.rate_id",$service_id);
    	$this->db->where("l.source_geo",1);
    	$this->db->where("l.destination_geo",1);
        $this->db->where("rl.status",1);
        $this->db->where("l.status",1);
    	$this->db->group_by('l.id');
    	$this->db->order_by("l.id","DESC");
    	$chkqry = $this->db->get();
    	return $chkqry;
    }
    public function getifrangeexits($service_id){

    	$this->db->select("l.id,l.lane_id,l.lane_name");
    	$this->db->from("tb_lanes_master l");
    	$this->db->join("tb_rateservice_lanes rl","l.id=rl.lane_id","LEFT");
    	$this->db->where("rl.rate_id",$service_id);
    	$this->db->where("l.source_geo",1);
    	$this->db->where("l.destination_geo",1);
    	$this->db->group_by('l.id');
    	$this->db->order_by("l.id","DESC");
    	$chkqry = $this->db->get();
    	return $chkqry;
    }

     public function getVendorbasedservices($vendor_code,$service_id){
    	$this->db->select('rs.id,rs.service_id,rs.service_name');
    	$this->db->from("tb_rate_services rs");
    	$this->db->join("tb_rate_offerings ro","rs.id=ro.rate_service_id","LEFT");
        $this->db->join("tb_vendor_profile vp","vp.id=ro.vendor_profile_id","LEFT");
    	$this->db->join("tb_vendor_profile_list vpl","vp.id=vpl.vp_id","LEFT");
    	$this->db->where("vpl.profile_id",$vendor_code);
    	$this->db->where("rs.service_type",$service_id);
    	$this->db->where('rs.status',1);
        $this->db->where('vp.status',1);
    	$this->db->where('vpl.status',1);
    	$this->db->where('ro.status',1);
    	$this->db->group_by('rs.id');
    	$this->db->order_by("rs.id","DESC");
    	$chkqry = $this->db->get();
    	return $chkqry;

    }
     /* Get customer profile by using customer code */
    public function getcustomerprofileforpreference($cust_code,$user_id){
        $this->db->select('cp.id');
        $this->db->from("tb_customer_profile cp");
        $this->db->join("tb_customer_profile_list cpl","cpl.cp_id=cp.id","LEFT");
        $this->db->where("cpl.profile_id",$cust_code);
        $this->db->where("cp.user_id",$user_id);
        $this->db->where("cp.status",1);
        $chkqry = $this->db->get();
        return $chkqry;
    }
    /* Get rate Preference list by order details */
    public function getpreferencebyorderdetails($cust_profile_id,$service,$product,$user_id,$pickup,$delivery){
        if($cust_profile_id != "" && $cust_profile_id != '0'){
            $this->db->select('rp.rate_offering_id,rp.rate_record_id,rp.auto_bill');
            $this->db->from("tb_rate_preferences rp");
            $this->db->join("tb_rateprefer_source rps","rps.rate_prefer_id=rp.id","LEFT");
            $this->db->join("tb_rateprefer_destination rds","rds.rate_prefer_id=rp.id","LEFT");
            $this->db->join("tb_rateprefer_product rpp","rpp.rate_prefer_id=rp.id","LEFT");
            $this->db->join("tb_rateprefer_service rpsc","rpsc.rate_prefer_id=rp.id","LEFT");
            $this->db->where("rp.cust_profile_id",$cust_profile_id);
            $this->db->where("rps.country",$pickup);
            $this->db->where("rps.user_id",$user_id);
            $this->db->where("rps.status",1);
            $this->db->where("rds.country",$delivery);
            $this->db->where("rds.user_id",$user_id);
            $this->db->where("rds.status",1);
            $this->db->where("rpp.product_id",$product);
            $this->db->where("rpp.status",1);
            $this->db->where("rpsc.service_id",$service);
            $this->db->where("rpsc.status",1);
            $this->db->where("rp.user_id",$user_id);
            $this->db->where("rp.status",1);
            $chkqry = $this->db->get();
            return $chkqry;
        }
    } 
     public function getvatdetails($data){
        $result = array();
        $this->db->select("v.name,lv.charge_id,lv.vat,c.charge_code,l.source_geo,l.source_country,l.destination_geo,l.destination_country");
        $this->db->from("tbl_vat_master v"); 
        $this->db->join('tbl_lanes l', 'l.vatid = v.id', 'left');
        $this->db->join('tbl_lane_vat lv', 'lv.lane_id = l.id', 'left');
        $this->db->join('tb_charge_codes c', 'c.id = lv.charge_id', 'left');
        if($data['type'] == 0){
        $this->db->join('tbl_party_master p', 'p.customeridentifier = v.customeridentifier', 'left');
        }else{
         $this->db->join('tbl_party_master p', 'p.customeridentifier = v.vendoridentifier', 'left');
        }
        $this->db->where("p.code", $data['custcode']); 
        $this->db->where("c.id", $data['chargecodeid']);
        $this->db->where("v.status", 1);
        $this->db->where("l.status", 1);
        $this->db->where("lv.status", 1);
        if($data['company_code'] == "RUKN"){
            $this->db->where("p.company_code", "RUKN");
        }else {
            $this->db->where("p.user_id", $data['user_id']);
        }
        $this->db->where("p.status", 1);
        $getvatdetails = $this->db->get();
        if($getvatdetails->num_rows() > 0){
            $result = $getvatdetails->result_array();
        }
        return $result;
    }
}
?>