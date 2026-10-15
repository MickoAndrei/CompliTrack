import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../widgets/ustp_app_bar.dart';

class HelpReferenceScreen extends StatelessWidget {
  const HelpReferenceScreen({super.key});

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
        onProfileTap: () => context.go('/auditee/profile'),
      ),
      body: ListView(
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
                        'Need guidance on compliance criteria?',
                        style: TextStyle(
                          fontFamily: 'Poppins',
                          fontSize: 13,
                          fontWeight: FontWeight.bold,
                          color: AppColors.ustpGold,
                        ),
                      ),
                      SizedBox(height: 5),
                      Text(
                        'Explore government regulations and international standards mapping Philippine national requirements below.',
                        style: TextStyle(
                          fontSize: 10,
                          height: 1.4,
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
                    backgroundColor:
                        AppColors.ustpWhite.withValues(alpha: 0.12),
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
              fontSize: 15,
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
      ),
    );
  }
}

class _StandardCard extends StatelessWidget {
  const _StandardCard({required this.standard});

  final _StandardInfo standard;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
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
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: AppColors.ustpDark,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            standard.description,
            style: const TextStyle(
              fontSize: 10,
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
