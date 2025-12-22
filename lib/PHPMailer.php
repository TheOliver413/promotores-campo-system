<?php

/**
 * Utilidad mejorada para envío de correos electrónicos usando PHPMailer
 * Con logs detallados para rastreo y debugging
 */

require_once __DIR__ . '/../config/database.php';

$phpmailerPaths = [
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php',
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php',
    __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php',
    __DIR__ . '/../vendor/PHPMailer/src/Exception.php',
    __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php',
    __DIR__ . '/../vendor/PHPMailer/src/SMTP.php',
];

$phpmailerLoaded = false;
foreach ($phpmailerPaths as $i => $path) {
    if ($i % 3 === 0) { // Check first file of each set
        if (file_exists($path)) {
            require_once $path;
            require_once $phpmailerPaths[$i + 1];
            require_once $phpmailerPaths[$i + 2];
            $phpmailerLoaded = true;
            error_log('[PHPMailer] INFO: PHPMailer loaded from: ' . dirname($path));
            break;
        }
    }
}

if (!$phpmailerLoaded) {
    error_log('[PHPMailer] WARNING: PHPMailer library not found. Creating mock classes to prevent errors.');
    
    // Crear namespace mock
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        eval('
            namespace PHPMailer\PHPMailer {
                class Exception extends \Exception {}
                
                class PHPMailer {
                    const ENCRYPTION_STARTTLS = "tls";
                    const ENCRYPTION_SMTPS = "ssl";
                    
                    public $Host;
                    public $Port;
                    public $SMTPAuth;
                    public $Username;
                    public $Password;
                    public $SMTPSecure;
                    public $CharSet;
                    public $SMTPDebug;
                    public $Debugoutput;
                    public $Subject;
                    public $Body;
                    public $AltBody;
                    public $ErrorInfo;
                    
                    public function __construct($exceptions = false) {}
                    public function isSMTP() {}
                    public function setFrom($address, $name = "") {}
                    public function addAddress($address, $name = "") {}
                    public function isHTML($isHtml = true) {}
                    public function send() {
                        error_log("[PHPMailer] ERROR: Cannot send email - PHPMailer not installed. Please run: composer require phpmailer/phpmailer");
                        $this->ErrorInfo = "PHPMailer not installed. Please run: composer require phpmailer/phpmailer";
                        return false;
                    }
                }
                
                class SMTP {}
            }
        ');
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailUtilidad
{
    private static $smtpHost = 'smtp.hostinger.com';
    private static $smtpPort = 587;
    private static $smtpUsername = 'info@sdw.com.co';
    private static $smtpPassword = 'O7=k5M2w#';
    private static $fromEmail = 'info@sdw.com.co';
    private static $fromName = 'Sistema WMS - Notificaciones';
    private static $logToDatabase = true;

    /**
     * Registra un log en la base de datos
     */
    private static function log($nivel, $mensaje, $contexto = [])
    {
        try {
            error_log("[PHPMailer] [{$nivel}] {$mensaje} " . json_encode($contexto));
            
            if (self::$logToDatabase) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    INSERT INTO email_logs (nivel, mensaje, contexto, fecha)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$nivel, $mensaje, json_encode($contexto)]);
            }
        } catch (Exception $e) {
            error_log("[PHPMailer] ERROR logging to database: " . $e->getMessage());
        }
    }

    /**
     * Envía un correo electrónico usando PHPMailer con configuración SMTP
     * 
     * @param array $destinatario Array con 'correo' y 'nombre' del destinatario
     * @param string $asunto Asunto del correo
     * @param string $mensaje Cuerpo del mensaje en HTML
     * @return bool True si el correo se envió correctamente
     * @throws Exception Si hay algún error al enviar el correo
     */
    public static function enviarCorreo($destinatario, $asunto, $mensaje)
    {
        self::log('INFO', 'Iniciando envío de correo', [
            'destinatario' => $destinatario['correo'],
            'asunto' => $asunto
        ]);

        try {
            $mail = new PHPMailer(true); // true enables exceptions

            self::log('DEBUG', 'Configurando servidor SMTP', [
                'host' => self::$smtpHost,
                'port' => self::$smtpPort,
                'username' => self::$smtpUsername
            ]);

            $mail->isSMTP();
            $mail->Host = self::$smtpHost;
            $mail->Port = self::$smtpPort;
            $mail->SMTPAuth = true;
            $mail->Username = self::$smtpUsername;
            $mail->Password = self::$smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use STARTTLS for port 587
            $mail->CharSet = 'UTF-8';
            
            $mail->SMTPDebug = 0; // Set to 2 for detailed debugging
            $mail->Debugoutput = function($str, $level) {
                self::log('DEBUG', "SMTP Debug: {$str}", ['level' => $level]);
            };

            self::log('DEBUG', 'Configurando remitente', [
                'from' => self::$fromEmail,
                'fromName' => self::$fromName
            ]);
            $mail->setFrom(self::$fromEmail, self::$fromName);

            self::log('DEBUG', 'Agregando destinatario', [
                'email' => $destinatario['correo'],
                'nombre' => $destinatario['nombre']
            ]);
            $mail->addAddress($destinatario['correo'], $destinatario['nombre']);

            $mail->Subject = $asunto;
            $mail->isHTML(true);
            $mail->Body = $mensaje;
            $mail->AltBody = strip_tags($mensaje); // Plain text alternative

            self::log('INFO', 'Intentando enviar correo');
            
            if (!$mail->send()) {
                self::log('ERROR', 'Fallo al enviar correo', [
                    'error' => $mail->ErrorInfo
                ]);
                throw new Exception("Error al enviar correo: " . $mail->ErrorInfo);
            }

            self::log('SUCCESS', 'Correo enviado exitosamente', [
                'destinatario' => $destinatario['correo'],
                'asunto' => $asunto
            ]);

            return true;
        } catch (Exception $e) {
            self::log('ERROR', 'Excepción al enviar correo', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new Exception("Error al enviar correo: " . $e->getMessage());
        }
    }

    /**
     * Envía una notificación de observación de pedido
     */
    public static function enviarNotificacionObservacion($destinatario, $pedido, $observaciones)
    {
        $asunto = "Nueva observación en pedido #" . $pedido;

        $mensaje = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Observación</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;">Sistema Promotores</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Sistema de Gestión</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 50px 30px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Estimado/a <strong>' . htmlspecialchars($destinatario['nombre']) . '</strong>,
                            </p>
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Se ha registrado una nueva observación para el pedido <strong>#' . htmlspecialchars($pedido) . '</strong>:
                            </p>
                            <div style="background-color: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin: 20px 0;">
                                <p style="margin: 0 0 10px 0; color: #856404; font-size: 14px; font-weight: 600;">Observaciones:</p>
                                <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">' . htmlspecialchars($observaciones) . '</p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 8px 8px; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 10px 0; color: #999999; font-size: 13px; line-height: 1.5;">
                                Este correo es informativo, por favor no responder este mensaje.
                            </p>
                            <p style="margin: 0; color: #cccccc; font-size: 12px;">
                                © ' . date('Y') . ' Sistema Promotores - Todos los derechos reservados
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        ';

        return self::enviarCorreo($destinatario, $asunto, $mensaje);
    }

    /**
     * Envía una notificación de cambio de estado
     */
    public static function enviarNotificacionEstado($destinatario, $pedido, $estadoNuevo, $estadoAnterior = null)
    {
        $asunto = "Pedido " . $estadoNuevo;

        $tipoPedido = is_numeric($pedido)
            ? 'El pedido <b># ' . htmlspecialchars($pedido)
            : 'El documento <b># ' . htmlspecialchars($pedido);

        $mensaje = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Pedido</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;">Sistema Promotores</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Sistema de Gestión</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 50px 30px; text-align: center;">
                            <p style="margin: 0; color: #333333; font-size: 20px; line-height: 1.6;">
                                ' . $tipoPedido . '</b> fue <strong style="color: #667eea; text-transform: uppercase;">' . htmlspecialchars($estadoNuevo) . '</strong>.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 8px 8px; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 10px 0; color: #999999; font-size: 13px; line-height: 1.5;">
                                Este correo es informativo, por favor no responder este mensaje.
                            </p>
                            <p style="margin: 0; color: #cccccc; font-size: 12px;">
                                © ' . date('Y') . ' Sistema Promotores - Todos los derechos reservados
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        ';

        return self::enviarCorreo($destinatario, $asunto, $mensaje);
    }

    /**
     * Envía una notificación de ruta asignada a un promotor
     */
    public static function enviarNotificacionRutaAsignada($promotor, $rutaData)
    {
        $asunto = "Nueva ruta asignada: " . $rutaData['nombre_ruta'];

        $puntosHtml = '';
        if (!empty($rutaData['puntos'])) {
            $puntosHtml = '<div style="margin: 20px 0;">';
            $puntosHtml .= '<p style="margin: 0 0 10px 0; color: #333333; font-size: 14px; font-weight: 600;">Puntos de visita:</p>';
            $puntosHtml .= '<ul style="margin: 0; padding-left: 20px; color: #666666; font-size: 14px;">';
            foreach ($rutaData['puntos'] as $punto) {
                $puntosHtml .= '<li style="margin-bottom: 5px;">' . htmlspecialchars($punto['nombre']) . ' - ' . htmlspecialchars($punto['direccion']) . '</li>';
            }
            $puntosHtml .= '</ul>';
            $puntosHtml .= '</div>';
        }

        $mensaje = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Ruta Asignada</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;">Sistema Promotores</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Sistema de Gestión</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 50px 30px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Hola <strong>' . htmlspecialchars($promotor['nombre_completo']) . '</strong>,
                            </p>
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Se te ha asignado una nueva ruta para el proyecto <strong>' . htmlspecialchars($rutaData['nombre_proyecto']) . '</strong>:
                            </p>
                            
                            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <table style="width: 100%;" cellpadding="8" cellspacing="0">
                                    <tr>
                                        <td style="color: #666666; font-size: 14px; font-weight: 600; border-bottom: 1px solid #e9ecef;">Ruta:</td>
                                        <td style="color: #333333; font-size: 14px; border-bottom: 1px solid #e9ecef;">' . htmlspecialchars($rutaData['nombre_ruta']) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666; font-size: 14px; font-weight: 600;">Fecha planificada:</td>
                                        <td style="color: #333333; font-size: 14px;">' . htmlspecialchars($rutaData['fecha_planificada']) . '</td>
                                    </tr>
                                </table>
                            </div>
                            
                            ' . $puntosHtml . '
                            
                            <p style="margin: 20px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;">
                                Por favor, revisa los detalles completos en el sistema.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 8px 8px; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 10px 0; color: #999999; font-size: 13px; line-height: 1.5;">
                                Este correo es informativo, por favor no responder este mensaje.
                            </p>
                            <p style="margin: 0; color: #cccccc; font-size: 12px;">
                                © ' . date('Y') . ' Sistema Promotores - Todos los derechos reservados
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        ';

        $destinatario = [
            'correo' => $promotor['email'],
            'nombre' => $promotor['nombre_completo']
        ];

        return self::enviarCorreo($destinatario, $asunto, $mensaje);
    }

    /**
     * Envía una notificación de ruta actualizada a un promotor
     */
    public static function enviarNotificacionRutaActualizada($promotor, $rutaData)
    {
        $asunto = "Ruta actualizada: " . $rutaData['nombre_ruta'];

        $puntosHtml = '';
        if (!empty($rutaData['puntos'])) {
            $puntosHtml = '<div style="margin: 20px 0;">';
            $puntosHtml .= '<p style="margin: 0 0 10px 0; color: #333333; font-size: 14px; font-weight: 600;">Puntos de visita actualizados:</p>';
            $puntosHtml .= '<ul style="margin: 0; padding-left: 20px; color: #666666; font-size: 14px;">';
            foreach ($rutaData['puntos'] as $punto) {
                $puntosHtml .= '<li style="margin-bottom: 5px;">' . htmlspecialchars($punto['nombre']) . ' - ' . htmlspecialchars($punto['direccion']) . '</li>';
            }
            $puntosHtml .= '</ul>';
            $puntosHtml .= '</div>';
        }

        $mensaje = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruta Actualizada</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f4f4f4;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; letter-spacing: -0.5px;">Sistema Promotores</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Sistema de Gestión</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="padding: 50px 30px;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Hola <strong>' . htmlspecialchars($promotor['nombre_completo']) . '</strong>,
                            </p>
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">
                                Se ha actualizado tu ruta del proyecto <strong>' . htmlspecialchars($rutaData['nombre_proyecto']) . '</strong>:
                            </p>
                            
                            <div style="background-color: #fff3cd; padding: 20px; border-left: 4px solid #ffc107; margin: 20px 0;">
                                <p style="margin: 0 0 10px 0; color: #856404; font-size: 14px; font-weight: 600;">⚠️ Atención: Esta ruta ha sido modificada</p>
                            </div>
                            
                            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <table style="width: 100%;" cellpadding="8" cellspacing="0">
                                    <tr>
                                        <td style="color: #666666; font-size: 14px; font-weight: 600; border-bottom: 1px solid #e9ecef;">Ruta:</td>
                                        <td style="color: #333333; font-size: 14px; border-bottom: 1px solid #e9ecef;">' . htmlspecialchars($rutaData['nombre_ruta']) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666; font-size: 14px; font-weight: 600;">Fecha planificada:</td>
                                        <td style="color: #333333; font-size: 14px;">' . htmlspecialchars($rutaData['fecha_planificada']) . '</td>
                                    </tr>
                                </table>
                            </div>
                            
                            ' . $puntosHtml . '
                            
                            <p style="margin: 20px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;">
                                Por favor, revisa los detalles actualizados en el sistema.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-radius: 0 0 8px 8px; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 10px 0; color: #999999; font-size: 13px; line-height: 1.5;">
                                Este correo es informativo, por favor no responder este mensaje.
                            </p>
                            <p style="margin: 0; color: #cccccc; font-size: 12px;">
                                © ' . date('Y') . ' Sistema Promotores - Todos los derechos reservados
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        ';

        $destinatario = [
            'correo' => $promotor['email'],
            'nombre' => $promotor['nombre_completo']
        ];

        return self::enviarCorreo($destinatario, $asunto, $mensaje);
    }
}

try {
    $db = Database::getInstance()->getConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nivel VARCHAR(20) NOT NULL,
            mensaje TEXT NOT NULL,
            contexto TEXT,
            fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_nivel (nivel),
            INDEX idx_fecha (fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    error_log('[PHPMailer] INFO: email_logs table verified/created');
} catch (Exception $e) {
    error_log("[PHPMailer] ERROR creating email_logs table: " . $e->getMessage());
}
