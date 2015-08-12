<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

namespace Tricho\DataUi;

use \DOMComment;
use \DOMDocument;
use \DOMElement;
use \DOMText;
use \Exception;
use \DataValidationException;
use \InvalidArgumentException;
use \UnexpectedValueException;
use \UnknownColumnException;

use Tricho\Runtime;
use Tricho\DbConn\ConnManager;
use Tricho\Meta;
use Tricho\Meta\Database;
use Tricho\Meta\Table;
use Tricho\Meta\Column;
use Tricho\Meta\EmailColumn;
use Tricho\Meta\FileColumn;
use Tricho\Meta\PasswordColumn;
use Tricho\Meta\UploadedFile;
use Tricho\Util\HtmlDom;
use Tricho\Query\InsertQuery;
use Tricho\Query\UpdateQuery;
use Tricho\Query\QueryFieldLiteral;


/**
 * Represents a form that contains multiple items (columns, headings, etc.)
 * @todo Extend with an AdminForm class that handles parent traversal etc.
 */
class Form {
    protected $id;
    protected $id_field = 'f';
    protected $method = 'post';
    protected $form_url;
    protected $action_url;
    protected $success_url;
    protected $type = 'unknown';
    protected $table;
    protected $items;
    protected $step;
    protected $final;
    protected $presets = array();
    protected $modifier;
    protected $file = null;
    
    /**
     * The DOMDocument created by Form::generateDoc, which can be manipulated
     * by Column::attachInputField.
     */
    protected $doc = null;
    
    /**
     * A counter for the fields generated by Form::generateDoc.
     */
    protected $field_num = 1;
    
    
    function __construct($id = '', $method = '') {
        if ($method != '') $this->setMethod($method);
        $this->items = array();
        if ($id != '') {
            $this->id = $id;
        } else {
            $this->id = generate_code(20);
        }
    }
    
    
    function getID() {
        return $this->id;
    }
    
    
    function getStep() {
        return $this->step;
    }
    
    
    function getDoc() {
        return $this->doc;
    }
    
    
    /**
     * @param string $method 'post' or 'get'
     */
    function setMethod($method) {
        $method = strtolower($method);
        if ($method != 'post' and $method != 'get') {
            $err = "Invalid method, must be post or get";
            throw new InvalidArgumentException($err);
        }
        $this->method = $method;
    }
    
    
    /**
     * @param string $field
     */
    function setIDField($field) {
        $this->id_field = (string) $field;
    }
    
    
    /**
     * @param string $form_url
     */
    function setFormURL($form_url) {
        $this->form_url = (string) $form_url;
    }
    
    /**
     * @return string
     */
    function getFormURL() {
        return $this->form_url;
    }
    
    /**
     * @param string $action_url
     */
    function setActionURL($action_url) {
        $this->action_url = (string) $action_url;
    }
    
    /**
     * @param string $success_url
     */
    function setSuccessURL($success_url) {
        $this->success_url = (string) $success_url;
    }
    
    
    /**
     * @return string Usually 'add', 'edit' or 'step'
     */
    function getType() {
        return $this->type;
    }
    
