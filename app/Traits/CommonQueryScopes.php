<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait CommonQueryScopes
{
    /**
     * Scope a query to filter by date.
     */
    public function scopeFilterByDate(Builder $query, ?string $date, string $column = 'date'): Builder
    {
        if (empty($date)) {
            return $query;
        }

        return $query->whereDate($column, $date);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeFilterByDateRange(
        Builder $query,
        ?string $startDate,
        ?string $endDate,
        string $column = 'date'
    ): Builder {
        if (! empty($startDate)) {
            $query->whereDate($column, '>=', $startDate);
        }

        if (! empty($endDate)) {
            $query->whereDate($column, '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope a query to search by title.
     */
    public function scopeSearchByTitle(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('title', 'LIKE', "%{$search}%");
    }

    /**
     * Scope a query to search by multiple columns.
     */
    public function scopeSearchMultiple(Builder $query, ?string $search, array $columns): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search, $columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', "%{$search}%");
            }
        });
    }

    /**
     * Scope a query to filter by location.
     */
    public function scopeFilterByLocation(Builder $query, ?string $location): Builder
    {
        if (empty($location)) {
            return $query;
        }

        return $query->where('location', 'LIKE', "%{$location}%");
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope a query to order by created date.
     */
    public function scopeLatest(Builder $query, string $column = 'created_at'): Builder
    {
        return $query->orderBy($column, 'desc');
    }

    /**
     * Scope a query to order by oldest date.
     */
    public function scopeOldest(Builder $query, string $column = 'created_at'): Builder
    {
        return $query->orderBy($column, 'asc');
    }
}
