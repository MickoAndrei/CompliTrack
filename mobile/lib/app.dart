import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'core/theme/ustp_theme.dart';
import 'presentation/navigation/app_router.dart';
import 'presentation/providers/auth_provider.dart';

class CompliTrackApp extends StatelessWidget {
  const CompliTrackApp({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, child) {
        if (authProvider.isInitializing) {
          return MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: UstpTheme.lightTheme,
            home: const Scaffold(
              body: Center(
                child: CircularProgressIndicator(),
              ),
            ),
          );
        }

        final router = AppRouter.buildRouter(
          currentRoleId: authProvider.roleId,
          isLoggedIn: authProvider.isLoggedIn,
        );

        return MaterialApp.router(
          title: 'CompliTrack V8',
          debugShowCheckedModeBanner: false,
          theme: UstpTheme.lightTheme,
          routerConfig: router,
        );
      },
    );
  }
}
