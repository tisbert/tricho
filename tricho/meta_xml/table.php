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
 * Stores meta-data about a database table
 * @package meta_xml
 */
class Table implements QueryTable {
    private $name;
    private $name_single;
    private $english_name;
    private $columns;
    private $order;
    private $comments;
    private $indices;
    private $is_joiner = false;
    private $display;
    private $display_style = TABLE_DISPLAY_STYLE_ROWS;
    private $mask;
    private $allowed_actions;
    private $alt_pages;
    private $alt_buttons;
    private $confirm_delete;
    private $cascade_delete;
    private $top_nodes_enabled = true;
    private $partition = null;
    private $database = null;
    private $disable_parent_edit = false;
    private $row_identifier = array();
    private $show_sub_record_count = null;
    private $list_view = array ();
    private $add_view = array ();
    private $edit_view = array ();
    private $export_view = array ();
    private $add_edit_view = null;
    private $tree_node_chars = null;
    private $access_level = TABLE_ACCESS_ADMIN;
    private $static_table = false;
    private $show_search = null;
    
    /**
     * Creates a new object for storing table meta-data.
     *
     * @param string $new_name the name of the table in question.
     */
    function __construct ($new_name = 'unknown') {
        $this->name = $new_name;
        $this->columns = array ();
        $this->order = array ('view' => array (), 'search' => array ());
        
        // use an empty primary key by default,
        // so that the code doesn't break if we import a table that doesn't have a primary key
        $this->indices = array ('PRIMARY KEY' => array ());
        $this->home_page = '';
        $this->mask = generate_code (6);
        $this->allowed_actions = array ();
        $this->alt_pages = array ();
        $this->alt_buttons = array ();
        $this->confirm_delete = true;
        $this->cascade_delete = true;
        $this->disable_parent_edit = true;
        
        $this->add_edit_view = array ();
    }
    
    
    /**
     * Creates a DOMElement that represents this table (for use in tables.xml)
     * @param DOMDocument $doc The document to which this node will belong
     * @return DOMElement
     * @author benno, 2011-08-09
     */
    function toXMLNode (DOMDocument $doc) {
        $params = array (
            'name' => $this->getName (),
            'name_single' => $this->getNameSingle (),
            'access' => to_access_string ($this->getAccessLevel ()),
            'engname' => $this->getEngName(),
            'display' => to_yn ($this->getDisplay ()),
            'display_style' => to_row_type ($this->getDisplayStyle ())
        );
        
        if ($this->getDisplayStyle () == TABLE_DISPLAY_STYLE_TREE) {
            $params['tree_top_nodes'] = to_yn ($this->getTopNodesEnabled ());
            if ($this->getTreeNodeChars () != null) {
                $params['tree_node_chars'] = (int) $this->getTreeNodeChars ();
            }
        }
        
        $params['show_sub_record_count'] = to_yni ($this->getShowSubRecordCount ());
        $params['show_search'] = to_yni ($this->getShowSearch ());
        $params['cascade_del'] = to_yn ($this->getCascadeDel ());
        
        // table actions
        $params['confirm_del'] = to_yn ($this->getConfirmDel ());
        $params['disable_parent_edit'] = to_yn ($this->getDisableParentEdit ());
        $params['allow'] = implode (',', $this->getAllAllowed ());
        
        // A few additional options
        if ($this->isJoiner ()) $params['joiner'] = 'y';
        if ($this->isStatic ()) $params['static'] = 'y';
        
        // table needs a mask if it contains at least one file column
        foreach ($this->getColumns () as $column) {
            if ($column instanceof FileColumn) {
                if ($this->getMask () == null) $this->newMask ();
                $params['mask'] = $this->getMask ();
                break;
            }
        }
        
        // partitions
        $partition_col = $this->getPartition ();
        if ($partition_col !== null) {
            $params['partition'] = $partition_col->getName ();
        }
        
        $node = $doc->createElement ('table');
        foreach ($params as $param => $value) {
            $node->setAttribute ($param, $value);
        }
        
        foreach ($this->columns as $column) {
            $node->appendChild ($column->toXMLNode ($doc));
        }
        
        $this->appendViewsToXMLNode ($node);
        $this->appendOrderToXMLNode ($node);
        $this->appendSearchToXMLNode ($node);
        $this->appendIndexesToXMLNode ($node);
        $this->appendAltPagesToXMLNode ($node);
        $this->appendAltButtonsToXMLNode ($node);
        $this->appendRowIdentifierToXMLNode ($node);
        
        if ($this->comments != '') {
            $comment_node = HtmlDom::appendNewChild ($node, 'comment');
            $comment = trim ($this->comments);
            $comment = str_replace ("\r\n", '<br/>', $comment);
            $comment = str_replace (array ("\r", "\n"), '<br/>', $comment);
            $comment_node->appendChild ($doc->createCDATASection ($comment));
        }
        
        return $node;
    }
    
    
    /**
     * Auxilliary method for Table::toXMLNode()
     * @param DOMElement $parent The table node to which the views belong
     * @author benno, 2011-08-09
     */
    function appendViewsToXMLNode (DOMElement $parent) {
        $doc = $parent->ownerDocument;
        
        // list
        $view_node = $doc->createElement ('list');
        $parent->appendChild ($view_node);
        foreach ($this->list_view as $item) {
            $item_node = $item->toXMLNode ($doc);
            $view_node->appendChild ($item_node);
        }
        
        // add/edit
        $view_node = $doc->createElement ('add_edit');
        $parent->appendChild ($view_node);
        foreach ($this->add_edit_view as $item) {
            $item_node = $item['item']->toXMLNode ($doc, $item);
            $view_node->appendChild ($item_node);
        }
        
        // export
        if (count ($this->export_view) > 0) {
            $view_node = $doc->createElement ('export');
            $parent->appendChild ($view_node);
            foreach ($this->export_view as $item) {
                $item_node = $item->toXMLNode ($doc);
                $view_node->appendChild ($item_node);
            }
        }
    }
    
    
    /**
     * Auxilliary method for Table::toXMLNode()
     * @param DOMElement $parent The table node to which the order data belongs
     * @author benno, 2011-08-11
     */
    function appendOrderToXMLNode (DOMElement $parent) {
        $doc = $parent->ownerDocument;
        $order = $doc->createElement ('vieworder');
        $parent->appendChild ($order);
        $order_cols = $this->getOrder ('view');
        foreach ($order_cols as $col) {
            $item = $doc->createElement ('orderitem');
            $order->appendChild ($item);
            $item->setAttribute ('type', 'column');
            $item->setAttribute ('name', $col[0]->getName ());
            $item->setAttribute ('dir', $col[1]);
        }
    }
    
    
    /**
     * Auxilliary method for Table::toXMLNode()
     * @param DOMElement $parent The table node to which the search params
     *        belong
     * @author benno, 2011-08-11
     */
    function appendSearchToXMLNode (DOMElement $parent) {
        $search_cols = $this->getSearch ();
        if (count ($search_cols) == 0) return;
        
        $doc = $parent->ownerDocument;
        $params = $doc->createElement ('searchparams');
        $parent->appendChild ($params);
        foreach ($search_cols as $col) {
            $item = $doc->createElement ('orderitem');
            $params->appendChild ($item);
            $item->setAttribute ('type', 'column');
            $item->setAttribute ('name', $col->getName ());
        }
    }
    
    
    /**
     * Auxilliary method for Table::toXMLNode()
     * @param DOMElement $parent The table node to which the indexes belong
     * @author benno, 2011-08-11
     */
    function appendIndexesToXMLNode (DOMElement $parent) {
        $doc = $parent->ownerDocument;
        $indexes = $doc->createElement ('indices');
        $parent->appendChild ($indexes);
        foreach ($this->getIndices () as $name => $cols) {
            if (is_numeric ($name)) $name = '';
            if (is_array ($cols)) {
                $col_names_arr = array ();
                foreach ($cols as $col) {
                    $col_names_arr[] = $col->getName ();
                }
                $col_names = implode (', ', $col_names_arr);
            } else {
                $col_names = $cols->getName ();
            }
            $index = $doc->createElement ('index');
            $indexes->appendChild ($index);
            $index->setAttribute ('name', $name);
            $index->setAttribute ('columns', $col_names);
        }
    }
    
    
    /**
     * Auxilliary method for Table::toXMLNode()
     * @param DOMElement $parent The table node to which the alternate pages
     *        belong
     * @author benno, 2011-08-11
     */
    function appendAltPagesToXMLNode (DOMElement $parent) {
        $doc = $parent->ownerDocument;
        foreach ($this->getAltPages () as $old => $new) {
            $node = $doc->createElement ('alt_page');
            $parent->appendChild ($node);
            $node->setAttribute ('old', $old);
            $node->setAttribute ('new', $new);
        }
    }
    
    
    /**
     * Auxilliary method for Table::toXMLNode()
     * @param DOMElement $parent The table node to which the alternate buttons
     *        belong
     * @author benno, 2011-08-12
     */
    function appendAltButtonsToXMLNode (DOMElement $parent) {
        $doc = $parent->ownerDocument;
        foreach ($this->getAltButtons () as $old => $new) {
            $node = $doc->createElement ('alt_button');
            $parent->appendChild ($node);
            $node->setAttribute ('old', $old);
            $node->setAttribute ('new', $new);
        }
    }
    
    
    /**
     * Auxilliary method for Table::toXMLNode()
     * @param DOMElement $parent The table node to which the row identifier
     *        belongs
     * @author benno, 2011-08-11
     */
    function appendRowIdentifierToXMLNode (DOMElement $parent) {
        $identifier_items = $this->getRowIdentifier ();
        if (count ($identifier_items) == 0) return;
        $doc = $parent->ownerDocument;
        $identifier = $doc->createElement ('row_identifier');
        $parent->appendChild ($identifier);
        foreach ($identifier_items as $item) {
            $node = $doc->createElement ('id_item');
            $identifier->appendChild ($node);
            if ($item instanceof Column) {
                $node->setAttribute ('type', 'col');
                $node->setAttribute ('data', $item->getName ());
            } else {
                $node->setAttribute ('type', 'sep');
                $node->setAttribute ('data', cast_to_string ($item));
            }
        }
    }
    
    
    /**
     * Creates a Table meta object from a corresponding XML node.
     * Also creates its Column objects by calling their fromXMLNode methods.
     * @param DOMElement $node The table node
     * @author benno 2011-08-15
     * @return Table the meta-data store
     */
    static function fromXMLNode(DOMElement $node) {
        $attribs = HtmlDom::getAttribArray($node);
        $table = new Table($attribs['name']);
        $table->setEngName($attribs['engname']);
        $table->setNameSingle($attribs['name_single']);
        $table->setComments(@$attribs['comment']);
        $table->setDisplay(to_bool($attribs['display']));
        $table->setMask(@$attribs['mask']);
        $table->setCascadeDel(to_bool($attribs['cascade_del']));
        $table->setStatic(to_bool(@$attribs['static']));
        $table->setTreeNodeChars(@$attribs['tree_node_chars']);
        
        switch ($attribs['access']) {
            case '':
            case 'admin':
                $table->setAccessLevel (TABLE_ACCESS_ADMIN);
                break;
            
            case 'setup-limited':
                $table->setAccessLevel (TABLE_ACCESS_SETUP_LIMITED);
                break;
            
            default:
                $table->setAccessLevel (TABLE_ACCESS_SETUP_FULL);
        }
        
        // display style
        switch ($attribs['display_style']) {
            case 'tree':
                $table->setDisplayStyle (TABLE_DISPLAY_STYLE_TREE);
                break;
            
            default:
                $table->setDisplayStyle (TABLE_DISPLAY_STYLE_ROWS);
        }
        
        if (to_num(@$attribs['tree_top_nodes']) == 0) {
            $table->setTopNodesEnabled (false);
        } else {
            $table->setTopNodesEnabled ($attribs['tree_top_nodes']);
        }
        
        if (@$attribs['home'] != '') {
            $table->addAltPage ('main', $attribs['home']);
        }
        
        
        // allowed admin actions, default to true
        // allow is a comma separated set of values.
        // supported values are 'add', 'edit', 'del', 'export', 'all'
        // use ~ to invert (set the value to false).
        // e.g. "all,~del" for add, edit and export
        $actions = explode (',', $attribs['allow']);
        foreach ($actions as $action) {
            $action = strtolower (trim ($action));
            $value = true;
            if (@$action[0] == '~') {
                $action = substr($action, 1);
                $value = false;
            }
            if ($action == 'all') {
                $table->setAllAllowed ($value);
            } else {
                $table->setAllowed ($action, $value);
            }
        }
        
        // other table settings
        if (strtolower ($attribs['confirm_del']) == 'n') {
            $table->setConfirmDel (false);
        } else {
            $table->setConfirmDel (true);
        }
        $table->setDisableParentEdit (to_bool ($attribs['disable_parent_edit']));
        
        if (to_bool(@$attribs['joiner'])) $table->setJoiner(true);
        if (isset ($attribs['show_sub_record_count'])) {
            $table->setShowSubRecordCount (to_bool_i ($attribs['show_sub_record_count']));
        }
        if (isset ($attribs['show_search'])) {
            $table->setShowSearch (to_bool_i ($attribs['show_search']));
        }
        
        // apply columns
        $col_nodes = HtmlDom::getChildrenByTagName ($node, 'column');
        foreach ($col_nodes as $col_node) {
            $class_name = $col_node->getAttribute ('class');
            $column = $class_name::fromXMLNode ($col_node);
            $table->addColumn ($column);
            $column->setTable ($table);
        }
        
        
        // partitioned tree
        $partition = @$attribs['partition'];
        if ($partition != '') {
            $partition_col = $table->get ($partition);
            if ($partition_col != null) {
                $table->setPartition ($partition_col);
            } else {
                throw new Exception ("Partition column {$partition} not found");
            }
        }
        
        
        // views
        // list
        $list = $node->getElementsByTagName ('list')->item (0);
        if ($list != null) {
            $items = array ();
            $item_nodes = $list->getElementsByTagName ('item');
            foreach ($item_nodes as $item_node) {
                $items[] = ViewItem::fromXMLNode ($item_node, $table);
            }
            $table->setView('list', $items);
        }
        
        // add_edit
        $add_edit = $node->getElementsByTagName ('add_edit')->item (0);
        if ($add_edit != null) {
            $add_items = array ();
            $edit_items = array ();
            $item_nodes = $add_edit->getElementsByTagName ('item');
            foreach ($item_nodes as $item_node) {
                $attribs = HtmlDom::getAttribArray ($item_node);
                $item = ViewItem::fromXMLNode ($item_node, $table);
                $table->appendAddEditView ($item, $attribs['add'], $attribs['edit']);
            }
            $table->morphAddEditView ();
        }
        
        // export
        $export = $node->getElementsByTagName ('export')->item (0);
        if ($export != null) {
            $items = array ();
            $item_nodes = $export->getElementsByTagName ('item');
            foreach ($item_nodes as $item_node) {
                $items[] = ViewItem::fromXMLNode ($item_node, $table);
            }
            $table->setView('export', $items);
        }
        
        // list order
        $order = $node->getElementsByTagName ('vieworder')->item (0);
        if ($order) {
            $order_item_nodes = $order->getElementsByTagName ('orderitem');
            foreach ($order_item_nodes as $item_node) {
                $col = $table->get ($item_node->getAttribute ('name'));
                if ($col) {
                    $table->addToOrder ('view', $col, $item_node->getAttribute ('DIR'));
                }
            }
        }
        
        // search
        $search = $node->getElementsByTagName ('searchparams')->item (0);
        if ($search) {
            $search_item_nodes = $search->getElementsByTagName ('orderitem');
            foreach ($search_item_nodes as $item_node) {
                $col = $table->get ($item_node->getAttribute ('name'));
                if ($col) $table->searchAdd ($col);
            }
        }
        
        // indexes
        $indexes = $node->getElementsByTagName ('indices')->item (0);
        if (!$indexes) {
            throw new Exception ('No indexes defined for table '. $table->name);
        }
        $index_nodes = $indexes->getElementsByTagName ('index');
        foreach ($index_nodes as $index_node) {
            $index_col_names = preg_split ('/, */', $index_node->getAttribute ('columns'));
            $index_cols = array ();
            foreach ($index_col_names as $col_name) {
                $col = $table->get ($col_name);
                if ($col) $index_cols[] = $col;
            }
            if (count ($index_cols) == 0) continue;
            $table->addIndex ($index_node->getAttribute ('name'), $index_cols);
        }
        
        // alt pages
        $alt_page_nodes = $node->getElementsByTagName ('alt_page');
        foreach ($alt_page_nodes as $alt_node) {
            $table->setAltPage ($alt_node->getAttribute ('old'), $alt_node->getAttribute ('new'));
        }
        
        // alt buttons
        $alt_button_nodes = $node->getElementsByTagName ('alt_button');
        foreach ($alt_button_nodes as $alt_node) {
            $table->setAltButton ($alt_node->getAttribute ('old'), $alt_node->getAttribute ('new'));
        }
        
        // row identifier
        $row_id = $node->getElementsByTagName ('row_identifier')->item (0);
        if ($row_id != null) {
            $item_nodes = $row_id->getElementsByTagName ('id_item');
            foreach ($item_nodes as $item_node) {
                $type = $item_node->getAttribute ('type');
                switch ($type) {
                    case 'col':
                        $col_name = $item_node->getAttribute ('data');
                        $col = $table->get ($col_name);
                        if ($col == null) {
                            throw new Exception ("Couldn't get \"identifier\" column {$col_name}");
                        }
                        $table->addRowIdentifier ($col);
                        break;
                        
                    case 'sep':
                        $table->addRowIdentifier ($item_node->getAttribute ('data'));
                        break;
                        
                    default:
                        throw new Exception ("Unknown row identifier type: {$type}");
                }
            }
        }
        
        // comment
        $comment_node = HtmlDom::getChildByTagName ($node, 'comment');
        if ($comment_node) {
            foreach ($comment_node->childNodes as $child) {
                if ($child->nodeType == XML_CDATA_SECTION_NODE) {
                    $comment = $child->data;
                    $table->setComments ($comment);
                    break;
                }
            }
        }
        
        return $table;
    }
    
    
    /**
     * Checks to see if a Table or AliasedTable is the same table as this
     * @return bool True if the two objects refer to the same table
     */
    function matches(QueryTable $target) {
        if ($this === $target) return true;
        if ($target instanceof AliasedTable) {
            if ($this === $target->getTable()) return true;
        }
        return false;
    }
    
    
    /**
     * Used by {@link print_human} to create a human-readable string that
     * expresses this object's properties.
     * 
     * @param int $indent_tab The indent tab to start on
     * @param bool $indent_self If true, the output of this item will be
     *        indented. If not, only its children will be indented.
     */
    function __printHuman ($indent_tab = 0, $indent_self = true) {
        
        if (defined ('PRINT_HUMAN_INDENT_WIDTH')) {
            $indent_width = PRINT_HUMAN_INDENT_WIDTH;
        } else {
            $indent_width = 2;
        }
        
        $indent = str_repeat (' ', $indent_width * $indent_tab);
        
        if ($indent_self) {
            echo $indent;
        }
        
        echo $this->name, " [";
        if ($this->isStatic ()) {
            echo 'static, ';
        }
        echo "access: ";
        
        switch ($this->access_level) {
            case TABLE_ACCESS_ADMIN:
                echo 'admin';
                break;
            
            case TABLE_ACCESS_SETUP_LIMITED:
                echo 'setup-limited';
                break;
            
            default:
                echo 'setup-full';
                break;
        }
        
        echo "], cols {\n";
        
        foreach ($this->columns as $col) {
            print_human ($col, $indent_tab + 1);
        }
        
        $inner_indent = $indent. str_repeat (' ', $indent_width);
        echo $indent, "}, views {\n";
        echo $inner_indent, "main {\n";
        foreach ($this->list_view as $item) {
            print_human ($item, $indent_tab + 2);
        }
        echo $inner_indent, "}\n";
        echo $inner_indent, "add {\n";
        foreach ($this->add_view as $item) {
            print_human ($item, $indent_tab + 2);
        }
        echo $inner_indent, "}\n";
        echo $inner_indent, "edit {\n";
        foreach ($this->edit_view as $item) {
            print_human ($item, $indent_tab + 2);
        }
        echo $inner_indent, "}\n";
        echo $indent, "}\n";
        
    }
    
