<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

$licenseId = require_paid_access();
$database = app_db();
$settings = app_settings();
$maxCards = max(1, min(20, (int) $settings['max_cards_per_license']));

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $list = $database->prepare('SELECT id, public_token, owner_name, phone, note, plate, locale, template, whatsapp, emergency_phone, blood_type, allergies, chronic_conditions, special_notes FROM cards WHERE license_id = :id ORDER BY id ASC');
    $list->execute(['id' => $licenseId]);
    $cards = [];
    foreach ($list->fetchAll() as $row) {
        $row['url'] = card_public_url((string) $row['public_token']);
        $row['whatsapp'] = (int) $row['whatsapp'];
        $cards[] = $row;
    }
    echo json_encode(['ok' => true, 'max' => $maxCards, 'cards' => $cards], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_is_valid()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Doğrulama başarısız.']);
    exit;
}

$action = (string) ($_POST['action'] ?? 'save');
if ($action === 'delete') {
    $cardId = filter_var($_POST['card_id'] ?? null, FILTER_VALIDATE_INT);
    $delete = $database->prepare('DELETE FROM cards WHERE id = :id AND license_id = :license');
    $delete->execute(['id' => $cardId, 'license' => $licenseId]);
    echo json_encode(['ok' => $delete->rowCount() === 1]);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$note = trim((string) ($_POST['note'] ?? ''));
$plate = strtoupper(trim((string) ($_POST['plate'] ?? '')));
$locale = ($_POST['locale'] ?? 'tr') === 'en' ? 'en' : 'tr';
$template = (string) ($_POST['template'] ?? 'navy');
if (!array_key_exists($template, card_templates())) {
    $template = 'navy';
}
$whatsapp = isset($_POST['whatsapp']) && $_POST['whatsapp'] !== '0' ? 1 : 0;
$emergencyPhone = trim((string) ($_POST['emergency_phone'] ?? ''));
$bloodType = trim((string) ($_POST['blood_type'] ?? ''));
$allergies = trim((string) ($_POST['allergies'] ?? ''));
$chronicConditions = trim((string) ($_POST['chronic_conditions'] ?? ''));
$specialNotes = trim((string) ($_POST['special_notes'] ?? ''));
$digits = normalize_phone_digits($phone);
$cardId = filter_var($_POST['card_id'] ?? null, FILTER_VALIDATE_INT);

if (mb_strlen($name) < 2 || mb_strlen($name) > 48) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Ad 2-48 karakter olmalı.']);
    exit;
}
if (strlen($digits) < 10 || strlen($digits) > 15) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Geçerli bir telefon girin.']);
    exit;
}
if (mb_strlen($note) > 100 || mb_strlen($plate) > 16) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Not veya plaka çok uzun.']);
    exit;
}

if ($cardId) {
    $update = $database->prepare(
        'UPDATE cards SET owner_name=:name, phone=:phone, note=:note, plate=:plate, locale=:locale, template=:template, whatsapp=:wa,
         emergency_phone=:emergency_phone, blood_type=:blood_type, allergies=:allergies, chronic_conditions=:chronic_conditions, special_notes=:special_notes, updated_at=CURRENT_TIMESTAMP
         WHERE id=:id AND license_id=:license'
    );
    $update->execute([
        'name' => $name,
        'phone' => $phone,
        'note' => $note,
        'plate' => $plate,
        'locale' => $locale,
        'template' => $template,
        'wa' => $whatsapp,
        'emergency_phone' => $emergencyPhone,
        'blood_type' => $bloodType,
        'allergies' => $allergies,
        'chronic_conditions' => $chronicConditions,
        'special_notes' => $specialNotes,
        'id' => $cardId,
        'license' => $licenseId
    ]);
    if ($update->rowCount() < 1) {
        $exists = $database->prepare('SELECT id FROM cards WHERE id = :id AND license_id = :license');
        $exists->execute(['id' => $cardId, 'license' => $licenseId]);
        if (!$exists->fetch()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Kart bulunamadı.']);
            exit;
        }
    }
    $tokenQuery = $database->prepare('SELECT public_token FROM cards WHERE id = :id');
    $tokenQuery->execute(['id' => $cardId]);
    $token = (string) $tokenQuery->fetchColumn();
} else {
    $database->exec('BEGIN IMMEDIATE');
    try {
    $count = $database->prepare('SELECT COUNT(*) FROM cards WHERE license_id = :id');
    $count->execute(['id' => $licenseId]);
    if ((int) $count->fetchColumn() >= $maxCards) {
        $database->exec('ROLLBACK');
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'Bu lisans için kart sınırına ulaşıldı.']);
        exit;
    }
    $token = bin2hex(random_bytes(12));
    $insert = $database->prepare(
        'INSERT INTO cards (public_token, license_id, owner_name, phone, note, plate, locale, template, whatsapp,
         emergency_phone, blood_type, allergies, chronic_conditions, special_notes)
         VALUES (:token, :license, :name, :phone, :note, :plate, :locale, :template, :wa,
         :emergency_phone, :blood_type, :allergies, :chronic_conditions, :special_notes)'
    );
    $insert->execute([
        'token' => $token,
        'license' => $licenseId,
        'name' => $name,
        'phone' => $phone,
        'note' => $note,
        'plate' => $plate,
        'locale' => $locale,
        'template' => $template,
        'wa' => $whatsapp,
        'emergency_phone' => $emergencyPhone,
        'blood_type' => $bloodType,
        'allergies' => $allergies,
        'chronic_conditions' => $chronicConditions,
        'special_notes' => $specialNotes
    ]);
    $cardId = (int) $database->lastInsertId();
    $database->exec('COMMIT');
    } catch (Throwable $exception) {
        if ($database->inTransaction()) {
            $database->exec('ROLLBACK');
        }
        throw $exception;
    }
}

echo json_encode([
    'ok' => true,
    'id' => $cardId,
    'token' => $token,
    'url' => card_public_url($token)
], JSON_UNESCAPED_UNICODE);
