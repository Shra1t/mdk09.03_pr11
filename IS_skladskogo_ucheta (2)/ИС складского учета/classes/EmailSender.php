<?php
class EmailSender {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    private $isDebugMode;

    public function __construct() {
        // Получаем настройки из конфигурации
        $this->smtpHost = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.mail.ru';
        $this->smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 465;
        $this->smtpUsername = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
        $this->smtpPassword = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $this->fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@example.com';
        $this->fromName = defined('FROM_NAME') ? FROM_NAME : 'Система складского учета';
        
        // Определяем режим разработки
        $this->isDebugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
    }

    public function sendSupportEmail($toEmail, $subject, $message, $replyTo = null) {
        try {
            if (empty($this->smtpPassword)) {
                if ($this->isDebugMode) error_log("SMTP password is empty, using simple mail()");
                return $this->sendSimpleMail($toEmail, $subject, $message, $replyTo);
            }
            
            if ($this->isDebugMode) error_log("Using SMTP method for email sending");
            $result = $this->sendSMTPMail($toEmail, $subject, $message, $replyTo);
            if ($this->isDebugMode) error_log("SMTP result: " . ($result ? 'success' : 'failed'));
            return $result;
            
        } catch (Exception $e) {
            if ($this->isDebugMode) {
                error_log("Email sending error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            return false;
        }
    }

    private function sendSimpleMail($toEmail, $subject, $message, $replyTo = null) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . ($replyTo ?: $this->fromEmail),
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 3'
        ];

        $message = "\xEF\xBB\xBF" . $message;

        $this->logEmailAttempt($toEmail, $subject, 'mail() function');

        if (!function_exists('mail')) {
            error_log("PHP mail() function is not available");
            return $this->sendAlternativeMail($toEmail, $subject, $message, $replyTo);
        }

        $result = mail($toEmail, $subject, $message, implode("\r\n", $headers));
        
        if ($result) {
            error_log("Email sent successfully via mail() to: $toEmail");
        } else {
            error_log("Failed to send email via mail() to: $toEmail");
        }
        
        if (!$result) {
            error_log("mail() function failed for: $toEmail");
            return false;
        }
        
        return $result;
    }

    private function sendAlternativeMail($toEmail, $subject, $message, $replyTo = null) {
        $this->logEmailAttempt($toEmail, $subject, 'alternative method');
        
        error_log("Email sending failed - SMTP not available: $toEmail - $subject");
        
        return false;
    }

    private function sendSMTPMail($toEmail, $subject, $message, $replyTo = null) {
        $this->logEmailAttempt($toEmail, $subject, 'SMTP');
        error_log("Starting SMTP mail to: $toEmail");
        
        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            $smtp = stream_socket_client(
                "ssl://{$this->smtpHost}:{$this->smtpPort}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if (!$smtp) {
                error_log("SMTP SSL connection failed: $errstr ($errno)");
                return false;
            }
            
            // Читаем приветствие сервера
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) != '220') {
                error_log("SMTP server error: $response");
                fclose($smtp);
                return false;
            }
            
            // EHLO команда (SSL уже включен)
            fputs($smtp, "EHLO localhost\r\n");
            $response = fgets($smtp, 515);
            error_log("EHLO response: " . trim($response));
            
            // Читаем все ответы EHLO (многострочный ответ)
            while (substr($response, 3, 1) == '-') {
                $response = fgets($smtp, 515);
                error_log("EHLO continuation: " . trim($response));
            }
            
            // AUTH LOGIN
            fputs($smtp, "AUTH LOGIN\r\n");
            $response = fgets($smtp, 515);
            error_log("AUTH LOGIN response: " . trim($response));
            
            if (substr($response, 0, 3) != '334') {
                error_log("AUTH LOGIN failed: $response");
                fclose($smtp);
                return false;
            }
            
            // Отправляем логин
            fputs($smtp, base64_encode($this->smtpUsername) . "\r\n");
            $response = fgets($smtp, 515);
            error_log("Username auth response: " . trim($response));
            
            if (substr($response, 0, 3) != '334') {
                error_log("Username auth failed: $response");
                fclose($smtp);
                return false;
            }
            
            // Отправляем пароль
            fputs($smtp, base64_encode($this->smtpPassword) . "\r\n");
            $response = fgets($smtp, 515);
            error_log("Password auth response: " . trim($response));
            
            if (substr($response, 0, 3) != '235') {
                error_log("Password auth failed: $response");
                fclose($smtp);
                return false;
            }
            
            // MAIL FROM
            fputs($smtp, "MAIL FROM: <" . $this->fromEmail . ">\r\n");
            $response = fgets($smtp, 515);
            
            if (substr($response, 0, 3) != '250') {
                error_log("MAIL FROM failed: $response");
                fclose($smtp);
                return false;
            }
            
            // RCPT TO
            fputs($smtp, "RCPT TO: <" . $toEmail . ">\r\n");
            $response = fgets($smtp, 515);
            
            if (substr($response, 0, 3) != '250') {
                error_log("RCPT TO failed: $response");
                fclose($smtp);
                return false;
            }
            
            // DATA
            fputs($smtp, "DATA\r\n");
            $response = fgets($smtp, 515);
            
            if (substr($response, 0, 3) != '354') {
                error_log("DATA failed: $response");
                fclose($smtp);
                return false;
            }
            
            // Заголовки письма
            $headers = "From: " . $this->fromName . " <" . $this->fromEmail . ">\r\n";
            $headers .= "To: " . $toEmail . "\r\n";
            $headers .= "Subject: " . $subject . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            if ($replyTo) {
                $headers .= "Reply-To: " . $replyTo . "\r\n";
            }
            $headers .= "\r\n";
            
            // Отправляем заголовки и сообщение
            fputs($smtp, $headers . $message . "\r\n.\r\n");
            $response = fgets($smtp, 515);
            
            if (substr($response, 0, 3) != '250') {
                error_log("Message send failed: $response");
                fclose($smtp);
                return false;
            }
            
            // QUIT
            fputs($smtp, "QUIT\r\n");
            fclose($smtp);
            
            error_log("Email sent successfully via SMTP to: $toEmail");
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            return false;
        }
    }

    private function logEmailAttempt($toEmail, $subject, $method) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $toEmail,
            'subject' => $subject,
            'method' => $method,
            'from' => $this->fromEmail
        ];
        
        error_log("Email attempt: " . json_encode($logData, JSON_UNESCAPED_UNICODE));
    }

    public function testEmailConfiguration() {
        $testMessage = "
        <html>
        <head>
            <title>Тест отправки email</title>
            <meta charset='UTF-8'>
        </head>
        <body>
            <h2>Тест отправки email</h2>
            <p>Это тестовое сообщение для проверки работы системы отправки email.</p>
            <p>Время отправки: " . date('d.m.Y H:i:s') . "</p>
        </body>
        </html>
        ";

        return $this->sendSupportEmail(
            $this->fromEmail,
            'Тест отправки email - Система складского учета',
            $testMessage
        );
    }
}
