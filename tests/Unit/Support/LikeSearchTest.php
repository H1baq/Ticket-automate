<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LikeSearch;
use PHPUnit\Framework\TestCase;

class LikeSearchTest extends TestCase
{
    public function test_the_pattern_is_lower_case_and_wrapped_in_wildcards(): void
    {
        $this->assertSame('%wanjiku%', LikeSearch::pattern('WanJiku'));
    }

    public function test_user_typed_wildcards_and_the_escape_character_are_escaped(): void
    {
        $this->assertSame('%100!%%', LikeSearch::pattern('100%'));
        $this->assertSame('%a!_b%', LikeSearch::pattern('a_b'));
        $this->assertSame('%wow!!%', LikeSearch::pattern('wow!'));
        $this->assertSame('%!!!%!_%', LikeSearch::pattern('!%_'));
    }

    public function test_quotes_and_sql_text_stay_plain_text_inside_the_bound_value(): void
    {
        $this->assertSame("%' or 1=1 --%", LikeSearch::pattern("' OR 1=1 --"));
    }
}
