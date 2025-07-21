<?php
session_start();

// Enable error logging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Database connection
$conn = new mysqli("localhost", "root", "", "speech_therapy_clinic_therapist");

if ($conn->connect_error) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Validate OTP first
    if (!isset($_SESSION['otp']) || 
        !isset($_SESSION['otp_email']) || 
        $_POST['otp'] != $_SESSION['otp'] || 
        $_POST['email'] != $_SESSION['otp_email'] ||
        (time() - $_SESSION['otp_timestamp']) > 300) { // 5 minutes expiry
        throw new Exception("Invalid OTP or OTP expired. Please request a new OTP.");
    }

    // Collecting and validating form data
    $fullname = trim($_POST['fullname']);
    $dob = trim($_POST['dob']);
    $gender = trim($_POST['gender']);
    $contact = trim($_POST['contact']);
    $license = trim($_POST['license']);
    $education = trim($_POST['education']);
    $experience = trim($_POST['experience']);
    $specialization = trim($_POST['specialization']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation
    if (empty($fullname) || empty($dob) || empty($gender) || empty($contact) || 
        empty($license) || empty($education) || empty($experience) || 
        empty($specialization) || empty($email) || empty($password)) {
        throw new Exception("All fields are required.");
    }

    // Password validation
    if ($password !== $confirm_password) {
        throw new Exception("Passwords do not match.");
    }

    // File upload handling
    $certificate_new_name = '';
    $photo_new_name = '';
    
    if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['certificate']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            throw new Exception("Invalid certificate file type.");
        }
        $certificate_new_name = uniqid() . '.' . $ext;
        $upload_path = "uploads/certificates/";
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        move_uploaded_file($_FILES['certificate']['tmp_name'], $upload_path . $certificate_new_name);
    }

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            throw new Exception("Invalid photo file type.");
        }
        $photo_new_name = uniqid() . '.' . $ext;
        $upload_path = "uploads/photos/";
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path . $photo_new_name);
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Use prepared statement for insertion
    $stmt = $conn->prepare("INSERT INTO therapists (fullname, dob, gender, contact, license, education, experience, specialization, certificate, photo, email, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("ssssssssssss", 
        $fullname, $dob, $gender, $contact, $license, $education, 
        $experience, $specialization, $certificate_new_name, 
        $photo_new_name, $email, $hashed_password
    );

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