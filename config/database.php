<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change this to your MySQL username
define('DB_PASS', '');            // Change this to your MySQL password
define('DB_NAME', 'bus_rental_chatbot');

// Create connection
function getDbConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            die("<p style='color:red; font-weight:bold;'>Connection failed: " . $conn->connect_error . 
                "</p><p>Please make sure you've created the database 'bus_rental_chatbot' in phpMyAdmin.</p>" .
                "<p>Steps to create the database:<br>1. Open phpMyAdmin (http://localhost/phpmyadmin)<br>" .
                "2. Click on 'New' on the left sidebar<br>" .
                "3. Enter 'bus_rental_chatbot' as the database name and click 'Create'<br>" .
                "4. Select the database and import the database.sql file from this project.</p>");
        }
        
        return $conn;
    } catch (Exception $e) {
        die("<p style='color:red; font-weight:bold;'>Database Error: " . $e->getMessage() . 
            "</p><p>Please make sure you've created the database 'bus_rental_chatbot' in phpMyAdmin.</p>" .
            "<p>Steps to create the database:<br>1. Open phpMyAdmin (http://localhost/phpmyadmin)<br>" .
            "2. Click on 'New' on the left sidebar<br>" .
            "3. Enter 'bus_rental_chatbot' as the database name and click 'Create'<br>" .
            "4. Select the database and import the database.sql file from this project.</p>");
    }
}

// Function to execute SQL queries
function executeQuery($sql, $params = [], $types = "") {
    $conn = getDbConnection();
    
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            die("Error preparing statement: " . $conn->error);
        }
        
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $conn->query($sql);
        
        if (!$result) {
            die("Error executing query: " . $conn->error);
        }
    }
    
    $conn->close();
    return $result;
}

// Function to get a single row
function getRow($sql, $params = [], $types = "") {
    $result = executeQuery($sql, $params, $types);
    $row = $result->fetch_assoc();
    $result->free();
    return $row;
}

// Function to get multiple rows
function getRows($sql, $params = [], $types = "") {
    $result = executeQuery($sql, $params, $types);
    $rows = [];
    
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    $result->free();
    return $rows;
}

// Function to insert data and return inserted ID
function insertData($sql, $params = [], $types = "") {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $insertId = $conn->insert_id;
    $stmt->close();
    $conn->close();
    
    return $insertId;
}
?>
