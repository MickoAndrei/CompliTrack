import 'dart:math' as math;
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../../core/constants/app_colors.dart';
import '../../../data/models/audit_log_model.dart';
import '../../../data/models/super_admin_dashboard_model.dart';
import '../../../data/repositories/audit_log_repository.dart';
import '../../../data/repositories/super_admin_repository.dart';
import '../../../data/repositories/system_health_repository.dart';
import '../admin/admin_notifications_screen.dart';
import '../../widgets/ustp_app_bar.dart';

class SuperAdminDashboardScreen extends StatefulWidget {
  const SuperAdminDashboardScreen({super.key});

  @override
  State<SuperAdminDashboardScreen> createState() =>
      _SuperAdminDashboardScreenState();
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
      appBar: UstpAppBar(
        onNotificationTap: () => AdminNotificationsScreen.showOverlay(context),
      ),
      body: FutureBuilder<SuperAdminDashboardModel>(
        future: _dashboardFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(
                child: CircularProgressIndicator(color: AppColors.ustpNavy));
          }
          if (snapshot.hasError || snapshot.data == null) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Text('Could not load dashboard metrics',
                    style: TextStyle(color: AppColors.ustpDark)),
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
                  Expanded(
                      child: _MetricCard(
                          value: '${metrics.totalUsers}',
                          label: 'Total Users',
                          icon: Icons.person_outline_rounded,
                          color: const Color(0xFF2878FF),
                          tint: const Color(0xFFEEF5FF))),
                  const SizedBox(width: 8),
                  Expanded(
                      child: _MetricCard(
                          value: '${metrics.totalDepts}',
                          label: 'Total Depts',
                          icon: Icons.grid_view_rounded,
                          color: const Color(0xFF8B3DFF),
                          tint: const Color(0xFFF5F0FF))),
                ]),
                const SizedBox(height: 8),
                Row(children: [
                  Expanded(
                      child: _MetricCard(
                          value: '${metrics.storageUsedGb} GB',
                          label: 'Storage Used',
                          icon: Icons.storage_outlined,
                          color: AppColors.ustpGold,
                          tint: const Color(0xFFFFFAE9))),
                  const SizedBox(width: 8),
                  Expanded(
                      child: _MetricCard(
                          value: '${metrics.activeAudits}',
                          label: 'Active Audits',
                          icon: Icons.assignment_turned_in_outlined,
                          color: const Color(0xFF00B984),
                          tint: const Color(0xFFE9FBF4))),
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
  const _MetricCard(
      {required this.value,
      required this.label,
      required this.icon,
      required this.color,
      required this.tint});
  final String value, label;
  final IconData icon;
  final Color color, tint;

  @override
  Widget build(BuildContext context) => Container(
        height: 60,
        padding: const EdgeInsets.symmetric(horizontal: 10),
        decoration: _cardDecoration(),
        child: Row(children: [
          Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                  color: tint, borderRadius: BorderRadius.circular(9)),
              child: Icon(icon, size: 18, color: color)),
          const SizedBox(width: 9),
          Expanded(
              child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                Text(value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontFamily: 'Poppins',
                        fontSize: 14,
                        height: 1.25,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ustpDark)),
                Text(label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 9,
                        color: AppColors.ustpMuted)),
              ])),
        ]),
      );
}

class _GrowthCard extends StatelessWidget {
  const _GrowthCard({required this.data});
  final List<UserGrowthData> data;

