<?php
// public/admin_user_insight.php
require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// เช็คสิทธิ์ Admin
if (!in_array($user['role'], ['admin', 'manager'])) { header("Location: index.php"); exit; }

$uid = $_GET['uid'] ?? 0;
$year = $_GET['year'] ?? date('Y') + 543;

if (!$uid) { header("Location: admin_dashboard.php"); exit; }

// 1. ข้อมูล User
$userQ = $conn->prepare("SELECT name, role, email FROM users WHERE id = ?");
$userQ->bind_param("i", $uid);
$userQ->execute();
$userData = $userQ->get_result()->fetch_assoc();

if (!$userData) { die("ไม่พบข้อมูลผู้ใช้"); }

// 2. ดึงคะแนน
$sql = "SELECT wc.main_area, SUM(wi.computed_hours) as total 
        FROM workload_items wi 
        JOIN workload_categories wc ON wi.category_id = wc.id 
        WHERE wi.user_id = ? AND wi.status IN ('approved_admin', 'approved_final') 
        GROUP BY wc.main_area";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();

$scores = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0, 6=>0];
while($r = $res->fetch_assoc()) {
    $scores[$r['main_area']] = floatval($r['total']);
}
$totalScore = array_sum($scores);

// 3. Logic Rule-based
$targetRole = $userData['role']; 
$benchmarks = [];
$requiredTotal = 0;

if ($targetRole == 'staff') {
    // --- STAFF ---
    $requiredTotal = 1645;
    $benchmarks = [
        1 => ['target' => 1400, 'name' => 'งานประจำ (Routine)', 'advice' => 'ตรวจสอบภาระงานประจำวัน บันทึกให้ครอบคลุม'],
        2 => ['target' => 50,   'name' => 'พัฒนางาน (Dev)', 'advice' => 'ควรหาคอร์สอบรม หรือทำคู่มือปฏิบัติงาน (KM)'],
        3 => ['target' => 50,   'name' => 'งานยุทธศาสตร์', 'advice' => 'เข้าร่วมโครงการที่ตอบโจทย์กลยุทธ์มหาวิทยาลัย'],
        4 => ['target' => 20,   'name' => 'งานที่ได้รับมอบหมาย', 'advice' => 'บันทึกงานพิเศษที่หัวหน้ามอบหมาย'],
        5 => ['target' => 10,   'name' => 'กิจกรรม/ส่วนร่วม', 'advice' => 'เข้าร่วมกิจกรรมองค์กร/กีฬาบุคลากร'],
        6 => ['target' => 0,    'name' => 'อื่นๆ', 'advice' => '-']
    ];
} else {
    // --- TEACHER ---
    $requiredTotal = 1330;
  
    $benchmarks = [
        1 => ['target' => 300,  'name' => 'การสอน', 'advice' => 'พิจารณาเปิดรายวิชาเลือกเสรีเพิ่ม'],
        2 => ['target' => 600, 'name' => 'วิจัย/วิชาการ', 'advice' => 'ควรขอทุนวิจัยเพิ่ม หรือตีพิมพ์ TCI'],
        3 => ['target' => 100,  'name' => 'บริการวิชาการ', 'advice' => 'เป็นวิทยากร หรือจัดโครงการบริการสังคม'],
        4 => ['target' => 100,   'name' => 'ทำนุบำรุงศิลปฯ', 'advice' => 'เข้าร่วมกิจกรรมวันสำคัญ'],
        5 => ['target' => 100,   'name' => 'บริหาร', 'advice' => '-'],
        6 => ['target' => 130,   'name' => 'อื่นๆ', 'advice' => '-']
    ];
}

// 4. ประมวลผลจุดแข็ง/จุดอ่อน (แก้ให้เก็บ Array ข้อมูล)
$strengths = [];
$weaknesses = []; 

