<?php
require_once 'coloradogives_cividesk.php';


function coloradogives_civicrm_config(&$config) {
    $extRoot = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
    if (is_dir($extRoot . 'packages')) {
        set_include_path($extRoot . 'packages' . PATH_SEPARATOR . get_include_path());
    }
    _coloradogives_cividesk_civicrm_config($config);
}

function coloradogives_civicrm_disable() {
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_job WHERE api_action = 'cogives_import'");
    CRM_Core_DAO::executeQuery("DELETE FROM civicrm_setting where name= 'last_import_date'");
}

function coloradogives_civicrm_enable() {
    
    // Create entry in civicrm_job table for cron call
    $version = _coloradogives_getCRMVersion();
    
    if ($version >= 4.3) {
        // looks like someone finally wrote an api ..
        civicrm_api('job', 'create', array(
                                           'version'       => 3,
                                           'name'          => ts('Import Offline Contribution & Recurring Payments'),
                                           'description'   => ts('Import Offline Contribution & Recurring Payments'),
                                           'run_frequency' => 'Daily',
                                           'api_entity'    => 'job',
                                           'api_action'    => 'cogives_import',
                                           'is_active'     => 0
                                           ));
    } else {
        // otherwise, this ..
        CRM_Core_DAO::executeQuery("
                                    INSERT INTO civicrm_job (
                                        id, domain_id, run_frequency, last_run, name, description,
                                        api_prefix, api_entity, api_action, parameters, is_active
                                    ) 
                                    VALUES (
                                          NULL, %1, 'Daily', NULL, 'Import Offline Contribution & Recurring Payments',
                                          'Import Offline Contribution & Recurring Payments',
                                          'civicrm_api3', 'job', 'cogives_import', '', 0
                                    )
                                   ", 
                                   array( 1 => array(CIVICRM_DOMAIN_ID, 'Integer') )
                                   );
    }
  if ($version >= 4.7) {
    $sql = "INSERT INTO civicrm_setting(name, value, domain_id) values ('last_import_date', '0', %1)";
  } else {
    $sql = "INSERT INTO civicrm_setting(group_name, name, value, domain_id) values ('Import Job', 'last_import_date', '0', %1)";
  }
    CRM_Core_DAO::executeQuery($sql, array( 1 => array(CIVICRM_DOMAIN_ID, 'Integer') ) );
}


function _coloradogives_getCRMVersion() {
    $crmversion = explode('.', ereg_replace('[^0-9\.]','', CRM_Utils_System::version()));
    return floatval($crmversion[0] . '.' . $crmversion[1]);
}

function civicrm_api3_job_cogives_import($params = null) {
    $file = CRM_Utils_File::makeFileName('Export-anonymized.xls');
    $coloradogives_download = new CRM_Utils_ColoradogivesDownload();
    $coloradogives_download->download($file);
    try {
        $coloradogives_import = new CRM_Utils_ColoradogivesImport();
        $log = $coloradogives_import->paraseXls($file);
        $return['is_error']      = 0;
        $return['error_message'] = '';
        $return['values']        = $log;
        return $return;
    } catch (Exception $e) {
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
}


/**
 * Implementation of hook_civicrm_navigationMenu
 */
function coloradogives_civicrm_navigationMenu( &$params ) {
    // Add menu entry for extension administration page
    _coloradogives_cividesk_insert_navigationMenu($params, 
                                               'Administer/Customize Data and Screens',
                                                  array(
                                                        'name'       => 'Colorado Gives',
                                                        'url'        => 'civicrm/admin/setting/coloradogives',
                                                        'permission' => 'administer CiviCRM',
                                                        )
                                                  );
}


/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function coloradogives_civicrm_xmlMenu(&$files) {
    _coloradogives_cividesk_civicrm_xmlMenu($files);
}
