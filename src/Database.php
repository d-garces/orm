<?php
/**
 * DATABASE MODELLING INTERFACE
 * @ Darwin 
 */

namespace Orm;

require_once __DIR__ . '/../config.php';
require_once OrmConfig::CONNECTION_SCRIPT_PATH;


// CONNECTION ACCESS # CONFIG THE CONNECTION VARIABLE
if( !isset($connection) ) $connection = ${OrmConfig::CONNECTION_VAR_NAME};

abstract class Database {

    const DATE_FORMAT = 'Y-m-d';
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    public static function sanitizeValue($data){
        global $connection;
        return $connection->real_escape_string($data);
    }
    
    public static function sanitizeDataBlock( array $array_data ){
        global $connection;
        $sanitized = [];
    
        // MUST TO REMOVE ALWAYS THE ID TO AVOID OVERRIDING
        foreach ($array_data as $key => $value) {
            if( $key==='id') continue; 
    
            switch(true){
                case $value===NULL : 
                    $value = 'NULL';
                    break;
                
                default:
                    $value = Database::sanitizeValue($value);  // mysql_real_escape_string
                    $value = "'$value'";
            }
            $sanitized[] = "`$key` = $value";
        }
        return $sanitized;
    }
    

    public static function dateFormat(\DateTime $dt){
        return $dt->format('Y-m-d');
    }
    
    public static function dateTimeFormat(\DateTime $dt){
        return $dt->format('Y-m-d H:i:s');
    }

    protected function getRowInfo($from_table, $row_id){
        $response = self::getLineValues($from_table, '*', "id='$row_id'");

        if ( empty($response) ) {
            throw new \Exception( '['.__FUNCTION__."] Errors: Item $row_id, not found in $from_table");
        }

        return $response;
    }

    protected function getRowsInfoIds($from_table, $csv_ids){
        $response = Database::execute( "SELECT * FROM `$from_table` WHERE id IN( $csv_ids )", true);

        if ( !$response->success ) {
            throw new \Exception( '['.__FUNCTION__."] Query Errors: $response->result"  );
        }
        
        if ( empty($response->result) ) {
            throw new \Exception( '['.__FUNCTION__."] Errors: No Items found in $from_table for $csv_ids"  );
        }

        return $response->result;
    }
    
    /**
     * GET STANDARDIZED QUERY RESPONSE AS ARRAY
     * @$query : Raw SQL query
     */
    public static function getQueryResult($query){
        $response = Database::execute( $query, true );

        if ( !$response->success ) {
            throw new \Exception( $response->result );
        }

        return $response->result;
    }

# BASIC FUNCTIONS

public static function getSingleValue( $table, $value, $condition='0' ){		// NON ESEGUE SENZA CONDIZIONI PER DEFAULT
    $field = $value;
    if( strpos( $value,' AS ')!==false ){
        $aux = explode( ' AS ', $value );
        $field = $aux[1]; 													// ALIAS
    }	
    $query = "SELECT $value FROM $table WHERE $condition";
    $result = Database::execute( $query, true );
    
    if( !$result->success ) return false;

    return ( isset( $result->result[0][$field] ) ) ? $result->result[0][$field] : null;
}


public static function setSingleValue( $table, $assignment, $condition ){
    $query = "UPDATE $table SET $assignment WHERE $condition";
    $result = Database::execute( $query );											// ESECUZIONE QUERY
    
    return $result->success;
}


public static function getLineValues( $table, $values, $condition ){
    if(is_array( $values )) $assignments = implode(',', $assignments);
    $query = "SELECT $values FROM $table WHERE $condition";
    $result = Database::execute( $query );											// ESECUZIONE QUERY
    
    return ( $result->success ) ?  $result->result : false;
}
	
public static function setLineValues( $table, $assignments, $condition ){
    if(is_array( $assignments )) $assignments = implode(',', $assignments);
    $query = "UPDATE $table SET $assignments WHERE $condition";
    $result = Database::execute( $query );											// ESECUZIONE QUERY
    
    return $result->success;
}
    
public static function execute( $query, $std_output=false ){
    global $debug;
    global $connection;																			// MYSQLI: scomentare

    $res = new \stdClass();
    $res->query  = $query;																		// SET QUERY
    $res->success  = false;																		// NOT EXECUTED
    $res->message  = 'Not exectued';															// DEFAULT MSG
    $res->affected = $res->result_cols = $res->result_rows = 0;									// DEFAULT BOUNDS
    $res->result   = array();																	// EMPTY RESPONSE

    $insert = ( strpos( $query, 'INSERT ')===0 ) ? true : false ;							    // INS QUERY CHECK
    $select = ( strpos( $query, 'SELECT ')===0 ) ? true : false ;							    // SEL QUERY CHECK
    
    try{
        // ESECUZIONE DELLA QUERY
        $result = mysqli_query( $connection, $query); 											// MYSQLI: $connection->query( $query );

        // QUERY IN ERRORE
        if ( $result===false ) {
            throw new \Exception( mysqli_error( $connection ) );								// MYSQLI: $connection->error
        } else {
            
            $res->success	= true;
            $res->affected	= mysqli_affected_rows( $connection );								// MYSQLI: $connection->affected_rows
            $res->message	= "Query executed";

            // RESULT SET / QUERY TYPE
            $flag_result = is_bool( $result );
            if( $flag_result ){
                $res->result[] = ( $insert ) ?  mysqli_insert_id( $connection ) : $result;      // SALVATAGGIO RISULTATO DELLA QUERY IN BASE AL TIPO (MYSQLI: $connection->insert_id )
            } else {
                $res->result_rows = ( $select ) ? mysqli_num_rows($result)		: 1 ;			// MYSQLI: $result->num_rows;
                $res->result_cols = ( $select ) ? mysqli_num_fields($result)	: 1;			// MYSQLI: $result->field_count;
                while ( $row = mysqli_fetch_assoc($result)) $res->result[] = $row;				// MYSQLI: $result->fetch_assoc()
                // NOT AVAILABLE FOR MYSQLI : $res->result = $res->fetch_all( MYSQLI_ASSOC );   # TILL PHP 8
            }

            if( $std_output==false And count($res->result)===1 ){                               // <=1 HANDLE IGNORE CLAUSE
                $res->result = $res->result[0];													// SBOBINATURA PER SEMPLIFICARE IL CODICE DI CALLBACK
            }
        }
    } catch( \Exception $e ){
        $res->success  = false;
        $res->message  = $e->getMessage();
        if( $debug ){
            var_dump( $res );
            die();
        }
    }
    return $res;
}


};



?>