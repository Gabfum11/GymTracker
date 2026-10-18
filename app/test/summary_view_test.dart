import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gymtracker/features/summary/summary_view.dart';
import 'package:gymtracker/models/workout_summary.dart';

import 'fake_backend.dart';

void main() {
  testWidgets('il riepilogo mostra conteggi, record e serie', (tester) async {
    final summary = WorkoutSummary.fromJson({
      ...summaryJson(exercises: [
        exerciseJson(12, 'Dip zavorrati', [[45, 8], [45, 6]], weighted: true),
        exerciseJson(3, 'Panca piana', [[80, 5]]),
      ]),
      'duration_minutes': 58,
      'records': [
        {'exercise': 'Dip zavorrati', 'date': '2026-10-18', 'metric': 'kg', 'weight_kg': 45.0, 'reps': 8},
      ],
    });

    await tester.pumpWidget(MaterialApp(home: Scaffold(body: SummaryView(summary: summary))));

    expect(find.text("58'"), findsOneWidget); // durata
    expect(find.text('3'), findsOneWidget); // serie
    expect(find.text('2'), findsOneWidget); // esercizi
    expect(find.text('Nuovo record'), findsOneWidget);
    expect(find.text('+45 kg × 8'), findsWidgets); // nel box record e tra le serie
    expect(find.text('80 kg × 5'), findsOneWidget);
  });

  testWidgets('senza record niente box arancione', (tester) async {
    final summary = WorkoutSummary.fromJson(summaryJson(exercises: [exerciseJson(3, 'Panca piana', [[80, 5]])]));

    await tester.pumpWidget(MaterialApp(home: Scaffold(body: SummaryView(summary: summary))));

    expect(find.textContaining('record'), findsNothing);
  });
}
