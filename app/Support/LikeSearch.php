<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Case-insensitive "contains" search over a few columns, safe for text typed by a user.
 *
 * "!" is the escape character, so a typed % or _ is searched for literally instead of acting as a
 * wildcard. The text always goes in as a bound value; only the column names, which come from code, are
 * written into the SQL. It works on both MySQL and SQLite.
 */
final class LikeSearch
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  non-empty-list<string>  $columns  Trusted column names, never user input.
     */
    public static function apply(Builder $query, array $columns, string $term): void
    {
        $like = self::pattern($term);

        $query->where(function (Builder $inner) use ($columns, $like): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$like]);
            }
        });
    }

    public static function pattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%';
    }
}
