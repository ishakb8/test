<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
class Services extends CI_Controller {

    public function __Construct() {
		parent::__Construct();
		if($this->session->userdata('user_id') == ''){
			redirect('login');
        }
		$this->load->library('form_validation');
        $this->load->model('common');
        $this->load->model('servicesmodel');
	}

    public function index()	{
		$data['page_title'] = "Services";
		$data['sub_title'] = "List";
        $user_id = $this->session->userdata('user_id');
        if(!empty($this->input->post('from_date')) && !empty($this->input->post('to_date'))){
            $from_date = date('Y-m-d',strtotime($this->input->post('from_date')));
            $to_date = date('Y-m-d',strtotime($this->input->post('to_date')));
        } else {
            $from_date = null;
            $to_date = null;
        }
        $company_code = !empty($this->input->post('company_code'))?$this->input->post('company_code'):NULL;
        $branch_code = !empty($this->input->post('branch_code'))?$this->input->post('branch_code'):NULL;
        $data['services'] = $this->servicesmodel->getServices($company_code,$branch_code,$from_date,$to_date);
		$this->newtemplate->dashboard("services/index", $data);
    }

    public function add()   {
        $data['page_title'] = "Services";
        $data['sub_title'] = "Add";
        $data['cmp_branch'] = $this->db->get_where("tb_branch_master", array("status" => 1));
        if($_POST){
            $user = $this->common->gettblrowdata(array('company_code'=>$this->input->post('company_code'),'branch_code'=>$this->input->post('branch_code')),'id','tb_users',0,0);
            $array['company_code'] = $this->input->post('company_code');
            $array['branch_code'] = $this->input->post('branch_code');
            $array['user_id'] = $user['id'];
            $array['service_id'] = $this->input->post('service_id');
            $array['name'] = $this->input->post('name');
            $array['status'] = 1;
            $array['createdon'] = date('Y-m-d H:i:s');
            $array['updatedon'] = date('Y-m-d H:i:s');
            $this->common->insertTableData('tb_service_master',$array);
            redirect(base_url('services/index'));
        } else {
            $this->newtemplate->dashboard('services/add', $data);
        }
    }

    public function edit($id) {
        $data['page_title'] = "Services";
        $data['sub_title'] = "Edit";
        $data['service'] = $this->common->gettblrowdata(array('id'=>$id),'*','tb_service_master',1,1);
        if(isset($data['service']['id'])) {
            if($_POST){
                $user = $this->common->gettblrowdata(array('company_code'=>$this->input->post('company_code'),'branch_code'=>$this->input->post('branch_code')),'id','tb_users',0,0);
                $array['company_code'] = $this->input->post('company_code');
                $array['branch_code'] = $this->input->post('branch_code');
                $array['user_id'] = $user['user_id'];
                $array['service_id'] = $this->input->post('service_id');
                $array['name'] = $this->input->post('name');
                $array['updatedon'] = date('Y-m-d H:i:s');
                $this->common->updatetbledata('tb_service_master',$array,array('id'=>$id));
                redirect(base_url('services/index'));
            } else {
                $this->newtemplate->dashboard('services/edit', $data);
            }
        } else {
            show_error('The Service you are trying to Edit does not exist.');
        }
    }

    function delete($id){
        $service = $this->common->gettblrowdata(array('id'=>$id),'*','tb_service_master',1,1);
        if(isset($service['id'])) {
            $this->common->updatetbledata('tb_service_master',array('status'=>0),array("id"=>$id));
            redirect('services/index');
        }  else{
            show_error('The Service you are trying to delete does not exist.');
        }
    }

    public function view($id){
        $data['page_title'] = "Services";
        $data['sub_title'] = "View";
        $data['product'] = $this->common->gettblrowdata(array('id'=>$id),'id,company_code,branch_code,service_id,name','tb_service_master',0,0);
        $this->newtemplate->dashboard('services/view', $data);
    }
}

