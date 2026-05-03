@props(['active'])

@php
// Jika aktif: Teks warna Emerald, Background Emerald transparan 10%
// Jika tidak aktif: Teks abu-abu muted, saat di-hover jadi Emerald dan background body
$classes = ($active ?? false)
            ? 'flex items-center px-4 py-3 text-sm font-bold text-primary bg-primary/10 rounded-xl transition-all duration-200'
            : 'flex items-center px-4 py-3 text-sm font-medium text-theme-muted hover:text-primary hover:bg-theme-body rounded-xl transition-all duration-200';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>