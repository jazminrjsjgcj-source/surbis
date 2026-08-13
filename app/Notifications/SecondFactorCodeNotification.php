<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Identity\SecondFactorCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SecondFactorCodeNotification extends Notification
{
    /**
     * Publico a proposito: la notificacion solo vive en memoria mientras se
     * envia, y exponerlo evita que las pruebas tengan que leerlo por
     * reflexion. Lo que nunca sale de aqui es el codigo hacia la base: alli
     * solo va su hash.
     */
    public function __construct(public readonly SecondFactorCode $code) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.second_factor.mail_subject'))
            ->greeting(__('auth.second_factor.mail_greeting'))
            ->line(__('auth.second_factor.mail_intro'))
            ->line('**'.$this->code->forHumans().'**')
            ->line(__('auth.second_factor.mail_validity', [
                'minutes' => SecondFactorCode::MINUTES_VALID,
            ]))
            ->line(__('auth.second_factor.mail_warning'));
    }
}
