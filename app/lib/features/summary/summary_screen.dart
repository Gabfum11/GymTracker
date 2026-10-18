import 'package:flutter/material.dart';

import '../../models/workout_summary.dart';
import '../../theme.dart';
import '../../util/format.dart';
import 'summary_view.dart';

/// Riepilogo di fine allenamento: si controlla e si salva, oppure si torna alla chat.
class SummaryScreen extends StatelessWidget {
  const SummaryScreen({super.key, required this.summary, required this.onSave});

  final WorkoutSummary summary;

  /// Chiude l'allenamento sul backend; la schermata poi si chiude da sola.
  final Future<void> Function() onSave;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Riepilogo'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: Center(child: Text(formatDay(summary.startedAt), style: const TextStyle(color: AppColors.muted))),
          ),
        ],
      ),
      body: SummaryView(summary: summary),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
          decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.rule))),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Torna all\'allenamento'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: FilledButton(
                  onPressed: () async {
                    await onSave();
                    if (context.mounted) Navigator.pop(context);
                  },
                  child: const Text('Salva allenamento'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
