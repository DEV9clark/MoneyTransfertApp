@extends('receipts.layout')

@section('title', 'Reçu de retrait ' . $t->reference)

@php
    $fmt = fn ($v) => number_format((float) $v, ((float) $v == floor((float) $v)) ? 0 : 2, ',', ' ') . ' ' . $t->currency;
@endphp

@section('body')
    <div class="title">REÇU DE RETRAIT / PAIEMENT</div>

    <table class="meta">
        <tr><td class="label">N° de reçu</td><td><strong>{{ $receiptNumber }}</strong></td></tr>
        <tr><td class="label">Référence du transfert</td><td class="reference">{{ $t->reference }}</td></tr>
        <tr><td class="label">Envoyé le</td><td>{{ $t->created_at->format('d/m/Y à H:i') }} (reçu {{ $t->sender_receipt_number }})</td></tr>
        <tr><td class="label">Payé le</td><td><strong>{{ optional($t->paid_at)->format('d/m/Y à H:i') }}</strong></td></tr>
    </table>

    @include('receipts._parties')

    <h3>Montant payé</h3>
    <table class="amounts">
        <tr class="total"><td>MONTANT REMIS AU BÉNÉFICIAIRE</td><td class="num">{{ $fmt($t->amount) }}</td></tr>
    </table>
    <div class="words">Soit : {{ $amountInWords }}.</div>

    <h3>Agents</h3>
    <table class="grid">
        <tr><td class="label">Agent payeur</td><td>{{ optional($t->payer)->name ?? '—' }}@if (optional($t->payer)->phone_number) &middot; {{ $t->payer->phone_number }}@endif</td></tr>
        <tr><td class="label">Agent expéditeur</td><td>{{ optional($t->creator)->name ?? '—' }}</td></tr>
    </table>

    <div class="notice">
        Je soussigné(e), <strong>{{ $t->recipient_name }}</strong>, reconnais avoir reçu ce jour la somme de
        <strong>{{ $fmt($t->amount) }}</strong> au titre du transfert <strong>{{ $t->reference }}</strong>,
        après vérification de mon identité. Le code de retrait a été utilisé et n'est plus valable.
    </div>

    <table class="signatures">
        <tr>
            <td>Signature du bénéficiaire<div class="sigbox"></div></td>
            <td>Signature et cachet de l'agent payeur<div class="sigbox"></div></td>
        </tr>
    </table>
@endsection
