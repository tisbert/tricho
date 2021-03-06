<?php
/**
 * This file is part of Tricho and is copyright (C) Transmogrifier E-Solutions.
 * It is released under the GNU General Public License, version 3 or later.
 * See COPYRIGHT.txt and LICENCE.txt in the tricho directory for more details.
 */

function tricho_autoload ($class_name) {
    static $extensions = null;
    
    $tricho_ns = substr($class_name, 0, 7) == 'Tricho\\';
    
    if (ends_with($class_name, 'Exception') and !$tricho_ns) {
        $file = __DIR__ . '/custom_exceptions.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    if (!$tricho_ns) return;
    
    $file_name = str_replace('\\', '/', substr($class_name, 7)) . '.php';
    $file = __DIR__ . '/' . $file_name;
    if (file_exists($file)) {
        require_once $file;
        if (ends_with($class_name, 'Column')) {
            Tricho\Runtime::add_column_class($class_name);
        }
        return;
    } else {
        $ext_dir = __DIR__ . "/ext";
        
        if ($extensions == null) {
            $extensions = [];
            $exts = (array) @scandir($ext_dir);
            foreach ($exts as $ext) {
                if ($ext[0] == '.') continue;
                if (is_dir("{$ext_dir}/{$ext}")) $extensions[] = $ext;
            }
        }
        
        foreach ($extensions as $ext) {
            $ext_path = "{$ext_dir}/{$ext}/{$file_name}";
            if (file_exists($ext_path)) {
                require_once $ext_path;
                if (ends_with($class_name, 'Column')) {
                    Tricho\Runtime::add_column_class($class_name);
                }
                return;
            }
        }
    }
}


function class_name_to_file_name($name) {
    settype($name, 'string');
    $decapitalised = preg_replace_callback(
        '/[A-Z]/',
        function($matches) {
            return '_' . strtolower($matches[0]);
        },
        substr($name, 1)
    );
    return strtolower($name[0]) . $decapitalised . '.php';
}

function file_name_to_class_name($name) {
    settype($name, 'string');
    $dot_pos = strpos($name, '.');
    if ($dot_pos !== false) {
        $name = substr($name, 0, $dot_pos);
    }
    $capitalised = preg_replace_callback(
        '/_[a-z]/',
        function($matches) {
            return strtoupper($matches[0][1]);
        },
        substr($name, 1)
    );
    return strtoupper($name[0]) . $capitalised;
}

spl_autoload_register('tricho_autoload');
?>
