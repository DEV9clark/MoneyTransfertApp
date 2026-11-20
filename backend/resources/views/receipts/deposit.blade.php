@extends('receipts.layout')

@section('title', "Reçu d'envoi " . $t->reference)

@php
    $fmt = fn ($v) => number_format((float) $v, ((float) $v == floor((float) $v)) ? 0 : 2, ',', ' ') . ' ' . $t->currency;
    $statusLabels = ['pending' => 'En attente de retrait', 'completed' => 'Payé', 'cancelled' => 'Annulé', 'expired' => 'Expiré'];
@endphp

@section('body')
    <div class="title">REÇU D'ENVOI D'ARGENT</div>

    <table class="meta">
        <tr><td class="label">N° de reçu</td><td><strong>{{ $receiptNumber }}</strong></td></tr>
        <tr><td class="label">Référence du transfert</td><td class="reference">{{ $t->reference }}</td></tr>
        <tr><td class="label">Date et heure</td><td>{{ $t->created_at->format('d/m/Y à H:i') }}</td></tr>
        <tr><td class="label">Statut</td><td class="status">{{ $statusLabels[$t->status] ?? $t->status }}</td></tr>
    </table>

    @include('receipts._parties')

    <h3>Détail de l'opération</h3>
    <table class="amounts">
        <tr><td>Montant envoyé (à remettre au bénéficiaire)</td><td class="num">{{ $fmt($t->amount) }}</td></tr>
        <tr><td>Frais d'envoi</td><td class="num">{{ $fmt($t->fee_amount) }}</td></tr>
        <tr class="total"><td>TOTAL PAYÉ PAR L'EXPÉDITEUR</td><td class="num">{{ $fmt($t->total_amount) }}</td></tr>
    </table>
    <div class="words">Arrêté le présent reçu à la somme de : {{ $amountInWords }}.</div>

    <h3>Agent expéditeur</h3>
    <table class="grid">
        <tr><td class="label">Agent</td><td>{{ optional($t->creator)->name ?? '—' }}@if (optional($t->creator)->phone_number) &middot; {{ $t->creator->phone_number }}@endif</td></tr>
        @if ($t->expires_at)
            <tr><td class="label">Retrait possible jusqu'au</td><td>{{ $t->expires_at->format('d/m/Y') }}</td></tr>
        @endif
    </table>

    <div class="notice">
        Le <strong>code secret de retrait</strong> a été envoyé par SMS au bénéficiaire. Il ne figure pas sur ce reçu.
        Le bénéficiaire devra présenter ce code et une pièce d'identité valide. Ne communiquez jamais ce code à un tiers,
        y compris à un agent. Sans retrait avant la date limite, le transfert est annulé et remboursable.
    </div>

    <table class="signatures">
        <tr>
            <td>Signature de l'expéditeur<div class="sigbox"></div></td>
            <td>Signature et cachet de l'agent<div class="sigbox"></div></td>
        </tr>
    </table>
@endsection
