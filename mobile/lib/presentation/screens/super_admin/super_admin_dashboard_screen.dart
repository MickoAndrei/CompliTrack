import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/super_admin_dashboard_model.dart';
import '../../../data/repositories/super_admin_repository.dart';
import '../../widgets/kpi_card.dart';
import '../../widgets/manage_on_web_badge.dart';
import '../../widgets/ustp_app_bar.dart';

class SuperAdminDashboardScreen extends StatefulWidget {
  const SuperAdminDashboardScreen({super.key});

  @override
  State<SuperAdminDashboardScreen> createState() => _SuperAdminDashboardScreenState();
}

class _SuperAdminDashboardScreenState extends State<SuperAdminDashboardScreen> {
  final SuperAdminRepository _repository = SuperAdminRepository();
  late Future<SuperAdminDashboardModel> _dashboardFuture;

  @override
  void initState() {
    super.initState();
    _loadMetrics();
  }

  void _loadMetrics() {
    setState(() {
      _dashboardFuture = _repository.getDashboardMetrics();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      appBar: UstpAppBar(
        onNotificationTap: () {
          // Navigate to Notifications
        },
        onProfileTap: () {
          // Navigate to Profile
        },
      ),
      body: FutureBuilder<SuperAdminDashboardModel>(
        future: _dashboardFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(
              child: CircularProgressIndicator(color: AppColors.ustpNavy),
            );
          }

          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24.0),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(AppIcons.forbidden, color: AppColors.error, size: 48),
                    const SizedBox(height: 16),
                    const Text(
                      'Failed to load dashboard metrics',
                      style: TextStyle(
                        fontFamily: 'Poppins',
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: AppColors.ustpDark,
                      ),
                    ),
                    const SizedBox(height: 12),
                    ElevatedButton.icon(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.ustpNavy,
                        foregroundColor: AppColors.ustpWhite,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      onPressed: _loadMetrics,
                      icon: const Icon(AppIcons.refresh, size: 18),
                      label: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            );
          }

          final metrics = snapshot.data ?? const SuperAdminDashboardModel(
            totalUsers: 248,
            totalDepts: 12,
            storageUsedGb: 38.4,
            activeAudits: 17,
            userGrowth: [
              UserGrowthData(month: 'Apr', count: 152),
              UserGrowthData(month: 'May', count: 170),
              UserGrowthData(month: 'Jun', count: 188),
              UserGrowthData(month: 'Jul', count: 210),
              UserGrowthData(month: 'Aug', count: 230),
              UserGrowthData(month: 'Sep', count: 248),
            ],
            deptCompliance: [
              DepartmentComplianceData(departmentCode: 'IT', compliancePercentage: 92.0),
              DepartmentComplianceData(departmentCode: 'Civil', compliancePercentage: 78.0),
              DepartmentComplianceData(departmentCode: 'EE', compliancePercentage: 85.0),
              DepartmentComplianceData(departmentCode: 'ME', compliancePercentage: 71.0),
              DepartmentComplianceData(departmentCode: 'CpE', compliancePercentage: 88.0),
            ],
          );

          return RefreshIndicator(
            color: AppColors.ustpNavy,
            onRefresh: () async => _loadMetrics(),
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16.0),
              physics: const AlwaysScrollableScrollPhysics(),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // KPI Grid (2x2)
                  Row(
                    children: [
                      Expanded(
                        child: KpiCard(
                          title: 'Total Users',
                          value: metrics.totalUsers.toString(),
                          icon: AppIcons.users,
                          iconColor: AppColors.info,
                          iconBgColor: const Color(0xFFE0F2FE),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: KpiCard(
                          title: 'Total Depts',
                          value: metrics.totalDepts.toString(),
                          icon: AppIcons.department,
                          iconColor: AppColors.ustpPurple,
                          iconBgColor: const Color(0xFFF3E8FF),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: KpiCard(
                          title: 'Storage Used',
                          value: '${metrics.storageUsedGb} GB',
                          icon: AppIcons.storage,
                          iconColor: AppColors.ustpGold,
                          iconBgColor: const Color(0xFFFEF9C3),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: KpiCard(
                          title: 'Active Audits',
                          value: metrics.activeAudits.toString(),
                          icon: AppIcons.compliance,
                          iconColor: AppColors.success,
                          iconBgColor: const Color(0xFFDCFCE7),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // User Growth Trend Chart
                  _buildUserGrowthChartCard(metrics.userGrowth),

                  const SizedBox(height: 20),

                  // Compliance by Department Chart
                  _buildComplianceChartCard(metrics.deptCompliance),

                  const SizedBox(height: 24),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildUserGrowthChartCard(List<UserGrowthData> userGrowth) {
    final startCount = userGrowth.isNotEmpty ? userGrowth.first.count : 152;
    final endCount = userGrowth.isNotEmpty ? userGrowth.last.count : 248;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  'User Growth Trend ($startCount → $endCount)',
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                    color: AppColors.ustpDark,
                  ),
                ),
              ),
              const Text(
                'Apr - Sep',
                style: TextStyle(
                  fontFamily: 'Inter',
                  fontSize: 12,
                  color: AppColors.ustpMuted,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          SizedBox(
            height: 180,
            child: LineChart(
              LineChartData(
                gridData: FlGridData(
                  show: true,
                  drawVerticalLine: false,
                  getDrawingHorizontalLine: (value) => FlLine(
                    color: AppColors.ustpLight,
                    strokeWidth: 1,
                  ),
                ),
                titlesData: FlTitlesData(
                  rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      getTitlesWidget: (value, meta) {
                        final index = value.toInt();
                        if (index >= 0 && index < userGrowth.length) {
                          return Padding(
                            padding: const EdgeInsets.only(top: 8.0),
                            child: Text(
                              userGrowth[index].month,
                              style: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 11,
                                color: AppColors.ustpMuted,
                              ),
                            ),
                          );
                        }
                        return const SizedBox.shrink();
                      },
                      reservedSize: 28,
                    ),
                  ),
                ),
                borderData: FlBorderData(show: false),
                lineBarsData: [
                  LineChartBarData(
                    spots: List.generate(
                      userGrowth.length,
                      (i) => FlSpot(i.toDouble(), userGrowth[i].count.toDouble()),
                    ),
                    isCurved: false,
                    color: AppColors.ustpNavy,
                    barWidth: 2.5,
                    isStrokeCapRound: true,
                    dotData: FlDotData(
                      show: true,
                      getDotPainter: (spot, percent, barData, index) {
                        return FlDotCirclePainter(
                          radius: 4,
                          color: AppColors.ustpGold,
                          strokeWidth: 2,
                          strokeColor: AppColors.ustpNavy,
                        );
                      },
                    ),
                    belowBarData: BarAreaData(
                      show: true,
                      color: AppColors.ustpNavy.withValues(alpha: 0.04),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildComplianceChartCard(List<DepartmentComplianceData> deptCompliance) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Compliance by Department (%)',
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                  color: AppColors.ustpDark,
                ),
              ),
              ManageOnWebBadge(label: 'Read-only'),
            ],
          ),
          const SizedBox(height: 24),
          SizedBox(
            height: 200,
            child: BarChart(
              BarChartData(
                alignment: BarChartAlignment.spaceAround,
                maxY: 100,
                barTouchData: BarTouchData(enabled: true),
                titlesData: FlTitlesData(
                  show: true,
                  rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      getTitlesWidget: (value, meta) {
                        final index = value.toInt();
                        if (index >= 0 && index < deptCompliance.length) {
                          return Padding(
                            padding: const EdgeInsets.only(top: 8.0),
                            child: Text(
                              deptCompliance[index].departmentCode,
                              style: const TextStyle(
                                fontFamily: 'Inter',
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: AppColors.ustpMuted,
                              ),
                            ),
                          );
                        }
                        return const SizedBox.shrink();
                      },
                      reservedSize: 28,
                    ),
                  ),
                ),
                gridData: FlGridData(show: false),
                borderData: FlBorderData(show: false),
                barGroups: List.generate(
                  deptCompliance.length,
                  (i) => BarChartGroupData(
                    x: i,
                    showingTooltipIndicators: [0],
                    barRods: [
                      BarChartRodData(
                        toY: deptCompliance[i].compliancePercentage,
                        color: AppColors.ustpGold,
                        width: 28,
                        borderRadius: const BorderRadius.only(
                          topLeft: Radius.circular(6),
                          topRight: Radius.circular(6),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
