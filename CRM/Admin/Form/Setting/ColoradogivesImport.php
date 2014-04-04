<?php
/*
 +--------------------------------------------------------------------------+
 | Copyright IT Bliss LLC (c) 2012-2013                                     |
 +--------------------------------------------------------------------------+
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program.  If not, see <http://www.gnu.org/licenses/>.    |
 +--------------------------------------------------------------------------+
*/

class CRM_Admin_Form_Setting_ColoradogivesImport extends CRM_Admin_Form_Setting {
  protected $_settings;

  function preProcess() {
    // Needs to be here as from is build before default values are set
    $this->_settings = CRM_Utils_Coloradogives::getSettings();
    if (!$this->_settings) $this->_settings = array();
  }

  /**
   * Function to build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    $this->applyFilter('__ALL__', 'trim');
    $this->add('text', 'coloradogives_username',  ts('Username'),  array('size' => 50, 'maxlength' => 50), TRUE );
    $this->add('text', 'coloradogives_password',  ts('Password'),  array('size' => 50, 'maxlength' => 50), TRUE );
    $this->add('text', 'coloradogives_orgid',     ts('Org ID'),    array('size' => 50, 'maxlength' => 50), TRUE );

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ),
    ));
  }

  function setDefaultValues() {
    $defaults = $this->_settings;
    return $defaults;
  }

  /**
   * Function to process the form
   *
   * @access public
   * @return None
   */
  public function postProcess(){
    $params = $this->exportValues();    
    // Save all settings
    foreach (array('coloradogives_username', 'coloradogives_password', 'coloradogives_orgid') as $key => $name) {
      CRM_Utils_Coloradogives::setSetting(CRM_Utils_Array::value($name, $params, 0), $name);
    }
  } //end of function
  
  static function checkPermission() {
    if (CRM_Core_Permission::check('access coloradogives') ) {
      $out = array('y');
    } else {
      $out = array('n');
    }
    echo json_encode($out);
    CRM_Utils_System::civiExit();
  }
} // end class
