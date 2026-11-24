import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../widgets/ustp_app_bar.dart';

class HelpReferenceScreen extends StatelessWidget {
  const HelpReferenceScreen({super.key, this.forAuditor = false});

  final bool forAuditor;

  static const _standards = [
    _StandardInfo(
      code: 'ARTA 3',
      name: 'Service Charter Publication',
      description:
          'Service Charter Publication & Accessibility requirements under the Philippine Anti-Red Tape Authority. This governs clear mapping, Citizen’s Charters, and processing time tables.',
    ),
    _StandardInfo(
      code: 'ARTA 7',
      name: 'Zero-Contact Policy',
      description:
          'Zero-Contact Policy Auditing Process. Restricts personal communication between public officers and clients during processing of government documents to eliminate corruption.',
    ),
    _StandardInfo(
      code: 'ARTA 20',
      name: 'Public Feedback Mechanism',
      description:
          'Public Feedback and Complaint Mechanism. Mandatory feedback dropboxes, hotlines, and continuous auditing of customer satisfaction across all active regional government desks.',
    ),
  ];

  void _showHelp(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Compliance guidance'),
        content: const Text(
          'These standards map Philippine national requirements to the compliance checks used by your campus.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        onProfileTap: () => context.go(
          forAuditor ? '/auditor/profile' : '/auditee/profile',
        ),
      ),
      body: forAuditor
          ? _AuditorHelpTopics(
              onHelpPressed: () => _showHelp(context),
            )
          : _buildStandardsBody(context),
    );
  }

  Widget _buildStandardsBody(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.ustpNavy,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Need guidance on compliance criteria?',
                      style: TextStyle(
                        fontFamily: 'Poppins',
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpGold,
                      ),
                    ),
                    const SizedBox(height: 5),
                    const Text(
                      'Explore government regulations and international standards mapping Philippine national requirements below.',
                      style: TextStyle(
                        fontSize: 11,
                        height: 1.45,
                        color: AppColors.ustpWhite,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              IconButton(
                onPressed: () => _showHelp(context),
                style: IconButton.styleFrom(
                  backgroundColor: AppColors.ustpWhite.withValues(alpha: 0.12),
                  foregroundColor: AppColors.ustpWhite,
                ),
                icon: const Icon(AppIcons.help),
                tooltip: 'Compliance guidance',
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const Text(
          'Mapped Compliance Standards',
          style: TextStyle(
            fontFamily: 'Poppins',
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: AppColors.ustpDark,
          ),
        ),
        const SizedBox(height: 10),
        for (final standard in _standards) ...[
          _StandardCard(standard: standard),
          const SizedBox(height: 10),
        ],
      ],
    );
  }
}

class _AuditorHelpTopics extends StatefulWidget {
  const _AuditorHelpTopics({required this.onHelpPressed});

  final VoidCallback onHelpPressed;

  @override
  State<_AuditorHelpTopics> createState() => _AuditorHelpTopicsState();
}

