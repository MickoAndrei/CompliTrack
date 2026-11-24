import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_icons.dart';
import '../../../data/models/user_model.dart';
import '../../../data/repositories/user_repository.dart';
import '../../widgets/admin_page_header.dart';
import '../../widgets/empty_state.dart';

class AdminUserManagementScreen extends StatefulWidget {
  const AdminUserManagementScreen({super.key, this.repository});

  final UserRepository? repository;

  @override
  State<AdminUserManagementScreen> createState() =>
      _AdminUserManagementScreenState();
}

class _AdminUserManagementScreenState extends State<AdminUserManagementScreen> {
  late final _repository = widget.repository ?? UserRepository();
  final _searchController = TextEditingController();
  late Future<List<UserModel>> _users;
  int? _roleFilter;

  @override
  void initState() {
    super.initState();
    _users = _loadUsers();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<List<UserModel>> _loadUsers() => _repository.getUsers(perPage: 100);

  Future<void> _refresh() async {
    setState(() => _users = _loadUsers());
    await _users;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.ustpLight,
      body: Column(
        children: [
          const AdminPageHeader(
            title: 'Users',
            subtitle: 'View auditors and auditees assigned to CET departments.',
          ),
          Container(
            margin: const EdgeInsets.fromLTRB(12, 10, 12, 0),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFFEAF3FF),
              border: const Border(
                left: BorderSide(color: AppColors.info, width: 3),
              ),
              borderRadius: BorderRadius.circular(8),
            ),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(AppIcons.open, color: AppColors.info, size: 15),
                SizedBox(width: 7),
                Expanded(
                  child: Text(
                    'View only. User accounts are managed by the Super Admin.',
                    style: TextStyle(
                      color: AppColors.info,
                      fontSize: 10,
                      height: 1.3,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
            child: Column(
              children: [
                TextField(
                  controller: _searchController,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    hintText: 'Search user or email...',
                    prefixIcon: const Icon(AppIcons.search, size: 17),
                    filled: true,
                    fillColor: AppColors.ustpWhite,
                    contentPadding: const EdgeInsets.symmetric(vertical: 10),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(9),
                      borderSide: const BorderSide(color: Color(0xFFE4EAF1)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(9),
                      borderSide: const BorderSide(color: Color(0xFFE4EAF1)),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<int?>(
                        // Keep `value` for compatibility with Flutter 3.24.
                        // ignore: deprecated_member_use
                        value: _roleFilter,
                        decoration: InputDecoration(
                          filled: true,
                          fillColor: AppColors.ustpWhite,
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 9,
                          ),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(9),
                            borderSide:
                                const BorderSide(color: Color(0xFFE4EAF1)),
                          ),
                        ),
                        items: const [
                          DropdownMenuItem<int?>(
                            value: null,
                            child: Text('Filter by Role'),
                          ),
                          DropdownMenuItem<int?>(
                            value: 2,
                            child: Text('Auditor'),
                          ),
                          DropdownMenuItem<int?>(
                            value: 3,
                            child: Text('Auditee'),
                          ),
                        ],
                        onChanged: (value) =>
                            setState(() => _roleFilter = value),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Expanded(
            child: FutureBuilder<List<UserModel>>(
              future: _users,
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return _LoadError(error: snapshot.error!, onRetry: _refresh);
                }
                if (!snapshot.hasData) {
                  return const Center(
                    child: CircularProgressIndicator(
                      color: AppColors.ustpNavy,
                    ),
                  );
                }
                final search = _searchController.text.trim().toLowerCase();
                final users = snapshot.data!.where((user) {
                  final roleMatches =
                      _roleFilter == null || user.roleId == _roleFilter;
                  final searchMatches = search.isEmpty ||
                      user.fullName.toLowerCase().contains(search) ||
                      user.email.toLowerCase().contains(search) ||
                      user.userCode.toLowerCase().contains(search);
                  return roleMatches && searchMatches;
                }).toList();
                if (users.isEmpty) {
                  return RefreshIndicator(
                    onRefresh: _refresh,
                    child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: const [
                        SizedBox(height: 80),
                        EmptyState(
                          title: 'No users found',
                          subtitle: 'Try changing your search or role filter.',
                        ),
                      ],
                    ),
                  );
                }
                return RefreshIndicator(
                  color: AppColors.ustpNavy,
                  onRefresh: _refresh,
                  child: ListView.builder(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
                    itemCount: users.length,
                    itemBuilder: (context, index) => _UserCard(
                      user: users[index],
                    ),
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

class _UserCard extends StatefulWidget {
  const _UserCard({
    required this.user,
  });

  final UserModel user;

  @override
  State<_UserCard> createState() => _UserCardState();
}

class _UserCardState extends State<_UserCard> {
  bool _isExpanded = false;

  @override
  Widget build(BuildContext context) {
    final active = widget.user.isActive;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 250),
      curve: Curves.easeInOut,
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: AppColors.ustpWhite,
        border: Border.all(
          color: _isExpanded
              ? AppColors.ustpNavy.withValues(alpha: 0.3)
              : const Color(0xFFE5EAF1),
        ),
        borderRadius: BorderRadius.circular(11),
        boxShadow: _isExpanded
            ? [
                BoxShadow(
                  color: AppColors.ustpNavy.withValues(alpha: 0.06),
                  blurRadius: 8,
                  offset: const Offset(0, 3),
                ),
              ]
            : [],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(11),
        child: InkWell(
          onTap: () => setState(() => _isExpanded = !_isExpanded),
          borderRadius: BorderRadius.circular(11),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(11, 10, 11, 10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        widget.user.userCode,
                        style: const TextStyle(
                          color: AppColors.ustpNavy,
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    _Badge(
                      label: active ? 'Active' : 'Inactive',
                      color: active ? AppColors.success : AppColors.ustpMuted,
                    ),
                    const SizedBox(width: 6),
                    Icon(
                      _isExpanded
                          ? Icons.keyboard_arrow_up_rounded
                          : Icons.keyboard_arrow_down_rounded,
                      size: 18,
                      color: AppColors.ustpMuted,
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  widget.user.fullName,
                  style: const TextStyle(
                    color: AppColors.ustpDark,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  widget.user.email,
                  style: const TextStyle(
                      color: AppColors.ustpMuted, fontSize: 10),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    _RoleBadge(
                        label: _roleName(widget.user.roleId),
                        roleId: widget.user.roleId),
                    const SizedBox(width: 7),
                    const Icon(Icons.circle,
                        size: 3, color: AppColors.ustpMuted),
                    const SizedBox(width: 7),
                    Expanded(
                      child: Text(
                        widget.user.departmentName ??
                            (widget.user.officeName.isNotEmpty
                                ? widget.user.officeName
                                : 'Department assigned'),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: AppColors.ustpMuted, fontSize: 10),
                      ),
                    ),
                  ],
                ),
                AnimatedCrossFade(
                  firstChild: const SizedBox.shrink(),
                  secondChild: Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Divider(
                            height: 1,
                            thickness: 1,
                            color: Color(0xFFF1F5F9)),
                        const SizedBox(height: 8),
                        _DetailRow(
                            label: 'Role Level',
                            value:
                                '${widget.user.roleId} - ${_roleName(widget.user.roleId)}'),
                        const SizedBox(height: 4),
                        _DetailRow(
                            label: 'Office / Unit',
                            value: widget.user.officeName.isNotEmpty
                                ? widget.user.officeName
                                : 'Not specified'),
                        const SizedBox(height: 4),
                        _DetailRow(
                            label: 'Account Status',
                            value: active
                                ? 'Active & Authorized'
                                : 'Inactive'),
                        const SizedBox(height: 4),
                        _DetailRow(
                            label: 'Management Scope',
                            value: 'CET Department Assignment'),
                      ],
                    ),
                  ),
                  crossFadeState: _isExpanded
                      ? CrossFadeState.showSecond
                      : CrossFadeState.showFirst,
                  duration: const Duration(milliseconds: 200),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label,
            style: const TextStyle(
                color: AppColors.ustpMuted, fontSize: 9.5)),
        Text(value,
            style: const TextStyle(
                color: AppColors.ustpDark,
                fontSize: 9.5,
                fontWeight: FontWeight.w500)),
      ],
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 8,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }
}

class _RoleBadge extends StatelessWidget {
  const _RoleBadge({required this.label, required this.roleId});

  final String label;
  final int roleId;

  @override
  Widget build(BuildContext context) {
    final color = roleId == 2 ? AppColors.ustpPurple : AppColors.ustpMuted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 8,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

String _roleName(int roleId) => switch (roleId) {
      2 => 'Auditor',
      3 => 'Auditee',
      _ => 'Unknown',
    };

class _LoadError extends StatelessWidget {
  const _LoadError({required this.error, required this.onRetry});

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
                'Could not load users.\n$error',
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
