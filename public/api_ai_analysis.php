<?php
// public/api_ai_analysis.php
header('Content-Type: application/json');

require_once '../config/app.php';
require_once '../middleware/require_login.php';
require_once '../config/db.php';

// 1. ตรวจสอบสิทธิ์
if (!in_array($user['role'], ['admin', 'manager'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 2. รับค่า ID
$input = json_decode(file_get_contents('php://input'), true);
$uid = $input['uid'] ?? 0;

if (!$uid) {
    echo json_encode(['error' => 'User ID required']);
    exit;
}

// 3. ดึงข้อมูล User
$userQ = $conn->prepare("SELECT name, role FROM users WHERE id = ?");
$userQ->bind_param("i", $uid);
$userQ->execute();
$userData = $userQ->get_result()->fetch_assoc();

if (!$userData) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

// กำหนดตัวแปรตาม Role
$roleName = "";
$criteriaTotal = 0;
$areaMap = [];

if ($userData['role'] == 'staff') {
    $roleName = "บุคลากรสายสนับสนุน (Staff)";
    $criteriaTotal = 1645;
    $areaMap = [
        1 => "งานประจำ (Routine)",
        2 => "พัฒนางาน (Dev)",
        3 => "งานยุทธศาสตร์",
        4 => "งานที่ได้รับมอบหมาย",
        5 => "กิจกรรมองค์กร",
        6 => "อื่นๆ"
    ];
} else {
    $roleName = "อาจารย์ผู้สอน (Teacher)";
    $criteriaTotal = 1330;
    $areaMap = [
        1 => "การสอน",
        2 => "วิจัย/วิชาการ",
        3 => "บริการวิชาการ",
        4 => "ทำนุบำรุงศิลปฯ",
        5 => "บริหาร",
        6 => "อื่นๆ"
    ];
}

// 4. ดึงคะแนนรายด้าน
$sql = "SELECT wc.main_area, SUM(wi.computed_hours) as total 
        FROM workload_items wi 
        JOIN workload_categories wc ON wi.category_id = wc.id 
        WHERE wi.user_id = ? AND wi.status IN ('approved_admin', 'approved_final') 
        GROUP BY wc.main_area";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();

$scoresRaw = []; // เก็บแบบ Key
$totalScore = 0;
while($r = $res->fetch_assoc()) {
    $scoresRaw[$r['main_area']] = floatval($r['total']);
    $totalScore += floatval($r['total']);
}

// จัดรูปแบบคะแนนรายด้านเป็น Text เพื่อส่งให้ AI
$scoreBreakdown = "";
foreach ($areaMap as $id => $name) {
    $val = $scoresRaw[$id] ?? 0;
    $scoreBreakdown .= "- ด้านที่ $id ($name): " . number_format($val, 2) . " คะแนน\n";
}

// 5. ดึง Top 5 Tasks (งานที่ทำจริง)
$topTasks = [];
$taskSql = "SELECT title, computed_hours FROM workload_items 
            WHERE user_id = ? AND status IN ('approved_admin', 'approved_final') 
            ORDER BY computed_hours DESC LIMIT 5";
$stmtTask = $conn->prepare($taskSql);
$stmtTask->bind_param("i", $uid);
$stmtTask->execute();
$resTask = $stmtTask->get_result();
$i = 1;
while($row = $resTask->fetch_assoc()) {
    $topTasks[] = "$i. " . $row['title'] . " (" . number_format($row['computed_hours'], 2) . " คะแนน)";
    $i++;
}
$taskListString = implode("\n", $topTasks);
if (empty($taskListString)) $taskListString = "- ยังไม่มีการบันทึกภาระงาน";

// 6. สร้าง Prompt (ตามสูตรผู้เชี่ยวชาญ)
$passStatus = ($totalScore >= $criteriaTotal) ? "ผ่านเกณฑ์ขั้นต่ำแล้ว" : "ยังไม่ผ่านเกณฑ์ (ขาดอีก " . number_format($criteriaTotal - $totalScore) . " คะแนน)";

$prompt = "
บทบาท (Role):
คุณคือผู้เชี่ยวชาญด้านทรัพยากรบุคคลและการพัฒนาศักยภาพบุคลากรในมหาวิทยาลัยแพทยศาสตร์ 
มีประสบการณ์ในการวิเคราะห์ภาระงานเชิงลึก การวาง Career Path และการให้คำแนะนำเชิงกลยุทธ์
มีแนวคิดแบบ Growth Mindset สุภาพ เป็นทางการ และให้กำลังใจ

บริบท (Context):
คุณได้รับข้อมูลภาระงานประจำปีของบุคลากร 1 ราย 
ข้อมูลนี้สะท้อนทั้ง “สิ่งที่บุคลากรทำจริง” และ “ผลลัพธ์เชิงคะแนน”
หน้าที่ของคุณคือวิเคราะห์ข้อมูลเหล่านี้เพื่อช่วยให้บุคลากรพัฒนาอย่างเหมาะสมกับบทบาทหน้าที่

ข้อมูลบุคลากร:
- ชื่อ: {$userData['name']}
- ตำแหน่ง: $roleName
- สถานะตามเกณฑ์: $passStatus
- คะแนนรวม: " . number_format($totalScore) . " จากเกณฑ์ขั้นต่ำ " . number_format($criteriaTotal) . "

คะแนนรายด้าน:
$scoreBreakdown

ภาระงานหลักที่ทำจริง (Top 5):
$taskListString

คำสั่งในการวิเคราะห์:
โปรดดำเนินการตามลำดับความคิดดังนี้ (แต่แสดงผลเฉพาะบทสรุป):
1. วิเคราะห์ “รูปแบบการทำงาน” จาก Top 5 Tasks ว่าบุคลากรทุ่มเทเวลาไปกับงานประเภทใดเป็นหลัก
2. เชื่อมโยงรูปแบบงานกับคะแนนรายด้าน เพื่อประเมินความสอดคล้องหรือความไม่สมดุล
3. ประเมินศักยภาพและทิศทางการเติบโตที่เหมาะสมกับบทบาท

รูปแบบผลลัพธ์ที่ต้องการ (ตอบเป็น HTML Tag เท่านั้น):
<b>1. ภาพรวมผลงาน</b>
- สรุประดับผลงานโดยรวม จุดแข็งที่ชัดเจน และคุณค่าที่บุคลากรสร้างให้หน่วยงาน

<b>2. ประเด็นที่ควรพัฒนา</b>
- วิเคราะห์ด้านที่ยังต่ำกว่าศักยภาพหรือยังไม่สอดคล้องกับบทบาท

<b>3. ข้อเสนอแนะเชิงกลยุทธ์ (Action Plan)</b>
<ul>
<li>ข้อเสนอแนะที่ 1 (เชิงพัฒนาในบทบาทปัจจุบัน)</li>
<li>ข้อเสนอแนะที่ 2 (เชิงเพิ่มมูลค่าผลงานหรือคะแนน)</li>
<li>ข้อเสนอแนะที่ 3 (เชิงเตรียมความก้าวหน้าในอนาคต)</li>
</ul>

เงื่อนไข:
- ห้ามทวนตัวเลขซ้ำโดยไม่วิเคราะห์
- ใช้ภาษาไทย สุภาพ เป็นมืออาชีพ
- ใช้ HTML tag เพื่อความสวยงาม
";

// 7. ส่งไปหา Gemini API
$apiKey = 'AIzaSyBh0S5gPRR8jznicDV2JBq2wMnidBUsVw8'; 
$model = 'gemini-2.5-flash'; 
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 8192
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 8. ส่งผลลัพธ์กลับหน้าเว็บ
if ($httpCode === 200) {
    $result = json_decode($response, true);
    $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'ไม่สามารถวิเคราะห์ข้อมูลได้ในขณะนี้';
    echo json_encode(['success' => true, 'message' => $aiText]);
} else {
    $err = json_decode($response, true);
    $errMsg = $err['error']['message'] ?? 'Unknown Error';
    echo json_encode(['success' => false, 'error' => "AI Error: $errMsg"]);
}
?>