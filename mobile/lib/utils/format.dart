import 'package:intl/intl.dart';

class Fmt {
  static String money(num? amount, String? currency) {
    final value = (amount ?? 0).toDouble();
    final hasCents = value != value.roundToDouble();
    final f = NumberFormat.decimalPatternDigits(locale: 'fr_FR', decimalDigits: hasCents ? 2 : 0);
    return '${f.format(value)} ${currency ?? ''}'.trim();
  }

  static String dateTime(DateTime? d) => d == null ? '—' : DateFormat('dd/MM/yyyy à HH:mm').format(d.toLocal());

  static String date(DateTime? d) => d == null ? '—' : DateFormat('dd/MM/yyyy').format(d.toLocal());

  /// Accepte "10 000", "10000,50" ou "10000.50".
  static double? parseAmount(String input) {
    final cleaned = input.replaceAll(RegExp(r'[\s  ]'), '').replaceAll(',', '.');
    return double.tryParse(cleaned);
  }
}