    /**
     * Builds a query that can be used to create a table to match this metadata
     * @param string $engine the storage engine to use, e.g. MyISAM
     * @param string $table_collation the default collation to use for this
     *        table's columns, e.g. utf8_unicode_ci.
     * @param array $column_collations the specific collations to use for each
     *        column, where different from the default collation for the table.
     *        Each key in the array is a column name, with the corresponding
     *        value being the name of the collation to use for that column.
     * @return string the CREATE TABLE query
     * @author benno 2011-08-02
     */
    function getCreateQuery ($engine = '', $table_collation = '', $col_collations = null) {
        // Nasty :(
        $root = tricho\Runtime::get('root_path');
        require_once $root . 'admin/setup/setup_functions.php';
        
        if ($engine == '') {
            $available_engines = get_available_engines();
            $engine = reset($available_engines);
        }
        
        if ($table_collation == '') {
            $table_collation = get_table_collation ($this->getName ());
        }
        if ($table_collation == '') $table_collation = SQL_DEFAULT_COLLATION;
        
        // If getting create query for an extant table, and column collations
        // haven't been explicitly specified, make sure that the column collations
        // match the current table
        if (!is_array ($col_collations)) {
            $col_collations = array ();
            $q = new RawQuery("SHOW FULL COLUMNS FROM `{$this->getName ()}`");
            $q->set_internal(true);
            try {
                $res = execq($q);
            } catch (QueryException $ex) {
                $res = false;
            }
            while ($row = @fetch_assoc($res)) {
                if ($row['Collation'] != '' and $row['Collation'] != $table_collation) {
                    $col_collations[$row['Field']] = $row['Collation'];
                }
            }
        }
        
        $result = "CREATE TABLE IF NOT EXISTS `". $this->getName (). "` (\n    ";
        $columns = $this->getColumns ();
        $col_defns = array ();
        foreach ($columns as $col) {
            $column_collation = @$col_collations[$col->getName ()];
            if ($column_collation == $table_collation) $column_collation = '';
            $defn = $col->getCreateSql ();
            if ($column_collation) {
                $defn .= ' COLLATE '. $column_collation;
            }
            $col_defns[] = $defn;
            
        }
        $result .= implode (",\n    ", $col_defns);
        
        $indexes = $this->getIndices ();
        $index_defns = array ();
        foreach ($indexes as $name => $index) {
            if (is_numeric ($name)) $name = '';
            if ($name != 'PRIMARY KEY') {
                $name = "INDEX `{$name}`";
            }
            if (is_array ($index)) {
                $col_names_arr = array ();
                foreach ($index as $id => $col) {
                    $col_names_arr[] = '`'. $col->getName (). '`';
                }
                $index_defns[] = "{$name} (". implode (',', $col_names_arr). ')';
            } else {
                $index_defns[] = "{$name} (`". $index->getName (). '`)';
            }
        }
        if (count($index_defns) > 0) {
            $result .= ",\n    ". implode (",\n    ", $index_defns);
        }
        
        $result .= "\n) ENGINE={$engine}";
        
        if ($table_collation != '') {
            list ($charset) = explode ('_', $table_collation);
            $result .= " DEFAULT CHARACTER SET {$charset} COLLATE {$table_collation}";
        }
        
        return $result;
    }
    
    
    /**
     * gets the column used to partition a tree display
     * @return Column the partition column (null if one is not defined).
     */
    function getPartition () {
        return $this->partition;
    }
    
    /**
     * sets the column used to partition a tree display
     * @param Column $partition the column to use.
     */
    function setPartition (Column $partition) {
        $this->partition = $partition;
    }
    
    /**
     * unsets the partitioning of a tree display
     */
    function clearPartition () {
        $this->partition = null;
    }
    
    /**
     * Sets whether or not the search bar will be open by default on the main
     * view.
     * 
     * @author benno, 2008-09-16
     * 
     * @param mixed $value True if the search bar will always be open, false if
     *        the search bar is only open when search parameters are defined,
     *        or null to inherit the database-wide setting
     */
    function setShowSearch ($value) {
        if ($value === true or $value === false) {
            $this->show_search = $value;
        } else {
            $this->show_search = null;
        }
    }
    
    
    /**
     * Gets whether or not the search bar will be open by default on the main
     * view.
     * 
     * @author benno, 2008-09-16
     * 
     * @return mixed True if the search bar will always be open, false if the
     *         search bar is only open when search parameters are defined,
     *         or null to inherit the database-wide setting
     */
    function getShowSearch () {
        return $this->show_search;
    }
    
    
    /**
     * Gets whether or not the search bar will be open on the main view.
     * 
     * @author benno, 2008-09-17
     * 
     * @return mixed True if the search bar will be open, false if not
     */
    function showSearch () {
        if ($this->show_search === true or $this->show_search === false) {
            return $this->show_search;
        } else {
            return $this->getDatabase ()->getShowSearch ();
        }
    }
    
    
    /**
     * Set the show_sub_record_count setting.
     * True to show tab record counts, False to not show tab record counts,
     * null to inherit from database
     * @author Josh 2007-08-14
     * @param boolean $value The setting
     */
    function setShowSubRecordCount ($value) {
        $this->show_sub_record_count = $value;
    }
    
    
    /**
     * Get the show_sub_record_count setting.
     * True to show tab record counts, False to not show tab record counts,
     * null to inherit from database
     * @author Josh 2007-08-14
     * @return boolean The setting
     */
    function getShowSubRecordCount () {
        return $this->show_sub_record_count;
    }
    
    
    /**
     * Determine if we should show the record count when this table is shown as
     * a tab
     * @author Josh 2007-08-14
     * @return boolean True if we should, false otherwise
     */
    function showTabCount () {
        if ($this->show_sub_record_count === null) {
            //echo 'inheriting...';
            return $this->database->showTabCount ();
        } else {
            //echo 'using '; var_dump($this->show_sub_record_count);
            return $this->show_sub_record_count;
        }
    }
    
    
    /**
     * Gets the order string relating to a row.
     * Used to determine if rows belong in the same order block or not.
     */
    function getOrderString ($order_list, $row) {
        
        if (is_array ($order_list)) {
            
            $order_string_elements = array ();
            foreach ($order_list as $el) {
                list($full_field_name, $junk) = explode(' ', $el);
                list($table_name, $field_name) = explode('.', $full_field_name);
                if ($field_name == '') $field_name = $table_name;
                $order_string_elements[] = '"'. addslashes ($row[$field_name]). '"';
                $last_field_name = $field_name;
            }
            // exclude the actual ordernum field?
            $last_col = $this->get ($last_field_name);
            if ($last_col !== null) {
                if ($last_col->getOption () == 'ordernum') {
                    array_pop ($order_string_elements);
                }
            }
            
            return implode (',', $order_string_elements);
        } else {
            return false;
        }
        
    }
    
