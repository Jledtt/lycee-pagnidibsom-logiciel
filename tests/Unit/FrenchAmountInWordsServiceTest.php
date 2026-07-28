<?php

namespace Tests\Unit;

use App\Services\FrenchAmountInWordsService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FrenchAmountInWordsServiceTest extends TestCase
{
    #[DataProvider('amounts')]
    public function test_it_spells_fcfa_amounts(int $amount, string $expected): void
    {
        $service = new FrenchAmountInWordsService;

        $this->assertSame($expected, $service->convert($amount));
    }

    public static function amounts(): array
    {
        return [
            [0, 'Zéro francs CFA'],
            [1, 'Un franc CFA'],
            [21, 'Vingt et un francs CFA'],
            [71, 'Soixante et onze francs CFA'],
            [80, 'Quatre-vingts francs CFA'],
            [81, 'Quatre-vingt-un francs CFA'],
            [200, 'Deux cents francs CFA'],
            [201, 'Deux cent un francs CFA'],
            [1000, 'Mille francs CFA'],
            [25000, 'Vingt-cinq mille francs CFA'],
            [80000, 'Quatre-vingt mille francs CFA'],
            [200000, 'Deux cent mille francs CFA'],
            [1000000, 'Un million de francs CFA'],
            [125000000, 'Cent vingt-cinq millions de francs CFA'],
            [125000001, 'Cent vingt-cinq millions un francs CFA'],
        ];
    }

    public function test_it_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FrenchAmountInWordsService)->convert(-1);
    }
}
