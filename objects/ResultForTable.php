<?php 

    Class ResultForTable{

        public array $result;
        public int $total;
        public int $offset;
        public int $limit;
        public int $pages;
        public string $query;
        public function __construct(array $result, int $total, int $offset, int $limit, $query){

            $this->result = $result;
            $this->total = $total;
            $this->offset = $offset;
            $this->limit = $limit;
            $this->pages = $total==0?0:ceil($total/$limit);
            $this->query = $query;

        }

}