    /**
     * @param string $type 'add', 'edit', 'step', or perhaps something like
     *        'custom'. Add and edit will save data into the database, while
     *        step will add it to the session and then continue to the next
     *        step of a multi-form process. Any other type (e.g. 'custom') will
     *        mean the process() method can't be used.
     */
    function setType($type) {
        $this->type = strtolower($type);
    }
    
    
    function setTable(Table $table) {
        $this->table = $table;
    }
    function getTable() {
        return $this->table;
    }
    
    
    function addItem(FormItem $item, $pos = null) {
        if ($pos === null) {
            $this->items[] = $item;
            return;
        }
        $pos = (int) $pos;
        if ($pos < 0) $pos = 0;
        
        $added = false;
        $num_nodes = count($this->items);
        $items = array();
        for ($i = 0; $i < $num_nodes; ++$i) {
            if ($i == $pos) {
                $items[] = $item;
                $added = true;
            }
            // N.B. this doesn't rely on contiguous key numbers
            $items[] = array_shift($this->items);
        }
        if (!$added) $items[] = $item;
        $this->items = $items;
    }
    function getItems() {
        return $this->items;
    }
    
    
    /**
     * Attempts to find a column, and returns the first match
     * @param Column $col The column
     * @param string $type 'add', 'edit', 'edit-view', or '' for any
     * @return mixed The relevant ColumnFormItem, or null if not found
     */
    function getColumnItem(Column $col, $type = '') {
        if (!in_array($type, ['add', 'edit', 'edit-view', ''])) {
            throw new InvalidArgumentException('Unknown type: ' . $type);
        }
        foreach ($this->items as $item) {
            if (!($item instanceof ColumnFormItem)) continue;
            if ($item->getColumn() !== $col) continue;
            if ($type != '' and strpos($item->getApply(), $type) === false) {
                continue;
            }
            return $item;
        }
        return null;
    }
    
    
    /**
     * Removes an item
     * @param FormItem $item
     * @return bool True if the item was found and removed
     */
    function removeItem(FormItem $item) {
        foreach ($this->items as $key => $val) {
            if ($item === $val) {
                unset($this->items[$key]);
                return true;
            }
        }
        return false;
    }
    
    
    /**
     * Removes all item
     * @return void
     */
    function removeAllItems() {
        $this->items = array();
    }
    
    
    function setModifier($modifier) {
        if ($modifier !== null and !($modifier instanceof FormModifier)) {
            throw new InvalidArgumentException('Must be FormModifier or null');
        }
        $this->modifier = $modifier;
    }
    function getModifier() {
        return $this->modifier;
    }
    
    
    /**
     * Loads a Form from a file
     * @param string $file_path A complete file path, or a file name
     * @param bool $ignore_missing_cols If false, if the form references an
     *        unknown Column, an UnknownColumnException will be thrown
     * @return void
     * @throws UnknownColumnException, InvalidArgumentException,
     *         UnexpectedValueException, FormStepException, Exception
     */
    function load($file_path, $ignore_missing_cols = false) {
        $file_path = (string) $file_path;
        if ($file_path[0] != '/') {
            $root = Runtime::get('root_path') . 'tricho/data/';
            $file_path = $root . $file_path;
        }
        if (!ends_with($file_path, '.form.xml')) $file_path .= '.form.xml';
        $file = basename($file_path);
        
        if (!@is_file($file_path) or !is_readable($file_path)) {
            $err = "Missing or unreadable file {$file}";
            throw new InvalidArgumentException($err);
        }
        
        $this->setFile($file_path);
        
        $doc = new DOMDocument();
        $doc->load($file_path);
        
        $db = Database::parseXML();
        
        $form = $doc->documentElement;
        $table = $db->get($form->getAttribute('table'));
        if (!($table instanceof Table)) {
            $err = 'No table named ' . $form->getAttribute('table');
            $err .= " in form {$file}";
            throw new UnexpectedValueException($err);
        }
        $this->setTable($table);
        $this->final = true;
        $step = (int) @$form->getAttribute('step');
        if ($step > 0) {
            $this->step = $step;
            if (!$form->hasAttribute('final')) $this->final = false;
        } else {
            $this->step = 1;
        }
        
        $step = @$_SESSION['forms'][$this->id]['step'];
        if ($this->step > 1 and $this->step > $step) {
            throw new FormStepException('Step(s) skipped');
        }
        
        $modifier = $form->getAttribute('modifier');
        if ($modifier != '') $this->setModifier(new $modifier());
        
        $items = $form->getElementsByTagName('items')->item(0);
        foreach ($items->childNodes as $node) {
            if ($node instanceof DOMText) continue;
            if ($node instanceof DOMComment) continue;
            $type = $node->tagName;
            if ($type == 'field') {
                $col_name = $node->getAttribute('name');
                $col = $table->get($col_name);
                if (!$col) {
                    if ($ignore_missing_cols) continue;
                    $err = "Unknown column '$col_name' in form {$file}";
                    $ex = new UnknownColumnException($err);
                    $ex->setColumn($col_name);
                    throw $ex;
                }
                
                $item = new ColumnFormItem($col);
                $item->setLabel($node->getAttribute('label'));
                $item->setValue($node->getAttribute('value'));
                $item->setApply($node->getAttribute('apply'));
                $mandatory = $node->getAttribute('mandatory');
                $item->setMandatory(Meta::toBool($mandatory));
            } else {
                $err = "Unknown element '{$node->tagName}' in form {$file}";
                throw new Exception($err);
            }
            $this->items[] = $item;
        }
        
        $presets = $form->getElementsByTagName('presets')->item(0);
        if ($presets) {
            foreach ($presets->childNodes as $node) {
                if (!($node instanceof DOMElement)) continue;
                $type = $node->getAttribute('type');
                $value = $node->getAttribute('value');
                switch ($type) {
                case '':
                case 'string':
                    $preset = new QueryFieldLiteral($value);
                    break;
                
                case 'literal':
                    $preset = new QueryFieldLiteral($value, false);
                    break;
                
                case 'random':
                    $preset = new RandomString($value);
                    break;
                
                default:
                    $err = "Unknown preset '{$type}' in form {$file}";
                    throw new Exception($err);
                }
                $field_name = $node->getAttribute('field');
                $this->presets[$field_name] = $preset;
            }
        }
            
        
        if ($this->modifier) {
            $this->modifier->postLoad($this);
        }
    }
    
    
    /**
     * Gets the name of the file where this form's definition should be saved,
     * without the extension.
     */
    function getFile() {
        return $this->file;
    }
    
