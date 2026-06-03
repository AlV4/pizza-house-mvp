<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\Aggregate;

use App\Kitchen\Domain\Event\RecipeCreated;
use App\Kitchen\Domain\Event\RecipeIngredientAdded;
use App\Kitchen\Domain\Event\RecipeIngredientRemoved;
use App\Kitchen\Domain\Event\RecipePriceChanged;
use App\Kitchen\Domain\Exception\DuplicateIngredient;
use App\Kitchen\Domain\Exception\IngredientNotInRecipe;
use App\Kitchen\Domain\Exception\RecipeMustHaveAtLeastOneIngredient;
use App\Kitchen\Domain\ValueObject\IngredientRequirement;
use App\Kitchen\Domain\ValueObject\Money;
use App\Kitchen\Domain\ValueObject\RecipeId;
use App\Kitchen\Domain\ValueObject\RecipeName;
use App\Shared\Domain\AggregateRoot;

final class Recipe extends AggregateRoot
{
    private const MIN_COOKING_TIME = 1;
    private const MAX_COOKING_TIME = 120;

    /**
     * @param list<IngredientRequirement> $ingredients
     */
    private function __construct(
        private readonly RecipeId $id,
        private readonly RecipeName $name,
        private array $ingredients,
        private Money $price,
        private readonly int $cookingTimeMinutes,
    ) {
    }

    /**
     * @param list<IngredientRequirement> $ingredients
     */
    public static function create(
        RecipeId $id,
        RecipeName $name,
        array $ingredients,
        Money $price,
        int $cookingTimeMinutes,
    ): self {
        if ($ingredients === []) {
            throw RecipeMustHaveAtLeastOneIngredient::create();
        }

        $seen = [];
        foreach ($ingredients as $ingredient) {
            $key = self::normalizeName($ingredient->name());
            if (isset($seen[$key])) {
                throw DuplicateIngredient::named($ingredient->name());
            }
            $seen[$key] = true;
        }

        if (!$price->isPositive()) {
            throw new \InvalidArgumentException('Recipe price must be strictly positive.');
        }

        if ($cookingTimeMinutes < self::MIN_COOKING_TIME || $cookingTimeMinutes > self::MAX_COOKING_TIME) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cooking time must be between %d and %d minutes, got %d.',
                    self::MIN_COOKING_TIME,
                    self::MAX_COOKING_TIME,
                    $cookingTimeMinutes
                )
            );
        }

        $recipe = new self($id, $name, array_values($ingredients), $price, $cookingTimeMinutes);

        $recipe->recordEvent(new RecipeCreated(
            $id->value(),
            $name->value(),
            $price->amount(),
            $price->currency(),
        ));

        return $recipe;
    }

    public function addIngredient(IngredientRequirement $requirement): void
    {
        if ($this->hasIngredientNamed($requirement->name())) {
            throw DuplicateIngredient::named($requirement->name());
        }

        $this->ingredients[] = $requirement;

        $this->recordEvent(new RecipeIngredientAdded(
            $this->id->value(),
            $requirement->name(),
            $requirement->quantity(),
            $requirement->unit()->value,
        ));
    }

    public function removeIngredient(string $ingredientName): void
    {
        if (!$this->hasIngredientNamed($ingredientName)) {
            throw IngredientNotInRecipe::named($ingredientName);
        }

        if (count($this->ingredients) === 1) {
            throw RecipeMustHaveAtLeastOneIngredient::create();
        }

        $key = self::normalizeName($ingredientName);
        $this->ingredients = array_values(array_filter(
            $this->ingredients,
            static fn (IngredientRequirement $ingredient): bool
                => self::normalizeName($ingredient->name()) !== $key
        ));

        $this->recordEvent(new RecipeIngredientRemoved(
            $this->id->value(),
            $ingredientName,
        ));
    }

    public function changePrice(Money $newPrice): void
    {
        if (!$newPrice->isPositive()) {
            throw new \InvalidArgumentException('Recipe price must be strictly positive.');
        }

        if ($newPrice->equals($this->price)) {
            return;
        }

        $oldAmount = $this->price->amount();
        $this->price = $newPrice;

        $this->recordEvent(new RecipePriceChanged(
            $this->id->value(),
            $oldAmount,
            $newPrice->amount(),
            $newPrice->currency(),
        ));
    }

    public function id(): RecipeId
    {
        return $this->id;
    }

    public function name(): RecipeName
    {
        return $this->name;
    }

    /**
     * @return list<IngredientRequirement>
     */
    public function ingredients(): array
    {
        return $this->ingredients;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function cookingTimeMinutes(): int
    {
        return $this->cookingTimeMinutes;
    }

    private function hasIngredientNamed(string $name): bool
    {
        $key = self::normalizeName($name);

        foreach ($this->ingredients as $ingredient) {
            if (self::normalizeName($ingredient->name()) === $key) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
