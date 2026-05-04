<?php

declare(strict_types=1);

function insertUserAccountAssignment(PDO $pdo, int $userId, int $accountId): array
{
    $stmt = $pdo->prepare('INSERT INTO usuario_cuentas_servicio (usuario_id, cuenta_servicio_id) VALUES (:usuario_id, :cuenta_servicio_id)');

    try {
        $stmt->execute([
            'usuario_id' => $userId,
            'cuenta_servicio_id' => $accountId,
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            return ['success' => false, 'message' => 'Esa cuenta ya está asignada a ese usuario.'];
        }

        throw $exception;
    }

    return ['success' => true, 'message' => 'Cuenta asignada correctamente.'];
}

function fetchUserAccountAssignmentById(PDO $pdo, int $assignmentId): ?array
{
    $stmt = $pdo->prepare('SELECT id, usuario_id, cuenta_servicio_id FROM usuario_cuentas_servicio WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $assignmentId]);
    $assignment = $stmt->fetch();

    return $assignment === false ? null : $assignment;
}

function fetchUserAccountAssignmentsByIds(PDO $pdo, array $assignmentIds): array
{
    $normalizedAssignmentIds = array_values(array_unique(array_map('intval', $assignmentIds)));

    if ($normalizedAssignmentIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($normalizedAssignmentIds), '?'));
    $stmt = $pdo->prepare("SELECT id, usuario_id, cuenta_servicio_id FROM usuario_cuentas_servicio WHERE id IN ($placeholders)");
    $stmt->execute($normalizedAssignmentIds);

    return $stmt->fetchAll();
}

function deleteUserAccountAssignmentById(PDO $pdo, int $assignmentId): array
{
    $stmt = $pdo->prepare('DELETE FROM usuario_cuentas_servicio WHERE id = :id');
    $stmt->execute(['id' => $assignmentId]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'La asignación indicada no existe.'];
    }

    return ['success' => true, 'message' => 'Cuenta desasignada correctamente.'];
}

function deleteUserAccountAssignmentsByIds(PDO $pdo, array $assignmentIds): array
{
    $normalizedAssignmentIds = array_values(array_unique(array_map('intval', $assignmentIds)));

    if ($normalizedAssignmentIds === []) {
        return [
            'success' => false,
            'message' => 'Debes indicar al menos una asignación válida para desasignar.',
            'removed_assignments' => 0,
        ];
    }

    $placeholders = implode(',', array_fill(0, count($normalizedAssignmentIds), '?'));
    $stmt = $pdo->prepare("DELETE FROM usuario_cuentas_servicio WHERE id IN ($placeholders)");
    $stmt->execute($normalizedAssignmentIds);

    return [
        'success' => $stmt->rowCount() > 0,
        'message' => $stmt->rowCount() > 0 ? 'Asignaciones desasignadas correctamente.' : 'No se encontraron asignaciones para desasignar.',
        'removed_assignments' => $stmt->rowCount(),
    ];
}

function deleteUserAccountAssignmentsByAccountIdsAndUserIds(PDO $pdo, array $accountIds, array $userIds): array
{
    $normalizedAccountIds = array_values(array_unique(array_map('intval', $accountIds)));
    $normalizedUserIds = array_values(array_unique(array_map('intval', $userIds)));

    if ($normalizedAccountIds === [] || $normalizedUserIds === []) {
        return [
            'success' => false,
            'message' => 'Debes indicar cuentas y usuarios válidos para desasignar.',
            'removed_assignments' => 0,
        ];
    }

    $accountPlaceholders = implode(',', array_fill(0, count($normalizedAccountIds), '?'));
    $userPlaceholders = implode(',', array_fill(0, count($normalizedUserIds), '?'));
    $stmt = $pdo->prepare(
        "DELETE FROM usuario_cuentas_servicio WHERE cuenta_servicio_id IN ($accountPlaceholders) AND usuario_id IN ($userPlaceholders)"
    );
    $stmt->execute([...$normalizedAccountIds, ...$normalizedUserIds]);

    return [
        'success' => $stmt->rowCount() > 0,
        'message' => $stmt->rowCount() > 0 ? 'Usuarios desasignados correctamente.' : 'No se encontraron asignaciones para desasignar.',
        'removed_assignments' => $stmt->rowCount(),
    ];
}