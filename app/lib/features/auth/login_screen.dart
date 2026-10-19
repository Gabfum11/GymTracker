import 'package:flutter/material.dart';

import 'auth_view_model.dart';
import 'auth_widgets.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.auth});

  final AuthViewModel auth;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  void _submit() => widget.auth.login(_email.text, _password.text);

  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    return AuthPage(
      bottom: Wrap(
        // Wrap e non Row: sugli schermi stretti va a capo invece di uscire dallo schermo
        alignment: WrapAlignment.center,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          const Text('Non hai un account?'),
          TextButton(onPressed: () => auth.goTo(AuthStep.register), child: const Text('Registrati')),
        ],
      ),
      children: [
        const Brand(),
        Text('Accedi', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 30)),
        if (auth.info != null) AuthMessage(text: auth.info!, error: false),
        // AutofillGroup: il browser/telefono propone email e password salvate
        AutofillGroup(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              LabeledField(
                key: const Key('login-email'),
                label: 'Email',
                hint: 'nome@email.it',
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                autofillHints: const [AutofillHints.email],
              ),
              const SizedBox(height: 12),
              LabeledField(
                key: const Key('login-password'),
                label: 'Password',
                controller: _password,
                password: true,
                autofillHints: const [AutofillHints.password],
                onSubmitted: (_) => _submit(),
              ),
            ],
          ),
        ),
        Align(
          alignment: Alignment.centerRight,
          child: TextButton(onPressed: () => auth.goTo(AuthStep.forgot), child: const Text('Password dimenticata?')),
        ),
        if (auth.error != null) AuthMessage(text: auth.error!),
        MainButton(label: 'Accedi', onPressed: _submit, busy: auth.busy),
      ],
    );
  }
}
