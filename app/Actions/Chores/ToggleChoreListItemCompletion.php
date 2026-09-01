<?php

namespace App\Actions\Chores;

use App\Models\ChoreListItem;

class ToggleChoreListItemCompletion
{
    /**
     * Toggle a chore item's completion. Whoever is currently assigned to the
     * item is who gets credited — there's no separate "who completed this"
     * concept. If nobody is assigned yet, $actingUserId (the person clicking
     * the checkbox) is assigned before crediting, so the UI's assignee list
     * stays truthful about who actually did it. When more than one person is
     * assigned, the effort is split evenly between them.
     */
    public function __invoke(ChoreListItem $item, int $actingUserId): ChoreListItem
    {
        if ($item->is_checked) {
            $latestBatch = $item->completions()->max('completed_at');
            $restoredBountyCents = null;

            if ($latestBatch !== null) {
                $batchCompletions = $item->completions()->where('completed_at', $latestBatch)->get();

                // The bounty was cleared off the item and split across the
                // batch at check time — an accidental uncheck must give it
                // back, not silently lose it, since the chore is no longer
                // considered done.
                $restoredBountyCents = $batchCompletions->every(fn ($completion) => $completion->bounty_cents !== null)
                    ? $batchCompletions->sum('bounty_cents')
                    : null;

                $item->completions()->where('completed_at', $latestBatch)->delete();
            }

            $item->update([
                'is_checked' => false,
                'bounty_cents' => $restoredBountyCents,
            ]);

            return $item->fresh();
        }

        if (! $item->users()->exists()) {
            $item->users()->attach($actingUserId);
            $item->unsetRelation('users');
        }

        app(CreditChoreCompletion::class)($item, $actingUserId, now(), $item->bounty_cents);

        $item->update([
            'is_checked' => true,
            'bounty_cents' => null,
        ]);

        return $item->fresh();
    }
}
