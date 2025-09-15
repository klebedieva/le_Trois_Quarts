<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class SymfonyEmailService
{
    private MailerInterface $mailer;
    private string $fromEmail;
    private string $fromName;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
        $this->fromEmail = 'contact@letroisquarts.com';
        $this->fromName = 'Le Trois Quarts';
    }

    public function sendReplyToClient(string $clientEmail, string $clientName, string $subject, string $message): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($clientEmail, $clientName))
                ->replyTo(new Address($this->fromEmail, $this->fromName))
                ->subject($subject)
                ->html($this->getReplyTemplate($clientName, $message));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Error sending email: " . $e->getMessage());
        }
    }

    public function sendNotificationToAdmin(string $clientEmail, string $clientName, string $subject, string $message): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->fromEmail, $this->fromName)) // Admin receives at contact@letroisquarts.com
                ->replyTo(new Address($clientEmail, $clientName))
                ->subject("🔔 Nouveau message de contact: " . $subject)
                ->html($this->getAdminNotificationTemplate($clientName, $clientEmail, $subject, $message));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Error sending notification: " . $e->getMessage());
        }
    }

    private function getReplyTemplate(string $clientName, string $message): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Réponse de Le Trois Quarts</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d4a574, #8b4513); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { font-family: 'Playfair Display', serif; font-weight: 700; margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; background-color: #f8f9fa; }
                .message-box { background-color: white; padding: 20px; border-left: 4px solid #d4a574; border-radius: 4px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .footer { background-color: #2c1810; color: white; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Le Trois Quarts</h1>
                    <p>Brasserie - Marseille</p>
                </div>
                <div class='content'>
                    <h2 style='font-family: Playfair Display, serif; color: #8b4513; margin-bottom: 20px;'>Bonjour {$clientName},</h2>
                    <div class='message-box'>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                    <p style='font-size: 16px; margin-top: 25px; margin-bottom: 15px;'>Nous espérons vous voir bientôt au restaurant !</p>
                    <p style='font-size: 16px; margin-bottom: 0;'>Cordialement,<br><strong>L'équipe du Trois Quarts</strong></p>
                </div>
                <div class='footer'>
                    <p>139 Boulevard Chave, 13005 Marseille | 04 91 92 96 16</p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getAdminNotificationTemplate(string $clientName, string $clientEmail, string $subject, string $message): string
    {
        $subjectLabels = [
            'reservation' => 'Réservation',
            'commande' => 'Commande',
            'evenement_prive' => 'Événement privé',
            'suggestion' => 'Suggestion',
            'reclamation' => 'Réclamation',
            'autre' => 'Autre'
        ];
        
        $subjectLabel = $subjectLabels[$subject] ?? $subject;
        $currentTime = date('d/m/Y H:i');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Nouveau message de contact - Le Trois Quarts</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d4a574, #8b4513); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { font-family: 'Playfair Display', serif; font-weight: 700; margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; background-color: #f8f9fa; }
                .info { background-color: white; padding: 25px; margin: 20px 0; border-left: 4px solid #d4a574; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .info h3 { font-family: 'Playfair Display', serif; font-weight: 600; color: #8b4513; margin-top: 0; margin-bottom: 15px; font-size: 18px; }
                .message-box { background-color: #f4e4c1; padding: 20px; border-radius: 6px; margin: 15px 0; border: 1px solid #d4a574; }
                .footer { background-color: #2c1810; color: white; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; }
                .btn { display: inline-block; color: white; padding: 12px 25px; text-decoration: none; border-radius: 25px; margin: 10px 8px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s ease; }
                .btn-admin { background: linear-gradient(135deg, #d4a574, #8b4513); }
                .btn-admin:hover { background: linear-gradient(135deg, #8b4513, #d4a574); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
                .btn-reply { background: linear-gradient(135deg, #8b4513, #d4a574); }
                .btn-reply:hover { background: linear-gradient(135deg, #d4a574, #8b4513); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
                .urgent { background-color: #8b4513; }
                .urgent .info { border-left-color: #8b4513; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Nouveau message de contact</h1>
                    <p>Le Trois Quarts - Brasserie Marseille</p>
                </div>
                <div class='content'>
                    <div class='info'>
                        <h3>Informations du client</h3>
                        <p><strong>Nom:</strong> {$clientName}</p>
                        <p><strong>Email:</strong> <a href='mailto:{$clientEmail}'>{$clientEmail}</a></p>
                        <p><strong>Sujet:</strong> {$subjectLabel}</p>
                        <p><strong>Reçu le:</strong> {$currentTime}</p>
                    </div>
                    
                    <div class='info'>
                        <h3>Message du client</h3>
                        <div class='message-box'>
                            " . nl2br(htmlspecialchars($message)) . "
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://127.0.0.1:8000/admin/contact-message' class='btn btn-admin'>Voir dans l'admin</a>
                        <a href='mailto:{$clientEmail}?subject=Re: {$subjectLabel}' class='btn btn-reply'>Répondre directement</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>contact@letroisquarts.com | 04 91 92 96 16</p>
                    <p>139 Boulevard Chave, 13005 Marseille</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
