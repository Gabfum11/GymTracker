<?php
//mette alla prova SetValidator.php, ovvero la classe che decide se una serie o un nome di un esercizio si possono salvare
declare(strict_types=1);

namespace Tests\Unit;

use App\Service\SetValidator;
use App\Service\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SetValidatorTest extends TestCase
{
    /** Ogni riga: [dato in ingresso, risultato atteso]. */
    public static function validSets(): array
    {
        //dati validi che vengono ripuliti
        return [
             //ogni riga è un caso di prova : se do a setvalidator questo dato, deve restituirmi quest'altro
             //se il test fallisce viene mostrato il nome del caso es. "virgola italiana"
            'numeri normali'          => [[['weight_kg' => 45, 'reps' => 8]], [['weight_kg' => 45.0, 'reps' => 8]]],
            'virgola italiana'        => [[['weight_kg' => '42,5', 'reps' => '8']], [['weight_kg' => 42.5, 'reps' => 8]]],
            'punto come decimale'     => [[['weight_kg' => '42.5', 'reps' => 8]], [['weight_kg' => 42.5, 'reps' => 8]]],
            'corpo libero (0 kg)'     => [[['weight_kg' => 0, 'reps' => 12]], [['weight_kg' => 0.0, 'reps' => 12]]],
            'massimo 500 kg'          => [[['weight_kg' => 500, 'reps' => 1]], [['weight_kg' => 500.0, 'reps' => 1]]],
            'quarti di kg'            => [[['weight_kg' => 12.25, 'reps' => 100]], [['weight_kg' => 12.25, 'reps' => 100]]],
        ];
    }

    #[DataProvider('validSets')]
    public function test_valid_sets_are_normalized(array $input, array $expected): void
    {
        $this->assertSame($expected, SetValidator::sets($input));
    }

    /** Ogni riga: un dato che deve essere rifiutato. */
    public static function invalidSets(): array
    //dati sbagliati che devono essere rifiutati
    {
        return [

            'kg negativi'             => [[['weight_kg' => -1, 'reps' => 8]]],
            'oltre 500 kg'            => [[['weight_kg' => 500.5, 'reps' => 8]]],
            'non multiplo di 0,25'    => [[['weight_kg' => 42.3, 'reps' => 8]]],
            'kg non numerici'         => [[['weight_kg' => 'abc', 'reps' => 8]]],
            'kg mancanti'             => [[['reps' => 8]]],
            'zero ripetizioni'        => [[['weight_kg' => 45, 'reps' => 0]]],
            'oltre 100 ripetizioni'   => [[['weight_kg' => 45, 'reps' => 101]]],
            'ripetizioni decimali'    => [[['weight_kg' => 45, 'reps' => 8.5]]],
            'non è una lista'         => ['45 per 8'],
            'lista vuota'             => [[]],
            'più di 20 serie'         => [array_fill(0, 21, ['weight_kg' => 45, 'reps' => 8])],
        ];
    }

    #[DataProvider('invalidSets')]
    public function test_invalid_sets_are_rejected(mixed $input): void
    {
        $this->expectException(ValidationException::class);

        SetValidator::sets($input);
    }
    //il testo dell'errore deve indicare quale serie è sbagliata e il perchè
    public function test_error_message_is_in_italian(): void
    {
        try {
            SetValidator::sets([['weight_kg' => 45, 'reps' => 0]]);
            $this->fail('Doveva rifiutare 0 ripetizioni');
        } catch (ValidationException $e) {
            $this->assertSame('Serie 1: le ripetizioni devono essere un numero intero tra 1 e 100', $e->getMessage());
        }
    }

    public function test_exercise_name_is_cleaned(): void
    {
        $this->assertSame('Dip zavorrati', SetValidator::exerciseName('  dip   zavorrati '));
        $this->assertSame('Èrcole press', SetValidator::exerciseName('èrcole press')); // lettere accentate
    }

    public function test_empty_exercise_name_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        SetValidator::exerciseName('   ');
    }

    public function test_too_long_exercise_name_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        SetValidator::exerciseName(str_repeat('a', 61));
    }
}
