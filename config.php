<?php
// config.php - การตั้งค่าฐานข้อมูล
class DatabaseConfig {
    private $host = "localhost";
    private $db_name = "smart_parcel_box";
    private $username = "root"; // เปลี่ยนตามการตั้งค่าจริง
    private $password = "pinkcuteroot"; // เปลี่ยนตามการตั้งค่าจริง
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>