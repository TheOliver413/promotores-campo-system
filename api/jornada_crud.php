<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/auth_helpers.php';
require_once '../db/Jornada.php';
require_once '../db/Auditoria.php';

header('Content-Type: application/json');
checkAuth();

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$jornadaModel = new Jornada();
$auditoriaModel = new Auditoria();

try {
    switch ($action) {
        case 'checkin':
            checkRole(['Promotor']);

            $latitud = $_POST['latitud'] ?? null;
            $longitud = $_POST['longitud'] ?? null;
            $proyectoId = $_POST['proyecto_id'] ?? null;

            if (!$latitud || !$longitud) {
                throw new Exception('Ubicación GPS requerida');
            }

            // Verificar que no haya jornada activa
            $jornadaActiva = $jornadaModel->getJornadaActivaHoy($user_id);
            if ($jornadaActiva) {
                throw new Exception('Ya existe una jornada activa');
            }

            // Manejar foto
            $fotoUrl = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
                $fotoUrl = '/uploads/checkin_' . time() . '_' . $user_id . '.jpg';
                // Simular guardado de archivo
            }

            $jornadaId = $jornadaModel->create([
                'promotor_user_id' => $user_id,
                'proyecto_id' => $proyectoId,
                'check_in_lat' => $latitud,
                'check_in_lon' => $longitud,
                'check_in_foto_url' => $fotoUrl
            ]);

            $auditoriaModel->registrar(
                $user_id,
                'Check-in',
                'jornadas',
                $jornadaId,
                ['latitud' => $latitud, 'longitud' => $longitud]
            );

            echo json_encode(['success' => true, 'jornada_id' => $jornadaId]);
            break;

        case 'checkout':
            checkRole(['Promotor']);

            $data = json_decode(file_get_contents('php://input'), true);
            $latitud = $data['latitud'] ?? null;
            $longitud = $data['longitud'] ?? null;

            if (!$latitud || !$longitud) {
                throw new Exception('Ubicación GPS requerida');
            }

            $jornadaActiva = $jornadaModel->getJornadaActivaHoy($user_id);
            if (!$jornadaActiva) {
                throw new Exception('No hay jornada activa');
            }

            // Calcular horas trabajadas
            $checkIn = new DateTime($jornadaActiva['check_in_time']);
            $checkOut = new DateTime();
            $diff = $checkIn->diff($checkOut);
            $horasCalculadas = $diff->h + ($diff->i / 60);

            $jornadaModel->update($jornadaActiva['id'], [
                'check_out_time' => date('Y-m-d H:i:s'),
                'check_out_lat' => $latitud,
                'check_out_lon' => $longitud,
                'horas_calculadas' => round($horasCalculadas, 2)
            ]);

            $auditoriaModel->registrar(
                $user_id,
                'Check-out',
                'jornadas',
                $jornadaActiva['id'],
                ['latitud' => $latitud, 'longitud' => $longitud, 'horas' => $horasCalculadas]
            );

            echo json_encode(['success' => true]);
            break;

        case 'historial':
            checkRole(['Promotor']);

            $jornadas = $jornadaModel->getByPromotor($user_id, 10);
            echo json_encode(['success' => true, 'jornadas' => $jornadas]);
            break;

        case 'activa':
            checkRole(['Promotor']);

            $jornadaActiva = $jornadaModel->getJornadaActivaHoy($user_id);
            echo json_encode(['success' => true, 'jornada' => $jornadaActiva]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
