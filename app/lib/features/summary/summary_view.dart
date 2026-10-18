import 'package:flutter/material.dart';

import '../../models/workout_summary.dart';
import '../../theme.dart';
import '../../util/format.dart';

/// Il contenuto del riepilogo: tre riquadri, il box dei record, gli esercizi con le serie.
/// Lo usano la schermata di fine allenamento e (passo 12) il foglio dello Storico.
class SummaryView extends StatelessWidget {
  const SummaryView({super.key, required this.summary});

  final WorkoutSummary summary;

  bool _weighted(String exercise) => summary.exercises.any((e) => e.name == exercise && e.weighted);

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return ListView(
      padding: const EdgeInsets.all(14),
      children: [
        Row(
          children: [
            _Stat(value: "${summary.durationMinutes}'", label: 'durata'),
            const SizedBox(width: 8),
            _Stat(value: '${summary.setCount}', label: 'serie'),
            const SizedBox(width: 8),
            _Stat(value: '${summary.exerciseCount}', label: 'esercizi'),
          ],
        ),
        if (summary.records.isNotEmpty) ...[
          const SizedBox(height: 12),
          _RecordsBox(summary: summary, weighted: _weighted),
        ],
        const SizedBox(height: 6),
        for (final exercise in summary.exercises)
          Container(
            padding: const EdgeInsets.symmetric(vertical: 12),
            decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: AppColors.rule))),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(exercise.name, style: text.titleMedium)),
                    Text(
                      exercise.sets.length == 1 ? '1 serie' : '${exercise.sets.length} serie',
                      style: const TextStyle(color: AppColors.faint),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    for (final set in exercise.sets)
                      _Chip(formatSet(set.weightKg, set.reps, weighted: exercise.weighted), isRecord: set.isRecord),
                  ],
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _Stat extends StatelessWidget {
  const _Stat({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Expanded(
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(14)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(value, style: Theme.of(context).textTheme.headlineSmall),
              Text(label, style: const TextStyle(color: AppColors.muted, fontSize: 13)),
            ],
          ),
        ),
      );
}

/// Il box arancione: l'unico posto (insieme alle serie record) dove si usa l'arancione.
class _RecordsBox extends StatelessWidget {
  const _RecordsBox({required this.summary, required this.weighted});

  final WorkoutSummary summary;
  final bool Function(String exercise) weighted;

  @override
  Widget build(BuildContext context) {
    final count = summary.records.length;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: AppColors.recordSoft,
        border: Border.all(color: AppColors.record.withValues(alpha: 0.5)),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.emoji_events_outlined, size: 18, color: AppColors.record),
              const SizedBox(width: 6),
              Text(
                count == 1 ? 'Nuovo record' : '$count nuovi record',
                style: const TextStyle(color: AppColors.record, fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const SizedBox(height: 8),
          for (final r in summary.records)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Row(
                children: [
                  Expanded(child: Text(r.exercise, style: const TextStyle(fontWeight: FontWeight.w600))),
                  Text(
                    r.metric == 'reps' ? '${r.reps} rip' : formatSet(r.weightKg, r.reps, weighted: weighted(r.exercise)),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(color: AppColors.record),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip(this.label, {required this.isRecord});

  final String label;
  final bool isRecord;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        decoration: BoxDecoration(
          color: isRecord ? AppColors.record : AppColors.surface2,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          label,
          style: TextStyle(fontWeight: FontWeight.w600, color: isRecord ? AppColors.onRecord : AppColors.ink),
        ),
      );
}
