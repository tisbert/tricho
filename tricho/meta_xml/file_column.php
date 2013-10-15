<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

/**
 * @package meta_xml
 */

/**
 * Stores meta-data about a column that will store file data
 * @package meta_xml_file_del
 */
class FileColumn extends Column {
    protected $store_location;
    protected $max_file_size = 0;
    protected $storage_location;
    protected $allow_delete = false;
    protected $types_allowed = array();
    protected $mask;
    
    
    /**
     * Creates a DOMElement that represents this column (for use in tables.xml)
     * @param DOMDocument $doc The document to which this node will belong
     * @return DOMElement
     * @author benno, 2012-11-24
     */
    function toXMLNode (DOMDocument $doc) {
        $node = parent::toXMLNode ($doc);
        if ($this->table != null) {
            $id = $this->table->getName (). '.'. $this->name;
        } else {
            $id = $this->name;
        }
        if ($this->mask == '') {
            throw new InvalidColumnConfigException ("Mask required in {$id}");
        }
        if ($this->storage_location == '') {
            throw new InvalidColumnConfigException ("Storage location required in {$id}");
        }
        $node->setAttribute ('mask', $this->mask);
        $param = HtmlDom::appendNewChild ($node, 'param');
        $param->setAttribute ('name', 'storage_location');
        $param->setAttribute ('value', $this->storage_location);
        if ($this->max_file_size > 0) {
            $param = HtmlDom::appendNewChild ($node, 'param');
            $param->setAttribute ('name', 'max_file_size');
            $param->setAttribute ('value', $this->max_file_size);
        }
        $param = HtmlDom::appendNewChild ($node, 'param');
        $param->setAttribute ('name', 'allow_del');
        $param->setAttribute ('value', $this->allow_delete? 'y': 'n');
        if (count($this->types_allowed) > 0) {
            $param = HtmlDom::appendNewChild ($node, 'param');
            $param->setAttribute ('name', 'types_allowed');
            $param->setAttribute ('value', implode(',', $this->types_allowed));
        }
        return $node;
    }
    
    
    /**
     * @author benno 2012-11-24
     */
    function applyXMLNode (DOMElement $node) {
        parent::applyXMLNode ($node);
        $param_nodes = $node->getElementsByTagName ('param');
        $params = array ();
        foreach ($param_nodes as $param) {
            $name = $param->getAttribute ('name');
            $value = $param->getAttribute ('value');
            $params[$name] = $value;
        }
        $this->setStorageLocation (@$params['storage_location']);
        if (@$params['max_file_size'] > 0) {
            $this->setMaxFileSize ($params['max_file_size']);
        }
        if (@$params['allow_del'] == 'y') {
            $this->allow_delete = true;
        } else {
            $this->allow_delete = false;
        }
        if ($params['types_allowed'] != '') {
            $this->types_allowed = preg_split('/,\s*/', $params['types_allowed']);
        }
    }
    
    
    static function getAllowedSqlTypes () {
        return array ('VARCHAR');
    }
    
    static function getDefaultSqlType () {
        return 'VARCHAR';
    }
    
    /**
     * Generates a new mask to use with this column.
     * This mask will be unique to this table
     */
    function newMask () {
        // determine other columns' masks
        $other_masks = array ();
        
        if ($this->table != null) {
            $columns = $this->getTable ()->getColumns ();
            foreach ($columns as $column) {
                if (!($column instanceof FileColumn)) continue;
                $other_masks[] = $column->getMask ();
            }
        }
        
        // generate a mask that's unique within this table
        do {
            $mask = generate_code (6);
        } while (in_array ($mask, $other_masks));
        
        $this->mask = $mask;
    }
    
    
    /**
     * Gets the mask applied to this column.
     * Masks hide the database structure from the user in file.php
     * 
     * @return string The mask applied to this column
     */
    function getMask () {
        return $this->mask;
    }
    
    /**
     * Generates a new mask to use with this column.
     * Masks hide the database structure from the user in file.php
     * 
     * @param string $new_mask The new mask to be applied
     */
    function setMask ($new_mask) {
        $this->mask = $new_mask;
    }
    
    
    /**
     * Gets the full mask applied to this column (including the table mask)
     * @return string
     * @author benno, 2012-02-05
     */
    function getFullMask () {
        return $this->table->getMask (). '.'. $this->mask;
    }
    
    
    /**
     * Gets the maximum file size allowed for this column.
     * 
     * @author benno, 2008-08-28, 2012-02-05
     * 
     * @return int the maximum size, in bytes, for file uploads to be stored
     *         against this column.
     *         This is calculated as the minimum of the following:
     *         - The maximum upload size specified in PHP's ini settings
     *         - The constant MAX_UPLOAD_SIZE
     *         - The max_file_size parameter for this column
     */
    function getMaxFileSize () {
        
        $possible_max_sizes = array (INI_MAX_UPLOAD_SIZE);
        if (defined ('MAX_UPLOAD_SIZE')) $possible_max_sizes[] = MAX_UPLOAD_SIZE;
        
        if ($this->max_file_size > 0) {
            $possible_max_sizes[] = $this->max_file_size;
        }
        
        return min ($possible_max_sizes);
    }
    
