<?php

declare(strict_types=1);

namespace App\Tests\Unit\Kitchen\Domain\ValueObject;

use App\Kitchen\Domain\ValueObject\RecipeId;
use PHPUnit\Framework\TestCase;

final class RecipeIdTest extends TestCase
{
    private const VALID_ULID = '01HZX9P3K8Q7R6S5T4V3W2X1Y0';

    public function test_accepts_a_valid_uppercase_ulid(): void
    {
        $id = new RecipeId(self::VALID_ULID);

        self::assertSame(self::VALID_ULID, $id->value());
    }

    public function test_accepts_a_lowercase_ulid(): void
    {
        $lowercase = '01hzx9p3k8q7r6s5t4v3w2x1y0';

        $id = new RecipeId($lowercase);

        self::assertSame($lowercase, $id->value());
    }

    public function test_rejects_a_ulid_of_the_wrong_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeId('01HZX9P3K8Q7R6S5T4V3W2X1Y');
    }

    public function test_rejects_a_ulid_whose_leading_character_exceeds_seven(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecipeId('81HZX9P3K8Q7R6S5T4V3W2X1Y0');
    }

    public function test_equals_is_true_for_the_same_value(): void
    {
        $a = new RecipeId(self::VALID_ULID);
        $b = new RecipeId(self::VALID_ULID);

        self::assertTrue($a->equals($b));
    }

    public function test_equals_is_false_for_a_different_value(): void
    {
        $a = new RecipeId(self::VALID_ULID);
        $b = new RecipeId('01HZX9P3K8Q7R6S5T4V3W2X1Y1');

        self::assertFalse($a->equals($b));
    }

    public function test_casts_to_its_underlying_value(): void
    {
        $id = new RecipeId(self::VALID_ULID);

        self::assertSame(self::VALID_ULID, (string) $id);
    }
}
