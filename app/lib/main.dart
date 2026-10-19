import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'features/auth/auth_flow.dart';
import 'features/auth/auth_view_model.dart';
import 'shell.dart';
import 'theme.dart';

void main() {
  final api = ApiClient(baseUrl: apiUrl);
  final auth = AuthViewModel(api)..restore();
  runApp(GymTrackerApp(api: api, auth: auth));
}

class GymTrackerApp extends StatelessWidget {
  const GymTrackerApp({super.key, required this.api, required this.auth});

  final ApiClient api;
  final AuthViewModel auth;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'GymTracker',
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      // la "porta d'ingresso": sessione valida → app, altrimenti il flusso di accesso
      home: ListenableBuilder(
        listenable: auth,
        builder: (context, _) {
          if (!auth.ready) return const Scaffold(body: Center(child: CircularProgressIndicator()));
          // la chiave cambia con l'utente: dopo Esci e un nuovo accesso la chat riparte da zero
          return auth.loggedIn ? Shell(key: ValueKey(auth.user?.id), api: api, auth: auth) : AuthFlow(auth: auth);
        },
      ),
    );
  }
}