    /**
     * Sets the maximum file size allowed for this column.
     * 
     * @author benno, 2012-02-05
     * 
     * @param int $max The maximum file size, in bytes
     */
    function setMaxFileSize ($max) {
        $max = (int) $max;
        if ($max < 0) $max = 0;
        $this->max_file_size = $max;
    }
    
    
    /**
     * Gets the storage location for files associated with this column
     * 
     * @author benno, 2012-02-05
     * 
     * @return string The name of a directory, relative to ROOT_PATH_WEB
     */
    function getStorageLocation () {
        return $this->storage_location;
    }
    
    
    /**
     * Sets the storage location for files associated with this column
     * 
     * @author benno, 2012-02-05
     * 
     * @param string $loc The name of a directory, relative to ROOT_PATH_WEB
     */
    function setStorageLocation ($loc) {
        $this->storage_location = $loc;
    }
    
    
    /**
     * Gets the text used to display the value stored in a non-editable column.
     * 
     * @param string $input_value the value to be displayed
     * @return string The HTML text to be used to display the value.
     * @todo code something that will make the link query have a where clause
     * @todo add a primary key field, as per getInputField?
     */
    function displayValue ($input_value = '') {
        $file = $this->getTable ()->getMask (). '.'. $this->getMask (). '.'. $_GET['id'];
        $file_loc = ROOT_PATH_FILE. $this->getParam ('storage_location'). '/'. $file;
        if (@is_file ($file_loc)) {
            $out_text = '<a href="'. ROOT_PATH_WEB. 'file.php?f='. $file. '">'. $input_value. '</a> '.
                ' ('. bytes_to_human (filesize ($file_loc)). ')';
        } else {
            $out_text = 'N/A';
        }
        return $out_text;
    }
    
    
    function getConfigArray () {
        $config = parent::getConfigArray ();
        $config['storeloc'] = $this->storage_location;
        if ($this->max_file_size > 0) {
            $config['max_file_size'] = $this->max_file_size;
        }
        if ($this->allow_delete) {
            $config['allow_del'] = true;
        }
        $config['types_allowed'] = $this->types_allowed;
        return $config;
    }
    
    
    /**
     * Gets the configuration input fields for file storage
     * @return string
     * @author benno, 2011-08-05, 2012-02-05
     */
    static function getFileConfigFormFields ($config, $class) {
        $fields = "    <p class=\"fake-tr\">\n".
            "        <span class=\"fake-td left-col\">Storage location</span>\n".
            "        <span class=\"fake-td\"><small>". $_SERVER['SERVER_NAME']. ROOT_PATH_WEB. "</small>\n".
            "        <input type=\"text\" name=\"{$class}_storeloc\" value=\"{$config['storeloc']}\"></span>\n".
            "    </p>\n".
            "    <p class=\"fake-tr\">\n".
            "        <span class=\"fake-td left-col\">Max file size (bytes)</span>\n".
            "        <span class=\"fake-td\"><input type=\"text\" name=\"{$class}_max_file_size\" value=\"";
        if ($config['max_file_size'] != 0) $fields .= $config['max_file_size'];
        $fields .= "\" size=\"9\" maxlength=\"9\"></span>\n".
            "    </p>\n";
        
        $fields .= "<p class=\"fake-tr\">\n";
        $fields .= "<span class=\"fake-td left-col\">Allow deletion</span>";
        $fields .= "<span class=\"fake-td\">";
        $fields .= "<label for=\"{$class}_del_y\">";
        $fields .= "<input id=\"{$class}_del_y\" type=\"radio\" name=\"{$class}_allow_del\" value=\"1\"";
        if ($config['allow_del']) $fields .= ' checked="checked"';
        $fields .= ">Yes <label for=\"{$class}_del_n\">";
        $fields .= "<input id=\"{$class}_del_n\" type=\"radio\" name=\"{$class}_allow_del\" value=\"0\"";
        if (!$config['allow_del']) $fields .= ' checked="checked"';
        $fields .= ">No</label></span></p>\n";
        
        return $fields;
    }
    
    
    static function getConfigFormFields ($config, $class) {
        $fields = self::getFileConfigFormFields ($config, $class);
        
        $fields .=    "<p>Show icon on main view:\n";
        $fields .= "<select name=\"{$class}_file_icon\">\n";
        $icon_options = array (
            MAIN_PIC_NONE => 'None',
            MAIN_PIC_LEFT => 'Left of text',
            MAIN_PIC_RIGHT => 'Right of text',
            MAIN_PIC_ONLY_IMAGE => 'Only show icon'
        );
        foreach ($icon_options as $option_num => $option_name) {
            if ($option_num == $config['file_icon']) {
                $fields .= "<option selected=\"selected\" value=\"{$option_num}\">".
                    "{$option_name}</option>\n";
            } else {
                $fields .= "<option value=\"{$option_num}\">{$option_name}</option>\n";
            }
        }
        $fields .= "            </select></p>\n";
        return $fields;
    }
    
    
    /**
     * @author benno, 2012-02-05
     */
    function applyConfig (array $config) {
        if ($this->mask == '') $this->newMask ();
        if (@$config['max_file_size'] > 0) {
            $this->setMaxFileSize ($config['max_file_size']);
        }
        $config['storeloc'] = @trim($config['storeloc']);
        $config['storeloc'] = ltrim ($config['storeloc'], "/.");
        $config['storeloc'] = ltrim($config['storeloc']);
        if (@$config['storeloc'] != '') {
            $this->setStorageLocation ($config['storeloc']);
        }
        $this->allow_delete = ((int) $config['allow_del']? true: false);
        if (@count($config['types_allowed']) > 0) {
            $this->types_allowed = $config['types_allowed'];
        }
    }
    
    
    function getInputField (Form $form, $input_value = '', $primary_key = null, $field_params = array ()) {
        $input = '<input type="hidden" name="MAX_FILE_SIZE" value="'.
            $this->getMaxFileSize(). '">';

        $input .= '<input type="file" name="'. $this->name;
        if (isset($field_params['change_event'])) {
            $out_txt .= ' onchange="'. $field_params['change_event']. '"';
        }
        $input .= '"> ';
        
        // display the current file if there is one
        if ($input_value instanceof UploadedFile) {
            $input .= 'Unsaved file: '. $input_value->getName ();
        } else if ($primary_key !== null and $input_value != '') {
            $file_path = '../'. $this->storage_location;
            if (substr ($file_path, -1) != '/') $file_path .= '/';
            $file_name = $this->getFullMask (). '.'. implode (',', $primary_key);
            $file_path .= $file_name;
            
            if (file_exists ($file_path)) {
                $input .= "Current file: <a href=\"../file.php?f={$file_name}\">{$input_value}</a>";
            } else {
                $input .= "<span class=\"error\">File {$input_value} doesn't exist</span>";
            }
        } else if ($form->getType() == 'edit') {
            $input .= "No file";
        }
        
        return $input;
    }
    
    
    function validateUpload($input) {
        $err = @$input['error'];
        if ($err === UPLOAD_ERR_OK) {
            // In case the max file size directive was removed from the form
            if (filesize($input['tmp_name']) > $this->getMaxFileSize ()) {
                throw new DataValidationException('The file was too large');
            }
        } else if ($err === UPLOAD_ERR_INI_SIZE or $err === UPLOAD_ERR_FORM_SIZE) {
            throw new DataValidationException('The file was too large');
        } else if ($err === UPLOAD_ERR_PARTIAL) {
            throw new DataValidationException('The file failed to upload');
        } else if ($err !== UPLOAD_ERR_NO_FILE) {
            throw new DataValidationException('Unknown error');
        }
    }
    
    
    /**
     * @author benno, 2012-02-10
     */
    function collateInput ($input, &$original_value) {
        $safe_name = $this->getPostSafeName ();
        $this->validateUpload($input);
        
        // TODO: use UploadFailedException
        $err = @$input['error'];
        if ($err === UPLOAD_ERR_OK) {
            $file = new UploadedFile($input);
            $original_value = $file;
            return array ($this->name => $file);
        } else if ($err === UPLOAD_ERR_NO_FILE) {
            if ($original_value instanceof UploadedFile) {
                return array ($this->name => $original_value);
            }
            return array ();
        }
        return array ();
    }
    
    /*
    function isInputEmpty (array $input, $old_value = null) {
        $value = (string) reset ($input);
        if ($value != '') return false;
        if ($old_value != '') return false;
        return true;
    }
    */
    
    
    /**
     * Saves an uploaded file to be attached to this column
     * @author benno, 2012-02-10
     * @param UploadedFile $file The file to save
     * @param mixed $pk The primary key of the value. Can be a string (only if
     *        the table has 1 PK column), or an array of strings
     */
    function saveData ($file, $pk) {
        if (!($file instanceof UploadedFile)) {
            $backtrace = debug_backtrace();
            foreach ($backtrace as &$step) {
                if (is_object($step['object'])) $step['object'] = get_class($step['object']);
                unset($step['args']);
            }
            echo "<pre>", print_r($backtrace, true), "</pre>\n";
        }
        
        $file_name = '../' . $this->storage_location;
        if (substr ($file_name, - 1) != '/') $file_name .= '/';
        $file_name .= $this->getFullMask () . '.';
        if (is_array ($pk)) {
            $file_name .= implode (',', $pk);
        } else {
            $file_name .= $pk;
        }
        file_put_contents ($file_name, $file->getData ());
        apply_file_security ($file_name);
    }
}
?>
