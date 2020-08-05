<?php

declare(strict_types=1);

namespace Rector\Order\Rector\Class_;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\Order\PropertyRanker;
use Rector\Order\StmtOrder;

/**
 * @see \Rector\Order\Tests\Rector\Class_\OrderPropertyByComplexityRector\OrderPropertyByComplexityRectorTest
 */
final class OrderPropertyByComplexityRector extends AbstractRector
{
    /**
     * @var string
     */
    private const RANK = 'rank';

    /**
     * @var string
     */
    private const POSITION = 'position';

    /**
     * @var StmtOrder
     */
    private $stmtOrder;

    /**
     * @var PropertyRanker
     */
    private $propertyRanker;

    public function __construct(PropertyRanker $propertyRanker, StmtOrder $stmtOrder)
    {
        $this->stmtOrder = $stmtOrder;
        $this->propertyRanker = $propertyRanker;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Order properties by complexity, from the simplest like scalars to the most complex, like union or collections',
            [
                new CodeSample(
                    <<<'PHP'
class SomeClass
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var Type
     */
    private $service;

    /**
     * @var int
     */
    private $price;
}
PHP
,
                    <<<'PHP'
class SomeClass implements FoodRecipeInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var int
     */
    private $price;

    /**
     * @var Type
     */
    private $service;
}
PHP
            ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $propertyByVisibilityByPosition = $this->resolvePropertyByVisibilityByPosition($node);

        foreach ($propertyByVisibilityByPosition as $propertyByPosition) {
            $propertyNameToRank = [];
            $propertyPositionByName = [];

            foreach ($propertyByPosition as $position => $property) {
                /** @var string $propertyName */
                $propertyName = $this->getName($property);

                $propertyPositionByName[$position] = $propertyName;
                $propertyNameToRank[$propertyName][self::RANK] = $this->propertyRanker->rank($property);
                $propertyNameToRank[$propertyName][self::POSITION] = $position;
            }

            $sortedPropertyByRank = $this->getSortedPropertiesByRankAndPosition($propertyNameToRank);

            $oldToNewKeys = $this->stmtOrder->createOldToNewKeys($sortedPropertyByRank, $propertyPositionByName);

            $this->stmtOrder->reorderClassStmtsByOldToNewKeys($node, $oldToNewKeys);
        }

        return $node;
    }

    /**
     * @return Property[][]
     */
    private function resolvePropertyByVisibilityByPosition(Class_ $class): array
    {
        $propertyByVisibilityByPosition = [];
        foreach ($class->stmts as $position => $classStmt) {
            if (! $classStmt instanceof Property) {
                continue;
            }

            $visibility = $this->getVisibilityAsString($classStmt);
            $propertyByVisibilityByPosition[$visibility][$position] = $classStmt;
        }

        return $propertyByVisibilityByPosition;
    }

    private function getSortedPropertiesByRankAndPosition(array $propertyNameToRank): array
    {
        uasort(
            $propertyNameToRank,
            function (array $firstArray, array $secondArray): int {
                return [$firstArray[self::RANK], $firstArray[self::POSITION]] <=> [
                    $secondArray[self::RANK],
                    $secondArray[self::POSITION],
                ];
            }
        );

        return array_keys($propertyNameToRank);
    }

    private function getVisibilityAsString(Property $property): string
    {
        if ($property->isPrivate()) {
            return 'private';
        }

        if ($property->isProtected()) {
            return 'protected';
        }

        return 'public';
    }
}
