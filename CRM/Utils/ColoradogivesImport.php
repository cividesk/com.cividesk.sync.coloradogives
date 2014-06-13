<?php


class CRM_Utils_ColoradogivesImport {
    
    // Last Import Date
    var $last_import_date = 0;
    
    // Next Import Date
    var $next_import_date = '';
    // Column Header of XLS
    var $column_header = array();
    
    // Defualt Location type id
    var $location_type = '';
    
    // Country array
    var $country_list = array();
    
    // Contribution Type id
    var $contribution_type_id = '';
    
    // Payment Instrument ID
    var $payment_instrument_id = '';
    
    // Honor Type array
    var $honor_type = array();
    
    // Count of New contacts
    var $contact_new_count  = 0;
    
    // Count of Existing contacts
    var $contact_existig_count = 0;

    // Count of New Contribution
    var $contribution_new_count = 0;
    
    // Count of Existing Contribution
    var $contribution_existing_count = 0;
    
        // Count of New Recurring Contribution
    var $contribution_recur_new_count = 0;
    
    // Count of Existing Recurring Contribution
    var $contribution_recur_existing_count = 0;
    
    // Count of New Campaign
    var $campaign_new_count = 0;
    
    // Count of existing Campaign
    var $campaign_existing_count;
    
    var $file_name = "Export-anonymized.xls";
    
    function __construct() {
        $this->get_last_import_date();
        $this->donation_type();
        $this->payment_instrument();
        $this->location_type();
        $this->honor_type();
    }
    
    /*
     * Create/ Get Donation (contribution) Type
     */
    function donation_type() {
        if ( CRM_Core_DAO::checkTableExists('civicrm_contribution_type') ) {
            $contributionType = CRM_Contribute_PseudoConstant::contributionType();
        } else {
            $contributionType = CRM_Contribute_PseudoConstant::financialType();
        }
        if ( $key = CRM_Utils_Array::key('Donation', $contributionType) ) {
            $this->contribution_type_id = $key;
        } else {
            if ( CRM_Core_DAO::checkTableExists('civicrm_contribution_type') ) {
                $dao = new CRM_Contribute_DAO_ContributionType();
            } else {
-               $dao = new CRM_Financial_DAO_FinancialType();
            }
            $dao->name      = 'Donation';
            $dao->is_active = 1;
            $result = $dao->save();
            $this->contribution_type_id = $result->id;
        }
    }

    /*
     * Create / Get Location Type
     */
    function location_type() {
        $defaultLocationType = CRM_Core_BAO_LocationType::getDefault();
        $this->location_type = $defaultLocationType->id;
    }
    
    /*
     * Get Honoy Types
     */
    function honor_type() {
        $honorType = civicrm_api3('contribution', 'getoptions', array('field' => 'honor_type_id'));
        $this->honor_type = $honorType['values'];
    }
    /*
     * Create /Get Payment Instrument ID
     * 
     */
    function payment_instrument() {
        $paymentInstrument = CRM_Contribute_PseudoConstant::paymentInstrument();
        if ( $key = CRM_Utils_Array::key('Colorado Gives', $paymentInstrument) ) {
            $this->payment_instrument_id = $key;
        } else {
            $group_id = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_OptionGroup', 'payment_instrument', 'id', 'name');
            $sql      = "select max(value) as value, max(weight) as weight from civicrm_option_value where option_group_id={$group_id} limit 1";
            $dao      = CRM_Core_DAO::executeQuery($sql);
            while ($dao->fetch(true)) {
                $value  = $dao->value  + 1;
                $weight = $dao->weight + 1;
                $dao    = new CRM_Core_DAO_OptionValue();
                $dao->option_group_id = $group_id;
                $dao->value           = $value;
                $dao->weight          = $weight;
                $dao->label           = 'Colorado Gives';
                $dao->name            = 'Colorado Gives';
                $dao->is_active       = 1;
                $result = $dao->save();
                $this->payment_instrument_id = $result->id;
            }
        }
    }
    
    
    /*
     * Parase XSL uinsg PHPExcel Library
     */
    function paraseXls() {
        // Include Excel library
        require_once 'PHPExcel/IOFactory.php';
        
        // Path of xls file ( absolute path )
        //$file_name = "Export-anonymized.xls";
       
        //echo "\nLoading file " . $this->file_name . '<br/>';
        
        $realpath      = dirname(__FILE__);
        $inputFileName = $realpath. DIRECTORY_SEPARATOR .  'files'. DIRECTORY_SEPARATOR. $this->file_name;
        $objPHPExcel   = PHPExcel_IOFactory::load($inputFileName);
        //echo "\n<br/>import file<br/>\n";
        //echo '<hr />';
        $sheetData = $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
        
        // Get First row as header
        $data_header = array_shift($sheetData);
        $this->column_header = $data_header;

        // Filter data using last import date
        $filter_data = $this->filter_data($sheetData);
        return $this->process_data($filter_data);
        
    }
    
    
    /*
     * Get Last Import Date
     */
    function get_last_import_date() {
        $sql = "select value as last_import_date from civicrm_setting where group_name ='Import Job' AND name= 'last_import_date'";
        $last_import_date = CRM_Core_DAO::singleValueQuery($sql);
        $this->last_import_date = $last_import_date;
    }
    
