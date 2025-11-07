<?php

require_once __DIR__ . '/../config/database.php';

class EmailService
{
    private $config;

    public function __construct()
    {
        $this->loadConfig();
    }

    private function loadConfig()
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM configuracion_smtp WHERE activo = 1 LIMIT 1");
        $stmt->execute();
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->config) {
            // Configuración por defecto desde variables de entorno
            $this->config = [
                'host' => getenv('SMTP_HOST') ?: 'smtp.hostinger.com',
                'puerto' => getenv('SMTP_PORT') ?: 587,
                'usuario' => getenv('SMTP_USER') ?: 'info@sdw.com.co',
                'password' => getenv('SMTP_PASSWORD') ?: 'O7=k5M2w#',
                'encriptacion' => getenv('SMTP_ENCRYPTION') ?: 'tls',
                'remitente_email' => getenv('SMTP_FROM_EMAIL') ?: 'info@sdw.com.co',
                'remitente_nombre' => getenv('SMTP_FROM_NAME') ?: 'Sistema Promotores Campo'
            ];
        }
    }

    public function enviarEmail($destinatario, $asunto, $cuerpoHtml, $tipo = 'notificacion')
    {
        $db = Database::getInstance()->getConnection();

        try {
            // Preparar headers
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $this->config['remitente_nombre'] . ' <' . $this->config['remitente_email'] . '>',
                'Reply-To: ' . $this->config['remitente_email'],
                'X-Mailer: PHP/' . phpversion()
            ];

            // Enviar email usando mail() de PHP
            $enviado = mail(
                $destinatario['email'],
                $asunto,
                $cuerpoHtml,
                implode("\r\n", $headers)
            );

            // Registrar en base de datos
            $stmt = $db->prepare("
                INSERT INTO emails_enviados (destinatario_email, destinatario_nombre, asunto, tipo_email, estado, error_mensaje)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $destinatario['email'],
                $destinatario['nombre'] ?? '',
                $asunto,
                $tipo,
                $enviado ? 'enviado' : 'fallido',
                $enviado ? null : 'Error al enviar email'
            ]);

            return $enviado;
        } catch (Exception $e) {
            // Registrar error
            $stmt = $db->prepare("
                INSERT INTO emails_enviados (destinatario_email, destinatario_nombre, asunto, tipo_email, estado, error_mensaje)
                VALUES (?, ?, ?, ?, 'fallido', ?)
            ");

            $stmt->execute([
                $destinatario['email'],
                $destinatario['nombre'] ?? '',
                $asunto,
                $tipo,
                $e->getMessage()
            ]);

            return false;
        }
    }

    public function enviarNotificacionRutaAsignada($promotor, $ruta)
    {
        $asunto = "Nueva Ruta Asignada: {$ruta['nombre_ruta']}";

        $cuerpo = $this->getTemplate('ruta_asignada', [
            'nombre_promotor' => $promotor['nombre_completo'],
            'nombre_ruta' => $ruta['nombre_ruta'],
            'fecha_planificada' => $ruta['fecha_planificada'],
            'num_puntos' => count($ruta['puntos'] ?? []),
            'proyecto' => $ruta['nombre_proyecto'] ?? ''
        ]);

        return $this->enviarEmail(
            ['email' => $promotor['email'], 'nombre' => $promotor['nombre_completo']],
            $asunto,
            $cuerpo,
            'ruta_asignada'
        );
    }

    public function enviarNotificacionRutaActualizada($promotor, $ruta)
    {
        $asunto = "Ruta Actualizada: {$ruta['nombre_ruta']}";

        $cuerpo = $this->getTemplate('ruta_actualizada', [
            'nombre_promotor' => $promotor['nombre_completo'],
            'nombre_ruta' => $ruta['nombre_ruta'],
            'fecha_planificada' => $ruta['fecha_planificada'],
            'num_puntos' => count($ruta['puntos'] ?? [])
        ]);

        return $this->enviarEmail(
            ['email' => $promotor['email'], 'nombre' => $promotor['nombre_completo']],
            $asunto,
            $cuerpo,
            'ruta_actualizada'
        );
    }

    public function enviarResetPassword($usuario, $token)
    {
        $asunto = "Restablecer Contraseña - Sistema Promotores";

        $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
            . "://{$_SERVER['HTTP_HOST']}/reset_password.php?token={$token}";

        $cuerpo = $this->getTemplate('reset_password', [
            'nombre_usuario' => $usuario['nombre_completo'],
            'reset_url' => $resetUrl
        ]);

        return $this->enviarEmail(
            ['email' => $usuario['email'], 'nombre' => $usuario['nombre_completo']],
            $asunto,
            $cuerpo,
            'reset_password'
        );
    }

    public function enviarBienvenida($usuario, $passwordTemporal = null)
    {
        $asunto = "Bienvenido al Sistema de Promotores de Campo";

        $cuerpo = $this->getTemplate('bienvenida', [
            'nombre_usuario' => $usuario['nombre_completo'],
            'email' => $usuario['email'],
            'password_temporal' => $passwordTemporal,
            'login_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                . "://{$_SERVER['HTTP_HOST']}/login.php"
        ]);

        return $this->enviarEmail(
            ['email' => $usuario['email'], 'nombre' => $usuario['nombre_completo']],
            $asunto,
            $cuerpo,
            'registro'
        );
    }

    private function getTemplate($tipo, $datos)
    {
        $baseStyle = "
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1e40af; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
                .button { display: inline-block; padding: 12px 24px; background: #1e40af; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 12px; }
                .info-box { background: white; padding: 15px; border-left: 4px solid #1e40af; margin: 15px 0; }
            </style>
        ";

        switch ($tipo) {
            case 'ruta_asignada':
                return "
                    <!DOCTYPE html>
                    <html>
                    <head>{$baseStyle}</head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Nueva Ruta Asignada</h1>
                            </div>
                            <div class='content'>
                                <p>Hola <strong>{$datos['nombre_promotor']}</strong>,</p>
                                <p>Se te ha asignado una nueva ruta para realizar:</p>
                                <div class='info-box'>
                                    <p><strong>Ruta:</strong> {$datos['nombre_ruta']}</p>
                                    <p><strong>Proyecto:</strong> {$datos['proyecto']}</p>
                                    <p><strong>Fecha Planificada:</strong> {$datos['fecha_planificada']}</p>
                                    <p><strong>Número de Puntos:</strong> {$datos['num_puntos']}</p>
                                </div>
                                <p>Por favor, revisa los detalles de la ruta en el sistema y prepárate para completarla en la fecha indicada.</p>
                                <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/promotor/asignaciones.php' class='button'>Ver Ruta</a>
                            </div>
                            <div class='footer'>
                                <p>Este es un mensaje automático del Sistema de Promotores de Campo</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

            case 'ruta_actualizada':
                return "
                    <!DOCTYPE html>
                    <html>
                    <head>{$baseStyle}</head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Ruta Actualizada</h1>
                            </div>
                            <div class='content'>
                                <p>Hola <strong>{$datos['nombre_promotor']}</strong>,</p>
                                <p>La ruta <strong>{$datos['nombre_ruta']}</strong> ha sido actualizada.</p>
                                <div class='info-box'>
                                    <p><strong>Fecha Planificada:</strong> {$datos['fecha_planificada']}</p>
                                    <p><strong>Número de Puntos:</strong> {$datos['num_puntos']}</p>
                                </div>
                                <p>Por favor, revisa los cambios en el sistema.</p>
                                <a href='" . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/promotor/asignaciones.php' class='button'>Ver Ruta</a>
                            </div>
                            <div class='footer'>
                                <p>Este es un mensaje automático del Sistema de Promotores de Campo</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

            case 'reset_password':
                return "
                    <!DOCTYPE html>
                    <html>
                    <head>{$baseStyle}</head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Restablecer Contraseña</h1>
                            </div>
                            <div class='content'>
                                <p>Hola <strong>{$datos['nombre_usuario']}</strong>,</p>
                                <p>Hemos recibido una solicitud para restablecer tu contraseña.</p>
                                <p>Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                                <a href='{$datos['reset_url']}' class='button'>Restablecer Contraseña</a>
                                <p><small>Este enlace expirará en 1 hora.</small></p>
                                <p>Si no solicitaste este cambio, puedes ignorar este mensaje.</p>
                            </div>
                            <div class='footer'>
                                <p>Este es un mensaje automático del Sistema de Promotores de Campo</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

            case 'bienvenida':
                $passwordInfo = $datos['password_temporal']
                    ? "<p><strong>Contraseña Temporal:</strong> {$datos['password_temporal']}</p>"
                    : "";

                return "
                    <!DOCTYPE html>
                    <html>
                    <head>{$baseStyle}</head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>Bienvenido al Sistema</h1>
                            </div>
                            <div class='content'>
                                <p>Hola <strong>{$datos['nombre_usuario']}</strong>,</p>
                                <p>Tu cuenta ha sido creada exitosamente en el Sistema de Promotores de Campo.</p>
                                <div class='info-box'>
                                    <p><strong>Email:</strong> {$datos['email']}</p>
                                    {$passwordInfo}
                                </div>
                                <p>Puedes iniciar sesión usando el siguiente enlace:</p>
                                <a href='{$datos['login_url']}' class='button'>Iniciar Sesión</a>
                                <p><small>Por seguridad, te recomendamos cambiar tu contraseña después del primer inicio de sesión.</small></p>
                            </div>
                            <div class='footer'>
                                <p>Este es un mensaje automático del Sistema de Promotores de Campo</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

            default:
                return "<p>Notificación del sistema</p>";
        }
    }
}
