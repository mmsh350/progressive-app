<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>APC 2027 - Progressive Nigerian Support & Wishes Portal</title>

        <!-- SEO Metadata -->
        <meta name="description" content="Official engagement platform for citizens to voluntarily declare support, submit wishes, and verify voter status for the APC 2027 campaign.">
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700|figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts & Styling -->
        <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio,line-clamp"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Outfit', 'Figtree', 'sans-serif'],
                        }
                    }
                }
            }
        </script>
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900 selection:bg-rose-500 selection:text-white">
        
        <!-- Main Content -->
        <main>
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
