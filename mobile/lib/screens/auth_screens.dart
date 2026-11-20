import 'dart:async';

import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/api.dart';
import '../widgets/common.dart';
import '../widgets/pin_pad.dart';
import 'home_screen.dart';

/// Ce que le client a saisi sur l'écran d'accueil, transmis d'écran en écran.
class AuthFlow {
  final Country country;
  final String phone;
  final String purpose; // 'login' | 'register' | 'reset_pin'
  final String? otp;
  final String? name;

  const AuthFlow({required this.country, required this.phone, this.purpose = 'login', this.otp, this.name});

  String get displayPhone => '${country.dialCode} $phone';

  AuthFlow copyWith({String? purpose, String? otp, String? name}) => AuthFlow(
        country: country,
        phone: phone,
        purpose: purpose ?? this.purpose,
        otp: otp ?? this.otp,
        name: name ?? this.name,
      );
}

void _goHome(BuildContext context) {
  Navigator.of(context).pushAndRemoveUntil(
    MaterialPageRoute(builder: (_) => const HomeScreen()),
    (_) => false,
  );
}

// -----------------------------------------------------------------------------
// Connexion : code PIN
// -----------------------------------------------------------------------------

class PinLoginScreen extends StatelessWidget {
  final AuthFlow flow;

  const PinLoginScreen({super.key, required this.flow});

