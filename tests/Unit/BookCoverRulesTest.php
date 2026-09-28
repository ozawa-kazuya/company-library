<?php

namespace Tests\Unit;

use App\Support\BookCoverRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BookCoverRulesTest extends TestCase
{
    public function test_accepts_http_url_and_local_cover_path(): void
    {
        $this->assertTrue($this->passes('https://cover.openbd.jp/9784798168494.jpg'));
        $this->assertTrue($this->passes('/covers/aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'));
        $this->assertTrue($this->passes(null));
        $this->assertTrue($this->passes(''));
    }

    public function test_rejects_invalid_cover_values(): void
    {
        $this->assertFalse($this->passes('not-a-url'));
        $this->assertFalse($this->passes('/covers/not-a-uuid'));
        $this->assertFalse($this->passes('data:image/jpeg;base64,AAAA'));
        $this->assertFalse($this->passes('ftp://example.com/cover.jpg'));
        $this->assertFalse($this->passes('http://localhost/cover.jpg'));
        $this->assertFalse($this->passes('http://127.0.0.1/cover.jpg'));
    }

    private function passes(mixed $value): bool
    {
        return Validator::make(
            ['cover' => $value],
            ['cover' => BookCoverRules::validationRules()],
        )->passes();
    }
}
