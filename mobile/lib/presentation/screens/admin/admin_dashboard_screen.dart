import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/compliance_check_model.dart';
import '../../../data/models/super_admin_dashboard_model.dart';
import '../../../data/repositories/compliance_repository.dart';
import '../../../data/repositories/report_repository.dart';
import '../../widgets/admin_page_header.dart';
import '../../widgets/empty_state.dart';

class AdminDashboardScreen extends StatefulWidget {
  const AdminDashboardScreen({super.key});

  @override
  State<AdminDashboardScreen> createState() => _AdminDashboardScreenState();
}

class _AdminDashboardScreenState extends State<AdminDashboardScreen> {
  final _reports = ReportRepository();
  final _checks = ComplianceRepository();
  late Future<_DashboardData> _dashboard;

  @override
  void initState() {
    super.initState();
    _dashboard = _loadDashboard();
  }

  Future<_DashboardData> _loadDashboard() async {
    final reportJson = await _reports.getReports();
    final checks = await _checks.getChecks(perPage: 100);
    final metrics = SuperAdminDashboardModel.fromJson(reportJson);
    final rawTrend = reportJson['monthlyComplianceTrend'];
    if (rawTrend is! List) {
      throw const FormatException(
        'The server returned an invalid monthly compliance trend.',
      );
    }
    final months = <String>[];
    final trend = <double>[];
    for (final entry in rawTrend) {
      if (entry is! Map ||
          entry['month'] is! String ||
          entry['compliancePercentage'] is! num) {
        throw const FormatException(
          'The server returned an invalid monthly compliance trend entry.',
        );
      }
      months.add(entry['month'] as String);
      trend.add((entry['compliancePercentage'] as num).toDouble());
    }

    return _DashboardData(
      metrics: metrics,
      complianceRate: _requiredDouble(reportJson, 'complianceRate'),
      totalAuditors: _requiredInt(reportJson, 'totalAuditors'),
      totalAuditees: _requiredInt(reportJson, 'totalAuditees'),
      recentFindings: checks
          .where((check) => check.result == 'non_compliant')
          .take(5)
          .toList(),
      months: months,
      complianceTrend: trend,
    );
  }

