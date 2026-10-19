import 'package:flutter/material.dart';

import '../../theme.dart';
import 'auth_view_model.dart';
import 'auth_widgets.dart';

class ForgotScreen extends StatefulWidget {
  const ForgotScreen({super.key, required this.auth});

  final AuthViewModel auth;

  @override
  State<ForgotScreen> createState() => _ForgotScreenState();
}

class _ForgotScreenState extends State<ForgotScreen> {
  final _email = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  void _submit() => widget.auth.forgotPassword(_email.text);

  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    return AuthPage(
      onBack: () => auth.goTo(AuthStep.login),
      children: [
        Text('Password dimenticata', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 30)),
        const Text(
          'Scrivi l\'email del tuo account: ti mandiamo un codice per sceglierne una nuova.',
          style: TextStyle(color: AppColors.muted),
        ),
        LabeledField(
          label: 'Email',
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          autofillHints: const [AutofillHints.email],
          onSubmitted: (_) => _submit(),
        ),
        if (auth.error != null) AuthMessage(text: auth.error!),
        MainButton(label: 'Mandami il codice', onPressed: _submit, busy: auth.busy),
      ],
    );
  }
}
