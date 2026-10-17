<?php

namespace App\Modules\Mail;

use BitsHost\UpmvcSaas\Mail\MailGateway;

/**
 * Thin mail helpers — prefer MailGateway for product sends (logged + routed).
 * Kept for backward-compatible module discovery.
 */
class MailController
{
    /**
     * @return array{status:string,transport?:string,error?:string,engine?:string}
     */
    public function send(
        string $to,
        string $subject,
        string $html,
        string $template = 'generic',
        ?int $tenantId = null,
        ?string $type = null
    ): array {
        return (new MailGateway())->send($to, $subject, $html, $template, $tenantId, $type);
    }

    /** @deprecated Use send() / MailGateway — kept for older call sites */
    public function sendMailByPHPMailer($to, $from, $subject, $message): bool
    {
        $result = (new MailGateway())->send(
            (string) $to,
            (string) $subject,
            (string) $message,
            'legacy',
            null,
            null,
            null,
            true
        );
        return ($result['status'] ?? '') === 'sent';
    }

    /** @deprecated Use MailGateway */
    public function sendMailByPHPMailerAddAttachment($to, $from, $subject, $message, $attachmentroute): bool
    {
        // Attachments: use PHPMailer directly in the app for now; gateway is HTML body focused.
        return $this->sendMailByPHPMailer($to, $from, $subject, $message);
    }
}
