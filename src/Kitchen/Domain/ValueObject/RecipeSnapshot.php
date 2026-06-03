<?php

declare(strict_types=1);

namespace App\Kitchen\Domain\ValueObject;

use App\Kitchen\Domain\Aggregate\Recipe;

/**
 * An immutable, frozen copy of the parts of a Recipe that a CookingOrder needs
 * at the moment the order is placed.
 *
 * Why this exists: a Recipe is a living menu item — its ingredients, name, and
 * cooking time can change over time. A cooking order, once placed, must keep
 * cooking the pizza exactly as it was specified when the order was taken; later
 * edits to the Recipe must not retroactively alter work already in flight.
 *
 * Price is deliberately NOT snapshotted: a cooking order has no business use for
 * price. Monetary concerns belong to the Restaurant/Sales contexts.
 */
final readonly class RecipeSnapshot
{
    private const MIN_COOKING_TIME = 1;
    private const MAX_COOKING_TIME = 120;

    public string $name;

    /** @var list<IngredientRequirement> */
    public array $ingredients;

    public int $cookingTimeMinutes;

    /**
     * @param list<IngredientRequirement> $ingredients
     */
    public function __construct(string $name, array $ingredients, int $cookingTimeMinutes)
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Recipe snapshot name must not be empty.');
        }

        if ($ingredients === []) {
            throw new \InvalidArgumentException('Recipe snapshot must have at least one ingredient.');
        }

        foreach ($ingredients as $ingredient) {
            if (!$ingredient instanceof IngredientRequirement) {
                throw new \InvalidArgumentException(
                    'Recipe snapshot ingredients must be IngredientRequirement instances.'
                );
            }
        }

        if ($cookingTimeMinutes < self::MIN_COOKING_TIME || $cookingTimeMinutes > self::MAX_COOKING_TIME) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Recipe snapshot cooking time must be between %d and %d minutes, got %d.',
                    self::MIN_COOKING_TIME,
                    self::MAX_COOKING_TIME,
                    $cookingTimeMinutes
                )
            );
        }

        $this->name = $trimmed;
        $this->ingredients = array_values($ingredients);
        $this->cookingTimeMinutes = $cookingTimeMinutes;
    }

    public static function fromRecipe(Recipe $recipe): self
    {
        return new self(
            $recipe->name()->value(),
            $recipe->ingredients(),
            $recipe->cookingTimeMinutes(),
        );
    }

    public function name(): string
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

    public function cookingTimeMinutes(): int
    {
        return $this->cookingTimeMinutes;
    }

    public function equals(self $other): bool
    {
        if ($this->name !== $other->name
            || $this->cookingTimeMinutes !== $other->cookingTimeMinutes
            || count($this->ingredients) !== count($other->ingredients)
        ) {
            return false;
        }

        foreach ($this->ingredients as $i => $ingredient) {
            if (!$ingredient->equals($other->ingredients[$i])) {
                return false;
            }
        }

        return true;
    }
}
