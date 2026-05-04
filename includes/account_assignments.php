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

function deleteUserAccountAssignmentById(PDO $pdo, int $assignmentId): array
{
    $stmt = $pdo->prepare('DELETE FROM usuario_cuentas_servicio WHERE id = :id');
    $stmt->execute(['id' => $assignmentId]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'message' => 'La asignación indicada no existe.'];
    }

    return ['success' => true, 'message' => 'Cuenta desasignada correctamente.'];
}