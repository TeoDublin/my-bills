
<?php 
class Enum{

    public $list;
    public function __construct(string $table, string $column) {

        $result=Sql()->select("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$column}'");

        $ret=str_replace("'",'',str_replace(')','',str_replace('enum(','',$result[0]['COLUMN_TYPE'])));
        $this->list=explode(',',$ret);

    }

    public function get(){

        return $this->list;

    }

}
