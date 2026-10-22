import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/audit_log_model.dart';
import '../../../data/models/super_admin_dashboard_model.dart';
import '../../../data/repositories/audit_log_repository.dart';
import '../../../data/repositories/super_admin_repository.dart';
import '../../widgets/manage_on_web_badge.dart';
import '../../widgets/ustp_app_bar.dart';

class SuperAdminDashboardScreen extends StatefulWidget {
  const SuperAdminDashboardScreen({super.key});

  @override
  State<SuperAdminDashboardScreen> createState() => _SuperAdminDashboardScreenState();
}

class _SuperAdminDashboardScreenState extends State<SuperAdminDashboardScreen> {
  final SuperAdminRepository _repository = SuperAdminRepository();
  final AuditLogRepository _auditLogRepository = AuditLogRepository();
  late Future<SuperAdminDashboardModel> _dashboardFuture;
  late Future<List<AuditLogModel>> _auditLogsFuture;

  @override
  void initState() {
    super.initState();
    _dashboardFuture = _repository.getDashboardMetrics();
    _auditLogsFuture = _auditLogRepository.getAuditLogs();
  }

  Future<void> _reload() async {
    final future = _repository.getDashboardMetrics();
    final logsFuture = _auditLogRepository.getAuditLogs();
    setState(() {
      _dashboardFuture = future;
      _auditLogsFuture = logsFuture;
    });
    await future;
    await logsFuture.catchError((_) => <AuditLogModel>[]);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: const UstpAppBar(),
      body: FutureBuilder<SuperAdminDashboardModel>(
        future: _dashboardFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: AppColors.ustpNavy));
          }
          if (snapshot.hasError || snapshot.data == null) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Text('Could not load dashboard metrics', style: TextStyle(color: AppColors.ustpDark)),
                const SizedBox(height: 12),
                FilledButton(onPressed: _reload, child: const Text('Retry')),
              ]),
            );
          }
          final metrics = snapshot.data!;
          return RefreshIndicator(
            color: AppColors.ustpNavy,
            onRefresh: _reload,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 24),
              children: [
                Row(children: [
                  Expanded(child: _MetricCard(value: '${metrics.totalUsers}', label: 'Total Users', icon: AppIcons.users, color: const Color(0xFF2878FF), tint: const Color(0xFFEEF5FF))),
                  const SizedBox(width: 8),
                  Expanded(child: _MetricCard(value: '${metrics.totalDepts}', label: 'Total Depts', icon: AppIcons.department, color: const Color(0xFF8B3DFF), tint: const Color(0xFFF5F0FF))),
                ]),
                const SizedBox(height: 8),
                Row(children: [
                  Expanded(child: _MetricCard(value: '${metrics.storageUsedGb} GB', label: 'Storage Used', icon: AppIcons.storage, color: AppColors.ustpGold, tint: const Color(0xFFFFFAE9))),
                  const SizedBox(width: 8),
                  Expanded(child: _MetricCard(value: '${metrics.activeAudits}', label: 'Active Audits', icon: AppIcons.compliance, color: const Color(0xFF00B984), tint: const Color(0xFFE9FBF4))),
                ]),
                const SizedBox(height: 18),
                _GrowthCard(data: metrics.userGrowth),
                const SizedBox(height: 14),
                _ComplianceCard(data: metrics.deptCompliance),
                const SizedBox(height: 14),
                _RecentLogsCard(future: _auditLogsFuture),
                const SizedBox(height: 14),
                const _SystemHealthCard(),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.value, required this.label, required this.icon, required this.color, required this.tint});
  final String value, label;
  final IconData icon;
  final Color color, tint;

  @override
  Widget build(BuildContext context) => Container(
        height: 60,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        decoration: _cardDecoration(),
        child: Row(children: [
          Container(width: 32, height: 32, decoration: BoxDecoration(color: tint, borderRadius: BorderRadius.circular(9)), child: Icon(icon, size: 18, color: color)),
          const SizedBox(width: 9),
          Expanded(child: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontFamily: 'Poppins', fontSize: 14, height: 1.25, fontWeight: FontWeight.w700, color: AppColors.ustpDark)),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontFamily: 'Inter', fontSize: 9, color: AppColors.ustpMuted)),
          ])),
        ]),
      );
}

