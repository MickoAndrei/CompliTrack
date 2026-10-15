import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/archive_document_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class MyArchivesScreen extends StatefulWidget {
  const MyArchivesScreen({super.key});

  @override
  State<MyArchivesScreen> createState() => _MyArchivesScreenState();
}

class _MyArchivesScreenState extends State<MyArchivesScreen> {
  final _repository = ArchiveRepository();
  late Future<List<ArchiveDocumentModel>> _archives;

  @override
  void initState() {
    super.initState();
    _archives = _repository.getArchiveDocuments();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: const UstpAppBar(title: 'My Archives'),
      body: FutureBuilder<List<ArchiveDocumentModel>>(
        future: _archives,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text('Could not load your archives.\n${snapshot.error}', textAlign: TextAlign.center),
              ),
            );
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final archives = snapshot.data!;
          if (archives.isEmpty) {
            return const EmptyState(
              title: 'No archived evidence',
              subtitle: 'Documents you upload will appear here.',
              icon: Icons.archive_outlined,
            );
          }
          return RefreshIndicator(
            onRefresh: () async => setState(() => _archives = _repository.getArchiveDocuments()),
            child: ListView.separated(
              itemCount: archives.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final archive = archives[index];
                return ListTile(
                  leading: const Icon(Icons.insert_drive_file_outlined),
                  title: Text(archive.originalName),
                  subtitle: Text(
                    '${archive.fileCode} · ${archive.departmentCode} · ${archive.referenceStandard}',
                  ),
                  trailing: const Icon(Icons.lock_outline, color: AppColors.success),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
