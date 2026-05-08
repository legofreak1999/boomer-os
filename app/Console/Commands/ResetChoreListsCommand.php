<?php

namespace App\Console\Commands;

use App\Models\ChoreList;
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

        $lists = ChoreList::whereNotNull('repeat_type')->get();

        $resetCount = 0;

        foreach ($lists as $list) {
            if ($list->shouldResetOn($today)) {
                $list->update(['is_hidden' => false]);
                $list->items()->update(['is_checked' => false]);
                $resetCount++;
            }
        }

        $this->info("Reset {$resetCount} chore list(s).");
    }
}
