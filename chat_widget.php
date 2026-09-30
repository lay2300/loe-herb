<?php
// รองรับการเรียกไฟล์นี้โดยตรงผ่าน AJAX (ตรวจสอบและเชื่อมต่อ DB หากยังไม่มี)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn) && file_exists(__DIR__ . '/config/db.php')) {
    require_once __DIR__ . '/config/db.php';
}

// จะแสดงกล่องแชทก็ต่อเมื่อล็อกอินแล้ว และมีการเชื่อมต่อฐานข้อมูลอยู่
if (isset($_SESSION['user_login']) && isset($conn)) {
    $widget_user_id = $_SESSION['user_id'];
    // Ensure notifications schema
    if (file_exists(__DIR__ . '/config/schema_helpers.php')) {
        require_once __DIR__ . '/config/schema_helpers.php';
        if (file_exists(__DIR__ . '/config/app_log.php')) require_once __DIR__ . '/config/app_log.php';
        try { ensure_notifications_columns($conn); } catch (Exception $e) { if (function_exists('app_log')) app_log('chat_widget ensure_notifications_columns failed: ' . $e->getMessage()); }
    }
    
    // จัดการอัปเดตสถานะกำลังพิมพ์
    if (isset($_POST['update_typing_widget'])) {
        $is_typing = (int)$_POST['is_typing'];
        $conn->prepare("INSERT INTO chat_status (user_id, user_typing) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_typing = ?, last_active = NOW()")->execute([$widget_user_id, $is_typing, $is_typing]);
        exit();
    }

    // จัดการเมื่อมีการกดส่งข้อความจาก Widget
    if (isset($_POST['send_chat_widget'])) {
        try {
        $chat_msg = trim($_POST['chat_message']);
        $image_path = null;
        if (isset($_FILES['chat_image']) && $_FILES['chat_image']['error'] == 0) {
            $ext = pathinfo($_FILES['chat_image']['name'], PATHINFO_EXTENSION);
            $image_path = "chat_" . $widget_user_id . "_" . time() . "_" . uniqid() . "." . $ext;
            if (!file_exists("uploads/")) mkdir("uploads/", 0777, true);
            move_uploaded_file($_FILES['chat_image']['tmp_name'], "uploads/" . $image_path);
        }

        if (!empty($chat_msg) || $image_path) {
            // อัปเดตข้อความที่แอดมินส่งมาให้กลายเป็นอ่านแล้วด้วย
            $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND sender = 'admin'")->execute([$widget_user_id]);
            
            // บันทึกข้อความใหม่
            try {
                $ins = $conn->prepare("INSERT INTO notifications (user_id, sender, message, image_path) VALUES (?, 'user', ?, ?)");
                $ins->execute([$widget_user_id, $chat_msg, $image_path]);
                $new_id = $conn->lastInsertId();
            } catch (Exception $e) {
                if (function_exists('app_log')) app_log('chat_widget INSERT notifications failed: ' . $e->getMessage());
                if (isset($_POST['ajax'])) { if (ob_get_length()) ob_clean(); header('Content-Type: application/json'); echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit(); }
            }
            
            $conn->prepare("UPDATE chat_status SET user_typing = 0 WHERE user_id = ?")->execute([$widget_user_id]);

            // ถ้าเป็นการส่งแบบเบื้องหลัง (AJAX) ให้หยุดการทำงานตรงนี้ เพื่อไม่ให้หน้าเว็บรีเฟรช
            if (isset($_POST['ajax'])) {
                if (ob_get_length()) ob_clean(); // ล้างโค้ด HTML เก่าที่อาจติดมา
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'id' => $new_id]);
                exit();
            }

        }
        } catch (Exception $e) {
            if (isset($_POST['ajax'])) {
                if (ob_get_length()) ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit();
            }
        }
        if (isset($_POST['ajax'])) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error']);
            exit();
        }

            // ใช้ Post/Redirect/Get สำหรับกรณีที่ JavaScript ไม่ได้ดักฟอร์มไว้
            $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
            $redirect_parts = parse_url($redirect_url);
            if (($redirect_parts['host'] ?? $_SERVER['HTTP_HOST']) !== $_SERVER['HTTP_HOST']) {
                $redirect_url = 'index.php';
            }
            header('Location: ' . $redirect_url);
            exit();
    }

    // สำหรับการดึงข้อความใหม่ (AJAX Polling)
    if (isset($_POST['fetch_chat_widget_updates'])) {
        $last_id = isset($_POST['last_id']) ? (int)$_POST['last_id'] : 0;
        
        $stmt_new = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND id > ? ORDER BY id ASC");
        $stmt_new->execute([$widget_user_id, $last_id]);
        $new_msgs = $stmt_new->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_un = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
        $stmt_un->execute([$widget_user_id]);
        $unread = $stmt_un->fetchColumn();
        
        $stmt_typing = $conn->prepare("SELECT admin_typing FROM chat_status WHERE user_id = ? AND last_active > NOW() - INTERVAL 10 SECOND");
        $stmt_typing->execute([$widget_user_id]);
        $admin_typing = $stmt_typing->fetchColumn() ?: 0;

        if (ob_get_length()) ob_clean(); // ล้างโค้ด HTML เก่าที่อาจติดมา
        header('Content-Type: application/json');
        echo json_encode(['messages' => $new_msgs, 'unread' => $unread, 'admin_typing' => $admin_typing]);
        exit();
    }

    // ตรวจสอบว่าควรเปิดกล่องแชทค้างไว้ไหม (หลังจากกดส่ง)
    $is_chat_open = false;
    if (isset($_SESSION['chat_open'])) {
        $is_chat_open = true;
        unset($_SESSION['chat_open']);
    }

    // ดึงประวัติแชทมาแสดง
    $stmt_chat = $conn->prepare("SELECT * FROM (SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50) sub ORDER BY created_at ASC");
    $stmt_chat->execute([$widget_user_id]);
    $widget_chats = $stmt_chat->fetchAll(PDO::FETCH_ASSOC);
    
    // นับจำนวนแจ้งเตือนที่ยังไม่ได้อ่าน
    $stmt_unread = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND sender = 'admin' AND is_read = 0");
    $stmt_unread->execute([$widget_user_id]);
    $widget_unread = $stmt_unread->fetchColumn();
