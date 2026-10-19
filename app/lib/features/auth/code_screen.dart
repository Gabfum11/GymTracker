import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../theme.dart';
import 'auth_view_model.dart';
import 'auth_widgets.dart';

/// Il codice a 6 cifre arrivato via email: per verificare l'email o per il recupero password.
class CodeScreen extends StatefulWidget {
  const CodeScreen({super.key, required this.auth});

  final AuthViewModel auth;

  @override
  State<CodeScreen> createState() => _CodeScreenState();
}

class _CodeScreenState extends State<CodeScreen> {
  final _code = TextEditingController();

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  void _submit() => widget.auth.submitCode(_code.text);

  @override
  Widget build(BuildContext context) {
    final auth = widget.auth;
    return AuthPage(
      onBack: () => auth.goTo(auth.codeIsForReset ? AuthStep.forgot : AuthStep.register),
      backLabel: 'Cambia email',
      children: [
        Text('Controlla la tua email', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 30)),
        Text.rich(TextSpan(
          style: const TextStyle(color: AppColors.muted),
          children: [
            const TextSpan(text: 'Abbiamo mandato un codice a 6 cifre a '),
            TextSpan(text: auth.pendingEmail, style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w600)),
            const TextSpan(text: '. Vale 10 minuti.'),
          ],
        )),
        const Text('Codice', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
        TextField(
          key: const Key('code'),
          controller: _code,
          autofocus: true,
          maxLength: 6,
          textAlign: TextAlign.center,
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          // sul telefono la tastiera propone il codice appena arrivato via email
          autofillHints: const [AutofillHints.oneTimeCode],
          onSubmitted: (_) => _submit(),
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: 34, letterSpacing: 14),
          decoration: InputDecoration(
            counterText: '',
            hintText: '······',
            filled: true,
            fillColor: AppColors.surface,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppColors.lime),
            ),
          ),
        ),
        if (auth.error != null) AuthMessage(text: auth.error!),
        if (auth.info != null) AuthMessage(text: auth.info!, error: false),
        MainButton(label: 'Verifica', onPressed: _submit, busy: auth.busy),
        Wrap(
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            const Text('Non è arrivato? Guarda nello spam, oppure', style: TextStyle(color: AppColors.faint)),
            TextButton(onPressed: auth.busy ? null : auth.resendCode, child: const Text('rimanda il codice')),
          ],
        ),
      ],
    );
  }
}