foreach ($benchmarks as $areaID => $criteria) {
    $current = $scores[$areaID] ?? 0;
    $target = $criteria['target'];
    $percent = ($target > 0) ? ($current / $target) * 100 : 100;
    
    // --- จุดแข็ง (เกินเป้า) ---
    if (($target > 0 && $current >= $target) || ($target == 0 && $current > 0)) { 
        $strengths[] = [
            'id' => $areaID, 
            'name' => $criteria['name'],
            'current' => $current, 
            'target' => $target,
            'gap' => $current - $target, // ส่วนเกิน
            'percent' => $percent,
            'advice' => 'ยอดเยี่ยม! รักษามาตรฐานผลงานนี้ไว้', // คำชม
            'type' => 'strength'
        ];
    }

    // --- จุดอ่อน (ต่ำกว่าเป้า) ---
    if ($target > 0 && $current < $target) {
        $weaknesses[] = [
            'id' => $areaID, 
            'name' => $criteria['name'],
            'current' => $current, 
            'target' => $target,
            'gap' => $target - $current, // ส่วนขาด
            'percent' => $percent, 
            'advice' => $criteria['advice'],
            'type' => 'weakness'
        ];
    }
}

// 5. คำแนะนำภาพรวม
$suggestions = [];
if ($totalScore < $requiredTotal) {
    $missing = $requiredTotal - $totalScore;
    $suggestions[] = "<span class='text-danger'>⚠️ ภาระงานรวมยังไม่ถึงเกณฑ์ขั้นต่ำ</span> (ขาดอีก " . number_format($missing) . " คะแนน)";
    if (!empty($weaknesses)) $suggestions[] = "💡 <strong>กลยุทธ์:</strong> ควรเร่งเก็บคะแนนในด้านที่ยังไม่ถึงเป้าหมาย";
} else {
    $suggestions[] = "<span class='text-success'>✅ ภาระงานภาพรวมผ่านเกณฑ์แล้ว</span> (ทำได้ " . number_format($totalScore) . " คะแนน)";
    if (count($weaknesses) > 0) $suggestions[] = "💡 <strong>ข้อเสนอแนะ:</strong> ควรเกลี่ยภาระงานให้ครบทุกด้านตามเกณฑ์ย่อย";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Insight: <?= htmlspecialchars($userData['name']) ?></title>
    <link rel="stylesheet" href="../medui/medui.css">
    <link rel="stylesheet" href="../medui/medui.components.css">
    <link rel="stylesheet" href="../medui/medui.layout.css">
    <link rel="stylesheet" href="../medui/medui.theme.medical.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .insight-card { background: #fff; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); height: 100%; }
        .stat-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 30px; font-size: 0.95rem; margin-right: 8px; margin-bottom: 8px; transition: all 0.2s; cursor: pointer; }
        .stat-pill:hover { transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        .pill-strength { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }
        .pill-weakness { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
        
        .suggestion-box { background: #f0fdf4; border-left: 5px solid #10b981; padding: 20px; border-radius: 8px; }
        .suggestion-box.warning { background: #fff7ed; border-left-color: #f97316; }
        
        /* New AI Section Styles */
        .ai-section {
            margin-top: 30px;
            background: linear-gradient(135deg, #ffffff 0%, #f3f0ff 100%);
            border: 2px solid #e9d5ff; border-radius: 16px; padding: 30px;
            position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(124, 58, 237, 0.1);
        }
        .ai-bg-icon {
            position: absolute; right: -20px; top: -20px; font-size: 8rem; 
            color: rgba(139, 92, 246, 0.08); transform: rotate(15deg);
        }
        .ai-content-box {
            font-size: 1.1rem; line-height: 1.8; color: #374151;
            background: rgba(255,255,255,0.8); backdrop-filter: blur(5px);
            padding: 25px; border-radius: 12px; border: 1px solid rgba(139, 92, 246, 0.2);
        }
        
        /* Modal */
        .progress-bar-bg { background: #eee; height: 10px; border-radius: 5px; width: 100%; margin: 10px 0; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: #ef4444; width: 0%; transition: width 0.5s; }
    </style>
</head>
<body>
<div class="app">
    <?php include '../inc/nav.php'; ?>
    <div class="app-content">
        <header class="topbar">
            <div class="container stack-between">
                <div>
                    <h3 class="m-0 text-primary">📊 ผลวิเคราะห์ศักยภาพ (Insight)</h3>
                    <p class="muted m-0">บุคลากร: <strong><?= htmlspecialchars($userData['name']) ?></strong> (<?= ucfirst($userData['role']) ?>)</p>
                </div>
                <a href="admin_dashboard.php" class="btn btn-outline">กลับ Dashboard</a>
            </div>
        </header>

        <main class="main">
            <div class="container">
                
                <div class="grid grid-2 mb-4" style="gap: 30px; align-items: stretch;">
                    
                    <div class="insight-card">
                        <h4 class="mb-4 text-center">สมดุลภาระงาน</h4>
                        <div style="max-height: 400px; position: relative;">
                            <canvas id="radarChart"></canvas>
                        </div>
                    </div>

                    <div class="insight-card">
                        <h4 class="mb-4">💡 บทวิเคราะห์เบื้องต้น</h4>
                        
                        <div class="mb-4">
                            <strong class="text-success"><i class="bi bi-graph-up-arrow"></i> จุดแข็ง (ผ่านเกณฑ์):</strong>
                            <div class="mt-2">
                                <?php if(!empty($strengths)): foreach($strengths as $s): ?>
                                    <span class="stat-pill pill-strength" onclick='showDetail(<?= json_encode($s) ?>)'>
                                        <i class="bi bi-star-fill"></i> <?= $s['name'] ?>
                                    </span>
                                <?php endforeach; else: ?>
                                    <span class="text-muted small">- ยังไม่มีด้านที่โดดเด่นชัดเจน -</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-4">
                            <strong class="text-danger"><i class="bi bi-exclamation-triangle"></i> จุดที่ควรเสริม (ต่ำกว่าเกณฑ์):</strong>
                            <div class="mt-2">
                                <?php if(!empty($weaknesses)): foreach($weaknesses as $w): ?>
                                    <span class="stat-pill pill-weakness" onclick='showDetail(<?= json_encode($w) ?>)'>
                                        <?= $w['name'] ?> <i class="bi bi-info-circle"></i>
                                    </span>
                                <?php endforeach; else: ?>
                                    <span class="text-success small"><i class="bi bi-check-lg"></i> ครบถ้วนตามเกณฑ์ทุกด้าน</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="suggestion-box <?= ($totalScore < $requiredTotal) ? 'warning' : '' ?>">
                            <h5 class="m-0 mb-2 font-bold">🤖 ระบบแนะนำ (Auto Suggestion):</h5>
                            <ul class="pl-4 mb-0" style="line-height: 1.6;">
                                <?php foreach($suggestions as $msg): ?>
                                    <li><?= $msg ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="mt-4 pt-4 border-top text-center">
                            <a href="admin_user_workloads.php?user_id=<?= $uid ?>" class="btn btn-outline w-full">
                                <i class="bi bi-list-check"></i> จัดการภาระงาน
                            </a>
                        </div>
                    </div>
                </div>

                <div class="ai-section">
                    <i class="bi bi-robot ai-bg-icon"></i>
                    
                    <div style="position:relative; z-index:2;">
                        <div class="stack-between mb-4">
                            <div>
                                <h3 class="m-0 text-primary" style="color:#6d28d9 !important; font-weight:bold;">
                                    <i class="bi bi-stars"></i> ขอคำแนะนำเชิงลึกจาก AI (Gemini)
                                </h3>
                                <p class="text-muted m-0 mt-1">ให้ปัญญาประดิษฐ์ช่วยวิเคราะห์ภาพรวม จุดแข็ง จุดอ่อน และแผนพัฒนาแบบเจาะลึก</p>
                            </div>
                            <button id="aiBtn" onclick="fetchAI()" class="btn btn-primary btn-lg" style="background: linear-gradient(90deg, #7c3aed, #4f46e5); border:none; padding:12px 30px; box-shadow: 0 4px 15px rgba(124, 58, 237, 0.4);">
                                ✨ เริ่มวิเคราะห์
                            </button>
                        </div>

                        <div id="aiLoading" class="text-center py-5" style="display:none;">
                            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem; color:#7c3aed !important;"></div>
                            <h4 class="mt-3 text-muted">กำลังประมวลผลข้อมูล...</h4>
                        </div>

                        <div id="aiResult" style="display:none;">
                            <div class="ai-content-box" id="aiText">
                                </div>
                            <div class="text-right mt-2">
                                <small class="text-muted">Analysis by Google Gemini • <span id="aiTime"></span></small>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<div class="modal" id="insightModal">
    <div class="modal-content" style="max-width:500px; text-align:center; padding:30px;">
        <span class="close" onclick="closeModal('insightModal')" style="position:absolute; right:20px; top:15px; cursor:pointer; font-size:1.5rem;">&times;</span>
        <div class="mb-3" id="modalIconContainer">
            </div>
        <h3 id="modalTitle" class="mb-1">วิเคราะห์ด้าน...</h3>
        <p class="text-muted">เปรียบเทียบผลงานปัจจุบันกับเป้าหมาย</p>
        <div class="bg-light p-4 rounded border mt-4 mb-4">
            <div class="stack-between mb-2"><span>มีอยู่ปัจจุบัน</span><strong class="text-dark" id="modalCurrent">0</strong></div>
            <div class="stack-between mb-2"><span>เป้าหมายขั้นต่ำ</span><strong class="text-primary" id="modalTarget">0</strong></div>
            <div class="progress-bar-bg"><div id="modalBar" class="progress-bar-fill" style="width: 0%"></div></div>
            <div class="mt-2 text-lg font-bold" id="modalGapText"></div>
        </div>
        <div class="text-left"><strong class="text-primary">💡 คำแนะนำ:</strong><p id="modalAdvice" class="mt-1" style="line-height:1.6;">-</p></div>
        <button class="btn btn-muted w-full mt-4" onclick="closeModal('insightModal')">ปิดหน้าต่าง</button>
    </div>
</div>

<div class="modal" id="aiResultModal">
    <div class="modal-content" style="max-width: 800px; padding: 0; border-radius: 16px;">
        <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 20px; color: white; display: flex; justify-content: space-between; align-items: center;">
            <h3 class="m-0 text-white"><i class="bi bi-stars"></i> ผลการวิเคราะห์จาก AI</h3>
            <span class="text-white" onclick="closeModal('aiResultModal')" style="cursor: pointer; font-size: 2rem; line-height: 1;">&times;</span>
        </div>
        <div style="padding: 30px; font-size: 1.1rem; line-height: 1.8; max-height: 70vh; overflow-y: auto;">
            <div id="aiModalContent"></div>
        </div>
        <div class="p-3 bg-light text-right border-top">
            <button class="btn btn-muted" onclick="closeModal('aiResultModal')">ปิดหน้าต่าง</button>
        </div>
    </div>
</div>

<script>
    // 1. ดึง Role ของ User
    const targetRole = "<?= $userData['role'] ?>"; 

    // 2. ข้อมูลจริง
    const actualData = [
        <?= $scores[1] ?? 0 ?>, 
        <?= $scores[2] ?? 0 ?>, 
        <?= $scores[3] ?? 0 ?>, 
        <?= $scores[4] ?? 0 ?>, 
        <?= $scores[5] ?? 0 ?>, 
        <?= $scores[6] ?? 0 ?> 
    ];

    // 3. ตั้งค่ากราฟ
    let standardData, maxScale, stepSize, labels;

    if (targetRole === 'staff') {
        maxScale = 600; stepSize = 100;
        labels = ['งานประจำ', 'พัฒนางาน', 'ยุทธศาสตร์', 'ได้รับมอบหมาย', 'กิจกรรมองค์กร', 'อื่นๆ'];
        standardData = [700, 50, 50, 20, 10, 5];
    } else {
        // Teacher (Demo Target)
        maxScale = 400; stepSize = 100;
        labels = ['การสอน', 'วิจัย/วิชาการ', 'บริการวิชาการ', 'ทำนุบำรุงฯ', 'บริหาร', 'อื่นๆ'];
        standardData = [50, 100, 30, 5, 5, 5]; 
    }

    // 4. สร้างกราฟ
    const ctx = document.getElementById('radarChart').getContext('2d');
    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'ภาระงานจริง (Actual)',
                    data: actualData,
                    backgroundColor: 'rgba(54, 162, 235, 0.25)', 
                    borderColor: '#36A2EB',                   
                    borderWidth: 2,
                    pointBackgroundColor: '#36A2EB',
                    pointRadius: 4
                },
                {
                    label: 'เป้าหมาย (Target)',
                    data: standardData,
                    backgroundColor: 'rgba(255, 99, 132, 0.05)', 
                    borderColor: '#FF6384',                      
                    borderWidth: 2,
                    borderDash: [5, 5],                          
                    pointRadius: 0,                              
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    angleLines: { display: true, color: '#e5e5e5' },
                    grid: { color: '#f0f0f0' },
                    suggestedMin: 0,
                    suggestedMax: maxScale,
                    ticks: { stepSize: stepSize, backdropColor: 'transparent', font: { size: 10 }, showLabelBackdrop: false },
                    pointLabels: { font: { size: 12, weight: 'bold', family: 'Sarabun' }, color: '#333' }
                }
            },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: { label: function(context) { return ' ' + context.dataset.label + ': ' + context.raw.toLocaleString() + ' คะแนน'; } }
                }
            }
        }
    });

    // ----------------------------------------------------
    // Helper Functions
    // ----------------------------------------------------
    function closeModal(id) { document.getElementById(id).classList.remove('show'); }
    window.onclick = function(event) { if (event.target.classList.contains('modal')) event.target.classList.remove('show'); }

    function showDetail(data) {
        document.getElementById('modalTitle').innerText = 'วิเคราะห์: ' + data.name;
        document.getElementById('modalCurrent').innerText = data.current.toLocaleString();
        document.getElementById('modalTarget').innerText = data.target.toLocaleString();
        document.getElementById('modalAdvice').innerText = data.advice;
        
        const iconContainer = document.getElementById('modalIconContainer');
        const gapText = document.getElementById('modalGapText');
        const bar = document.getElementById('modalBar');
        
        let percent = data.percent;
        if(percent > 100) percent = 100; // บาร์ยาวสุดแค่ 100%
        bar.style.width = percent + '%';

        if (data.type === 'strength') {
            // กรณี: จุดแข็ง (สีเขียว)
            iconContainer.innerHTML = `
                <div style="background:#dcfce7; color:#166534; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto; font-size:1.8rem;">
                    <i class="bi bi-trophy-fill"></i>
                </div>`;
            bar.style.backgroundColor = '#10b981'; // เขียว
            gapText.innerHTML = `<span class="text-success">🎉 เกินเป้าหมาย ${data.gap.toLocaleString()} คะแนน</span>`;
        } else {
            // กรณี: จุดอ่อน (สีแดง/ส้ม)
            iconContainer.innerHTML = `
                <div style="background:#fee2e2; color:#ef4444; width:60px; height:60px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto; font-size:1.8rem;">
                    <i class="bi bi-exclamation-lg"></i>
                </div>`;
            
            if(percent < 30) bar.style.backgroundColor = '#ef4444'; // แดง
            else if(percent < 70) bar.style.backgroundColor = '#f59e0b'; // ส้ม
            else bar.style.backgroundColor = '#10b981'; // เขียว

            gapText.innerHTML = `<span class="text-danger">⚠️ ขาดอีก ${data.gap.toLocaleString()} คะแนน</span>`;
        }
        
        document.getElementById('insightModal').classList.add('show');
    }

    // AI Fetch Logic
    async function fetchAI() {
        const uiBtn = document.getElementById('aiBtn');
        const uiLoad = document.getElementById('aiLoading');
        const uiRes = document.getElementById('aiResult');
        const uiText = document.getElementById('aiText');
        const uiModal = document.getElementById('aiResultModal');
        const uiModalContent = document.getElementById('aiModalContent');

        uiBtn.disabled = true;
        uiBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังวิเคราะห์...';
        uiLoad.style.display = 'block';
        uiRes.style.display = 'none';

        try {
            const response = await fetch('api_ai_analysis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ uid: <?= $uid ?> })
            });
            const data = await response.json();

            uiLoad.style.display = 'none';
            uiBtn.disabled = false;
            uiBtn.innerHTML = '✨ วิเคราะห์อีกครั้ง';

            if (data.success) {
                let formattedMsg = data.message.replace(/\n/g, '<br>').replace(/\*\*(.*?)\*\*/g, '<b class="text-primary">$1</b>').replace(/\* /g, '• ');
                uiModalContent.innerHTML = formattedMsg;
                uiModal.classList.add('show'); 
                uiRes.style.display = 'block';
                uiText.innerHTML = formattedMsg;
                document.getElementById('aiTime').innerText = new Date().toLocaleString('th-TH');
            } else {
                alert('AI Error: ' + (data.error || 'Unknown'));
            }
        } catch (e) {
            console.error(e);
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            uiLoad.style.display = 'none';
            uiBtn.disabled = false;
            uiBtn.innerHTML = '✨ ลองใหม่';
        }
    }
</script>
</body>
</html>