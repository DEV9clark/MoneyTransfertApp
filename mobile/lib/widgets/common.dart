import 'package:flutter/material.dart';

import '../models/models.dart';

void showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(
      content: Text(error.toString()),
      backgroundColor: Theme.of(context).colorScheme.error,
      behavior: SnackBarBehavior.floating,
    ));
}

void showInfo(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message), behavior: SnackBarBehavior.floating));
}

/// Gros bouton principal, pleine largeur, avec indicateur de chargement.
class PrimaryButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final IconData? icon;

  const PrimaryButton({super.key, required this.label, required this.onPressed, this.busy = false, this.icon});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 54,
      child: FilledButton.icon(
        onPressed: busy ? null : onPressed,
        icon: busy
            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
            : Icon(icon ?? Icons.arrow_forward),
        label: Text(label, style: const TextStyle(fontSize: 16)),
      ),
    );
  }
}

class InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final bool strong;

  const InfoRow(this.label, this.value, {super.key, this.strong = false});

  @override
  Widget build(BuildContext context) {
    final muted = Theme.of(context).colorScheme.onSurfaceVariant;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(child: Text(label, style: TextStyle(color: muted))),
          const SizedBox(width: 12),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(fontWeight: strong ? FontWeight.bold : FontWeight.w500, fontSize: strong ? 17 : 15),
            ),
          ),
        ],
      ),
    );
  }
}

/// Libellé, couleur et icône de chaque statut, en langage client.
({String label, String hint, Color color, IconData icon}) statusInfo(TransferStatus s) => switch (s) {
      TransferStatus.awaitingPayment => (
          label: 'À payer en agence',
          hint: 'Présentez votre code de paiement à un agent.',
          color: Colors.orange.shade800,
          icon: Icons.storefront,
        ),
      TransferStatus.pending => (
          label: 'Envoyé',
          hint: 'Le bénéficiaire a reçu son code par SMS et peut retirer l\'argent.',
          color: Colors.blue.shade700,
          icon: Icons.schedule_send,
        ),
      TransferStatus.completed => (
          label: 'Retiré',
          hint: 'Le bénéficiaire a retiré l\'argent.',
          color: Colors.green.shade700,
          icon: Icons.check_circle,
        ),
      TransferStatus.cancelled => (
          label: 'Annulé',
          hint: 'Cet envoi a été annulé.',
          color: Colors.grey.shade700,
          icon: Icons.cancel,
        ),
      TransferStatus.expired => (
          label: 'Expiré',
          hint: 'Le délai est dépassé.',
          color: Colors.grey.shade700,
          icon: Icons.timer_off,
        ),
      TransferStatus.unknown => (label: '—', hint: '', color: Colors.grey, icon: Icons.help_outline),
    };

class StatusBadge extends StatelessWidget {
  final TransferStatus status;

  const StatusBadge(this.status, {super.key});

  @override
  Widget build(BuildContext context) {
    final info = statusInfo(status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: info.color.withAlpha(28), borderRadius: BorderRadius.circular(20)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(info.icon, size: 14, color: info.color),
          const SizedBox(width: 4),
          Text(info.label, style: TextStyle(color: info.color, fontSize: 12, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

/// Sélecteur de pays (avec indicatif).
class CountryField extends StatelessWidget {
  final List<Country> countries;
  final int? value;
  final ValueChanged<int?> onChanged;
  final String label;

  const CountryField({
    super.key,
    required this.countries,
    required this.value,
    required this.onChanged,
    this.label = 'Pays',
  });

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<int>(
      key: ValueKey('country-$label-$value-${countries.length}'),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: countries
          .map((c) => DropdownMenuItem<int>(value: c.id, child: Text('${c.name}  ${c.dialCode}')))
          .toList(),
      onChanged: onChanged,
      validator: (v) => v == null ? 'Choisissez un pays' : null,
    );
  }
}
