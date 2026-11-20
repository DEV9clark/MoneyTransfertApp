import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Clavier de saisie d'un code (PIN à 4 chiffres ou code SMS à 6 chiffres).
///
/// [onCompleted] renvoie : null si le code est accepté, '' pour simplement vider la saisie,
/// ou un message d'erreur à afficher (la saisie est vidée).
class PinPad extends StatefulWidget {
  final int length;
  final Future<String?> Function(String code) onCompleted;
  final String? footerLabel;
  final VoidCallback? onFooterTap;

  const PinPad({
    super.key,
    this.length = 4,
    required this.onCompleted,
    this.footerLabel,
    this.onFooterTap,
  });

  @override
  State<PinPad> createState() => _PinPadState();
}

class _PinPadState extends State<PinPad> {
  String _code = '';
  String? _error;
  bool _busy = false;

  Future<void> _tap(String digit) async {
    if (_busy || _code.length >= widget.length) return;
    HapticFeedback.selectionClick();
    setState(() {
      _code += digit;
      _error = null;
    });
    if (_code.length == widget.length) {
      setState(() => _busy = true);
      final error = await widget.onCompleted(_code);
      if (!mounted) return;
      setState(() {
        _busy = false;
        if (error != null) {
          // Chaîne vide = simple remise à zéro (ex. passage à la confirmation du PIN).
          _error = error.isEmpty ? null : error;
          _code = '';
          if (error.isNotEmpty) HapticFeedback.vibrate();
        }
      });
    }
  }

  void _erase() {
    if (_busy || _code.isEmpty) return;
    setState(() => _code = _code.substring(0, _code.length - 1));
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(widget.length, (i) {
            final filled = i < _code.length;
            return AnimatedContainer(
              duration: const Duration(milliseconds: 120),
              margin: const EdgeInsets.symmetric(horizontal: 8),
              width: 18,
              height: 18,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: filled ? scheme.primary : Colors.transparent,
                border: Border.all(color: _error != null ? scheme.error : scheme.primary, width: 2),
              ),
            );
          }),
        ),
        SizedBox(
          height: 44,
          child: Center(
            child: _busy
                ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))
                : Text(_error ?? '', textAlign: TextAlign.center, style: TextStyle(color: scheme.error)),
          ),
        ),
        for (final row in const [
          ['1', '2', '3'],
          ['4', '5', '6'],
          ['7', '8', '9'],
        ])
          Row(mainAxisAlignment: MainAxisAlignment.center, children: row.map(_key).toList()),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const SizedBox(width: 88, height: 72),
            _key('0'),
            SizedBox(
              width: 88,
              height: 72,
              child: IconButton(
                onPressed: _erase,
                icon: const Icon(Icons.backspace_outlined),
                tooltip: 'Effacer',
              ),
            ),
          ],
        ),
        if (widget.footerLabel != null)
          TextButton(onPressed: widget.onFooterTap, child: Text(widget.footerLabel!)),
      ],
    );
  }

  Widget _key(String digit) {
    return SizedBox(
      width: 88,
      height: 72,
      child: TextButton(
        style: TextButton.styleFrom(shape: const CircleBorder()),
        onPressed: () => _tap(digit),
        child: Text(digit, style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w500)),
      ),
    );
  }
}
