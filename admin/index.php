<?php
require_once 'admin_bootstrap.php'; // ไฟล์นี้จะจัดการ session, db connection, และข้อมูลส่วนกลาง

// ตรวจสอบการค้นหา
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // จำนวนรายการต่อหน้า
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . str_replace(' ', '%', $search) . '%';

try {
    // 1. ข้อมูลสำหรับ Dashboard (กราฟและสรุปยอด)
    $sql_global = "SELECT COUNT(*) FROM herbs";
    $dashboard_total = $conn->query($sql_global)->fetchColumn();

    // กราฟ 1: แยกตามอำเภอ
    $sql_chart = "SELECT location_found, COUNT(*) as count FROM herbs WHERE location_found != '' GROUP BY location_found ORDER BY count DESC LIMIT 10";
    $chart_result = $conn->query($sql_chart)->fetchAll(PDO::FETCH_ASSOC);
    $chart_labels = [];
    $chart_data = [];
    foreach ($chart_result as $row) {
        $chart_labels[] = "อ." . $row['location_found'];
        $chart_data[] = $row['count'];
    }

    // กราฟ 2: แยกตามหมวดหมู่ (เพิ่มใหม่)
    $sql_cat = "SELECT category, COUNT(*) as count FROM herbs WHERE category != '' GROUP BY category ORDER BY count DESC";
    $cat_result = $conn->query($sql_cat)->fetchAll(PDO::FETCH_ASSOC);
    $cat_labels = [];
    $cat_data = [];
    foreach ($cat_result as $row) {
        $cat_labels[] = $row['category'];
        $cat_data[] = $row['count'];
    }

    // 2. ข้อมูลสำหรับตาราง (Search & Pagination)
    $sql_count = "SELECT COUNT(*) FROM herbs 
                  WHERE thai_name LIKE :s 
                  OR local_name LIKE :s 
                  OR sci_name LIKE :s 
                  OR other_names LIKE :s 
                  OR properties LIKE :s";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->execute(['s' => $search_param]);
    $total_items = $stmt_count->fetchColumn();
    $total_pages = ceil($total_items / $limit);

    // ดึงข้อมูลตามหน้า
    $sql = "SELECT * FROM herbs 
            WHERE thai_name LIKE :s 
            OR local_name LIKE :s 
            OR sci_name LIKE :s 
            OR other_names LIKE :s 
            OR properties LIKE :s
            ORDER BY id DESC 
            LIMIT :limit OFFSET :offset";



    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':s', $search_param, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $herbs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Dashboard - จัดการสมุนไพรจังหวัดเลย</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel='stylesheet' href='https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">

    <div class="flex flex-col md:flex-row min-h-screen">
        <aside class="admin-sidebar w-full md:w-64 bg-green-900 p-6 text-white">
            <h1 class="text-2xl font-bold mb-8 text-white flex items-center">🌿 LoeiHerb <span class="text-xs bg-green-800 text-green-100 px-2 py-1 rounded ml-2">Admin</span></h1>
            <nav class="space-y-2">
                <a href="index.php" class="block py-2.5 px-4 rounded-xl bg-green-800 text-white font-semibold shadow-sm"><i class="fi fi-rr-document-signed"></i> รายการสมุนไพร</a>
                <a href="articles.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-document"></i> จัดการบทความ</a>
                <a href="add.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-add"></i> เพิ่มสมุนไพรใหม่</a>
                <a href="users.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-users"></i> ข้อมูลผู้ใช้งาน</a>
                <a href="chats.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition relative"><i class="fi fi-rr-comment"></i> ศูนย์ข้อความ (แชท) <?php if($total_admin_unread > 0): ?><span class="absolute right-4 top-3 bg-red-500 text-white text-[10px] w-5 h-5 flex items-center justify-center font-bold rounded-full border border-green-800"><?php echo $total_admin_unread; ?></span><?php endif; ?></a>
                <a href="../index.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition mt-6 border-t border-green-800 pt-6"><i class="fi fi-rr-globe"></i> ไปที่หน้าเว็บหลัก</a>
                <a href="change_password.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-green-800 hover:text-white transition"><i class="fi fi-rr-key"></i> เปลี่ยนรหัสผ่าน</a>
                <a href="logout.php" class="admin-nav-link block py-2.5 px-4 rounded-xl text-green-100 hover:bg-red-600 hover:text-white transition"><i class="fi fi-rr-exit"></i> ออกจากระบบ</a>
            </nav>
        </aside>

        <main class="flex-1 p-4 md:p-8">
            
            <!-- Welcome Banner -->
            <div class="animate-slide-up bg-gradient-to-r from-green-800 to-emerald-600 rounded-3xl p-8 shadow-lg mb-8 text-white relative overflow-hidden">
                <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/leaf.png')] opacity-20"></div>
                <div class="relative z-10 flex flex-col md:flex-row justify-between items-center">
                    <div>
                        <h2 class="text-3xl font-bold mb-2">สวัสดีครับ แอดมิน 👋</h2>
                        <p class="text-green-100">ยินดีต้อนรับสู่ระบบจัดการฐานข้อมูลสมุนไพรจังหวัดเลย</p>
                    </div>
                    <div class="mt-4 md:mt-0 text-right">
                        <p class="text-sm text-green-200">วันที่ปัจจุบัน</p>
                        <p class="text-xl font-semibold">
                            <?php 
                            $thai_months = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
                            echo date('d') . ' ' . $thai_months[(int)date('m')] . ' ' . (date('Y') + 543);
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Summary Section -->
            <div class="animate-slide-up delay-100 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Card: Total Herbs -->
                <div class="admin-card bg-white rounded-2xl p-6 border border-gray-200 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">สมุนไพรทั้งหมด</p>
                        <h3 class="text-3xl font-bold text-green-700 mt-1"><?php echo $dashboard_total; ?></h3>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full text-green-600">
                        <i class="fi fi-rr-list text-2xl"></i>
                    </div>
                </div>

                <!-- Card: Total Categories -->
                <div class="admin-card bg-white rounded-2xl p-6 border border-gray-200 flex items-center justify-between shadow-sm">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">หมวดหมู่</p>
                        <h3 class="text-3xl font-bold text-blue-700 mt-1"><?php echo count($cat_result); ?></h3>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full text-blue-600">
                        <i class="fi fi-rr-apps text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="animate-slide-up delay-200 grid grid-cols-1 lg:grid-cols-2 gap-6 mb-10">
                <!-- Chart: Location -->
                <div class="admin-card bg-white rounded-2xl p-6 border border-gray-200 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <span class="w-1.5 h-6 bg-green-500 rounded-full mr-3"></span>
                        สถิติแยกตามอำเภอ
                    </h3>
                    <div class="h-80">
                        <canvas id="herbChart"></canvas>
                    </div>
                </div>

                <!-- Chart: Category -->
                <div class="admin-card bg-white rounded-2xl p-6 border border-gray-200 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                        <span class="w-1.5 h-6 bg-blue-500 rounded-full mr-3"></span>
                        สัดส่วนหมวดหมู่สมุนไพร
                    </h3>
                    <div class="h-80 flex justify-center relative">
                        <canvas id="catChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="animate-slide-up delay-300 flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">จัดการข้อมูลสมุนไพร</h2>
                    <p class="text-gray-500 text-sm mt-1">รายการสมุนไพรทั้งหมดในระบบ</p>
                </div>
                <div class="flex items-center space-x-4">
                    <form action="index.php" method="GET">
                        <input type="text" name="search" placeholder="ค้นหา..." value="<?php echo htmlspecialchars($search); ?>"
                               class="py-2 px-4 rounded-lg border text-sm">
                        <input type="hidden" name="page" value="1">
                        <button type="submit" class="bg-green-600 text-white rounded-lg px-4 py-2 text-sm hover:bg-green-700 transition">ค้นหา</button>
                    </form>
                    <a href="export.php" class="bg-yellow-500 text-white rounded-lg px-4 py-2 text-sm hover:bg-yellow-600 transition flex items-center shadow-sm">
                        <i class="fi fi-rr-download mr-1"></i>
                        Export CSV
                    </a>
                    <div class="bg-white px-4 py-2 rounded-lg shadow-sm border text-sm font-medium text-gray-600">
                        รวมทั้งหมด <span class="text-green-600 font-bold text-lg ml-1"><?php echo $total_items; ?></span> รายการ
                    </div>
                </div>

            </div>

            <div class="animate-slide-up delay-400 table-container overflow-x-auto mb-8">

                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="p-4 font-semibold text-gray-500 text-sm">รูปภาพ</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm">ชื่อสมุนไพร</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm">พื้นที่ที่พบ</th>
                            <th class="p-4 font-semibold text-gray-500 text-sm text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php if (count($herbs) > 0): ?>
                            <?php foreach ($herbs as $row): ?>
                            <tr class="hover:bg-gray-50 transition duration-150">
                                <td class="p-4">
                                    <?php if ($row['image_path']): ?>
                                        <img src="../uploads/<?php echo $row['image_path']; ?>" class="w-16 h-16 object-cover rounded-xl shadow-sm border border-gray-100">
                                    <?php else: ?>
                                        <div class="w-16 h-16 bg-gray-100 rounded-xl flex items-center justify-center text-xs text-gray-400 border border-gray-200">No Img</div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-gray-800 text-lg"><?php echo $row['thai_name']; ?></div>
                                    <div class="text-sm text-gray-500 italic"><?php echo $row['sci_name']; ?></div>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <?php echo $row['location_found']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center space-x-3">
                                    <a href="edit.php?id=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-sm transition"><i class="fi fi-rr-edit"></i> แก้ไข</a>
                                    <a href="delete.php?id=<?php echo $row['id']; ?>" 
                                       onclick="return confirm('ยืนยันการลบข้อมูลนี้ไหมคะ?')" 
                                       class="text-red-600 hover:text-red-800 font-medium text-sm transition"><i class="fi fi-rr-trash"></i> ลบ</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-gray-400 italic">ยังไม่มีข้อมูลสมุนไพรในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <!-- ปุ่มย้อนกลับ -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-l-md px-3 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center rounded-l-md px-3 py-2 text-gray-300 ring-1 ring-inset ring-gray-300 cursor-not-allowed">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    <?php endif; ?>

                    <!-- เลขหน้า -->
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php 
                        $activeClass = "relative z-10 inline-flex items-center bg-green-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600";
                        $inactiveClass = "relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0";
                        ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                           class="<?php echo $i == $page ? $activeClass : $inactiveClass; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- ปุ่มถัดไป -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" class="relative inline-flex items-center rounded-r-md px-3 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="relative inline-flex items-center rounded-r-md px-3 py-2 text-gray-300 ring-1 ring-inset ring-gray-300 cursor-not-allowed">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // ตั้งค่า Font ให้ Chart.js
        Chart.defaults.font.family = "'Sarabun', sans-serif";
        Chart.defaults.color = '#6b7280';

        // 1. กราฟแท่ง (Bar Chart) - แยกตามอำเภอ
        const ctx = document.getElementById('herbChart').getContext('2d');
        
        // สร้าง Gradient สีเขียว
        const gradientGreen = ctx.createLinearGradient(0, 0, 0, 400);
        gradientGreen.addColorStop(0, 'rgba(20, 83, 45, 0.9)'); // Green-900 (Darker)
        gradientGreen.addColorStop(1, 'rgba(20, 83, 45, 0.2)');

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'จำนวนสมุนไพร (รายการ)',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: gradientGreen,
                    borderColor: '#14532d', // Green-900
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 },
                        grid: { borderDash: [2, 4], color: '#f3f4f6' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 2. กราฟวงกลม (Doughnut Chart) - แยกตามหมวดหมู่
        const ctxCat = document.getElementById('catChart').getContext('2d');
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($cat_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($cat_data); ?>,
                    backgroundColor: [
                        '#14532d', '#166534', '#15803d', '#16a34a', 
                        '#22c55e', '#4ade80', '#86efac', '#bbf7d0'
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { usePointStyle: true, padding: 20 }
                    }
                },
                cutout: '70%'
            }
        });
    </script>

</body>
</html>