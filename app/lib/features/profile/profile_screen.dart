import 'package:flutter/material.dart';

import '../../theme.dart';
import '../auth/auth_view_model.dart';

/// Profilo. Per ora: chi sei ed Esci (calendario e record arrivano al passo 14).
class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key, required this.auth});

  final AuthViewModel auth;

  @override
  Widget build(BuildContext context) {
    final user = auth.user;
    return Scaffold(
      appBar: AppBar(title: const Text('Profilo')),
      body: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(18)),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: AppColors.limeSoft,
                  child: Text(
                    (user?.name ?? '?').characters.first.toUpperCase(),
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(color: AppColors.lime),
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user?.name ?? '', style: Theme.of(context).textTheme.titleLarge),
                      Text(user?.email ?? '', style: const TextStyle(color: AppColors.muted)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          OutlinedButton(onPressed: auth.logout, child: const Text('Esci')),
          const SizedBox(height: 8),
          TextButton(
            onPressed: auth.busy ? null : auth.logoutAll,
            child: const Text('Esci da tutti i dispositivi', style: TextStyle(color: AppColors.muted)),
          ),
        ],
      ),
    );
  }
}
