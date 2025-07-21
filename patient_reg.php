<?php
session_start();

// Enable error logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Database connection
$conn = new mysqli("localhost", "root", "", "speech_therapy_clinic_patient");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Collecting form data
$fullname = $_POST['fullname'];
$gender = $_POST['gender'];
$contact = $_POST['contact'];
$email = $_POST['email'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$otp = $_POST['otp'];

try {
    // Validate all required fields
    if (empty($fullname) || empty($gender) || empty($contact) || 
        empty($email) || empty($password) || empty($confirm_password) || empty($otp)) {
        throw new Exception("All fields are required.");
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    // Validate contact number (assuming 10 digits)
    if (!preg_match("/^[0-9]{10}$/", $contact)) {
        throw new Exception("Invalid contact number. Please enter 10 digits.");
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }

    // Validate OTP
    if (!isset($_SESSION['otp']) || 
        !isset($_SESSION['otp_email']) || 
        $_SESSION['otp'] != $otp || 
        $_SESSION['otp_email'] != $email ||
        (time() - $_SESSION['otp_timestamp']) > 300) { // 5 minutes expiry
        throw new Exception("Invalid OTP or OTP expired. Please request a new OTP.");
    }

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT email FROM patients WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception("Email already registered. Please use a different email.");
    }
    $check_stmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert data using prepared statement
    $stmt = $conn->prepare("INSERT INTO patients (fullname, gender, contact, email, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $fullname, $gender, $contact, $email, $hashed_password);

    if ($stmt->execute()) {
        // Clear OTP session variables
        unset($_SESSION['otp']);
        unset($_SESSION['otp_email']);
        unset($_SESSION['otp_timestamp']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Registration successful! Please login.',
            'redirect' => 'login.html'
        ]);
    } else {
        throw new Exception("Registration failed. Please try again.");
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}
?>