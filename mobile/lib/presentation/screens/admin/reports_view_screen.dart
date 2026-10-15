import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/repositories/report_repository.dart';
import '../../widgets/admin_page_header.dart';

class ReportsViewScreen extends StatefulWidget {
  const ReportsViewScreen({super.key});

  @override
  State<ReportsViewScreen> createState() => _ReportsViewScreenState();
}

class _ReportsViewScreenState extends State<ReportsViewScreen> {
  final _repository = ReportRepository();
  late DateTime _startDate;
  late DateTime _endDate;
  late Future<Map<String, dynamic>> _reports;
  bool _isExporting = false;

  @override
  void initState() {
    super.initState();
    _endDate = DateTime.now();
    _startDate = DateTime(_endDate.year, _endDate.month - 1, _endDate.day);
    _reports = _loadReports();
  }

  Future<Map<String, dynamic>> _loadReports() async {
    final report = await _repository.getReports(
      startDate: _startDate,
      endDate: _endDate,
    );
    _officeRows(report);
    _number(report['complianceRate']);
    _number(report['openCars']);
    _number(report['openOverSevenDays']);
    return report;
  }

  Future<void> _refresh() async {
    setState(() => _reports = _loadReports());
    await _reports;
  }

  Future<void> _chooseDate({required bool isStart}) async {
    final selected = await showDatePicker(
      context: context,
      initialDate: isStart ? _startDate : _endDate,
      firstDate: DateTime(2000),
      lastDate: DateTime.now(),
    );
    if (selected == null || !mounted) return;

    final start = isStart ? selected : _startDate;
    final end = isStart
        ? _endDate
        : DateTime(
            selected.year,
            selected.month,
            selected.day,
            23,
            59,
            59,
            999,
          );
    if (end.isBefore(start)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('The end date must be on or after the start date.')),
      );
      return;
    }
    setState(() {
      _startDate = start;
      _endDate = end;
      _reports = _loadReports();
    });
  }

  Future<void> _exportPdf(Map<String, dynamic> report) async {
    setState(() => _isExporting = true);
    try {
      final offices = _officeRows(report);
      final document = pw.Document();
      final dateFormat = DateFormat('yyyy-MM-dd');
      final tableRows = <List<String>>[
        ['Office', 'Compliance rate', 'Open CAR aging'],
        ...offices.map(
          (office) => [
            office.name,
            '${office.complianceRate.toStringAsFixed(0)}%',
            'Aging: ${office.agingDays} days',
          ],
        ),
      ];
      document.addPage(
        pw.MultiPage(
          pageFormat: PdfPageFormat.a4,
          margin: const pw.EdgeInsets.all(36),
          build: (context) => [
            pw.Text(
              'USTP Claveria CET · Compliance Report',
              style: pw.TextStyle(
                fontSize: 20,
                fontWeight: pw.FontWeight.bold,
                color: PdfColor.fromHex('#00205B'),
              ),
            ),
            pw.SizedBox(height: 8),
            pw.Text(
              'Report period: ${dateFormat.format(_startDate)} to ${dateFormat.format(_endDate)}',
            ),
            pw.SizedBox(height: 20),
            pw.Text(
              'Overall compliance rate: ${_number(report['complianceRate']).toStringAsFixed(1)}%',
            ),
            pw.Text(
                'Open corrective actions: ${_number(report['openCars']).round()}'),
            pw.Text(
              'Open over 7 days: ${_number(report['openOverSevenDays']).round()}',
            ),
            pw.SizedBox(height: 18),
            pw.TableHelper.fromTextArray(
              data: tableRows,
              headerStyle: pw.TextStyle(
                color: PdfColors.white,
                fontWeight: pw.FontWeight.bold,
              ),
              headerDecoration: const pw.BoxDecoration(
                color: PdfColor.fromInt(0xFF00205B),
              ),
              cellStyle: const pw.TextStyle(fontSize: 10),
              cellPadding: const pw.EdgeInsets.all(8),
              border: pw.TableBorder.all(color: PdfColors.grey300),
            ),
            pw.SizedBox(height: 20),
            pw.Text(
              'Generated ${DateFormat('yyyy-MM-dd HH:mm').format(DateTime.now())}',
              style: const pw.TextStyle(
                fontSize: 9,
                color: PdfColor.fromInt(0xFF64748B),
              ),
            ),
          ],
        ),
      );
      final bytes = Uint8List.fromList(await document.save());
      final savedPath = await FilePicker.platform.saveFile(
        fileName:
            'complitrack-report-${dateFormat.format(_startDate)}-${dateFormat.format(_endDate)}.pdf',
        bytes: bytes,
      );
      if (savedPath != null && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Compliance report PDF exported.')),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not export report PDF: $error')),
        );
      }
    } finally {
      if (mounted) setState(() => _isExporting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      body: Column(
        children: [
          const AdminPageHeader(
            title: 'Reports',
            subtitle: 'CET Admin · Generate compliance PDFs',
          ),
          Expanded(
            child: FutureBuilder<Map<String, dynamic>>(
              future: _reports,
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return _ReportError(
                    error: snapshot.error!,
                    onRetry: _refresh,
                  );
                }
                if (!snapshot.hasData) {
                  return const Center(
                    child: CircularProgressIndicator(
                      color: AppColors.ustpNavy,
                    ),
                  );
                }
                return RefreshIndicator(
                  color: AppColors.ustpNavy,
                  onRefresh: _refresh,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 20),
                    children: [
                      _ReportPanel(
                        startDate: _startDate,
                        endDate: _endDate,
                        report: snapshot.data!,
                        isExporting: _isExporting,
                        onChooseStart: () => _chooseDate(isStart: true),
                        onChooseEnd: () => _chooseDate(isStart: false),
                        onExport: () => _exportPdf(snapshot.data!),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _OfficeReport {
  const _OfficeReport({
    required this.name,
    required this.complianceRate,
    required this.agingDays,
  });

  final String name;
  final double complianceRate;
  final int agingDays;
}

List<_OfficeReport> _officeRows(Map<String, dynamic> report) {
  final raw = report['deptCompliance'];
  if (raw is! List) {
    throw const FormatException(
        'The report contains no office compliance data.');
  }
  return raw.map((entry) {
    if (entry is! Map ||
        entry['departmentName'] is! String ||
        entry['compliancePercentage'] is! num ||
        entry['openCarAgingDays'] is! num) {
      throw const FormatException(
          'The report contains an invalid office entry.');
    }
    return _OfficeReport(
      name: entry['departmentName'] as String,
      complianceRate: (entry['compliancePercentage'] as num).toDouble(),
      agingDays: (entry['openCarAgingDays'] as num).round(),
    );
  }).toList();
}

double _number(dynamic value) {
  if (value is num) return value.toDouble();
  throw const FormatException('The report contains an invalid numeric value.');
}

class _ReportPanel extends StatelessWidget {
  const _ReportPanel({
    required this.startDate,
    required this.endDate,
    required this.report,
    required this.isExporting,
    required this.onChooseStart,
    required this.onChooseEnd,
    required this.onExport,
  });

  final DateTime startDate;
  final DateTime endDate;
  final Map<String, dynamic> report;
  final bool isExporting;
  final VoidCallback onChooseStart;
  final VoidCallback onChooseEnd;
  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    final offices = _officeRows(report);
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        border: Border.all(color: const Color(0xFFE5EAF1)),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Generate Compliance Report',
            style: TextStyle(
              color: AppColors.ustpNavy,
              fontFamily: 'Poppins',
              fontSize: 13,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 3),
          const Text(
            'Compliance Rate per Office · Open CAR Aging',
            style: TextStyle(color: AppColors.ustpMuted, fontSize: 9),
          ),
          const SizedBox(height: 10),
          const _DateLabel('From'),
          const SizedBox(height: 4),
          _DateButton(date: startDate, onPressed: onChooseStart),
          const SizedBox(height: 7),
          const _DateLabel('To'),
          const SizedBox(height: 4),
          _DateButton(date: endDate, onPressed: onChooseEnd),
          const SizedBox(height: 12),
          Container(
            decoration: BoxDecoration(
              border: Border.all(color: const Color(0xFFE5EAF1)),
              borderRadius: BorderRadius.circular(6),
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 9,
                  ),
                  color: AppColors.ustpNavy,
                  child: const Text(
                    'USTP Claveria CET · Report Preview',
                    style: TextStyle(
                      color: AppColors.ustpWhite,
                      fontSize: 9,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                for (final office in offices) _OfficePreviewRow(office: office),
                _SummaryPreviewRow(report: report),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            height: 38,
            child: ElevatedButton(
              onPressed: isExporting ? null : onExport,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.ustpGold,
                foregroundColor: AppColors.ustpDark,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(7),
                ),
              ),
              child: isExporting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: AppColors.ustpDark,
                      ),
                    )
                  : const Text(
                      'Export PDF',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DateLabel extends StatelessWidget {
  const _DateLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) => Text(
        label,
        style: const TextStyle(
          color: AppColors.ustpDark,
          fontSize: 9,
          fontWeight: FontWeight.w600,
        ),
      );
}

class _DateButton extends StatelessWidget {
  const _DateButton({required this.date, required this.onPressed});

  final DateTime date;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => SizedBox(
        width: double.infinity,
        height: 34,
        child: OutlinedButton(
          onPressed: onPressed,
          style: OutlinedButton.styleFrom(
            alignment: Alignment.centerLeft,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            side: const BorderSide(color: Color(0xFFE3EAF2)),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(7),
            ),
          ),
          child: Row(
            children: [
              const Icon(AppIcons.archive,
                  size: 13, color: AppColors.ustpMuted),
              const SizedBox(width: 8),
              Text(
                DateFormat('yyyy-MM-dd').format(date),
                style: const TextStyle(
                  color: AppColors.ustpDark,
                  fontSize: 10,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      );
}

class _OfficePreviewRow extends StatelessWidget {
  const _OfficePreviewRow({required this.office});

  final _OfficeReport office;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: Color(0xFFE8EDF4))),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              office.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: AppColors.ustpDark,
                fontSize: 9,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 3),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Rate: ${office.complianceRate.toStringAsFixed(0)}%',
                  style: const TextStyle(
                    color: AppColors.ustpMuted,
                    fontSize: 8,
                  ),
                ),
                Text(
                  'Aging: ${office.agingDays} days',
                  style: const TextStyle(
                    color: AppColors.ustpMuted,
                    fontSize: 8,
                  ),
                ),
              ],
            ),
          ],
        ),
      );
}

class _SummaryPreviewRow extends StatelessWidget {
  const _SummaryPreviewRow({required this.report});

  final Map<String, dynamic> report;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'Overall rate: ${_number(report['complianceRate']).toStringAsFixed(0)}%',
              style: const TextStyle(
                color: AppColors.ustpNavy,
                fontSize: 8,
                fontWeight: FontWeight.bold,
              ),
            ),
            Text(
              'Open CARs: ${_number(report['openCars']).round()}',
              style: const TextStyle(
                color: AppColors.error,
                fontSize: 8,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      );
}

class _ReportError extends StatelessWidget {
  const _ReportError({required this.error, required this.onRetry});

  final Object error;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Could not load reports.\n$error',
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.ustpMuted),
              ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(AppIcons.refresh, size: 16),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      );
}