    /*
     * Set next Import Date
     */
    function set_next_import_date() {
        $sql = "update civicrm_setting set value = '{$this->next_import_date}' where group_name ='Import Job' AND name = 'last_import_date' ";
        CRM_Core_DAO::singleValueQuery($sql);
    
    }
    /*
     * Filter XLS data using Last import Date
     */
    function filter_data($sheetData) {

        $last_date   = $this->last_import_date;
        $recent_date = 0;
        $data_array  = array();
        $filter_data = array_filter($sheetData, 
                                    function($data) use($last_date, &$data_array, &$recent_date) {
                                        $date = date('Ymd', strtotime($data['A']));
                                        // filter data using last import date
                                        if ( $date > $last_date ) {
                                            $email_index = $data['G'] ? $data['G'] : 'XX';
                                            $data_array[$email_index][] =$data;
                                            // Get most recent date from xls used for set last import date after completion of import job
                                            if ($date > $recent_date ) {
                                                $recent_date = $date;
                                            }
                                            return $data;
                                        } else {
                                            return '';
                                        }
                                    }
                                    );
        $this->next_import_date = $recent_date? $recent_date:$last_date;
        return $data_array;
    }
    
    
    /*
     * Process All XLS Data
     * Create Contact. Contribution, Campaign, Honor Contact, Soft Contribution Contact
     * @param $filtered_data
     * @return void
     */
    function process_data($filtered_data) {

        // Populate contry list
        $countryName = array();
        CRM_Core_PseudoConstant::populate($countryNames, 'CRM_Core_DAO_Country', TRUE, 'name', 'is_active' );
        $this->country_list = $countryNames;
        
        $this->message('Get Last Import date : ') . $this->last_import_date;
        foreach($filtered_data as $email_key => $grouped_data ) {
            foreach( $grouped_data as $values ) {
                try {
                    $contact_id = $this->create_contact($values);
                    if ( $contact_id ) {
                        $this->create_contribution($values, $contact_id);
                    }
                } catch (Exception $e) {
                    $mes = 'process_data Caught exception: '. $e->getMessage();
                    crm_core_error::debug('Exception', $mes);
                }
            }
        }
        $this->message('set Next Import date : ') . $this->next_import_date;
        $this->set_next_import_date();
        return $this->display_log();
    }
    
    function display_log() {
        $mesg = $this->message("Import Logs");
        $mesg .= $this->message("=====================Last Import Date===========================");
        $mesg .= $this->message("Last Import Date  : ")     . $this->last_import_date;
        $mesg .= $this->message("=====================Contact Logs===========================");
        $mesg .= $this->message("New Contact  : ")     . $this->contact_new_count;
        $mesg .= $this->message("Exsiting Contact : ") . $this->contact_existig_count;
        $mesg .= $this->message("=====================Contribution Logs===========================");
        $mesg .= $this->message("New Contribution :")       . $this->contribution_new_count;
        $mesg .= $this->message("Exsiting Contribution : ") . $this->contribution_existing_count;
        $mesg .= $this->message("=====================Recurring Contribution Logs===========================");
        $mesg .= $this->message("New Recurring Contribution :")       . $this->contribution_recur_new_count;
        $mesg .= $this->message("Exsiting Recurring Contribution : ") . $this->contribution_recur_existing_count;
        $mesg .= $this->message("=====================Campaign Logs===========================");
        $mesg .= $this->message("New Campaign : ")     . $this->campaign_new_count;
        $mesg .= $this->message("Exsiting Campaign :") . $this->campaign_existing_count;
        $mesg .= $this->message("=====================Next Import Date===========================");
        $mesg .= $this->message("Next Import Date  : ")     . $this->next_import_date;        
        return $mesg;
    }
    