  @override
  Widget build(BuildContext context) {
    final hasGrowthData = data.any((point) => point.count > 0);
    final counts = data.map((point) => point.count).toList();
    final minCount =
        counts.isEmpty ? 0 : counts.reduce((a, b) => a < b ? a : b);
    final maxCount =
        counts.isEmpty ? 0 : counts.reduce((a, b) => a > b ? a : b);
    final padding = ((maxCount - minCount) * .15).ceil().clamp(1, 1000000);
    final minY = (minCount - padding).clamp(0, maxCount).toDouble();
    final maxY = (maxCount + padding).toDouble();
    return Container(
      height: 164,
      padding: const EdgeInsets.fromLTRB(12, 12, 8, 8),
      decoration: _cardDecoration(),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(
              child: Text(
                  hasGrowthData
                      ? 'User Growth Trend (${data.first.count} -> ${data.last.count})'
                      : 'User Growth Trend',
                  style: const TextStyle(
                      fontFamily: 'Poppins',
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.ustpDark))),
          Text(
              data.length < 2 ? '' : '${data.first.month} - ${data.last.month}',
              style: const TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 9,
                  color: AppColors.ustpMuted)),
        ]),
        const SizedBox(height: 8),
        if (!hasGrowthData)
          const Expanded(
            child: Center(
              child: Text(
                'No user growth data available',
                style: TextStyle(
                    fontFamily: 'Inter',
                    fontSize: 9,
                    color: AppColors.ustpMuted),
              ),
            ),
          )
        else
          Expanded(
              child: LineChart(LineChartData(
            minX: 0,
            maxX: data.length < 2 ? 1 : (data.length - 1).toDouble(),
            minY: minY,
            maxY: maxY,
            gridData: FlGridData(
              show: true,
              drawVerticalLine: true,
              horizontalInterval: (maxY - minY) / 4,
              verticalInterval: 1,
              getDrawingHorizontalLine: (_) =>
                  const FlLine(color: Color(0xFFE8EDF4), strokeWidth: 1),
              getDrawingVerticalLine: (_) =>
                  const FlLine(color: Color(0xFFF1F4F8), strokeWidth: 1),
            ),
            borderData: FlBorderData(show: false),
            titlesData: FlTitlesData(
              topTitles:
                  const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              rightTitles:
                  const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              leftTitles:
                  const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              bottomTitles: AxisTitles(
                  sideTitles: SideTitles(
                      showTitles: true,
                      interval: 1,
                      reservedSize: 18,
                      getTitlesWidget: (v, _) {
                        if (v != v.roundToDouble())
                          return const SizedBox.shrink();
                        final i = v.toInt();
                        if (i < 0 || i >= data.length)
                          return const SizedBox.shrink();
                        return Text(data[i].month,
                            style: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 8,
                                color: AppColors.ustpMuted));
                      })),
            ),
            lineBarsData: [
              LineChartBarData(
                  spots: List.generate(data.length,
                      (i) => FlSpot(i.toDouble(), data[i].count.toDouble())),
                  isCurved: false,
                  color: AppColors.ustpNavy,
                  barWidth: 1.5,
                  dotData: FlDotData(
                      show: true,
                      getDotPainter: (_, __, ___, ____) => FlDotCirclePainter(
                          radius: 2,
                          color: AppColors.ustpWhite,
                          strokeWidth: 1.2,
                          strokeColor: AppColors.ustpNavy)),
                  belowBarData: BarAreaData(show: false))
            ],
          ))),
      ]),
    );
  }
}

class _ComplianceCard extends StatefulWidget {
  const _ComplianceCard({required this.data});
  final List<DepartmentComplianceData> data;

  @override
  State<_ComplianceCard> createState() => _ComplianceCardState();
}

class _ComplianceCardState extends State<_ComplianceCard> {
  bool _mostCompliantFirst = true;

  @override
  Widget build(BuildContext context) {
    final departments = [...widget.data]..sort((a, b) => _mostCompliantFirst
        ? b.compliancePercentage.compareTo(a.compliancePercentage)
        : a.compliancePercentage.compareTo(b.compliancePercentage));

    return Container(
      height: 178,
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
      decoration: _cardDecoration(),
      child: Column(
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Compliance by Department (%)',
                  style: TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ustpDark,
                  ),
                ),
              ),
              PopupMenuButton<bool>(
                initialValue: _mostCompliantFirst,
                tooltip: 'Sort departments',
                onSelected: (value) =>
                    setState(() => _mostCompliantFirst = value),
                itemBuilder: (context) => const [
                  PopupMenuItem(value: true, child: Text('Most Compliant')),
                  PopupMenuItem(value: false, child: Text('Least Compliant')),
                ],
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF5F7FA),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFE3EAF2)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _mostCompliantFirst
                            ? 'Most Compliant'
                            : 'Least Compliant',
                        style: const TextStyle(
                          fontFamily: 'Inter',
                          fontSize: 8,
                          fontWeight: FontWeight.w600,
                          color: AppColors.ustpMuted,
                        ),
                      ),
                      const SizedBox(width: 3),
                      const Icon(Icons.expand_more,
                          size: 13, color: AppColors.ustpMuted),
                    ],
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Expanded(
            child: departments.isEmpty
                ? const Center(
                    child: Text(
                      'No department compliance data',
                      style: TextStyle(
                          fontFamily: 'Inter',
                          fontSize: 9,
                          color: AppColors.ustpMuted),
                    ),
                  )
                : _DepartmentBars(data: departments),
          ),
        ],
      ),
    );
  }
}

