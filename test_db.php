<?php
// test_db.php - File untuk menguji koneksi database
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Testing Database Connection</h2>";

try {
    echo "<p>Database Name: " . DB_NAME . "</p>";
    echo "<p>Database Host: " . DB_HOST . "</p>";
    
    // Test koneksi
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ]
    );
    
    echo "<p style='color: green;'>✓ Connected successfully!</p>";
    
    // Test query sederhana
    $stmt = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✓ Query executed successfully!</p>";
    
    // Test tabel yang ada
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Tables:</h3>";
    
    if ($tables && count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table['Tables_in_' . DB_NAME]) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠ No tables found or access denied</p>";
    }
    
    // Test tabel reservations
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' AND table_name = 'reservations'");
    if ($stmt->fetch()['count'] > 0) {
        echo "<p style='color: green;'>✓ Table 'reservations' exists</p>";
        
        // Test struktur tabel reservations
        $columns = $pdo->query("DESCRIBE reservations");
        echo "<h4>Reservations Table Structure:</h4>";
        
        if ($columns && $columns->rowCount() > 0) {
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr style='background-color: #f2f2f2;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
            
            while ($column = $columns->fetch()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
                echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
                echo "<td>" . ($column['Null'] === 'YES' ? 'Yes' : 'No') . "</td>";
                echo "<td>" . ($column['Key'] ? $column['Key'] : '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Test query ke tabel reservations
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations");
        $result = $stmt->fetch();
        echo "<p>Total records in reservations table: " . $result['count'] . "</p>";
        
        // Test query sample data
        $stmt = $pdo->query("SELECT * FROM reservations LIMIT 1");
        if ($stmt->rowCount() > 0) {
            echo "<h4>Sample Data:</h4>";
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
            // Header
            echo "<tr style='background-color: #f2f2f2;'>";
            foreach ($row as $key => $value) {
                echo "<th>" . htmlspecialchars($key) . "</th>";
            }
            echo "</tr>";
            
            // Data
            echo "<tr>";
            foreach ($row as $key => $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
            echo "</table>";
        }
        
    } else {
        echo "<p style='color: red;'>⚠ Table 'reservations' not found!</p>";
        
        // Coba membuat tabel jika belum ada
        echo "<h4>Trying to create table...</h4>";
        $createTableSQL = "CREATE TABLE IF NOT EXISTS `reservations` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `lab_id` int(11) NOT NULL,
            `purpose` text NOT NULL,
            `participants` int(11) DEFAULT NULL,
            `start_time` datetime NOT NULL,
            `end_time` datetime NOT NULL,
            `notes` text DEFAULT NULL,
            `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if ($pdo->exec($createTableSQL)) {
            echo "<p style='color: green;'>✓ Table created successfully!</p>";
        } else {
            echo "<p style='color: red;'>⚠ Failed to create table: " . $pdo->errorInfo()[2] . "</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Database Error:</h2>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color: red;'>File: " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p style='color: red;'>Line: " . $e->getLine() . "</p>";
    echo "<pre style='color: red;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?>