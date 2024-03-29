<?php
/**
 * ORM CONFIGURATION MODULE
 * @Darwin
 */

namespace Orm;

# DEBUG HANDLE
$debug = filter_var(@$GET['debug'], FILTER_VALIDATE_BOOLEAN);
if( $debug ){
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

# CONFIGURATION SETTINGS
# ----------------------
abstract class OrmConfig {
    # MYSQLI DB CONNECTION SCRIPT
    const CONNECTION_SCRIPT_PATH = __DIR__ . '/../../includes/connection.php';     # __DIR__ . '/relative/path/to/connection.php
    
    # MYSQLI VARIABLE NAME USER FOR DB CONNECTION
    const CONNECTION_VAR_NAME = 'mysqli';                                       # 'mysqli_var_name'
 }