    /**
     * Gets the column that joins a joiner table to the joined table.
     * For this to work, the first two links defined for the table must be the
     * joiner links (i.e. the links to the parent tables that define a
     * many-many relationship)
     * 
     * @param Table $parent_table the parent table, for example if this table
     *        is UserPrefs and the parent is Users, this method will provide
     *        the column that links to Prefs.
     * @return mixed the joiner {@link Column} if it exists, or null on
     *         failure. It should never fail.
     */
    function getJoinerColumn (Table $parent_table) {
        if ($this->is_joiner !== true) throw new Exception ("Called getJoinerColumn on non-joiner table");
        
        $columns = $this->columns;
        reset ($columns);
        foreach ($columns as $col_id => $col) {
            $link_data = $col->getLink ();
            if ($link_data !== null and $link_data->getToColumn ()->getTable () !== $parent_table) {
                return $col;
            }
        }
        return null;
    }
    
    /**
     * Gets the children tables of this table
     * @return array Children tables (array of Table)
     */
    function getChildren () {
        if ($this->database == null) throw new exception("No database set!");
        
        $tables = $this->database->getTables();
        $children = array();
        foreach ($tables as $toTable) {
            $column = $toTable->getLinkToTable($this);
            if ($column != null) {
                $link = $column->getLink();
                if ($link->isParent()) {
                    $children[] = $toTable;
                }
            }
        }
        
        return $children;
    }
    
    /**
     * Gets array of links to child tables
     * @return array Link objects
     */
    function getChildLinks () {
        if ($this->database == null) throw new exception ("No database set!");
        
        $tables = $this->database->getTables ();
        $children = array ();
        foreach ($tables as $toTable) {
            $column = $toTable->getLinkToTable ($this);
            if ($column != null) {
                $link = $column->getLink ();
                if ($link->isParent ()) {
                    $children[] = $link;
                }
            }
        }
        
        return $children;
    }
    
    /**
     * sets the parent database for this table
     * 
     * @param mixed $database a {@link Database}, or null
     */
    function setDatabase ($database) {
        if ($database === null or $database instanceOf Database) {
            $this->database = $database;
        } else {
            throw new Exception ('$database needs to be a Database object, or null');
        }
    }
    
    /**
     * gets the parent database for this table
     * 
     * @author benno, 2008-07-10 - I'm surprised this didn't already exist
     * 
     * @return mixed $database a {@link Database}, or null
     */
    function getDatabase () {
        return $this->database;
    }
    
    /**
     * sets whether or not a table is defined as being a joiner table (used for
     * many-many relationships) or not
     * 
     * @param bool $val
     */
    function setJoiner ($val) {
        if ($val) {
            $this->is_joiner = true;
        } else {
            $this->is_joiner = false;
        }
    }
    
    /**
     * gets whether or not a table is defined as being a joiner table (used for
     * many-many relationships) or not
     * 
     * @return bool $val
     */
    function isJoiner () {
        return $this->is_joiner;
    }
    
    /**
     * gets whether or not the top nodes of a tree table are allowed to be
     * renamed, re-ordered, or added or deleted.
     * 
     * @return bool
     */
    function getTopNodesEnabled () {
        return $this->top_nodes_enabled;
    }
    
    /**
     * sets whether or not the top nodes of a tree table are allowed to be
     * renamed, re-ordered, or added or deleted.
     * 
     * Modifying top nodes needs to be disabled when designs have CSS hard set
     * for the data of the top nodes
     * 
     * @param bool $val true to allow hacking top nodes, false to disable it.
     */
    function setTopNodesEnabled ($val) {
        if ($val) {
            $this->top_nodes_enabled = true;
        } else {
            $this->top_nodes_enabled = false;
        }
    }
    
    /**
     * sets a singular English name to use for this table (e.g. the singular of
     * 'People' is 'Person')
     * 
     * @param string $name the name to use
     */
    function setNameSingle ($name) {
        $this->name_single = $name;
    }
    
    /**
     * gets the singular English name to use for this table (e.g. the singular
     * of 'People' is 'Person')
     * 
     * @return string the name to use
     */
    function getNameSingle () {
        return $this->name_single;
    }
    
    
    /**
     * sets the disable_parent_edit attribute, so you can disable editing of
     * parent data when in a child
     * @param bool $value the new value
     */
    function setDisableParentEdit ($value) {
        $this->disable_parent_edit = (bool) $value;
    }
    
    /**
     * gets the disable_parent_edit attribute
     * @return bool The value
     */
    function getDisableParentEdit () {
        return $this->disable_parent_edit;
    }
    
    /**
     * Returns the row identifier columns and strings for this table. Uses the
     * same form as link description.
     * @return array The columns and strings
     */
    function getRowIdentifier () {
        return $this->row_identifier;
    }
    
    /**
     * Sets the row identifier. Uses the same format as link description
     * 
     * A row identifier is a human-readable string used to identify a row by
     * its defining attributes. Each identifier is a mixture of string and
     * Column components, allowing you to intertwine static and dynamic
     * content. For example, a personal record might be identified by the
     * person's first and last name, which would be 3 components: FirstName
     * column, ' ', and LastName; or perhaps LastName, ', ', and FirstName.
     * 
     * @param array $value The columns and strings
     */
    function setRowIdentifier ($value) {
        if (is_array ($value)) {
            $this->row_identifier = $value;
        }
    }
    
    /**
     * Appends a component to row identifier for this table.
     * 
     * @param mixed $value The identifier to add (either a Column or a string)
     */
    function addRowIdentifier ($value) {
        
        if (!$value instanceof Column) {
            $value = (string) $value;
        }
        
        $this->row_identifier[] = $value;
    }
    
