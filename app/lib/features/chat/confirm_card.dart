import 'package:flutter/material.dart';

import '../../theme.dart';
import 'chat_view_model.dart';

/// La scheda di conferma: nome dell'esercizio + una riga per serie (kg, ripetizioni, ×).
/// I numeri si toccano e si modificano; niente si salva finché non si preme "Conferma".
class ConfirmCard extends StatelessWidget {
  const ConfirmCard({super.key, required this.card, required this.viewModel});

  final CardItem card;
  final ChatViewModel viewModel;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
      decoration: BoxDecoration(
        color: AppColors.surface,
        border: Border.all(color: AppColors.rule),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(card.exercise, style: text.titleLarge),
          if (card.canUseTypedName)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(
                onPressed: () => viewModel.useTypedName(card),
                child: Text('Non è questo: crea «${card.typedName}»'),
              ),
            ),
          const SizedBox(height: 6),
          // la chiave cambia quando si aggiungono o tolgono righe: i campi si ricreano con i valori giusti
          KeyedSubtree(
            key: ValueKey('${identityHashCode(card)}-${card.revision}'),
            child: Column(children: [for (var i = 0; i < card.sets.length; i++) _setRow(context, i)]),
          ),
          const SizedBox(height: 10),
          if (card.saved)
            Row(
              children: [
                Text(
                  card.sets.length == 1 ? '1 serie salvata' : '${card.sets.length} serie salvate',
                  style: const TextStyle(color: AppColors.lime, fontWeight: FontWeight.w600),
                ),
                const Text(' · ', style: TextStyle(color: AppColors.muted)),
                TextButton(onPressed: () => viewModel.reopen(card), child: const Text('Modifica')),
              ],
            )
          else
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(onPressed: () => viewModel.addSet(card), child: const Text('+ Serie')),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: FilledButton(
                    onPressed: viewModel.busy ? null : () => viewModel.confirm(card),
                    child: const Text('Conferma'),
                  ),
                ),
              ],
            ),
        ],
      ),
    );
  }

  Widget _setRow(BuildContext context, int i) {
    final set = card.sets[i];
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 6),
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.rule))),
      child: Row(
        children: [
          SizedBox(width: 24, child: Text('${i + 1}', style: const TextStyle(color: AppColors.faint))),
          Expanded(
            child: _NumberField(
              key: Key('kg-$i'),
              initial: set.kg,
              suffix: 'kg',
              prefix: card.weighted ? '+' : null,
              decimal: true,
              readOnly: card.saved,
              onChanged: (v) => viewModel.updateSet(card, i, kg: v),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: _NumberField(
              key: Key('reps-$i'),
              initial: set.reps,
              suffix: 'rip',
              readOnly: card.saved,
              onChanged: (v) => viewModel.updateSet(card, i, reps: v),
            ),
          ),
          SizedBox(
            width: 40,
            child: card.saved
                ? null
                : IconButton(
                    tooltip: 'Elimina serie ${i + 1}',
                    icon: const Icon(Icons.close, size: 18, color: AppColors.faint),
                    onPressed: () => viewModel.removeSet(card, i),
                  ),
          ),
        ],
      ),
    );
  }
}

/// Un campo numerico grande, con l'unità di misura accanto.
class _NumberField extends StatelessWidget {
  const _NumberField({
    super.key,
    required this.initial,
    required this.suffix,
    required this.onChanged,
    required this.readOnly,
    this.prefix,
    this.decimal = false,
  });

  final String initial;
  final String suffix;
  final String? prefix;
  final bool decimal;
  final bool readOnly;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      initialValue: initial,
      readOnly: readOnly,
      onChanged: onChanged,
      // su telefono apre la tastiera numerica (con la virgola per i kg)
      keyboardType: TextInputType.numberWithOptions(decimal: decimal),
      style: Theme.of(context).textTheme.headlineSmall,
      decoration: InputDecoration(
        isDense: true,
        filled: true,
        fillColor: AppColors.surface2,
        prefixText: prefix,
        suffixText: suffix,
        suffixStyle: const TextStyle(color: AppColors.muted),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: AppColors.lime),
        ),
      ),
    );
  }
}
