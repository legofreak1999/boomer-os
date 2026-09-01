{{--
    Shared by the "Points" and "Points that don't count" sections of the
    Rewards receipt — same columns either way, just a different subset of
    lines (see ⚡rewards.blade.php).
--}}
<div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead>
            <tr class="text-left text-zinc-500">
                <th class="pr-2 py-1 font-medium">{{ __('Date') }}</th>
                <th class="pr-2 py-1 font-medium">{{ __('Chore') }}</th>
                <th class="pr-2 py-1 font-medium text-right">{{ __('Time') }}</th>
                <th class="pr-2 py-1 font-medium text-right">{{ __('Difficulty') }}</th>
                <th class="pr-2 py-1 font-medium">{{ __('Day') }}</th>
                <th class="pr-2 py-1 font-medium text-right">{{ __('Weight') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr class="border-t border-zinc-100 dark:border-zinc-800">
                    <td class="pr-2 py-1 whitespace-nowrap">{{ \Carbon\Carbon::parse($line['date'])->format('M j') }}</td>
                    <td class="pr-2 py-1">
                        {{ $line['chore_name'] }}
                        @if (! empty($line['shared_with']))
                            <flux:tooltip content="{{ __('Split with :names', ['names' => implode(', ', $line['shared_with'])]) }}">
                                <flux:icon name="user-group" variant="micro" class="inline size-3.5 text-zinc-400 align-text-bottom" />
                            </flux:tooltip>
                        @endif
                    </td>
                    <td class="pr-2 py-1 text-right whitespace-nowrap">
                        @if ($line['escalation_bonus_points'] > 0)
                            <flux:tooltip content="{{ __('Missed :n time(s) before this', ['n' => $line['escalation_level']]) }}">
                                <span>{{ $line['base_time_points'] }} +{{ $line['escalation_bonus_points'] }} = {{ $line['time_points'] }}</span>
                            </flux:tooltip>
                        @elseif ($line['completer_count'] > 1)
                            <flux:tooltip content="{{ __('Split :count ways', ['count' => $line['completer_count']]) }}">
                                <span>{{ $line['time_points'] * $line['completer_count'] }} &divide; {{ $line['completer_count'] }} = {{ $line['time_points'] }}</span>
                            </flux:tooltip>
                        @else
                            {{ $line['time_points'] }}
                        @endif
                    </td>
                    <td class="pr-2 py-1 text-right whitespace-nowrap">
                        @if ($line['completer_count'] > 1)
                            <flux:tooltip content="{{ __('Your own rating, split :count ways', ['count' => $line['completer_count']]) }}">
                                <span>{{ $line['base_difficulty_points'] * $line['completer_count'] }} &divide; {{ $line['completer_count'] }} = {{ $line['base_difficulty_points'] }}</span>
                            </flux:tooltip>
                        @else
                            {{ $line['base_difficulty_points'] }}
                        @endif
                        @if ($line['multiplier'] > 1)
                            &times; {{ $line['multiplier'] }} = {{ $line['effective_difficulty_points'] }}
                        @endif
                    </td>
                    <td class="pr-2 py-1">
                        @if ($line['day_bonus_level'] === 'bad')
                            <flux:badge size="sm" color="amber">{{ __('Bad') }}</flux:badge>
                        @elseif ($line['day_bonus_level'] === 'super_bad')
                            <flux:badge size="sm" color="red">{{ __('Super bad') }}</flux:badge>
                        @endif
                    </td>
                    <td class="pr-2 py-1 text-right font-medium whitespace-nowrap">{{ $line['weight'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
