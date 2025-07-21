<?php
session_start();
// Database connection
$conn = new mysqli('localhost', 'root', '', 'speech_therapy_clinic');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Check if email already exists
function emailExists($conn, $email, $userType) {
    $sql = "SELECT * FROM $userType WHERE email='$email'";
    $result = $conn->query($sql);
    return $result->num_rows > 0;
}

// Insert user data
function insertUser($conn, $userType, $fullname, $dob, $gender, $contact, $email, $password, $extraFields = []) {
    // Add OTP verification
    if (!isset($_SESSION['otp']) || !isset($_POST['otp']) || $_SESSION['otp'] != $_POST['otp']) {
        echo "<script>alert('Invalid OTP!');</script>";
        return false;
    }
    // Check OTP expiration
    if (time() - $_SESSION['otp_timestamp'] > 300) { // 5 minutes expiration
        echo "<script>alert('OTP has expired! Please request a new one.');</script>";
        return false;
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $sql = "INSERT INTO $userType (fullname, dob, gender, contact, email, password";
    $values = "?, ?, ?, ?, ?, ?";
    $types = "ssssss";
    $params = [$fullname, $dob, $gender, $contact, $email, $hashed_password];
    // Add extra fields dynamically
    foreach ($extraFields as $field => $value) {
        $sql .= ", $field";
        $values .= ", ?";
        $types .= "s";
        $params[] = $value;
    }

    $sql .= ") VALUES ($values)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Clear OTP session after successful registration
        unset($_SESSION['otp']);
        unset($_SESSION['otp_timestamp']);
        echo "<script>
            alert('Registration successful!');
            window.location.href = 'login.php';
        </script>";
        return true;
    } else {
        echo "Error: " . $stmt->error;
        return false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userType = sanitize($_POST['userType']);
    $fullname = sanitize($_POST['fullname']);
    $dob = sanitize($_POST['dob']);
    $gender = sanitize($_POST['gender']);
    $contact = sanitize($_POST['contact']);
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format!');</script>";
        exit;
    }

    // Validate password strength
    if (strlen($password) < 8) {
        echo "<script>alert('Password must be at least 8 characters long!');</script>";
        exit;
    }

    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!');</script>";
    } else {
        if (emailExists($conn, $email, $userType)) {
            echo "<script>alert('Email already exists!');</script>";
        } else {
            $extraFields = [];

            if ($userType === 'therapist' || $userType === 'supervisor') {
                $extraFields['license'] = sanitize($_POST['license']);
                $extraFields['qualification'] = sanitize($_POST['qualification']);
                $extraFields['experience'] = sanitize($_POST['experience']);
            }

            // Check if OTP exists and is valid
            if (!isset($_POST['otp'])) {
                echo "<script>alert('Please enter OTP!');</script>";
                exit;
            }

            insertUser($conn, $userType, $fullname, $dob, $gender, $contact, $email, $password, $extraFields);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Speech Language Therapy Services</title>
    <script>
        function sendOTP(email) {
            fetch('send_otp.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.text())
            .then(data => {
                if(data === 'success') {
                    alert('OTP sent to your email');
                    document.getElementById('otpField').style.display = 'block';
                } else {
                    alert('Failed to send OTP: ' + data);
                }
            });
        }       
        function showForm() {
            var userType = document.getElementById("userType").value;
            document.getElementById("patientForm").style.display = "none";
            document.getElementById("therapistForm").style.display = "none";
            document.getElementById("supervisorForm").style.display = "none";
            document.getElementById("adminForm").style.display = "none";
            if (userType == "patient") {
                document.getElementById("patientForm").style.display = "block";
            } else if (userType == "therapist") {
                document.getElementById("therapistForm").style.display = "block";
            } else if (userType == "supervisor") {
                document.getElementById("supervisorForm").style.display = "block";
            } else if (userType == "admin") {
                document.getElementById("adminForm").style.display = "block";
            }
        }
    </script>
</head>
<body>
    <h2>Sign Up</h2>
    <label for="userType">Select User Type:</label>
    <select id="userType" name="userType" onchange="showForm()" required>
        <option value="">--Select User Type--</option>
        <option value="patient">Patient</option>
        <option value="therapist">Therapist</option>
        <option value="supervisor">Supervisor</option>
        <option value="admin">Admin</option>
    </select>
    <br><br>

    <!-- Patient Registration Form -->
    <form id="patientForm" action="reg_new.php" method="post" style="display:none;">
        <h3>Patient Registration</h3>
        <input type="hidden" name="userType" value="patient">
        <label for="fullname">Full Name:</label>
        <input type="text" id="fullname" name="fullname" required><br><br>
        <label for="dob">Date of Birth:</label>
        <input type="date" id="dob" name="dob" required><br><br>
        <label for="gender">Gender:</label>
        <input type="text" id="gender" name="gender" required><br><br>
        <label for="contact">Contact No.:</label>
        <input type="text" id="contact" name="contact" required><br><br>
        <label for="email">Email ID:</label>
        <input type="email" id="email" name="email" required><br><br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br><br>
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required><br><br>
        <div id="otpField" style="display:none;">
            <label for="otp">Enter OTP:</label>
            <input type="text" name="otp" required><br><br>
        </div>
        <button type="button" onclick="sendOTP(this.form.email.value)">Send OTP</button>
        <button type="submit">Sign Up</button>
    </form>

    <!-- Therapist Registration Form -->
    <form id="therapistForm" action="reg_new.php" method="post" style="display:none;">
        <h3>Therapist Registration</h3>
        <input type="hidden" name="userType" value="therapist">
        <label for="fullname">Full Name:</label>
        <input type="text" id="fullname" name="fullname" required><br><br>
        <label for="dob">Date of Birth:</label>
        <input type="date" id="dob" name="dob" required><br><br>
        <label for="gender">Gender:</label>
        <input type="text" id="gender" name="gender" required><br><br>
        <label for="contact">Contact No.:</label>
        <input type="text" id="contact" name="contact" required><br><br>
        <label for="license">Professional License Number:</label>
        <input type="text" id="license" name="license" required><br><br>
        <label for="qualification">Qualification:</label>
        <input type="text" id="qualification" name="qualification" required><br><br>
        <label for="experience">Years of Experience:</label>
        <input type="number" id="experience" name="experience" required><br><br>
        <label for="email">Email ID:</label>
        <input type="email" id="email" name="email" required><br><br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br><br>
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required><br><br>
        <div id="otpField" style="display:none;">
            <label for="otp">Enter OTP:</label>
            <input type="text" name="otp" required><br><br>
        </div>
        <button type="button" onclick="sendOTP(this.form.email.value)">Send OTP</button>
        <button type="submit">Sign Up</button>
    </form>

    <!-- Supervisor Registration Form -->
    <form id="supervisorForm" action="reg_new.php" method="post" style="display:none;">
        <h3>Supervisor Registration</h3>
        <input type="hidden" name="userType" value="supervisor">
        <label for="fullname">Full Name:</label>
        <input type="text" id="fullname" name="fullname" required><br><br>
        <label for="dob">Date of Birth:</label>
        <input type="date" id="dob" name="dob" required><br><br>
        <label for="gender">Gender:</label>
        <input type="text" id="gender" name="gender" required><br><br>
        <label for="contact">Contact No.:</label>
        <input type="text" id="contact" name="contact" required><br><br>
        <label for="license">Professional License Number:</label>
        <input type="text" id="license" name="license" required><br><br>
        <label for="qualification">Qualification:</label>
        <input type="text" id="qualification" name="qualification" required><br><br>
        <label for="experience">Years of Experience:</label>
        <input type="number" id="experience" name="experience" required><br><br>
        <label for="email">Email ID:</label>
        <input type="email" id="email" name="email" required><br><br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br><br>
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required><br><br>
        <div id="otpField" style="display:none;">
            <label for="otp">Enter OTP:</label>
            <input type="text" name="otp" required><br><br>
        </div>
        <button type="button" onclick="sendOTP(this.form.email.value)">Send OTP</button>
        <button type="submit">Sign Up</button>
    </form>

    <!-- Admin Registration Form -->
    <form id="adminForm" action="reg_new.php" method="post" style="display:none;">
        <h3>Admin Registration</h3>
        <input type="hidden" name="userType" value="admin">
        <label for="fullname">Full Name:</label>
        <input type="text" id="fullname" name="fullname" required><br><br>
        <label for="dob">Date of Birth:</label>
        <input type="date" id="dob" name="dob" required><br><br>
        <label for="gender">Gender:</label>
        <input type="text" id="gender" name="gender" required><br><br>
        <label for="contact">Contact No.:</label>
        <input type="text" id="contact" name="contact" required><br><br>
        <label for="email">Email ID:</label>
        <input type="email" id="email" name="email" required><br><br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br><br>
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required><br><br>
        <div id="otpField" style="display:none;">
            <label for="otp">Enter OTP:</label>
            <input type="text" name="otp" required><br><br>
        </div>
        <button type="button" onclick="sendOTP(this.form.email.value)">Send OTP</button>
        <button type="submit">Sign Up</button>
    </form>
</body>
</html>
    