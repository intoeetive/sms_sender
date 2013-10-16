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

class Sms_sender_upd {

    var $version = SMSSENDER_ADDON_VERSION;
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function install() { 
		
		$this->EE->load->dbforge(); 
        
        //----------------------------------------
		// EXP_MODULES
		// The settings column, Ellislab should have put this one in long ago.
		// No need for a seperate preferences table for each module.
		//----------------------------------------
		if ($this->EE->db->field_exists('settings', 'modules') == FALSE)
		{
			$this->EE->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
		}
        
        $settings = array(
			'sms_gateway' 		=> 'twilio',
			'sms_username'		=> '',
			'sms_password'		=> '',
			'sms_api_id'		=> '',
			'sms_from_number'	=> '',
		);

        $data = array( 'module_name' => 'Sms_sender' , 'module_version' => $this->version, 'has_cp_backend' => 'y', 'has_publish_fields' => 'n', 'settings'=> serialize($settings) ); 
        $this->EE->db->insert('modules', $data); 
        
        $data = array( 'class' => 'Sms_sender' , 'method' => 'send' ); 
        $this->EE->db->insert('actions', $data); 
        
        //exp_sms_sender_messages
        $fields = array(
			'sms_id'			=> array('type' => 'INT',		'unsigned' => TRUE, 'auto_increment' => TRUE),
            'sender_member_id'	=> array('type' => 'INT',		'unsigned' => TRUE),
			'message_text'		=> array('type' => 'VARCHAR',	'constraint'=> 255,	'default' => '')
		);

		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('sms_id', TRUE);
        $this->EE->dbforge->add_key('sender_member_id');
		$this->EE->dbforge->create_table('sms_sender_messages', TRUE);
        
        //exp_sms_sender_recipients
        $fields = array(
			'sms_id'			=> array('type' => 'INT',		'unsigned' => TRUE, 'auto_increment' => TRUE),
            'recipient'			=> array('type' => 'VARCHAR',	'constraint'=> 100,	'default' => ''),
			'sending_date'		=> array('type' => 'INT',		'unsigned' => TRUE, 'default' => 0),
            'status'	       	=> array('type' => 'VARCHAR',	'constraint'=> 50,	'default' => '')
		);

		$this->EE->dbforge->add_field($fields);
		$this->EE->dbforge->add_key('sms_id');
		$this->EE->dbforge->create_table('sms_sender_recipients', TRUE);
        
        return TRUE; 
        
    } 
    
    function uninstall() { 

        $this->EE->load->dbforge(); 
		
		$this->EE->db->select('module_id'); 
        $query = $this->EE->db->get_where('modules', array('module_name' => 'Sms_sender')); 
        
        $this->EE->db->where('module_id', $query->row('module_id')); 
        $this->EE->db->delete('module_member_groups'); 
        
        $this->EE->db->where('module_name', 'Sms_sender'); 
        $this->EE->db->delete('modules'); 
        
        $this->EE->db->where('class', 'Sms_sender'); 
        $this->EE->db->delete('actions'); 
        
        $this->EE->dbforge->drop_table('sms_sender_messages');
        $this->EE->dbforge->drop_table('sms_sender_recipients');
        
        return TRUE; 
    } 
    
    function update($current='') 
	{ 
        return TRUE; 
    } 
	

}
/* END */
?>