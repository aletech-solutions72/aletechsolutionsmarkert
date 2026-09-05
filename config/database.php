<?php
class Database {
    private $host = "localhost";
    private $db_name = "msika_premium";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                  $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            // For debugging - remove in production
            echo "Connection error: " . $exception->getMessage();
            error_log("Database connection failed: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>