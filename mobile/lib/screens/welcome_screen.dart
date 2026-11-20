import 'package:flutter/material.dart';

import '../config.dart';
import '../models/models.dart';
import '../services/api.dart';
import '../widgets/common.dart';
import 'auth_screens.dart';

/// Écran d'entrée : le client saisit seulement son numéro.
/// L'app choisit ensuite d'elle-même entre « code PIN » (compte existant) et « inscription ».
class WelcomeScreen extends StatefulWidget {
  final String? message;

  const WelcomeScreen({super.key, this.message});

  @override
  State<WelcomeScreen> createState() => _WelcomeScreenState();
}

class _WelcomeScreenState extends State<WelcomeScreen> {
  final _formKey = GlobalKey<FormState>();
  final _phone = TextEditingController();
  List<Country> _countries = [];
  int? _countryId;
  bool _loading = true;
  bool _busy = false;
  String? _loadError;

  @override
  void initState() {
    super.initState();
    _load();
    if (widget.message != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) showInfo(context, widget.message!);
      });
    }
  }

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final countries = await Api.instance.countries();
      if (!mounted) return;
      setState(() {
        _countries = countries;
        _countryId ??= countries
            .where((c) => c.code == AppConfig.defaultCountryCode)
            .map((c) => c.id)
            .firstOrNull ?? (countries.isNotEmpty ? countries.first.id : null);
      });
    } catch (e) {
      if (mounted) setState(() => _loadError = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Country get _country => _countries.firstWhere((c) => c.id == _countryId);

  Future<void> _continue() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() => _busy = true);
    try {
      final flow = AuthFlow(country: _country, phone: _phone.text.trim());
      final registered = await Api.instance.isRegistered(flow.country.id, flow.phone);
      if (!mounted) return;
      if (registered) {
        Navigator.push(context, MaterialPageRoute(builder: (_) => PinLoginScreen(flow: flow)));
      } else {
        final debug = await Api.instance.requestOtp(flow.country.id, flow.phone, 'register');
        if (!mounted) return;
        Navigator.push(
          context,
          MaterialPageRoute(
            settings: const RouteSettings(name: OtpScreen.routeName),
            builder: (_) => OtpScreen(flow: flow.copyWith(purpose: 'register'), debugCode: debug),
          ),
        );
      }
    } catch (e) {
      if (mounted) showError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _loadError != null
                ? _ErrorRetry(message: _loadError!, onRetry: _load)
                : Center(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Icon(Icons.send_to_mobile, size: 64, color: theme.colorScheme.primary),
                            const SizedBox(height: 16),
                            Text('Money Transfer', textAlign: TextAlign.center, style: theme.textTheme.headlineMedium),
                            const SizedBox(height: 8),
                            Text(
                              'Préparez vos envois depuis votre téléphone, payez en agence.',
                              textAlign: TextAlign.center,
                              style: theme.textTheme.bodyLarge,
                            ),
                            const SizedBox(height: 40),
                            CountryField(
                              countries: _countries,
                              value: _countryId,
                              onChanged: (v) => setState(() => _countryId = v),
                            ),
                            const SizedBox(height: 16),
                            TextFormField(
                              controller: _phone,
                              keyboardType: TextInputType.phone,
                              autofillHints: const [AutofillHints.telephoneNumber],
                              style: const TextStyle(fontSize: 20, letterSpacing: 1),
                              decoration: InputDecoration(
                                labelText: 'Votre numéro de téléphone',
                                prefixText: _countryId != null ? '${_country.dialCode} ' : null,
                              ),
                              validator: (v) {
                                final digits = (v ?? '').replaceAll(RegExp(r'\D'), '');
                                return digits.length < 7 ? 'Numéro invalide' : null;
                              },
                              onFieldSubmitted: (_) => _continue(),
                            ),
                            const SizedBox(height: 24),
                            PrimaryButton(label: 'Continuer', onPressed: _continue, busy: _busy),
                          ],
                        ),
                      ),
                    ),
                  ),
      ),
    );
  }
}

class _ErrorRetry extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorRetry({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Réessayer')),
          ],
        ),
      ),
    );
  }
}
