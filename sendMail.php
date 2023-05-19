<?php

declare(strict_types=1);

require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable('.');
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SendMail
{
    public function sendMail(string $subject, string $to, string $message, ?string $nameTo = null)
    {
        $email = new PHPMailer();

        //$email->SMTPDebug = 2;
        $email->isSMTP();
        $email->Host       = $_ENV['SMTP_HOST'];
        $email->SMTPAuth   = true;
        $email->Username   = $_ENV['SMTP_USER'];
        $email->Password   = $_ENV['SMTP_PASS'];
        $email->SMTPSecure = 'tls';
        $email->Port       = 587;
        $email->setFrom($_ENV['SMTP_NAME_FROM'], "Alerta Nfe");
        $email->addAddress($to, $nameTo);
        $email->Subject = $subject;
        $email->isHTML(true);
        $email->Body = $message;

        try {
            $response = $email->send();
            if (!$response) throw new Exception('Erro ao enviar Email' . 'Detalhes erro: ' . $email->ErrorInfo, 1007);
        } catch (Exception $e) {
            echo $e->getMessage();
            require_once 'utils.php';
            $utils = new Utils;
            $utils->add_log_error($e);
        }
    }
}
