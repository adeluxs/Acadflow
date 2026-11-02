@php
    $whatsappNumber = preg_replace('/[\s()+-]+/', '', (string) config('services.whatsapp.support_number', ''));
    $whatsappMessage = 'Hello! I have a question, feedback, or need support with AcadSyntra.';
@endphp
@if(preg_match('/^[1-9][0-9]{6,14}$/', $whatsappNumber))
    <style>
        .whatsapp-support {
            position: fixed;
            right: calc(1rem + env(safe-area-inset-right, 0px));
            bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
            z-index: 30;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border: 2px solid #fff;
            border-radius: 50%;
            background: #128c4a;
            color: #fff;
            box-shadow: 0 4px 16px rgb(0 0 0 / 18%);
            text-decoration: none;
        }
        .whatsapp-support:hover { background: #075e36; color: #fff; }
        .whatsapp-support:focus-visible { outline: 3px solid #0f172a; outline-offset: 4px; }
        .whatsapp-support svg { width: 30px; height: 30px; }
        body:has(.whatsapp-support) :is(#sync-status, #pwa-install-prompt, [data-push-invitation]) {
            bottom: calc(5.5rem + env(safe-area-inset-bottom, 0px));
        }
        @media print { .whatsapp-support { display: none; } }
    </style>
    <a class="whatsapp-support"
       href="https://wa.me/{{ $whatsappNumber }}?text={{ rawurlencode($whatsappMessage) }}"
       target="_blank" rel="noopener noreferrer"
       aria-label="Chat with us on WhatsApp for enquiries, feedback, or support (opens in a new tab)"
       title="Questions, feedback or support? Chat with us on WhatsApp">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
            <path d="M20.52 3.48A11.91 11.91 0 0 0 12.04 0C5.46 0 .11 5.35.1 11.93c0 2.1.55 4.15 1.6 5.96L0 24l6.26-1.64a11.94 11.94 0 0 0 5.77 1.47h.01c6.58 0 11.93-5.35 11.94-11.93a11.87 11.87 0 0 0-3.46-8.42ZM12.04 21.8h-.01a9.9 9.9 0 0 1-5.05-1.38l-.36-.21-3.72.98.99-3.63-.23-.37a9.88 9.88 0 0 1-1.52-5.26c0-5.47 4.45-9.92 9.93-9.92a9.84 9.84 0 0 1 7.02 2.91 9.86 9.86 0 0 1 2.9 7.02c0 5.47-4.45 9.92-9.95 9.92Zm5.44-7.43c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.67-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.18.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.67-1.61-.92-2.2-.24-.59-.49-.51-.67-.52h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.48 0 1.47 1.07 2.88 1.22 3.08.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.42.25-.69.25-1.29.17-1.42-.07-.12-.27-.19-.57-.34Z"/>
        </svg>
    </a>
@endif
