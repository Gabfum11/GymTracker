import 'package:flutter/material.dart';

import 'auth_view_model.dart';
import 'code_screen.dart';
import 'forgot_screen.dart';
import 'login_screen.dart';
import 'register_screen.dart';
import 'reset_screen.dart';
import 'welcome_screen.dart';

/// Mostra la schermata di accesso giusta per il passo in cui si trova AuthViewModel.
class AuthFlow extends StatelessWidget {
  const AuthFlow({super.key, required this.auth});

  final AuthViewModel auth;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: auth,
      builder: (context, _) => switch (auth.step) {
        AuthStep.welcome => WelcomeScreen(auth: auth),
        AuthStep.login => LoginScreen(auth: auth),
        AuthStep.register => RegisterScreen(auth: auth),
        AuthStep.code => CodeScreen(auth: auth),
        AuthStep.forgot => ForgotScreen(auth: auth),
        AuthStep.reset => ResetScreen(auth: auth),
      },
    );
  }
}