    function message($str) {
        return $str = "\n<br/>". $str . "<br/>\n";
    }
    
    
    /*
     * Function to Create Contact
     * @param $values All values in xls row
     * @return Contact ID
     */
    function create_contact($values) {
        $params = $this->create_contact_format_data($values);
        $result = civicrm_api( 'contact','create',$params );
        if (@$result['is_error'] == 0 ) {
            $this->increment_counter('contact_new_count');
            return $result['id'];
        } else if (@$result['is_error'] && @$result['error_code'] == 'duplicate' ) {
            $this->increment_counter('contact_existig_count');
            return $result['ids']['0'];
        }
    }
    
    
    /*
     * Function to Create Honor contact
     * @param $values All values in xls row
     * @return Contact ID of honor
     */
    function create_honor($values) {
        if ( $values['X'] && $values['Y']) {
            $param = array();
            $param['first_name']  = $values['X'];
            $param['last_name']   = $values['Y'];
            return $this->create_contact_other($param);
        }
        return '';
    }
    /*
     * Function to create contact using first and last name
     * @param $values All values in xls row
     * @return Contact ID
     */
    function create_contact_other($values) {
        $params = array(
                        'version'       => 3,
                        'contact_type'  => 'Individual',
                        'first_name'    => $values['first_name'],
                        'last_name'     => $values['last_name'],
                        'dupe_check'    => 1
                        );
         
        $result = civicrm_api( 'contact','get',$params );
        if ($result['count'] == 1 ) {
            $this->increment_counter('contact_existig_count');
            return $result['id'];
        } else if ($result['count'] > 1 ) {
            $this->increment_counter('contact_existig_count');
            $out = array_shift($result['values']);
            return $out['contact_id'];
        } else {
            try {
                $result = civicrm_api( 'contact','create',$params );
            } catch (Exception $e) {
                $mes =  'create_contact_other Caught exception: '. $e->getMessage();
                crm_core_error::debug('Exception', $mes);
                //crm_core_error::debug('$params', $params);
            }
        
            if (@$result['is_error'] == 0 ) {
                $this->increment_counter('contact_new_count');
                return $result['id'];
            } else if (@$result['is_error'] && @$result['error_code'] == 'duplicate' ) {
                return $result['ids']['0'];
            }
        }    
    }
    
    
    /*
     * Function to Format Prmimary contact
     * @param $values All values in xls row
     * @return $params Formated contact array
     */
    function create_contact_format_data(&$values) {
        
        $param_email = array();
        $param_address = array();
        $params = array(
                        'version'       => 3,
                        'contact_type'  => 'Individual',
                        'first_name'    => $values['D'],
                        'last_name'     => $values['E'],
                        'dupe_check'    => 1
                        );
                        
        if ($values['G'] && CRM_Utils_Rule::email($values['G'])) {
            $param_email = array('api.email.create' => 
                                 array(
                                       'email'            => $values['G'],
                                       'location_type_id' => $this->location_type,
                                       'is_primary'       => 1
                                       )
                                 );
        }
        $state_id = $country_id = 'null';
        if ($values['H'] || $values['I'] || $values['J'] || $values['L']) {
            if ( $values['M']) {
                if ($values['M'] == 'United States of America') {
                    $values['M'] = 'United States';
                }
                $country_id = CRM_Utils_Array::key($values['M'], $this->country_list);
            }
            if ($values['K'] && $country_id) {
                // Get state proviance list using country id
                $condition = " country_id = " .$country_id ;
                CRM_Core_PseudoConstant::populate($stateName, 'CRM_Core_DAO_StateProvince', TRUE, 'abbreviation', 'is_active',  $condition);
                $state_id  = CRM_Utils_Array::key($values['K'], $stateName);
            }
            
            $param_address = array('api.address.create' => 
                                   array(
                                         'location_type_id' => $this->location_type,
                                         'is_primary'       => 1,
                                         'is_billing'       => 0,
                                         'street_address'   => $values['H'],
                                         'street_name'      => $values['I'],
                                         'city'             => $values['J'],
                                         'postal_code'      => $values['L'],
                                         'country_id'       => $country_id,
                                         'state_province_id'=> $state_id,
                                         )
                                   );
        }
        $params = array_merge($params, $param_email, $param_address);
        return $params;
    }
    

