import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gymtracker/features/chat/chat_screen.dart';
import 'package:gymtracker/features/chat/chat_view_model.dart';

import 'fake_backend.dart';

void main() {
  testWidgets('flusso completo: inizia, scrivi, modifica, conferma', (tester) async {
    final backend = FakeBackend()
      ..on('GET /workouts/current', null)
      ..on('POST /workouts', summaryJson())
      ..on('POST /parse', {
        'type': 'sets',
        'exercise': 'Dip zavorrati',
        'typed_name': 'dip',
        'matched_existing_id': 12,
        'weighted': true,
        'sets': [
          {'weight_kg': 45.0, 'reps': 8},
        ],
      })
      ..on('PUT /workouts/1/exercises/12/sets', summaryJson());
    final vm = ChatViewModel(backend.client());
    await tester.pumpWidget(MaterialApp(home: ChatScreen(viewModel: vm)));
    await tester.runAsync(vm.load);
    await tester.pump();

    await tester.tap(find.text('Inizia allenamento'));
    await tester.runAsync(() => Future<void>.delayed(Duration.zero));
    await tester.pump();
    expect(find.text('Prossimo esercizio'), findsOneWidget);
    expect(find.text('Fine allenamento'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('chat-input')), 'dip 45 per 8');
    await tester.tap(find.byKey(const Key('chat-send')));
    await tester.runAsync(() => Future<void>.delayed(Duration.zero));
    await tester.pump();
    expect(find.text('Dip zavorrati'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('reps-0')), '7');
    await tester.tap(find.text('Conferma'));
    await tester.runAsync(() => Future<void>.delayed(Duration.zero));
    await tester.pump();

    expect(find.text('1 serie salvata'), findsOneWidget);
    expect(backend.calls.last.body, {
      'sets': [
        {'weight_kg': 45.0, 'reps': 7},
      ],
    });
  });
}
