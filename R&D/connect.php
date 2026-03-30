<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = mysqli_connect('localhost', 'root', '', 'test1');
if(!$conn){
    die('Connection Failed: ' . mysqli_connect_error());
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])){

    $name = $_POST['name'];
    $age = $_POST['age'];
    $blood_group = $_POST['blood_group'];
    $contact = $_POST['contact'];

    if(!empty($name) && is_numeric($age) && !empty($blood_group) && !empty($contact)){

        $stmt = $conn->prepare("INSERT INTO users (name, age, blood_group, contact) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siss", $name, $age, $blood_group, $contact);

        if($stmt->execute()){
            echo "Entry added successfully!";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Invalid input!";
    }
}

mysqli_close($conn);
?>