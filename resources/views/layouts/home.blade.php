<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>APC 2027 - Progressive Nigerian Support & Wishes Portal</title>

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('assets/images/apc-logo.png') }}">

        <!-- SEO Metadata -->
        <meta name="description" content="Official engagement platform for citizens to voluntarily declare support, submit wishes, and verify voter status for the APC 2027 campaign.">
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700|figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts & Styling -->
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900 selection:bg-rose-500 selection:text-white">
        
        <!-- Main Content -->
        <main>
            {{ $slot }}
        </main>

        @livewireScripts
        <script>
            window.downloadCardAsImage = function() {
                const card = document.getElementById('pvc-card-to-print');
                if (!card) return;
                
                const refElement = document.getElementById('declaration-reference-number');
                const ref = refElement ? refElement.innerText.trim() : 'CARD';
                
                // Add loading indicator or change button text
                const btn = document.querySelector('button[onclick*="Card"]');
                const originalText = btn ? btn.innerHTML : '';
                if (btn) {
                    btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Generating Image...`;
                    btn.disabled = true;
                }
                
                // Use html2canvas to render the card
                html2canvas(card, {
                    useCORS: true,
                    allowTaint: true,
                    scale: 3, // scale up for high-resolution print quality
                    backgroundColor: null // transparent background outside rounded corners
                }).then(canvas => {
                    const link = document.createElement('a');
                    link.download = `APC_2027_Voter_Card_${ref}.png`;
                    link.href = canvas.toDataURL('image/png');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    if (btn) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }).catch(err => {
                    console.error('Error generating card image:', err);
                    alert('Failed to generate image. Please try again.');
                    if (btn) {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                });
            };
            
            // Fallback for any old elements still calling printCard()
            window.printCard = window.downloadCardAsImage;
        </script>
    </body>
</html>
