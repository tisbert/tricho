<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

require_once '../tricho.php';
test_admin_login ();
require_once ROOT_PATH_FILE. 'tricho/data_objects.php';

$db = Database::parseXML ('tables.xml');
$table = $db->getTable ($_POST['_t']);
force_redirect_to_alt_page_if_exists ($table, 'main_search_action');

list ($urls, $seps) = $table->getPageUrls ();
if ($_POST['_search_type'] == 'inline') {
    define ('SEARCH_KEY', 'inline_search');
    $urls['main'] = "inline_search.php?f={$_POST['_f']}";
    $seps['main'] = '&';
} else {
    define ('SEARCH_KEY', 'search_params');
}

define ('AM', 1);
define ('PM', 2);

$table_name = $table->getName ();

// leave options clear if that's what the user clicked
if ($_POST['_action'] == 'clear') {
    unset ($_SESSION[ADMIN_KEY][SEARCH_KEY][$table->getName ()]);

    // redirect
    $url = $urls['main'] . $seps['main'] . 't=' . $table_name;
    if ($_POST['_p'] != '') $url .= '&p=' . $_POST['_p'];
    redirect ($url);
}

// clear previous search data, so that search can be updated according to what has been posted
$_SESSION[ADMIN_KEY][SEARCH_KEY][$table->getName ()] = array ();

$filters = &$_SESSION[ADMIN_KEY][SEARCH_KEY][$table->getName ()];
if (@is_array ($_POST['field']) and @is_array ($_POST['condition']) and @is_array ($_POST['val'])) {
    foreach ($_POST['field'] as $id => $name) {
        
        $column = $table->get ($name);
        
        if ($column === null) continue;
        
        
        $link = $column->getLink ();
        if ($link == null) {
            /* REGULAR COLUMNS */
            
            // get someinfo
            $cond = $_POST['condition'][$id];
            $col = $table->get ($name);
            
            // IS [ NULL | NOT NULL ] is a pain
            if ($cond == LOGIC_CONDITION_IS
                        or $cond == LOGIC_CONDITION_STARTS_WITH
                        or $cond == LOGIC_CONDITION_ENDS_WITH) {
                $value = $_POST['val'][$id][0];
                
            } else {
                // value switching based on type
                switch ($col->getSqlType ()) {
                    case SQL_TYPE_DATE:
                        $value = work_out_date ($id, 0);
                        if ($value === false) continue 2;
                        $value = $value->getVal ();
                        break;
                        
                    case SQL_TYPE_DATETIME:
                        $value = work_out_date ($id, 0);
                        if ($value === false) continue 2;
                        $value2 = work_out_time ($id, 0);
                        if ($value2 === false) continue 2;
                        $value = $value->concat ($value2);
                        $value = $value->getVal ();
                        break;
                    case SQL_TYPE_TIME:
                        $value = work_out_time ($id, 0);
                        if ($value === false) continue 2;
                        $value = $value->getVal ();
                        break;
                    default:
                        $value = $_POST['val'][$id][0];
                }
                
                // if value is null, make into a IS NULL;
                if ($value === null) {
                    switch ($cond) {
                        case LOGIC_CONDITION_NOT_LIKE:
                        case LOGIC_CONDITION_NOT_EQ:
                            $value = 'not null'; break;
                        default:
                            $value = 'null'; break;
                    }
                    $cond = LOGIC_CONDITION_IS;
                }
                
                // clever stuff for between
                if ($cond == LOGIC_CONDITION_BETWEEN) {
                    switch ($col->getSqlType ()) {
                        case SQL_TYPE_DATE:
                            $value_right = work_out_date ($id, 1);
                            if ($value_right === false) continue 2;
                            $value_right = $value_right->getVal ();
                            break;
                            
                        case SQL_TYPE_DATETIME:
                            $value_right = work_out_date ($id, 1);
                            if ($value_right === false) continue 2;
                            $value_right2 = work_out_time ($id, 1);
                            if ($value_right2 === false) continue 2;
                            $value_right = $value_right->concat ($value_right2);
                            $value_right = $value_right->getVal ();
                            break;
                        case SQL_TYPE_TIME:
                            $value_right = work_out_time ($id, 1);
                            if ($value_right === false) continue 2;
                            $value_right = $value_right->getVal ();
                            break;
                        default:
                            $value_right = $_POST['val'][$id][1];
                    }
                    $value = array ($value, $value_right);
                }
            }
            
            // save filter
            $filters[] = new MainFilter ($name, $cond, $value);
            
            
            
            
            
        } else {
            /* LINKED COLUMNS */
            $value = $_POST['val'][$id][0];
            
            // this is a chain builder
            $joinlist = array ();
            $from = $table->get ($name);
            $to = $link->getToColumn ();
            //echo '<pre>';
            while ($to != null) {
                // add join to list
                $join = new MainJoin ('','','','');
                $join->setFromColumn ($from->getName ());
                $join->setFromTable ($from->getTable ()->getName ());
                $join->setToColumn ($to->getName ());
                $join->setToTable ($to->getTable ()->getName ());
                $joinlist[] = $join;
                
                //todo: getParent? argh! die!
                $to = null; //hack that make it think it has no parent
                
                /*
                // find the next item in the chain
                $table = $to->getTable();
                $tbl = $table->getParent();
                $to = null;
                if ($tbl != null) {
                    $columns = $table->getColumns();
                    foreach ($columns as $column) {
                        $link = $column->getLink();
                        if ($link != null and $link->getToColumn()->getTable() === $tbl) {
                            $from = $column;
                            $to = $link->getToColumn();
                            break;
                        }
                    }
                }*/
                
            }
            //echo '</pre>';
            
            // create and add the filter
            $filter = new MainJoinFilter ($value, $joinlist);
            $filter->setType ($_POST['condition'][$id]);
            $filters[] = $filter;
            
        }
    }
}
$filters['_match_type'] = $_POST['_match_type'];


