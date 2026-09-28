<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends ResetPasswordNotification
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【図書管理システム】パスワードの再設定')
            ->greeting($notifiable->name.' さん')
            ->line('パスワード再設定の依頼を受け付けました。次のボタンから新しいパスワードを設定してください。')
            ->action('パスワードを再設定する', $this->resetUrl($notifiable))
            ->line('この操作に心当たりがない場合は、メールを破棄してください。')
            ->salutation('図書管理システム');
    }
}
