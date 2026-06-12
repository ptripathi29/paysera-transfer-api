<?php

declare(strict_types=1);

namespace App\DTO;

use App\Validator\Constraints\ValidTransferAmount;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateTransferRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[SerializedName('source_account_id')]
        public readonly ?string $sourceAccountId = null,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        #[SerializedName('destination_account_id')]
        public readonly ?string $destinationAccountId = null,

        #[Assert\NotBlank]
        #[ValidTransferAmount]
        public readonly ?string $amount = null,
    ) {
    }

    public function requestHash(): string
    {
        return hash('sha256', implode('|', [
            $this->sourceAccountId ?? '',
            $this->destinationAccountId ?? '',
            $this->amount ?? '',
        ]));
    }
}
