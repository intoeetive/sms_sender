<?php  if (!defined('BASEPATH')) exit('No direct script access allowed');
	
	
class Clickatell
{

    var $config;

    /**
     * Class constructor - loads CodeIgnighter and Configs
     */
    public function __construct($config = array())
    {
        $this->EE =& get_instance();

        $this->config = $config;
    }

 
 
    public function send($to, $message)
    {
        $this->EE->load->library('curl');
		
		$this->EE->curl->option('HTTPAUTH', CURLAUTH_BASIC);
 		$this->EE->curl->option('SSLVERSION', 3);
		$this->EE->curl->option('SSL_VERIFYPEER', FALSE);
		$this->EE->curl->option('SSL_VERIFYHOST', FALSE);
		
		$this->EE->curl->option('HEADER', FALSE);
		$this->EE->curl->option('RETURNTRANSFER', true);
	        		
		if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off'))
        {
            $this->EE->curl->option('FOLLOWLOCATION', true);
        }
        
        $data = array(
			'to'		=> $to,
			'api_id'	=> $this->config['sms_api_id'],
			'user'		=> $this->config['sms_username'],
			'password'	=> $this->config['sms_password'],
			'text'		=> $message
		);


		$res = $this->EE->curl->simple_post("https://api.clickatell.com/http/sendmsg", $data);	
		
		$arr = explode(':',$res);

        if ($arr[0] == 'ID')
        {
            $message_id = $arr[1];
            return $message_id;
        }
        else
        {
            return FALSE;
        }               
    }
    
  
}