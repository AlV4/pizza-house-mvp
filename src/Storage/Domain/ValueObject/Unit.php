<?php

declare(strict_types=1);

namespace App\Storage\Domain\ValueObject;

enum Unit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';
    case Piece = 'piece';
}
