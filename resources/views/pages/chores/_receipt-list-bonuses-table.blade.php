{{--
    One-time list bonuses (see ⚡rewards.blade.php's $userListBonusLines) —
    a separate record from the main monthly points/rewards receipt, keyed
    by when the list was archived rather than when its items were done.
--}}
<div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead>
            <tr class="text-left text-zinc-500">
                <th class="pr-2 py-1 font-medium">{{ __('Date') }}</th>
                <th class="pr-2 py-1 font-medium">{{ __('List') }}</th>
                <th class="pr-2 py-1 font-medium text-right">{{ __('Your share') }}</th>
                <th class="pr-2 py-1 font-medium text-right">{{ __('Bonus') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr class="border-t border-zinc-100 dark:border-zinc-800" wire:key="list-bonus-{{ $line['list_id'] }}">
                    <td class="pr-2 py-1 whitespace-nowrap">{{ \Carbon\Carbon::parse($line['archived_at'])->format('M j') }}</td>
                    <td class="pr-2 py-1">{{ $line['list_name'] }}</td>
                    <td class="pr-2 py-1 text-right whitespace-nowrap">
                        <flux:tooltip content="{{ __(':weight of :total points on this list', ['weight' => $line['share']['weight'], 'total' => $line['total_weight']]) }}">
                            <span>&euro;{{ number_format($line['share']['share_cents'] / 100, 2, ',', '.') }}</span>
                        </flux:tooltip>
                    </td>
                    <td class="pr-2 py-1 text-right whitespace-nowrap">&euro;{{ number_format($line['bonus_cents'] / 100, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
