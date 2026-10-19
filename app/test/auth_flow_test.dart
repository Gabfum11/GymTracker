import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gymtracker/features/auth/auth_flow.dart';
import 'package:gymtracker/features/auth/auth_view_model.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'fake_backend.dart';

void main() {
  Future<void> settle(WidgetTester tester) async {
    await tester.runAsync(() => Future<void>.delayed(const Duration(milliseconds: 10)));
    await tester.pump();
  }

  testWidgets('benvenuto → accesso → registrazione → codice', (tester) async {
    SharedPreferences.setMockInitialValues({});
    final backend = FakeBackend()..on('POST /auth/register', {'message': 'ok'});
    final auth = AuthViewModel(backend.client());
    await tester.runAsync(auth.restore);
    await tester.pumpWidget(MaterialApp(home: AuthFlow(auth: auth)));

    // benvenuto: nome, frase, esempio di chat
    expect(find.text('Crea i tuoi allenamenti personalizzati'), findsOneWidget);
    expect(find.text('Che esercizio stai facendo?'), findsOneWidget);
    await tester.tap(find.text('Continua'));
    await settle(tester);

    // accesso, poi "Registrati"
    expect(find.text('Accedi'), findsWidgets);
    await tester.tap(find.text('Registrati'));
    await tester.pump();

    await tester.enterText(find.byKey(const Key('register-name')), 'Mario');
    await tester.enterText(find.byKey(const Key('register-email')), 'mario@test.it');
    await tester.enterText(find.byKey(const Key('register-password')), 'password123');
    await tester.ensureVisible(find.text('Crea account')); // scorre come farebbe una persona
    await tester.pump();
    await tester.tap(find.text('Crea account'));
    await settle(tester);

    expect(find.text('Controlla la tua email'), findsOneWidget);
    expect(find.textContaining('mario@test.it'), findsOneWidget);
  });

  testWidgets('errore di accesso mostrato sotto il form', (tester) async {
    SharedPreferences.setMockInitialValues({'welcome_seen': true});
    final backend = FakeBackend()..fail('POST /auth/login', 401, 'Email o password errati');
    final auth = AuthViewModel(backend.client());
    await tester.runAsync(auth.restore);
    await tester.pumpWidget(MaterialApp(home: AuthFlow(auth: auth)));

    await tester.enterText(find.byKey(const Key('login-email')), 'mario@test.it');
    await tester.enterText(find.byKey(const Key('login-password')), 'sbagliata1');
    await tester.ensureVisible(find.widgetWithText(FilledButton, 'Accedi'));
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, 'Accedi'));
    await settle(tester);

    expect(find.text('Email o password errati'), findsOneWidget);
  });
}
