<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ActiveAdScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $now = now();
        $isActiveColumn = $builder->qualifyColumn('is_active');
        $startsAtColumn = $builder->qualifyColumn('starts_at');
        $endsAtColumn = $builder->qualifyColumn('ends_at');

        $builder
            ->where($isActiveColumn, true)
            ->where(function (Builder $query) use ($startsAtColumn, $now): void {
                $query
                    ->whereNull($startsAtColumn)
                    ->orWhere($startsAtColumn, '<=', $now);
            })
            ->where(function (Builder $query) use ($endsAtColumn, $now): void {
                $query
                    ->whereNull($endsAtColumn)
                    ->orWhere($endsAtColumn, '>=', $now);
            });
    }
}