?>

<!-- Floating Chat Widget -->
<div id="floating-chat-container" class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-[100] font-sans max-w-[calc(100vw-2rem)]">
    <!-- Chat Box -->
    <div id="chat-box" class="<?php echo $is_chat_open ? '' : 'hidden'; ?> w-full sm:w-[340px] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden mb-4 transition-all origin-bottom-right">
        <div class="bg-green-600 text-white p-4 flex justify-between items-center shadow-md z-10">
            <h3 class="font-bold flex items-center text-sm"><span class="mr-2 text-lg">💬</span> คุยกับผู้ดูแลระบบ</h3>
            <button onclick="toggleChat()" class="hover:text-green-200 transition text-xl font-bold leading-none">&times;</button>
        </div>
        <div class="h-[350px] p-4 overflow-y-auto bg-gray-50 flex flex-col space-y-3" id="widget-chat-messages">
            <?php if(count($widget_chats) > 0): ?>
                <?php foreach($widget_chats as $c): ?>
                    <?php $img_html = $c['image_path'] ? '<img src="uploads/'.htmlspecialchars($c['image_path']).'" class="w-full rounded-lg mb-2 object-cover max-h-40 cursor-pointer" onclick="window.open(this.src, \'_blank\')">' : ''; ?>
                    <?php if($c['sender'] == 'admin'): ?>
                        <div class="self-start max-w-[85%]" data-msg-id="<?php echo $c['id']; ?>">
                            <div class="p-2.5 rounded-2xl rounded-tl-sm bg-white border border-gray-200 text-gray-700 text-sm shadow-sm leading-relaxed">
                                <?php echo $img_html . nl2br(htmlspecialchars($c['message'])); ?>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-1 ml-1"><?php echo date('H:i', strtotime($c['created_at'])); ?></div>
                        </div>
                    <?php else: ?>
                        <div class="self-end max-w-[85%]" data-msg-id="<?php echo $c['id']; ?>">
                            <div class="p-2.5 rounded-2xl rounded-tr-sm bg-green-600 text-white text-sm shadow-sm leading-relaxed">
                                <?php echo $img_html . nl2br(htmlspecialchars($c['message'])); ?>
                            </div>
                            <div class="text-[10px] text-gray-400 mt-1 mr-1 text-right"><?php echo date('H:i', strtotime($c['created_at'])); ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="m-auto text-center text-gray-400 text-sm bg-white px-4 py-2 rounded-xl shadow-sm border border-gray-100">เริ่มต้นพูดคุยสอบถามได้เลยครับ!</div>
            <?php endif; ?>
            <div id="widget-typing-indicator" class="hidden self-start max-w-[85%] mt-2 mb-2">
                <div class="p-2.5 rounded-2xl rounded-tl-sm bg-gray-200 text-gray-500 text-xs italic flex items-center space-x-1 w-fit shadow-sm">
                    <span>แอดมินกำลังพิมพ์</span>
                    <span class="animate-bounce">.</span><span class="animate-bounce" style="animation-delay: 0.1s">.</span><span class="animate-bounce" style="animation-delay: 0.2s">.</span>
                </div>
            </div>
        </div>
        <form id="widget-chat-form" action="chat_widget.php" method="POST" enctype="multipart/form-data" class="p-2 border-t border-gray-200 bg-white flex gap-2 shrink-0 z-10 items-center">
            <label class="cursor-pointer text-gray-400 hover:text-green-600 transition p-1">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                <input type="file" id="widget_chat_image" name="chat_image" accept="image/*" class="hidden">
            </label>
            <div class="flex-1 relative">
                <div id="widget_img_preview_wrap" class="hidden absolute bottom-full left-0 mb-2 p-1 bg-white border border-gray-200 rounded-lg shadow-lg">
                    <img id="widget_img_preview" src="" class="h-16 w-16 object-cover rounded">
                    <button type="button" id="widget_clear_img" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">x</button>
                </div>
                <input type="text" id="widget_chat_input" name="chat_message" autocomplete="off" placeholder="พิมพ์ข้อความ..." class="w-full px-4 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition">
            </div>
            <input type="hidden" name="send_chat_widget" value="1">
            <button type="submit" class="bg-green-600 text-white px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-green-700 transition shadow-sm flex items-center justify-center">ส่ง</button>
        </form>
    </div>
    
    <!-- Floating Button -->
    <button onclick="toggleChat()" class="w-14 h-14 bg-green-600 rounded-full flex items-center justify-center text-white shadow-xl hover:bg-green-700 transition transform hover:scale-105 relative ml-auto">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
        <?php if($widget_unread > 0): ?>
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white shadow-sm"><?php echo $widget_unread; ?></span>
        <?php endif; ?>
    </button>
