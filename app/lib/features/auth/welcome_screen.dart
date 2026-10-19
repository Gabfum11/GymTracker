import 'package:flutter/material.dart';

import '../../theme.dart';
import 'auth_view_model.dart';
import 'auth_widgets.dart';

/// Benvenuto (solo la prima volta): nome, frase e un esempio di conversazione con il bot.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key, required this.auth});

  final AuthViewModel auth;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;
    return AuthPage(
      bottom: SizedBox(width: double.infinity, child: MainButton(label: 'Continua', onPressed: auth.continueFromWelcome)),
      children: [
        const SizedBox(height: 24),
        const Brand(big: true),
        Text('Crea i tuoi allenamenti personalizzati', style: text.headlineSmall?.copyWith(fontSize: 34, height: 1.05)),
        // l'esempio: è un disegno statico, non una chat vera
        ExcludeSemantics(
          excluding: false,
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: AppColors.surface,
              border: Border.all(color: AppColors.rule),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const _Bubble('Che esercizio stai facendo?', mine: false),
                const _Bubble('dip con zavorra, 45 per 8 poi 45 per 6', mine: true),
                Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.screen,
                    border: Border.all(color: AppColors.rule),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Dip zavorrati', style: text.titleMedium),
                      const SizedBox(height: 6),
                      const _SetRow(1, '+45 kg', '8 rip'),
                      const _SetRow(2, '+45 kg', '6 rip'),
                      const SizedBox(height: 4),
                      const Text('✓ 2 serie salvate', style: TextStyle(color: AppColors.lime, fontWeight: FontWeight.w600, fontSize: 13)),
                    ],
                  ),
                ),
                const _Bubble('Fatto! Qual è il prossimo esercizio?', mine: false),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble(this.text, {required this.mine});

  final String text;
  final bool mine;

  @override
  Widget build(BuildContext context) => Align(
        alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
        child: Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          constraints: const BoxConstraints(maxWidth: 280),
          decoration: BoxDecoration(
            color: mine ? AppColors.limeSoft : AppColors.surface2,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Text(text, style: const TextStyle(fontSize: 14)),
        ),
      );
}

class _SetRow extends StatelessWidget {
  const _SetRow(this.n, this.kg, this.reps);

  final int n;
  final String kg;
  final String reps;

  @override
  Widget build(BuildContext context) {
    Widget cell(String t) => Expanded(
          child: Container(
            margin: const EdgeInsets.only(left: 8, bottom: 6),
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
            decoration: BoxDecoration(color: AppColors.surface2, borderRadius: BorderRadius.circular(8)),
            child: Text(t, style: Theme.of(context).textTheme.titleMedium),
          ),
        );
    return Row(children: [
      SizedBox(width: 14, child: Text('$n', style: const TextStyle(color: AppColors.faint))),
      cell(kg),
      cell(reps),
    ]);
  }
}