    /**
     * Gets all the view items for a specific view
     * @param int $view The view to get the view items of:
     *        'list', 'add', 'edit', or 'export'
     * @return array The view items
     * @author Josh 2007-08-24
     */
    function getView ($view) {
        switch ($view) {
            case 'list': return $this->list_view;
            case 'add': return $this->add_view;
            case 'edit': return $this->edit_view;
            case 'export': return $this->export_view;
            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
    }
    
    
    /**
     * Gets all the view items for a specific view as a reference, for direct
     * manipulation. Use this function at your own risk!
     * 
     * @param string $view The view to get the view items of:
     *        'list', 'add', 'edit', or 'export'
     * @return &array The view items
     * @author benno 2010-10-25
     */
    function &getViewRef ($view) {
        switch ($view) {
            case 'list': return $this->list_view;
            case 'add': return $this->add_view;
            case 'edit': return $this->edit_view;
            case 'export': return $this->export_view;
            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
    }
    
    
    /**
     * Set all the items for a view
     * @param string $view The view to set the items for
     * @param array $new_list The new view items
     * @author Josh 2007-09-04
     */
    function setView ($view, $new_list) {
        switch ($view) {
            case 'list':
                $this->list_view = $new_list;
                break;
                
            case 'add':
                $this->add_view = $new_list;
                break;
                
            case 'edit':
                $this->edit_view = $new_list;
                break;
                
            case 'export':
                $this->export_view = $new_list;
                break;
                
            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
    }
    
    /**
     * Sets the AddEdit view to a new list
     * Get the format right or you will kill the system (see getAddEditView)
     */
    function setAddEditView ($new_list) {
        $this->add_edit_view = $new_list;
    }
    
    /**
     * Gets all the view items that are columns for a specific view.
     * @param int $view The view (constant) to get the view items of
     * @return array The view items
     * @author Josh 2007-08-24
     */
    function getViewColumns ($view) {
        switch ($view) {
            case 'list': $items = $this->list_view; break;
            case 'add': $items = $this->add_view; break;
            case 'edit': $items = $this->edit_view; break;
            case 'export': $items = $this->export_view; break;
            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
        $return = array();
        foreach ($items as $item) {
            if ($item instanceof ColumnViewItem) {
                $return[] = $item;
            }
        }
        return $return;
    }
    
    /**
     * Determine if a column is in a view
     * @param int $view The view (constant) to look for the column in
     * @param Column $column The column to look for in the view
     * @param bool $require_editable Set to true to require the column to be
     *        editable.
     * @return bool True if the column is in this view, false otherwise
     * @author Josh 2007-09-03
     */
    function isColumnInView ($view, $column, $require_editable = false) {
        switch ($view) {
            case 'list':
                foreach ($this->list_view as $view_item) {
                    if ($view_item instanceof ColumnViewItem) {
                        if ($view_item->getColumn () === $column) {
                            return true;
                        }
                    }
                }
                return false;
                
            case 'add':
                foreach ($this->add_view as $view_item) {
                    if ($view_item instanceof ColumnViewItem) {
                        if ($view_item->getColumn () === $column) {
                            return true;
                        }
                    }
                }
                return false;

            case 'edit':
                foreach ($this->edit_view as $view_item) {
                    if ($view_item instanceof ColumnViewItem) {
                        if ($view_item->getColumn () === $column) {
                            if (($require_editable == false) or ($view_item->getEditable())) {
                                return true;
                            }
                        }
                    }
                }
                return false;

            case 'export':
                foreach ($this->export_view as $view_item) {
                    if ($view_item instanceof ColumnViewItem) {
                        if ($view_item->getColumn () === $column) {
                            return true;
                        }
                    }
                }
                return false;

            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
    }
    
    /**
     * Appends a view item to the end of a view.
     * @param int $view The view (constant) to append the view item to
     * @param ViewItem $view_item The item to append to the end of the view
     * @author Josh 2007-08-24
     */
    function appendView ($view, ViewItem $view_item) {
        switch ($view) {
            case 'list': $this->list_view[] = $view_item; break;
            case 'add': $this->add_view[] = $view_item; break;
            case 'edit': $this->edit_view[] = $view_item; break;
            case 'export': $this->export_view[] = $view_item; break;
            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
    }
    
    /**
     * Inserts a view item into the middle of a view
     *
     * @param int $view The view (constant) to append the view item to
     * @param int $after The index of the item to put this item after
     *            (-1 to put the item at the very start of the view)
     * @param ViewItem $view_item The item to add to the view
     * @author josh 2008-04-01
     * @author benno 2010-11-09 added ability to add to the start of the view
     */
    function insertView ($view, $after, ViewItem $view_item) {
        if ($after < 0) {
            switch ($view) {
                case 'list':
                    $this->list_view = array_merge (array ($view_item), $this->list_view);
                    break;
                case 'add':
                    $this->add_view = array_merge (array ($view_item), $this->add_view);
                    break;
                case 'edit':
                    $this->edit_view = array_merge (array ($view_item), $this->edit_view);
                    break;
                case 'export':
                    $this->export_view = array_merge (array ($view_item), $this->export_view);
                    break;
                default:
                    throw new exception ("Invalid view requested; view {$view} is not valid");
            }
            return;
        }
        
        switch ($view) {
            case 'list':
                $before_items = array_slice ($this->list_view, 0, $after);
                $after_items = array_slice ($this->list_view, $after);
                $this->list_view = array_merge ($before_items, array ($view_item), $after_items);
                break;
                
            case 'add':
                $before_items = array_slice ($this->add_view, 0, $after);
                $after_items = array_slice ($this->add_view, $after);
                $this->add_view = array_merge ($before_items, array ($view_item), $after_items);
                break;
                
            case 'edit':
                $before_items = array_slice ($this->edit_view, 0, $after);
                $after_items = array_slice ($this->edit_view, $after);
                $this->edit_view = array_merge ($before_items, array ($view_item), $after_items);
                break;
                
            case 'export':
                $before_items = array_slice ($this->export_view, 0, $after);
                $after_items = array_slice ($this->export_view, $after);
                $this->export_view = array_merge ($before_items, array ($view_item), $after_items);
                break;
                
            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
    }
    
    /**
     * Removes an item from a view
     * 
     * @param int $view_type the view: 'list', 'add', 'edit', or 'export'
     * @param mixed $item the item to remove (a Column object, an include file
     *                name, a heading name, or an include descriptive name)
     * @return bool True if something was removed
     * @author benno 2010-10-25
     */
    function removeFromView ($view_type, $item) {
        if ($item instanceof Column) {
            return $this->removeColumnFromView ($view_type, $item);
        } else if (substr ($item, -4) == '.php') {
            return $this->removeIncludeFromView ($view_type, $item);
        } else {
            if ($this->removeHeadingFromView ($view_type, $item)) return true;
            return $this->removeIncludeFromView ($view_type, $item);
        }
    }
    
    
    /**
     * removes a Column from a view
     * @param int $view_type the view: 'list', 'add', 'edit', or 'export'
     * @param Column $col The column
     * @return bool True if the column was removed
     * @author benno 2010-10-25 initial version
     * @author benno 2010-11-09 use removeItemFromViewByKey
     */
    function removeColumnFromView ($view_type, Column $col) {
        $view = $this->getView ($view_type);
        foreach ($view as $key => $item) {
            if ($item instanceof ColumnViewItem and $item->getColumn () === $col) {
                $this->removeItemFromViewByKey ($view_type, $key);
                return true;
            }
        }
        return false;
    }
    
    
    /**
     * removes a file include from a view
     * @param int $view_type the view: 'list', 'add', 'edit', or 'export'
     * @param string $name The name of the file, e.g. inc_example.php, or its
     *        descriptive name
     * @return bool True if the file include was removed
     * @author benno 2010-10-25 initial version
     * @author benno 2010-11-09 use removeItemFromViewByKey
     */
    function removeIncludeFromView ($view_type, $name) {
        $view = &$this->getViewRef ($view_type);
        foreach ($view as $key => $item) {
            if ($item instanceof IncludeViewItem and $item->getFilename () == $name) {
                $this->removeItemFromViewByKey ($view_type, $key);
                return true;
            }
        }
        foreach ($view as $key => $item) {
            if ($item instanceof IncludeViewItem and strcasecmp ($item->getName (), $name) == 0) {
                $this->removeItemFromViewByKey ($view_type, $key);
                return true;
            }
        }
        return false;
    }
    
    
    /**
     * removes a heading from a view
     * @param int $view_type the view: 'list', 'add', 'edit', or 'export'
     * @param string $heading The heading, e.g. Postal address
     *        (case-insensitive)
     * @return bool True if the heading was removed
     * @author benno 2010-10-25 initial version
     * @author benno 2010-11-09 use removeItemFromViewByKey
     */
    function removeHeadingFromView ($view_type, $heading) {
        $view = &$this->getViewRef ($view_type);
        foreach ($view as $key => $item) {
            if ($item instanceof HeadingViewItem and strcasecmp ($item->getName (), $heading) == 0) {
                $this->removeItemFromViewByKey ($view_type, $key);
                return true;
            }
        }
        return false;
    }
    
    
    /**
     * Removes an item from a view by key, and resets the keys to be contiguous.
     * Use this function at your own risk!
     * @param int $view_type the view: 'list', 'add', 'edit', or 'export'
     * @param int $key the numeric key of the item in question
     * @author benno 2011-11-09
     */
    function removeItemFromViewByKey ($view_type, $key) {
        settype ($key, 'int');
        $view = $this->getView ($view_type);
        unset ($view[$key]);
        $view = array_merge ($view);
        $this->setView ($view_type, $view);
    }
    
    
    /**
     * Appends an item to the AddEdit view.
     * This view is ONLY to be used in setup
     *
     * @param ViewItem $item The item to add
     * @param string $add_flag The add flag ('y', 'n', 'v')
     * @param string $edit_flag The edit flag ('y', 'n', 'v')
     */
    function appendAddEditView (ViewItem $item, $add_flag, $edit_flag) {
        $new_item = array();
        $new_item['item'] = $item;
        
        // determine the addibility
        switch ($add_flag) {
            case 'y':
                $new_item['add'] = true;
                break;
            case 'n':
                $new_item['add'] = false;
                break;
        }
        
        // determine the editablility
        switch ($edit_flag) {
            case 'y':
                $new_item['edit_view'] = true;
                $new_item['edit_change'] = true;
                break;
            case 'n':
                $new_item['edit_view'] = false;
                $new_item['edit_change'] = false;
                break;
            case 'v':
                $new_item['edit_view'] = true;
                $new_item['edit_change'] = false;
                break;
        }
        
        $this->add_edit_view[] = $new_item;
    }
    
    /**
     * Inserts an item into the AddEdit view.
     * This view is ONLY to be used in setup
     *
     * @param ViewItem $item The item to add
     * @param int $after The index of the item to put this item after
     * @param string $add_flag The add flag ('y', 'n', 'v')
     * @param string $edit_flag The edit flag ('y', 'n', 'v')
     */
    function insertAddEditView (ViewItem $item, $after, $add_flag, $edit_flag) {
        $new_item = array ();
        $new_item['item'] = $item;
        
        // determine the addibility
        switch ($add_flag) {
            case 'y':
                $new_item['add'] = true;
                break;
            case 'n':
                $new_item['add'] = false;
                break;
        }
        
        // determine the editablility
        switch ($edit_flag) {
            case 'y':
                $new_item['edit_view'] = true;
                $new_item['edit_change'] = true;
                break;
            case 'n':
                $new_item['edit_view'] = false;
                $new_item['edit_change'] = false;
                break;
            case 'v':
                $new_item['edit_view'] = true;
                $new_item['edit_change'] = false;
                break;
        }
        
        // put the new item where it belongs
        $before_items = array_slice ($this->add_edit_view, 0, $after);
        $after_items = array_slice ($this->add_edit_view, $after);
        $new_items = array_merge ($before_items, array ($new_item), $after_items);
        
        $this->add_edit_view = $new_items;
    }
    
    /**
     * Gets the AddEdit view.
     * Returns an array of arrays. Each array has the following keys:
     * 'item' => the view item
     * 'add' => if add viewing/changing is enabled
     * 'edit_view' => if edit viewing is enabled
     * 'edit_change' => if edit changing is enabled
     *
     * This view is ONLY to be used in setup
     */
    function getAddEditView () {
        return $this->add_edit_view;
    }
    
    /**
     * Clears the add/edit view
     *
     * This view is ONLY to be used in setup
     */
    function clearAddEditView () {
        $this->add_edit_view = array();
    }
    
    /**
     * Clears the specified view
     * @param int $view The view (constant) to clear.
     */
    function clearView ($view) {
        switch ($view) {
            case 'list': $this->list_view = array (); break;
            case 'add': $this->add_view = array (); break;
            case 'edit': $this->edit_view = array (); break;
            case 'export': $this->export_view = array (); break;
            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
    }
    
    /**
     * Returns the ColumnViewItem for a specific column, as specified with the
     * column name, in the specified view
     *
     * @author Josh 2008-03-06
     * @param int $view The view (constant) of the view to search for the
     *        column in
     * @param string $column_name The name of the column to find in the view
     * @param bool $return_index If this is set to true, the function will
     *        return the index in the view, not the item itself. Defaults to
     *        false
     * @return mixed If the column is found, the ColumnViewItem, or the index
     *         (depending on the $return_index parameter). If not found, null
     */
    function getColumnInView ($view, $column_name, $return_index = false) {
        // work out what view we will be searching
        switch ($view) {
            case 'list': $search_view = &$this->list_view; break;
            case 'add': $search_view = &$this->add_view; break;
            case 'edit': $search_view = &$this->edit_view; break;
            case 'export': $search_view = &$this->export_view; break;
            default:
                throw new exception ("Invalid view requested; view {$view} is not valid");
        }
        
        // do the search itself
        foreach ($search_view as $index => $view_item) {
            if ($view_item instanceof ColumnViewItem) {
                if ($view_item->getColumn ()->getName () == $column_name) {
                    // we found the column
                    if ($return_index) {
                        return $index;
                    } else {
                        return $view_item;
                    }
                }
            }
        }
        
        // nothing found
        return null;
    }
    
    /**
     * Returns the ColumnViewItem for a specific column, as specified with the
     * column name, in the specified view.
     * This view is ONLY to be used in setup
     *
     * Return value, if $return_index is false, is an array of the following
     * format:
     *    'item' => the view item
     *    'add' => if add viewing/changing is enabled
     *    'edit_view' => if edit viewing is enabled
     *    'edit_change' => if edit changing is enabled
     *
     * @author Josh 2008-03-06
     * @param string $column_name The name of the column to find in the view
     * @param bool $return_index If this is set to true, the function will
     *        return the index in the view, not the item itself. Defaults to
     *        false
     * @return mixed If the column is found, an array of the add_edit view
     *         item, as desribed above, or the index (depending on the
     *         $return_index parameter) If not found, null.
     */
    function getColumnInAddEditView ($column_name, $return_index = false) {
        // do the search itself
        foreach ($this->add_edit_view as $index => $item) {
            $view_item = $item['item'];
            
            if ($view_item instanceof ColumnViewItem) {
                if ($view_item->getColumn ()->getName () == $column_name) {
                    
                    // we found the column
                    if ($return_index) {
                        return $index;
                    } else {
                        return $item;
                    }
                }
            }
        }
        
        // nothing found
        return null;
    }
    
    /**
     * gets the column that links from the current table to a specified table
     * 
     * @param Table $table the table to find a link to
     * @return mixed a {@link Column} if a link is found, or null otherwise
     */
    function getLinkToTable (Table $table) {
        $link_col = null;
        $columns = $this->columns;
        reset ($columns);
        foreach ($columns as $id => $col) {
            $link_data = $col->getLink ();
            if ($link_data != null and $link_data->getToColumn ()->getTable () === $table) {
                $link_col = $col;
                break;
            }
        }
        return $link_col;
    }
    
    /**
     * Returns all links that are made from columns on this table
     * @return array of {@link Link} objects.
     */
    function getLinks () {
        $links = array ();
        foreach ($this->columns as $column) {
            if ($column->hasLink ()) {
                $links[] = $column->getLink ();
            }
        }
        return $links;
    }
    
    /**
     * gets whether deleting rows from this table activates a confirm dialogue
     * on the main interface
     * 
     * @return bool true for a confirm dialogue, false to allow the user to
     *         delete straight up
     */
    function getConfirmDel () {
        return $this->confirm_delete;
    }
    
    /**
     * sets whether deleting rows from this table activates a confirm dialogue
     * on the main interface
     * 
     * @param bool true for a confirm dialogue, false to allow the user to
     *        delete straight up
     */
    function setConfirmDel ($bool) {
        if ($bool) {
            $this->confirm_delete = true;
        } else {
            $this->confirm_delete = false;
        }
    }
    
    /**
     * Gets whether deleting one or more rows from this table will cause
     * records in sub-tables to be deleted as well.
     * If for example you have a Users table and a UserPrefs table, with the
     * UserPrefs having a field UserID that has a parental link to Users.ID. In
     * this instance, if this option was enabled, all the user's preferences
     * would be removed if the user was removed.
     *
     * @return bool True to cascade deletes, false to maintain the old behavior.
     */
    function getCascadeDel () {
        return $this->cascade_delete;
    }
    
    /**
     * Sets the cascade delete option. See getCascadeDel for a full explanation
     * of cascade deletes.
     *
     * @param bool $value The new value to use for this paramater
     */
    function setCascadeDel ($value) {
        if ($value) {
            $this->cascade_delete = true;
        } else {
            $this->cascade_delete = false;
        }
    }
    
    /**
     * Gets the list of alternate pages to use when editing data in this table.
     * 
     * @return array The keys are the default page names, values are the
     *         alternates to use.
     */
    function getAltPages () {
        return $this->alt_pages;
    }
    
    /**
     * Gets the list of alternate buttons to use when editing data in this
     * table.
     * 
     * @return array The keys are the default page names, values the alternates
     *         to use.
     */
    function getAltButtons () {
        return $this->alt_buttons;
    }
    
    /**
     * Gets the complete list of page URLs to use when editing data in this
     * table.
     * 
     * Usage: <code>list($urls, $seps) = $table->getPageUrls();</code>
     * 
     * @return array Two keys: 0 contains an array of the URLs (standard or
     *         alternate) to use for each page; and 1 contains an array with
     *         the separators to use for each page. For both arrays, the keys
     *         are the original page names (without the .php suffix).
     */
    function getPageUrls () {
        $urls = array ();
        $seps = array ();
        $defaults = array (
            'main',
            'main_action',
            'main_add',
            'main_add_action',
            'main_edit',
            'main_edit_action',
            'main_search_action',
            'main_order'
        );
        
        reset ($defaults);
        foreach ($defaults as $id => $page) {
            if (!isset ($this->alt_pages[$page])) {
                $urls[$page] = "{$page}.php";
                $seps[$page] = '?';
            } else {
                $urls[$page] = $this->alt_pages[$page];
                if (strpos ($this->alt_pages[$page], '?') === false) {
                    $seps[$page] = '?';
                } else {
                    $seps[$page] = '&';
                }
            }
        }
        
        return array ($urls, $seps);
    }
    
    /**
     * Sets an alternate page to use when editing data in this table.
     * 
     * @param string $name The default page name for the action in question.
     * @param string $val The alternate page to use.
     */
    function setAltPage ($name, $val) {
        $this->alt_pages[$name] = $val;
    }
    
    /**
     * Cancels use of an alternate page when editing data in this table.
     * 
     * @param string $name The default page name for the action in question.
     */
    function unsetAltPage ($name) {
        unset ($this->alt_pages[$name]);
    }
    
    /**
     * Sets an alternate button to use when editing data in this table.
     * 
     * @param string $name The default button name for the action in question.
     * @param string $val The alternate button text to use.
     */
    function setAltButton ($name, $val) {
        $this->alt_buttons[$name] = $val;
    }
    
    /**
     * Cancels use of an alternate button when editing data in this table.
     * 
     * @param string $name The default button name for the action in question.
     */
    function unsetAltButton ($name) {
        unset ($this->alt_buttons[$name]);
    }
    
    /**
     * Replaces the current mask with a randomly generated new one.
     * The new mask will not be the same as any other table in this database
     */
    function newMask () {
        $tables = $this->database->getTables ();
        
        // determine other tables masks
        $other_masks = array ();
        foreach ($tables as $table) {
            $other_masks[] = $table->getMask ();
        }
        
        // keep generating masks until we have one
        // there will be a problem when you have more than 75,418,890,625 tables.
        do {
            $mask = generate_code (6);
        } while (in_array($mask, $other_masks));
        
        $this->mask = $mask;
    }
    
    /**
     * Gets the mask that has been applied to hide this table's name
     * @return string mask
     */
    function getMask () {
        return $this->mask;
    }
    
    /**
     * Sets the mask to a pre-defined value.
     * @param string $new_mask the preset mask to apply
     */
    function setMask ($new_mask) {
        $this->mask = $new_mask;
    }
    
    /**
     * Gets the display style for this table: TABLE_DISPLAY_STYLE_ROWS or
     * TABLE_DISPLAY_STYLE_TREE
     * @return int style
     */
    function getDisplayStyle () {
        return $this->display_style;
    }
    
    /**
     * Sets the display style for this table: TABLE_DISPLAY_STYLE_ROWS or
     * TABLE_DISPLAY_STYLE_TREE
     * @param int $new_style the display style to use.
     */
    function setDisplayStyle ($new_style) {
        
        settype ($new_style, 'int');
        if ($new_style === TABLE_DISPLAY_STYLE_ROWS or $new_style === TABLE_DISPLAY_STYLE_TREE) {
            $this->display_style = (int) $new_style;
        } else {
            throw new Exception ('Table style must be TABLE_DISPLAY_STYLE_ROWS or TABLE_DISPLAY_STYLE_TREE');
        }
    }
    
    /**
     * Clears all meta-data for this table.
     */
    function wipe () {
        $this->columns = array ();
        $this->order = array ('view' => array (), 'search' => array ());
        $this->indices = array ('PRIMARY KEY' => array ());
        
        $this->list_view = array ();
        $this->add_view = array ();
        $this->edit_view = array ();
        $this->export_view = array ();
        
        $this->add_edit_view = array ();
    }
    
    /**
     * Sets the name of this table.
     * @param string $new_name the name to apply
     */
    function setName ($new_name) {
        $this->name = (string) $new_name;
    }
    
    /**
     * Gets a column defined in this table, or null if the desired name is not
     * found.
     *
     * @param string $column_name the name of the desired column
     * @return Column The column meta-data
     */
    function getColumn ($column_name) {
        $found_column = null;
        
        foreach ($this->columns as $column) {
            if ($column->getName () == $column_name) {
                $found_column = $column;
                break;
            }
        }
        
        return $found_column;
    }
    
    /**
     * Gets the position in the table for a column defined in this table, or
     * null if the column is not found.
     * The first column in the table is position 0, the second column is
     * position 1, etc.
     *
     * @param string $column_name the name of the desired column
     * @return int The 0-basedposition of the column in the table.
     */
    function getColumnPosition ($column_name) {
        $found_index = null;
        
        foreach ($this->columns as $index => $column) {
            if ($column->getName () == $column_name) {
                $found_index = $index;
                break;
            }
        }
        
        return $found_index;
    }
    
    /**
     * Gets a column in the table using the column position. Returns null if
     * the position is invalid
     * The first column in the table is position 0, the second column is
     * position 1, etc.
     *
     * @param int $position The 0-based position of the column in the table.
     * @return Column The column meta-data
     */
    function getColumnByPosition ($position) {
        $position = (int) $position;
        
        if ($position < 0) return null;
        if ($position >= count($this->columns)) return null;
        
        return $this->columns[$position];
    }
    
    /**
     * Short for {@link getColumn}
     *
     * @param string $column_name the name of the desired column
     * @return Column column meta-data
     */
    function get ($column_name) {
        return $this->getColumn ($column_name);
    }
    
    /**
     * Gets a parent Table specified by name
     *
     * @param string $table_name The name of the parent Table
     * @return mixed The parent Table, or null if no match was found
     */
    function getParent ($table_name) {
        $links = $this->getLinks ();
        foreach ($links as $link) {
            if ($link->isParent ()) {
                $linked_table = $link->getToTable ();
                if ($linked_table->getName () == $table_name) return $linked_table;
            }
        }
        return null;
    }
    
    /**
     * Gets a column defined in this table (search by mask), or null if the
     * desired mask was not found.
     *
     * @param string $mask the mask of the desired column
     * @return Column $found_column column meta-data
     */
    function getColumnByMask ($mask) {
        foreach ($this->columns as $column) {
            if (!($column instanceof FileColumn)) continue;
            if ($column->getMask () == $mask) return $column;
        }
        return null;
    }
    
    /**
     * Adds meta-data for another column in this table
     *
     * @param Column $column column meta-data
     * @param integer $insert_after What column to insert this one after.
     *        NULL to insert at the end, and -1 at the very beginning
     */
    function addColumn (Column $column, $insert_after = null) {
        // see if there is already a column with this name, replace it if so
        $use_id = -1;
        foreach ($this->columns as $exist_col_id => $exist_col) {
            if ($exist_col->getName () == $column->getName ()) {
                $use_id = $exist_col_id;
                break;
            }
        }
        
        // Column Inserts
        if ($use_id == -1) {
            if ($insert_after == null) {
                // dump at end
                $this->columns[] = $column;
                
            } else {
                // dump in middle
                $columns = $this->columns;
                if ($insert_after == -1) {
                    // first element
                    array_unshift ($columns, $column);
                    
                } else {
                    // middle element
                    $before = array_slice ($columns, 0, $insert_after + 1);
                    $after = array_slice ($columns, $insert_after + 1);
                    $columns = array_merge ($before, array ($column), $after);
                    
                }
                $this->columns = $columns;
            }
            
        } else {
            // update
            $this->columns[$use_id] = $column;
        }
        
        $column->setTable ($this);
    }
    
    
    function replaceColumn(Column $old, Column $new) {
        $found = false;
        foreach ($this->columns as $key => $col) {
            if ($col === $old) {
                $this->columns[$key] = $new;
                $found = true;
                break;
            }
        }
        if (!$found) return;
        foreach ($this->list_view as $item) {
            if (!($item instanceof ColumnViewItem)) continue;
            if ($item->getColumn() === $old) {
                $item->setColumn($new);
            }
        }
        foreach ($this->add_view as $key => $item) {
            if (!($item instanceof ColumnViewItem)) continue;
            if ($item->getColumn() === $old) {
                $item->setColumn($new);
            }
        }
        foreach ($this->edit_view as $key => $item) {
            if (!($item instanceof ColumnViewItem)) continue;
            if ($item->getColumn() === $old) {
                $item->setColumn($new);
            }
        }
        foreach ($this->export_view as $key => $item) {
            if (!($item instanceof ColumnViewItem)) continue;
            if ($item->getColumn() === $old) {
                $item->setColumn($new);
            }
        }
        foreach ($this->add_edit_view as $key => $item) {
            if (!($item['item'] instanceof ColumnViewItem)) continue;
            if ($item['item']->getColumn() === $old) {
                $item['item']->setColumn($new);
            }
        }
        
        //TODO: when Link(ed)Columns are implemented, need to update any relevant
        // links to point to the new column
    }
    
    
    /**
     * Moves a column to a new position in this table
     * N.B. this only updates the metadata, not the database schema.
     * 
     * @param Column $col The column to move
     * @param mixed $position_after The Column in this table after which the
     *        specified Column should be placed, or null to place the Column at
     *        the start of the list of Columns.
     * @return void
     * @author benno 2010-11-09
     */
    function repositionColumn (Column $col, $position_after) {
        if ($position_after == null) {
            $cols = $this->columns;
            $index = array_search ($col, $cols, true);
            if ($index !== false) unset ($cols[$index]);
            $this->columns = array_merge (array ($col), $cols);
        } else {
            if (!$position_after instanceof Column) {
                throw new Exception ('Second parameter must be a Column, or null');
            }
            if ($col->getTable () !== $position_after->getTable ()) {
                throw new Exception ('First and second parameters must belong to the same table');
            }
            $cols = $this->columns;
            $index = array_search ($col, $cols, true);
            if ($index !== false) unset ($cols[$index]);
            $new_cols = array ();
            $positioned = false;
            foreach ($cols as $extant_col) {
                $new_cols[] = $extant_col;
                if ($extant_col === $position_after) $new_cols[] = $col;
            }
            $this->columns = $new_cols;
        }
    }
    
    
    /**
     * Removes meta-data for a column
     * 
     * @param Column $rem_col the column to remove
     * @return boolean True on success and false on failure.
     */
    function removeColumn (Column $rem_col) {
        // find column in this table, and remove it
        $res = false;
        $_SESSION['setup']['warn'] = array ();
        
        // check that the column is not in the priamry key
        $pk_cols = $this->getIndex ('PRIMARY KEY');
        foreach ($pk_cols as $col) {
            if ($col === $rem_col) {
                return false;
            }
        }
        
        // remove any references to said column from order options
        foreach ($this->order['view'] as $index => $item) {
            if ($item[0]->getName () === $rem_col->getName ()) {
                unset($this->order['view'][$index]);
                break;
            }
        }
        if (count ($this->order['view']) == 0) {
            $_SESSION['setup']['warn'][] = 'This table does not have any order columns; Your data is currently ordered by rand()';
        }
        
        // also remove column from search options
        foreach ($this->order['search'] as $index => $column) {
            if ($column->getName () === $rem_col->getName ()) {
                unset($this->order['search'][$index]);
                break;
            }
        }
        
        // remove from identifiers
        $identifier_list = $this->row_identifier;
        foreach ($identifier_list as $index => $item) {
            if ($item instanceof Column) {
                if ($item === $rem_col) {
                    unset ($identifier_list[$index]);
                }
            }
        }
        $this->row_identifier = $identifier_list;
        
        // remove from main view
        foreach ($this->list_view as $index => $view_item) {
            if ($view_item instanceof ColumnViewItem) {
                if ($view_item->getColumn () === $rem_col) {
                    unset ($this->list_view[$index]);
                }
            }
        }

        // remove from export view
        foreach ($this->export_view as $index => $view_item) {
            if ($view_item instanceof ColumnViewItem) {
                if ($view_item->getColumn () === $rem_col) {
                    unset ($this->export_view[$index]);
                }
            }
        }
        
        // remove from add/edit view
        foreach ($this->add_edit_view as $index => $view_item) {
            if ($view_item['item'] instanceof ColumnViewItem) {
                if ($view_item['item']->getColumn () === $rem_col) {
                    unset ($this->add_edit_view[$index]);
                }
            }
        }
        
        // Remove references to this table from links from other tables
        // The null check is there because during the table creation process,
        // the database reference is always null
        if ($this->database != null) {
            $tables = $this->database->getTables ();
            foreach ($tables as $table) {
                $links = $table->getLinks ();
                foreach ($links as $link) {
                    // check to
                    if ($link->getToColumn() === $rem_col) {
                        $link->getFromColumn ()->setLink (null);
                        $_SESSION['setup']['warn'][] = "Link from {$link->getFromColumn ()->getTable ()->getName ()}.{$link->getFromColumn ()->getName ()} has been severed due to link to column being removed";
                        
                    } else {
                        // check desc
                        $desc = $link->getDescription ();
                        foreach ($desc as $index => $item) {
                            if ($item === $rem_col) {
                                unset ($desc[$index]);
                            }
                        }
                        if (count ($desc) == 0) {
                            $link->getFromColumn ()->setLink (null);
                            $_SESSION['setup']['warn'][] = "Link from {$link->getFromColumn ()->getTable ()->getName ()}.{$link->getFromColumn ()->getName ()} has been severed due to removal of only description column";
                        } else {
                            $link->setDescription ($desc);
                        }
                    }
                }
            }
        }
        
        // remove the column itself
        foreach ($this->columns as $id => $col) {
            if ($col === $rem_col) {
                unset($this->columns[$id]);
                $res = true;
                break;
            }
        }
        
        // remove files
        if ($res) {
            if (($col->getOption () == 'file') || ($col->getOption () == 'image')) {
                $mask = $col->getTable ()->getMask () . '.' . $col->getMask ();
                $store_loc = ROOT_PATH_FILE . $col->getParam ('storage_location');
                if (substr ($store_loc, -1) != '/') $store_loc .= '/';
                
                $files = glob ($store_loc . $mask . '.*');
                $overall_status = true;
                foreach ($files as $file) {
                    $status = unlink ($file);
                    if ($status == false) $overall_status = false;
                }
                
                if ($overall_status == false) {
                    $_SESSION['setup']['warn'][] = 'Some files were unable to be deleted for this column. Storage Location: "' . $store_loc . '", Mask: "' . $mask . '"';
                }
            }
        }
        
        
        if (count ($_SESSION['setup']['warn']) == 0) {
            unset ($_SESSION['setup']['warn']);
        }
        
        return $res;
    }
    
    
    /**
     * Delete one or more records from this table, and associated files, etc.
     * This will also delete sub-records as appropriate, and move order number
     * columns too.
     *
     * @param array $record_pks The primary keys of the records to delete, as
     *        an array of arrays.
     *        Example: 2 integer pks, 3 records to be deleted:
     *        $record_pks = [ [<int>,<int>], [<int>,<int>], [<int>,<int>] ];
     * @return int Number of records deleted in this table (sub-tabs are not
     *         counted)
     * @author josh, 2007-09-07
     */
    function deleteRecords ($record_pks) {
        $debug = false;
        
        if ($debug) {
            echo '<div style="border: 1px black solid; padding: 1em; margin: 1em"><pre>';
            echo "Table: <strong>{$this->getName ()}</strong>\n";
        }
        
        // TODO: Simple record delete with a single query
        
        // Delete each record requested
        $success_count = 0;
        foreach ($record_pks as $pks) {
            $return = $this->deleteRecord ($pks);
            if ($return) $success_count++;
        }
        
        if ($debug) echo '</pre></div>';
        
        return $success_count;
    }
    
    /**
     * Delete a single record from tha database, as well as associated files
     * and sub-records. Will also maintain order number consistency.
     *
     * @param array $pks The primary keys for the record to delete, in the
     *        order they appear in the table definition.
     *        Example: 2 integer pks:
     *        $pks = [<int>,<int>];
     * @return bool True on success, false on failure
     * @author josh, 2007-09-07
     * @author benno, 2008-07-10 - added logging for static tables
     */
    function deleteRecord ($pks) {
        $debug = false;
        
        $primary_key_cols = $this->getPKNames ();
        
        if ($debug) echo "deleting record with pk = [" . implode (' , ', $pks) . "]\n";
        
        // Check we got enough keys.
        if (count ($primary_key_cols) != count ($pks)) {
            throw new exception ('Not enough primary keys passed. Expected '. count ($primary_key_cols).
                ', but got '. count ($pks));
        }
        
        // See if we can find an ordernumber column. Also make an array of the columns that are before it.
        $ordernum = null;
        $other_order_cols = array();
        foreach ($this->order['view'] as $index => $item) {
            if ($item[0]->getOption () == 'ordernum') {
                $ordernum = $item[0];
                if ($debug) echo "    has ordernum {$ordernum->getName()}\n";
                break;
            } else {
                $other_order_cols[] = $item[0]->getName ();
            }
        }
        
        // See what tables are children tables of this one, and store in an array.
        // Format:    [ "<this_col_name>" => {link_obj}, ... ]
        $child_links = array ();
        foreach ($this->database->getTables () as $table) {
            $column = $table->getLinkToTable ($this);
            if ($column != null) {
                $link = $column->getLink ();
                if ($link->isParent ()) {
                    if (! isset($child_links[$link->getToColumn ()->getName ()])) {
                        $child_links[$link->getToColumn ()->getName ()] = array ();
                    }
                    $child_links[$link->getToColumn ()->getName ()][] = $link;
                    if ($debug) echo "    has child link: {$link}\n";
                }
            }
        }
        
        // If this table has an ordernumber, do bumping.
        if ($ordernum != null) {
            if ($debug) echo "    doing ordernum bumping\n";
            
            // Determine a few things
            $ordernum_name = $ordernum->getName ();
            if (count ($other_order_cols) > 0) {
                foreach ($other_order_cols as $column) {
                    $order_params .= ", `{$column}`";
                }
            }
            
            // Build the query for getting the current ordernum value
            $q = "SELECT `{$ordernum_name}`{$order_params} FROM `". $this->getName (). "` WHERE ";
            
            // Build the pk for the where clause
            $j = 0;
            foreach ($primary_key_cols as $index => $pk_col) {
                if ($j++ > 0) $q .= ' AND ';
                $q .= "`{$pk_col}` = ". sql_enclose ($pks[$index]);
            }
            
            
            // Get the current ordernum
            $res = execq($q);
            if ($res->rowCount() == 1) {
                $row = fetch_assoc($res);
                
                settype($row[$ordernum_name], 'int');
                
                // Build the query that does the updates
                $q = "UPDATE `". $this->getName (). "` SET `{$ordernum_name}` = `{$ordernum_name}` - 1 WHERE ".
                    "`{$ordernum_name}` > {$row[$ordernum_name]}";
                
                // handle before-ordernum order columns
                if (count($other_order_cols) > 0) {
                    foreach ($other_order_cols as $param_name) {
                        if ($row[$param_name] === null) {
                            $q .= " AND `{$param_name}` IS NULL";
                        } else {
                            $order_col = $this->get ($param_name);
                            if ($order_col != null and $order_col->isNumeric ()) {
                                $value = new QueryFieldLiteral ($row[$param_name], false);
                            } else {
                                $value = $row[$param_name];
                            }
                            $q .= " AND `{$param_name}` = ". sql_enclose ($value, false);
                        }
                    }
                }
                
                // just do it
                execq($q);
                
                if ($this->isStatic ()) {
                    log_action ($this->getDatabase (), "Updated order in static table ". $this->getName (), $q);
                }
            }
        }
        
        
        // Do some per-column thinking.
        foreach ($this->columns as $column) {
            
            // Delete files and images.
            if (($column->getOption () == 'file') || ($column->getOption () == 'image')) {
                if ($debug) echo "    doing file deletions from {$column->getName ()}\n";
                // Get our storage location and file mask.
                $file_mask = $this->mask. '.'. $column->getMask (). '.';
                $store_loc = $column->getParam ('storage_location');
                if (substr ($store_loc, -1) != '/') {
                    $store_loc .= '/';
                }
                
                // Delete the thumbnails, if it has any.
                $thumbs = $column->getParam ('thumbnails');
                if ($thumbs != null) {
                    foreach ($thumbs as $name => $junk) {
                        @unlink (ROOT_PATH_FILE. $store_loc. $file_mask. implode (',', $pks). '.'. $name);
                    }
                }
                
                // Delete the file.
                @unlink (ROOT_PATH_FILE. $store_loc. $file_mask. implode (',', $pks));
            }
            
            
            // Delete child records.
            $links = @$child_links[$column->getName()];
            if ($links != null and $this->cascade_delete) {
                foreach ($links as $link) {
                    if ($debug) echo "    deleting child records from {$column->getName ()}\n";
                    // Determine the column index of the link to column.
                    foreach ($this->columns as $index => $column) {
                        if ($column === $link->getToColumn ()) {
                            break;
                        }
                    }
                    
                    // Get some table info so we can determine the records to delete.
                    $table = $link->getFromColumn ()->getTable ();
                    $table_pks = $table->getPKnames ();
                    
                    // Build a query that tells us the pks of the records to delete.
                    $q = 'SELECT `'. implode ('`, `', $table_pks). '` FROM `'. $table->getName (). '` WHERE `';
                    $q .= $link->getFromColumn ()->getName (). '` = '. $pks[$index];
                    
                    // Determine the columns to delete.
                    $delete_pks = array ();
                    $res = execq($q);
                    while ($row = fetch_row($res)) {
                        $delete_pks[] = $row;
                    }
                    
                    // Do a cascade delete on the children.
                    $table->deleteRecords ($delete_pks);
                }
            }
        }
        
        
        // Build the query that will delete the actual record.
        $q = "DELETE FROM `{$this->getName()}` WHERE ";
        $j = 0;
        foreach ($primary_key_cols as $index => $column) {
            if ($j++ > 0) $q .= ' AND ';
            
            $q .= "`{$column}` = ". sql_enclose ($pks[$index]);
        }
        $q .= ' LIMIT 1';
        
        // Delete the record.
        if ($debug) echo "    delete record\n";
        execq($q);
        
        if ($this->isStatic ()) {
            log_action ($this->getDatabase (), "Deleted row from static table ". $this->getName (), $q);
        }
        
        return true;
    }
    
    
    /**
     * Adds a column to an order list
     * 
     * @param string $partition - there are currently two order lists:
     *        1. view: how items should be ordered when viewing multiple rows
     *        2. search: the filter parameters for finding specific rows
     * @param Column $column column meta-data
     * @param string $order the order direction of columns in the view
     *        partition (ASC or DESC)
     */
    function addToOrder ($partition, Column $column, $order = '') {
        if ($partition != 'view') {
            $this->order[$partition][] = $column;
        } else {
            $order = strtoupper ($order);
            if ($order != 'DESC') {
                $order = 'ASC';
            }
            $this->order['view'][] = array ($column, $order);
        }
    }
    
    /**
     * Changes the direction used when a column is used to order results
     * 
     * @param Column $column The column which should have its order direction
     *        changed
     * @return bool True if the direction order was changed
     */
    function changeOrderDirection (Column $column) {
        $result = false;
        foreach ($this->order['view'] as $id => $item) {
            if ($item[0] === $column) {
                $new_dir = 'DESC';
                if (strtoupper($item[1]) == 'DESC') {
                    $new_dir = 'ASC';
                }
                $this->order['view'][$id][1] = $new_dir;
                $result = true;
                break;
            }
        }
        return $result;
    }
    
    /**
     * Build an identifier string for a row in this table
     * 
     * e.g. for a row in a table called Members, the identifier would likely
     * be the first and last name of the member.
     * 
     * @param array $primary_key Array of column_name => value for the primary
     *        key value
     * @author Josh 2007-07-17
     * @author Benno added support for multiple links to the same table, date
     *         formatting
     * @author Josh 2009-03-12, fixed a bug when there are no columns in the
     *         identifier.
     * @return string The identifier string
     */
    function buildIdentifier ($primary_key) {
        // empty identifier
        if (count($this->row_identifier) == 0) {
            return '';
        }
        
        $handler = new SelectQuery ($this);
        
        if (defined ('UPPER_CASE_AM_PM') and UPPER_CASE_AM_PM === true) {
            $lc_am_pm = false;
        } else {
            $lc_am_pm = true;
        }
        
        // determine what we need
        $joined_tables = array ();
        foreach ($this->row_identifier as $item) {
            if ($item instanceof Column) {
                
                // dates get proper formatting
                if ($item->getOption () == 'date') {
                    
                    $format = $item->getParam ('date_format');
                    if ($format == '') $format = '%d/%m/%Y';
                    $col = new DateTimeQueryColumn ($base_table, $item->getName ());
                    $col->setDateFormat ($format);
                
                // links get the appropriate JOINs
                } else if ($item->hasLink ()) {
                    
                    $to_col = $item->getLink ()->getToColumn ();
                    
                    // create the on clause for the join to the linked table
                    $join_table_name = $to_col->getTable ()->getName ();
                    $join_table = new QueryTable ($join_table_name);
                    
                    $logic = new LogicTree ();
                    $cond = new LogicConditionNode (
                        new QueryColumn ($base_table, $item->getName ()),
                        LOGIC_CONDITION_EQ,
                        new QueryColumn ($join_table, $to_col->getName ())
                    );
                    $logic->addCondition ($cond);
                    
                    // create the join
                    
                    // auto-generate alias for linked table
                    $alias_num = 1;
                    $existing_joins = $handler->getAllJoins ();
                    
                    if (count($existing_joins) > 0) {
                        foreach ($existing_joins as $join) {
                            
                            // find all numeric aliases, and use max(alias number) + 1 as alias number for new join
                            // also record any existing joins that don't have aliases, so they can have aliases applied
                            if ($join->getTable ()->getName () == $join_table_name) {
                                $existing_alias = $join->getTable ()->getAlias ();
                                if ($existing_alias != '') {
                                    
                                    $regex_matches = array ();
                                    preg_match (
                                        '/^'. preg_quote ($join_table_name). '([0-9]*)/',
                                        $existing_alias,
                                        $regex_matches
                                    );
                                    if ($alias_num <= $regex_matches[1]) {
                                        $alias_num = (int) $regex_matches[1] + 1;
                                    } else {
                                        echo "!{$alias_num} <= {$regex_matches[1]}<br>\n";
                                    }
                                    
                                } else {
                                    $join->getTable ()->setAlias ($join_table_name. $alias_num++);
                                }
                            }
                        }
                    }
                    
                    $join_table_alias = $join_table_name. $alias_num;
                    $join_table->setAlias ($join_table_alias);
                    
                    $join = new QueryJoin ($join_table, $logic);
                    $handler->addJoin ($join);
                    $joined_tables[] = $to_col->getTable ()->getName ();
                    
                    // get the query for this column, and change the alias to be useful
                    $link_query = $item->getChooserQuery ($join_table_alias);
                    $col = $link_query->getSelectFieldByAlias ('val');
                    $col->setAlias ($item->getName ());
                    
                    // add all the joins from the chooser query
                    $joins = $link_query->getAllJoins ();
                    foreach ($joins as $join) {
                        $handler->addJoin ($join);
                        $joined_tables[] = $join->getTable ()->getName ();
                    }
                
                // binary columns are processed as 'Y', 'N', or 'unknown'
                } else if ($item instanceof BooleanColumn) {
                    
                    $col = new QueryFieldLiteral (
                        "IF(`{$item->getName ()}` <=> 1, 'Y', IF(`{$item->getName ()}` <=> 0, 'N', 'unknown')) ".
                            "AS `{$item->getName ()}`",
                        false
                    );
                    
                // regular columns
                } else {
                    
                    $col = $item;
                }
                $handler->addSelectField ($col);
            }
        }
        
        
        // There may be a case where the identifier does not have any columns
        // If thats the case, don't do anything more with this query handler - just skip straight to
        // the code that actually creates the identifier.
        if (count ($handler->getSelectFields()) > 0) {
            
            // build where clause
            $logic_tree = $handler->getWhere ();
            foreach ($primary_key as $name => $value) {
                
                if (preg_match ('/^[0-9]+$/', cast_to_string ($value))) {
                    $escape_literal = false;
                } else {
                    $escape_literal = true;
                }
                
                $cond = new LogicConditionNode (
                    $this->get($name),
                    LOGIC_CONDITION_EQ,
                    new QueryFieldLiteral ($value, $escape_literal)
                );
                
                $logic_tree->addCondition ($cond, LOGIC_TREE_AND);
            }
            
            $handler->setLimit (1);
            
            // final query build
            $q = cast_to_string ($handler);
            if (@$_SESSION['setup']['view_q']) {
                echo "<pre>[id] q: {$q}</pre>";
            }
            
            // query execute
            $res = execq($q);
            $row = fetch_assoc($res);
        }
        
        
        // build output
        $output = '';
        foreach ($this->row_identifier as $item) {
            if ($item instanceof Column) {
                $output .= $row[$item->getName ()];
            } else {
                $output .= cast_to_string ($item);
            }
        }
        
        return $output;
    }
    
    /**
     * Add an index (eg the primary key) for this table.
     *
     * @param string $name the name for the index
     * @param mixed $columns a single Column, or an array of Columns to be
     *        indexed
     */
    function addIndex ($name, $columns) {
        $result = true;
        if (strtoupper ($name) == 'PRIMARY KEY') {
            if (is_array ($columns) and count($columns) > 0) {
                $this->indices['PRIMARY KEY'] = $columns;
            } else {
                $result = false;
            }
        } else if (!is_array ($columns)) {
            if ($name == '') {
                // auto-assign key number
                $this->indices[] = $columns;
            } else {
                $this->indices[$name] = $columns;
            }
        } else {
            $result = false;
        }
        return $result;
    }
    
    /**
     * Moves an ordered element up or down. If moving down, and there are no
     * elements below it, removes it from the list completely. Returns true on
     * success, false on failure.
     *
     * @param string partition which order list the action applies to
     * @param integer $id the key of the item that is to be moved
     * @param boolean $up whether to move the item up the list (i.e. use false
     *        for down)
     * @return boolean $result true if movement succeeded, false otherwise or
     *         if moving down removed the element
     */
    function ChangeOrder ($partition, $id, $up) {
        $result = false;
        if (isset ($this->order[$partition]) and $id >= 0 and $id < count($this->order[$partition])) {
            if ($up == true) {
                // move up
                if ($id == 0) {
                    // do nothing if already at top of list
                } else {
                    // move this element up
                    if (isset ($this->order[$partition][$id - 1]) and isset ($this->order[$partition][$id])) {
                        $tmp = $this->order[$partition][$id - 1];
                        $this->order[$partition][$id - 1] = $this->order[$partition][$id];
                        $this->order[$partition][$id] = $tmp;
                        $result = true;
                    }
                }
            } else {
                // move down
                if ($id == count($this->order[$partition]) - 1) {
                    // remove if already at bottom of list
                    unset ($this->order[$partition][$id]);
                } else {
                    // move this element down
                    if (isset ($this->order[$partition][$id + 1]) and isset ($this->order[$partition][$id])) {
                        $tmp = $this->order[$partition][$id + 1];
                        $this->order[$partition][$id + 1] = $this->order[$partition][$id];
                        $this->order[$partition][$id] = $tmp;
                        $result = true;
                    }
                }
            }
        }
        return $result;
    }
    
    /**
     * Gets the name of this table
     * 
     * @return string the name of the table
     */
    function getName () {
        return $this->name;
    }
    
    /**
     * Gets all of the column meta-data for this table
     *
     * @return array Array of Column objects
     */
    function getColumns () {
        return $this->columns;
    }
    
    /**
     * Gets the columns used for ordering.
     * 
     * @param string $partition the order set you want. Currently the only
     *        option is 'view'
     * 
     * @return array an Array in which each element is an array:
     *         - The 0th element is a Column object.
     *         - The 1st element is the order (ASC|DESC) to use with the column.
     */
    function getOrder ($partition) {
        return $this->order[$partition];
    }
    
    /**
     * Returns whether or not this table should be shown as an item in the main
     * table list
     * 
     * @return boolean true to display, false if this table should be excluded
     *         from the displayed list
     */
    function getDisplay () {
        return $this->display;
    }
    
    /**
     * Sets whether or not this table should be shown in the main table list
     * 
     * @param boolean $bool true to display, false otherwise
     */
    function setDisplay ($bool) {
        $this->display = (bool) $bool;
    }
    
    /**
     * Defines comments for this table
     *
     * @param string $str comments for database programmer's documentation
     */
    function setComments    ($str) {
        $this->comments = (string) $str;
    }
        
    /**
     * Sets an allowed option: add, edit, del or export.
     * These options control which buttons (ie add and/or delete) are visible
     * in main.php, and if records can be edited in main_edit.php.
     * 
     * @param string $action the option you wish to set
     * @param boolean $allowed whether the relevant action is allowed (true) or
     *        not (false)
     * 
     * @return boolean true if the option was set
     */
    function setAllowed ($action, $allowed) {
        $res = true;
        if ($allowed !== false) {
            $allowed = true;
        }
        if (in_array ($action, array ('add', 'edit', 'del', 'export'))) {
            $this->allowed_actions[$action] = $allowed;
        } else {
            $res = false;
        }
        return $res;
    }
    
    /**
     * Sets an a value for all allowed options
     * These options control which buttons (ie add and/or delete) are visible
     * in main.php, and if records can be edited in main_edit.php.
     * 
     * @param boolean $allowed whether the all actions should be allowed (true)
     *        or not (false)
     */
    function setAllAllowed ($allowed) {
        $valid = array ('add', 'edit', 'del', 'export');
        foreach ($valid as $action) {
            $this->allowed_actions[$action] = $allowed;
        }
    }
    
    /**
     * Checks whether an admin action is allowed.
     * 
     * The actions available are: add, edit, del.
     * 
     * @param string $action the action to check
     * @return boolean true if the action is allowed, false otherwise.
     */
    function getAllowed ($action) {
        return @$this->allowed_actions[$action];
    }
    
    /**
     * Returns an array of all the actions that are allowed for this table
     * @return array Everything thats allowed
     */
    function getAllAllowed () {
        $return = array ();
        foreach ($this->allowed_actions as $key => $val) {
            if ($val) $return[] = $key;
        }
        return $return;
    }
    
    /**
     * Gets the comments defined for this table
     * 
     * @return string Comments about this table, stored by and for the database
     *         programmer
     */
    function getComments () {
        return $this->comments;
    }
    
    /**
     * Gets the name of the auto-incrementing primary key column.
     * 
     * @return mixed the name of the auto-incrementing column in this table's
     *         primary key, or false if there isn't one.
     * 
     */
    function getAutoIncPK () {
        $auto_inc_pk = false;
        
        $pk = $this->indices['PRIMARY KEY'];
        if (count($pk) > 0) {
            foreach ($pk as $pk_col) {
                if (in_array ('AUTO_INCREMENT', $pk_col->getSqlAttributes ())) {
                    $auto_inc_pk = $pk_col->getName ();
                    break;
                }
            }
        }
        return $auto_inc_pk;
        
    }
    
    /**
     * Gets the next value of the given order number field
     * 
     * @param Column $order_num_field The order number field to increment
     * @return int The next value for the order number field
     */
    function getNextOrderNum (Column $order_num_field) {
        // get order by clause, use data for all higher order fields
        $where_clause_data = array ();
        $order_fields = $this->getOrder ('view');
        
        foreach ($order_fields as $field) {
            if ($field[0] === $order_num_field) {
                break;
            } else {
                $junk = '';
                $data = $field[0]->collateInputData($junk);
                if (count($data) != 1) {
                    $err = 'Wrong type of column in Ordernum';
                    throw new LogicException($err);
                }
                $where_clause_data[$field[0]->getName ()] = reset($data);
            }
        }
        
        // note: assume there is only 1 ordernum field per table, doesn't make sense otherwise
        $order_q = "SELECT `". $order_num_field->getName (). "` FROM `". $this->getName () . '`';
        if (count($where_clause_data) > 0) {
            $j = 0;
            foreach ($where_clause_data as $clause_field => $clause_data) {
                if ($j++ == 0) {
                    $order_q .= " WHERE ";
                } else {
                    $order_q .= " AND ";
                }
                if ($clause_data === null) {
                    $order_q .= "`{$clause_field}` IS NULL";
                } else {
                    $order_q .= "`{$clause_field}` = " . sql_enclose($clause_data);
                }
            }
        }
        $order_q .= " ORDER BY `". $order_num_field->getName (). "` DESC LIMIT 1";
        // echo "order q: {$order_q}<br>\n";
        $res = execq($order_q);
        $row = fetch_assoc($res);
        $new_id = $row[$order_num_field->getName ()] + 1;
        
        return $new_id;
    }
    
    /**
     * Gets the primary key values that were posted
     * 
     * @return mixed an array of values if the PK values were posted and there
     *         isn't an auto-increment field, otherwise returns false
     */
    function getPostedPK () {
        $val = true;
        
        $pk = $this->indices['PRIMARY KEY'];
        
        $pk_vals_arr = array ();
        foreach ($pk as $pk_col) {
            if (in_array ('AUTO_INCREMENT', $pk_col->getSqlAttributes ())) {
                $val = false;
                break;
            }
            try {
                $junk = '';
                $pk_vals = $pk_col->collateInputData($junk);
                if (count($pk_vals) != 1) {
                    throw new LogicException('Wrong type of column for a PK');
                }
                $pk_vals_arr[] = reset($pk_vals);
            } catch (Exception $e) {
                $val = false;
                break;
            }
        }
        if ($val !== false) $val = $pk_vals_arr;
        return $val;
    }
    
    
    /**
     * Gets the list of columns that define the search order for this table
     * 
     * @return array Array of Column items that are searchable, in order of
     *         importance
     */
    function getSearch () {
        if (is_array ($this->order['search'])) {
            return $this->order['search'];
        } else {
            return array ();
        }
    }
    
    
    /**
     * Changes the order of the columns used for searching.
     * Moving down an item that is already at the bottom of the list will
     * remove it.
     *
     * @param string $dir 'up' or 'down': which direction the item will be
     *        moved in the order.
     * @param integer $id the key of the item to move.
     */
    function searchMove ($dir, $id) {
        if (isset ($this->order['search'][$id])) {
            if ($dir == 'up') {
                if ($id > 0 and isset ($this->order['search'][$id - 1])) {
                    $temp_item = $this->order['search'][$id];
                    $this->order['search'][$id] = $this->order['search'][$id - 1];
                    $this->order['search'][$id - 1] = $temp_item;
                }
            } else if ($dir == 'down') {
                if ($id == count($this->order['search'])) {
                    // last item, remove it
                    unset ($this->order['search'][$id]);
                } else if (isset ($this->order['search'][$id + 1])) {
                    // move down
                    $temp_item = $this->order['search'][$id];
                    $this->order['search'][$id] = $this->order['search'][$id + 1];
                    $this->order['search'][$id + 1] = $temp_item;
                }
            }
        }
    }
    
    
    /**
     * Adds a column to the list of columns that define the search parameters.
     * 
     * @param Column $column the column to add
     */
    function searchAdd (Column $item) {
        $size = @count($this->order['search']);
        $this->order['search'][$size + 1] = $item;
    }
    
    
    /**
     * Sets the english name for this table.
     * 
     * Used so the administrator doesn't see the actual name of a table, e.g.
     * "Product information" instead of "ProdInf".
     * 
     * @param string $str the english name to use.
     */
    function setEngName ($str) {
        $this->english_name = (string) $str;
    }
    
    /**
     * Gets the english name of this table.
     *
     * @return string The english name.
     */
    function getEngName () {
        return $this->english_name;
    }
    
    /**
     * Gets the list of indices that are defined for this table.
     * 
     * @return array The list of indices. Each Index is an array of Column
     *         objects.
     */
    function getIndices () {
        return $this->indices;
    }
    
    /**
     * Gets a specific index that is defined for this table.
     * 
     * @param string $name the name of the index, e.g. PRIMARY KEY
     * @return array The desired index (an array of Column objects).
     */
    function getIndex ($name) {
        if (isset ($this->indices[$name])) {
            return $this->indices[$name];
        } else {
            throw new Exception ("Key {$name} is not defined for table ". $this->getName ());
        }
    }
    
    /**
     * Gets the names and columns for all the UNIQUE indices on this table.
     * Will not return the primary key.
     * Uses a SHOW INDEX query, so will also get indices not known by Tricho.
     * 
     * Return format: [ 'index_name' => [ 'col_name', 'col_name', ... ], ... ]
     * 
     * @return array The index information for all the unique indexes on the
     *         table in the format specified above
     */
    function getUniqueIndices () {
        $q = "SHOW INDEX FROM `{$this->getName()}`";
        $res = execq($q);
        
        $unique_indexes = array ();
        while ($row = fetch_assoc($res)) {
            if ($row['Non_unique'] == 0 and $row['Key_name'] != 'PRIMARY') {
                $unique_indexes[$row['Key_name']][] = $row['Column_name'];
            }
        }
        
        return $unique_indexes;
    }
    
    /**
     * Gets the names of the Primary Key columns which are defined for this
     * table.
     *
     * This is used to ensure that Primary Key fields are included in select
     * queries on the main edit page.
     * 
     * @return array Array of strings - each one is a column name.
     */
    function getPKnames () {
        $pk_names = array ();
        $pk_cols = $this->getIndex ('PRIMARY KEY');
        if ($pk_cols[0] == null) return array ();
        foreach ($pk_cols as $col) {
            $pk_names[] = $col->getName ();
        }
        return $pk_names;
    }
    
    /**
     * Gets an array where the keys are the names of the primary key columns,
     * and the values are the corresponding values for the designated row.
     * 
     * @return array Array of strings - each one is a value for a primary key
     *         column. These are ordered as per the primary key definition in
     *         the meta-data store.
     */
    function getPKvalues ($row) {
        $pk_data = array ();
        $pk_cols = $this->getIndex ('PRIMARY KEY');
        foreach ($pk_cols as $col) {
            $pk_data[$col->getName()] = $row[$col->getName()];
        }
        return $pk_data;
    }
    
    /**
     * Returns the error that should be shown to the user if the specified
     * unique or primary key has invalid values.
     * This function does not check for the error condition, it only returns
     * the error message.
     *
     * @param array $column_names The names of the columns that are part of the
     *        key in which the error was detected
     * @param bool $edit_mode If true, specifies that the error happened while
     *        editing, otherwise it is assumed it was while adding a record.
     * @param Table $parent The name of the parent table, when accessing this
     *        table as a subtab.
     * @return string The error message to return to the user
     */
    function getKeyError ($column_names, $edit_mode = false, $parent = null) {
        if (!is_array ($column_names)) {
            throw new exception ('Need to specify the columns for the error as an array of strings');
        }
        
        // determine the english names for the columns
        $english_names = array();
        foreach ($column_names as $column_name) {
            $column = $this->get ($column_name);
            if ($column == null) {
                throw new exception ("Column '{$column_name}' does not exist in this table");
            }
            
            // if this column links to the parent table
            // and the user cannot edit the value (add or parent editing disabled)
            // dont show the error for this column
            if ($parent != null) {
                $link = $column->getLink ();
                if ($link != null and $link->isParent () and $link->getToColumn ()->getTable () === $parent) {
                    if ($edit_mode == false or $this->getDisableParentEdit ()) {
                        continue;
                    }
                }
            }
            
            $english_names[] = '<em>' . htmlspecialchars ($column->getEngName ()) . '</em>';
        }
        
        // build the actual message
        $table_single = strtolower ($this->getNameSingle ());
        $table_single = htmlspecialchars ($table_single);
        if (count ($english_names) == 1) {
            $error = "Each {$table_single} must have a unique value for the field {$english_names[0]}.";
            
        } else {
            $error = "Each {$table_single} must have a unique combination of values for the fields ";
            $error .= implode_and (', ', $english_names);
            
        }
        
        return $error;
    }
    
    
    /**
     * gets the maximum number of characters allowed when displaying a tree
     * node - if specified, larger node names will be truncated.
     * 
     * @return mixed an integer if truncation is to be used, null otherwise
     */
    function getTreeNodeChars () {
        return $this->tree_node_chars;
    }
    
    /**
     * sets the maximum number of characters allowed when displaying a tree node
     * 
     * @param int $chars the maximum number of characters allowed. Zero or
     *        negative will disable truncation.
     */
    function setTreeNodeChars ($chars) {
        
        settype ($chars, 'int');
        if ($chars <= 0) $chars = null;
        
        $this->tree_node_chars = $chars;
    }
    
    /**
     * Determines who has rights to view/edit the content in this table
     * 
     * @author benno, 2008-07-02
     * 
     * @return int one of the following: TABLE_ACCESS_ADMIN;
     *         TABLE_ACCESS_SETUP_LIMITED; or TABLE_ACCESS_SETUP_FULL.
     */
    function getAccessLevel () {
        return $this->access_level;
    }
    
    /**
     * Sets who has rights to view/edit the content in this table
     * 
     * @author benno, 2008-07-02
     * 
     * @param int $level one of the following: TABLE_ACCESS_ADMIN;
     *        TABLE_ACCESS_SETUP_LIMITED; or TABLE_ACCESS_SETUP_FULL.
     */
    function setAccessLevel ($level) {
        
        settype ($level, 'int');
        
        // If level provided makes no sense, use TABLE_ACCESS_SETUP_FULL
        switch ($level) {
            case TABLE_ACCESS_ADMIN:
            case TABLE_ACCESS_SETUP_LIMITED:
                break;
            
            default:
                $level = TABLE_ACCESS_SETUP_FULL;
        }
        $this->access_level = $level;
    }
    
    /**
     * Checks to see that the user is authorised to access this table
     * 
     * @author benno, 2008-07-03
     * 
     * @return bool true if the user is authorised, false otherwise
     */
    function checkAuth () {
        switch ($this->access_level) {
            case TABLE_ACCESS_SETUP_FULL:
                if ($_SESSION['setup']['level'] == SETUP_ACCESS_FULL) return true;
                break;
            
            case TABLE_ACCESS_SETUP_LIMITED:
                if ($_SESSION['setup']['level'] >= SETUP_ACCESS_LIMITED) return true;
                break;
            
            default:
                return test_admin_login (false);
        }
        return false;
    }
    
    
    /**
     * Sets whether the contents of this table are static.
     * 
     * A 'static' table is one in which the data is basically unchanging and is
     * of prime importance. For example, if you have a Users table, and each
     * user has a status that is stored in StatusID, and StatusID points to a
     * small table (UserStatuses) that contains the statuses that a user can
     * have, then UserStatuses is a static table - its values need to remain
     * the same on development and live servers.
     * 
     * @author benno, 2008-07-08
     * 
     * @param bool $val true for static, false otherwise
     */
    function setStatic ($val) {
        $this->static_table = (bool) $val;
    }
    
    /**
     * Determines if the contents of this table are static.
     * 
     * @see Table::setStatic()
     * @author benno, 2008-07-08
     * 
     * @return bool true for static, false otherwise
     */
    function isStatic () {
        return $this->static_table;
    }
    
    
    /**
     * Generates the contents of the add and edit views individually from the
     * combined add_edit view used in setup
     * @author benno, 2011-08-17
     */
    function morphAddEditView () {
        $add_edit = $this->getAddEditView ();
        $add_view = array ();
        $edit_view = array ();
        foreach ($add_edit as $aev_item) {
            if ($aev_item['add']) {
                $view_item = clone $aev_item['item'];
                if ($view_item instanceof ColumnViewItem) {     
                    $view_item->setEditable (true);
                }
                $add_view[] = $view_item;
            }
            if ($aev_item['edit_view']) {
                $view_item = clone $aev_item['item'];
                if ($view_item instanceof ColumnViewItem) {
                    $view_item->setEditable ($aev_item['edit_change']);
                }
                $edit_view[] = $view_item;
            }
        }
        $this->setView('add', $add_view);
        $this->setView('edit', $edit_view);
    }
    
    
    /**
     * Get a string that is a visualisation of the table in completeness.
     * 
     * Used in debugging.
     *
     * @return string Visualisation of this Table.
     * @deprecated Remove this
     */
    function vis () {
        $str = "<h3>". $this->name. " (". $this->english_name. ")</h3>\n";
        $str .= $this->visColumns ();
        $str .= $this->visIndices ();
        $str .= $this->visSearchOptions ();
        return $str;
    }
    
    /**
     * Get a string that is a visualisation of the columns of this table.
     * 
     * Used in debugging.
     * 
     * @return string Visualisation of the Column objects contained in this
     *         Table's meta-data store.
     * @deprecated Remove this
     */
    function visColumns () {
        $str = "<table>\n<tr><td><b>Columns</b></td><td>";
        if (count($this->columns) > 0) {
            $str .= "    <table>\n<tr><th>Type</th><th>Name</th><th>Link</th><th>SQL</th></tr>\n";
            foreach ($this->columns as $col) {
                $str .= "    <tr>\n";
                $str .= "        <td>". $col->getType (). "</td>\n";
                $str .= "        <td>". $col->getName (). " (". $col->getEngName (). ")</td>\n";
                $link = $col->getLink ();
                $prev_links = array ();
                if ($link == null or (is_array ($link) and count($link) == 0)) {
                    $str .= "        <td>&nbsp;</td>\n";
                } else {
                    // echo "LINK: "; print_r ($link); echo "<br>\n";
                    $loc = $link->getToColumn ()->getTable ()->getName (). ".". $link->getToColumn ()->getName ();
                    $prev_links[] = $loc;
                    $str .= "        <td>-&gt; {$loc}";
                    // traverse multi-level links
                    /*
                    while ($link = $link->getLink ()) {
                        if ($link == null) break;
                        $loc = $link->getTable ()->getName (). ".". $link->getName ();
                        if (in_array ($loc, $prev_links)) break;
                        $str .= " -&gt; {$loc}";
                    }
                    */
                    $str .= "</td>\n";
                }
                $str .= "        <td>". $col->getSqlDefn (). "</td>\n";
                $str .= "    <tr>\n";
            }
            $str.= "    </table>\n";
        } else {
            $str .= "None available";
        }
        $str .= "</td></tr><table>\n";
        return $str;
    }
    
    /**
     * Get a string that is a visualisation of indices of this table.
     * 
     * Used in debugging.
     * 
     * @return string Visualisation of the indices defined for this Table.
     * @deprecated Remove this
     */
    function visIndices () {
        $str = "<table>\n<tr><td><b>Indices</b></td><td>";
        if (count($this->indices) > 0) {
            foreach ($this->indices as $id => $index) {
                // echo "Index: <pre>"; print_r ($index); echo "</pre>\n";
                $str .= "{$id}: ";
                
                if (is_array($index)) {
                    $i = 0;
                    foreach ($index as $index_col) {
                        if ($i++ > 0) $str .= ', ';
                        $str .= $index_col->getName ();
                    }
                } else {
                    $str .= $index->getName ();
                }
                $str .= "<br>\n";
            }
        } else {
            $str .= "<b>ERROR: none defined</b>";
        }
        $str .= "</td></tr><table>\n";
        return $str;
    }
    
    /**
     * Get a string that is a visualisation of the search options for this
     * table.
     * 
     * Used in debugging. NOT IMPLEMENTED
     * 
     * @return string Visualisation of the search options defined for this
     *         Table.
     * @deprecated Remove this
     */
    function visSearchOptions () {
        $str = '';
        return $str;
    }
    
    /**
     * Draws a list item for use in an admin menu (with 'on' class if it's the
     * active table), containing an A element that links to the table's main
     * view
     * @param bool $active true if this is the active (i.e. currently being
     *        viewed) table
     * @author benno, 2009-09-04
     */
    function menuDraw ($active) {
        list ($urls, $seps) = $this->getPageUrls ();
        $eng_name = $this->getEngName ();
        $eng_name = htmlspecialchars ($eng_name);
        
        $url = $urls['main'];
        if ($url[0] != '/' and strpos ($url, '://') === false) {
            $url = ROOT_PATH_WEB. ADMIN_DIR. $url;
        }
        
        echo '        <li class="table';
        if ($active) echo ' on';
        echo "\"> <a href=\"{$url}{$seps['main']}t=",
            urlencode ($this->getName ()), "\">{$eng_name}</a></li>\n";
    }
    
    function __toString () {
        return 'Table:'. $this->name;
    }
    
    /**
     * Identify this table in a specific context
     *
     * @param int $context The context to identify this table in:
     *        'select' (FROM x or JOIN y)
     *        'normal' (everywhere else)
     */
    function identify ($context) {
        return '`'. $this->name. '`';
    }
}

?>
