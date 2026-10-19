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
      // sopra ogni schermata: l'avviso quando il server (Render gratuito) si sta svegliando
      builder: (context, child) => Column(
        children: [
          ValueListenableBuilder<bool>(
            valueListenable: api.slow,
            builder: (context, slow, _) => slow ? const _WakingBanner() : const SizedBox.shrink(),
          ),
          Expanded(child: child!),
        ],
      ),
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

class _WakingBanner extends StatelessWidget {
  const _WakingBanner();

  @override
  Widget build(BuildContext context) => Material(
        color: AppColors.surface2,
        child: SafeArea(
          bottom: false,
          child: Semantics(
            liveRegion: true,
            child: const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              child: Row(
                children: [
                  SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                  SizedBox(width: 10),
                  Expanded(child: Text('Sto svegliando il server… la prima volta può volerci un minuto.')),
                ],
              ),
            ),
          ),
        ),
      );
}