class _GrowthCard extends StatelessWidget {
  const _GrowthCard({required this.data});
  final List<UserGrowthData> data;

  @override
  Widget build(BuildContext context) {
    final start = data.isEmpty ? 0 : data.first.count;
    final end = data.isEmpty ? 0 : data.last.count;
    return Container(
      height: 164,
      padding: const EdgeInsets.fromLTRB(12, 12, 8, 8),
      decoration: _cardDecoration(),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('User Growth Trend ($start → $end)', style: const TextStyle(fontFamily: 'Poppins', fontSize: 10.5, fontWeight: FontWeight.w700, color: AppColors.ustpDark))),
          Text(data.length < 2 ? '' : '${data.first.month} - ${data.last.month}', style: const TextStyle(fontFamily: 'Inter', fontSize: 9, color: AppColors.ustpMuted)),
        ]),
        const SizedBox(height: 8),
        Expanded(child: LineChart(LineChartData(
          minX: 0, maxX: data.length < 2 ? 1 : (data.length - 1).toDouble(),
          minY: data.isEmpty ? 0 : (data.map((e) => e.count).reduce((a, b) => a < b ? a : b) - 12).toDouble(),
          maxY: data.isEmpty ? 100 : (data.map((e) => e.count).reduce((a, b) => a > b ? a : b) + 12).toDouble(),
          gridData: FlGridData(show: true, drawVerticalLine: false, getDrawingHorizontalLine: (_) => const FlLine(color: Color(0xFFE8EDF4), strokeWidth: 1)),
          borderData: FlBorderData(show: false),
          titlesData: FlTitlesData(
            topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)), rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)), leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, interval: 1, reservedSize: 18, getTitlesWidget: (v, _) {
              if (v != v.roundToDouble()) return const SizedBox.shrink();
              final i = v.toInt();
              if (i < 0 || i >= data.length) return const SizedBox.shrink();
              return Text(data[i].month, style: const TextStyle(fontFamily: 'Inter', fontSize: 8, color: AppColors.ustpMuted));
            })),
          ),
          lineBarsData: [LineChartBarData(spots: List.generate(data.length, (i) => FlSpot(i.toDouble(), data[i].count.toDouble())), isCurved: false, color: AppColors.ustpNavy, barWidth: 1.5, dotData: FlDotData(show: true, getDotPainter: (_, __, ___, ____) => FlDotCirclePainter(radius: 2, color: AppColors.ustpWhite, strokeWidth: 1.2, strokeColor: AppColors.ustpNavy)), belowBarData: BarAreaData(show: false))],
        ))),
      ]),
    );
  }
}

class _ComplianceCard extends StatelessWidget {
  const _ComplianceCard({required this.data});
  final List<DepartmentComplianceData> data;

  @override
  Widget build(BuildContext context) => Container(
        height: 178,
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
        decoration: _cardDecoration(),
        child: Column(children: [
          Row(children: [const Expanded(child: Text('Compliance by Department (%)', style: TextStyle(fontFamily: 'Poppins', fontSize: 10.5, fontWeight: FontWeight.w700, color: AppColors.ustpDark))), const ManageOnWebBadge(label: 'Read-only')]),
          const SizedBox(height: 9),
          Expanded(child: BarChart(BarChartData(
            maxY: 110, minY: 0, alignment: BarChartAlignment.spaceAround,
            gridData: FlGridData(show: false), borderData: FlBorderData(show: false),
            titlesData: FlTitlesData(
              show: true,
              topTitles: const AxisTitles(
                sideTitles: SideTitles(showTitles: false),
              ),
              leftTitles: const AxisTitles(
                sideTitles: SideTitles(showTitles: false),
              ),
              rightTitles: const AxisTitles(
                sideTitles: SideTitles(showTitles: false),
              ),
              bottomTitles: AxisTitles(
                sideTitles: SideTitles(
                  showTitles: true,
                  reservedSize: 18,
                  getTitlesWidget: (value, _) {
                    final index = value.toInt();
                    if (index < 0 || index >= data.length) {
                      return const SizedBox.shrink();
                    }
                    return Text(
                      data[index].departmentCode,
                      style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 8,
                        color: AppColors.ustpMuted,
                      ),
                    );
                  },
                ),
              ),
            ),
            barGroups: List.generate(
              data.length,
              (index) => BarChartGroupData(
                x: index,
                barsSpace: 0,
                barRods: [
                  BarChartRodData(
                    toY: data[index].compliancePercentage,
                    width: 22,
                    color: AppColors.ustpGold,
                    borderRadius: const BorderRadius.vertical(
                      top: Radius.circular(4),
                    ),
                  ),
                ],
              ),
            ),
            barTouchData: BarTouchData(enabled: false),
          ))),
        ]),
      );
}

