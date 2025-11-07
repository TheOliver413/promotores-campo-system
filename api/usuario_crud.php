<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../db/User.php';
require_once __DIR__ . '/../db/UsuarioCliente.php';
require_once __DIR__ . '/../db/SupervisorPromotor.php';

header('Content-Type: application/json');

$userModel = new User();
$usuarioClienteModel = new UsuarioCliente();
$supervisorPromotorModel = new SupervisorPromotor();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            $userId = $_GET['id'];
            $usuario = $userModel->getById($userId);

            // Get assigned clientes
            $clientes = $usuarioClienteModel->getClientesByUsuario($userId);
            $clienteIds = array_map(function ($c) {
                return $c['id'];
            }, $clientes);

            // Get assigned supervisores (if promotor)
            $supervisores = $supervisorPromotorModel->getSupervisoresByPromotor($userId);
            $supervisorIds = array_map(function ($s) {
                return $s['id'];
            }, $supervisores);

            echo json_encode([
                'success' => true,
                'usuario' => $usuario,
                'clientes' => $clienteIds,
                'supervisores' => $supervisorIds
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
