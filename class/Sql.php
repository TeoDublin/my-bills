<?php

require_once __DIR__ . '/../objects/ResultForTable.php';
require_once __DIR__ . '/../includes/env.php';

class Sql {

    private ?mysqli $connection = null;
    private $db;
    private $query;

    public function __construct() {

        load_env_file(__DIR__ . '/../.env');
        $host = env_value('DB_HOST', 'localhost');
        $user = env_value('DB_USERNAME', 'teo');
        $pass = env_value('DB_PASSWORD', '123testes');
        $db = env_value('DB_DATABASE', 'my-bills');
        $this->db=$db;
        try {

            $this->connection = new mysqli($host, $user, $pass, $db);
        }
        catch (mysqli_sql_exception $e) {

            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

    }

    public function select(string $query):array {

        $this->assert_connection();
        $this->query=$query;
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);

    }

    public function query(string $query):void {

        $this->assert_connection();
        $this->query=$query;
        $stmt = $this->connection->prepare($query);
        $stmt->execute();

    }

    public function raw(string $query):array {

        $this->assert_connection();
        $this->query=$query;
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);

    }

    public function escape(string $value): string {

        $this->assert_connection();
        return $this->connection->real_escape_string($value);

    }

    public function insert_id(): int {

        return $this->connection->insert_id;

    }

    public function columns($table){

        $this->assert_connection();
        return $this->raw("
            SELECT COLUMN_NAME, COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = '{$table}' AND TABLE_SCHEMA = '{$this->db}'

            ORDER BY ORDINAL_POSITION;        
        ");
    }

    public function __destruct() {

        if ($this->connection instanceof mysqli) {

            $this->connection->close();
        }

    }

    private function assert_connection(): void
    {

        if (!$this->connection instanceof mysqli) {

            throw new RuntimeException('Database connection is not available.');
        }

    }

}
