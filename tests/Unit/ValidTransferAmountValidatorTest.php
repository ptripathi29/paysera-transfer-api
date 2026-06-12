<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Validator\Constraints\ValidTransferAmount;
use App\Validator\Constraints\ValidTransferAmountValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class ValidTransferAmountValidatorTest extends TestCase
{
    private ValidTransferAmountValidator $validator;
    private ExecutionContextInterface $context;
  private int $violations = 0;

    protected function setUp(): void
    {
        $this->validator = new ValidTransferAmountValidator();
        $this->violations = 0;

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->method('addViolation')->willReturnCallback(function (): ConstraintViolationBuilderInterface {
            ++$this->violations;

            return $this->createMock(ConstraintViolationBuilderInterface::class);
        });

        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->context->method('buildViolation')->willReturn($builder);
        $this->validator->initialize($this->context);
    }

    #[DataProvider('validAmountProvider')]
    public function testAcceptsValidAmounts(string $amount): void
    {
        $this->validator->validate($amount, new ValidTransferAmount());
        self::assertSame(0, $this->violations);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validAmountProvider(): iterable
    {
        yield 'whole number' => ['10'];
        yield 'decimal' => ['10.5000'];
        yield 'small amount' => ['0.0001'];
    }

    #[DataProvider('invalidAmountProvider')]
    public function testRejectsInvalidAmounts(?string $amount): void
    {
        $this->validator->validate($amount, new ValidTransferAmount());
        self::assertGreaterThan(0, $this->violations);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function invalidAmountProvider(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1.00'];
        yield 'too many decimals' => ['1.12345'];
        yield 'invalid format' => ['abc'];
    }
}
