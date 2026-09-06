<?php
// Helper functions

function generateHospitalId() {
    return 'PT' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

function generateAppointmentNumber() {
    return 'APT' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

function generateAdmissionNumber() {
    return 'ADM' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

function formatDate($date, $format = 'F j, Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function formatTime($time, $format = 'g:i A') {
    if (empty($time)) return '';
    return date($format, strtotime($time));
}

function calculateAge($birth_date) {
    $birthday = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birthday);
    return $age->y;
}

function getTriageColor($level) {
    $colors = [
        'resuscitation' => 'red',
        'emergency' => 'orange',
        'urgent' => 'yellow',
        'semi_urgent' => 'blue',
        'non_urgent' => 'green'
    ];
    return $colors[$level] ?? 'gray';
}

function getStatusBadge($status) {
    $badges = [
        'scheduled' => 'blue',
        'confirmed' => 'green',
        'in_progress' => 'yellow',
        'completed' => 'emerald',
        'cancelled' => 'red',
        'no_show' => 'orange',
        'active' => 'green',
        'inactive' => 'gray',
        'admitted' => 'blue',
        'discharged' => 'green',
        'waiting' => 'yellow',
        'available' => 'green',
        'occupied' => 'red',
        'maintenance' => 'orange'
    ];
    return $badges[$status] ?? 'gray';
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function getClinicRooms(PDO $db, ?string $department = null): array {
    $query = "SELECT * FROM clinic_rooms WHERE status <> 'inactive'";
    if ($department) {
        $query .= " AND department = :department";
    }
    $query .= " ORDER BY department, name";

    $stmt = $db->prepare($query);
    if ($department) {
        $stmt->bindParam(':department', $department);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function getDoctorScheduleForDate(PDO $db, int $doctor_id, string $date): ?array {
    $day_of_week = (int) date('w', strtotime($date));
    $query = "SELECT ds.*, r.name AS room_name, r.department AS room_department
              FROM doctor_schedules ds
              LEFT JOIN clinic_rooms r ON ds.room_id = r.id
              WHERE ds.doctor_id = :doctor_id AND ds.day_of_week = :day_of_week";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $stmt->bindParam(':day_of_week', $day_of_week, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch() ?: null;
}

function calculateSlotEndTime(string $start_time, int $duration_minutes): string {
    $start = new DateTime($start_time);
    $start->modify("+{$duration_minutes} minutes");
    return $start->format('H:i:s');
}

function normalizeTime(string $time): string {
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time . ':00';
    }
    return date('H:i:s', strtotime($time));
}

function isWithinSchedule(array $schedule, string $time): bool {
    $time_normalized = normalizeTime($time);
    $start = normalizeTime($schedule['start_time']);
    $end = normalizeTime($schedule['end_time']);
    return ($time_normalized >= $start && $time_normalized < $end);
}

function isDoctorSlotAvailable(PDO $db, int $doctor_id, string $date, string $time, ?int $appointment_id = null): bool {
    $time = normalizeTime($time);
    $query = "SELECT COUNT(*) as total 
              FROM appointments 
              WHERE doctor_id = :doctor_id 
                AND appointment_date = :appointment_date
                AND appointment_time = :appointment_time
                AND status NOT IN ('cancelled', 'no_show')";

    if ($appointment_id) {
        $query .= " AND id <> :appointment_id";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $stmt->bindParam(':appointment_date', $date);
    $stmt->bindParam(':appointment_time', $time);
    if ($appointment_id) {
        $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $row = $stmt->fetch();
    return $row['total'] == 0;
}

function countDoctorAppointmentsForDate(PDO $db, int $doctor_id, string $date, ?int $appointment_id = null): int {
    $query = "SELECT COUNT(*) as total 
              FROM appointments 
              WHERE doctor_id = :doctor_id 
                AND appointment_date = :appointment_date
                AND status NOT IN ('cancelled', 'no_show')";
    if ($appointment_id) {
        $query .= " AND id <> :appointment_id";
    }
    $stmt = $db->prepare($query);
    $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $stmt->bindParam(':appointment_date', $date);
    if ($appointment_id) {
        $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $row = $stmt->fetch();
    return (int) $row['total'];
}

function isRoomSlotAvailable(PDO $db, int $room_id, string $date, string $time, ?int $appointment_id = null): bool {
    $time = normalizeTime($time);
    $query = "SELECT COUNT(*) as total 
              FROM appointments 
              WHERE room_id = :room_id 
                AND appointment_date = :appointment_date
                AND appointment_time = :appointment_time
                AND status NOT IN ('cancelled', 'no_show')";

    if ($appointment_id) {
        $query .= " AND id <> :appointment_id";
    }

    $stmt = $db->prepare($query);
    $stmt->bindParam(':room_id', $room_id, PDO::PARAM_INT);
    $stmt->bindParam(':appointment_date', $date);
    $stmt->bindParam(':appointment_time', $time);
    if ($appointment_id) {
        $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $row = $stmt->fetch();
    return $row['total'] == 0;
}

function validateAppointmentSlot(PDO $db, int $doctor_id, int $room_id, string $date, string $time, ?int $appointment_id = null): array {
    $time = normalizeTime($time);
    $schedule = getDoctorScheduleForDate($db, $doctor_id, $date);
    if (!$schedule) {
        return [false, "Selected doctor has no clinic schedule for that date."];
    }

    if (!isWithinSchedule($schedule, $time)) {
        return [false, "Selected time falls outside the doctor's clinic hours."];
    }

    if (!isDoctorSlotAvailable($db, $doctor_id, $date, $time, $appointment_id)) {
        return [false, "Doctor already has an appointment for the selected slot."];
    }

    if (!empty($schedule['max_daily_appointments'])) {
        $daily_total = countDoctorAppointmentsForDate($db, $doctor_id, $date, $appointment_id);
        if ($daily_total >= (int) $schedule['max_daily_appointments']) {
            return [false, "Doctor has reached the maximum number of appointments for that day."];
        }
    }

    if ($room_id && !isRoomSlotAvailable($db, $room_id, $date, $time, $appointment_id)) {
        return [false, "Selected clinic room is occupied at that time."];
    }

    return [true, $schedule];
}

function findNextAvailableSlot(PDO $db, int $doctor_id, string $start_date, int $horizon_days = 7): ?array {
    for ($i = 0; $i <= $horizon_days; $i++) {
        $date = date('Y-m-d', strtotime("{$start_date} +{$i} day"));
        $schedule = getDoctorScheduleForDate($db, $doctor_id, $date);
        if (!$schedule) {
            continue;
        }

        $slot_minutes = (int) ($schedule['slot_duration_minutes'] ?? 30);
        if ($slot_minutes <= 0) {
            $slot_minutes = 30;
        }

        $schedule_start = new DateTime(normalizeTime($schedule['start_time']));
        $schedule_end = new DateTime(normalizeTime($schedule['end_time']));
        if ($date === date('Y-m-d')) {
            $current_time = new DateTime(max(normalizeTime($schedule['start_time']), date('H:i:s')));
        } else {
            $current_time = clone $schedule_start;
        }

        while ($current_time < $schedule_end) {
            $slot_start = $current_time->format('H:i:s');

            if (isWithinSchedule($schedule, $slot_start) && isDoctorSlotAvailable($db, $doctor_id, $date, $slot_start)) {
                if (empty($schedule['max_daily_appointments']) || countDoctorAppointmentsForDate($db, $doctor_id, $date) < (int) $schedule['max_daily_appointments']) {
                    $room_id = $schedule['room_id'] ? (int) $schedule['room_id'] : null;
                    if (!$room_id || isRoomSlotAvailable($db, $room_id, $date, $slot_start)) {
                        return [
                            'date' => $date,
                            'time' => $slot_start,
                            'room_id' => $room_id,
                            'schedule' => $schedule
                        ];
                    }
                }
            }

            $current_time->modify("+{$slot_minutes} minutes");
        }
    }

    return null;
}

function recordAppointmentNotification(PDO $db, int $appointment_id, string $channel, string $recipient, string $message): int {
    $query = "INSERT INTO appointment_notifications (appointment_id, channel, recipient, message) 
              VALUES (:appointment_id, :channel, :recipient, :message)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
    $stmt->bindParam(':channel', $channel);
    $stmt->bindParam(':recipient', $recipient);
    $stmt->bindParam(':message', $message);
    $stmt->execute();
    return (int) $db->lastInsertId();
}

function markNotificationStatus(PDO $db, int $notification_id, string $status, ?string $error_message = null): void {
    $query = "UPDATE appointment_notifications 
              SET status = :status, sent_at = CASE WHEN :status = 'sent' THEN NOW() ELSE sent_at END, error_message = :error_message 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':error_message', $error_message);
    $stmt->bindParam(':id', $notification_id, PDO::PARAM_INT);
    $stmt->execute();
}

function upsertAppointmentLink(PDO $db, int $appointment_id, ?string $ehr_reference = null, ?string $billing_reference = null): void {
    $query = "INSERT INTO appointment_links (appointment_id, ehr_reference, billing_reference) 
              VALUES (:appointment_id, :ehr_reference, :billing_reference)
              ON DUPLICATE KEY UPDATE 
                ehr_reference = COALESCE(:ehr_reference_update, ehr_reference),
                billing_reference = COALESCE(:billing_reference_update, billing_reference),
                updated_at = NOW()";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
    $stmt->bindParam(':ehr_reference', $ehr_reference);
    $stmt->bindParam(':billing_reference', $billing_reference);
    $stmt->bindParam(':ehr_reference_update', $ehr_reference);
    $stmt->bindParam(':billing_reference_update', $billing_reference);
    $stmt->execute();
}

function verifyRecaptcha($response) {
    if (empty($response)) {
        return false;
    }
    
    if (!defined('RECAPTCHA_SECRET_KEY') || empty(RECAPTCHA_SECRET_KEY)) {
        error_log('RECAPTCHA_SECRET_KEY is missing');
        return false;
    }
    
    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $response
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($verifyUrl, false, $context);
    
    if ($result === FALSE) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $verifyUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $result = curl_exec($ch);
            curl_close($ch);
        }
    }

    if ($result === FALSE) {
        error_log('Failed to connect to reCAPTCHA verification service');
        return false;
    }
    
    $json = json_decode($result);
    return isset($json->success) && $json->success;
}
?>