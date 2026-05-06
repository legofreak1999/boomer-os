<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased bg-linear-to-b from-neutral-950 to-neutral-900">
        <div class="flex min-h-screen flex-col items-center justify-center p-6">
            <div class="flex flex-col items-center gap-6 text-center max-w-md">
                {{-- Logo --}}
                <div style="width: 15rem; height: 15rem; border-radius: 1rem; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <x-app-logo-icon style="width: 15rem; height: 15rem; object-fit: contain;" />
                </div>

                {{-- Title --}}
                <div>
                    <h1 style="font-size: 2.25rem; font-weight: 700; color: #f4f4f5; letter-spacing: -0.025em;">Boomer OS</h1>
                    <p style="margin-top: 0.5rem; font-size: 1.125rem; color: #a1a1aa;">Your home management system</p>
                </div>

                {{-- Actions --}}
                <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
                    @auth
                        <a href="{{ route('dashboard') }}" style="padding: 0.625rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; background: #f4f4f5; color: #18181b; text-decoration: none; transition: background 0.15s;" onmouseenter="this.style.background='#ffffff'" onmouseleave="this.style.background='#f4f4f5'">
                            {{ __('Go to Dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" style="padding: 0.625rem 1.5rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600; background: #f4f4f5; color: #18181b; text-decoration: none; transition: background 0.15s;" onmouseenter="this.style.background='#ffffff'" onmouseleave="this.style.background='#f4f4f5'">
                            {{ __('Log in') }}
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </body>
</html>
