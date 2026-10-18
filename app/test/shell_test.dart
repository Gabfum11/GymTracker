import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gymtracker/api/api_client.dart';
import 'package:gymtracker/shell.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  // nessun allenamento in corso: /workouts/current risponde null
  ApiClient fakeApi() => ApiClient(
        baseUrl: 'http://api.test',
        httpClient: MockClient((_) async => http.Response('null', 200)),
      );

  testWidgets('la barra in basso ha le 4 voci', (tester) async {
    await tester.pumpWidget(MaterialApp(home: Shell(api: fakeApi())));

    for (final label in ['Allena', 'Storico', 'Progressi', 'Profilo']) {
      expect(find.text(label), findsOneWidget);
    }
  });

  testWidgets('senza allenamento in corso, Allena mostra "Inizia allenamento"', (tester) async {
    await tester.pumpWidget(MaterialApp(home: Shell(api: fakeApi())));
    await tester.pumpAndSettle();

    expect(find.text('Inizia allenamento'), findsOneWidget);
  });

  testWidgets('toccando una voce si cambia schermata', (tester) async {
    await tester.pumpWidget(MaterialApp(home: Shell(api: fakeApi())));

    await tester.tap(find.text('Progressi'));
    await tester.pumpAndSettle();

    expect(find.widgetWithText(AppBar, 'Progressi'), findsOneWidget);
  });
}
