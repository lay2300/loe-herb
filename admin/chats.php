<?php
session_start();
if (!isset($_SESSION['admin_login'])) { header("Location: login.php"); exit(); }
require_once '../config/db.php';
require_once '../config/app_log.php';
require_once '../config/schema_helpers.php';
try { ensure_notifications_columns($conn); } catch (Exception $e) { app_log('admin/chats.php ensure_notifications_columns failed: ' . $e->getMessage()); }

$active_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ---------------- AJAX & REQUEST HANDLING ----------------
if (isset($_POST['update_typing_admin']) && $active_id) {
    $is_typing = (int)$_POST['is_typing'];
    $conn->prepare("INSERT INTO chat_status (user_id, admin_typing) VALUES (?, ?) ON DUPLICATE KEY UPDATE admin_typing = ?, last_active = NOW()")->execute([$active_id, $is_typing, $is_typing]);
    exit();
}

if (isset($_POST['send_message_admin']) && $active_id) {
    try {
    $message = trim($_POST['message']);
    $image_path = null;
    if (isset($_FILES['chat_image']) && $_FILES['chat_image']['error'] == 0) {
        $ext = pathinfo($_FILES['chat_image']['name'], PATHINFO_EXTENSION);
        $image_path = "admin_chat_" . $active_id . "_" . time() . "_" . uniqid() . "." . $ext;
        if (!file_exists("../uploads/")) mkdir("../uploads/", 0777, true);
        move_uploaded_file($_FILES['chat_image']['tmp_name'], "../uploads/" . $image_path);
    }
    
    if (!empty($message) || $image_path) {
        $insert = $conn->prepare("INSERT INTO notifications (user_id, sender, message, image_path) VALUES (?, 'admin', ?, ?)");
        if ($insert->execute([$active_id, $message, $image_path])) {
            $new_id = $conn->lastInsertId();
            $conn->prepare("UPDATE chat_status SET admin_typing = 0 WHERE user_id = ?")->execute([$active_id]);
            if (isset($_POST['ajax'])) { if(ob_get_length()) ob_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'success', 'id' => $new_id]); exit(); }
        }
    }
    } catch (Exception $e) {}
    if (isset($_POST['ajax'])) { if(ob_get_length()) ob_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error']); exit(); }
    header("Location: chats.php?id=" . $active_id); exit();
}

