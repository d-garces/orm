<?php
/**
 * SIMPLE MODEL CLASS FOR EASY DB MAPPING
 * @ Darwin
 */
namespace Orm;

use Orm\Database;

require_once __DIR__ . '/Database.php';


abstract class Model{
    const SAVE_OR_IGNORE = 1;   // IGNORE IF EXISTS ALREADY
    const SAVE_OR_UPDATE = 2;   // UPDATE IF EXISTS ALREADY


    ###########################################
    ### PUBLIC MEMBER FUNCTIONS ###############

    /**
     * INSERT A NEW ITEM INTO THE DB
     * $mode : SAVE OR FAIL (Default) SAVE_OR_IGNORE = 1, SAVE_OR_UPDATE = 2
     */
    public function save( int $mode=null ){

        // ENSURE KEEP THE ORIGINAL ID
        $current_id = $this->id();

        # IF NOT SPECIFIED THE MODE
        if( empty($mode) ){
            # IF HAS AN ID => IS AN EXISTING RECORD IN THE DB (ID SHOUD NOT BE MODIFIABLE)
            if( ! empty($current_id) ) $mode = self::SAVE_OR_UPDATE;
        }

        $insData = $this->attrs();
        $escaped_values = Database::sanitizeDataBlock($insData);

        $escaped_values[] = "`id` = $current_id";

        $values  = implode(', ', $escaped_values);
        $table = static::TABLE;
        
        switch($mode){
            case self::SAVE_OR_IGNORE: 
                $insert = "INSERT IGNORE INTO `{$table}` SET {$values}";
                break;
            case self::SAVE_OR_UPDATE: 
                $insert = "INSERT INTO `{$table}` SET {$values} ON DUPLICATE KEY UPDATE {$values}";
                break;
            default: # SAVE OR FAIL
                $insert = "INSERT INTO `{$table}` SET {$values}";
        }
        $done = Database::execute($insert);
        $this->id = $done->result ?: $current_id;   # KEEP SAME ID IF NOT INSERTION OR ERROR
        return $this->id;                           # ID OF INSERTED IF DONE
    }


    /**
     * UPDATE AN EXISTING SINGLE ITEM INTO THE DB
     * $array : CURRENT FIELDS (DEFAULT) FIELDS TO UPDATE (key => value)
     */
    public function update($array=[]){
        $id = (int) $this->id;
        if( empty($id) ) return false;
        if(empty($array)) $array = $this->attrs();

        $updates = Database::sanitizeDataBlock($array);

        $values = implode(', ', $updates);
        $update = sprintf("UPDATE `%s` SET %s WHERE id='%s'", static::TABLE, $values, $id);
        $done = Database::execute($update);
        return $done->success;
    }

    /**
     * REMOVES THE CURRENT RECORD FROM THE DB
     */
    public function delete(){
        $id = (int) $this->id;
        if( empty($id) ) return false;
        $delete = sprintf("DELETE FROM `%s` WHERE id='%s'", static::TABLE, $id); 
        $done = Database::execute($delete);
        return $done->success;
    }

    /**
     * RETURN THE MODEL OBJECT ID
     * IF NOT EMPTY => IS AN EXISTING RECORD IN THE DB
     */
    public function id(){
        return intval( $this->id );
    }

    /**
     * GET INSTANCED key => value ITEM ATTRIBUTES
     */
    public function getAttributes(){
        return $this->attrs();
    }

    /**
     * GET SINGLE ATTRIBUTE
     * $key : REQUESTED ATTRIBUTE
     */
    public function getAttribute($key){
        return property_exists($this, $key) ? $this->$key : null;
    }

    /**
     * SET TO $value THE SPECIFIED ATTRIBUTE ($key)
     */
    public function setAttribute($key, $value){
        # id CAN NOT BE SET TO PREVENT CONFLICTS WITH THE OTHER RECORDS
        if( property_exists($this, $key) && $key!=='id' ){
            $this->$key = $value;
            return true;
        }
        return false;
    }

    /**
     * GET INSTANCED ITEM ATTRIBUTES : key => value (PROPS + VALUES)
     */
    protected function attrs( $type=\ReflectionProperty::IS_PUBLIC ){

        $class = get_class($this);

        $reflect = new \ReflectionClass($class);
        $props = $reflect->getProperties($type);

        $attributes = [];

        foreach ($props as $property) {
            if( $property->class == $class ){
                $prop = $property->getName();
                $attributes[ $prop ] = $this->$prop;
            } 
        }
        # ADD ID INDEX ATTRIBUTE
        if( property_exists($class, 'id') ) $attributes['id'] = $this->id();

        return $attributes;
    }


    ###########################################
    ### PUBLIC STATIC FUNCTIONS ###############

    /**
     * UPDATE ALL THE ITEMS THAT MATCHES THE $where CONDITION
     * $where : CONDITION TO UPDATE
     * $array : FIELDS TO UPDATE (key => value) (REQUIRED)
     */
    public static function updateAll($where, $array){
        if( empty($array) || empty($where)  ) return false;

        $updates = Database::sanitizeDataBlock($array);

        $values = implode(', ', $updates);
        $update = sprintf("UPDATE `%s` SET %s WHERE %s", static::TABLE, $values, $where);
        $done = Database::execute($update);
        return $done->success;
    }

    /**
     * CREATE AN ITEM WITH THE GIVEN DATA
     * $data : FIELDS TO FILL (key => value) (REQUIRED)
     */
    public static function create($data, $mode=self::SAVE_OR_IGNORE){
        $new = self::fill($data);
        if( empty($new) ) return null;
        $new_id = $new->save($mode);
        $new->id = $new_id;
        return $new_id ? $new : null;
    }

