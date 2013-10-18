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


class Sms_sender {

    var $return_data	= ''; 	

    /** ----------------------------------------
    /**  Constructor
    /** ----------------------------------------*/

    function __construct()
    {        
    	$this->EE =& get_instance(); 
    	
    	$this->EE->lang->loadfile('sms_sender');  
    }
    /* END */
    
    
    
    
    function form()
    {
    	$query = $this->EE->db->select('settings')->from('modules')->where('module_name', 'Sms_sender')->limit(1)->get();
        $settings = unserialize($query->row('settings')); 

        if ($this->EE->session->userdata('group_id')!=1 && !in_array($this->EE->session->userdata('group_id'), $settings['groups_can_send']))
    	{
    		return $this->EE->TMPL->no_results();
    	}
    	
    	$tagdata = $this->EE->TMPL->tagdata;
    	
		if ($this->EE->TMPL->fetch_param('return')=='')
        {
            $return = $this->EE->functions->fetch_site_index();
        }
        else if ($this->EE->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = $this->EE->functions->fetch_current_uri();
        }
        else if (strpos($this->EE->TMPL->fetch_param('return'), "http://")!==FALSE || strpos($this->EE->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = $this->EE->TMPL->fetch_param('return');
        }
        else
        {
            $return = $this->EE->functions->create_url($this->EE->TMPL->fetch_param('return'));
        }
        
        $data['hidden_fields']['ACT'] = $this->EE->functions->fetch_action_id('Sms_sender', 'send');
		$data['hidden_fields']['RET'] = $return;
        $data['hidden_fields']['PRV'] = $this->EE->functions->fetch_current_uri();
        
        if ($this->EE->TMPL->fetch_param('ajax')=='yes') $data['hidden_fields']['ajax'] = 'yes';
        if ($this->EE->TMPL->fetch_param('skip_success_message')=='yes')
        {
            $data['hidden_fields']['skip_success_message'] = 'y';
        }
									      
        $data['id']		= ($this->EE->TMPL->fetch_param('id')!='') ? $this->EE->TMPL->fetch_param('id') : 'sms_sender_form';
        $data['name']		= ($this->EE->TMPL->fetch_param('name')!='') ? $this->EE->TMPL->fetch_param('name') : 'sms_sender_form';
        $data['class']		= ($this->EE->TMPL->fetch_param('class')!='') ? $this->EE->TMPL->fetch_param('class') : 'sms_sender_form';
		
        $out = $this->EE->functions->form_declaration($data).$tagdata."\n"."</form>";
        
        return $out;
    }
    
    
    
    function send()
    {
		$query = $this->EE->db->select('settings')->from('modules')->where('module_name', 'Sms_sender')->limit(1)->get();
        $settings = unserialize($query->row('settings')); 

        if ($this->EE->session->userdata('group_id')!=1 && !in_array($this->EE->session->userdata('group_id'), $settings['groups_can_send']))
    	{
    		if ($this->EE->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".$this->EE->lang->line('unauthorized_access');
                exit();
            }
			$this->EE->output->show_user_error('general', lang('unauthorized_access'));
    	}
    	
    	if ($this->EE->input->post('message')=='')
    	{
    		if ($this->EE->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".$this->EE->lang->line('message_empty');
                exit();
            }
			$this->EE->output->show_user_error('general', lang('message_empty'));
    	}
        else
        {
            $message = $this->EE->input->post('message');
        }
        
        
        
        if ($this->EE->input->post('recipients')=='')
    	{
    		if ($this->EE->input->get_post('ajax')=='yes')
            {
                echo lang('error').": ".$this->EE->lang->line('recipients_empty');
                exit();
            }
			$this->EE->output->show_user_error('general', lang('recipients_empty'));
    	}
        
        
        //build a list of recipients
        $recipients = array();
        $recipients_a = explode("\n", $this->EE->input->post('recipients'));
        foreach ($recipients_a as $recipient)
        {
            $comma_a = explode(",", $recipient);
            foreach ($comma_a as $recip)
            {
                $recipients[] = trim($recip);
            }
        }
        
        //exclude possible duplicates
        //those received exactly same message within last 1 hour
        $duplicates = array();
        $q = $this->EE->db->select('sms_id')
                ->from('sms_sender_messages')
                ->where('message_text', $message)
                ->get();
        if ($q->num_rows()>0)
        {
            $this->EE->db->select('recipient')
                ->from('sms_sender_recipients')
                ->where('sms_id', $q->row('sms_id'))
                ->where_in('recipient', $recipients)
                ->where('sending_date > ', ($this->EE->localize->now - 60*60*1))
                ->where('status', 'ok');
            $check_q = $this->EE->db->get();
            if ($check_q->num_rows()>0)
            {
                foreach ($check_q->result_array() as $row)
                {
                    $duplicates[] = $row['recipient'];
                }
                $recipients = array_diff($recipients, $duplicates);
            }
        }
        
        $gateway = $settings['sms_gateway'];
        $this->EE->load->library($gateway, $settings);
        
        $data = array(
            'sender_member_id'	=> $this->EE->session->userdata('member_id'),
			'message_text'		=> $message
		);
        $this->EE->db->insert('sms_sender_messages', $data);
        $sms_id = $this->EE->db->insert_id();
        
        $success_count = 0;
        $fail_count = 0;
        foreach ($recipients as $phone)
        {
            $ok = $this->EE->$gateway->send($phone, $message);
            $data = array(
        			'sms_id'        => $sms_id,
                    'recipient'		=> $phone,
        			'sending_date'	=> $this->EE->localize->now
        		);
            if ($ok!=false)
            {
                $data['status'] = 'ok';
                $success_count++;
            }
            else
            {
                $fail_count++;
            }
            $this->EE->db->insert('sms_sender_recipients', $data);
        }
    	
        $success_msg = str_replace("%success%", $success_count, $this->EE->lang->line('success_msg'));
        $success_msg = str_replace("%fail%", $fail_count, $success_msg);
        $success_msg = str_replace("%skip%", count($duplicates), $success_msg);

    	//return
    	
		if ($this->EE->input->get_post('ajax')=='yes')
        {
            echo $success_msg;
            exit();
        }
        
        $return = ($this->EE->input->get_post('RET')!==false)?$this->EE->input->get_post('RET'):$this->EE->config->item('site_url');
        $site_name = ($this->EE->config->item('site_name') == '') ? $this->EE->lang->line('back') : stripslashes($this->EE->config->item('site_name'));
        
        if ($this->EE->input->get_post('skip_success_message')=='y')
        {
        	$this->EE->functions->redirect($return);
        }
            
        $data = array(	'title' 	=> $this->EE->lang->line('success'),
        				'heading'	=> $this->EE->lang->line('success'),
        				'content'	=> $success_msg,
        				//'redirect'	=> $return,
        				'link'		=> array($return, $site_name),
                        //'rate'		=> 3
        			 );
			
		$this->EE->output->show_message($data);
		
    }
    
    
    
    function log()
    {
		$query = $this->EE->db->select('settings')->from('modules')->where('module_name', 'Sms_sender')->limit(1)->get();
        $settings = unserialize($query->row('settings')); 
        
        if ($this->EE->session->userdata('group_id')!=1 && !in_array($this->EE->session->userdata('group_id'), $settings['groups_can_send']))
        {
            return $this->EE->TMPL->no_results();
        }
        
        $this->EE->db->select('members.member_id, members.username, members.screen_name, message_text, recipient, sending_date, status')
            ->from('sms_sender_messages')
            ->join('members', 'members.member_id=sms_sender_messages.sender_member_id', 'left')
            ->join('sms_sender_recipients', 'sms_sender_messages.sms_id=sms_sender_recipients.sms_id', 'left');
        if ($this->EE->TMPL->fetch_param('sender_id')!==false)
        {
            if (in_array($this->EE->TMPL->fetch_param('sender_id'), array("CURRENT_USER", "{member_id}", "{logged_in_member_id}")))
            {
                $this->EE->db->where('sender_member_id', $this->EE->session->userdata('member_id'));
            }
            else
            {
                $this->EE->db->where('sender_member_id', $this->EE->TMPL->fetch_param('sender_id'));
            }
        }
        
        if ($this->EE->TMPL->fetch_param('recipient')!==false)
        {
            $this->EE->db->like('recipient', $this->EE->TMPL->fetch_param('recipient'));
        }
        
        if ($this->EE->TMPL->fetch_param('date_from')!==false)
        {
            $this->EE->db->where('sending_date > ', $this->EE->localize->string_to_timestamp($this->EE->TMPL->fetch_param('date_from')));
        }
        
        if ($this->EE->TMPL->fetch_param('date_to')!==false)
        {
            $this->EE->db->where('sending_date < ', $this->EE->localize->string_to_timestamp($this->EE->TMPL->fetch_param('date_to')));
        }
        
        $query = $this->EE->db->get();
        
        if ($query->num_rows()==0)
        {
            return $this->EE->TMPL->no_results();
        }
        
		$variables = array();

		foreach ($query->result_array() as $row)
		{
	        if ($row['status']=='') $row['status'] = 'error';
            $variables[] = $row;
		}
		
		$output = $this->EE->TMPL->parse_variables(trim($this->EE->TMPL->tagdata), $variables);
		
		return $output;
		
	}

    

}
/* END */
?>