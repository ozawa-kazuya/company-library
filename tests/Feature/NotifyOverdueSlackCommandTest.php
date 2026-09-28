<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotifyOverdueSlackCommandTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://hooks.slack.com/services/T000/B000/XXXX';

    public function test_sends_overdue_loans_to_slack(): void
    {
        Http::fake([
            self::WEBHOOK => Http::response('ok', 200),
        ]);

        config([
            'library.slack.overdue_webhook_url' => self::WEBHOOK,
            'library.slack.bot_token' => '',
            'app.url' => 'http://library.example.test',
        ]);

        $user = User::factory()->create([
            'name' => '山田 花子',
            'user_code' => '042',
        ]);
        $this->createLoan($user, [
            'title' => 'Laravel入門',
            'due_date' => now()->subDays(2),
            'borrowed_at' => now()->subDays(16),
            'duration_days' => 14,
        ]);

        $this->artisan('library:notify-overdue-slack')
            ->expectsOutputToContain('期限切れ 1 件を通知します')
            ->expectsOutputToContain('山田 花子 『Laravel入門』')
            ->expectsOutputToContain('Slack に送信しました（1 件）')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === self::WEBHOOK
                && str_contains((string) $request['text'], '返却期限切れが 1 件あります')
                && str_contains((string) $request['text'], '山田 花子（042）')
                && str_contains((string) $request['text'], 'Laravel入門')
                && str_contains((string) $request['text'], 'http://library.example.test/admin/overdue');
        });
    }

    public function test_does_not_send_when_webhook_is_empty(): void
    {
        Http::fake();

        config([
            'library.slack.overdue_webhook_url' => '',
            'library.slack.bot_token' => '',
        ]);

        $user = User::factory()->create();
        $this->createLoan($user, [
            'due_date' => now()->subDays(3),
            'borrowed_at' => now()->subDays(17),
            'duration_days' => 14,
        ]);

        $this->artisan('library:notify-overdue-slack')
            ->expectsOutputToContain('SLACK_OVERDUE_WEBHOOK_URL と SLACK_BOT_TOKEN が未設定のため送信しません')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_does_not_send_when_there_are_no_overdue_loans(): void
    {
        Http::fake();

        config(['library.slack.overdue_webhook_url' => self::WEBHOOK]);

        $this->artisan('library:notify-overdue-slack')
            ->expectsOutputToContain('期限切れはありません')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_sends_overdue_and_skips_returned_loans(): void
    {
        Http::fake([
            self::WEBHOOK => Http::response('ok', 200),
        ]);

        config([
            'library.slack.overdue_webhook_url' => self::WEBHOOK,
            'library.slack.bot_token' => '',
        ]);

        $user = User::factory()->create();

        $this->createLoan($user, [
            'title' => '期限切れの本',
            'due_date' => now()->subDays(2),
            'borrowed_at' => now()->subDays(16),
            'duration_days' => 14,
        ]);

        $this->createLoan($user, [
            'title' => '返却済み',
            'due_date' => now()->subDays(5),
            'borrowed_at' => now()->subDays(19),
            'duration_days' => 14,
            'returned_at' => now()->subDay(),
        ]);

        $this->artisan('library:notify-overdue-slack')
            ->expectsOutputToContain('期限切れ 1 件を通知します')
            ->expectsOutputToContain('期限切れの本')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === self::WEBHOOK
                && str_contains((string) $request['text'], '期限切れの本')
                && str_contains((string) $request['text'], '日超過');
        });
    }

    public function test_does_not_resend_already_notified_loans(): void
    {
        Http::fake();

        config(['library.slack.overdue_webhook_url' => self::WEBHOOK]);

        $user = User::factory()->create();
        $this->createLoan($user, [
            'due_date' => now()->subDays(2),
            'borrowed_at' => now()->subDays(16),
            'duration_days' => 14,
            'slack_notified_at' => now()->subHour(),
        ]);

        $this->artisan('library:notify-overdue-slack')
            ->expectsOutputToContain('期限切れはありません')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_dry_run_does_not_post_to_slack(): void
    {
        Http::fake();

        config(['library.slack.overdue_webhook_url' => self::WEBHOOK]);

        $user = User::factory()->create(['name' => '佐藤 次郎']);
        $this->createLoan($user, [
            'due_date' => now()->subDays(1),
            'borrowed_at' => now()->subDays(15),
            'duration_days' => 14,
        ]);

        $this->artisan('library:notify-overdue-slack', ['--dry-run' => true])
            ->expectsOutputToContain('佐藤 次郎')
            ->expectsOutputToContain('dry-run のため送信していません')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_sample_sends_test_message_without_overdue_loans(): void
    {
        Http::fake([
            self::WEBHOOK => Http::response('ok', 200),
        ]);

        config([
            'library.slack.overdue_webhook_url' => self::WEBHOOK,
            'app.url' => 'http://library.example.test',
        ]);

        $this->artisan('library:notify-overdue-slack', ['--sample' => true])
            ->expectsOutputToContain('実験用メッセージを送ります')
            ->expectsOutputToContain('Slack チャンネルに実験用メッセージを送信しました')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === self::WEBHOOK
                && str_contains((string) $request['text'], '【実験】返却期限切れ通知のテストです')
                && str_contains((string) $request['text'], 'http://library.example.test/admin/overdue');
        });
    }

    public function test_sample_to_sends_direct_message(): void
    {
        Http::fake([
            self::WEBHOOK => Http::response('ok', 200),
            'https://slack.com/api/users.lookupByEmail*' => Http::response([
                'ok' => true,
                'user' => ['id' => 'U999'],
            ]),
            'https://slack.com/api/conversations.open' => Http::response([
                'ok' => true,
                'channel' => ['id' => 'D999'],
            ]),
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
            ]),
        ]);

        config([
            'library.slack.overdue_webhook_url' => self::WEBHOOK,
            'library.slack.bot_token' => 'xoxb-test-token',
            'app.url' => 'http://library.example.test',
        ]);

        $this->artisan('library:notify-overdue-slack', [
            '--sample' => true,
            '--to' => 'hanako@example.co.jp',
        ])
            ->expectsOutputToContain('本人 DM の実験用メッセージを送信しました')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/chat.postMessage'
                && $request['channel'] === 'D999'
                && str_contains((string) $request['text'], '本人通知テスト')
                && str_contains((string) $request['text'], 'http://library.example.test/dashboard');
        });
    }

    public function test_sends_direct_message_when_bot_token_is_set(): void
    {
        Http::fake([
            self::WEBHOOK => Http::response('ok', 200),
            'https://slack.com/api/users.lookupByEmail*' => Http::response([
                'ok' => true,
                'user' => ['id' => 'U123HANAKO'],
            ]),
            'https://slack.com/api/conversations.open' => Http::response([
                'ok' => true,
                'channel' => ['id' => 'D123HANAKO'],
            ]),
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
            ]),
        ]);

        config([
            'library.slack.overdue_webhook_url' => self::WEBHOOK,
            'library.slack.bot_token' => 'xoxb-test-token',
            'app.url' => 'http://library.example.test',
        ]);

        $user = User::factory()->create([
            'name' => '山田 花子',
            'user_code' => '042',
            'email' => 'hanako@example.co.jp',
        ]);
        $this->createLoan($user, [
            'title' => 'Laravel入門',
            'due_date' => now()->subDays(2),
            'borrowed_at' => now()->subDays(16),
            'duration_days' => 14,
        ]);

        $this->artisan('library:notify-overdue-slack')
            ->expectsOutputToContain('本人への DM: 1 件成功')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/users.lookupByEmail?email=hanako%40example.co.jp';
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/conversations.open'
                && $request['users'] === 'U123HANAKO';
        });
        Http::assertSent(function ($request) {
            return $request->url() === 'https://slack.com/api/chat.postMessage'
                && $request['channel'] === 'D123HANAKO'
                && str_contains((string) $request['text'], '返却期限切れのお知らせ')
                && str_contains((string) $request['text'], 'Laravel入門')
                && str_contains((string) $request['text'], 'http://library.example.test/dashboard')
                && ! str_contains((string) $request['text'], '/admin/');
        });
    }

    public function test_does_not_dm_admin_users(): void
    {
        Http::fake([
            self::WEBHOOK => Http::response('ok', 200),
        ]);

        config([
            'library.slack.overdue_webhook_url' => self::WEBHOOK,
            'library.slack.bot_token' => 'xoxb-test-token',
            'app.url' => 'http://library.example.test',
        ]);

        $admin = User::factory()->admin()->create([
            'name' => '管理者',
            'user_code' => '001',
            'email' => 'admin@example.co.jp',
        ]);
        $this->createLoan($admin, [
            'title' => '管理者が借りた本',
            'due_date' => now()->subDays(2),
            'borrowed_at' => now()->subDays(16),
            'duration_days' => 14,
        ]);

        $this->artisan('library:notify-overdue-slack')
            ->expectsOutputToContain('本人への DM: 0 件成功')
            ->expectsOutputToContain('管理者のため、本人 DM の対象外です')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            return $request->url() === self::WEBHOOK
                && str_contains((string) $request['text'], '管理者が借りた本');
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'slack.com/api/');
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLoan(User $user, array $overrides = []): Loan
    {
        $book = Book::create([
            'title' => $overrides['title'] ?? 'テスト書籍',
            'isbn' => $overrides['isbn'] ?? fake()->unique()->numerify('978##########'),
            'status' => 'rented',
            'copy_number' => 1,
        ]);

        return Loan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'borrowed_at' => $overrides['borrowed_at'] ?? now()->subDays(16),
            'due_date' => $overrides['due_date'] ?? now()->subDays(2),
            'duration_days' => $overrides['duration_days'] ?? 14,
            'returned_at' => $overrides['returned_at'] ?? null,
            'slack_notified_at' => $overrides['slack_notified_at'] ?? null,
        ]);
    }
}