class _DepartmentBars extends StatelessWidget {
  const _DepartmentBars({required this.data});
  final List<DepartmentComplianceData> data;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
        builder: (context, constraints) {
          final slotWidth =
              data.length > 5 ? 56.0 : constraints.maxWidth / data.length;
          final chartWidth = slotWidth * data.length;

          return SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: SizedBox(
              width: chartWidth,
              height: constraints.maxHeight,
              child: Stack(
                children: [
                  Positioned(
                    left: 0,
                    right: 0,
                    top: 14,
                    bottom: 18,
                    child: CustomPaint(
                      painter: const _DepartmentGridPainter(),
                    ),
                  ),
                  Row(
                    children: [
                      for (final department in data)
                        SizedBox(
                          width: slotWidth,
                          child: _DepartmentBar(department: department),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      );
}

class _DepartmentBar extends StatelessWidget {
  const _DepartmentBar({required this.department});
  final DepartmentComplianceData department;

  @override
  Widget build(BuildContext context) {
    final percentage = department.compliancePercentage.clamp(0, 100).toDouble();

    return Column(
      children: [
        SizedBox(
          height: 14,
          child: Center(
            child: Text(
              '${percentage.round()}%',
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 8,
                fontWeight: FontWeight.w600,
                color: AppColors.ustpDark,
              ),
            ),
          ),
        ),
        Expanded(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final barHeight = math
                  .max(
                    2.0,
                    (constraints.maxHeight - 18) * percentage / 100,
                  )
                  .toDouble();
              return Align(
                alignment: Alignment.bottomCenter,
                child: Container(
                  width: 22,
                  height: barHeight,
                  decoration: const BoxDecoration(
                    color: AppColors.ustpGold,
                    borderRadius:
                        BorderRadius.vertical(top: Radius.circular(4)),
                  ),
                ),
              );
            },
          ),
        ),
        SizedBox(
          height: 18,
          child: Center(
            child: Text(
              department.departmentCode,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 8,
                color: AppColors.ustpMuted,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _DepartmentGridPainter extends CustomPainter {
  const _DepartmentGridPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFFE8EDF4)
      ..strokeWidth = 1;
    for (var line = 0; line <= 4; line++) {
      final y = size.height * line / 4;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _RecentLogsCard extends StatelessWidget {
  const _RecentLogsCard({required this.future});
  final Future<List<AuditLogModel>> future;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 7),
        decoration: _cardDecoration(),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Recent Audit Logs',
              style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ustpDark)),
          const SizedBox(height: 5),
          FutureBuilder<List<AuditLogModel>>(
            future: future,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting &&
                  !snapshot.hasData) {
                return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Center(
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: AppColors.ustpNavy)));
              }
              if (snapshot.hasError) {
                return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Text('Audit logs are unavailable.',
                        style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 9,
                            color: AppColors.ustpMuted)));
              }
              final logs = (snapshot.data ?? const <AuditLogModel>[]).take(4);
              if (logs.isEmpty) {
                return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Text('No recent audit activity.',
                        style: TextStyle(
                            fontFamily: 'Inter',
                            fontSize: 9,
                            color: AppColors.ustpMuted)));
              }
              return Column(children: [
                for (final log in logs)
                  Container(
                    padding: const EdgeInsets.symmetric(vertical: 5),
                    decoration: const BoxDecoration(
                        border: Border(
                            bottom: BorderSide(color: Color(0xFFE9EEF5)))),
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(children: [
                            Expanded(
                                child: Text(log.userName,
                                    style: const TextStyle(
                                        fontFamily: 'Inter',
                                        fontWeight: FontWeight.w700,
                                        fontSize: 9.5,
                                        color: AppColors.ustpDark))),
                            Text(_timeAgo(log.createdAt),
                                style: const TextStyle(
                                    fontFamily: 'Inter',
                                    fontSize: 8,
                                    color: Color(0xFF8A9BB3)))
                          ]),
                          const SizedBox(height: 1),
                          Text(log.action,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontFamily: 'Inter',
                                  fontSize: 8.5,
                                  color: AppColors.ustpMuted)),
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

class _SystemHealthCard extends StatefulWidget {
  const _SystemHealthCard();

  @override
  State<_SystemHealthCard> createState() => _SystemHealthCardState();
}

class _SystemHealthCardState extends State<_SystemHealthCard> {
  final SystemHealthRepository _repository = SystemHealthRepository();
  SystemHealthStatus? _status;
  bool _checking = true;

  @override
  void initState() {
    super.initState();
    _checkHealth();
  }

  Future<void> _checkHealth() async {
    setState(() => _checking = true);
    final status = await _repository.check();
    if (!mounted) return;
    setState(() {
      _status = status;
      _checking = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final api = _status?.apiAvailable;
    final realtime = _status?.realtimeAvailable;
    final database = _status?.databaseAvailable;
    final push = _status?.pushConfigured;

    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 9),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'System Health',
                  style: TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.ustpDark,
                  ),
                ),
              ),
              TextButton.icon(
                onPressed: _checking ? null : _checkHealth,
                icon: const Icon(Icons.refresh_rounded, size: 12),
                label: const Text('Check now'),
                style: TextButton.styleFrom(
                  minimumSize: Size.zero,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  textStyle: const TextStyle(fontFamily: 'Inter', fontSize: 8),
                ),
              ),
            ],
          ),
          const SizedBox(height: 3),
          _HealthRow(
            label: 'Laravel API',
            status: _checking
                ? 'Checking'
                : api == true
                    ? 'Operational'
                    : 'Unavailable',
            healthy: api == true,
            checking: _checking,
          ),
          _HealthRow(
            label: 'Node.js Service',
            status: _checking
                ? 'Checking'
                : realtime == true
                    ? 'Operational'
                    : 'Unavailable',
            healthy: realtime == true,
            checking: _checking,
          ),
          _HealthRow(
            label: 'MongoDB Cluster',
            status: _checking
                ? 'Checking'
                : database == true
                    ? 'Operational'
                    : 'Unavailable',
            healthy: database == true,
            checking: _checking,
          ),
          _HealthRow(
            label: 'FCM Push Service',
            status: _checking
                ? 'Checking'
                : push == true
                    ? 'Configured'
                    : push == false
                        ? 'Disabled'
                        : 'Unavailable',
            healthy: push == true,
            checking: _checking,
            disabled: push == false,
          ),
        ],
      ),
    );
  }
}

class _HealthRow extends StatelessWidget {
  const _HealthRow({
    required this.label,
    required this.status,
    required this.healthy,
    required this.checking,
    this.disabled = false,
  });

  final String label;
  final String status;
  final bool healthy;
  final bool checking;
  final bool disabled;

  @override
  Widget build(BuildContext context) {
    final color = checking
        ? AppColors.ustpMuted
        : healthy
            ? const Color(0xFF00A874)
            : disabled
                ? const Color(0xFFB7791F)
                : AppColors.error;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                fontFamily: 'Inter',
                fontSize: 9,
                color: AppColors.ustpDark,
              ),
            ),
          ),
          Icon(Icons.circle, size: 7, color: color),
          const SizedBox(width: 5),
          SizedBox(
            width: 64,
            child: Text(
              status,
              textAlign: TextAlign.left,
              style: TextStyle(
                fontFamily: 'Inter',
                fontSize: 8.5,
                color: color,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

BoxDecoration _cardDecoration() => BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(11),
      border: Border.all(color: const Color(0xFFE3EAF2)),
    );