if (isset($_POST['fetch_admin_chat'])) {
    try {
    $last_id = isset($_POST['last_id']) ? (int)$_POST['last_id'] : 0;
    $res = ['status' => 'success', 'messages' => [], 'user_typing' => 0, 'chat_list' => []];
    
    if ($active_id > 0) {
        $stmt_new = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND id > ? ORDER BY id ASC");
        $stmt_new->execute([$active_id, $last_id]);
        $res['messages'] = $stmt_new->fetchAll(PDO::FETCH_ASSOC);
        
        $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND sender = 'user' AND is_read = 0")->execute([$active_id]);
        
        $stmt_typing = $conn->prepare("SELECT user_typing FROM chat_status WHERE user_id = ? AND last_active > NOW() - INTERVAL 10 SECOND");
        $stmt_typing->execute([$active_id]);
        $res['user_typing'] = $stmt_typing->fetchColumn() ?: 0;
    }

    // หาว่าใครมีข้อความเข้ามาใหม่บ้างเพื่อโชว์จุดแดง
    $res['chat_list'] = $conn->query("SELECT user_id, COUNT(*) as unread FROM notifications WHERE sender = 'user' AND is_read = 0 GROUP BY user_id")->fetchAll(PDO::FETCH_ASSOC);

    if(ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode($res);
    } catch(Exception $e) { if(ob_get_length()) ob_clean(); header('Content-Type: application/json'); echo json_encode(['status' => 'error']); }
    exit();
}
// ---------------------------------------------------------

// ดึงรายชื่อผู้ใช้ทั้งหมด (เรียงคนที่มีแจ้งเตือนก่อน)
$sql_users = "SELECT u.id, u.username, u.avatar, 
            (SELECT COUNT(*) FROM notifications n WHERE n.user_id = u.id AND n.sender = 'user' AND n.is_read = 0) as unread_count,
            (SELECT MAX(created_at) FROM notifications n WHERE n.user_id = u.id) as last_msg_time
            FROM users u 
            ORDER BY unread_count DESC, last_msg_time DESC, u.created_at DESC";
$chat_users = $conn->query($sql_users)->fetchAll(PDO::FETCH_ASSOC);

$active_user = null;
$history = [];
if ($active_id > 0) {
    $stmt_active = $conn->prepare("SELECT username, avatar FROM users WHERE id = ?");
    $stmt_active->execute([$active_id]);
    $active_user = $stmt_active->fetch(PDO::FETCH_ASSOC);
    
    if ($active_user) {
        $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND sender = 'user' AND is_read = 0")->execute([$active_id]);
        $stmt_hist = $conn->prepare("SELECT * FROM (SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50) sub ORDER BY created_at ASC");
        $stmt_hist->execute([$active_id]);
        $history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
    }
}

$total_admin_unread = 0;
try { $total_admin_unread = $conn->query("SELECT COUNT(*) FROM notifications WHERE sender = 'user' AND is_read = 0")->fetchColumn(); } catch (PDOException $e) {}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>ศูนย์รวมการแชท - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-full md:w-64 bg-green-900 p-6 text-white shrink-0 z-20 hidden md:block overflow-y-auto">
        <h1 class="text-2xl font-bold mb-8 text-white flex items-center">🌿 LoeiHerb <span class="text-xs bg-green-800 text-green-100 px-2 py-1 rounded ml-2">Admin</span></h1>
        <nav class="space-y-2">
            <a href="index.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-document-signed"></i> รายการสมุนไพร</a>
            <a href="articles.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-document"></i> จัดการบทความ</a>
            <a href="add.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-add"></i> เพิ่มสมุนไพรใหม่</a>
            <a href="users.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-users"></i> ข้อมูลผู้ใช้งาน</a>
            <a href="chats.php" class="block py-2.5 px-4 rounded-xl bg-green-800 text-white font-semibold shadow-sm relative"><i class="fi fi-rr-comment"></i> ศูนย์ข้อความ (แชท) <?php if($total_admin_unread > 0): ?><span class="absolute right-4 top-3 bg-red-500 text-white text-[10px] w-5 h-5 flex items-center justify-center font-bold rounded-full border border-green-800"><?php echo $total_admin_unread; ?></span><?php endif; ?></a>
            <a href="../index.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition mt-6 border-t border-green-800 pt-6"><i class="fi fi-rr-globe"></i> ไปที่หน้าเว็บหลัก</a>
        </nav>
    </aside>

    <main class="flex-1 flex bg-white border-l border-gray-200">
        <!-- Left: Chat List -->
        <div class="w-full md:w-80 bg-gray-50 border-r border-gray-200 flex flex-col h-full <?php echo $active_id ? 'hidden md:flex' : ''; ?>">
            <div class="p-4 bg-white border-b border-gray-200 shrink-0">
                <h2 class="text-lg font-bold text-gray-800 flex items-center">💬 กล่องข้อความ</h2>
            </div>
            <div class="overflow-y-auto flex-1">
                <?php foreach($chat_users as $u): ?>
                    <a href="chats.php?id=<?php echo $u['id']; ?>" class="flex items-center p-4 border-b border-gray-100 hover:bg-gray-100 transition <?php echo $active_id == $u['id'] ? 'bg-green-50 border-l-4 border-green-600' : 'border-l-4 border-transparent'; ?>">
                        <div class="w-10 h-10 rounded-full bg-green-200 text-green-800 flex items-center justify-center font-bold text-sm shrink-0">
                            <?php echo $u['avatar'] ? '<img src="../uploads/'.$u['avatar'].'" class="w-full h-full rounded-full object-cover">' : mb_substr($u['username'], 0, 1); ?>
                        </div>
                        <div class="ml-3 flex-1 overflow-hidden">
                            <div class="font-bold text-gray-800 text-sm truncate">@<?php echo htmlspecialchars($u['username']); ?></div>
                            <div class="text-xs text-gray-400 mt-0.5 truncate"><?php echo $u['last_msg_time'] ? date('d/m H:i', strtotime($u['last_msg_time'])) : 'ยังไม่มีข้อความ'; ?></div>
                        </div>
                        <?php if($u['unread_count'] > 0): ?>
                            <span id="badge-user-<?php echo $u['id']; ?>" class="bg-red-500 text-white text-[10px] w-5 h-5 flex items-center justify-center font-bold rounded-full ml-2 shadow-sm"><?php echo $u['unread_count']; ?></span>
                        <?php else: ?>
                            <span id="badge-user-<?php echo $u['id']; ?>" class="hidden bg-red-500 text-white text-[10px] w-5 h-5 flex items-center justify-center font-bold rounded-full ml-2 shadow-sm"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right: Chat Box -->
        <div class="flex-1 flex flex-col h-full bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] bg-gray-50 relative <?php echo !$active_id ? 'hidden md:flex items-center justify-center' : ''; ?>">
            <?php if($active_id && $active_user): ?>
                <!-- Header -->
                <div class="p-4 bg-white border-b border-gray-200 flex justify-between items-center shadow-sm shrink-0 z-10">
                    <div class="flex items-center">
                        <a href="chats.php" class="mr-3 md:hidden text-gray-500 hover:text-green-600"><i class="fi fi-rr-arrow-left"></i></a>
                        <div class="w-10 h-10 rounded-full bg-green-200 text-green-800 flex items-center justify-center font-bold text-sm shrink-0 shadow-sm border border-green-100">
                            <?php echo $active_user['avatar'] ? '<img src="../uploads/'.$active_user['avatar'].'" class="w-full h-full rounded-full object-cover">' : mb_substr($active_user['username'], 0, 1); ?>
                        </div>
                        <h2 class="ml-3 font-bold text-gray-800">@<?php echo htmlspecialchars($active_user['username']); ?></h2>
                    </div>
                    <a href="user_edit.php?id=<?php echo $active_id; ?>" class="text-sm bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg transition font-medium"><i class="fi fi-rr-user"></i> ดูข้อมูล</a>
                </div>

                <!-- Messages -->
                <div class="flex-1 overflow-y-auto p-6 space-y-4 flex flex-col" id="chat-container">
                    <?php if(count($history) > 0): ?>
                        <?php foreach($history as $h): ?>
                            <?php $img_html = $h['image_path'] ? '<img src="../uploads/'.htmlspecialchars($h['image_path']).'" class="w-full rounded-lg mb-2 max-h-48 object-cover cursor-pointer shadow-sm" onclick="window.open(this.src, \'_blank\')">' : ''; ?>
                            <?php if($h['sender'] == 'user'): ?>
                                <div class="self-start max-w-[85%] md:max-w-[70%]" data-msg-id="<?php echo $h['id']; ?>">
                                    <div class="flex items-end mb-1">
                                        <span class="bg-green-100 text-green-700 text-[11px] font-bold px-2 py-0.5 rounded-full mr-2 shadow-sm">ผู้ใช้ (@<?php echo htmlspecialchars($active_user['username']); ?>)</span>
                                        <span class="text-[10px] text-gray-400"><?php echo date('d/m H:i', strtotime($h['created_at'])); ?></span>
                                    </div>
                                    <div class="p-3.5 rounded-2xl rounded-tl-sm bg-white border border-gray-200 text-gray-800 text-sm shadow-sm leading-relaxed">
                                        <?php echo $img_html . nl2br(htmlspecialchars($h['message'])); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="self-end max-w-[85%] md:max-w-[70%]" data-msg-id="<?php echo $h['id']; ?>">
                                    <div class="flex items-end justify-end mb-1">
                                        <span class="text-[10px] text-gray-400 mr-2"><?php echo date('d/m H:i', strtotime($h['created_at'])); ?></span>
                                        <span class="bg-yellow-100 text-yellow-800 text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm">คุณ (Admin)</span>
                                    </div>
                                    <div class="p-3.5 rounded-2xl rounded-tr-sm bg-yellow-100 border border-yellow-200 text-gray-800 text-sm shadow-sm leading-relaxed">
                                        <?php echo $img_html . nl2br(htmlspecialchars($h['message'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="m-auto bg-white px-6 py-3 rounded-2xl shadow-sm border border-gray-100 text-gray-500 text-sm text-center">เริ่มบทสนทนาได้เลยครับ 👋</div>
                    <?php endif; ?>
                    
                    <div id="admin-typing-indicator" class="hidden self-start max-w-[85%] mt-2 mb-2">
                        <div class="p-2.5 rounded-2xl rounded-tl-sm bg-white border border-gray-200 text-gray-500 text-xs italic flex items-center space-x-1 w-fit shadow-sm">
                            <span>กำลังพิมพ์</span><span class="animate-bounce">.</span><span class="animate-bounce" style="animation-delay: 0.1s">.</span><span class="animate-bounce" style="animation-delay: 0.2s">.</span>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <form id="admin-chat-form" method="POST" enctype="multipart/form-data" class="p-4 bg-white border-t border-gray-200 shrink-0 flex gap-2 items-center z-10">
                    <label class="cursor-pointer text-gray-400 hover:text-green-600 transition p-2 bg-gray-50 rounded-full">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                        <input type="file" id="admin_chat_image" name="chat_image" accept="image/*" class="hidden">
                    </label>
                    <div class="flex-1 relative">
                        <div id="admin_img_preview_wrap" class="hidden absolute bottom-full left-0 mb-3 p-1.5 bg-white border border-gray-200 rounded-xl shadow-xl">
                            <img id="admin_img_preview" src="" class="h-20 w-20 object-cover rounded-lg border border-gray-100">
                            <button type="button" id="admin_clear_img" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold shadow-md hover:bg-red-600 transition">x</button>
                        </div>
                        <input type="text" id="admin_chat_input" name="message" autocomplete="off" class="w-full px-5 py-3 rounded-full border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition text-sm bg-gray-50 focus:bg-white shadow-inner" placeholder="พิมพ์ข้อความถึงผู้ใช้...">
                    </div>
                    <input type="hidden" name="send_message_admin" value="1">
                    <button type="submit" class="bg-green-600 text-white w-12 h-12 flex justify-center items-center rounded-full font-bold hover:bg-green-700 transition shadow-md hover:shadow-lg transform hover:scale-105">
                        <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center text-gray-400 bg-white p-8 rounded-3xl shadow-sm border border-gray-100">
                    <div class="bg-gray-50 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-4"><i class="fi fi-rr-comment-alt text-4xl text-gray-300"></i></div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">เลือกผู้ใช้เพื่อเริ่มสนทนา</h3>
                    <p class="text-sm">คลิกที่รายชื่อทางด้านซ้ายเพื่อเปิดกล่องข้อความ</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const chatContainer = document.getElementById('chat-container');
        if(chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
        
        function playDing() { try { const ctx = new (window.AudioContext || window.webkitAudioContext)(); const osc = ctx.createOscillator(); const gainNode = ctx.createGain(); osc.connect(gainNode); gainNode.connect(ctx.destination); osc.type = 'sine'; osc.frequency.setValueAtTime(880, ctx.currentTime); gainNode.gain.setValueAtTime(0.1, ctx.currentTime); osc.start(); gainNode.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.3); osc.stop(ctx.currentTime + 0.3); } catch(e) {} }

        let lastAdminMessageId = <?php echo count($history) > 0 ? end($history)['id'] : 0; ?>;
        let activeId = <?php echo $active_id; ?>;

        const aInputImg = document.getElementById('admin_chat_image');
        const aImgWrap = document.getElementById('admin_img_preview_wrap');
        const aImgPreview = document.getElementById('admin_img_preview');
        if(aInputImg) {
            aInputImg.addEventListener('change', function() { if(this.files && this.files[0]) { let reader = new FileReader(); reader.onload = function(e) { aImgPreview.src = e.target.result; aImgWrap.classList.remove('hidden'); }; reader.readAsDataURL(this.files[0]); } });
            document.getElementById('admin_clear_img').addEventListener('click', function() { aInputImg.value = ''; aImgWrap.classList.add('hidden'); });
        }

        let aTypingTimeout; let aIsTyping = false;
        const adminChatForm = document.getElementById('admin-chat-form');
        if (adminChatForm) {
            document.getElementById('admin_chat_input').addEventListener('input', () => { if(!aIsTyping) { aIsTyping = true; let fd = new FormData(); fd.append('update_typing_admin', '1'); fd.append('is_typing', '1'); fetch('chats.php?id='+activeId, {method:'POST', body:fd}); } clearTimeout(aTypingTimeout); aTypingTimeout = setTimeout(() => { aIsTyping = false; let fd2 = new FormData(); fd2.append('update_typing_admin', '1'); fd2.append('is_typing', '0'); fetch('chats.php?id='+activeId, {method:'POST', body:fd2}); }, 2000); });
            adminChatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const input = document.getElementById('admin_chat_input'); const msg = input.value.trim(); const imgFile = aInputImg.files[0];
                if (!msg && !imgFile) return;
                const submitBtn = this.querySelector('button[type="submit"]'); submitBtn.disabled = true; submitBtn.classList.add('opacity-50');
                const placeholder = chatContainer.querySelector('.m-auto.bg-white'); if (placeholder) placeholder.remove();
                const now = new Date(); const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                const safeMsg = msg.replace(/</g, "&lt;").replace(/>/g, "&gt;"); let optImgHtml = imgFile ? `<img src="${aImgPreview.src}" class="w-full rounded-lg mb-2 max-h-48 object-cover opacity-70">` : '';
                const tempId = 'atemp-' + Date.now();
                const msgHTML = `<div class="self-end max-w-[85%] md:max-w-[70%] opacity-60" id="${tempId}"><div class="flex items-end justify-end mb-1"><span class="text-[10px] text-gray-400 mr-2 time-str">${timeStr} <span class="text-green-500 font-bold ml-1">ส่ง...</span></span><span class="bg-yellow-100 text-yellow-800 text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm">คุณ (Admin)</span></div><div class="p-3.5 rounded-2xl rounded-tr-sm bg-yellow-100 border border-yellow-200 text-gray-800 text-sm shadow-sm leading-relaxed">${optImgHtml}${safeMsg.replace(/\n/g, "<br>")}</div></div>`;
                const typingInd = document.getElementById('admin-typing-indicator');
                if(typingInd) chatContainer.insertBefore(document.createRange().createContextualFragment(msgHTML), typingInd); else chatContainer.insertAdjacentHTML('beforeend', msgHTML);
                chatContainer.scrollTop = chatContainer.scrollHeight;
                
                const formData = new FormData(this); formData.append('ajax', '1');
                input.value = ''; aInputImg.value = ''; aImgWrap.classList.add('hidden');
                
                fetch('chats.php?id='+activeId, { method: 'POST', body: formData })
                .then(res => res.json()).then(data => { if (data.status === 'success' && data.id) { lastAdminMessageId = Math.max(lastAdminMessageId, data.id); const tempEl = document.getElementById(tempId); if(tempEl) { tempEl.classList.remove('opacity-60'); tempEl.removeAttribute('id'); tempEl.setAttribute('data-msg-id', data.id); const timeEl = tempEl.querySelector('.time-str'); if(timeEl) timeEl.innerHTML = timeStr; } } else { const tempEl = document.getElementById(tempId); if(tempEl) tempEl.remove(); } })
                .catch(err => { const tempEl = document.getElementById(tempId); if(tempEl) tempEl.remove(); }).finally(() => { submitBtn.disabled = false; submitBtn.classList.remove('opacity-50'); });
            });
        }

        setInterval(() => {
            const formData = new FormData(); formData.append('fetch_admin_chat', '1'); formData.append('last_id', lastAdminMessageId);
            fetch('chats.php?id='+activeId, { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                // Update Active Chat
                if (data.messages && data.messages.length > 0 && chatContainer) {
                    const placeholder = chatContainer.querySelector('.m-auto.bg-white'); if (placeholder) placeholder.remove();
                    let hasNewMsg = false;
                    data.messages.forEach(msg => {
                        if (msg.id > lastAdminMessageId) lastAdminMessageId = msg.id;
                        if (document.querySelector(`[data-msg-id="${msg.id}"]`)) return;
                        const timeStr = msg.created_at.substring(11, 16); const safeMsg = msg.message.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>");
                        let msgImg = msg.image_path ? `<img src="../uploads/${msg.image_path}" class="w-full rounded-lg mb-2 max-h-48 object-cover cursor-pointer shadow-sm" onclick="window.open(this.src, '_blank')">` : '';
                        if(msg.sender === 'user') hasNewMsg = true;
                        let msgHTML = msg.sender === 'user' ? `<div class="self-start max-w-[85%] md:max-w-[70%]" data-msg-id="${msg.id}"><div class="flex items-end mb-1"><span class="bg-green-100 text-green-700 text-[11px] font-bold px-2 py-0.5 rounded-full mr-2 shadow-sm">ผู้ใช้ (@<?php echo $active_user ? htmlspecialchars($active_user['username']) : ''; ?>)</span><span class="text-[10px] text-gray-400">${timeStr}</span></div><div class="p-3.5 rounded-2xl rounded-tl-sm bg-white border border-gray-200 text-gray-800 text-sm shadow-sm leading-relaxed">${msgImg}${safeMsg}</div></div>` : `<div class="self-end max-w-[85%] md:max-w-[70%]" data-msg-id="${msg.id}"><div class="flex items-end justify-end mb-1"><span class="text-[10px] text-gray-400 mr-2">${timeStr}</span><span class="bg-yellow-100 text-yellow-800 text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm">คุณ (Admin)</span></div><div class="p-3.5 rounded-2xl rounded-tr-sm bg-yellow-100 border border-yellow-200 text-gray-800 text-sm shadow-sm leading-relaxed">${msgImg}${safeMsg}</div></div>`;
                        const typingInd = document.getElementById('admin-typing-indicator'); if(typingInd) chatContainer.insertBefore(document.createRange().createContextualFragment(msgHTML), typingInd); else chatContainer.insertAdjacentHTML('beforeend', msgHTML);
                    });
                    chatContainer.scrollTop = chatContainer.scrollHeight; if(hasNewMsg && document.hidden) playDing();
                }
                const typingInd = document.getElementById('admin-typing-indicator');
                if (typingInd) { if (data.user_typing == 1) { typingInd.classList.remove('hidden'); chatContainer.appendChild(typingInd); chatContainer.scrollTop = chatContainer.scrollHeight; } else { typingInd.classList.add('hidden'); } }
                
                // Update Badges List
                if (data.chat_list) {
                    data.chat_list.forEach(item => {
                        let badge = document.getElementById('badge-user-' + item.user_id);
                        if (badge) {
                            if(item.unread > 0 && item.user_id != activeId) { badge.classList.remove('hidden'); badge.textContent = item.unread; } 
                            else { badge.classList.add('hidden'); }
                        }
                    });
                }
            }).catch(err => {});
        }, 3000);
    </script>
</body>
</html>