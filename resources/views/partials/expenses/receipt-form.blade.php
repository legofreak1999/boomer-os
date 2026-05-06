<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ $formTitle }}</flux:heading>
        <flux:text class="mt-1">{{ $formSubtitle }}</flux:text>
    </div>

    <div class="grid gap-6 lg:grid-cols-2"
        x-data="{
            display: '0',
            firstOperand: null,
            operator: null,
            waitingForSecond: false,
            selectedCategoryId: '',

            inputDigit(digit) {
                if (this.waitingForSecond) {
                    this.display = String(digit);
                    this.waitingForSecond = false;
                } else {
                    this.display = this.display === '0' ? String(digit) : this.display + String(digit);
                }
            },

            inputDecimal() {
                if (this.waitingForSecond) {
                    this.display = '0.';
                    this.waitingForSecond = false;
                    return;
                }
                if (!this.display.includes('.')) {
                    this.display += '.';
                }
            },

            handleOperator(op) {
                const current = parseFloat(this.display);
                if (this.firstOperand !== null && !this.waitingForSecond) {
                    this.calculate();
                }
                this.firstOperand = parseFloat(this.display);
                this.operator = op;
                this.waitingForSecond = true;
            },

            calculate() {
                if (this.firstOperand === null || this.operator === null) return;
                const second = parseFloat(this.display);
                let result = 0;
                switch (this.operator) {
                    case '+': result = this.firstOperand + second; break;
                    case '-': result = this.firstOperand - second; break;
                }
                this.display = String(Math.round(result * 100) / 100);
                this.firstOperand = null;
                this.operator = null;
                this.waitingForSecond = false;
            },

            clear() {
                this.display = '0';
                this.firstOperand = null;
                this.operator = null;
                this.waitingForSecond = false;
            },

            getAmountCents() {
                return Math.round(parseFloat(this.display) * 100);
            },

            addRow() {
                if (!this.selectedCategoryId) return;
                const cents = this.getAmountCents();
                if (cents <= 0) return;
                $wire.addRow(parseInt(this.selectedCategoryId), cents);
                this.clear();
            },
        }"
        x-on:calculator-reset.window="clear()"
    >
        {{-- Left column: Date, Store, Calculator --}}
        <div class="space-y-6">
            {{-- Calendar date selector --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
                {{-- Month navigation --}}
                <div class="flex items-center justify-between mb-3">
                    <flux:button size="sm" icon="chevron-left" variant="ghost" wire:click="previousMonth" />
                    <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->calendarTitle }}</span>
                    <flux:button size="sm" icon="chevron-right" variant="ghost" wire:click="nextMonth" />
                </div>

                {{-- Day headers --}}
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;" class="mb-1">
                    @foreach (['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'] as $dayName)
                        <div class="text-center text-xs font-medium text-zinc-400 dark:text-zinc-500 py-1">{{ $dayName }}</div>
                    @endforeach
                </div>

                {{-- Calendar grid --}}
                <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;">
                    @foreach ($this->calendarWeeks as $week)
                        @foreach ($week as $day)
                            <div style="display: flex; align-items: center; justify-content: center; padding: 2px 0;">
                                @if ($day['isSelected'])
                                    <button
                                        type="button"
                                        wire:click="selectDate('{{ $day['date'] }}')"
                                        style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 700; background: #18181b; color: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3);"
                                        class="dark:!bg-white dark:!text-zinc-900"
                                    >{{ $day['day'] }}</button>
                                @elseif ($day['isToday'])
                                    <button
                                        type="button"
                                        wire:click="selectDate('{{ $day['date'] }}')"
                                        style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 500; outline: 2px solid #a1a1aa; outline-offset: -2px;"
                                        class="text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    >{{ $day['day'] }}</button>
                                @elseif ($day['currentMonth'])
                                    <button
                                        type="button"
                                        wire:click="selectDate('{{ $day['date'] }}')"
                                        style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.875rem; font-weight: 500;"
                                        class="text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                                    >{{ $day['day'] }}</button>
                                @else
                                    <button
                                        type="button"
                                        wire:click="selectDate('{{ $day['date'] }}')"
                                        style="width: 2.25rem; height: 2.25rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.875rem;"
                                        class="text-zinc-300 dark:text-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                    >{{ $day['day'] }}</button>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>

                {{-- Selected date display --}}
                <div class="mt-3 text-center text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    {{ \Carbon\Carbon::parse($date)->isoFormat('dddd D MMMM YYYY') }}
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1 text-zinc-700 dark:text-zinc-300">{{ __('Store') }}</label>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <flux:modal.trigger name="quick-add-store">
                        <flux:button size="sm" icon="plus" variant="ghost" title="{{ __('Add store') }}" />
                    </flux:modal.trigger>
                    <div style="flex: 1;">
                        <flux:select wire:model="storeId" placeholder="{{ __('Select a store...') }}">
                            @foreach ($this->stores as $store)
                                <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </div>

            {{-- Calculator --}}
            <div
                class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-4"
                @keydown.window="
                    if ($event.target.tagName === 'INPUT' || $event.target.tagName === 'TEXTAREA' || $event.target.tagName === 'SELECT') return;
                    const key = $event.key;
                    if (key >= '0' && key <= '9') { inputDigit(parseInt(key)); $event.preventDefault(); }
                    else if (key === '.') { inputDecimal(); $event.preventDefault(); }
                    else if (key === '+') { handleOperator('+'); $event.preventDefault(); }
                    else if (key === '-') { handleOperator('-'); $event.preventDefault(); }
                    else if (key === '=' || key === 'Enter') { calculate(); $event.preventDefault(); }
                    else if (key === 'Escape' || key === 'c' || key === 'C') { clear(); $event.preventDefault(); }
                    else if (key === 'Backspace') { display = display.length > 1 ? display.slice(0, -1) : '0'; $event.preventDefault(); }
                "
            >
                <flux:heading size="sm" class="mb-3">{{ __('Calculator') }}</flux:heading>
                <flux:text size="sm" class="mb-3 text-zinc-400">{{ __('Keyboard: type numbers, +, -, Enter/=, C to clear, Backspace') }}</flux:text>

                {{-- Display --}}
                <div
                    style="margin-top: 0.75rem; margin-bottom: 0.5rem; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: right; font-family: monospace; font-size: 1.75rem; font-variant-numeric: tabular-nums; border: 1px solid; min-height: 3.5rem;"
                    class="border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100"
                >
                    <span style="font-size: 1.25rem; opacity: 0.5; margin-right: 0.25rem;">&euro;</span>
                    <span x-text="display"></span>
                    <span x-show="operator" x-text="operator === '+' ? ' +' : ' −'" style="opacity: 0.4;"></span>
                </div>

                {{-- Buttons as bordered table --}}
                <div style="border: 1px solid; border-radius: 0.5rem; overflow: hidden;" class="border-zinc-300 dark:border-zinc-600">
                    {{-- Row 1: 7 8 9 + --}}
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr);">
                        <button type="button" @click="inputDigit(7)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">7</button>
                        <button type="button" @click="inputDigit(8)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">8</button>
                        <button type="button" @click="inputDigit(9)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">9</button>
                        <button type="button" @click="handleOperator('+')" style="padding: 0.875rem; text-align: center; font-size: 1.25rem; font-weight: 700;" class="text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition">+</button>
                    </div>
                    {{-- Row 2: 4 5 6 - --}}
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid;" class="border-zinc-300 dark:border-zinc-600">
                        <button type="button" @click="inputDigit(4)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">4</button>
                        <button type="button" @click="inputDigit(5)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">5</button>
                        <button type="button" @click="inputDigit(6)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">6</button>
                        <button type="button" @click="handleOperator('-')" style="padding: 0.875rem; text-align: center; font-size: 1.25rem; font-weight: 700;" class="text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition">−</button>
                    </div>
                    {{-- Row 3: 1 2 3 = --}}
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid;" class="border-zinc-300 dark:border-zinc-600">
                        <button type="button" @click="inputDigit(1)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">1</button>
                        <button type="button" @click="inputDigit(2)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">2</button>
                        <button type="button" @click="inputDigit(3)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">3</button>
                        <button type="button" @click="calculate()" style="padding: 0.875rem; text-align: center; font-size: 1.25rem; font-weight: 700;" class="text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 transition">=</button>
                    </div>
                    {{-- Row 4: 0 . C(span 2) --}}
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); border-top: 1px solid;" class="border-zinc-300 dark:border-zinc-600">
                        <button type="button" @click="inputDigit(0)" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">0</button>
                        <button type="button" @click="inputDecimal()" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 500; border-right: 1px solid;" class="border-zinc-300 dark:border-zinc-600 text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition">.</button>
                        <button type="button" @click="clear()" style="padding: 0.875rem; text-align: center; font-size: 1.125rem; font-weight: 700; grid-column: span 2;" class="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition">C</button>
                    </div>
                </div>

                {{-- Category select + Add Row --}}
                <div class="mt-4" style="display: flex; gap: 0.5rem; align-items: center;">
                    <flux:modal.trigger name="quick-add-category">
                        <flux:button size="sm" icon="plus" variant="ghost" title="{{ __('Add category') }}" />
                    </flux:modal.trigger>
                    <select x-model="selectedCategoryId" class="rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm text-zinc-900 dark:text-zinc-100" style="flex: 1;">
                        <option value="">{{ __('Select category...') }}</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <flux:button variant="primary" @click="addRow()">
                        {{ __('Add') }}
                    </flux:button>
                </div>
            </div>
        </div>

        {{-- Right column: Items list, Total, Actions --}}
        <div class="space-y-6">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <flux:heading size="sm">{{ __('Receipt Items') }}</flux:heading>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Category') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Amount') }}</flux:table.column>
                        <flux:table.column class="w-24"></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($rows as $index => $row)
                            @if ($editingIndex === $index)
                                <flux:table.row :key="$index">
                                    <flux:table.cell>
                                        <select wire:model="editCategoryId" class="w-full rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-2 py-1 text-sm text-zinc-900 dark:text-zinc-100">
                                            @foreach ($this->categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <input type="number" step="0.01" min="0.01" wire:model="editAmount" class="w-full rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-2 py-1 text-right text-sm tabular-nums text-zinc-900 dark:text-zinc-100" />
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex justify-end gap-1">
                                            <flux:button size="sm" icon="check" variant="ghost" wire:click="updateRow" />
                                            <flux:button size="sm" icon="x-mark" variant="ghost" wire:click="cancelEdit" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @else
                                <flux:table.row :key="$index">
                                    <flux:table.cell>{{ $row['category_name'] }}</flux:table.cell>
                                    <flux:table.cell variant="strong" class="text-right tabular-nums">&euro; {{ number_format($row['amount'] / 100, 2, ',', '.') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex justify-end gap-1">
                                            <flux:button size="sm" icon="pencil" variant="ghost" wire:click="startEditRow({{ $index }})" />
                                            <flux:button size="sm" icon="x-mark" variant="ghost" wire:click="removeRow({{ $index }})" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endif
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-center">
                                    <flux:text>{{ __('No items yet. Use the calculator or add manually below.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>

                {{-- Manual add row --}}
                <div class="mt-3 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-3">
                    <div style="display: grid; grid-template-columns: 1fr 7rem auto; gap: 0.5rem; align-items: end;">
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Category') }}</label>
                            <select wire:model="editCategoryId" style="height: 2.25rem;" class="w-full rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-2 text-sm text-zinc-900 dark:text-zinc-100">
                                <option value="">{{ __('Select...') }}</option>
                                @foreach ($this->categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1">{{ __('Amount') }}</label>
                            <input type="number" step="0.01" min="0.01" wire:model="editAmount" placeholder="0.00" style="height: 2.25rem;" class="w-full rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-2 text-right text-sm tabular-nums text-zinc-900 dark:text-zinc-100" />
                        </div>
                        <flux:button icon="plus" variant="primary" wire:click="addManualRow" style="height: 2.25rem;">
                            {{ __('Add') }}
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- Total --}}
            <div class="flex items-center justify-between rounded-lg bg-zinc-100 dark:bg-zinc-800 p-4">
                <flux:heading size="lg">{{ __('Total') }}</flux:heading>
                <flux:heading size="lg">&euro; {{ number_format($this->totalCents / 100, 2, ',', '.') }}</flux:heading>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3">
                <flux:button variant="primary" wire:click="saveAndGoBack" class="flex-1">
                    {{ __('Save & Back') }}
                </flux:button>
                @if ($showSaveAndNew ?? false)
                    <flux:button wire:click="saveAndNew" class="flex-1">
                        {{ __('Save & New') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Quick add store modal --}}
    <flux:modal name="quick-add-store" class="md:w-96">
        <form wire:submit="quickCreateStore" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Store') }}</flux:heading>
            </div>
            <flux:input wire:model="newStoreName" label="{{ __('Name') }}" placeholder="{{ __('e.g. Albert Heijn') }}" autofocus />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Quick add category modal --}}
    <flux:modal name="quick-add-category" class="md:w-96">
        <form wire:submit="quickCreateCategory" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Category') }}</flux:heading>
            </div>
            <flux:input wire:model="newCategoryName" label="{{ __('Name') }}" placeholder="{{ __('e.g. Groceries') }}" autofocus />
            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
