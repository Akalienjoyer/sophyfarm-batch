<?php
define("CONN_ERROR","Error connecting DB");
define("NO_DATA",0);
define("BAD_QUERY",1);
define("INSERT_OK",2);
define("DELETE_OK",3);
define("UPDATE_OK",4);
define("QUERY_OK",5);
define("SELECT_QUERY",1);
define("INSERT_QUERY",2);
define("DELETE_QUERY",3);
define("UPDATE_QUERY",4);

class Database {
    private $conn;
    private $user;
    private $pwd;
    private $db;
    private $host;
    private $path;

    public $results;
    public $rows;
    public $messages;

    public function __construct() {
        $this->conn = null;
        $this->results = null;
        $this->db = "sophyfarm";       
        $this->user = "root";          
        $this->pwd = "";               
        $this->host = "localhost:3306";
        $this->path = "http://localhost/sophyfarm_batch";
        $this->rows = 0;
        $this->messages = array(
            "Error en la conexión",
            "No se pudo realizar la operación"
        );

        $this->connect();
    }

    public function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db};charset=utf8mb4",
                $this->user,
                $this->pwd,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("❌ Error de conexión a la BD: " . $e->getMessage());
        }
        return $this->conn;
    }

    public function doQuery($query, $type = SELECT_QUERY) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            switch ($type) {
                case SELECT_QUERY:
                    $this->results = $stmt->fetchAll();
                    $this->rows = count($this->results);
                    return true;
                case INSERT_QUERY:
                case UPDATE_QUERY:
                case DELETE_QUERY:
                    $this->rows = $stmt->rowCount();
                    return true;
                default:
                    return false;
            }
        } catch (PDOException $e) {
            echo "⚠️ Error en la consulta: " . $e->getMessage() . "<br>Query: $query";
            return false;
        }
    }

    public function getNumResults() {
        return $this->rows;
    }

    public function getResults() {
        return $this->results;
    }

    public function getLastId() {
        return $this->conn->lastInsertId();
    }

    public function disconnect() {
        $this->conn = null;
    }
}
?>
