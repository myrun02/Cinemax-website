<?php
session_start();
require 'db.php';

$msg = "";
$msgType = ""; // 'error' or 'success'
$role = $_POST['role'] ?? $_GET['role'] ?? 'customer';
$mode = $_POST['mode'] ?? $_GET['mode'] ?? 'login';
$loginRedirectScript = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. CUSTOMER SIGN UP
    if ($action === 'cust_signup') {
        $fullName = trim($_POST['fullName'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$fullName || !$email || !$phone || !$password) {
            $msg = "Please fill in all fields.";
            $msgType = "error";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO customers (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $fullName, $email, $phone, $hashed);

            if ($stmt->execute()) {
                $msg = "Account created! You can now log in.";
                $msgType = "success";
                $mode = "login";
            } else {
                $msg = ($conn->errno === 1062) ? "Email is already registered." : "Error: " . $conn->error;
                $msgType = "error";
            }
            $stmt->close();
        }
    }

    // 2. CUSTOMER LOGIN
    elseif ($action === 'cust_login') {
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $conn->prepare("SELECT full_name, email, phone, password FROM customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $userData = [
                    'role'     => 'Customer',
                    'fullName' => $row['full_name'],
                    'email'    => $row['email'],
                    'phone'    => $row['phone']
                ];
                $_SESSION['user'] = $userData;
                $jsonUser = json_encode($userData);
                $loginRedirectScript = "<script>
                    localStorage.setItem('isLoggedIn', 'true');
                    localStorage.setItem('currentUser', " . json_encode($jsonUser) . ");
                    window.location.href = 'index.html';
                </script>";
            } else {
                $msg = "Invalid email or password.";
                $msgType = "error";
            }
        } else {
            $msg = "Invalid email or password.";
            $msgType = "error";
        }
        $stmt->close();
    }

    // 3. STAFF SIGN UP
    elseif ($action === 'staff_signup') {
        $staffId  = trim($_POST['staffId'] ?? '');
        $fullName = trim($_POST['fullName'] ?? '');
        $dept     = trim($_POST['dept'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$staffId || !$fullName || !$dept || !$email || !$password) {
            $msg = "Error: All staff credentials are required!";
            $msgType = "error";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO staff (staff_id, full_name, department, email, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $staffId, $fullName, $dept, $email, $hashed);

            if ($stmt->execute()) {
                $msg = "Staff registered! You can now log in.";
                $msgType = "success";
                $mode = "login";
            } else {
                $msg = ($conn->errno === 1062) ? "Staff ID is already registered." : "Error: " . $conn->error;
                $msgType = "error";
            }
            $stmt->close();
        }
    }

    // 4. STAFF LOGIN
    elseif ($action === 'staff_login') {
        $staffId  = trim($_POST['staffId'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = $conn->prepare("SELECT staff_id, full_name, department, email, password FROM staff WHERE staff_id = ?");
        $stmt->bind_param("s", $staffId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $userData = [
                    'role'     => 'Staff',
                    'staffId'  => $row['staff_id'],
                    'fullName' => $row['full_name'],
                    'dept'     => $row['department'],
                    'email'    => $row['email']
                ];
                $_SESSION['user'] = $userData;
                $jsonUser = json_encode($userData);
                $loginRedirectScript = "<script>
                    localStorage.setItem('isStaffLoggedIn', 'true');
                    localStorage.setItem('currentUser', " . json_encode($jsonUser) . ");
                    window.location.href = 'index.html';
                </script>";
            } else {
                $msg = "Invalid Staff ID or password.";
                $msgType = "error";
            }
        } else {
            $msg = "Invalid Staff ID or password.";
            $msgType = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CINEMAX - Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background-color: #0a0b10; color: white;
            display: flex; justify-content: center; align-items: center;
            min-height: 100vh; padding: 20px; overflow-x: hidden;
        }
        body::before {
            content: ""; position: fixed; width: 320px; height: 320px;
            background: rgba(0, 132, 255, 0.15); filter: blur(110px);
            border-radius: 50%; top: 10%; left: 10%; z-index: 0;
        }
        .auth-container {
            background: rgba(28, 30, 39, 0.85); padding: 40px 35px; border-radius: 20px;
            width: 100%; max-width: 420px; text-align: center;
            backdrop-filter: blur(10px); border: 1px solid rgba(0, 132, 255, 0.3);
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.6); position: relative; z-index: 1;
        }
        .auth-container h2 {
            color: #0084ff; font-weight: 800; font-size: 28px;
            letter-spacing: 3px; margin-bottom: 5px;
            text-shadow: 0 0 15px rgba(0, 132, 255, 0.6);
        }
        .subtitle { color: #888; font-size: 12px; margin-bottom: 22px; text-transform: uppercase; letter-spacing: 1px; }
        .role-tabs {
            display: flex; background: #161b22; border-radius: 10px;
            padding: 4px; margin-bottom: 20px; border: 1px solid #2d333b;
        }
        .role-btn {
            flex: 1; padding: 10px; border: none; border-radius: 8px;
            background: transparent; color: #888; font-weight: 600;
            font-size: 13px; cursor: pointer; transition: 0.3s;
        }
        .role-btn.active { background: #0084ff; color: white; box-shadow: 0 0 12px rgba(0, 132, 255, 0.4); }
        .input-group { position: relative; margin-bottom: 14px; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #0084ff; font-size: 14px; }
        .auth-container input, .auth-container select {
            width: 100%; padding: 13px 15px 13px 42px;
            background-color: #161b22; border: 1px solid #2d333b;
            border-radius: 10px; color: white; font-size: 14px; outline: none; transition: 0.3s;
        }
        .auth-container input:focus, .auth-container select:focus {
            border-color: #0084ff; box-shadow: 0 0 10px rgba(0, 132, 255, 0.4);
        }
        .submit-btn {
            width: 100%; padding: 14px; margin-top: 8px; background-color: #0084ff;
            color: white; border: none; border-radius: 10px; font-weight: 600;
            font-size: 15px; cursor: pointer; transition: 0.3s;
            text-transform: uppercase; box-shadow: 0 0 15px rgba(0, 132, 255, 0.4);
        }
        .submit-btn:hover { background-color: #00a2ff; transform: translateY(-2px); }
        .msg { font-size: 12px; margin-top: 14px; min-height: 18px; }
        .error { color: #ff4d4d; }
        .success { color: #00ff88; }
        .switch-mode { margin-top: 20px; font-size: 13px; color: #888; }
        .switch-mode a { color: #0084ff; text-decoration: none; font-weight: 600; cursor: pointer; }
        .hidden { display: none; }
    </style>
</head>
<body>

<?php echo $loginRedirectScript; ?>

<div class="auth-container">
    <h2 id="portalTitle">CINEMAX</h2>
    <p class="subtitle" id="portalSubtitle">Customer Login</p>

    <div class="role-tabs">
        <button type="button" class="role-btn" id="tabCustomer" onclick="setRole('customer')"><i class="fas fa-user"></i> Customer</button>
        <button type="button" class="role-btn" id="tabStaff" onclick="setRole('staff')"><i class="fas fa-id-badge"></i> Staff</button>
    </div>

    <!-- 1. CUSTOMER LOGIN -->
    <form method="POST" action="auth.php" id="custLoginForm" class="hidden">
        <input type="hidden" name="action" value="cust_login">
        <input type="hidden" name="role" value="customer">
        <input type="hidden" name="mode" value="login">
        <div class="input-group"><i class="fas fa-envelope"></i><input type="email" name="email" placeholder="Email Address" required></div>
        <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="Password" required></div>
        <button type="submit" class="submit-btn">Log In</button>
    </form>

    <!-- 2. CUSTOMER SIGN UP -->
    <form method="POST" action="auth.php" id="custSignupForm" class="hidden">
        <input type="hidden" name="action" value="cust_signup">
        <input type="hidden" name="role" value="customer">
        <input type="hidden" name="mode" value="signup">
        <div class="input-group"><i class="fas fa-user"></i><input type="text" name="fullName" placeholder="Full Name" required></div>
        <div class="input-group"><i class="fas fa-envelope"></i><input type="email" name="email" placeholder="Email Address" required></div>
        <div class="input-group"><i class="fas fa-phone"></i><input type="text" name="phone" placeholder="Phone Number" required></div>
        <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="Create Password" required></div>
        <button type="submit" class="submit-btn">Create Account</button>
    </form>

    <!-- 3. STAFF LOGIN -->
    <form method="POST" action="auth.php" id="staffLoginForm" class="hidden">
        <input type="hidden" name="action" value="staff_login">
        <input type="hidden" name="role" value="staff">
        <input type="hidden" name="mode" value="login">
        <div class="input-group"><i class="fas fa-id-card"></i><input type="text" name="staffId" placeholder="Staff ID (e.g. STF-001)" required></div>
        <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="Password" required></div>
        <button type="submit" class="submit-btn">Staff Log In</button>
    </form>

    <!-- 4. STAFF SIGN UP -->
    <form method="POST" action="auth.php" id="staffSignupForm" class="hidden">
        <input type="hidden" name="action" value="staff_signup">
        <input type="hidden" name="role" value="staff">
        <input type="hidden" name="mode" value="signup">
        <div class="input-group"><i class="fas fa-id-card"></i><input type="text" name="staffId" placeholder="Staff ID (e.g. STF-001)" required></div>
        <div class="input-group"><i class="fas fa-user"></i><input type="text" name="fullName" placeholder="Full Name" required></div>
        <div class="input-group">
            <i class="fas fa-building"></i>
            <select name="dept" required>
                <option value="" disabled selected>Select Department</option>
                <option value="Ticketing">Ticketing</option>
                <option value="Food & Beverage">Food & Beverage</option>
                <option value="IT Support">IT Support</option>
                <option value="Management">Management</option>
            </select>
        </div>
        <div class="input-group"><i class="fas fa-envelope"></i><input type="email" name="email" placeholder="Work Email" required></div>
        <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="Password" required></div>
        <button type="submit" class="submit-btn">Register Staff</button>
    </form>

    <p class="msg <?php echo $msgType; ?>" id="feedbackMsg"><?php echo htmlspecialchars($msg); ?></p>

    <div class="switch-mode" id="modeToggleText"></div>
</div>

<script>
    let currentRole = "<?php echo $role; ?>";
    let currentMode = "<?php echo $mode; ?>";

    function updateView(clearMsg = false) {
        document.getElementById('custLoginForm').classList.add('hidden');
        document.getElementById('custSignupForm').classList.add('hidden');
        document.getElementById('staffLoginForm').classList.add('hidden');
        document.getElementById('staffSignupForm').classList.add('hidden');
        if (clearMsg) document.getElementById('feedbackMsg').innerText = '';

        document.getElementById('tabCustomer').classList.toggle('active', currentRole === 'customer');
        document.getElementById('tabStaff').classList.toggle('active', currentRole === 'staff');

        const title = document.getElementById('portalTitle');
        const subtitle = document.getElementById('portalSubtitle');
        const toggleText = document.getElementById('modeToggleText');

        if (currentRole === 'customer' && currentMode === 'login') {
            document.getElementById('custLoginForm').classList.remove('hidden');
            title.innerText = 'CINEMAX';
            subtitle.innerText = 'Customer Login';
            toggleText.innerHTML = 'New here? <a onclick="toggleMode()">Create an Account</a>';
        } else if (currentRole === 'customer' && currentMode === 'signup') {
            document.getElementById('custSignupForm').classList.remove('hidden');
            title.innerText = 'JOIN CINEMAX';
            subtitle.innerText = 'Create Customer Account';
            toggleText.innerHTML = 'Already have an account? <a onclick="toggleMode()">Sign In</a>';
        } else if (currentRole === 'staff' && currentMode === 'login') {
            document.getElementById('staffLoginForm').classList.remove('hidden');
            title.innerText = 'STAFF PORTAL';
            subtitle.innerText = 'Internal Access - Login';
            toggleText.innerHTML = 'Need a staff account? <a onclick="toggleMode()">Register Staff</a>';
        } else {
            document.getElementById('staffSignupForm').classList.remove('hidden');
            title.innerText = 'STAFF PORTAL';
            subtitle.innerText = 'Internal Access - Registration';
            toggleText.innerHTML = 'Already registered? <a onclick="toggleMode()">Staff Sign In</a>';
        }
    }

    function setRole(role) {
        currentRole = role;
        currentMode = 'login';
        updateView(true);
    }

    function toggleMode() {
        currentMode = currentMode === 'login' ? 'signup' : 'login';
        updateView(true);
    }

    updateView(false);
</script>
</body>
</html>