class _AuditorHelpTopicsState extends State<_AuditorHelpTopics> {
  static const _topics = [
    _HelpTopic(
      id: 'assignments',
      label: 'Prepare Assigned Audits',
      intro: 'Start from the audits explicitly assigned to your account.',
      steps: [
        _HelpStep(
          'Open the assignment',
          'Use My Assigned Audits to review scope, criteria, dates, and current workflow status.',
        ),
        _HelpStep(
          'Prepare the checklist',
          'Use the draft workspace to prepare audit questions. Question preparation and evaluation are separate.',
        ),
        _HelpStep(
          'Review submission readiness',
          'Follow the workspace readiness guidance before submitting the evaluation to the assigned reviewer.',
        ),
      ],
      action: 'Open My Assigned Audits',
      route: '/auditor/checks',
    ),
    _HelpTopic(
      id: 'evidence',
      label: 'Request & Review Evidence',
      intro:
          'Request only the evidence needed within the assigned audit scope.',
      steps: [
        _HelpStep(
          'Address the request',
          'Open the assigned workspace and create a request for the intended auditee. A request does not grant document-copying permission.',
        ),
        _HelpStep(
          'Review the permitted presentation',
          'Digital references can use permitted codes, locations, or descriptions without restricted identifiers. Physical originals can be arranged for inspection; record whether a required signature was actually verified.',
        ),
        _HelpStep(
          'Review authorized uploads and feedback',
          'Only owner-authorized copies permitted by document-control rules may be stored. Record review feedback through the request workflow.',
        ),
      ],
      note:
          'A file is not mandatory. Never request protected QMS uploads solely because a request exists. During-audit presentation and corrective evidence are separate.',
      action: 'Open assigned audit workspaces',
      route: '/auditor/checks',
    ),
    _HelpTopic(
      id: 'decisions',
      label: 'Evaluate & Submit N/C/O',
      intro: 'Base checklist decisions on reviewed evidence.',
      steps: [
        _HelpStep(
          'Interpret the decision',
          'N — Non-Conformance: a requirement was not met. C — Compliant: the requirement was met. O — Observation: a factual observation recorded by the auditor.',
        ),
        _HelpStep(
          'Keep unanswered items distinct',
          'Pending means unanswered, not a compliance result. These are process assessments, not employee performance ratings. A finding and a CAR are separate from a checklist decision.',
        ),
        _HelpStep(
          'Submit when ready',
          'Complete the existing evidence and submission requirements. Submission and acceptance lock results; follow reviewer feedback for a returned audit.',
        ),
      ],
      action: 'Open evaluation workspaces',
      route: '/auditor/checks',
    ),
    _HelpTopic(
      id: 'corrective',
      label: 'Review Corrective Actions',
      intro: 'Follow corrective evidence through the authorized CAR workflow.',
      steps: [
        _HelpStep(
          'Locate the CAR',
          'Use Corrective Actions to inspect the nonconforming check and its recorded corrective status within your access scope.',
        ),
        _HelpStep(
          'Review corrective evidence',
          'Evaluate the corrective documentation separately from during-audit evidence presentation. Uploading evidence does not change N/C/O or close a CAR.',
        ),
        _HelpStep(
          'Record the authorized outcome',
          'Use the existing status actions and required remarks. Keep the original finding, evidence, and status history connected.',
        ),
      ],
      action: 'Open Corrective Actions',
      route: '/auditor/corrective',
    ),
    _HelpTopic(
      id: 'standards',
      label: 'Standards & Document Control',
      intro: 'Apply verified criteria without extending document access.',
      steps: [
        _HelpStep(
          'Confirm the approved criteria',
          'Use the existing ISO 9001:2015 internal-audit and ARTA reference identifiers in the assignment. Ask the audit coordinator which approved criteria apply.',
        ),
        _HelpStep(
          'Inspect authorized documents',
          'Open Documents for records your account is allowed to access. Omit restricted titles and identifiers from references.',
        ),
        _HelpStep(
          'Respect controlled-form restrictions',
          'Confirm institutional permission before reproducing controlled forms or storing protected QMS content.',
        ),
      ],
      note:
          'No approved external resources are listed. Ask the audit coordinator for institution-approved references.',
      action: 'Open Documents',
      route: '/auditor/documents',
    ),
  ];

  final _searchController = TextEditingController();
  String _query = '';
  String _selectedTopicId = 'assignments';

  List<_HelpTopic> get _filteredTopics {
    final words = _query
        .toLowerCase()
        .trim()
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty);
    final terms = words.toList();
    if (terms.isEmpty) return _topics;

