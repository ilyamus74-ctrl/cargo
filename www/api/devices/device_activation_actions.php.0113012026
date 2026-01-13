<?php

declare(strict_types=1);

switch ($action) {
    case 'activate_device':
        auth_require_role('ADMIN');

        $deviceId  = (int)($_POST['device_id'] ?? 0);
        $isActive  = (int)($_POST['is_active'] ?? 0) ? 1 : 0;

        if ($deviceId <= 0) {
            $response = [
                'status'  => 'error',
                'message' => 'device_id required',
            ];
            break;
        }

        $sql = "UPDATE devices
                   SET is_active = ?,
                       activated_at = CASE
                           WHEN ? = 1 AND activated_at IS NULL
                           THEN CURRENT_TIMESTAMP(6)
                           ELSE activated_at
                       END
                 WHERE id = ?";
        $stmt = $dbcnx->prepare($sql);
        $stmt->bind_param("iii", $isActive, $isActive, $deviceId);
        $stmt->execute();
        $stmt->close();

        audit_log(
            $user['id'] ?? null,
            $isActive ? 'DEVICE_ACTIVATE' : 'DEVICE_DEACTIVATE',
            'DEVICE',
            $deviceId,
            $isActive ? 'Активация устройства' : 'Деактивация устройства'
        );

        $response = [
            'status'  => 'ok',
            'message' => 'Статус устройства обновлён',
        ];
        break;
}
