<?php

/*
=====================================================
 SMS Sender
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2013 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: mcp.sms_sender.php
-----------------------------------------------------
 Purpose: Send SMS directly from ExpressionEngine site
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'sms_sender/config.php';

class Sms_sender_mcp {

    var $version = SMSSENDER_ADDON_VERSION;
    
    var $settings = array();
    
    var $perpage = 25;
    
    var $site_id = 1;
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
        
        if ($this->EE->config->item('app_version')>=260)
        {
        	$this->EE->view->cp_page_title = lang('sms_sender_module_name');
        }
        else
        {
        	$this->EE->cp->set_variable('cp_page_title', lang('sms_sender_module_name'));
        }
    } 
    
    
    function index()
    {
        return $this->settings();
        
        $this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');

    	$vars = array();
        
        if ($this->EE->input->get_post('perpage')!==false)
        {
        	$this->perpage = $this->EE->input->get_post('perpage');	
        }
        $vars['selected']['perpage'] = $this->perpage;
        
        $vars['selected']['search'] = $this->EE->input->get_post('search');
        
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        $this->EE->db->select('userphone.member_id, phone, screen_name')
        			->from('userphone')
					->join('members', 'members.member_id=userphone.member_id', 'left');
		if ($vars['selected']['search']!='')
		{
			$this->EE->db->like('username', $vars['selected']['search']);
			$this->EE->db->or_like('screen_name', $vars['selected']['search']);
			$this->EE->db->or_like('email', $vars['selected']['search']);
		}
		
		if ($this->perpage!=0)
		{
        	$this->EE->db->limit($this->perpage, $vars['selected']['rownum']);
 		}

        $query = $this->EE->db->get();
        $vars['total_count'] = $query->num_rows();
        
        $vars['table_headings'] = array(
                        $this->EE->lang->line('member'),
                        $this->EE->lang->line('phone')
        			);
		   
		$i = 0;
        foreach ($query->result_array() as $row)
        {
           $vars['data'][$i]['member'] = "<a href=\"".BASE.AMP.'C=myaccount'.AMP.'id='.$row['member_id']."\">".$row['screen_name']."</a>";
           $vars['data'][$i]['phone'] = $row['phone'];
           $i++;
 			
        }

		if ($this->perpage==0)
		{
        	$total = $query->num_rows();
 		}
 		else
 		{
 			$this->EE->db->select('COUNT(*) AS count');
	        $this->EE->db->from('userphone');
	        $q = $this->EE->db->get();
	        
	        if ($vars['selected']['search']!='')
			{
				$this->EE->db->like('username', $vars['selected']['search']);
				$this->EE->db->or_like('screen_name', $vars['selected']['search']);
				$this->EE->db->or_like('email', $vars['selected']['search']);
			}
	        
	        $total = $q->row('count');
 		}

        $this->EE->load->library('pagination');

        $base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=userphone'.AMP.'method=index';
        $base_url .= AMP.'perpage='.$vars['selected']['perpage'];
        if ($vars['selected']['search']!='') $base_url .= AMP.'search='.$vars['selected']['search'];

        $p_config = $this->_p_config($base_url, $total);

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
    	return $this->EE->load->view('index', $vars, TRUE);
	
    }    
    
    

    
    function settings()
    {
		
        $query = $this->EE->db->select('settings')->from('modules')->where('module_name', 'Sms_sender')->limit(1)->get();
        $settings = unserialize($query->row('settings')); 
		
		$this->EE->load->helper('form');
    	$this->EE->load->library('table');
    	
    	$gateways = array(
			'twilio'		=> lang('twilio'),
			//'clickatell'	=> lang('clickatell'),
			//'sms_global'	=> lang('sms_global'))
		);
        
        $member_groups = array();
        $this->EE->db->select('group_id, group_title');
        $this->EE->db->where('group_id NOT IN (1,2,3,4)');
        $q = $this->EE->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            $member_groups[$obj->group_id] = $obj->group_title;
        }

 
        $vars['settings'] = array(	
			'groups_can_send'	=> form_multiselect('groups_can_send[]', $member_groups, $settings['groups_can_send']),
            'sms_gateway'	=> form_dropdown('sms_gateway', $gateways, $settings['sms_gateway'], 'id="sms_gateway"'),
            'sms_username'	=> form_input('sms_username', $settings['sms_username']),
            'sms_password'	=> form_input('sms_password', $settings['sms_password']),
            'sms_api_id'	=> form_input('sms_api_id', $settings['sms_api_id'], 'id="sms_api_id"'),
            'sms_from_number'=> form_input('sms_from_number', $settings['sms_from_number'])
    		);
		
		$js = "
		
function load_labels(gateway){
	switch(gateway){
		case 'twilio':
			username = '".lang('twilio_username')."';
			password = '".lang('twilio_password')."';
			break;	
		case 'clickatell':
			username = '".lang('clickatell_username')."';
			password = '".lang('clickatell_password')."';
			break;	
		case 'sms_global':
			username = '".lang('sms_global_username')."';
			password = '".lang('sms_global_password')."';
			break;	
		case 'textmagic':
			username = '".lang('textmagic_username')."';
			password = '".lang('textmagic_password')."';
			break;	
		default:
			username = '".lang('sms_username')."';
			password = '".lang('sms_password')."';
			break;	
	}
	$('label[for=sms_username]').text(username);
	$('label[for=sms_password]').text(password);
	if (gateway=='clickatell'){
		$('#sms_api_id').parent().parent().show();
	}
	else
	{
		$('#sms_api_id').parent().parent().hide();
	}
}		
		
$(document).ready(function() {
	load_labels($('#sms_gateway').val());
	$('#sms_gateway').change(function(){
		load_labels($(this).val());
	});
});
		";
		
		$this->EE->javascript->output($js);
        
    	return $this->EE->load->view('settings', $vars, TRUE);
	
    }    
    
    function save_settings()
    {
		
		if (empty($_POST))
    	{
    		show_error($this->EE->lang->line('unauthorized_access'));
    	}

        unset($_POST['submit']);
        
        $this->EE->db->where('module_name', 'Sms_sender');
    	$this->EE->db->update('modules', array('settings' => serialize($_POST)));
    	
    	$this->EE->session->set_flashdata(
    		'message_success',
    	 	$this->EE->lang->line('preferences_updated')
    	);
        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=sms_sender'.AMP.'method=settings');
    }
    
    
    
    
    
    function _p_config($base_url, $total_rows)
    {
        $p_config = array();
        $p_config['base_url'] = $base_url;
        $p_config['total_rows'] = $total_rows;
		$p_config['per_page'] = $this->perpage;
		$p_config['page_query_string'] = TRUE;
		$p_config['query_string_segment'] = 'rownum';
		$p_config['full_tag_open'] = '<p id="paginationLinks">';
		$p_config['full_tag_close'] = '</p>';
		$p_config['prev_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif" width="13" height="13" alt="&lt;" />';
		$p_config['next_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif" width="13" height="13" alt="&gt;" />';
		$p_config['first_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif" width="13" height="13" alt="&lt; &lt;" />';
		$p_config['last_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif" width="13" height="13" alt="&gt; &gt;" />';
        return $p_config;
    }
    
 

}
/* END */
?>