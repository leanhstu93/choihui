@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-lg shadow-sm']) }}>
