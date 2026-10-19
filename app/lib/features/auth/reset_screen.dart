import 'package:flutter/material.dart';

import '../../theme.dart';
import 'auth_view_model.dart';
import 'auth_widgets.dart';

class ResetScreen extends StatefulWidget {
  const ResetScreen({super.key, required this.auth});

  final AuthViewModel auth;

  @override
  State<ResetScreen> createState() => _ResetScreenState();
}

class _ResetScreenState extends State<ResetScreen> {
  final _password = TextEditingController();

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  void _submit() => widget.auth.resetPassword(_password.text);

  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    return AuthPage(
      children: [
        Text('Scegli una nuova password', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 30)),
        LabeledField(
          label: 'Nuova password',
          controller: _password,
          password: true,
          help: 'Almeno 8 caratteri.',
          autofillHints: const [AutofillHints.newPassword],
          onSubmitted: (_) => _submit(),
        ),
        const Text(
          'Per sicurezza verrai disconnesso da tutti i dispositivi: poi accedi con la nuova password.',
          style: TextStyle(color: AppColors.muted),
        ),
        if (auth.error != null) AuthMessage(text: auth.error!),
        MainButton(label: 'Salva nuova password', onPressed: _submit, busy: auth.busy),
      ],
    );
  }
}