    /*
     * Function to format and create contribution record
     * @param $values All values in xls row
     * @param $contact_id contact id
     * @return void
     */
    function create_contribution($values, $contact_id) {
        // We don't have any invoice and transaction id in xls, So not checking duplicate donation records
        //$this->check_existing_contribution($values, $contact_id);
        // Check and create Campaign 
        $campaign         = $this->create_campaign($values, $contact_id);
        // Check and create recurring record
        $recuring_id      = $this->create_recurring_contribution($values, $contact_id, $campaign);
        // Check and create Honor contact
        $honor_contact_id = $this->create_honor($values);
        
        $honor_type_id = '';
        if ( $honor_contact_id ) {
            if ( $values['W'] == 'In honor of someone') {
                $honor_type_id = CRM_Utils_Array::key('In Honor of', $this->honor_type);
            } else if ( $values['W'] == 'In honor of someone') {
                $honor_type_id = CRM_Utils_Array::key('In Memory of', $this->honor_type);
            }
        }
        
        $note = '';
        if ( $values['C'] && $values['C'] == 'Anonymous' ) {
            $note = "Anonymous\n";
        } else if ( $values['C'] && $values['C'] == 'Completely anonymous' ) {
            $note = "Completely anonymous\n";
        }
        
        if ( $values['AA'] ) {
            $note .= "Program :" . $values['AA'];
        } else {
            $note .= "Capital contribution";
        }
        
        $params = array( 
                        'contact_id'             => $contact_id,
                        'receive_date'           => CRM_Utils_Date::processDate($values['A']),
                        'total_amount'           => $values['B'],
                        'contribution_type_id'   => $this->contribution_type_id,
                        'payment_instrument_id'  => $this->payment_instrument_id,
                        'non_deductible_amount'  => '0',
                        'fee_amount'             => '0',
                        'net_amount'             => $values['B'],
                        'source'                 => $values['Z'] ? 'Colorado Gives: Gives Day ' .$values['B'] :  'Colorado Gives',
                        'contribution_status_id' => 1,
                        'honor_contact_id'       => $honor_contact_id ? $honor_contact_id: '',
                        'honor_type_id'          => $honor_type_id ? $honor_type_id : '',
                        'campaign_id'            => $campaign,
                        'contribution_recur_id'  => $recuring_id,
                        'version'                => 3,
                        'note'                   => $note
                         );
        require_once 'api/api.php';
        try {
            $result = civicrm_api( 'contribution','create',$params );
        } catch (Exception $e) {
            $mes =  'create_contribution Caught exception: '. $e->getMessage();
            crm_core_error::debug('Exception', $mes);
        }
        
        if ($result['is_error'] == 1 ) {
            return;
        }
               
        $this->increment_counter('contribution_new_count');
        
        if ( $values['C'] && $values['C'] == 'Completely anonymous' ) {
            $params = array();
            $params['first_name']  = 'Completely';
            $params['last_name']   = 'anonymous';
            $soft_contact_id = $this->create_contact_other($params);
            if ($soft_contact_id) {
                try {
                    $contributionSoftDAO = new CRM_Contribute_DAO_ContributionSoft();  
                    $contributionSoftDAO->contact_id      = $soft_contact_id;
                    $contributionSoftDAO->amount          = $result['values'][$result['id']]['total_amount'];
                    $contributionSoftDAO->currency        = $result['values'][$result['id']]['currency'];
                    $contributionSoftDAO->contribution_id = $result['id'];
                    $contributionSoftDAO->save();
                } catch (Exception $e) {
                    $mes =  'Create soft Contribution Caught exception: '. $e->getMessage();
                    crm_core_error::debug('Exception', $mes);
                }
            }
        }
    }
    
    /*
     *
     */
    function check_existing_contribution($values, $contact_id) {
        
        $params = array( 
                        'contact_id'             => $contact_id,
                        'receive_date'           => CRM_Utils_Date::processDate($values['A']),
                        'total_amount'           => $values['B'],
                        'contribution_type_id'   => $this->contribution_type_id,
                        'payment_instrument_id'  => $this->payment_instrument_id,
                        'non_deductible_amount'  => '0',
                        'fee_amount'             => '0',
                        'net_amount'             => $values['B'],
                        'contribution_status_id' => 1,
                        'version'                => 3
                         );

        require_once 'api/api.php';
        $result = civicrm_api( 'contribution','get',$params );
        exit;
        
    }
    
