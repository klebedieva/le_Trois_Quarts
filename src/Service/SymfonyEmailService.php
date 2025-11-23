<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Entity\Reservation;
use App\Entity\Order;

class SymfonyEmailService
{
    private MailerInterface $mailer;
    private string $fromEmail;
    private string $fromName;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
        $this->fromEmail = 'contact@letroisquarts.online';
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
                ->to(new Address($this->fromEmail, $this->fromName)) // Admin receives at contact@letroisquarts.online
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
                    <p>contact@letroisquarts.online | 04 91 92 96 16</p>
                    <p>139 Boulevard Chave, 13005 Marseille</p>
                </div>
            </div>
        </body>
        </html>";
    }

    public function sendReservationConfirmation(string $clientEmail, string $clientName, string $subject, 
    string $message, Reservation $reservation): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($clientEmail, $clientName))
                ->replyTo(new Address($this->fromEmail, $this->fromName))
                ->subject($subject)
                ->html($this->getReservationConfirmationTemplate($clientName, $message, $reservation));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Error sending reservation confirmation: " . $e->getMessage());
        }
    }

    private function getReservationConfirmationTemplate(string $clientName, string $message, Reservation $reservation): string
    {
        $date = $reservation->getDate()->format('d/m/Y');
        $time = $reservation->getTime();
        $guests = $reservation->getGuests();
        $guestsText = $guests == 1 ? '1 personne' : $guests . ' personnes';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmation de réservation - Le Trois Quarts</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d4a574, #8b4513); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { font-family: 'Playfair Display', serif; font-weight: 700; margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; background-color: #f8f9fa; }
                .reservation-details { background-color: white; padding: 20px; border: 2px solid #d4a574; border-radius: 8px; margin: 20px 0; }
                .reservation-details h3 { font-family: 'Playfair Display', serif; color: #8b4513; margin-top: 0; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #8b4513; }
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
                    
                    <p style='font-size: 16px; margin-top: 25px; margin-bottom: 0;'>Cordialement,<br><strong>L'équipe du Trois Quarts</strong></p>
                </div>
                <div class='footer'>
                    <p>139 Boulevard Chave, 13005 Marseille | 04 91 92 96 16</p>
                </div>
            </div>
        </body>
        </html>";
    }

    public function sendReservationCancellation(string $clientEmail, string $clientName, string $subject, Reservation $reservation): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($clientEmail, $clientName))
                ->replyTo(new Address($this->fromEmail, $this->fromName))
                ->subject($subject)
                ->html($this->getReservationCancellationTemplate($clientName, $reservation));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Error sending reservation cancellation: " . $e->getMessage());
        }
    }

    private function getReservationCancellationTemplate(string $clientName, Reservation $reservation): string
    {
        $date = $reservation->getDate()->format('d/m/Y');
        $time = $reservation->getTime();
        $guests = $reservation->getGuests();
        $guestsText = $guests == 1 ? '1 personne' : $guests . ' personnes';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Annulation de réservation - Le Trois Quarts</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d4a574, #8b4513); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { font-family: 'Playfair Display', serif; font-weight: 700; margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; background-color: #f8f9fa; }
                .reservation-details { background-color: white; padding: 20px; border: 2px solid #d4a574; border-radius: 8px; margin: 20px 0; }
                .reservation-details h3 { font-family: 'Playfair Display', serif; color: #8b4513; margin-top: 0; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #8b4513; }
                .cancellation-notice { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 15px 0; }
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
                    <p style='font-size: 16px; margin-bottom: 20px;'>Nous vous informons que votre réservation a été annulée.</p>
                    
                    <div class='cancellation-notice'>
                        <p style='margin: 0; font-weight: 600; color: #856404;'>⚠️ Réservation annulée</p>
                    </div>
                    
                    <div class='reservation-details'>
                        <h3>📅 Détails de la réservation annulée</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Date :</span>
                            <span>{$date}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Heure :</span>
                            <span>{$time}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Nombre de personnes :</span>
                            <span>{$guestsText}</span>
                        </div>
                    </div>
                    
                    <p style='font-size: 16px; margin-top: 25px; margin-bottom: 15px;'>Nous nous excusons pour tout inconvénient causé.</p>
                    <p style='font-size: 16px; margin-bottom: 15px;'>N'hésitez pas à nous contacter si vous souhaitez effectuer une nouvelle réservation.</p>
                    <p style='font-size: 16px; margin-bottom: 0;'>Cordialement,<br><strong>L'équipe du Trois Quarts</strong></p>
                </div>
                <div class='footer'>
                    <p>139 Boulevard Chave, 13005 Marseille | 04 91 92 96 16</p>
                </div>
            </div>
        </body>
        </html>";
    }

    public function sendReservationNotificationToAdmin(Reservation $reservation): bool
    {
        try {
            $clientName = $reservation->getFirstName() . ' ' . $reservation->getLastName();
            $clientEmail = $reservation->getEmail();
            $date = $reservation->getDate() ? $reservation->getDate()->format('d/m/Y') : 'Non spécifiée';
            $time = $reservation->getTime() ?: 'Non spécifié';
            $guests = $reservation->getGuests() ?: 0;
            $phone = $reservation->getPhone() ?: 'Non spécifié';
            $message = $reservation->getMessage() ?: '';

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->fromEmail, $this->fromName)) // Admin receives at contact@letroisquarts.online
                ->replyTo(new Address($clientEmail, $clientName))
                ->subject("🔔 Nouvelle demande de réservation: " . $clientName)
                ->html($this->getReservationAdminNotificationTemplate($clientName, $clientEmail, $date, $time, $guests, $phone, $message));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Error sending reservation notification: " . $e->getMessage());
        }
    }

    private function getReservationAdminNotificationTemplate(string $clientName, string $clientEmail, string $date, string $time, int $guests, string $phone, string $message): string
    {
        $guestsText = $guests == 1 ? '1 personne' : $guests . ' personnes';
        $currentTime = date('d/m/Y H:i');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Nouvelle demande de réservation - Le Trois Quarts</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d4a574, #8b4513); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { font-family: 'Playfair Display', serif; font-weight: 700; margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; background-color: #f8f9fa; }
                .info { background-color: white; padding: 25px; margin: 20px 0; border-left: 4px solid #d4a574; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .info h3 { font-family: 'Playfair Display', serif; font-weight: 600; color: #8b4513; margin-top: 0; margin-bottom: 15px; font-size: 18px; }
                .reservation-details { background-color: white; padding: 20px; border: 2px solid #d4a574; border-radius: 8px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #8b4513; }
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
                    <h1>Nouvelle demande de réservation</h1>
                    <p>Le Trois Quarts - Brasserie Marseille</p>
                </div>
                <div class='content'>
                    <div class='info'>
                        <h3>Informations du client</h3>
                        <p><strong>Nom complet:</strong> {$clientName}</p>
                        <p><strong>Email:</strong> <a href='mailto:{$clientEmail}'>{$clientEmail}</a></p>
                        <p><strong>Téléphone:</strong> <a href='tel:{$phone}'>{$phone}</a></p>
                        <p><strong>Demande reçue le:</strong> {$currentTime}</p>
                    </div>
                    
                    <div class='reservation-details'>
                        <h3>📅 Détails de la réservation</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Date :</span>
                            <span>{$date}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Heure :</span>
                            <span>{$time}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Nombre de personnes :</span>
                            <span>{$guestsText}</span>
                        </div>
                    </div>
                    
                    " . (!empty($message) ? "
                    <div class='info'>
                        <h3>Message du client</h3>
                        <div class='message-box'>
                            " . nl2br(htmlspecialchars($message)) . "
                        </div>
                    </div>
                    " : "") . "
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://127.0.0.1:8000/admin/reservation' class='btn btn-admin'>Gérer dans l'admin</a>
                        <a href='mailto:{$clientEmail}?subject=Re: Réservation pour le {$date}' class='btn btn-reply'>Répondre au client</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>contact@letroisquarts.online | 04 91 92 96 16</p>
                    <p>139 Boulevard Chave, 13005 Marseille</p>
                </div>
            </div>
        </body>
        </html>";
    }

    public function sendOrderConfirmation(string $clientEmail, string $clientName, string $subject, string $message, Order $order): bool
    {
        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($clientEmail, $clientName))
                ->replyTo(new Address($this->fromEmail, $this->fromName))
                ->subject($subject)
                ->html($this->getOrderConfirmationTemplate($clientName, $message, $order));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Error sending order confirmation: " . $e->getMessage());
        }
    }

    private function getOrderConfirmationTemplate(string $clientName, string $message, Order $order): string
    {
        $orderNumber = $order->getNo();
        $total = $order->getTotal();
        $deliveryMode = $order->getDeliveryMode()->value === 'delivery' ? 'Livraison' : 'À emporter';
        $paymentMode = match($order->getPaymentMode()->value) {
            'card' => 'Carte bancaire',
            'cash' => 'Espèces',
            'tickets' => 'Tickets restaurant',
            default => 'Non spécifié'
        };

        // Format order items
        $itemsHtml = '';
        foreach ($order->getItems() as $item) {
            $itemsHtml .= "
                <div class='detail-row'>
                    <span class='detail-label'>{$item->getQuantity()}x {$item->getProductName()}</span>
                    <span>{$item->getTotal()}€</span>
                </div>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Confirmation de commande - Le Trois Quarts</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d4a574, #8b4513); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { font-family: 'Playfair Display', serif; font-weight: 700; margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; background-color: #f8f9fa; }
                .order-details { background-color: white; padding: 20px; border: 2px solid #d4a574; border-radius: 8px; margin: 20px 0; }
                .order-details h3 { font-family: 'Playfair Display', serif; color: #8b4513; margin-top: 0; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #8b4513; }
                .total-row { background-color: #f4e4c1; padding: 15px; border-radius: 4px; margin-top: 15px; font-weight: 600; font-size: 18px; }
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
                    
                    <p style='font-size: 16px; margin: 0 0 12px 0;'>Votre commande <strong>#{$orderNumber}</strong> a été confirmée et sera préparée rapidement.</p>
                    <p style='font-size: 16px; margin: 0 0 20px 0;'>Nous vous la livrerons à l'adresse indiquée. Notre coursier vous contactera à son arrivée.</p>
                    
                    <div class='order-details'>
                        <h3>🍽️ Détails de votre commande</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Numéro de commande :</span>
                            <span>{$orderNumber}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Mode de livraison :</span>
                            <span>{$deliveryMode}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Mode de paiement :</span>
                            <span>{$paymentMode}</span>
                        </div>
                        <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
                        <h4 style='color: #8b4513; margin-bottom: 15px;'>Articles commandés :</h4>
                        {$itemsHtml}
                        <div class='total-row'>
                            <span class='detail-label'>Total :</span>
                            <span>{$total}€</span>
                        </div>
                    </div>
                    
                    <p style='font-size: 16px; margin-top: 25px; margin-bottom: 15px;'>Nous vous remercions pour votre commande !</p>
                    <p style='font-size: 16px; margin-bottom: 0;'>Cordialement,<br><strong>L'équipe du Trois Quarts</strong></p>
                </div>
                <div class='footer'>
                    <p>139 Boulevard Chave, 13005 Marseille | 04 91 92 96 16</p>
                </div>
            </div>
        </body>
        </html>";
    }

    public function sendOrderNotificationToAdmin(Order $order): bool
    {
        try {
            $clientName = trim(($order->getClientFirstName() ?? '') . ' ' . ($order->getClientLastName() ?? '')) ?: ($order->getClientName() ?? 'Client');
            $clientEmail = $order->getClientEmail() ?: '';
            $subject = "🔔 Nouvelle commande: " . $order->getNo();

            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->fromEmail, $this->fromName))
                ->replyTo(new Address($clientEmail ?: $this->fromEmail, $clientName))
                ->subject($subject)
                ->html($this->getOrderAdminNotificationTemplate($order));

            $this->mailer->send($email);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Error sending order notification: " . $e->getMessage());
        }
    }

    private function getOrderAdminNotificationTemplate(Order $order): string
    {
        $clientName = trim(($order->getClientFirstName() ?? '') . ' ' . ($order->getClientLastName() ?? '')) ?: ($order->getClientName() ?? 'Client');
        $clientEmail = $order->getClientEmail() ?: '';
        $phone = $order->getClientPhone() ?: '';
        $orderNumber = $order->getNo();
        $total = $order->getTotal();
        $deliveryMode = $order->getDeliveryMode()->value === 'delivery' ? 'Livraison' : 'À emporter';
        $paymentMode = match($order->getPaymentMode()->value) {
            'card' => 'Carte bancaire',
            'cash' => 'Espèces',
            'tickets' => 'Tickets restaurant',
            default => 'Non spécifié'
        };

        $addressHtml = '';
        if ($order->getDeliveryMode()->value === 'delivery') {
            $addr = htmlspecialchars((string)($order->getDeliveryAddress() ?? ''));
            $zip = htmlspecialchars((string)($order->getDeliveryZip() ?? ''));
            $instr = htmlspecialchars((string)($order->getDeliveryInstructions() ?? ''));
            $fee = $order->getDeliveryFee();
            $addressHtml = "
                <div class='detail-row'><span class='detail-label'>Adresse:</span><span>{$addr}</span></div>
                <div class='detail-row'><span class='detail-label'>Code postal:</span><span>{$zip}</span></div>
                " . (!empty($instr) ? "<div class='detail-row'><span class='detail-label'>Instructions:</span><span>{$instr}</span></div>" : '') . "
                <div class='detail-row'><span class='detail-label'>Frais de livraison:</span><span>{$fee}€</span></div>
            ";
        }

        $itemsHtml = '';
        foreach ($order->getItems() as $item) {
            $itemsHtml .= "
                <div class='detail-row'>
                    <span class='detail-label'>{$item->getQuantity()}x {$item->getProductName()}</span>
                    <span>{$item->getTotal()}€</span>
                </div>";
        }

        $currentTime = date('d/m/Y H:i');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Nouvelle commande - Le Trois Quarts</title>
            <style>
                body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #d4a574, #8b4513); color: white; padding: 30px 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .header h1 { font-family: 'Playfair Display', serif; font-weight: 700; margin: 0; font-size: 28px; }
                .content { padding: 30px 20px; background-color: #f8f9fa; }
                .info { background-color: white; padding: 25px; margin: 20px 0; border-left: 4px solid #d4a574; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .info h3 { font-family: 'Playfair Display', serif; font-weight: 600; color: #8b4513; margin-top: 0; margin-bottom: 15px; font-size: 18px; }
                .order-details { background-color: white; padding: 20px; border: 2px solid #d4a574; border-radius: 8px; margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
                .detail-label { font-weight: 600; color: #8b4513; }
                .total-row { background-color: #f4e4c1; padding: 15px; border-radius: 4px; margin-top: 15px; font-weight: 600; font-size: 18px; }
                .footer { background-color: #2c1810; color: white; padding: 20px; text-align: center; border-radius: 0 0 8px 8px; font-size: 14px; }
                .btn { display: inline-block; color: white; padding: 12px 25px; text-decoration: none; border-radius: 25px; margin: 10px 8px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s ease; }
                .btn-admin { background: linear-gradient(135deg, #d4a574, #8b4513); }
                .btn-admin:hover { background: linear-gradient(135deg, #8b4513, #d4a574); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Nouvelle commande</h1>
                    <p>Reçue le {$currentTime}</p>
                </div>
                <div class='content'>
                    <div class='info'>
                        <h3>Informations du client</h3>
                        <p><strong>Nom:</strong> {$clientName}</p>
                        <p><strong>Email:</strong> " . ($clientEmail ? "<a href='mailto:{$clientEmail}'>{$clientEmail}</a>" : '—') . "</p>
                        <p><strong>Téléphone:</strong> " . ($phone ? "<a href='tel:{$phone}'>{$phone}</a>" : '—') . "</p>
                    </div>
                    <div class='order-details'>
                        <h3>🍽️ Détails de la commande</h3>
                        <div class='detail-row'><span class='detail-label'>N°:</span><span>{$orderNumber}</span></div>
                        <div class='detail-row'><span class='detail-label'>Livraison:</span><span>{$deliveryMode}</span></div>
                        <div class='detail-row'><span class='detail-label'>Paiement:</span><span>{$paymentMode}</span></div>
                        {$addressHtml}
                        <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
                        <h4 style='color: #8b4513; margin-bottom: 15px;'>Articles :</h4>
                        {$itemsHtml}
                        <div class='total-row'><span class='detail-label'>Total :&nbsp;</span><span>{$total}€</span></div>
                    </div>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://127.0.0.1:8000/admin/order' class='btn btn-admin'>Voir dans l'admin</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>contact@letroisquarts.online | 04 91 92 96 16</p>
                    <p>139 Boulevard Chave, 13005 Marseille</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
