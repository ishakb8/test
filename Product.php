<?php if(!defined('BASEPATH')) exit('No direct script access allowed');
class Product extends CI_Controller {

    public function __Construct() {
		parent::__Construct();
		if($this->session->userdata('user_id') == ''){
			redirect('login');
        }
		$this->load->library('form_validation');
        $this->load->model('common');
	}

    public function index()	{
		$data['page_title'] = $this->lang->line('bpartner_product');
        $data['sub_title'] = $this->lang->line('bpartner_product');
       // $where = array('status'=>1);
       $where = array();
        if($this->input->post('company_code')){
            $where['company_code'] = $this->input->post('company_code');
        }
        if($this->input->post('branch_code')){
            $where['branch_code'] = $this->input->post('branch_code');
        }
        if($this->input->post('product_name')){
            $where['name'] = $this->input->post('product_name');
        }
        $data['products'] = $this->common->gettbldata($where,'*','tb_products_master',0,0);
		$this->newtemplate->dashboard("product/index", $data);
    }
    
    public function add()   {
        $data['page_title'] = $this->lang->line('bpartner_product');
        $data['sub_title'] = $this->lang->line('bpartner_product');
		$company_code = $this->session->userdata('company_code');
        $branch_code = $this->session->userdata('branch_code');
        $data['company_code'] = $company_code;
        $data['branch_code'] = $branch_code;
        if($_POST){
            $cmp = $this->input->post('company_code');
            if($cmp == ""){
                $cmp = $company_code;
            }
            $brnch = $this->input->post('branch_code');
            if($brnch == ""){
                $brnch = $branch_code;
            }
            $user = $this->common->gettblrowdata(array('company_code'=>$cmp,'branch_code'=>$brnch),'id','tb_users',0,0);
            $user['id'] = 0;
            if(count($user)>0){
                $user['id'] = $user['id'];
            }
            $array['company_code'] = $this->input->post('company_code');
            $array['branch_code'] = $this->input->post('branch_code');
            $array['user_id'] = $user['id'];
            $array['name'] = $this->input->post('product_name');
            $array['status'] = $this->input->post('status');
            $array['updatedon'] = $array['craetedon'] = date('Y-m-d H:i:s');
            $this->common->insertTableData('tb_products_master',$array);
            redirect('product');
        } else {
            $this->newtemplate->dashboard('product/add', $data);
        }
    }

    public function edit($id) {
        $data['page_title'] = $this->lang->line('bpartner_product');
        $data['sub_title'] = $this->lang->line('bpartner_product');
        $data['product'] = $this->common->gettblrowdata(array('id'=>$id),'id,company_code,branch_code,name,status','tb_products_master',0,0);
        if(isset($data['product']['id'])) {
            if($_POST){
                $cmp = $this->input->post('company_code');
                if($cmp == ""){
                    $cmp = $company_code;
                }
                $brnch = $this->input->post('branch_code');
                if($brnch == ""){
                    $brnch = $branch_code;
                }
                $user = $this->common->gettblrowdata(array('company_code'=>$cmp,'branch_code'=>$brnch),'id','tb_users',0,0);
                $user['id'] = 0;
                if(count($user)>0){
                    $user['id'] = $user['id'];
                }
                $array['company_code'] = $this->input->post('company_code');
                $array['branch_code'] = $this->input->post('branch_code');
                $array['user_id'] = $user['id'];
                $array['name'] = $this->input->post('product_name');
                $array['status'] = $this->input->post('status');
                $array['updatedon'] = date('Y-m-d H:i:s');
               // print_r($array); exit;
                $this->common->updatetbledata('tb_products_master',$array,array('id'=>$id));
                redirect('product');
            } else {
                $this->newtemplate->dashboard('product/edit', $data);
            }
        } else {
            show_error('The Product you are trying to Edit does not exist.');
        }
    }

    public function deleteproduct($id){
		if($id != '' || $id != 0){
            $chk = $this->common->gettblrowdata(array('id'=>$id),'id','tb_products_master',0,0);
			if($chk){
				$upd = $this->common->updatetbledata('tb_products_master',array('status'=>0),array('id'=>$id));
				if($upd){
					echo "1";
				}else{
					echo "0";
				}
			}else{
				echo "0";
			}
		}
    }

    public function view($id){
        $data['page_title'] = $this->lang->line('bpartner_product');
        $data['sub_title'] = $this->lang->line('bpartner_product');
        $data['product'] = $this->common->gettblrowdata(array('id'=>$id),'id,company_code,branch_code,name,status','tb_products_master',0,0);
        $this->newtemplate->dashboard('product/view', $data);
    }
  
}

