@php
    $idLabel = fn ($type) => $type ? ($idTypes[$type] ?? $type) : null;
@endphp
<h3>Expéditeur</h3>
<table class="grid">
    <tr><td class="label">Nom</td><td>{{ optional($t->client)->name ?? '—' }}</td></tr>
    <tr><td class="label">Téléphone</td><td>{{ optional($t->client)->phone ?? '—' }}</td></tr>
    @if ($t->sender_id_number)
        <tr><td class="label">Pièce d'identité</td><td>{{ $idLabel($t->sender_id_type) }} n° {{ $t->sender_id_number }}</td></tr>
    @endif
</table>

<h3>Bénéficiaire</h3>
<table class="grid">
    <tr><td class="label">Nom</td><td>{{ $t->recipient_name ?? '—' }}</td></tr>
    <tr><td class="label">Téléphone</td><td>{{ $t->recipient_phone ?? '—' }}</td></tr>
    <tr><td class="label">Pays de destination</td><td>{{ optional($t->destinationCountry)->name ?? $t->destination ?? '—' }}</td></tr>
    @if ($kind === 'withdrawal' && $t->recipient_id_number)
        <tr><td class="label">Pièce présentée</td><td>{{ $idLabel($t->recipient_id_type) }} n° {{ $t->recipient_id_number }}</td></tr>
    @endif
</table>
