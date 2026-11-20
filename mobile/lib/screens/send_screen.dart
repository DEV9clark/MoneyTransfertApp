import 'dart:async';

import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/api.dart';
import '../utils/format.dart';
import '../widgets/common.dart';
import 'transfer_screen.dart';

/// Préparation d'un envoi en un seul écran : à qui, combien, et c'est tout.
class SendScreen extends StatefulWidget {
  const SendScreen({super.key});

  @override
  State<SendScreen> createState() => _SendScreenState();
}

class _SendScreenState extends State<SendScreen> {
  final _api = Api.instance;
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _amount = TextEditingController();

  List<Country> _countries = [];
  List<Beneficiary> _recent = [];
  int? _countryId;

  Quote? _quote;
  String? _quoteError;
  bool _quoting = false;
  Timer? _debounce;

  bool _loading = true;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _amount.addListener(_onAmountChanged);
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _name.dispose();
    _phone.dispose();
    _amount.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final results = await Future.wait([_api.countries(), _api.beneficiaries()]);
      if (!mounted) return;
      setState(() {
        _countries = results[0] as List<Country>;
        _recent = results[1] as List<Beneficiary>;
        _countryId = _api.me?.countryId ?? (_countries.isNotEmpty ? _countries.first.id : null);
      });
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Country? get _country {
    for (final c in _countries) {
      if (c.id == _countryId) return c;
    }
    return null;
  }

  void _pick(Beneficiary b) {
    setState(() {
      _name.text = b.name;
      _phone.text = b.phone;
      if (b.countryId != null) _countryId = b.countryId;
    });
  }

  void _onAmountChanged() {
    _debounce?.cancel();
    final amount = Fmt.parseAmount(_amount.text);
    if (amount == null || amount <= 0) {
      setState(() {
        _quote = null;
        _quoteError = null;
      });
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () => _fetchQuote(amount));
  }

  Future<void> _fetchQuote(double amount) async {
    setState(() => _quoting = true);
    try {
      final q = await _api.quote(amount);
      if (!mounted || Fmt.parseAmount(_amount.text) != amount) return;
      setState(() {
        _quote = q;
        _quoteError = null;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _quote = null;
          _quoteError = e.toString();
        });
      }
    } finally {
      if (mounted) setState(() => _quoting = false);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final amount = Fmt.parseAmount(_amount.text)!;
    final q = _quote;
    if (q == null) {
      await _fetchQuote(amount);
      if (_quote == null) return;
    }
    if (!mounted) return;

    final quote = _quote!;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => Padding(
        padding: const EdgeInsets.fromLTRB(24, 0, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Vérifiez votre envoi', style: Theme.of(ctx).textTheme.titleLarge),
            const SizedBox(height: 12),
            InfoRow('À', _name.text.trim()),
            InfoRow('Téléphone', '${_country?.dialCode ?? ''} ${_phone.text.trim()}'),
            InfoRow('Pays', _country?.name ?? ''),
            const Divider(),
            InfoRow('Montant envoyé', Fmt.money(quote.amount, quote.currency)),
            InfoRow('Frais', Fmt.money(quote.fee, quote.currency)),
            InfoRow('À payer en agence', Fmt.money(quote.total, quote.currency), strong: true),
            const SizedBox(height: 16),
            PrimaryButton(label: 'Confirmer', icon: Icons.check, onPressed: () => Navigator.pop(ctx, true)),
          ],
        ),
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _submitting = true);
    try {
      final t = await _api.createTransfer(
        amount: amount,
        destinationCountryId: _countryId!,
        recipientName: _name.text,
        recipientPhone: _phone.text,
      );
      if (!mounted) return;
      Navigator.pushReplacement(context, MaterialPageRoute(builder: (_) => TransferScreen(uuid: t.uuid, initial: t)));
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Nouvel envoi')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_recent.isNotEmpty) ...[
                    const Text('Envoyer à nouveau', style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 40,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: _recent.length,
                        separatorBuilder: (context, index) => const SizedBox(width: 8),
                        itemBuilder: (_, i) => ActionChip(
                          avatar: const Icon(Icons.person, size: 18),
                          label: Text(_recent[i].name),
                          onPressed: () => _pick(_recent[i]),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                  ],
                  const Text('Bénéficiaire', style: TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  CountryField(
                    countries: _countries,
                    value: _countryId,
                    label: 'Pays du bénéficiaire',
                    onChanged: (v) => setState(() => _countryId = v),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _name,
                    textCapitalization: TextCapitalization.words,
                    decoration: const InputDecoration(
                      labelText: 'Nom complet',
                      helperText: "Comme sur sa pièce d'identité",
                    ),
                    validator: (v) => (v ?? '').trim().length < 2 ? 'Nom requis' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    decoration: InputDecoration(
                      labelText: 'Téléphone',
                      helperText: 'Il recevra le code de retrait par SMS',
                      prefixText: _country != null ? '${_country!.dialCode} ' : null,
                    ),
                    validator: (v) {
                      final d = (v ?? '').replaceAll(RegExp(r'\D'), '');
                      return d.length < 7 || d.length > 15 ? 'Numéro invalide' : null;
                    },
                  ),
                  const SizedBox(height: 24),
                  const Text('Montant', style: TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  TextFormField(
                    controller: _amount,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
                    decoration: InputDecoration(labelText: 'Montant à envoyer', suffixText: _quote?.currency ?? 'XOF'),
                    validator: (v) {
                      final a = Fmt.parseAmount(v ?? '');
                      if (a == null || a <= 0) return 'Montant invalide';
                      return _quoteError;
                    },
                  ),
                  const SizedBox(height: 12),
                  if (_quoting) const LinearProgressIndicator(),
                  if (_quoteError != null)
                    Text(_quoteError!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  if (_quote != null && !_quoting)
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        child: Column(
                          children: [
                            InfoRow('Frais', Fmt.money(_quote!.fee, _quote!.currency)),
                            InfoRow('À payer en agence', Fmt.money(_quote!.total, _quote!.currency), strong: true),
                          ],
                        ),
                      ),
                    ),
                  const SizedBox(height: 28),
                  PrimaryButton(label: "Préparer l'envoi", onPressed: _submit, busy: _submitting),
                  const SizedBox(height: 12),
                  const Text(
                    "Vous paierez en espèces chez un agent. Le bénéficiaire reçoit son code dès votre paiement.",
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 13),
                  ),
                ],
              ),
            ),
    );
  }
}
