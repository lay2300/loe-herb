<?php
require_once '../config/security.php';
start_secure_session();
require_once '../config/db.php'; // เชื่อมต่อฐานข้อมูล

// ถ้าล็อกอินอยู่แล้ว ให้เด้งไปหน้า index
if (isset($_SESSION['admin_login'])) {
    header("Location: index.php");
    exit();
}

// สร้าง URL สำหรับ Google Login (Admin)
require_once '../config/oauth.php';
$admin_google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email') . '&redirect_uri=' . urlencode(ADMIN_GOOGLE_REDIRECT_URL) . '&response_type=code&client_id=' . ADMIN_GOOGLE_CLIENT_ID . '&access_type=online';

$error = '';

// ตรวจสอบการกดปุ่ม Login
if (isset($_POST['login'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // ตรวจสอบจากฐานข้อมูล
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // ตรวจสอบว่าเป็นบัญชีที่สมัครผ่าน OAuth หรือไม่
        if (!empty($user['oauth_provider'])) {
            $error = "บัญชีนี้เชื่อมต่อกับ " . ucfirst($user['oauth_provider']) . " กรุณาเข้าสู่ระบบด้วย " . ucfirst($user['oauth_provider']);
        }
        // ตรวจสอบรหัสผ่านที่เข้ารหัสแล้วเท่านั้น
        else if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_login'] = true; // ตั้งค่า Session ว่าล็อกอินแล้ว
            $_SESSION['admin_id'] = $user['id']; // เก็บ ID ผู้ใช้ไว้สำหรับเปลี่ยนรหัสผ่าน
            header("Location: index.php");
            exit();
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบผู้ดูแล - LoeiHerb</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/login.css?v=<?php echo time(); ?>">
</head>
<body class="bg-gradient-to-br from-green-900 via-green-800 to-emerald-900 h-screen flex items-center justify-center relative overflow-hidden">

    <!-- Decorative circles -->
    <div class="absolute top-0 left-0 w-full h-full overflow-hidden z-0 pointer-events-none">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-green-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-emerald-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-lime-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
    </div>

    <div class="login-card p-8 md:p-10 rounded-3xl shadow-2xl w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
                <span class="text-3xl">🌿</span>
            </div>
            <h2 class="text-3xl font-bold text-green-900">LoeiHerb Admin</h2>
            <p class="text-gray-500 text-sm mt-2">เข้าสู่ระบบจัดการฐานข้อมูลสมุนไพร</p>
        </div>
        
        <?php if($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r mb-6 text-sm">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?php echo $error; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if(isset($success_msg)): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-r mb-6 text-sm flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1 ml-1">ชื่อผู้ใช้งาน</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    <input type="text" name="username" required class="login-input w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 outline-none transition bg-gray-50 focus:bg-white" placeholder="Username">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1 ml-1">รหัสผ่าน</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    </div>
                    <input type="password" name="password" required class="login-input w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 outline-none transition bg-gray-50 focus:bg-white" placeholder="••••••••">
                </div>
            </div>
            <button type="submit" name="login" class="login-btn w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl hover:from-green-700 hover:to-emerald-700 font-bold shadow-lg hover:shadow-xl transition duration-200">
                เข้าสู่ระบบ
            </button>

            <div class="mt-4">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-white text-gray-500">หรือ</span>
                    </div>
                </div>
                <a href="<?php echo $admin_google_login_url; ?>" class="mt-4 w-full flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="h-5 w-5 mr-2" alt="Google">
                    เข้าสู่ระบบด้วย Google
                </a>
            </div>
        </form>
        <div class="mt-4 text-center text-sm text-gray-500">
            <a href="reset_admin.php" class="text-green-600 hover:text-green-800 font-medium">ลืมรหัสผ่าน? รีเซ็ตรหัสผ่านผู้ดูแลระบบ</a>
        </div>
        <div class="mt-8 text-center">
            <a href="../index.php" class="text-sm text-gray-500 hover:text-green-600 transition flex items-center justify-center group">
                <svg class="w-4 h-4 mr-1 group-hover:-translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                กลับหน้าเว็บไซต์หลัก
            </a>
        </div>
    </div>

</body>
</html>