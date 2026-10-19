import 'package:flutter/material.dart';

import '../../theme.dart';

/// Il marchio: quadrato lime con il manubrio + "GymTracker".
class Brand extends StatelessWidget {
  const Brand({super.key, this.big = false});

  final bool big;

  @override
  Widget build(BuildContext context) {
    final size = big ? 52.0 : 38.0;
    return Row(
      children: [
        Container(
          width: size,
          height: size,
          decoration: BoxDecoration(color: AppColors.lime, borderRadius: BorderRadius.circular(size * 0.29)),
          child: Icon(Icons.fitness_center, color: AppColors.onLime, size: size * 0.58),
        ),
        const SizedBox(width: 10),
        // Flexible: su schermi strettissimi il nome va a capo invece di uscire dalla riga
        Flexible(child: Text('GymTracker', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontSize: big ? 40 : 26))),
      ],
    );
  }
}

/// La struttura comune delle schermate di accesso: contenuto che scorre + parte fissa in basso.
class AuthPage extends StatelessWidget {
  const AuthPage({super.key, required this.children, this.onBack, this.backLabel = 'Indietro', this.bottom});

  final List<Widget> children;
  final VoidCallback? onBack;
  final String backLabel;
  final Widget? bottom;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          // su computer la colonna non si allarga oltre una larghezza comoda
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Column(
              children: [
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
                    children: [
                      if (onBack != null)
                        Align(
                          alignment: Alignment.centerLeft,
                          child: TextButton(onPressed: onBack, child: Text('← $backLabel')),
                        ),
                      for (final child in children) Padding(padding: const EdgeInsets.only(bottom: 14), child: child),
                    ],
                  ),
                ),
                if (bottom != null)
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
                    decoration: const BoxDecoration(border: Border(top: BorderSide(color: AppColors.rule))),
                    child: bottom,
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Un campo con l'etichetta sempre visibile sopra (non solo il testo grigio dentro).
class LabeledField extends StatefulWidget {
  const LabeledField({
    super.key,
    required this.label,
    required this.controller,
    this.hint,
    this.help,
    this.password = false,
    this.keyboardType,
    this.autofillHints,
    this.onSubmitted,
  });

  final String label;
  final TextEditingController controller;
  final String? hint;
  final String? help;
  final bool password;
  final TextInputType? keyboardType;
  final Iterable<String>? autofillHints;
  final ValueChanged<String>? onSubmitted;

  @override
  State<LabeledField> createState() => _LabeledFieldState();
}

class _LabeledFieldState extends State<LabeledField> {
  bool _hidden = true;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(widget.label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
        const SizedBox(height: 6),
        TextField(
          controller: widget.controller,
          obscureText: widget.password && _hidden,
          keyboardType: widget.keyboardType,
          autofillHints: widget.autofillHints,
          onSubmitted: widget.onSubmitted,
          decoration: InputDecoration(
            hintText: widget.hint,
            filled: true,
            fillColor: AppColors.surface,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.rule)),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.rule)),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.lime)),
            suffixIcon: widget.password
                ? TextButton(
                    onPressed: () => setState(() => _hidden = !_hidden),
                    child: Text(_hidden ? 'Mostra' : 'Nascondi', style: const TextStyle(color: AppColors.muted)),
                  )
                : null,
          ),
        ),
        if (widget.help != null) ...[
          const SizedBox(height: 4),
          Text(widget.help!, style: const TextStyle(color: AppColors.faint, fontSize: 13)),
        ],
      ],
    );
  }
}

/// Messaggio d'errore (rosso) o di conferma (lime). Letto anche dai lettori di schermo.
class AuthMessage extends StatelessWidget {
  const AuthMessage({super.key, required this.text, this.error = true});

  final String text;
  final bool error;

  @override
  Widget build(BuildContext context) {
    final color = error ? AppColors.danger : AppColors.lime;
    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: error ? AppColors.dangerSoft : AppColors.limeSoft,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(error ? Icons.error_outline : Icons.check, size: 18, color: color),
            const SizedBox(width: 8),
            Expanded(child: Text(text, style: TextStyle(color: color, fontWeight: FontWeight.w600))),
          ],
        ),
      ),
    );
  }
}

/// Pulsante principale lime, con la rotellina mentre si aspetta il server.
class MainButton extends StatelessWidget {
  const MainButton({super.key, required this.label, required this.onPressed, this.busy = false});

  final String label;
  final VoidCallback onPressed;
  final bool busy;

  @override
  Widget build(BuildContext context) => FilledButton(
        onPressed: busy ? null : onPressed,
        child: busy
            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
            : Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
      );
}
