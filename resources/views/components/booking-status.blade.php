{{-- A booking request's status as a coloured pill. --}}
@props(['booking'])

@php $status = $booking->currentStatus(); @endphp

<span {{ $attributes->class(['inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold', $status->pillClasses()]) }}>{{ $status->label() }}</span>