</div>
<script>
    function toggleChat() { document.getElementById('chat-box').classList.toggle('hidden'); const msgs = document.getElementById('widget-chat-messages'); if(msgs) msgs.scrollTop = msgs.scrollHeight; }
    <?php if($is_chat_open): ?> document.addEventListener('DOMContentLoaded', () => { const msgs = document.getElementById('widget-chat-messages'); if(msgs) msgs.scrollTop = msgs.scrollHeight; }); <?php endif; ?>

    function playDing() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gainNode = ctx.createGain();
            osc.connect(gainNode); gainNode.connect(ctx.destination);
            osc.type = 'sine'; osc.frequency.setValueAtTime(880, ctx.currentTime);
            gainNode.gain.setValueAtTime(0.1, ctx.currentTime);
            osc.start(); gainNode.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.3); osc.stop(ctx.currentTime + 0.3);
        } catch(e) {}
    }

    let lastWidgetMessageId = <?php echo count($widget_chats) > 0 ? end($widget_chats)['id'] : 0; ?>;

    const wInputImg = document.getElementById('widget_chat_image');
    const wImgWrap = document.getElementById('widget_img_preview_wrap');
    const wImgPreview = document.getElementById('widget_img_preview');
    if(wInputImg) {
        wInputImg.addEventListener('change', function() {
            if(this.files && this.files[0]) {
                let reader = new FileReader();
                reader.onload = function(e) { wImgPreview.src = e.target.result; wImgWrap.classList.remove('hidden'); }
                reader.readAsDataURL(this.files[0]);
            }
        });
        document.getElementById('widget_clear_img').addEventListener('click', function() { wInputImg.value = ''; wImgWrap.classList.add('hidden'); });
    }

    let wTypingTimeout;
    let wIsTyping = false;
    const wInputText = document.getElementById('widget_chat_input');
    if(wInputText) {
        wInputText.addEventListener('input', () => {
            if (!wIsTyping) {
                wIsTyping = true;
                let fd = new FormData(); fd.append('update_typing_widget', '1'); fd.append('is_typing', '1');
                fetch('chat_widget.php', {method:'POST', body:fd});
            }
            clearTimeout(wTypingTimeout);
            wTypingTimeout = setTimeout(() => {
                wIsTyping = false;
                let fd2 = new FormData(); fd2.append('update_typing_widget', '1'); fd2.append('is_typing', '0');
                fetch('chat_widget.php', {method:'POST', body:fd2});
            }, 2000);
        });
    }

    // ใช้งาน AJAX เพื่อส่งข้อความโดยไม่ให้หน้าเว็บรีเฟรชหรือเด้งไปหน้าอื่น
    const chatForm = document.getElementById('widget-chat-form');
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const msg = wInputText.value.trim();
            const imgFile = wInputImg.files[0];
            if (!msg && !imgFile) return;

            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            
            const msgsContainer = document.getElementById('widget-chat-messages');
            const placeholder = msgsContainer.querySelector('.m-auto.text-center');
            if (placeholder) placeholder.remove();
            
            const now = new Date();
            const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            const safeMsg = msg.replace(/</g, "&lt;").replace(/>/g, "&gt;");
            let optImgHtml = imgFile ? `<img src="${wImgPreview.src}" class="w-full rounded-lg mb-2 object-cover max-h-40 opacity-70">` : '';
            
            // กำหนด id ชั่วคราวให้กล่องข้อความที่เพิ่งส่ง
            const tempId = 'temp-' + Date.now();
            const msgHTML = `
                <div class="self-end max-w-[85%] widget-msg-temp opacity-60" id="${tempId}">
                    <div class="p-2.5 rounded-2xl rounded-tr-sm bg-green-600 text-white text-sm shadow-sm leading-relaxed">
                        ${optImgHtml}${safeMsg.replace(/\n/g, "<br>")}
                    </div>
                    <div class="text-[10px] text-gray-400 mt-1 mr-1 text-right">${timeStr} <span class="text-green-500 font-bold">ส่ง...</span></div>
                </div>
            `;
            const typingInd = document.getElementById('widget-typing-indicator');
            if(typingInd) msgsContainer.insertBefore(document.createRange().createContextualFragment(msgHTML), typingInd);
            else msgsContainer.insertAdjacentHTML('beforeend', msgHTML);
            msgsContainer.scrollTop = msgsContainer.scrollHeight;
            
            const formData = new FormData(this);
            formData.append('ajax', '1');
            
            wInputText.value = ''; wInputImg.value = ''; wImgWrap.classList.add('hidden');
            
            fetch('chat_widget.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success' && data.id) {
                    lastWidgetMessageId = Math.max(lastWidgetMessageId, data.id);
                    const tempEl = document.getElementById(tempId);
                    if(tempEl) {
                        tempEl.classList.remove('widget-msg-temp', 'opacity-60');
                        tempEl.removeAttribute('id');
                        tempEl.setAttribute('data-msg-id', data.id);
                        const timeEl = tempEl.querySelector('.text-right');
                        if(timeEl) timeEl.innerHTML = timeStr; // ลบคำว่า ส่ง...
                    }
                } else {
                    const tempEl = document.getElementById(tempId); if(tempEl) tempEl.remove();
                }
            })
            .catch(err => { const tempEl = document.getElementById(tempId); if(tempEl) tempEl.remove(); })
            .finally(() => { submitBtn.disabled = false; submitBtn.classList.remove('opacity-50', 'cursor-not-allowed'); });
        });
    }

    // ระบบ Polling ดึงข้อความใหม่ทุก 3 วินาที
    setInterval(() => {
        const formData = new FormData();
        formData.append('fetch_chat_widget_updates', '1');
        formData.append('last_id', lastWidgetMessageId);
        
        fetch('chat_widget.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                const msgsContainer = document.getElementById('widget-chat-messages');
                const placeholder = msgsContainer.querySelector('.m-auto.text-center');
                if (placeholder) placeholder.remove();

                let hasNewAdminMsg = false;
                data.messages.forEach(msg => {
                    if (msg.id > lastWidgetMessageId) lastWidgetMessageId = msg.id;
                    if (document.querySelector(`[data-msg-id="${msg.id}"]`)) return; // กันข้อความซ้ำ
                    
                    const timeStr = msg.created_at.substring(11, 16);
                    const safeMsg = msg.message.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>");
                    let msgImg = msg.image_path ? `<img src="uploads/${msg.image_path}" class="w-full rounded-lg mb-2 object-cover max-h-40 cursor-pointer" onclick="window.open(this.src, '_blank')">` : '';
                    
                    let msgHTML = '';
                    if (msg.sender === 'admin') {
                        hasNewAdminMsg = true;
                        msgHTML = `<div class="self-start max-w-[85%]" data-msg-id="${msg.id}"><div class="p-2.5 rounded-2xl rounded-tl-sm bg-white border border-gray-200 text-gray-700 text-sm shadow-sm leading-relaxed">${msgImg}${safeMsg}</div><div class="text-[10px] text-gray-400 mt-1 ml-1">${timeStr}</div></div>`;
                    } else {
                        msgHTML = `<div class="self-end max-w-[85%]" data-msg-id="${msg.id}"><div class="p-2.5 rounded-2xl rounded-tr-sm bg-green-600 text-white text-sm shadow-sm leading-relaxed">${msgImg}${safeMsg}</div><div class="text-[10px] text-gray-400 mt-1 mr-1 text-right">${timeStr}</div></div>`;
                    }
                    const typingInd = document.getElementById('widget-typing-indicator');
                    if(typingInd) msgsContainer.insertBefore(document.createRange().createContextualFragment(msgHTML), typingInd);
                    else msgsContainer.insertAdjacentHTML('beforeend', msgHTML);
                });
                msgsContainer.scrollTop = msgsContainer.scrollHeight;
                
                if (hasNewAdminMsg && document.getElementById('chat-box').classList.contains('hidden')) playDing();
            }
            
            const typingInd = document.getElementById('widget-typing-indicator');
            if (data.admin_typing == 1) {
                if(typingInd) { typingInd.classList.remove('hidden'); msgsContainer.appendChild(typingInd); msgsContainer.scrollTop = msgsContainer.scrollHeight; }
            } else { if(typingInd) typingInd.classList.add('hidden'); }

            // อัปเดตจุดแดงแจ้งเตือน (Badge)
            const btn = document.querySelector('button[onclick="toggleChat()"]');
            if (!btn) return;
            const badge = btn.querySelector('.bg-red-500');
            if (data.unread > 0) {
                if (badge) badge.textContent = data.unread;
                else btn.insertAdjacentHTML('beforeend', `<span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white shadow-sm">${data.unread}</span>`);
            } else if (badge) badge.remove();
        }).catch(err => {});
    }, 3000);
</script>
<?php } ?>