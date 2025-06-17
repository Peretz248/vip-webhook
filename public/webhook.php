<?php
// מענה אוטומטי לבדיקה של Tebex (HEAD או GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(200);
    exit('OK'); // הכרחי לצורך אימות ראשוני
}

// אימות Signature של Tebex (תוך טיפול ברגישות אותיות בכותרות)
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$secret = '68fbdd15730699609ff17ecaf12ce10b';

if (!isset($headers['x-tebex-signature']) || $headers['x-tebex-signature'] !== $secret) {
    http_response_code(403);
    exit('Unauthorized');
}

// קבלת נתונים מה-Webhook
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['player']['name']) || !isset($data['package']['name'])) {
    http_response_code(400);
    exit('Bad Request');
}

// מיפוי חבילות VIP לפריטים
$vipMap = [
    'VIP A' => ['item' => 'vip_a_vehicle_token', 'amount' => 1],
    'VIP B' => ['item' => 'vip_b_vehicle_token', 'amount' => 1],
    'VIP C' => ['item' => 'vip_c_vehicle_token', 'amount' => 1],
];

$packageName = $data['package']['name'];
$vip = $vipMap[$packageName] ?? null;
if (!$vip) {
    http_response_code(400);
    exit('Unknown VIP Package');
}

// יצירת קוד רנדומלי
$code = strtoupper("TBX-" . bin2hex(random_bytes(4)));

// התחברות למסד הנתונים
$db = new mysqli('181.214.214.142', 'discordbot', 'elite', 'fox');
if ($db->connect_error) {
    http_response_code(500);
    exit('DB Connection Failed');
}

// הוספת הקוד למסד
$stmt = $db->prepare("INSERT INTO redeem_codes (code, item, amount, created_by, redeemed) VALUES (?, ?, ?, ?, 0)");
$stmt->bind_param("ssis", $code, $vip['item'], $vip['amount'], $data['player']['name']);
$stmt->execute();

// מענה תקין ל-Tebex
http_response_code(200);
echo json_encode(['status' => 'success', 'code' => $code]);