    /**
     * Sets the name of the file where this form's definition should be saved,
     * leaving out the extension.
     */
    function setFile($file) {
        if (ends_with($file, '.form.xml')) {
            $file = substr($file, 0, -strlen('.form.xml'));
        }
        $this->file = $file;
    }
    
    
    function render($values = '', $errors = '', $pk = null) {
        $doc = $this->generateDoc($values, $errors, $pk);
        return $doc->saveXML($doc->documentElement);
    }
    
    
    /**
     * Initialises a DOMDocument with a FORM element for storing input fields.
     * @return DOMElement The FORM element
     */
    function initDocForm() {
        $this->doc = new DOMDocument();
        $form = $this->doc->createElement('form');
        $this->doc->appendChild($form);
        return $form;
    }
    
    
    function incrementFieldNum() {
        ++$this->field_num;
    }
    function getFieldId() {
        return $this->table->getName() . '-' . $this->type . '-' .
            $this->field_num;
    }
    
    
    /**
     * Generates a document which contains the form and all its fields
     * 
     * @param array $values Values to embed in the fields, typically from the
     *        user's session
     * @param array $errors Validation errors to display
     * @param mixed $pk Primary key value (applicable for edit forms)
     * @return DOMDocument
     */
    function generateDoc($values = '', $errors = '', $pk = null) {
        if (!is_array($values)) $values = array();
        if (!is_array($errors)) $errors = array();
        
        if ($this->modifier) {
            $this->modifier->preGenerate($this, $values, $errors, $pk);
        }
        
        $form = $this->initDocForm();
        $doc = $form->ownerDocument;
        $inner_doc = new DOMDocument();
        $form->setAttribute('method', $this->method);
        $form->setAttribute('action', $this->action_url);
        $input = $doc->createElement('input');
        $input->setAttribute('type', 'hidden');
        $input->setAttribute('name', '_f');
        $input->setAttribute('value', $this->id);
        $form->appendChild($input);
        $id_base = $this->table->getName() . '-' . $this->type . '-';
        $this->field_num = 0;
        $num_inputs = 0;
        foreach ($this->items as $item) {
            ++$this->field_num;
            $id = $id_base . $this->field_num;
            $display = 'input';
            list($col, $label, $value, $apply_str, $mand) = $item->toArray();
            $apply_arr = preg_split('/,\s*/', $apply_str);
            if (in_array($this->type, ['add', 'edit'])) {
                
                // Support 'add', 'edit', and 'edit-view'
                $ok = false;
                foreach ($apply_arr as $apply) {
                    if (preg_replace('/-.*/', '', $apply) == $this->type) {
                        $ok = true;
                        if (in_array($apply, ['add', 'edit'])) ++$num_inputs;
                        if ($apply == 'edit-view') $display = 'value';
                        break;
                    }
                }
                if (!$ok) continue;
            }
            
            if (!$col->isMandatory()) $col->setMandatory($mand);
            
            $col_name = $col->getName();
            if (isset($values[$col_name])) $value = $values[$col_name];
            
            if ($label == '') {
                $label = $col->getEngName();
            }
            if ($label != '') {
                $col->setEngName($label);
                $p = $doc->createElement('p');
                $p->setAttribute('class', 'label');
                $form->appendChild($p);
                $label_node = $doc->createElement('label');
                $label_node->setAttribute('for', $id);
                $p->appendChild($label_node);
                $text = $doc->createTextNode($label);
                $label_node->appendChild($text);
            }
            if (isset($errors[$col_name])) {
                $p = $doc->createElement('p');
                $p->setAttribute('class', 'error');
                $form->appendChild($p);
                $text = $doc->createTextNode($errors[$col_name]);
                $p->appendChild($text);
            }
            
            if ($display == 'value') {
                $col->attachValue($this, $value, $pk);
                continue;
            }
            
            // Have columns make their own DOMNodes where possible
            if (method_exists($col, 'attachInputField')) {
                if ($col instanceof FileColumn and $pk !== null) {
                    $col->attachInputField($this, $value, $pk);
                } else {
                    $col->attachInputField($this, $value);
                }
                HtmlDom::appendNewText($form, "\n");
                continue;
            }
            $inputs = $col->getMultiInputs($this, $value);
            
            $input_num = 0;
            foreach ($inputs as $input) {
                if (++$input_num != 1) {
                    $id = $id_base . $this->field_num . '-' . $input_num;
                    $p = $doc->createElement('p');
                    $p->setAttribute('class', 'label');
                    $form->appendChild($p);
                    $label = $doc->createElement('label');
                    $label->setAttribute('for', $id);
                    $p->appendChild($label);
                    
                    // nasty hack :(
                    // TODO: remove this once admin add/edit pages use Forms
                    $input_label = $input['label'];
                    if ($col instanceof PasswordColumn) {
                        $lt_pos = strpos($input_label, '<');
                        if ($lt_pos > 0) {
                            $input_label = substr($input_label, 0, $lt_pos);
                            $input_label = rtrim($input_label);
                        }
                    }
                    
                    $text = $doc->createTextNode($input_label);
                    $label->appendChild($text);
                }
                $input = $input['field'];
                
                // Need to wrap in HTML to specify UTF-8 encoding
                $input = '<html><head><meta http-equiv="Content-Type" ' .
                    'content="text/html; charset=UTF-8"></head><body>' .
                    $input . '</body></html>';
                $inner_doc->loadHTML($input);
                $node = $inner_doc->getElementsByTagName('body')->item(0)
                    ->firstChild;
                $node = $doc->importNode($node, true);
                $node->setAttribute('id', $id);
                if (strcasecmp($node->tagName, 'textarea') == 0) {
                    $blank = $doc->createTextNode('');
                    $node->appendChild($blank);
                }
                $p = $doc->createElement('p');
                $p->setAttribute('class', 'input');
                $form->appendChild($p);
                $p->appendChild($node);
            }
        }
        
        if (!in_array($this->type, ['add', 'edit']) or $num_inputs > 0) {
            $p = $doc->createElement('p');
            $p->setAttribute('class', 'submit');
            $form->appendChild($p);
            
            $submit = $doc->createElement('input');
            $submit->setAttribute('type', 'submit');
            $submit->setAttribute('name', '_submit');
            $submit->setAttribute('value', 'Save');
            $p->appendChild($submit);
            
            $cancel = $doc->createElement('input');
            $cancel->setAttribute('type', 'submit');
            $cancel->setAttribute('name', '_cancel');
            $cancel->setAttribute('value', 'Cancel');
            $p->appendChild($cancel);
        }
        
        if ($this->modifier) {
            $this->modifier->postGenerate($this, $doc);
        }
        
        return $doc;
    }
    
    
    /**
     * Process a form submission, including validation, session handling, and
     * redirects on error/success.
     * 
     * @param mixed $pk Primary Key value(s). Only applies for edit forms.
     * @param array $db_row The row from the DB with the extant data for this
     *        record. Only applies for edit forms.
     * @param array Possible key/value pairs are:
     *        - retain_session: bool (if true, session data stored with the
     *          form is not cleared upon success)
     * @return void An HTTP redirect is performed, so nothing is returned.
     */
    function process($pk = null, array $db_row = [], array $options = []) {
        if ($this->form_url == '' or $this->success_url == '') {
            throw new Exception('Invalid configuration');
        }
        
        if (!empty($_POST['_cancel'])) {
            unset($_SESSION['forms'][$this->id]);
            redirect($this->form_url);
        }
        
        $file = basename($this->file, '.form.xml');
        $file_parts = explode('.', $file);
        
        // Use first part of file name (e.g. admin) as extra session key
        if (count($file_parts) > 1) {
            $key = reset ($file_parts);
            if (!isset($_SESSION[$key]['forms'][$this->id])) {
                $_SESSION[$key]['forms'][$this->id] = array();
            }
            $session = &$_SESSION[$key]['forms'][$this->id];
        } else {
            if (!isset($_SESSION['forms'][$this->id])) {
                $_SESSION['forms'][$this->id] = array();
            }
            $session = &$_SESSION['forms'][$this->id];
        }
        
        if ($this->step > 1 and $this->step > @$session['step']) {
            redirect($this->form_url);
        }
        
        // Session data needs to be retained, otherwise multi-step forms won't
        // work, as only the data entered on the final step will be saved in
        // the DB
        if (@count($session['values']) > 0) {
            $db_data = $source_data = $session['values'];
        } else {
            $db_data = $source_data = array();
        }
        $errors = array();
        
        if ($this->modifier) {
            $this->modifier->preValidate($this, $source_data, $db_data, $errors);
        }
        
        $file_fields = array();
        foreach ($this->items as $item) {
            list($col, $label, $value, $apply, $mand) = $item->toArray();
            if (in_array($this->type, ['add', 'edit'])) {
                if (!in_array($this->type, preg_split('/,\s*/', $apply))) {
                    continue;
                }
            }
            
            if (!$col->isMandatory()) $col->setMandatory($mand);
            
            if ($label != '') $col->setEngName($label);
            if ($col instanceof FileColumn) {
                $source = $_FILES;
            } else {
                $source = $_POST;
            }
            
            // No need to ask for the current password when adding new record
            if ($col instanceof PasswordColumn and $this->type == 'add') {
                $col->setExistingRequired(false);
            }
            
            // No need to upload a new file if there's already one
            if ($col instanceof FileColumn and $this->type == 'edit') {
                if (!empty($db_row[$col->getName()])) $col->setMandatory(false);
            }
            
            $input = null;
            try {
                // TODO: replace $col->getMandatory () with a value for each form
                // e.g. new fields added long after a table's creation may be mandatory
                // for new records (add), but not for existing records (edit)
                if (method_exists($col, 'collateMultiInputs')) {
                    $value = $col->collateMultiInputs($source, $input);
                } else {
                    $value = $col->collateInput(@$source[$col->getPostSafeName()], $input);
                }
                
                $extant_value = @$session['values'][$col->getName()];
                if ($col instanceof FileColumn) {
                    $file_fields[] = $col;
                    if ($col->isInputEmpty($value)) {
                        if ($extant_value instanceof UploadedFile) {
                            $source_data[$col->getName()] = $extant_value;
                            $db_data[$col->getName()] = $extant_value;
                            continue;
                        }
                    }
                }
                
                if ($col->isMandatory() and $col->isInputEmpty($value)) {
                    $errors[$col->getName()] = 'Required field';
                } else {
                    $db_data = array_merge($db_data, $value);
                }
            } catch (DataValidationException $ex) {
                
                // Allow email address through on second submission if there
                // appears to be no working DNS resolution.
                // N.B. This is a hack, as it relies on EmailColumn's
                // behaviour matching what's expected: a single value gets
                // saved using exactly the posted data
                $add_error = true;
                $error_msg = $ex->getMessage();
                if ($col instanceof EmailColumn
                    and starts_with($ex->getMessage(), 'Domain ')
                ) {
                    $popular_sites = [
                        'google.com',
                        'facebook.com',
                        'amazon.com',
                    ];
                    $resolved = false;
                    foreach ($popular_sites as $site) {
                        $ip = gethostbyname($site);
                        if ($ip != $site) {
                            $resolved = true;
                            break;
                        }
                    }
                    
                    if ($resolved) {
                        unset($_SESSION['email_dns_failed']);
                    } else {
                        $email = $source[$col->getPostSafeName()];
                        
                        // First instance of error: remember the email address
                        // used by storing it in the session; inform the user
                        if (@$_SESSION['email_dns_failed'] != $email) {
                            $_SESSION['email_dns_failed'] = $email;
                            $error_msg .= '. This may be due to a general ' .
                                'connectivity problem. Please double check ' .
                                'the address and try again';
                            
                        // Same error twice: report no error, and save the data
                        } else {
                            $add_error = false;
                            $db_data[$col->getName()] = $email;
                            unset($_SESSION['email_dns_failed']);
                        }
                    }
                }
                
                if ($add_error) $errors[$col->getName()] = $error_msg;
            }
            $source_data[$col->getName()] = $input;
        }
        
        if ($this->modifier) {
            $this->modifier->postValidate($this, $source_data, $db_data, $errors);
        }
        
        if (count($errors) > 0) {
            $session['values'] = $source_data;
            $session['errors'] = $errors;
            $url = url_append_param($this->form_url, $this->id_field, $this->id);
            redirect($url);
        }
        
        if (!$this->final) {
            $session['values'] = $source_data;
            $session['errors'] = array();
            $session['step'] = $this->step + 1;
            $url = url_append_param($this->success_url, $this->id_field, $this->id);
            redirect($url);
        }
        
        $data = $db_data;
        
        $codes = array();
        foreach ($this->presets as $field => $preset) {
            if ($preset instanceof RandomString) {
                $code = $preset->generate();
                $codes[$field] = $code;
                $preset = new QueryFieldLiteral($code);
            }
            $db_data[$field] = $preset;
        }
        
        if ($this->type == 'add') {
            $q = new InsertQuery($this->table, $db_data);
            $q->exec();
        } else if ($this->type == 'edit') {
            $q = new UpdateQuery($this->table, $db_data, $pk);
            $q->exec();
        }
        
        if (empty($options['retain_session'])) {
            unset($_SESSION['forms'][$this->id]);
        } else {
            $session['values'] = $source_data;
        }
        
        $insert_id = 0;
        if ($this->type == 'add') {
            $conn = ConnManager::get_active();
            $insert_id = $conn->get_pdo()->lastInsertId();
        }
        
        // Data is only saved in the database for add/edit forms
        // All other forms have to work their magic in postprocessing
        if (in_array($this->type, ['add', 'edit'])) {
            $key = ($this->type == 'edit')? $pk: $insert_id;
            foreach ($file_fields as $col) {
                $name = $col->getName();
                if (!(@$db_data[$name] instanceof UploadedFile)) continue;
                $col->saveData($db_data[$name], $key);
            }
        }
        
        if ($this->modifier) {
            $this->modifier->postProcess($this, $data, $insert_id, $codes);
        }
        
        redirect($this->success_url);
    }
    
    
    /**
     * Generate a form with no fuss.
     * N.B. This relies on particular values in $_GET, $_SERVER, and $_SESSION
     * @param string $file The name of the form, without extension
     * @param string $type {@see self::setType()}
     * @param string $action_url Where to submit the form. Defaults to
     *        {form}_action.php
     * @param mixed $pk The primary key of the record (for edit actions only)
     * @return string
     */
    static function loadAndRender($file, $type, $action_url = '', $pk = null) {
        $id = empty($_GET['f'])? '': $_GET['f'];
        $form = new Form($id);
        if ($id == '') $id = $form->getID();
        if ($action_url == '') {
            $form_url = preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI']);
            $action_url = preg_replace('/\.php$/', '_action.php', $form_url);
        }
        $form->setActionURL($action_url);
        $form->load($file);
        $form->setType($type);
        if (!isset($_SESSION['forms'][$id])) {
            $_SESSION['forms'][$id] = ['values' => [], 'errors' => []];
        }
        $session = &$_SESSION['forms'][$id];
        return $form->render($session['values'], $session['errors'], $pk);
    }
    
    
    /**
     * Process a form with no fuss.
     * N.B. This relies on particular values in $_POST, $_SERVER, and $_SESSION
     * @param string $file The name of the form, without extension
     * @param string $type {@see self::setType()}
     * @param string $success_url Where to redirect upon success
     * @param string $form_url Where to redirect upon error
     * @param mixed $pk The primary key of the record (for edit actions only)
     * @return void Will redirect on both error and success
     */
    static function loadAndProcess($file, $type, $success_url, $form_url = '', $pk = null) {
        if (empty($_POST['_f'])) redirect($form_url);
        $id = $_POST['_f'];
        $form = new Form($id);
        if ($form_url == '') {
            if (!empty($_SERVER['HTTP_REFERER'])) {
                $parts = parse_url($_SERVER['HTTP_REFERER']);
                if ($parts['host'] == $_SERVER['HTTP_HOST']) {
                    $form_url = $_SERVER['HTTP_REFERER'];
                    $form_url = preg_replace('/\?.*/', '', $form_url);
                }
            }
        }
        if (!$form_url) redirect('/');
        $form->setFormURL($form_url);
        $form->setSuccessURL($success_url);
        $form->load($file);
        $form->setType($type);
        $form->process($pk);
    }
}


/**
 * Thrown when a user tries to skip a step in a multi-form process.
 */
class FormStepException extends Exception {
}
?>