// redirect
$url = $urls['main'] . $seps['main'] . 't=' . $table_name;
if ($_POST['_p'] != '') $url .= '&p=' . $_POST['_p'];
redirect ($url);





/*
 *************************************************************************************************************
 *************************************************************************************************************
 *************************************************************************************************************
 *************************************************************************************************************
 */



/**
 * The return value from the date and time computation functions
 * Includes a value and a boolean for if it should be quoted
 */
class ReturnVal {
    private $value;
    private $quote;
    
    function __construct ($value, $quote) {
        $this->value = $value;
        $this->quote = $quote;
    }
    
    function concat ($obj) {
        if ($this->quote or $obj->quote) {
            $quote = true;
        }
        $value = $this->value. ' '. $obj->value;
        return new ReturnVal ($value, $quote);
    }
    
    function getVal () {
        if ($this->quote) {
            return "'{$this->value}'";
        } else {
            return $this->value;
        }
    }

    public function __toString () {
        return __CLASS__;
    }
}





/**
 * Determines a date value to go into an SQL query for a specific DATE|DATETIME field.
 * 
 * @param int $id the array auto-key if the POST array that contains the field
 *     (i.e counting the search fields, starting from 0)
 * @param int $number 0 or 1, 0 being the first field
 *     (for most fields this will be 0, but if you have, say, BETWEEN you have numbers 0 and 1)
 */
