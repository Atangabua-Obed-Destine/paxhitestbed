<?php

namespace Database\Seeders\Concerns;

/**
 * Seed defaults without ever touching a row that already exists.
 *
 * These seeders originally used updateOrCreate, which re-runs cleanly but
 * silently overwrites the existing row. On a live system that is not a safe
 * operation: it wipes the budget-line ordering someone dragged into place,
 * repoints mappings that were deliberately moved, reactivates accounts that
 * were deliberately disabled, and resets an account's category — the last of
 * which can hide real money from every report that sums leaf accounts.
 *
 * Idempotent and safe are different properties. This trait provides the second
 * one: a row that exists is left completely alone, and only genuine gaps are
 * filled. What was created and what was left alone is recorded so the caller
 * can show it before anything is committed.
 */
trait SeedsWithoutOverwriting
{
    public int $createdCount = 0;
    public int $skippedCount = 0;

    /** @var array<int,string> Labels of the rows that were created. */
    public array $createdItems = [];

    /** @var array<int,string> Labels of the rows that could not be created. */
    public array $unresolved = [];

    /**
     * Create the row if it is absent; otherwise return it untouched.
     *
     * @param  class-string  $model
     * @param  array<string,mixed>  $keys    what identifies the row
     * @param  array<string,mixed>  $values  used only when creating
     */
    protected function createIfAbsent(string $model, array $keys, array $values, string $label)
    {
        // A soft-deleted row still occupies its unique key, so firstOrCreate
        // cannot see it and the insert fails on the constraint — one account
        // deleted through the interface would otherwise make the whole install
        // throw. It is also a deliberate removal: quietly resurrecting it would
        // be exactly the kind of overwriting this trait exists to prevent. So
        // it is left alone and reported.
        if ($this->softDeletes($model)) {
            $trashed = $model::onlyTrashed()->where($keys)->first();

            if ($trashed) {
                $this->noteUnresolved($label . ' — deleted here, left deleted');

                return $trashed;
            }
        }

        $row = $model::firstOrCreate($keys, $values);

        if ($row->wasRecentlyCreated) {
            $this->createdCount++;
            $this->createdItems[] = $label;
        } else {
            $this->skippedCount++;
        }

        return $row;
    }

    /** @param  class-string  $model */
    protected function softDeletes(string $model): bool
    {
        return in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($model),
            true
        );
    }

    /**
     * A default that could not be seeded because something it depends on is
     * missing — a category, a faculty, a parent account. Worth reporting: it is
     * the difference between "already present" and "silently absent".
     */
    protected function noteUnresolved(string $label): void
    {
        $this->unresolved[] = $label;
    }

    /**
     * Set a column only when it has never been set.
     *
     * Used for links the seeder guesses at, such as pointing a tuition line at
     * a faculty by keyword. A value already present was either a previous
     * guess or a human decision, and the seeder cannot tell which — so it
     * leaves it.
     */
    protected function fillIfBlank($row, string $column, $value): bool
    {
        if ($value === null || $row->{$column} !== null) {
            return false;
        }

        $row->update([$column => $value]);

        return true;
    }
}
