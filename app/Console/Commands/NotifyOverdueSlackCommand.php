<?php

namespace App\Console\Commands;

use App\Services\OverdueSlackNotifier;
use Illuminate\Console\Command;

class NotifyOverdueSlackCommand extends Command
{
    protected $signature = 'library:notify-overdue-slack
                            {--dry-run : Slack に送らず内容だけ表示する}
                            {--sample : 期限切れがなくても実験用メッセージを送る}
                            {--to= : 実験用 DM の宛先メール}';

    protected $description = '返却期限切れの貸出を Slack に通知する';

    public function handle(OverdueSlackNotifier $notifier): int
    {
        if ($this->option('sample')) {
            return $this->sendSample($notifier);
        }

        $loans = $notifier->overdueLoans();

        if ($loans->isEmpty()) {
            $this->info('期限切れはありません。送信しません。');

            return self::SUCCESS;
        }

        $this->info('期限切れ '.$loans->count().' 件を通知します。');
        foreach ($loans as $loan) {
            $name = $loan->user?->name ?? '不明な社員';
            $title = $loan->book?->title ?? '削除された書籍';
            $this->line("• {$name} 『{$title}』");
        }

        if ($this->option('dry-run')) {
            $this->comment('dry-run のため送信していません。');

            return self::SUCCESS;
        }

        if (! $notifier->hasAnyDestination()) {
            $this->warn('SLACK_OVERDUE_WEBHOOK_URL と SLACK_BOT_TOKEN が未設定のため送信しません。');

            return self::SUCCESS;
        }

        try {
            $result = $notifier->notify($loans);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['channel']) {
            $this->info('管理チャンネルへ送信しました。');
        }

        if ($notifier->botToken() !== '') {
            $this->info('本人への DM: '.$result['dm_sent'].' 件成功 / '.$result['dm_skipped'].' 件スキップ');
            foreach ($result['dm_errors'] as $error) {
                $this->warn($error);
            }
        }

        $this->info('Slack に送信しました（'.$loans->count().' 件）。');

        return self::SUCCESS;
    }

    private function sendSample(OverdueSlackNotifier $notifier): int
    {
        $text = $notifier->buildSampleText();
        $this->info('実験用メッセージを送ります。');
        $this->line($text);

        if ($this->option('dry-run')) {
            $this->comment('dry-run のため送信していません。');

            return self::SUCCESS;
        }

        if ($notifier->webhookUrl() === '') {
            $this->warn('SLACK_OVERDUE_WEBHOOK_URL が未設定のため送信しません。');

            return self::SUCCESS;
        }

        try {
            $notifier->sendText($text);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Slack チャンネルに実験用メッセージを送信しました。');

        $to = strtolower(trim((string) $this->option('to')));

        if ($to === '') {
            return self::SUCCESS;
        }

        if ($notifier->botToken() === '') {
            $this->warn('SLACK_BOT_TOKEN が未設定のため DM は送りません。');

            return self::SUCCESS;
        }

        try {
            $notifier->sendSampleDirectMessage($to);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('本人 DM の実験用メッセージを送信しました。');

        return self::SUCCESS;
    }
}
