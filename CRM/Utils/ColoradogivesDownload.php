<?php

class CRM_Utils_ColoradogivesDownload {
    var $cookies = "";
    var $file_name = "Export-anonymized.xls";
    var $username = "";
    var $password = "";
    var $org_id   = "";
    
    function __construct() {
        $this->cookies = sys_get_temp_dir() . DIRECTORY_SEPARATOR. $_SERVER['DOMAIN'] . '_cookies.txt';
        unlink($this->cookies);
        $settings = CRM_Utils_Coloradogives::getSettings();
        if ( empty($settings) || count($settings) < 3 ) {
            echo "Please check username, password and organization id";
            exit;
        }
        // put your Twilio API credentials here
        $this->username = $settings['coloradogives_username'];
        $this->password = $settings['coloradogives_password'];
        $this->org_id   = $settings['coloradogives_orgid'];
    }
    
    function urlify( $fields ) {
        $string = '';
        foreach($fields as $key => $value) {
            $string .= urlencode($key).'='.urlencode($value).'&';
        }
        return rtrim($string, '&');
    }
    
    function curl_setup($url) {
        // Initialize the curl library and enables cookies
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
        curl_setopt($ch, CURLOPT_COOKIEJAR      , $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE     , $this->cookies);
        curl_setopt($ch, CURLOPT_USERAGENT      , "Mozilla/5.0 (X11; Linux i686; rv:26.0) Gecko/20100101 Firefox/26.0" );
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION , 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST , 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , 0);
        return $ch;
    
    }
    
    function download($file_name) {
        // STEP 1
        $url = "https://www.coloradogives.org";
        $curl_session = $this->curl_setup($url);
        $page = curl_exec($curl_session);
        
        // STEP 2
        $url          = "https://www.coloradogives.org/admin/index.php?action=userLogin";
        $login_details = array(
                              'login[type]'     => 1,
                              'login[email]'    => $this->username,
                              'login[password]' => $this->password,
                              'login_type'      => 'nonprofit'
                              );
        $login_detail = $this->urlify($login_details); 
        $curl_session = $this->curl_setup($url);
        curl_setopt($curl_session, CURLOPT_POST       , true);
        curl_setopt($curl_session, CURLOPT_POSTFIELDS , $login_detail);
        curl_exec($curl_session);
        
        // STEP 3
        $url          = "https://www.coloradogives.org/admin/index.php?section=Organizations.donationInformation.donations&action=list&fwID=". $this->org_id;
        $curl_session = $this->curl_setup($url);
        $page = curl_exec($curl_session);
        preg_match_all('/index\.php\?ajaxRequest=\d{1,2}&ajaxFunction=export/mi', $page, $match);
        $exportUrl = $match[0][0];
        
        // STEP 4
        $url          = "https://www.coloradogives.org/admin/". $exportUrl;
        $curl_session = $this->curl_setup($url);
        $page = curl_exec($curl_session);
        $info = curl_getinfo($curl_session);
        $xls_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $file_name;
        // Save Xls file
        file_put_contents($xls_path, $page );
    }
}
