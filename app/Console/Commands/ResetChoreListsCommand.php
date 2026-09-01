<?php

namespace App\Console\Commands;

use App\Models\ChoreList;
use App\Models\ChoreListItem;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('chores:reset')]
#[Description('Reset repeating chore lists that are due today')]
class ResetChoreListsCommand extends Command
{
    public function handle(): void
    {
        $today = Carbon::now('Europe/Amsterdam')->startOfDay();

        $dueListIds = ChoreList::whereNotNull('repeat_type')
            ->get()
            ->filter(fn (ChoreList $list) => $list->shouldResetOn($today))
            ->pluck('id');

        if ($dueListIds->isEmpty()) {
            $this->info('Reset 0 chore list(s).');

            return;
        }

        ChoreList::whereIn('id', $dueListIds)->update(['is_hidden' => false]);

        // Missed items on chores with escalation enabled build up for next time.
        ChoreListItem::whereIn('chore_list_id', $dueListIds)
            ->where('is_checked', false)
            ->whereHas('chore', fn ($query) => $query->where('escalation_increment', '>', 0))
            ->increment('escalation_level');

        // Completed items reset for the new cycle, and their buildup clears
        // — but only once someone is actually credited for them. An item
        // checked off with nobody assigned (see SyncChoreCompletionCredit /
        // the Rewards page's "Unclaimed jobs" list) has no completion
        // recording it was ever done; resetting it here would silently
        // erase that fact with no way to claim it afterward. Leaving it
        // checked keeps it visible to claim until someone does.
        ChoreListItem::whereIn('chore_list_id', $dueListIds)
            ->where('is_checked', true)
            ->whereHas('users')
            ->update(['is_checked' => false, 'escalation_level' => 0]);

        $this->info('Reset '.$dueListIds->count().' chore list(s).');
    }
}
