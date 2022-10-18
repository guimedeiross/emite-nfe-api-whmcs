<?php

declare(strict_types=1);

require 'vendor/autoload.php';
require_once 'utils.php';

$dotenv = Dotenv\Dotenv::createImmutable('.');
$dotenv->load();

use \SendGrid\Mail\Mail;

class SendMail
{
    public function sendMail(string $subject, string $to, string $message, ?string $nameTo = null)
    {
        $email = new Mail;

        $email->setFrom("no-reply-nfeAlert@rowbot.com.br", "Alerta Nfe");
        $email->setSubject($subject);
        $email->addTo($to, $nameTo);
        $email->addContent(
            "text/html",
            $message
        );
        $sendgrid = new \SendGrid($_ENV['SENDGRID_API_KEY']);
        try {
            $response = $sendgrid->send($email);
            $response_json = json_decode($response->body(), true);
            if ($response->body() !== '') throw new Exception('Erro ao enviar Email' . 'Detalhes erro: ' . $response_json['errors'][0]['message'], 1007);
        } catch (Exception $e) {
            $utils = new Utils;
            $utils->add_log_error($e);
        }
    }
}
