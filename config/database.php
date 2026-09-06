<?php
class Database {
    private $host = "localhost";
    private $db_name = "fin-hospital";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            // Log error instead of echoing (which can break page rendering)
            error_log("Database connection error: " . $exception->getMessage());
            // Don't echo here - let calling code handle null connection
            $this->conn = null;
        }
        return $this->conn;
    }
}
?>