    return _topics.where((topic) {
      final searchableText = [
        topic.label,
        topic.intro,
        ...topic.steps.expand((step) => [step.title, step.description]),
        if (topic.note != null) topic.note!,
      ].join(' ').toLowerCase();
      return terms.every(searchableText.contains);
    }).toList();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _updateQuery(String query) {
    setState(() {
      _query = query;
      final topics = _filteredTopics;
      if (!topics.any((topic) => topic.id == _selectedTopicId)) {
        _selectedTopicId = topics.isEmpty ? '' : topics.first.id;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final topics = _filteredTopics;
    final selectedTopic =
        topics.where((topic) => topic.id == _selectedTopicId).firstOrNull;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.ustpNavy,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Help & Standards',
                      style: TextStyle(
                        fontFamily: 'Poppins',
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpGold,
                      ),
                    ),
                    SizedBox(height: 5),
                    Text(
                      'Guidance for assigned audits, evidence review, decisions, and corrective actions.',
                      style: TextStyle(
                        fontSize: 11,
                        height: 1.45,
                        color: AppColors.ustpWhite,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              IconButton(
                onPressed: widget.onHelpPressed,
                style: IconButton.styleFrom(
                  backgroundColor: AppColors.ustpWhite.withValues(alpha: 0.12),
                  foregroundColor: AppColors.ustpWhite,
                ),
                icon: const Icon(AppIcons.help),
                tooltip: 'Compliance guidance',
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            const Expanded(
              child: Text(
                'Explore topics',
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.ustpDark,
                ),
              ),
            ),
            Text(
              topics.isEmpty
                  ? 'No topics available'
                  : _query.trim().isEmpty
                      ? '5 topics to guide you'
                      : '${topics.length} matching ${topics.length == 1 ? 'topic' : 'topics'}',
              style: const TextStyle(
                fontSize: 11,
                color: AppColors.ustpMuted,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        TextField(
          key: const Key('auditor-help-search'),
          controller: _searchController,
          onChanged: _updateQuery,
          decoration: InputDecoration(
            hintText: 'Search help topics',
            prefixIcon: const Icon(Icons.search),
            suffixIcon: _query.isEmpty
                ? null
                : IconButton(
                    tooltip: 'Clear search',
                    onPressed: () {
                      _searchController.clear();
                      _updateQuery('');
                    },
                    icon: const Icon(Icons.close),
                  ),
            filled: true,
            fillColor: AppColors.ustpWhite,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
          ),
        ),
        const SizedBox(height: 10),
        if (topics.isEmpty)
          _NoHelpTopics(onClear: () {
            _searchController.clear();
            _updateQuery('');
          })
        else ...[
          DropdownButtonFormField<String>(
            initialValue: _selectedTopicId,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: 'Choose a topic',
              filled: true,
              fillColor: AppColors.ustpWhite,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            items: topics
                .map(
                  (topic) => DropdownMenuItem(
                    value: topic.id,
                    child: Text(
                      topic.label,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                )
                .toList(),
            onChanged: (topicId) {
              if (topicId != null) {
                setState(() => _selectedTopicId = topicId);
              }
            },
          ),
          if (selectedTopic != null) ...[
            const SizedBox(height: 14),
            _HelpTopicCard(topic: selectedTopic),
          ],
        ],
      ],
    );
  }
}

class _NoHelpTopics extends StatelessWidget {
  const _NoHelpTopics({required this.onClear});

  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        children: [
          const Icon(Icons.search_off, color: AppColors.ustpMuted, size: 28),
          const SizedBox(height: 8),
          const Text(
            'No topics available',
            style: TextStyle(
              fontWeight: FontWeight.w600,
              color: AppColors.ustpDark,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Try a broader search or clear it to explore all five topics.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 12, color: AppColors.ustpMuted),
          ),
          const SizedBox(height: 10),
          TextButton(
            onPressed: onClear,
            child: const Text('Clear search'),
          ),
        ],
      ),
    );
  }
}

class _HelpTopicCard extends StatelessWidget {
  const _HelpTopicCard({required this.topic});

  final _HelpTopic topic;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'AUDITOR GUIDANCE',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.bold,
              letterSpacing: 0.6,
              color: AppColors.ustpPurple,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            topic.label,
            style: const TextStyle(
              fontFamily: 'Poppins',
              fontSize: 17,
              fontWeight: FontWeight.bold,
              color: AppColors.ustpNavy,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            topic.intro,
            style: const TextStyle(
              fontSize: 13,
              height: 1.45,
              color: AppColors.ustpMuted,
            ),
          ),
          const SizedBox(height: 14),
          for (var index = 0; index < topic.steps.length; index++) ...[
            _HelpStepCard(number: index + 1, step: topic.steps[index]),
            if (index < topic.steps.length - 1) const SizedBox(height: 12),
          ],
          if (topic.note != null) ...[
            const SizedBox(height: 14),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFFDE68A)),
              ),
              child: Text(
                topic.note!,
                style: const TextStyle(
                  fontSize: 12,
                  height: 1.45,
                  color: AppColors.ustpDark,
                ),
              ),
            ),
          ],
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: () => context.go(topic.route),
              icon: const Icon(Icons.arrow_forward, size: 18),
              label: Text(topic.action),
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.ustpNavy,
                foregroundColor: AppColors.ustpWhite,
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HelpStepCard extends StatelessWidget {
  const _HelpStepCard({required this.number, required this.step});

  final int number;
  final _HelpStep step;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 26,
          height: 26,
          alignment: Alignment.center,
          decoration: const BoxDecoration(
            color: Color(0xFFEFF6FF),
            shape: BoxShape.circle,
          ),
          child: Text(
            '$number',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: AppColors.ustpNavy,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                step.title,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ustpDark,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                step.description,
                style: const TextStyle(
                  fontSize: 12,
                  height: 1.45,
                  color: AppColors.ustpMuted,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _HelpTopic {
  const _HelpTopic({
    required this.id,
    required this.label,
    required this.intro,
    required this.steps,
    required this.action,
    required this.route,
    this.note,
  });

  final String id;
  final String label;
  final String intro;
  final List<_HelpStep> steps;
  final String action;
  final String route;
  final String? note;
}

class _HelpStep {
  const _HelpStep(this.title, this.description);

  final String title;
  final String description;
}

class _StandardCard extends StatelessWidget {
  const _StandardCard({required this.standard});

  final _StandardInfo standard;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  standard.code,
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpNavy,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: const Text(
                  'NATIONAL RULE',
                  style: TextStyle(fontSize: 8, color: AppColors.ustpNavy),
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            standard.name,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: AppColors.ustpDark,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            standard.description,
            style: const TextStyle(
              fontSize: 11,
              height: 1.45,
              color: AppColors.ustpMuted,
            ),
          ),
        ],
      ),
    );
  }
}

class _StandardInfo {
  const _StandardInfo({
    required this.code,
    required this.name,
    required this.description,
  });

  final String code;
  final String name;
  final String description;
}