  int _requiredInt(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is int) return value;
    throw FormatException('Missing or invalid "$key" field.');
  }

  double _requiredDouble(Map<String, dynamic> json, String key) {
    final value = json[key];
    if (value is num) return value.toDouble();
    throw FormatException('Missing or invalid "$key" field.');
  }

  Future<void> _refresh() async {
    setState(() => _dashboard = _loadDashboard());
    await _dashboard;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      body: Column(
        children: [
          const AdminPageHeader(
            title: 'Compliance Overview',
            subtitle: 'CET USTP Claveria Central Administration Console',
          ),
          Expanded(
            child: FutureBuilder<_DashboardData>(
              future: _dashboard,
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return _DashboardError(
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
                      _KpiGrid(data: snapshot.data!),
                      const SizedBox(height: 12),
                      _DepartmentChart(data: snapshot.data!.metrics),
                      const SizedBox(height: 12),
                      _ComplianceTrend(data: snapshot.data!),
                      const SizedBox(height: 12),
                      _RecentFindings(findings: snapshot.data!.recentFindings),
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

class _DashboardData {
  const _DashboardData({
    required this.metrics,
    required this.complianceRate,
    required this.totalAuditors,
    required this.totalAuditees,
    required this.recentFindings,
    required this.months,
    required this.complianceTrend,
  });

  final SuperAdminDashboardModel metrics;
  final double complianceRate;
  final int totalAuditors;
  final int totalAuditees;
  final List<ComplianceCheckModel> recentFindings;
  final List<String> months;
  final List<double> complianceTrend;
}

class _KpiGrid extends StatelessWidget {
  const _KpiGrid({required this.data});

  final _DashboardData data;

  @override
  Widget build(BuildContext context) {
    final values = [
      _KpiData('Total Auditors', '${data.totalAuditors}', AppColors.ustpPurple),
      _KpiData(
        'General Compliance Rate',
        '${data.complianceRate.clamp(0, 100).round()}%',
        AppColors.ustpNavy,
        isRate: true,
      ),
      _KpiData('Open CARs', '${data.metrics.activeAudits}', AppColors.error),
      _KpiData('Total Auditees', '${data.totalAuditees}', AppColors.ustpMuted),
    ];
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: values.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 8,
        crossAxisSpacing: 8,
        mainAxisExtent: 76,
      ),
      itemBuilder: (context, index) => _KpiCard(data: values[index]),
    );
  }
}

class _KpiData {
  const _KpiData(this.label, this.value, this.color, {this.isRate = false});

  final String label;
  final String value;
  final Color color;
  final bool isRate;
}

class _KpiCard extends StatelessWidget {
  const _KpiCard({required this.data});

  final _KpiData data;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  data.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.ustpMuted,
                    fontFamily: 'Inter',
                    fontSize: 9,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  data.value,
                  style: TextStyle(
                    color: data.color,
                    fontFamily: 'Poppins',
                    fontSize: 17,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
          if (data.isRate)
            SizedBox(
              width: 34,
              height: 34,
              child: CircularProgressIndicator(
                value: data.value == '0%'
                    ? 0
                    : int.parse(data.value.replaceAll('%', '')).clamp(0, 100) /
                        100,
                strokeWidth: 3,
                backgroundColor: AppColors.ustpLight,
                color: AppColors.ustpGold,
              ),
            )
          else
            CircleAvatar(
              radius: 12,
              backgroundColor: data.color.withValues(alpha: 0.08),
              child: Icon(
                data.label == 'Open CARs' ? AppIcons.open : AppIcons.user,
                size: 12,
                color: data.color,
              ),
            ),
        ],
      ),
    );
  }
}

class _DepartmentChart extends StatelessWidget {
  const _DepartmentChart({required this.data});

  final SuperAdminDashboardModel data;

  @override
  Widget build(BuildContext context) {
    if (data.deptCompliance.isEmpty) {
      return const _Panel(
        child: EmptyState(
          title: 'No department data',
          subtitle: 'Department compliance will appear here.',
          icon: AppIcons.department,
        ),
      );
    }
    final departments = data.deptCompliance.take(5).toList();
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _PanelTitle('Compliance per Department'),
          const SizedBox(height: 14),
          SizedBox(
            height: 132,
            child: BarChart(
              BarChartData(
                maxY: 100,
                minY: 0,
                borderData: FlBorderData(show: false),
                gridData: const FlGridData(show: false),
                barTouchData: BarTouchData(
                  touchTooltipData: BarTouchTooltipData(
                    getTooltipItem: (group, groupIndex, rod, rodIndex) =>
                        BarTooltipItem(
                      '${rod.toY.round()}%',
                      const TextStyle(color: AppColors.ustpWhite),
                    ),
                  ),
                ),
                titlesData: FlTitlesData(
                  topTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  leftTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  rightTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      reservedSize: 22,
                      getTitlesWidget: (value, meta) {
                        final index = value.toInt();
                        if (index < 0 || index >= departments.length) {
                          return const SizedBox.shrink();
                        }
                        return Padding(
                          padding: const EdgeInsets.only(top: 5),
                          child: Text(
                            departments[index].departmentCode,
                            style: const TextStyle(
                              fontSize: 9,
                              color: AppColors.ustpDark,
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ),
                barGroups: [
                  for (var i = 0; i < departments.length; i++)
                    BarChartGroupData(
                      x: i,
                      barRods: [
                        BarChartRodData(
                          toY: departments[i]
                              .compliancePercentage
                              .clamp(0, 100)
                              .toDouble(),
                          width: 19,
                          borderRadius: BorderRadius.circular(3),
                          color: AppColors.ustpGold,
                        ),
                      ],
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ComplianceTrend extends StatelessWidget {
  const _ComplianceTrend({required this.data});

  final _DashboardData data;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _PanelTitle('Monthly Compliance Trend'),
          const SizedBox(height: 12),
          SizedBox(
            height: 120,
            child: LineChart(
              LineChartData(
                minY: 0,
                maxY: 100,
                gridData: const FlGridData(show: false),
                borderData: FlBorderData(show: false),
                titlesData: FlTitlesData(
                  topTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  leftTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  rightTitles: const AxisTitles(
                      sideTitles: SideTitles(showTitles: false)),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      reservedSize: 20,
                      getTitlesWidget: (value, meta) {
                        final index = value.toInt();
                        if (index < 0 || index >= data.months.length) {
                          return const SizedBox.shrink();
                        }
                        return Text(
                          data.months[index],
                          style: const TextStyle(
                            fontSize: 9,
                            color: AppColors.ustpMuted,
                          ),
                        );
                      },
                    ),
                  ),
                ),
                lineTouchData: const LineTouchData(enabled: false),
                lineBarsData: [
                  LineChartBarData(
                    spots: [
                      for (var i = 0; i < data.complianceTrend.length; i++)
                        FlSpot(i.toDouble(), data.complianceTrend[i]),
                    ],
                    isCurved: false,
                    color: AppColors.ustpNavy,
                    barWidth: 2,
                    dotData: const FlDotData(show: true),
                    belowBarData: BarAreaData(show: false),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RecentFindings extends StatelessWidget {
  const _RecentFindings({required this.findings});

  final List<ComplianceCheckModel> findings;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const _PanelTitle('Recent Non-Compliant Findings'),
          if (findings.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 14),
              child: Text(
                'No non-compliant findings.',
                style: TextStyle(color: AppColors.ustpMuted, fontSize: 12),
              ),
            )
          else
            ...findings.map((finding) => _FindingRow(finding: finding)),
        ],
      ),
    );
  }
}

class _FindingRow extends StatelessWidget {
  const _FindingRow({required this.finding});

  final ComplianceCheckModel finding;

  @override
  Widget build(BuildContext context) {
    final inProgress = finding.correctiveStatus == 'in_progress';
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 9),
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: Color(0xFFE8EDF4))),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  finding.departmentName ?? finding.checkCode,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.ustpDark,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              _StatusPill(
                label: inProgress ? 'In Progress' : 'Open',
                color: inProgress ? AppColors.ustpGold : AppColors.error,
                textColor: inProgress ? AppColors.ustpDark : AppColors.error,
              ),
            ],
          ),
          const SizedBox(height: 3),
          Row(
            children: [
              Expanded(
                child: Text(
                  finding.auditeeName ?? 'Auditee',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.ustpMuted,
                    fontSize: 9,
                  ),
                ),
              ),
              Text(
                DateFormat('MMM d, yyyy').format(finding.createdAt),
                style: const TextStyle(color: AppColors.ustpMuted, fontSize: 9),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({
    required this.label,
    required this.color,
    required this.textColor,
  });

  final String label;
  final Color color;
  final Color textColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
            fontSize: 8, color: textColor, fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.child, this.padding = const EdgeInsets.all(12)});

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE7ECF2)),
      ),
      child: child,
    );
  }
}

class _PanelTitle extends StatelessWidget {
  const _PanelTitle(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: const TextStyle(
        color: AppColors.ustpNavy,
        fontFamily: 'Poppins',
        fontSize: 12,
        fontWeight: FontWeight.bold,
      ),
    );
  }
}

class _DashboardError extends StatelessWidget {
  const _DashboardError({required this.error, required this.onRetry});

  final Object error;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(AppIcons.open, color: AppColors.error, size: 32),
            const SizedBox(height: 10),
            const Text(
              'Could not load dashboard data.',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 4),
            Text(
              '$error',
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.ustpMuted, fontSize: 11),
            ),
            const SizedBox(height: 12),
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
}
