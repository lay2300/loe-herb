<?php
require_once 'config/security.php';
start_secure_session();
if (isset($_SESSION['user_login'])) {
    header('Location: index.php');
    exit();
}
require_once 'config/db.php';
require_once 'config/oauth.php';

$google_login_url = 'https://accounts.google.com/o/oauth2/v2/auth?scope=' . urlencode('https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email') . '&redirect_uri=' . urlencode(GOOGLE_REDIRECT_URL) . '&response_type=code&client_id=' . GOOGLE_CLIENT_ID . '&access_type=online';
$facebook_login_url = 'https://www.facebook.com/v19.0/dialog/oauth?client_id=' . FACEBOOK_APP_ID . '&redirect_uri=' . urlencode(FACEBOOK_REDIRECT_URL) . '&response_type=code&scope=public_profile,email&auth_type=rerequest';

$facebook_configured = FACEBOOK_APP_ID !== '' && FACEBOOK_APP_SECRET !== '';
$google_configured = GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';

$error = '';

if (isset($_SESSION['oauth_error'])) {
    $error = $_SESSION['oauth_error'];
    unset($_SESSION['oauth_error']);
}

if (isset($_POST['login'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $conn->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (!empty($user['oauth_provider'])) {
                $error = 'บัญชีนี้สมัครผ่าน ' . ucfirst($user['oauth_provider']) . ' กรุณาเข้าสู่ระบบด้วย ' . ucfirst($user['oauth_provider']) . ' ค่ะ';
            } elseif (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $update_login = $conn->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
                $update_login->execute([$user['id']]);

                $_SESSION['user_login'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit();
            } else {
                $error = 'ชื่อผู้ใช้ หรือรหัสผ่านไม่ถูกต้องค่ะ';
            }
        } else {
            $error = 'ชื่อผู้ใช้ หรือรหัสผ่านไม่ถูกต้องค่ะ';
        }
    }
}

$current_redirect_debug = [
    'facebook_redirect_uri' => FACEBOOK_REDIRECT_URL,
    'google_redirect_uri' => GOOGLE_REDIRECT_URL,
    'generated_facebook_oauth_url' => $facebook_login_url,
    'generated_google_oauth_url' => $google_login_url,
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ฐานข้อมูลสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen relative overflow-hidden">

    <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/leaf.png')] opacity-10 pointer-events-none"></div>

    <div class="bg-white p-8 md:p-10 rounded-3xl shadow-xl w-full max-w-md relative z-10 border border-green-100">
        <div class="text-center mb-8">
            <a href="index.php" class="inline-block text-3xl text-green-800 font-bold tracking-tight mb-2">🌿 LoeiHerb</a>
            <h2 class="text-xl text-gray-600">เข้าสู่ระบบ</h2>
            <p class="text-sm text-gray-500">ล็อกอินด้วยบัญชีเว็บไซต์หรือ Google/Facebook</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-600 p-3 rounded text-sm mb-6"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
            <div>
                <input type="text" name="username" required placeholder="ชื่อผู้ใช้งาน หรือ อีเมล" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition bg-gray-50 focus:bg-white">
            </div>
            <div>
                <input type="password" name="password" required placeholder="รหัสผ่าน" class="w-full px-4 py-3 rounded-xl border border-gray-200 outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition bg-gray-50 focus:bg-white">
            </div>
            <button type="submit" name="login" class="w-full bg-green-600 text-white py-3 rounded-xl hover:bg-green-700 font-bold shadow-md hover:shadow-lg transition duration-200">เข้าสู่ระบบ</button>
        </form>

        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">หรือเข้าสู่ระบบด้วย</span>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-3">
                <?php if ($google_configured): ?>
                    <a href="<?php echo $google_login_url; ?>" class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition">
                        <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="h-5 w-5 mr-2" alt="Google">
                        Google
                    </a>
                <?php else: ?>
                    <div class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-xl text-gray-500 bg-gray-100">Google ไม่พร้อมใช้งาน</div>
                <?php endif; ?>

                <?php if ($facebook_configured): ?>
                    <a href="<?php echo $facebook_login_url; ?>" class="w-full flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-[#1877F2] hover:bg-[#166FE5] transition">
                        <img src="https://www.svgrepo.com/show/475647/facebook-color.svg" class="h-5 w-5 mr-2" alt="Facebook">
                        Facebook
                    </a>
                <?php else: ?>
                    <div class="w-full flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-xl text-white bg-[#9bb7e5]">Facebook ไม่พร้อมใช้งาน</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-6 text-center text-sm text-gray-500 flex flex-col space-y-3">
            <div>ยังไม่มีบัญชี? <a href="register.php" class="text-green-600 hover:text-green-800 font-bold underline">สมัครสมาชิก</a></div>
            <a href="contact.php" class="text-gray-400 hover:text-gray-600 text-xs">ติดต่อผู้ดูแลระบบกรณีลืมรหัสผ่าน</a>
            <a href="index.php" class="text-gray-400 hover:text-gray-600 inline-block mt-2">← กลับไปหน้าเว็บไซต์</a>
        </div>

        <?php if (isset($_GET['debug_oauth'])): ?>
            <div class="mt-6 p-4 border border-yellow-200 bg-yellow-50 text-sm rounded-lg text-gray-700">
                <div class="font-semibold mb-2">OAuth Debug</div>
                <div><strong>Facebook Redirect URI:</strong><br><?php echo htmlspecialchars($current_redirect_debug['facebook_redirect_uri']); ?></div>
                <div class="mt-2"><strong>Google Redirect URI:</strong><br><?php echo htmlspecialchars($current_redirect_debug['google_redirect_uri']); ?></div>
                <div class="mt-2"><strong>Facebook OAuth URL:</strong><br><code class="break-words text-xs"><?php echo htmlspecialchars($current_redirect_debug['generated_facebook_oauth_url']); ?></code></div>
                <div class="mt-2"><strong>Google OAuth URL:</strong><br><code class="break-words text-xs"><?php echo htmlspecialchars($current_redirect_debug['generated_google_oauth_url']); ?></code></div>
                <div class="mt-3 text-xs text-gray-500">เพิ่ม URL ที่แสดงข้างต้นใน Facebook/Google OAuth redirect URI ของแอปคุณ</div>
            </div>
        <?php endif; ?>
    </div>

    <script>
      window.fbAsyncInit = function() {
        FB.init({
          appId      : '<?php echo FACEBOOK_APP_ID; ?>',
          cookie     : true,
          xfbml      : true,
          version    : 'v19.0'
        });

        FB.AppEvents.logPageView();
      };

      (function(d, s, id){
         var js, fjs = d.getElementsByTagName(s)[0];
         if (d.getElementById(id)) {return;}
         js = d.createElement(s); js.id = id;
         js.src = "https://connect.facebook.net/en_US/sdk.js";
         fjs.parentNode.insertBefore(js, fjs);
       }(document, 'script', 'facebook-jssdk'));
    </script>

</body>
</html>