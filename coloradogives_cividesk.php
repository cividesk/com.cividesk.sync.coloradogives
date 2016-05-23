<?php

function _coloradogives_cividesk_civicrm_config(&$config = NULL) {
    static $configured = FALSE;
    if ($configured) return;
    $configured = TRUE;
    
    $template =& CRM_Core_Smarty::singleton();
    
    $extRoot = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
    $extDir = $extRoot . 'templates';
    
    if ( is_array( $template->template_dir ) ) {
        array_unshift( $template->template_dir, $extDir );
    } else {
        $template->template_dir = array( $extDir, $template->template_dir );
    }
    
    $include_path = $extRoot . PATH_SEPARATOR . get_include_path( );
    set_include_path( $include_path );
}


function _coloradogives_cividesk_insert_navigationMenu(&$menu, $path, $item, $parentId = null) {
    global $navId;
    
    // If we are done going down the path, insert menu
    if (empty($path)) {
        if (!$navId) $navId = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_navigation");
        $navId ++;
        $menu[$navId] = array (
                               'attributes' => 
                               array_merge($item, array(
                                                        'label'      => CRM_Utils_Array::value('name', $item),
                                                        'active'     => 1,
                                                        'parentID'   => $parentId,
                                                        'navID'      => $navId,
                                                        ))
                               );
        return true;
    } else {
        // Find an recurse into the next level down
        $found = false;
        $path  = explode('/', $path);
        $first = array_shift($path);
        foreach ($menu as $key => &$entry) {
            if ($entry['attributes']['name'] == $first) {
                if (!$entry['child']) $entry['child'] = array();
                $found = _coloradogives_cividesk_insert_navigationMenu($entry['child'], implode('/', $path), $item, $key);
            }
        }
        return $found;
    }
  }

/**
 * (Delegated) Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function _coloradogives_cividesk_civicrm_xmlMenu(&$files) {
    foreach (glob(__DIR__ . '/xml/Menu/*.xml') as $file) {
        $files[] = $file;
    }
}


