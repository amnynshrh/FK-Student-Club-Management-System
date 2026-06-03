<?php   //TO CREATE studentClub DB
$servername = "localhost";
$username = "root";
$password = "";

// 1. Create connection (without specifying a database)
$conn = new mysqli($servername, $username, $password);

// 2. Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 3. SQL query to create the database
$sql = "CREATE DATABASE fk_scems_db";

// 4. Execute and check
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully!";
} else {
    echo "Error creating database: " . $conn->error;
}

// 5. Close connection
$conn->close();
?>