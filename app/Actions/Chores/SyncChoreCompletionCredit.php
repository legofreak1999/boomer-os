<?php

namespace App\Actions\Chores;

use App\Models\ChoreListItem;
use Illuminate\Support\Facades\DB;

class SyncChoreCompletionCredit
{
    /**
     * Re-create an already-checked item's completion credit to match its
     * current assignees. Assignment IS completion credit in this app (see
     * ToggleChoreListItemCompletion), so linking or unlinking someone after
     * the item was already checked off must actually change who's credited
     * — not just who the UI shows as assigned. A no-op while the item is
     * still unchecked: the next check-off will pick up the current
     * assignees on its own.
     */
    public function __invoke(ChoreListItem $item, int $actingUserId): void
    {
        if (! $item->is_checked) {
            return;
        }

        DB::transaction(function () use ($item, $actingUserId) {
            $existing = $item->completions()->orderByDesc('completed_at')->get();

            if ($existing->isEmpty()) {
                // Nobody assigned and no completion exists yet: this is the
                // "unclaimed job" case, not a fresh check-off — don't invent
                // a credit for whoever happens to be resyncing it.
                app(CreditChoreCompletion::class)($item, $actingUserId, now(), null, fallbackToActingUser: false);

                return;
            }

            // Preserve the original batch's timestamp (so this doesn't jump to
            // "completed today" and shift which month it counts toward) and its
            // total bounty (already cleared from the item itself at check time,
            // so it only survives on the completion rows being replaced here).
            $completedAt = $existing->first()->completed_at;
            $totalBountyCents = $existing->every(fn ($completion) => $completion->bounty_cents !== null)
                ? $existing->sum('bounty_cents')
                : null;

            $item->completions()->where('completed_at', $completedAt)->delete();

            // Removing the last assignee must leave the item genuinely
            // unclaimed (matching the Rewards page's "Unclaimed jobs" list),
            // not silently re-credit whoever happened to do the unassigning.
            // ->fresh() matters here: if $item's `users` relation was
            // already cached on this exact instance by an earlier action
            // call (e.g. the initial check-off), a plain loadMissing()
            // downstream would keep serving that stale cached set instead
            // of picking up whatever the caller just attached/detached.
            app(CreditChoreCompletion::class)($item->fresh(), $actingUserId, $completedAt, $totalBountyCents, fallbackToActingUser: false);
        });
    }
}
