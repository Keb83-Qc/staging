<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Mail;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['sender_id', 'receiver_id', 'subject', 'body', 'is_read', 'data'];

    protected $casts = [
        'data' => 'array', // Important pour que Laravel gère le JSON
        'is_read' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (Message $message) {
            // 1. On récupère le destinataire
            $receiver = $message->receiver;

            // 2. On récupère le nom de l'expéditeur (ou "Système" si null)
            $senderName = $message->sender
                ? $message->sender->first_name . ' ' . $message->sender->last_name
                : 'Système VIP';

            // 3. Si le destinataire a un email, on envoie
            if ($receiver && $receiver->email) {

                // On utilise Mail::raw pour faire simple (texte brut)
                // Vous pourriez utiliser une "View" pour faire plus joli plus tard
                try {
                    Mail::raw(
                        "Bonjour {$receiver->first_name},\n\n" .
                            "Vous avez reçu un nouveau message interne de : {$senderName}.\n\n" .
                            "Sujet : {$message->subject}\n\n" .
                            "Connectez-vous à votre portail VIP GPI pour lire le contenu et y répondre.\n\n" .
                            "Cordialement,\nL'équipe VIP Services Financiers",

                        function ($mail) use ($receiver, $senderName) {
                            $mail->to($receiver->email)
                                ->subject("Nouveau message interne de $senderName");
                        }
                    );
                } catch (\Exception $e) {
                    // On ne fait rien si l'envoi échoue pour ne pas bloquer l'application
                    // (Utile si vous êtes en local sans serveur mail configuré)
                }
            }
        });
    }

    // L'expéditeur
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Le destinataire
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