    /**
     * FIND AN EXISTING SINGLE ITEM IN THE DB
     * $value : VALUE TO FIND
     * $key : FIELD WHERE TO FIND
     */
    public static function find($value, $key='id'){ # KEY MUST BE INDEX SO THE RESULT WILL BE UNIQUE
        $value = Database::sanitizeValue($value);
        $where = "`$key` = '$value' LIMIT 1";
        $data = Database::getLineValues( static::TABLE, '*', $where);
        return self::fill($data);
    }

    /** FIND ONE OR MORE RECORDS
     * GET ALL THE RECORDS AS MODEL OBJECTS THAT MATCHES THE CONDITION
     * $where : CONDITION TO APPLY
     * $fields : FIELD TO RETRIVE
     * $std_output : FORSE OUTPUT AS ARRAY OF OBJECTS (FOR ITERATIONS)
     */
    public static function get($where='1', $fields='*', $std_output=false){
        if( is_array($fields) ) $fields = implode(', ', $fields);
        $get_all = "SELECT $fields FROM `".static::TABLE."` WHERE $where";
        $done = Database::execute($get_all, $std_output);

        if(! $done->success ) return false;

        if( $std_output ) return self::fillAll($done->result);

        return $done->result_rows===1 ? self::fill($done->result) : self::fillAll($done->result);
    }

    /**
     * GUIVE THE FIELDS DATA MATCHING THE CONDITION
     */
    public static function getData($predicate='1', $subject='*', $join=''){
        $get = sprintf("SELECT %s FROM `%s` %s WHERE %s", $subject, static::TABLE, $join, $predicate);
        $all = Database::getQueryResult($get);
        return $all;
    }
    
    /**
     * RETURN A RECORD COUNT OF THE GIVEN CONDITION
     * USEFUL IF NEEDED JUST A COUNT AND NOT TO HANDLE THE SET
     */
    public static function count($where='1=1'){
        $count = Database::getSingleValue( static::TABLE, 'COUNT(*)', $where);
        return intval($count);
    }


    // /**
    //  * EXECUTE A RAW QUERY (USE Database class instead)
    //  */
    // protected static function getRawData($query){
    //     $all = Database::getQueryResult($query);
    //     return $all;
    // }

    /**
     * GET THE LAST ITEM MATCHING THE CONDITION OREDERED BY THE KEY
     * $where : CONDITION TO APPLY
     * $key : FIELD TO ORDER
     */
    public static function lastBy($where='1', $key='id'){
        $where = "$where ORDER BY `$key` DESC LIMIT 1";
        $data = Database::getLineValues( static::TABLE, '*', $where);
        return self::fill($data);
    }

    /**
     * GET THE FIRST ITEM MATCHING THE CONDITION OREDERED BY THE KEY
     * $where : CONDITION TO APPLY
     * $key : FIELD TO ORDER
     */
    public static function firstBy($where='1', $key='id'){
        $where = "$where ORDER BY `$key` ASC LIMIT 1";
        $data = Database::getLineValues( static::TABLE, '*', $where);
        return self::fill($data);
    }

    /**
     * GET AN ARRAY FROM COMBINING THE RECORDS MATCHING THE CONDITION WITH THE GIVEN FIELDS KEY => LABEL
     * USEFUL FOR SELECTS
     */
    public static function getOptionsData($key, $label, $where='1'){
        $data = self::getData($where, "$key,$label");
        if(empty($data)) return [];
        return array_combine( array_column($data, $key),  array_column($data, $label));
    }


    ###########################################
    ## PROTECTED STATIC ######

    /**
     * ! THIS WILL DELETE ALL DATA TABLE !
     * WILL DELETE ALL TABLE DATA (NOT PUBLIC)
     */
    protected static function clear(){
        $done = Database::execute('DELETE FROM `'.static::TABLE.'`;');
        return $done->success;
    }

    /**
     * GET MODELLING CLASS DEFINED PROPERTIES
     */
    protected static function props( $type=\ReflectionProperty::IS_PUBLIC ){

        $class = static::class;

        $reflect = new \ReflectionClass($class);
        $props   = $reflect->getProperties($type);

        $properties = [];

        foreach ($props as $property) {
            if( $property->class == $class ){
                $properties[] = $property->getName();
            } 
        }

        # ADD ID INDEX PROPERTY
        if( property_exists($class, 'id') ) $properties[] = 'id';

        return $properties;
    }
    

    /**
     * CREATE A NEW OBJECT WITH THE GIVEN DATA
     * $data : item fields
     */
    protected static function fill( array $data ){
        if( empty($data) ) return null;

        # GET INVOLVED CLASS
        $class = static::class;   # NEEDED IN STATIC HERITANCE CONTEXT
        
        $new = new $class();

        # GET PROPERTIES
        $props = self::props();

        foreach ($props as $key) {
            if( isset($data[$key]) ) $new->$key = $data[$key];
        }

        return $new;
    }

    /**
     * CREATE AN ARRAY OF OBJECTS FOREACH GIVEN DATA
     * $items : array of data items
     */
    protected static function fillAll( array $items ){

        if( empty($items) ) return [];
        
        if( ! is_array(array_values($items)[0]) ) return false;

        # GET INVOLVED CLASS
        $class = static::class;   # NEEDED IN STATIC HERITANCE CONTEXT

        # GET PROPERTIES
        $props = self::props();

        $result = [];

        foreach( $items as $data ){

            $new = new $class();

            foreach ($props as $key) {
                if( isset($data[$key]) ) $new->$key = $data[$key];
            }

            $result[] = $new;

        } 
        return $result;

    }

}