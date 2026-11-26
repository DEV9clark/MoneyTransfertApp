import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../models/models.dart';
import '../services/api.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'receipt_screen.dart';

/// Suivi d'un envoi. Tant qu'il n'est pas payé, le code de paiement est affiché en grand
/// pour être montré à l'agent.
class TransferScreen extends StatefulWidget {
  final String uuid;
  final Transfer? initial;

  const TransferScreen({super.key, required this.uuid, this.initial});

  @override
  State<TransferScreen> createState() => _TransferScreenState();
}

class _TransferScreenState extends State<TransferScreen> {
  Transfer? _t;
  String? _error;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _t = widget.initial;
    if (_t == null) _reload();
  }

  Future<void> _reload() async {
    try {
      final t = await Api.instance.transfer(widget.uuid);
      if (mounted) setState(() {
        _t = t;
        _error = null;
      });
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Annuler cet envoi ?'),
        content: const Text("Vous n'avez encore rien payé : l'envoi sera simplement supprimé."),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Non')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text("Oui, annuler")),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _busy = true);
    try {
      final t = await Api.instance.cancelTransfer(widget.uuid);
      if (!mounted) return;
      setState(() => _t = t);
      showInfo(context, 'Envoi annulé.');
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _receipt(String kind) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => ReceiptScreen(uuid: widget.uuid, kind: kind, reference: _t?.reference)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = _t;
    return Scaffold(
      appBar: AppBar(title: const Text('Mon envoi')),
      body: t == null
          ? Center(child: _error != null ? Text(_error!) : const CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: [
                  if (t.status == TransferStatus.awaitingPayment) _PaymentCodeCard(t: t) else _StatusCard(t: t),
                  const SizedBox(height: 12),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        children: [
                          InfoRow('Bénéficiaire', t.recipientName),
                          InfoRow('Téléphone', t.recipientPhone),
                          InfoRow('Pays', t.destination ?? '—'),
                          const Divider(),
                          InfoRow('Montant envoyé', Fmt.money(t.amount, t.currency)),
                          InfoRow('Frais', Fmt.money(t.fee, t.currency)),
                          InfoRow('Total', Fmt.money(t.total, t.currency), strong: true),
                          const Divider(),
                          InfoRow('Référence', t.reference),
                          InfoRow('Créé le', Fmt.dateTime(t.createdAt)),
                          if (t.fundedAt != null) InfoRow('Payé en agence le', Fmt.dateTime(t.fundedAt)),
                          if (t.paidAt != null) InfoRow('Retiré le', Fmt.dateTime(t.paidAt)),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (t.hasDepositReceipt)
                    OutlinedButton.icon(
                      icon: const Icon(Icons.receipt_long),
                      label: const Text("Reçu d'envoi"),
                      onPressed: () => _receipt('deposit'),
                    ),
                  if (t.hasWithdrawalReceipt)
                    OutlinedButton.icon(
                      icon: const Icon(Icons.receipt),
                      label: const Text('Reçu de retrait'),
                      onPressed: () => _receipt('withdrawal'),
                    ),
                  if (t.status == TransferStatus.awaitingPayment) ...[
                    const SizedBox(height: 8),
                    TextButton.icon(
                      onPressed: _busy ? null : _cancel,
                      icon: const Icon(Icons.close),
                      label: const Text('Annuler cet envoi'),
                      style: TextButton.styleFrom(foregroundColor: Theme.of(context).colorScheme.error),
                    ),
                  ],
                ],
              ),
            ),
    );
  }
}

class _PaymentCodeCard extends StatelessWidget {
  final Transfer t;

  const _PaymentCodeCard({required this.t});

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final code = t.paymentCode ?? '';
    return Card(
      color: scheme.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            const Text('Code de paiement', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            GestureDetector(
              onLongPress: () {
                Clipboard.setData(ClipboardData(text: code));
                showInfo(context, 'Code copié');
              },
              child: Text(
                code.split('').join(' '),
                style: TextStyle(fontSize: 40, fontWeight: FontWeight.bold, letterSpacing: 2, color: scheme.onPrimaryContainer),
              ),
            ),
            const SizedBox(height: 12),
            Text('À payer en agence : ${Fmt.money(t.total, t.currency)}',
                style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            Text(
              'Montrez ce code à un agent Nisi\'s Transfer et payez en espèces'
              '${t.expiresAt != null ? ' avant le ${Fmt.dateTime(t.expiresAt)}' : ''}.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 4),
            const Text('Munissez-vous de votre pièce d\'identité.', textAlign: TextAlign.center, style: TextStyle(fontSize: 13)),
          ],
        ),
      ),
    );
  }
}

class _StatusCard extends StatelessWidget {
  final Transfer t;

  const _StatusCard({required this.t});

  @override
  Widget build(BuildContext context) {
    final info = statusInfo(t.status);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            Icon(info.icon, size: 48, color: info.color),
            const SizedBox(height: 8),
            Text(info.label, style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: info.color)),
            const SizedBox(height: 6),
            Text(info.hint, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}
