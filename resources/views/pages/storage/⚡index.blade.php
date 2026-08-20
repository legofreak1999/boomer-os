<?php

use App\Actions\Storage\CreateFolder;
use App\Actions\Storage\DatabaseBackup;
use App\Actions\Storage\DeleteFolder;
use App\Actions\Storage\DeleteStorageFile;
use App\Actions\Storage\UploadFileToStorage;
use App\Models\StorageBackup;
use App\Models\StorageFile;
use App\Models\StorageFolder;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Storage')] class extends Component {
    use WithFileUploads;

    public string $activeTab = 'files';
    public mixed $uploadFile = null;
    public ?int $currentFolderId = null;
    public string $search = '';
    public string $sort = 'date_desc';
    public string $typeFilter = '';
    public string $viewMode = 'grid';
    public string $newFolderName = '';

    #[Computed]
    public function topLevelFolders()
    {
        return StorageFolder::with('children')->whereNull('parent_id')->orderBy('name')->get();
    }

    #[Computed]
    public function subfolders()
    {
        if ($this->search) {
            return collect();
        }

        return StorageFolder::where('parent_id', $this->currentFolderId)->orderBy('name')->get();
    }

    #[Computed]
    public function breadcrumbs(): array
    {
        if (! $this->currentFolderId) {
            return [];
        }

        $folder = StorageFolder::find($this->currentFolderId);

        return $folder ? [...$folder->ancestors(), $folder] : [];
    }

    #[Computed]
    public function files()
    {
        $query = StorageFile::query();

        if ($this->search) {
            $query->where('filename', 'like', '%'.$this->search.'%');
        } else {
            $query->where('folder_id', $this->currentFolderId);
        }

        match ($this->typeFilter) {
            'images' => $query->where('mime_type', 'like', 'image/%'),
            'videos' => $query->where('mime_type', 'like', 'video/%'),
            'audio' => $query->where('mime_type', 'like', 'audio/%'),
            'documents' => $query->where(fn ($q) => $q
                ->where('mime_type', 'application/pdf')
                ->orWhere('mime_type', 'like', 'application/vnd.%')
                ->orWhere('mime_type', 'like', 'text/%')
            ),
            'archives' => $query->where(fn ($q) => $q
                ->where('mime_type', 'application/zip')
                ->orWhere('mime_type', 'like', 'application/x-%')
            ),
            default => null,
        };

        [$col, $dir] = match ($this->sort) {
            'name_asc' => ['filename', 'asc'],
            'name_desc' => ['filename', 'desc'],
            'size_asc' => ['size_bytes', 'asc'],
            'size_desc' => ['size_bytes', 'desc'],
            'date_asc' => ['created_at', 'asc'],
            default => ['created_at', 'desc'],
        };

        return $query->orderBy($col, $dir)->get();
    }

    #[Computed]
    public function totalSize(): int
    {
        return StorageFile::sum('size_bytes');
    }

    #[Computed]
    public function fileCount(): int
    {
        return StorageFile::count();
    }

    #[Computed]
    public function backups()
    {
        return StorageBackup::latest()->get();
    }

    public function navigateTo(?int $folderId): void
    {
        $this->currentFolderId = $folderId;
        $this->search = '';
        unset($this->subfolders, $this->breadcrumbs, $this->files);
    }

    public function openUploadModal(): void
    {
        Flux::modal('upload-file')->show();
    }

    public function openNewFolderModal(): void
    {
        Flux::modal('new-folder')->show();
    }

    public function upload(UploadFileToStorage $action): void
    {
        $this->validate(['uploadFile' => ['required', 'file', 'max:102400']]);

        try {
            $action($this->uploadFile, $this->currentFolderId);
            $this->uploadFile = null;
            unset($this->files, $this->totalSize, $this->fileCount);
            Flux::modal('upload-file')->close();
            Flux::toast(text: __('File uploaded. Syncing to secondary storage in the background.'));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: __('Upload failed: :error', ['error' => $e->getMessage()]));
        }
    }

    public function deleteFile(int $id, DeleteStorageFile $action): void
    {
        $file = StorageFile::findOrFail($id);
        $action($file);
        unset($this->files, $this->totalSize, $this->fileCount);
        Flux::toast(text: __('File deleted.'));
    }

    public function createFolder(CreateFolder $action): void
    {
        $this->validate(['newFolderName' => ['required', 'string', 'max:255']]);
        $action($this->newFolderName, $this->currentFolderId);
        $this->newFolderName = '';
        unset($this->topLevelFolders, $this->subfolders);
        Flux::modal('new-folder')->close();
        Flux::toast(text: __('Folder created.'));
    }

    public function deleteFolder(int $id, DeleteFolder $action): void
    {
        try {
            $action(StorageFolder::findOrFail($id));
            unset($this->topLevelFolders, $this->subfolders);
            Flux::toast(text: __('Folder deleted.'));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function runBackup(DatabaseBackup $action): void
    {
        try {
            $action();
            unset($this->backups);
            Flux::toast(text: __('Backup created successfully.'));
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: __('Backup failed: :error', ['error' => $e->getMessage()]));
        }
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_073_741_824) {
            return number_format($bytes / 1_073_741_824, 2).' GB';
        }
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 2).' MB';
        }
        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, 2).' KB';
        }

        return $bytes.' B';
    }

    public static function fileIconInfo(?string $mime): array
    {
        if (! $mime) {
            return ['icon' => 'document', 'bg' => 'bg-zinc-100 dark:bg-zinc-700', 'color' => 'text-zinc-500'];
        }
        if (str_starts_with($mime, 'image/')) {
            return ['icon' => 'photo', 'bg' => 'bg-violet-100 dark:bg-violet-900/40', 'color' => 'text-violet-500'];
        }
        if (str_starts_with($mime, 'video/')) {
            return ['icon' => 'film', 'bg' => 'bg-blue-100 dark:bg-blue-900/40', 'color' => 'text-blue-500'];
        }
        if (str_starts_with($mime, 'audio/')) {
            return ['icon' => 'musical-note', 'bg' => 'bg-pink-100 dark:bg-pink-900/40', 'color' => 'text-pink-500'];
        }
        if ($mime === 'application/pdf') {
            return ['icon' => 'document-text', 'bg' => 'bg-red-100 dark:bg-red-900/40', 'color' => 'text-red-500'];
        }
        if (str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel') || str_contains($mime, '.sheet')) {
            return ['icon' => 'table-cells', 'bg' => 'bg-green-100 dark:bg-green-900/40', 'color' => 'text-green-600'];
        }
        if (str_contains($mime, 'word') || str_contains($mime, '.document')) {
            return ['icon' => 'document-text', 'bg' => 'bg-blue-100 dark:bg-blue-900/40', 'color' => 'text-blue-600'];
        }
        if (str_contains($mime, 'zip') || str_contains($mime, 'archive') || str_contains($mime, 'tar') || str_contains($mime, 'rar')) {
            return ['icon' => 'archive-box', 'bg' => 'bg-amber-100 dark:bg-amber-900/40', 'color' => 'text-amber-600'];
        }
        if (str_starts_with($mime, 'text/')) {
            return ['icon' => 'document-text', 'bg' => 'bg-zinc-100 dark:bg-zinc-700', 'color' => 'text-zinc-500'];
        }

        return ['icon' => 'document', 'bg' => 'bg-zinc-100 dark:bg-zinc-700', 'color' => 'text-zinc-500'];
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Storage') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage files and database backups on Hetzner Object Storage') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div x-data="{ tab: @entangle('activeTab') }" class="mt-6">

        {{-- Tab nav --}}
        <div class="flex border-b border-zinc-200 dark:border-zinc-700">
            <button
                @click="tab = 'files'"
                :class="tab === 'files'
                    ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100'
                    : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                class="flex items-center gap-2 px-4 py-3 text-sm font-medium transition"
            >
                <flux:icon.document class="size-4" />
                {{ __('Files') }}
            </button>
            <button
                @click="tab = 'backups'"
                :class="tab === 'backups'
                    ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-zinc-100 dark:text-zinc-100'
                    : 'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200'"
                class="flex items-center gap-2 px-4 py-3 text-sm font-medium transition"
            >
                <flux:icon.circle-stack class="size-4" />
                {{ __('Backups') }}
            </button>
        </div>

        {{-- ═══════════════════════════ FILES TAB ═══════════════════════════ --}}
        <div x-show="tab === 'files'" x-cloak class="mt-6 flex gap-6">

            {{-- Folder tree sidebar --}}
            <nav class="hidden w-52 shrink-0 flex-col lg:flex">
                {{-- All Files --}}
                <button
                    wire:click="navigateTo(null)"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm transition {{ $currentFolderId === null && !$search ? 'bg-zinc-100 font-medium text-zinc-900 dark:bg-white/10 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-200' }}"
                >
                    <flux:icon.home class="size-4 shrink-0" />
                    {{ __('All Files') }}
                </button>

                @if ($this->topLevelFolders->isNotEmpty())
                    <flux:separator variant="subtle" class="my-2" />

                    @foreach ($this->topLevelFolders as $folder)
                        @php
                            $isActive = $currentFolderId === $folder->id;
                            $hasActiveChild = $folder->children->contains('id', $currentFolderId);
                            $startOpen = $isActive || $hasActiveChild || $folder->children->isNotEmpty();
                        @endphp
                        <div x-data="{ open: {{ $startOpen ? 'true' : 'false' }} }">
                            <div class="flex items-center">
                                <button
                                    @click="open = !open"
                                    class="flex shrink-0 items-center p-1 text-zinc-400 transition hover:text-zinc-600 dark:hover:text-zinc-300"
                                >
                                    <flux:icon.chevron-right
                                        class="size-3 transition-transform duration-150"
                                        x-bind:class="open ? 'rotate-90' : ''"
                                    />
                                </button>
                                <button
                                    wire:click="navigateTo({{ $folder->id }})"
                                    class="flex flex-1 items-center gap-2 truncate rounded-lg px-2 py-1.5 text-sm transition {{ $isActive ? 'bg-zinc-100 font-medium text-zinc-900 dark:bg-white/10 dark:text-white' : 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-200' }}"
                                >
                                    <flux:icon.folder class="size-4 shrink-0 text-amber-500" />
                                    <span class="truncate">{{ $folder->name }}</span>
                                </button>
                            </div>

                            @if ($folder->children->isNotEmpty())
                                <div x-show="open" x-cloak class="ml-6 mt-0.5 space-y-0.5">
                                    @foreach ($folder->children as $child)
                                        <button
                                            wire:click="navigateTo({{ $child->id }})"
                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-sm transition {{ $currentFolderId === $child->id ? 'bg-zinc-100 font-medium text-zinc-900 dark:bg-white/10 dark:text-white' : 'text-zinc-500 hover:bg-zinc-50 hover:text-zinc-700 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-zinc-200' }}"
                                        >
                                            <flux:icon.folder class="size-4 shrink-0 text-amber-400" />
                                            <span class="truncate">{{ $child->name }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif

                <flux:separator variant="subtle" class="my-2" />

                <button
                    wire:click="openNewFolderModal"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-400 transition hover:bg-zinc-50 hover:text-zinc-600 dark:hover:bg-white/5 dark:hover:text-zinc-300"
                >
                    <flux:icon.folder-plus class="size-4 shrink-0" />
                    {{ __('New Folder') }}
                </button>

                <flux:separator variant="subtle" class="my-4" />

                {{-- Storage stats --}}
                <div class="space-y-1 px-3">
                    <flux:text size="xs" class="font-medium uppercase tracking-wide text-zinc-400">{{ __('Storage') }}</flux:text>
                    <flux:text size="sm" class="font-medium">{{ $this->formatBytes($this->totalSize) }}</flux:text>
                    <flux:text size="xs" class="text-zinc-400">{{ $this->fileCount }} {{ __('files') }}</flux:text>
                </div>
            </nav>

            {{-- Main content area --}}
            <div class="min-w-0 flex-1">

                {{-- Toolbar --}}
                <div class="mb-4 flex flex-wrap items-center gap-2">

                    {{-- Breadcrumb --}}
                    <div class="flex min-w-0 flex-1 items-center gap-1 text-sm">
                        <button
                            wire:click="navigateTo(null)"
                            class="shrink-0 text-zinc-500 transition hover:text-zinc-800 dark:hover:text-zinc-200"
                        >{{ __('Home') }}</button>
                        @foreach ($this->breadcrumbs as $crumb)
                            <flux:icon.chevron-right class="size-3 shrink-0 text-zinc-400" />
                            <button
                                wire:click="navigateTo({{ $crumb->id }})"
                                class="truncate transition hover:text-zinc-800 dark:hover:text-zinc-200 {{ $loop->last ? 'font-medium text-zinc-900 dark:text-zinc-100' : 'text-zinc-500' }}"
                            >{{ $crumb->name }}</button>
                        @endforeach
                    </div>

                    {{-- Search --}}
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Search files…') }}"
                        icon="magnifying-glass"
                        size="sm"
                        clearable
                        class="w-44"
                    />

                    {{-- Type filter --}}
                    <flux:select wire:model.live="typeFilter" size="sm" class="w-36">
                        <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                        <flux:select.option value="images">{{ __('Images') }}</flux:select.option>
                        <flux:select.option value="videos">{{ __('Videos') }}</flux:select.option>
                        <flux:select.option value="audio">{{ __('Audio') }}</flux:select.option>
                        <flux:select.option value="documents">{{ __('Documents') }}</flux:select.option>
                        <flux:select.option value="archives">{{ __('Archives') }}</flux:select.option>
                    </flux:select>

                    {{-- Sort --}}
                    <flux:select wire:model.live="sort" size="sm" class="w-40">
                        <flux:select.option value="date_desc">{{ __('Newest first') }}</flux:select.option>
                        <flux:select.option value="date_asc">{{ __('Oldest first') }}</flux:select.option>
                        <flux:select.option value="name_asc">{{ __('Name A–Z') }}</flux:select.option>
                        <flux:select.option value="name_desc">{{ __('Name Z–A') }}</flux:select.option>
                        <flux:select.option value="size_desc">{{ __('Largest first') }}</flux:select.option>
                        <flux:select.option value="size_asc">{{ __('Smallest first') }}</flux:select.option>
                    </flux:select>

                    {{-- View toggle --}}
                    <div class="flex">
                        <flux:button
                            size="sm"
                            icon="squares-2x2"
                            variant="{{ $viewMode === 'grid' ? 'filled' : 'ghost' }}"
                            wire:click="$set('viewMode', 'grid')"
                            :tooltip="__('Grid view')"
                        />
                        <flux:button
                            size="sm"
                            icon="list-bullet"
                            variant="{{ $viewMode === 'list' ? 'filled' : 'ghost' }}"
                            wire:click="$set('viewMode', 'list')"
                            :tooltip="__('List view')"
                        />
                    </div>

                    {{-- Upload --}}
                    <flux:button size="sm" variant="primary" icon="arrow-up-tray" wire:click="openUploadModal">
                        {{ __('Upload') }}
                    </flux:button>
                </div>

                {{-- Search results notice --}}
                @if ($search)
                    <flux:text size="sm" class="mb-3 text-zinc-500">
                        {{ __('Showing results for') }} <span class="font-medium text-zinc-800 dark:text-zinc-200">"{{ $search }}"</span>
                        — {{ $this->files->count() }} {{ __('file(s) found') }}
                    </flux:text>
                @endif

                {{-- Empty state --}}
                @if ($this->subfolders->isEmpty() && $this->files->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-white/10">
                            <flux:icon.folder-open class="size-7 text-zinc-400" />
                        </div>
                        <flux:heading size="sm">{{ $search ? __('No files match your search') : __('This folder is empty') }}</flux:heading>
                        <flux:text size="sm" class="mt-1 text-zinc-400">
                            {{ $search ? __('Try a different search term') : __('Upload a file or create a subfolder to get started') }}
                        </flux:text>
                    </div>

                {{-- GRID VIEW --}}
                @elseif ($viewMode === 'grid')
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">

                        {{-- Folder cards --}}
                        @foreach ($this->subfolders as $folder)
                            <div
                                wire:click="navigateTo({{ $folder->id }})"
                                wire:key="folder-{{ $folder->id }}"
                                class="group relative flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 transition hover:border-amber-300 hover:bg-amber-50 dark:border-white/10 dark:hover:border-amber-500/30 dark:hover:bg-amber-900/10"
                            >
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40">
                                    <flux:icon.folder class="size-8 text-amber-500" />
                                </div>
                                <div class="w-full text-center">
                                    <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $folder->name }}</p>
                                    <p class="text-xs text-zinc-400">{{ $folder->files()->count() }} {{ __('files') }}</p>
                                </div>
                                <div class="absolute right-1 top-1 hidden group-hover:flex">
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click.stop="deleteFolder({{ $folder->id }})" wire:confirm="{{ __('Delete folder :name? It must be empty.', ['name' => $folder->name]) }}" />
                                </div>
                            </div>
                        @endforeach

                        {{-- File cards --}}
                        @foreach ($this->files as $file)
                            @php $info = self::fileIconInfo($file->mime_type); @endphp
                            <div
                                wire:key="file-{{ $file->id }}"
                                class="group relative flex flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 transition dark:border-white/10"
                            >
                                <div class="flex h-14 w-14 items-center justify-center rounded-xl {{ $info['bg'] }}">
                                    <flux:icon :name="$info['icon']" class="size-8 {{ $info['color'] }}" />
                                </div>
                                <div class="w-full text-center">
                                    <p class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100" title="{{ $file->filename }}">{{ $file->filename }}</p>
                                    <p class="text-xs text-zinc-400">{{ $file->humanSize() }} · {{ $file->created_at->diffForHumans() }}</p>
                                </div>
                                @if (! $file->synced_to_secondary_at)
                                    <flux:badge color="yellow" size="sm">{{ __('Syncing') }}</flux:badge>
                                @endif
                                <div class="absolute right-1 top-1 hidden items-center gap-0.5 group-hover:flex">
                                    <flux:button size="sm" variant="ghost" icon="arrow-down-tray" href="{{ route('storage.download', $file) }}" as="a" target="_blank" />
                                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteFile({{ $file->id }})" wire:confirm="{{ __('Delete :filename?', ['filename' => $file->filename]) }}" />
                                </div>
                            </div>
                        @endforeach
                    </div>

                {{-- LIST VIEW --}}
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Name') }}</flux:table.column>
                            <flux:table.column>{{ __('Size') }}</flux:table.column>
                            <flux:table.column>{{ __('Modified') }}</flux:table.column>
                            <flux:table.column>{{ __('Secondary') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            {{-- Folder rows --}}
                            @foreach ($this->subfolders as $folder)
                                <flux:table.row :key="'folder-'.$folder->id" wire:click="navigateTo({{ $folder->id }})" class="cursor-pointer">
                                    <flux:table.cell variant="strong">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40">
                                                <flux:icon.folder class="size-4 text-amber-500" />
                                            </div>
                                            {{ $folder->name }}
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>—</flux:table.cell>
                                    <flux:table.cell>{{ $folder->created_at->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell>—</flux:table.cell>
                                    <flux:table.cell align="end">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click.stop="deleteFolder({{ $folder->id }})"
                                            wire:confirm="{{ __('Delete folder :name? It must be empty.', ['name' => $folder->name]) }}"
                                        />
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach

                            {{-- File rows --}}
                            @foreach ($this->files as $file)
                                @php $info = self::fileIconInfo($file->mime_type); @endphp
                                <flux:table.row :key="'file-'.$file->id">
                                    <flux:table.cell variant="strong">
                                        <div class="flex items-center gap-3">
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $info['bg'] }}">
                                                <flux:icon :name="$info['icon']" class="size-4 {{ $info['color'] }}" />
                                            </div>
                                            <span class="truncate" title="{{ $file->filename }}">{{ $file->filename }}</span>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $file->humanSize() }}</flux:table.cell>
                                    <flux:table.cell>{{ $file->created_at->diffForHumans() }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($file->synced_to_secondary_at)
                                            <flux:badge color="lime" size="sm" title="{{ $file->synced_to_secondary_at->format('Y-m-d H:i') }}">{{ __('Synced') }}</flux:badge>
                                        @else
                                            <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end">
                                        <div class="flex items-center justify-end gap-1">
                                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" href="{{ route('storage.download', $file) }}" as="a" target="_blank" />
                                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteFile({{ $file->id }})" wire:confirm="{{ __('Delete :filename?', ['filename' => $file->filename]) }}" />
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </div>
        </div>

        {{-- ═══════════════════════════ BACKUPS TAB ═══════════════════════════ --}}
        <div x-show="tab === 'backups'" x-cloak>
            <div class="my-6 flex items-center justify-between">
                <div>
                    <flux:heading size="sm">{{ __('Database Backups') }}</flux:heading>
                    <flux:text size="sm">{{ __('Daily backups run at 03:00. Last 30 backups are retained.') }}</flux:text>
                </div>
                <flux:button
                    variant="primary"
                    icon="circle-stack"
                    wire:click="runBackup"
                    wire:loading.attr="disabled"
                    wire:confirm="{{ __('Create a database backup now? This may take a moment.') }}"
                >
                    <span wire:loading.remove wire:target="runBackup">{{ __('Run Backup Now') }}</span>
                    <span wire:loading wire:target="runBackup">{{ __('Running…') }}</span>
                </flux:button>
            </div>

            @if ($this->backups->isEmpty())
                <flux:text class="py-8 text-center">{{ __('No backups yet. Run your first backup to get started.') }}</flux:text>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Filename') }}</flux:table.column>
                        <flux:table.column>{{ __('Size') }}</flux:table.column>
                        <flux:table.column>{{ __('Primary') }}</flux:table.column>
                        <flux:table.column>{{ __('Secondary') }}</flux:table.column>
                        <flux:table.column>{{ __('Created') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->backups as $backup)
                            <flux:table.row :key="$backup->id">
                                <flux:table.cell variant="strong">
                                    {{ $backup->filename }}
                                    @if ($backup->notes)
                                        <flux:text size="sm" class="text-red-500 dark:text-red-400">{{ $backup->notes }}</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $backup->humanSize() }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($backup->synced_to_primary_at)
                                        <flux:badge color="lime" size="sm" title="{{ $backup->synced_to_primary_at->format('Y-m-d H:i') }}">{{ __('OK') }}</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">{{ __('Failed') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($backup->synced_to_secondary_at)
                                        <flux:badge color="lime" size="sm" title="{{ $backup->synced_to_secondary_at->format('Y-m-d H:i') }}">{{ __('OK') }}</flux:badge>
                                    @else
                                        <flux:badge color="red" size="sm">{{ __('Failed') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $backup->created_at->diffForHumans() }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </div>
    {{-- Upload modal --}}
    <flux:modal name="upload-file" class="min-w-[22rem]">
        <form wire:submit="upload" class="space-y-6">
            <flux:heading>{{ __('Upload File') }}</flux:heading>
            @if ($currentFolderId && count($this->breadcrumbs))
                <flux:text size="sm" class="-mt-4 text-zinc-500">
                    {{ __('Uploading to') }}: <span class="font-medium">{{ collect($this->breadcrumbs)->last()->name }}</span>
                </flux:text>
            @endif
            <flux:field>
                <flux:label>{{ __('File') }}</flux:label>
                <flux:input type="file" wire:model="uploadFile" accept="*/*" />
                <flux:error name="uploadFile" />
            </flux:field>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button>{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="upload">{{ __('Upload') }}</span>
                    <span wire:loading wire:target="upload">{{ __('Uploading…') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- New folder modal --}}
    <flux:modal name="new-folder" class="min-w-[22rem]">
        <form wire:submit="createFolder" class="space-y-6">
            <flux:heading>{{ __('New Folder') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Folder name') }}</flux:label>
                <flux:input wire:model="newFolderName" placeholder="{{ __('e.g. Documents') }}" autofocus />
                <flux:error name="newFolderName" />
            </flux:field>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button>{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
