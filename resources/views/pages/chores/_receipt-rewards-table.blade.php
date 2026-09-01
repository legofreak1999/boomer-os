{{-- The "Rewards" section of the Rewards receipt (see ⚡rewards.blade.php) --}}
<div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead>
            <tr class="text-left text-zinc-500">
                <th class="pr-2 py-1 font-medium">{{ __('Date') }}</th>
                <th class="pr-2 py-1 font-medium">{{ __('Chore') }}</th>
                <th class="pr-2 py-1 font-medium">{{ __('Reward') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr class="border-t border-zinc-100 dark:border-zinc-800" wire:key="reward-{{ $loop->index }}-{{ $line['date'] }}-{{ $line['chore_name'] }}">
                    <td class="pr-2 py-1 whitespace-nowrap">{{ \Carbon\Carbon::parse($line['date'])->format('M j') }}</td>
                    <td class="pr-2 py-1">
                        {{ $line['chore_name'] }}
                        @if (! empty($line['shared_with']))
                            <flux:tooltip content="{{ __('Split with :names', ['names' => implode(', ', $line['shared_with'])]) }}">
                                <flux:icon name="user-group" variant="micro" class="inline size-3.5 text-zinc-400 align-text-bottom" />
                            </flux:tooltip>
                        @endif
                    </td>
                    <td class="pr-2 py-1">
                        @if ($line['bounty_cents'])
                            <flux:badge size="sm" color="amber">&euro;{{ number_format($line['bounty_cents'] / 100, 2, ',', '.') }}</flux:badge>
                        @elseif ($line['reward_note'])
                            <flux:badge size="sm" color="amber">{{ $line['reward_note'] }}</flux:badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
