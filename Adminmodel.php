<?php 
class Adminmodel extends CI_Model
{
	function __construct()
	{
		$CI = &get_instance();
		$this->transportDB = $CI->load->database('transportDB', TRUE);  
	}
	
	public function checkAdminLogin($whr)
	{
		$this->transportDB->select('user_id,email,us_name,roletype');
		$this->transportDB->from('tb_admin'); 
		$this->transportDB->where($whr);
		$query = $this->transportDB->get();
		return $query->result();
	}
	
	function getCallcenterData($limit,$start)
	{
		$this->transportDB->select('*');
		$this->transportDB->from('tb_callcenter_report'); 
		$this->transportDB->order_by('id','desc');
		$this->transportDB->limit($limit, $start);
		$query = $this->transportDB->get(); 
		return $query->result();
	}
	
	function getCallcenterDataCount()
	{
		$this->transportDB->select('*');
		$this->transportDB->from('tb_callcenter_report'); 
		$query = $this->transportDB->get(); 
		return $query->result();
	}
	
	function getCalldataByIds($id)
	{
		$this->transportDB->select('*');
		$this->transportDB->from('tb_callcenter_report'); 
		$this->transportDB->where('id',$id);
		$this->transportDB->order_by('id','desc');
		$query = $this->transportDB->get();
		return $query->result();
	}



	function getAllLiveLoads()
	{
		$this->transportDB->select('id');
		$this->transportDB->from(TBLOADS); 
		$this->transportDB->where('status','Active'); 
		$query = $this->transportDB->get();
		return $query->result();
	}
	
	function getAllSMS()
	{
		$this->transportDB->select('id');
		$this->transportDB->from('tbl_sms_logs'); 
		$query = $this->transportDB->get();
		return $query->result();
	}
	
	function getTodaySMS()
	{
		$query = $this->transportDB->query('SELECT id FROM tbl_sms_logs WHERE sent_on > DATE_SUB(NOW(), INTERVAL 1 DAY)');
	/* 	$this->transportDB->from('tbl_sms_logs'); 
		$this->transportDB->where('sent_on >',DATE_SUB(NOW(), INTERVAL 1 DAY)); 
		$query = $this->transportDB->get(); */
		return $query->result();
	}
	
	function getAllActivity()
	{
		return $this->transportDB->get(TBACTIVITYTYPES);
	}
	function getActivity($id)
	{
		return $this->transportDB->get_where(TBACTIVITYTYPES,array('id'=>$id));
	}
	function saveActivity($data,$id)
	{
		if($id > 0)
		{
			$this->transportDB->update(TBACTIVITYTYPES,$data,array('id'=>$id));
		}
		else
		{
			$data['status'] = 1;
			$this->transportDB->insert(TBACTIVITYTYPES,$data);
		}
	}
	//adeed by rushi
	function saveactivitylist($data,$id,$status)
	{
		$this->transportDB->insert(TBAACTIVITYLIST,$data);	
		if($id>0)
		{
			$this->transportDB->query("update tb_callcenter_report set currentstatus=$status where id=$id");
		}
		
	}
	function getcallactivitylistbyId($id)
	{
		$query=$this->transportDB->query("SELECT tb_activity_types.activity_type,tb_status_types.status_type,tb_caller_activitylist.description FROM `tb_caller_activitylist`,tb_status_types,tb_activity_types where tb_caller_activitylist.activitytype_id=tb_activity_types.id and tb_caller_activitylist.status_id=tb_status_types.id and caller_id=$id order by tb_activity_types.id desc");
		return $query;
	}
	function getPaginationactivitydata($activity,$status,$fromdate,$todate,$perpage,$page)
	{
		
			return $sql;
	}
	
	function getActivityreport($activity,$status,$fromdate,$todate)
	{
		$whr='';
		
		if($activity==0 && $status==0 && $fromdate=='' and $todate=='')
			$sql="select * from tb_callcenter_report order by id desc";
		
		else	
			$sql="select distinct tb_callcenter_report.caller_id ,tb_callcenter_report.id as id,end_time,call_duration,destination,type,resource_url from tb_callcenter_report,tb_caller_activitylist where tb_callcenter_report.id=tb_caller_activitylist.caller_id ";		
			
			
		if($activity>0 && $status>0 && $fromdate!='' and $todate!='')
			$whr="select distinct tb_callcenter_report.caller_id ,tb_callcenter_report.id as id,end_time,call_duration,destination,type,resource_url from tb_callcenter_report,tb_caller_activitylist where tb_callcenter_report.id=tb_caller_activitylist.caller_id and  activitytype_id=$activity and currentstatus=$status and date(end_time) between '$fromdate' and '$todate'  ";
		else if($activity>0 && $status>0 && $fromdate!='')
			$whr=$sql." and activitytype_id=$activity and currentstatus=$status and '$fromdate' between date(start_time) and date(end_time)";	
		else if($activity>0 && $status>0  && $todate!='')	
			$whr=$sql." and activitytype_id=$activity and currentstatus=$status and '$todate' between start_time and end_time";	
		else if($activity>0 &&  $fromdate!='' && $todate!='')	
			$whr=$sql. " and activitytype_id=$activity  and date(end_time) between '$fromdate' and '$todate'";
		else if($status && $fromdate!='' && $todate!='')	
			$whr=$sql. " and  currentstatus=$status and end_time between '$fromdate' and '$todate'";
		else if($status>0 && $fromdate!='')
				$whr=$sql. " and  currentstatus=$status and '$fromdate' between date(start_time) and date(end_time)";
		else if($status>0 && $todate!='')
				$whr=$sql. " and  currentstatus=$status and '$todate' between date(start_time) and date(end_time)";
		else if($activity>0 && $status>0)
			$whr=$sql." and activitytype_id=$activity and currentstatus=$status";
		else if($activity>0)
			$whr=$sql. " and  activitytype_id=$activity";		
		else if($status>0)
			$whr=$sql." and  currentstatus=$status";	
		else if($fromdate!='' and $todate!='')
			$sql=" select  * from tb_callcenter_report   where date(end_time) between '$fromdate' and '$todate' order by id desc";	
		else if($fromdate!='')
			$whr=$sql." and   '$fromdate' between date(start_time) and date(end_time)";
		else if($todate!='')
			$whr=$sql." and   '$todate' between date(start_time) and date(end_time)";
		//$whr=$sql;
		if($whr=='')
			$whr=$sql;
		//echo $whr;
		$result=$query=$this->transportDB->query($whr);
		return $result;
	}
	
	//end
	function getAllStatus()
	{
		return $this->transportDB->get(TBSTATUSTYPES);
	}
	function getStatus($id)
	{
		return $this->transportDB->get_where(TBSTATUSTYPES,array('id'=>$id));
	}
	function saveStatus($data,$id)
	{
		if($id > 0)
		{
			$this->transportDB->update(TBSTATUSTYPES,$data,array('id'=>$id));
		}
		else
		{
			$data['status'] = 1;
			$this->transportDB->insert(TBSTATUSTYPES,$data);
		}
	}
}