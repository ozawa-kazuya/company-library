<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\User;
use App\Support\OverdueFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class OverdueSlackNotifier
{
    /**
     * @return Collection<int, Loan>
     */
    public function overdueLoans(): Collection
    {
        return Loan::query()
            ->overdue()
            ->whereNull('slack_notified_at')
            ->with(['user', 'book'])
            ->orderBy('due_date')
            ->get();
    }

    public function webhookUrl(): string
    {
        return trim((string) config('library.slack.overdue_webhook_url', ''));
    }

    public function botToken(): string
    {
        return trim((string) config('library.slack.bot_token', ''));
    }

    public function hasAnyDestination(): bool
    {
        return $this->webhookUrl() !== '' || $this->botToken() !== '';
    }

    public function buildText(Collection $loans): string
    {
        $count = $loans->count();
        $lines = $loans->map(function (Loan $loan) {
            $name = $loan->user?->name ?? '不明な社員';
            $code = $loan->user?->user_code ?? '—';
            $title = $loan->book?->title ?? '削除された書籍';
            $due = $this->dueLabel($loan);
            $overdue = $this->overdueLabel($loan);

            return "• {$name}（{$code}）『{$title}』— {$overdue}（期限 {$due}）";
        })->implode("\n");

        $adminUrl = rtrim((string) config('app.url'), '/').'/admin/overdue';

        return "📚 *返却期限切れが {$count} 件あります*\n\n{$lines}\n\n<{$adminUrl}|管理画面で確認>";
    }

    public function buildSampleText(): string
    {
        $adminUrl = rtrim((string) config('app.url'), '/').'/admin/overdue';

        return "📚 *【実験】返却期限切れ通知のテストです*\n\n期限切れがあれば、管理チャンネルへ一覧、一般利用者へ DM が届きます。\n\n<{$adminUrl}|管理画面で確認>";
    }

    public function buildSampleDirectMessageText(): string
    {
        $dashboardUrl = $this->dashboardUrl();

        return "📚 *【実験】返却期限切れの本人通知テストです*\n\n一般利用者向けです。返却操作はオフィスの社内ネットワークから行ってください。\n\n<{$dashboardUrl}|マイページを開く>";
    }

    public function sendSampleDirectMessage(string $email): void
    {
        $email = strtolower(trim($email));
        $slackUserId = $this->lookupSlackUserId($email);

        if ($slackUserId === null) {
            throw new \RuntimeException("Slack アカウントが見つかりません（{$email}）。図書システムのメールと Slack のメールが一致しているか確認してください。");
        }

        $this->postDirectMessage($slackUserId, $this->buildSampleDirectMessageText());
    }

    public function buildDirectMessageText(Collection $loans): string
    {
        $lines = $loans->map(function (Loan $loan) {
            $title = $loan->book?->title ?? '削除された書籍';
            $due = $this->dueLabel($loan);
            $overdue = $this->overdueLabel($loan);

            return "• 『{$title}』— {$overdue}（期限 {$due}）";
        })->implode("\n");

        $dashboardUrl = $this->dashboardUrl();

        return "📚 *返却期限切れのお知らせ*\n\n次の図書が返却期限を過ぎています。現物はオフィスへお持ちいただき、社内ネットワークに接続した状態でマイページから返却操作をしてください。\n\n{$lines}\n\n<{$dashboardUrl}|マイページを開く>";
    }

    private function dashboardUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/dashboard';
    }

    /**
     * @return array{channel: bool, dm_sent: int, dm_skipped: int, dm_errors: array<int, string>}
     */
    public function notify(Collection $loans): array
    {
        $channel = false;

        if ($this->webhookUrl() !== '') {
            $this->sendText($this->buildText($loans));
            $channel = true;
        }

        $direct = [
            'dm_sent' => 0,
            'dm_skipped' => 0,
            'dm_errors' => [],
        ];

        if ($this->botToken() !== '') {
            $direct = $this->sendDirectMessages($loans);
        }

        $this->markNotified($loans);

        return [
            'channel' => $channel,
            ...$direct,
        ];
    }

    public function send(Collection $loans): void
    {
        $this->notify($loans);
    }

    public function sendText(string $text): void
    {
        $url = $this->webhookUrl();

        if ($url === '') {
            throw new \RuntimeException('SLACK_OVERDUE_WEBHOOK_URL が未設定です。');
        }

        $response = Http::timeout(10)
            ->asJson()
            ->post($url, [
                'text' => $text,
                'unfurl_links' => false,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Slack への送信に失敗しました（HTTP '.$response->status().'）。');
        }
    }

    /**
     * @return array{dm_sent: int, dm_skipped: int, dm_errors: array<int, string>}
     */
    public function sendDirectMessages(Collection $loans): array
    {
        $sent = 0;
        $skipped = 0;
        $errors = [];

        $loans->groupBy(fn (Loan $loan) => $loan->user_id)->each(function (Collection $userLoans) use (&$sent, &$skipped, &$errors) {
            /** @var User|null $user */
            $user = $userLoans->first()?->user;
            $email = strtolower(trim((string) ($user?->email ?? '')));
            $name = $user?->name ?? '不明な社員';

            if ($user?->isAdmin()) {
                $skipped++;
                $errors[] = "{$name} は管理者のため、本人 DM の対象外です。";

                return;
            }

            if ($email === '') {
                $skipped++;
                $errors[] = "{$name} はメール未登録のため DM を送れません。";

                return;
            }

            $slackUserId = $this->lookupSlackUserId($email);

            if ($slackUserId === null) {
                $skipped++;
                $errors[] = "{$name}（{$email}）の Slack アカウントが見つかりません。";

                return;
            }

            try {
                $this->postDirectMessage($slackUserId, $this->buildDirectMessageText($userLoans->values()));
                $sent++;
            } catch (\RuntimeException $e) {
                $errors[] = "{$name}（{$email}）への DM に失敗しました: ".$e->getMessage();
            }
        });

        return [
            'dm_sent' => $sent,
            'dm_skipped' => $skipped,
            'dm_errors' => $errors,
        ];
    }

    private function lookupSlackUserId(string $email): ?string
    {
        $response = Http::timeout(10)
            ->withToken($this->botToken())
            ->acceptJson()
            ->get('https://slack.com/api/users.lookupByEmail', [
                'email' => $email,
            ]);

        $json = $response->json();

        if (! ($json['ok'] ?? false)) {
            return null;
        }

        $id = $json['user']['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function postDirectMessage(string $slackUserId, string $text): void
    {
        $open = Http::timeout(10)
            ->withToken($this->botToken())
            ->acceptJson()
            ->asJson()
            ->post('https://slack.com/api/conversations.open', [
                'users' => $slackUserId,
            ]);

        $channelId = $open->json('channel.id');

        if (! ($open->json('ok')) || ! is_string($channelId) || $channelId === '') {
            throw new \RuntimeException($open->json('error') ?? 'conversations.open に失敗しました');
        }

        $post = Http::timeout(10)
            ->withToken($this->botToken())
            ->acceptJson()
            ->asJson()
            ->post('https://slack.com/api/chat.postMessage', [
                'channel' => $channelId,
                'text' => $text,
                'unfurl_links' => false,
            ]);

        if (! ($post->json('ok'))) {
            throw new \RuntimeException($post->json('error') ?? 'chat.postMessage に失敗しました');
        }
    }

    private function markNotified(Collection $loans): void
    {
        $ids = $loans->pluck('id')->filter()->all();

        if ($ids === []) {
            return;
        }

        Loan::query()->whereIn('id', $ids)->update(['slack_notified_at' => now()]);
    }

    private function dueLabel(Loan $loan): string
    {
        if ($loan->due_date === null) {
            return '—';
        }

        return $loan->due_date->timezone('Asia/Tokyo')->format('n/j');
    }

    private function overdueLabel(Loan $loan): string
    {
        if ($loan->due_date === null) {
            return '期限切れ';
        }

        $seconds = now()->getTimestamp() - $loan->due_date->getTimestamp();

        return OverdueFormatter::label($seconds);
    }
}
