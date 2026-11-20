<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>@yield('title')</title>
<style>
    @page { margin: 8mm 9mm; }
    * { box-sizing: border-box; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8pt; color: #1a1a1a; margin: 0; }
    .page { position: relative; }
    .page-break { page-break-after: always; }
    .watermark {
        position: fixed; top: 38%; left: 4%; width: 92%; text-align: center;
        font-size: 54pt; font-weight: bold; color: #000; opacity: 0.07;
        transform: rotate(-30deg);
    }
    table { width: 100%; border-collapse: collapse; }
    .header td { vertical-align: top; }
    .company { font-size: 12pt; font-weight: bold; letter-spacing: .3px; }
    .muted { color: #555; }
    .small { font-size: 7.2pt; }
    .copy-label {
        display: inline-block; border: 1px solid #1a1a1a; padding: 2px 6px;
        font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: .6px;
    }
    .title {
        margin: 6px 0 4px; padding: 4px 0; border-top: 2px solid #1a1a1a; border-bottom: 2px solid #1a1a1a;
        text-align: center; font-size: 11.5pt; font-weight: bold; letter-spacing: 1px;
    }
    .meta td { padding: 1px 0; }
    .meta .label { color: #555; width: 32%; }
    .reference { font-size: 10.5pt; font-weight: bold; letter-spacing: .5px; }
    h3 {
        font-size: 7.6pt; text-transform: uppercase; letter-spacing: .8px; color: #333;
        margin: 6px 0 2px; padding-bottom: 1px; border-bottom: 1px solid #bbb;
    }
    .grid td { padding: 1px 0; vertical-align: top; }
    .grid .label { color: #555; width: 32%; }
    .amounts { margin-top: 4px; }
    .amounts td { padding: 2px 6px; border-bottom: 1px solid #ddd; }
    .amounts td.num { text-align: right; white-space: nowrap; }
    .amounts tr.total td { border-top: 1.5px solid #1a1a1a; border-bottom: 1.5px solid #1a1a1a; font-weight: bold; font-size: 10pt; }
    .words { margin-top: 4px; font-style: italic; }
    .notice { margin-top: 6px; padding: 4px 6px; border: 1px solid #999; background: #f4f4f4; font-size: 7.4pt; }
    .signatures { margin-top: 7px; }
    .signatures td { width: 50%; padding-right: 10px; vertical-align: top; }
    .sigbox { height: 32px; border-bottom: 1px solid #1a1a1a; }
    .footer { margin-top: 6px; border-top: 1px solid #bbb; padding-top: 3px; font-size: 6.8pt; color: #555; }
    .status { font-weight: bold; text-transform: uppercase; }
</style>
</head>
<body>
@foreach (['Exemplaire client', 'Exemplaire agence'] as $copyLabel)
    <div class="page {{ $loop->last ? '' : 'page-break' }}">
        @if ($isDuplicate)
            <div class="watermark">DUPLICATA</div>
        @endif

        <table class="header">
            <tr>
                <td>
                    <div class="company">{{ $company['name'] }}</div>
                    <div class="muted small">
                        {{ $company['address'] }}
                        @if (!empty($company['phone'])) &middot; Tél. {{ $company['phone'] }} @endif
                        @if (!empty($company['email'])) &middot; {{ $company['email'] }} @endif
                    </div>
                    @if (!empty($company['legal']))
                        <div class="muted small">{{ $company['legal'] }}</div>
                    @endif
                </td>
                <td style="text-align:right; width: 34%;">
                    <span class="copy-label">{{ $copyLabel }}</span>
                    @if ($isDuplicate)
                        <div class="small" style="margin-top:3px;"><strong>DUPLICATA</strong> (impression n° {{ $printCount }})</div>
                    @endif
                </td>
            </tr>
        </table>

        @yield('body')

        <div class="footer">
            <table>
                <tr>
                    <td>Empreinte de contrôle : <strong>{{ $fingerprint }}</strong></td>
                    <td style="text-align:right;">Imprimé le {{ $printedAt->format('d/m/Y à H:i') }}@if ($printedBy) par {{ $printedBy }}@endif</td>
                </tr>
            </table>
            Ce reçu fait foi de l'opération. Toute rature ou surcharge le rend nul. Conservez-le jusqu'à la clôture du transfert.
        </div>
    </div>
@endforeach
</body>
</html>
