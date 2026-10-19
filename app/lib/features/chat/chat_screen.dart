import 'package:flutter/material.dart';

import '../../theme.dart';
import '../summary/summary_screen.dart';
import 'chat_view_model.dart';
import 'confirm_card.dart';

/// La schermata Allena: la chat in alto, i pulsanti e il campo di testo sempre in basso.
class ChatScreen extends StatefulWidget {
  const ChatScreen({super.key, required this.viewModel});

  final ChatViewModel viewModel;

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _input = TextEditingController();

  ChatViewModel get vm => widget.viewModel;

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  void _send() {
    final text = _input.text;
    _input.clear();
    vm.send(text);
  }

  Future<void> _openSummary() async {
    final summary = await vm.currentSummary();
    if (summary == null || !mounted) return;
    await Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (_) => SummaryScreen(summary: summary, onSave: vm.finish)),
    );
  }

  Future<void> _confirmCancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text("Annullare l'allenamento?"),
        content: const Text('Tutte le serie di questo allenamento verranno cancellate.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('No')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Annulla allenamento')),
        ],
      ),
    );
    if (ok == true) await vm.cancel();
  }

  @override
  Widget build(BuildContext context) {
    // ListenableBuilder ridisegna la schermata ogni volta che il ViewModel chiama notifyListeners()
    return ListenableBuilder(
      listenable: vm,
      builder: (context, _) => Scaffold(
        appBar: AppBar(
          title: const Text('Allenamento'),
          actions: [
            if (vm.hasOpenWorkout)
              TextButton(onPressed: vm.busy ? null : _confirmCancel, child: const Text('Annulla')),
          ],
        ),
        body: Column(
          children: [
            Expanded(
              child: ListView.separated(
                reverse: true, // i messaggi nuovi in basso, e la lista parte dal fondo
                padding: const EdgeInsets.all(14),
                itemCount: vm.items.length,
                separatorBuilder: (_, _) => const SizedBox(height: 10),
                itemBuilder: (context, i) => _item(vm.items[vm.items.length - 1 - i]),
              ),
            ),
            if (vm.error != null) _ErrorBanner(vm.error!),
            if (vm.busy) const LinearProgressIndicator(minHeight: 2),
            SafeArea(top: false, child: _dock()),
          ],
        ),
      ),
    );
  }

  Widget _item(ChatItem item) => switch (item) {
        BotMessage(:final text) => _Bubble(text: text, mine: false),
        UserMessage(:final text) => _Bubble(text: text, mine: true),
        final CardItem card => ConfirmCard(card: card, viewModel: vm),
      };

  Widget _dock() {
    if (!vm.hasOpenWorkout) {
      return Padding(
        padding: const EdgeInsets.all(14),
        child: SizedBox(
          width: double.infinity,
          child: FilledButton(onPressed: vm.busy ? null : vm.startWorkout, child: const Text('Inizia allenamento')),
        ),
      );
    }
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.rule))),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(child: OutlinedButton(onPressed: vm.nextExercise, child: const Text('Prossimo esercizio'))),
              const SizedBox(width: 8),
              Expanded(child: OutlinedButton(onPressed: vm.busy ? null : _openSummary, child: const Text('Fine allenamento'))),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: TextField(
                  key: const Key('chat-input'),
                  controller: _input,
                  textInputAction: TextInputAction.send,
                  onSubmitted: (_) => _send(),
                  decoration: InputDecoration(
                    hintText: 'Es. dip 45 per 8, poi 45 per 6',
                    filled: true,
                    fillColor: AppColors.surface,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(999)),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filled(
                key: const Key('chat-send'),
                tooltip: 'Invia',
                onPressed: vm.busy ? null : _send,
                icon: const Icon(Icons.arrow_upward),
                style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.text, required this.mine});

  final String text;
  final bool mine;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 320),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: mine ? AppColors.surface2 : AppColors.surface,
            borderRadius: BorderRadius.only(
              topLeft: const Radius.circular(16),
              topRight: const Radius.circular(16),
              bottomLeft: Radius.circular(mine ? 16 : 6),
              bottomRight: Radius.circular(mine ? 6 : 16),
            ),
          ),
          child: Text(text),
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner(this.message);

  final String message;

  @override
  // niente arancione: nel progetto è riservato ai record
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        color: AppColors.surface2,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        child: Row(
          children: [
            const Icon(Icons.error_outline, size: 18, color: AppColors.ink),
            const SizedBox(width: 8),
            Expanded(child: Text(message)),
          ],
        ),
      );
}