class _RecentLogsCard extends StatelessWidget {
  const _RecentLogsCard({required this.future});
  final Future<List<AuditLogModel>> future;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 7),
        decoration: _cardDecoration(),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Recent Audit Logs', style: TextStyle(fontFamily: 'Poppins', fontSize: 10.5, fontWeight: FontWeight.w700, color: AppColors.ustpDark)),
          const SizedBox(height: 5),
          FutureBuilder<List<AuditLogModel>>(
            future: future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting && !snapshot.hasData) {
                return const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Center(child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.ustpNavy)));
              }
              if (snapshot.hasError) {
                return const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('Audit logs are unavailable.', style: TextStyle(fontFamily: 'Inter', fontSize: 9, color: AppColors.ustpMuted)));
              }
              final logs = (snapshot.data ?? const <AuditLogModel>[]).take(4);
              if (logs.isEmpty) {
                return const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Text('No recent audit activity.', style: TextStyle(fontFamily: 'Inter', fontSize: 9, color: AppColors.ustpMuted)));
              }
              return Column(children: [
                for (final log in logs) Container(
                  padding: const EdgeInsets.symmetric(vertical: 5),
                  decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFE9EEF5)))),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [Expanded(child: Text(log.userName, style: const TextStyle(fontFamily: 'Inter', fontWeight: FontWeight.w700, fontSize: 9.5, color: AppColors.ustpDark))), Text(_timeAgo(log.createdAt), style: const TextStyle(fontFamily: 'Inter', fontSize: 8, color: Color(0xFF8A9BB3)))]),
                    const SizedBox(height: 1), Text(log.action, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontFamily: 'Inter', fontSize: 8.5, color: AppColors.ustpMuted)),
                  ]),
                ),
              ]);
            },
          ),
        ]),
      );

  String _timeAgo(DateTime date) {
    final minutes = DateTime.now().difference(date.toLocal()).inMinutes;
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return '$minutes min${minutes == 1 ? '' : 's'} ago';
    final hours = minutes ~/ 60;
    if (hours < 24) return '$hours hour${hours == 1 ? '' : 's'} ago';
    if (hours < 48) return 'Yesterday';
    return DateFormat('MMM d').format(date.toLocal());
  }
}

class _SystemHealthCard extends StatelessWidget {
  const _SystemHealthCard();
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 7),
        decoration: _cardDecoration(),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('System Health', style: TextStyle(fontFamily: 'Poppins', fontSize: 10.5, fontWeight: FontWeight.w700, color: AppColors.ustpDark)),
          const SizedBox(height: 4),
          for (final service in ['Node.js Service', 'MongoDB Cluster', 'FCM Push Service']) Padding(
            padding: const EdgeInsets.symmetric(vertical: 5),
            child: Row(children: [Expanded(child: Text(service, style: const TextStyle(fontFamily: 'Inter', fontSize: 9, color: AppColors.ustpDark))), const Icon(Icons.circle, size: 7, color: Color(0xFF00BE87)), const SizedBox(width: 5), const Text('Operational', style: TextStyle(fontFamily: 'Inter', fontSize: 8.5, color: Color(0xFF00A874), fontWeight: FontWeight.w600))]),
          ),
        ]),
      );
}

BoxDecoration _cardDecoration() => BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(11),
      border: Border.all(color: const Color(0xFFE3EAF2)),
    );