function work_out_date ($id, $number) {
    global $cond;
    
    // get date parts
    $year = (int) $_POST['val'][$id][$number]['y'];
    $month = (int) $_POST['val'][$id][$number]['m'];
    $day = (int) $_POST['val'][$id][$number]['d'];
    
    // use condition type to determine what to do on blanks
    switch ($cond) {
        case LOGIC_CONDITION_EQ:
        case LOGIC_CONDITION_NOT_EQ:
            if (($year == 0) or ($month == 0) or ($day == 0)) {
                return false;
            }
            break;
            
        case LOGIC_CONDITION_LIKE:
        case LOGIC_CONDITION_NOT_LIKE:
            if ($year == 0) $year = '____';
            if ($month == 0) $month = '__';
            if ($day == 0) $day = '__';
            $quote = true;
            break;
            
        case LOGIC_CONDITION_BETWEEN:
            if ($year == 0) return null;
            if ($month == 0) {
                if ($number == 0) {
                    $month = 1;
                } else {
                    $month = 12;
                }
                $day = 0;
            }
            if ($day == 0) {
                if ($number == 0) {
                    $day = 1;
                } else {
                    $day = 31;
                }
            }
            break;
            
        case LOGIC_CONDITION_LT:
        case LOGIC_CONDITION_GT_OR_EQ:
            if ($year == 0) return null;
            if ($month == 0) {
                $month = 1;
                $day = 1;
                break;
            }
            if ($day == 0) $day = 1;
            break;
            
        case LOGIC_CONDITION_GT:
        case LOGIC_CONDITION_LT_OR_EQ:
            if ($year == 0) return null;
            if ($month == 0) {
                $month = 12;
                $day = 31;
                break;
            }
            if ($day == 0) $day = 31;
            break;
            
    }
    
    // zero filling
    if (is_int ($month) and ($month < 10)) $month = '0'. $month;
    if (is_int ($day) and ($day < 10)) $day = '0'. $day;
    
    // determine final value
    $value = $year. '-'. $month. '-'. $day;
    
    // return it
    return new ReturnVal ($value, $quote);
}


/**
 * Determines a time value to go into an SQL query for a specific specific DATETIME|TIME field.
 */
function work_out_time ($id, $number) {
    global $cond;
    
    $hour = (int) $_POST['val'][$id][$number]['hr'];
    $min = (int) $_POST['val'][$id][$number]['mn'];
    if ($_POST['val'][$id][$number]['mn'] == '') $min = null;
    $ampm = (int) $_POST['val'][$id][$number]['AP'];
    $quote = false;
    
    switch ($cond) {
        case LOGIC_CONDITION_LIKE:
        case LOGIC_CONDITION_NOT_LIKE:
            if ($hour == 0) {
                $hour = '__';
            } else {
                $hour = hour_24 ($hour, $ampm);
            }
            if ($min === null) $min = '__';
            $sec = '__';
            $quote = true;
            break;
        
        case LOGIC_CONDITION_BETWEEN:
            if ($hour == 0) {
                if ($number == 0) {
                    $hour = '00';
                } else {
                    $hour = '23';
                }
            } else {
                $hour = hour_24 ($hour, $ampm);
            }
            if ($number == 0) {
                $sec = '00';
            } else {
                $sec = '59';
            }
            if ($min === null) {
                if ($number == 0) {
                    $min = '00';
                } else {
                    $min = '59';
                }
            }
            break;
            
        case LOGIC_CONDITION_LT:
        case LOGIC_CONDITION_GT_OR_EQ:
            if ($hour == 0) {
                $hour = '00';
            } else {
                $hour = hour_24 ($hour, $ampm);
            }
            $sec = '00';
            if ($min === null) $min = '00';
            break;
            
        case LOGIC_CONDITION_GT:
        case LOGIC_CONDITION_LT_OR_EQ:
            if ($hour == 0) {
                $hour = '23';
            } else {
                $hour = hour_24 ($hour, $ampm);
            }
            $sec = '59';
            if ($min === null) $min = '59';
            break;
    }
    
    if ($min < 0) $min = '00';
    if ($min > 59) $min = 59;
    if (is_int($hour) and ($hour < 10)) $hour = '0'. $hour;
    if (is_int($min) and ($min < 10)) $min = '0'. $min;
    
    $value = $hour. ':'. $min. ':'. $sec;
    
    // return it
    return new ReturnVal ($value, $quote);
}

/**
 * Returns the hour represented as 24 hour time
 */
function hour_24 ($hour, $ampm) {
    if ($ampm == AM) {
        if ($hour == 12) return 0;
        return $hour;
        
    } else {
        if ($hour == 12) return 12;
        return $hour + 12;
    }
}
?>
