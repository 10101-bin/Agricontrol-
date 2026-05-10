<?php

// Configuración de base de datos
 


class Database {
    
    // Propiedades privadas de conexión
    private $host = "localhost";      
    private $db_name = "agricontrol"; 
    private $username = "root";        
    private $password = "guayabo21";            
    private $conn;                    
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            // Crear conexión PDO con MySQL
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->conn->exec("set names utf8");
            
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            return false;
        }
        
        return $this->conn;
    }
    
    public function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

$database = new Database();
$pdo = $database->getConnection();
?>