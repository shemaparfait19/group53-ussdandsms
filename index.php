<?php
include 'Menu.php';
require_once 'DbConnect.php';

// Get the raw POST data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

// If no JSON data, try regular POST
if (!$data) {
    $data = $_POST;
}

// Initialize variables with values from either JSON or POST
$sessionId   = isset($data['sessionId']) ? $data['sessionId'] : '';
$phoneNumber = isset($data['phoneNumber']) ? $data['phoneNumber'] : '';
$serviceCode = isset($data['serviceCode']) ? $data['serviceCode'] : '';
$text        = isset($data['text']) ? $data['text'] : '';

// For testing purposes, if no parameters are received, use test values
if (empty($sessionId) && empty($phoneNumber) && empty($serviceCode)) {
    $sessionId = 'test_session_' . time();
    $phoneNumber = '250788123456';
    $serviceCode = '*123#';
    $text = '';
}

$menu = new Menu($text, $sessionId, $phoneNumber);

try {
    // Check if the user is already registered in the database
    $conn = new DbConnect();
    $db = $conn->connect();

    // Ensure phone_number column exists in users table
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'phone_number'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE users ADD COLUMN phone_number VARCHAR(20) UNIQUE AFTER email");
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE phone_number = ?");
    $stmt->execute([$phoneNumber]);
    $isRegistered = $stmt->fetch() ? true : false;

    $text = $menu->middleware($text);
    $textArray = explode("*", $text);

    // USSD flow
    if ($text == "" && !$isRegistered) {
        echo $menu->mainMenuUnregistered();
    }
    else if ($text == "" && $isRegistered) {
        echo $menu->mainMenuRegistered();
    }
    else if (!$isRegistered) {
        switch ($textArray[0]) {
            case "1":
                echo $menu->menuRegister($textArray);
                break;
            default:
                echo "END Invalid option, Retry";
        }
    }
    else {
        switch ($textArray[0]) {
            case "1":
                echo $menu->menuCheckAttendance($textArray);
                break;
            case "2":
                echo $menu->menuViewStudentInfo($textArray);
                break;
            default:
                echo "END Invalid choice\n";
        }
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo "END An error occurred. Please try again later.";
}
?> 