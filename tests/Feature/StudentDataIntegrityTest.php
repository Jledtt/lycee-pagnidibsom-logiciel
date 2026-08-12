<?php

namespace Tests\Feature;

use App\Rules\PlausibleStudentBirthDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentDataIntegrityTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('birthDateProvider')]
    public function test_birth_date_plausibility_boundaries(string $birthDate, bool $expected): void
    {
        CarbonImmutable::setTestNow('2026-08-11');

        $validator = Validator::make(
            ['birth_date' => $birthDate],
            ['birth_date' => ['required', 'date', new PlausibleStudentBirthDate]],
        );

        $this->assertSame($expected, $validator->passes());

        if (! $expected) {
            $this->assertSame(PlausibleStudentBirthDate::MESSAGE, $validator->errors()->first('birth_date'));
        }
    }

    public static function birthDateProvider(): array
    {
        return [
            'naissance en 2024 rejetée' => ['2024-01-01', false],
            'quinze ans acceptés' => ['2011-08-11', true],
            'borne six ans acceptée' => ['2020-08-11', true],
            'borne trente ans acceptée' => ['1996-08-11', true],
        ];
    }
}
