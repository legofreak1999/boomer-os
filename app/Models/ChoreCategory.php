<?php

namespace App\Models;

use Database\Factories\ChoreCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['name', 'parent_id'])]
class ChoreCategory extends Model
{
    /** @use HasFactory<ChoreCategoryFactory> */
    use HasFactory;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChoreCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChoreCategory::class, 'parent_id');
    }

    public function chores(): HasMany
    {
        return $this->hasMany(Chore::class);
    }

    /**
     * Get the ancestor chain from root to this category (inclusive).
     *
     * @return array<int, self>
     */
    public function ancestors(): array
    {
        $chain = [$this];
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            array_unshift($chain, $current);
        }

        return $chain;
    }

    /**
     * Build a nested category tree from a collection of categories.
     * Returns an array of tree nodes: ['category' => ChoreCategory, 'children' => [...], 'items' => Collection].
     *
     * @param  Collection  $categories  Flat collection of categories (leaf nodes that have items)
     * @param  Collection  $itemsByCategory  Items grouped by chore_category_id
     * @return array<int, array{category: self, children: array, items: Collection}>
     */
    public static function buildTree(Collection $categories, Collection $itemsByCategory): array
    {
        // Collect all categories including ancestors
        $allCategories = collect();
        foreach ($categories as $cat) {
            foreach ($cat->ancestors() as $ancestor) {
                $allCategories[$ancestor->id] = $ancestor;
            }
        }

        // Build tree from root nodes
        $roots = $allCategories->filter(fn ($cat) => $cat->parent_id === null)->sortBy('name');

        return self::buildTreeNodes($roots, $allCategories, $itemsByCategory);
    }

    /**
     * @param  Collection  $nodes
     * @param  Collection  $allCategories
     * @param  Collection  $itemsByCategory
     * @return array<int, array{category: self, children: array, items: Collection}>
     */
    private static function buildTreeNodes(Collection $nodes, Collection $allCategories, Collection $itemsByCategory): array
    {
        $tree = [];

        foreach ($nodes as $node) {
            $children = $allCategories->filter(fn ($cat) => $cat->parent_id === $node->id)->sortBy('name');
            $tree[] = [
                'category' => $node,
                'children' => self::buildTreeNodes($children, $allCategories, $itemsByCategory),
                'items' => ($itemsByCategory[$node->id] ?? collect())->sortBy(fn ($item) => is_object($item) && property_exists($item, 'chore') ? $item->chore->name : $item->name),
            ];
        }

        return $tree;
    }

    /**
     * Get the full path of category names from root to this category.
     *
     * @return string
     */
    public function fullPath(): string
    {
        $parts = [$this->name];
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            array_unshift($parts, $current->name);
        }

        return implode(' > ', $parts);
    }
}