    /*
     * Function To create Campaing
     * @param $values all values in xls row
     * @param $contact_id Contact ID of Campagn Creator/owner
     * @return Campagn ID
     */
    function create_campaign($values, $contact_id) {
        if ( ! $values['O'] ) {
            return '';
        }
        $campaignType = CRM_Campaign_PseudoConstant::campaignType();
        
        $params = array( 
              'version'       => 3,
              'title'         => $values['O'],
              'description'   => $values['O'],
              'created_date'  => CRM_Utils_Date::processDate($values['A']),
              'campaign_type_id' => CRM_Utils_Array::key('Constituent Engagement', $campaignType),
              'created_id'    => $contact_id,
              'dupe_check'    => 1
              );
      
        require_once 'api/api.php';
        $result = civicrm_api( 'campaign','get',$params );
        if ($result['count'] == 1 ) {
            $this->increment_counter('campaign_existing_count');
            return $result['id'];
        } else if ($result['count'] > 1 ) { 
            $out = array_shift($result['values']);
            $this->increment_counter('campaign_existing_count');
            return $out['id'];
        } else {
            $result = civicrm_api( 'campaign','create',$params );
            if (@$result['is_error'] == 0 ) {
                $this->increment_counter('campaign_new_count');
                return $result['id'];
            } else if (@$result['is_error'] && @$result['error_code'] == 'duplicate' ) {
                return $result['ids']['0'];
            }
        }
    }
    
    /*
     * Function to create Recurring Contribution
     * @param $values all values in xls row
     * @param $contact_id Contact Id of Contributor
     * @param $campaign Campaign ID
     * @return Recurring ID
     */
    function create_recurring_contribution($values, $contact_id, $campaign) {
        if ( $values['P']) {
            $frequency_unit = "";
            if($values['P'] == 'Monthly') {
                $frequency_unit = "month";
            } else if($values['P'] == 'Weekly') {
                $frequency_unit = "week";
            } else if($values['P'] == 'Yearly') {
                $frequency_unit = "year";
            } else if($values['P'] == 'daily') {
                $frequency_unit = "day";
            }
            $values['V'] = str_replace(array('$', ','), '', $values['V']);  
            $contributionRecur = new CRM_Contribute_DAO_ContributionRecur();
            $contributionRecur->contact_id              = $contact_id;
            $contributionRecur->amount                  = $values['V'];
            $contributionRecur->currency                = 'USD';
            $contributionRecur->frequency_unit          = $frequency_unit;
            $contributionRecur->frequency_interval      = "";
            $contributionRecur->installments            = $values['P']; 
            $contributionRecur->start_date              = $values['Q'] ? CRM_Utils_Date::processDate($values['Q']) : 'NULL';
            $contributionRecur->create_date             = $values['Q'] ? CRM_Utils_Date::processDate($values['Q']) : 'NULL';
            $contributionRecur->modified_date           = $values['T'] ? CRM_Utils_Date::processDate($values['T']) : 'NULL';
            $contributionRecur->next_sched_contribution = $values['S'] ? CRM_Utils_Date::processDate($values['S']) : 'NULL';
            $contributionRecur->end_date                = $values['U'] ? CRM_Utils_Date::processDate($values['U']) : 'NULL';
            $contributionRecur->payment_instrument_id   = $this->payment_instrument_id;
            $contributionRecur->contribution_type_id    = $this->contribution_type_id;
            
            try {
                $contributionRecur->find();
                if($contributionRecur->fetch(true)) {
                    $this->increment_counter('contribution_recur_existing_count');
                    return $contributionRecur->id;
                }
                $contributionRecur->campaign_id = $campaign ? $campaign: 'NULL';
                $recuring_out = $contributionRecur->save();
                $this->increment_counter('contribution_recur_new_count');
                return $recuring_out->id;
            } catch (Exception $e) {
                $mes =  'create_recurring_contribution Caught exception: '. $e->getMessage();
                crm_core_error::debug('Exception', $mes);
            }
            return '';
        }
    }
    
    function increment_counter($counter) {
        $this->$counter = $this->$counter + 1;
    }

}