  Future<void> _forgot(BuildContext context) async {
    try {
      final debug = await Api.instance.requestOtp(flow.country.id, flow.phone, 'reset_pin');
      if (!context.mounted) return;
      Navigator.push(
        context,
        MaterialPageRoute(
          settings: const RouteSettings(name: OtpScreen.routeName),
          builder: (_) => OtpScreen(flow: flow.copyWith(purpose: 'reset_pin'), debugCode: debug),
        ),
      );
    } catch (e) {
      if (context.mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _CodeScaffold(
      title: 'Entrez votre code PIN',
      subtitle: flow.displayPhone,
      child: PinPad(
        length: 4,
        onCompleted: (pin) async {
          try {
            await Api.instance.login(flow.country.id, flow.phone, pin);
            if (context.mounted) _goHome(context);
            return null;
          } catch (e) {
            return e.toString();
          }
        },
        footerLabel: 'Code PIN oublié ?',
        onFooterTap: () => _forgot(context),
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Code SMS (inscription ou PIN oublié)
// -----------------------------------------------------------------------------

class OtpScreen extends StatefulWidget {
  static const routeName = 'otp';

  final AuthFlow flow;
  final String? debugCode;

  const OtpScreen({super.key, required this.flow, this.debugCode});

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  static const _resendDelay = 60;
  int _secondsLeft = _resendDelay;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _startTimer();
    if (widget.debugCode != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) showInfo(context, 'Mode test : code ${widget.debugCode}');
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _startTimer() {
    _timer?.cancel();
    setState(() => _secondsLeft = _resendDelay);
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) return t.cancel();
      setState(() => _secondsLeft--);
      if (_secondsLeft <= 0) t.cancel();
    });
  }

  Future<void> _resend() async {
    try {
      final debug = await Api.instance.requestOtp(widget.flow.country.id, widget.flow.phone, widget.flow.purpose);
      if (!mounted) return;
      showInfo(context, debug != null ? 'Mode test : code $debug' : 'Nouveau code envoyé.');
      _startTimer();
    } catch (e) {
      if (mounted) showError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _CodeScaffold(
      title: 'Code reçu par SMS',
      subtitle: 'Envoyé au ${widget.flow.displayPhone}',
      child: PinPad(
        length: 6,
        // Le code est vérifié par le serveur à l'étape suivante, avec le PIN.
        onCompleted: (otp) async {
          final next = widget.flow.copyWith(otp: otp);
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => widget.flow.purpose == 'register' ? NameScreen(flow: next) : CreatePinScreen(flow: next),
            ),
          );
          return ''; // retour arrière : on vide la saisie
        },
        footerLabel: _secondsLeft > 0 ? 'Renvoyer le code dans $_secondsLeft s' : 'Renvoyer le code',
        onFooterTap: _secondsLeft > 0 ? null : _resend,
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Inscription : nom
// -----------------------------------------------------------------------------

class NameScreen extends StatefulWidget {
  final AuthFlow flow;

  const NameScreen({super.key, required this.flow});

  @override
  State<NameScreen> createState() => _NameScreenState();
}

class _NameScreenState extends State<NameScreen> {
  final _formKey = GlobalKey<FormState>();
  final _name = TextEditingController();

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  void _next() {
    if (!_formKey.currentState!.validate()) return;
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => CreatePinScreen(flow: widget.flow.copyWith(name: _name.text.trim()))),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('Comment vous appelez-vous ?', style: Theme.of(context).textTheme.headlineSmall),
                const SizedBox(height: 8),
                const Text("Indiquez le nom inscrit sur votre pièce d'identité : il figurera sur vos reçus."),
                const SizedBox(height: 24),
                TextFormField(
                  controller: _name,
                  autofocus: true,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Prénom et nom'),
                  validator: (v) => (v ?? '').trim().length < 2 ? 'Nom requis' : null,
                  onFieldSubmitted: (_) => _next(),
                ),
                const Spacer(),
                PrimaryButton(label: 'Continuer', onPressed: _next),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Choix du PIN (inscription ou réinitialisation) : saisie puis confirmation
// -----------------------------------------------------------------------------

class CreatePinScreen extends StatefulWidget {
  final AuthFlow flow;

  const CreatePinScreen({super.key, required this.flow});

  @override
  State<CreatePinScreen> createState() => _CreatePinScreenState();
}

class _CreatePinScreenState extends State<CreatePinScreen> {
  String? _firstPin;

  Future<String?> _onPin(String pin) async {
    if (_firstPin == null) {
      setState(() => _firstPin = pin);
      return ''; // vide la saisie pour la confirmation
    }
    if (pin != _firstPin) {
      setState(() => _firstPin = null);
      return 'Les deux codes ne correspondent pas. Recommencez.';
    }

    final f = widget.flow;
    try {
      if (f.purpose == 'register') {
        await Api.instance.register(countryId: f.country.id, phone: f.phone, otp: f.otp!, name: f.name!, pin: pin);
      } else {
        await Api.instance.resetPin(countryId: f.country.id, phone: f.phone, otp: f.otp!, pin: pin);
      }
      if (mounted) _goHome(context);
      return null;
    } on ApiException catch (e) {
      final badOtp = e.statusCode == 410 || e.statusCode == 429 || e.message.startsWith('Code incorrect');
      if (badOtp && mounted) {
        // Code SMS faux ou expiré : retour à l'écran du code.
        showError(context, e);
        Navigator.of(context).popUntil(ModalRoute.withName(OtpScreen.routeName));
        return null;
      }
      setState(() => _firstPin = null);
      return e.message;
    }
  }

  @override
  Widget build(BuildContext context) {
    return _CodeScaffold(
      title: _firstPin == null ? 'Choisissez un code PIN' : 'Confirmez votre code PIN',
      subtitle: _firstPin == null
          ? '4 chiffres, à garder secret. Évitez 1234 ou 0000.'
          : 'Saisissez-le une seconde fois.',
      child: PinPad(length: 4, onCompleted: _onPin),
    );
  }
}

/// Mise en page commune des écrans de saisie de code.
class _CodeScaffold extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget child;

  const _CodeScaffold({required this.title, required this.subtitle, required this.child});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Column(
              children: [
                Text(title, textAlign: TextAlign.center, style: theme.textTheme.headlineSmall),
                const SizedBox(height: 8),
                Text(subtitle, textAlign: TextAlign.center, style: theme.textTheme.bodyMedium),
                const SizedBox(height: 28),
                child,
              ],
            ),
          ),
        ),
      ),
    );
  }
}
