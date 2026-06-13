<?php

namespace Tests\Unit\Support;

use App\Support\Mask;
use Tests\TestCase;

class MaskTest extends TestCase
{
    // ─── Mask::string ─────────────────────────────────────────

    public function test_string_masks_odd_index_characters(): void
    {
        // 1*3*5*7* — 索引 1、3、5、7 換成 *
        $this->assertSame('1*3*5*7*', Mask::string('12345678'));
    }

    public function test_string_returns_empty_for_empty_input(): void
    {
        $this->assertSame('', Mask::string(''));
    }

    public function test_string_keeps_single_character_unmasked(): void
    {
        // 單字元只有索引 0（偶數），不遮蔽
        $this->assertSame('a', Mask::string('a'));
    }

    public function test_string_masks_second_of_two_characters(): void
    {
        $this->assertSame('a*', Mask::string('ab'));
    }

    public function test_string_is_multibyte_safe(): void
    {
        // 中*字 — 以「字元」而非「位元組」計數，多位元組安全
        $this->assertSame('中*字', Mask::string('中文字'));
    }

    // ─── Mask::email ──────────────────────────────────────────

    public function test_email_masks_local_part_only(): void
    {
        // local part 遮蔽、@ 之後網域保留原樣
        $this->assertSame('a*m*n@test.com', Mask::email('admin@test.com'));
    }

    public function test_email_masks_short_local_part(): void
    {
        $this->assertSame('a*c@gmail.com', Mask::email('abc@gmail.com'));
    }

    public function test_email_without_at_masks_whole_string(): void
    {
        // 無 @ 時退回 string() 遮蔽整串
        $this->assertSame('n*-*t*s*g*', Mask::email('no-at-sign'));
    }

    public function test_email_with_single_char_local_part_is_not_masked(): void
    {
        // local part 僅一字元（索引 0）不遮蔽 — 記錄此邊界行為
        $this->assertSame('a@b.com', Mask::email('a@b.com'));
    }

    public function test_email_with_empty_local_part(): void
    {
        $this->assertSame('@test.com', Mask::email('@test.com'));
    }

    public function test_email_is_multibyte_safe_in_local_part(): void
    {
        // @ 為 ASCII 切點，local part 交 mb-safe 的 string() 處理
        $this->assertSame('中*@test.com', Mask::email('中文@test.com'));
    }

    public function test_email_splits_on_first_at_sign(): void
    {
        // strpos 取第一個 @；local part 'a'（索引 0）不遮蔽，其餘原樣保留
        $this->assertSame('a@b@c.com', Mask::email('a@b@c.com'));
    }
}
