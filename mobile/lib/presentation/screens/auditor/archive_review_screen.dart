import 'package:flutter/material.dart';
import '../../../data/models/archive_document_model.dart';
import '../../../data/repositories/archive_repository.dart';
import '../../widgets/empty_state.dart';
import '../../widgets/ustp_app_bar.dart';

class ArchiveReviewScreen extends StatefulWidget {
  const ArchiveReviewScreen({super.key});

  @override
  State<ArchiveReviewScreen> createState() => _ArchiveReviewScreenState();
}

class _ArchiveReviewScreenState extends State<ArchiveReviewScreen> {
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
      appBar: const UstpAppBar(title: 'Evidence Archive'),
      body: FutureBuilder<List<ArchiveDocumentModel>>(
        future: _archives,
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Text('Could not load archive records.\n${snapshot.error}', textAlign: TextAlign.center));
          }
          if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
          final archives = snapshot.data!;
          if (archives.isEmpty) {
            return const EmptyState(
              title: 'No evidence documents',
              subtitle: 'Uploaded evidence in your assigned departments will appear here.',
              icon: Icons.archive_outlined,
            );
          }
          return RefreshIndicator(
            onRefresh: () async => setState(() => _archives = _repository.getArchiveDocuments()),
            child: ListView.builder(
              itemCount: archives.length,
              itemBuilder: (context, index) {
                final archive = archives[index];
                return ListTile(
                  leading: const Icon(Icons.verified_user_outlined),
                  title: Text(archive.originalName),
                  subtitle: Text('${archive.fileCode} · ${archive.departmentCode}'),
                  trailing: Text(archive.referenceStandard),
                );
              },
            ),
          );
        },
      ),
    );
  }
}