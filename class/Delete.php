<?php 
    class Delete{

        private $table;
        public function from(string $table){

            $this->table=$table;
            return $this;

        }

        public function where($where){

            SQL()->query("DELETE FROM {$this->table} WHERE {$where}");

        }
    }