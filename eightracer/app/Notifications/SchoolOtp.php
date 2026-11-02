<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolOtp extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public string $code, public string $purpose)
    {
        $this->onQueue('mail');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject('Kode verifikasi Eightracer')->line('Kode verifikasi Anda: '.$this->code)
            ->line('Berlaku 10 menit dan hanya dapat digunakan sekali. Jangan bagikan kode kepada siapa pun.');
    }
}
