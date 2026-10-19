import 'package:flutter/material.dart';

import 'auth_view_model.dart';
import 'auth_widgets.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key, required this.auth});

  final AuthViewModel auth;

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  void _submit() => widget.auth.register(_name.text, _email.text, _password.text);

  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    return AuthPage(
      onBack: () => auth.goTo(AuthStep.login),
      bottom: Wrap(
        // Wrap e non Row: sugli schermi stretti va a capo invece di uscire dallo schermo
        alignment: WrapAlignment.center,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          const Text('Hai già un account?'),
          TextButton(onPressed: () => auth.goTo(AuthStep.login), child: const Text('Accedi')),
        ],
      ),
      children: [
        Text('Crea il tuo account', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 30)),
        AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              LabeledField(
                key: const Key('register-name'),
                label: 'Nome',
                controller: _name,
                autofillHints: const [AutofillHints.givenName],
              ),
              const SizedBox(height: 12),
              LabeledField(
                key: const Key('register-email'),
                label: 'Email',
                controller: _email,
                help: 'Ti mandiamo un codice per verificarla.',
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
              ),
              const SizedBox(height: 12),
              LabeledField(
                key: const Key('register-password'),
                label: 'Password',
                controller: _password,
                password: true,
                help: 'Almeno 8 caratteri.',
                autofillHints: const [AutofillHints.newPassword],
                onSubmitted: (_) => _submit(),
              ),
            ],
          ),
        ),
        if (auth.error != null) AuthMessage(text: auth.error!),
        MainButton(label: 'Crea account', onPressed: _submit, busy: auth.busy),
      ],
    );
  }
}
