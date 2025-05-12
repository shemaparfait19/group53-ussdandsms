<?php
require_once("DbConnect.php");
include_once 'util.php';
require_once 'sms.php';

class Menu {
    protected $sessionId;
    protected $serviceCode;
    protected $phoneNumber;
    protected $text;
    protected $db;
   

    function __construct($text, $sessionId, $phoneNumber) {
        $this->text = $text;
        $this->sessionId = $sessionId;
        $this->phoneNumber = $phoneNumber;

        $conn = new DbConnect();
        $this->db = $conn->connect();
        $this->createOutboxTableIfNotExists();
    }

    private function createOutboxTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS sms_outbox (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('pending','sent','failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->db->exec($sql);
    }

    public function isRegistered() {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE phone_number = ?");
        $stmt->execute([$this->phoneNumber]);
        return $stmt->fetch();
    }

    public function mainMenu() {
        $user = $this->isRegistered();
        if ($user) {
            return $this->mainMenuRegistered();
        } else {
            return $this->mainMenuUnregistered();
        }
    }

    public function mainMenuUnregistered() {
        $response = "CON Welcome to School Attendance USSD\n";
        $response .= "1. Register as Teacher\n";
        return $response;
    }

    public function menuRegister($textArray) {
        $level = count($textArray);

        if ($level == 1) {
            return "CON Enter your full name";
        } else if ($level == 2) {
            return "CON Enter your PIN";
        } else if ($level == 3) {
            return "CON Re-enter your PIN";
        } else if ($level == 4) {
            $name = $textArray[1];
            $pin = $textArray[2];
            $confirm_pin = $textArray[3];

            if ($pin !== $confirm_pin) {
                return "END PINs do not match, try again.";
            }

            // Insert user with phone_number, name, and a dummy email (since email is required and must be unique)
            $email = $this->phoneNumber . '@ussd.local';
            $stmt = $this->db->prepare("INSERT INTO users (name, email, password, phone_number, role, created_at) VALUES (?, ?, ?, ?, 'teacher', NOW())");
            $stmt->execute([$name, $email, password_hash($pin, PASSWORD_DEFAULT), $this->phoneNumber]);

            $this->sendRealSMS($this->phoneNumber, "Dear $name, you have successfully registered as a teacher in the School Attendance System. Welcome!");
            return "END Dear $name, you have successfully registered as a teacher!";
        }
    }

    public function mainMenuRegistered() {
        $response = "CON School Attendance System\n";
        $response .= "1. Check Attendance\n";
        $response .= "2. View Student Info\n";
        return $response;
    }

    public function menuCheckAttendance($textArray) {
        $level = count($textArray);

        if ($level == 1) {
            return "CON Enter Student ID";
        } else {
            $studentId = $textArray[1];
            $stmt = $this->db->prepare(
                "SELECT a.*, s.firstname, s.lastname, c.name as class_name
                 FROM attendances a
                 JOIN students s ON a.student_id = s.id
                 JOIN classes c ON s.class_id = c.id
                 WHERE a.student_id = ?
                 ORDER BY a.date DESC
                 LIMIT 1"
            );
            $stmt->execute([$studentId]);
            $attendance = $stmt->fetch();

            if ($attendance) {
                return "END Student: {$attendance['firstname']} {$attendance['lastname']}\nClass: {$attendance['class_name']}\nStatus: {$attendance['status']}\nDate: {$attendance['date']}";
            } else {
                return "END No attendance record found.";
            }
        }
    }

    public function menuViewStudentInfo($textArray) {
        $level = count($textArray);

        if ($level == 1) {
            return "CON Enter Student ID";
        } else {
            $studentId = $textArray[1];
            $stmt = $this->db->prepare(
                "SELECT s.*, c.name as class_name
                 FROM students s
                 JOIN classes c ON s.class_id = c.id
                 WHERE s.id = ?"
            );
            $stmt->execute([$studentId]);
            $student = $stmt->fetch();

            if ($student) {
                return "END Student Info:\nName: {$student['firstname']} {$student['lastname']}\nClass: {$student['class_name']}\nID: {$student['id']}";
            } else {
                return "END Student not found.";
            }
        }
    }

    public function goBack($text) {
        $explodedText = explode("*", $text);
        while (($index = array_search(Util::$GO_BACK, $explodedText)) !== false) {
            array_splice($explodedText, $index - 1, 2);
        }
        return join('*', $explodedText);
    }

    public function goToMainMenu($text) {
        $explodedText = explode("*", $text);
        while (($index = array_search(Util::$GO_TO_MAIN_MENU, $explodedText)) !== false) {
            $explodedText = array_slice($explodedText, $index + 1);
        }
        return join('*', $explodedText);
    }

    public function middleware($text) {
        return $this->goBack($this->goToMainMenu($text));
    }

    private function sendRealSMS($to, $message) {
        $smsInstance = new sms($to);
        $smsInstance->sendSMS($message, $to);
    }
}
?> 