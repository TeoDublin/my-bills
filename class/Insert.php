<?php 
class Insert{

    private SQL $sql;
    private string $into;
    private string $insert;
    private string $values;
    private string $ignore;
    public function __construct(array $insert){

        $this->sql=SQL();
        $this->ignore='';
        $_insert=$_values=[];
        foreach ($insert as $key => $value) {

            $_insert[]="`{$key}`";
            if(preg_match('#([0-9]{2})\/([0-9]{2})\/([0-9]{4})#',$value,$m))$value="{$m[3]}-{$m[2]}-{$m[1]}";

            $value=str_replace("'","\'",$value);
            $_values[]=$value||$value==0?"'{$value}'":'NULL';
        }
        $this->insert='('.implode(',',$_insert).')';
        $this->values='('.implode(',',$_values).')';
    }

    public function into($table){

        $this->into=$table;
        $this->sql->query("INSERT {$this->ignore} INTO `{$this->into}` {$this->insert} VALUES {$this->values}");

        return $this;
    }

    public function ignore(){

        $this->ignore="IGNORE";
        return $this;

    }

    public function get(){

        return $this->sql->insert_id();

    }

    public function flush(){

        $this->sql->flush();

    }

}
