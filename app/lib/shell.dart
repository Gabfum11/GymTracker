import 'package:flutter/material.dart';

import 'api/api_client.dart';
import 'features/chat/chat_screen.dart';
import 'features/chat/chat_view_model.dart';

/// La "cornice" dell'app: la barra in basso con le 4 voci e la schermata scelta.
class Shell extends StatefulWidget {
  const Shell({super.key, required this.api});

  final ApiClient api;

  @override
  State<Shell> createState() => _ShellState();
}

class _ShellState extends State<Shell> {
  int _tab = 0;

  // creato una volta sola: la chat sopravvive ai cambi di tab
  late final _chat = ChatViewModel(widget.api)..load();

  @override
  void dispose() {
    _chat.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // IndexedStack tiene vive tutte le schermate: cambiando tab non si perde la chat
      body: IndexedStack(
        index: _tab,
        children: [
          ChatScreen(viewModel: _chat),
          const _Placeholder('Storico'),
          const _Placeholder('Progressi'),
          const _Placeholder('Profilo'),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.fitness_center), label: 'Allena'),
          NavigationDestination(icon: Icon(Icons.history), label: 'Storico'),
          NavigationDestination(icon: Icon(Icons.show_chart), label: 'Progressi'),
          NavigationDestination(icon: Icon(Icons.person_outline), label: 'Profilo'),
        ],
      ),
    );
  }
}

// Segnaposto: le schermate vere arrivano nei passi 12, 13 e 14.
class _Placeholder extends StatelessWidget {
  const _Placeholder(this.title);

  final String title;

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: Text(title)),
        body: const Center(child: Text('In arrivo')),
      );
}
