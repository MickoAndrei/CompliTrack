import 'package:complitrack/data/models/department_model.dart';
import 'package:complitrack/data/repositories/department_repository.dart';
import 'package:complitrack/presentation/screens/admin/department_edit_form.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('back closes the editor and legacy campus enables colleges', (tester) async {
    var cancelled = 0;
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: DepartmentEditForm(
      department: const DepartmentModel(id: 'legacy', departmentCode: 'CODE',
        departmentName: 'Original', campusName: 'USTP Claveria Campus',
        collegeName: 'College of Engineering and Technology'),
      onSaved: () {}, onCancel: () => cancelled++,
    ))));
    expect(find.text('USTP Claveria'), findsOneWidget);
    await tester.tap(find.text('CET - College of Engineering and Technology'));
    await tester.pumpAndSettle();
    expect(find.text('CA - College of Agriculture'), findsOneWidget);
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(cancelled, 0);
    expect(find.text('CA - College of Agriculture'), findsNothing);
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(cancelled, 1);
  });

  test('unassigned college survives API decoding', () {
    for (final college in [null, '']) {
      expect(
          DepartmentModel.fromJson({'collegeName': college}).collegeName, '');
    }
  });

  for (final reject in [false, true]) {
    testWidgets('edit selection and PATCH ${reject ? 'failure' : 'success'}',
        (tester) async {
      final dio = Dio();
      addTearDown(dio.close);
      final requests = <RequestOptions>[];
      var saved = 0;
      var cancelled = 0;
      dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) {
        requests.add(options);
        final response = Response(
            requestOptions: options,
            statusCode: reject ? 422 : 200,
            data: reject
                ? {'message': 'Verified ownership cannot be changed.'}
                : {
                    'data': {'_id': 'dept1', ...options.data as Map}
                  });
        if (reject) {
          handler.reject(
              DioException(requestOptions: options, response: response));
        } else {
          handler.resolve(response);
        }
      }));
      await tester.pumpWidget(MaterialApp(
          home: Scaffold(
              body: DepartmentEditForm(
        department: const DepartmentModel(
            id: 'dept1',
            departmentCode: 'CODE',
            departmentName: 'Original',
            campusName: 'Claveria',
            collegeName: 'CET'),
        repository: DepartmentRepository(dio: dio),
        onSaved: () => saved++,
        onCancel: () => cancelled++,
      ))));
      expect(tester.widget<TextField>(find.byType(TextField).first).readOnly,
          isTrue);
      await tester.enterText(find.byType(TextFormField).last, '');
      await tester.tap(find.text('Save Changes'));
      await tester.pumpAndSettle();
      expect(find.text('This field is required.'), findsOneWidget);
      expect(requests, isEmpty);
      await tester.enterText(find.byType(TextFormField).last, ' Updated ');
      await tester.tap(find.text('USTP Claveria'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('USTP Cagayan de Oro'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Save Changes'));
      await tester.pumpAndSettle();
      expect(find.text('Select a college for this campus.'), findsOneWidget);
      await tester.tap(find.text('Select a college'));
      await tester.pumpAndSettle();
      await tester
          .tap(find.text('CEA - College of Engineering and Architecture'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Save Changes'));
      await tester.pumpAndSettle();
      expect(requests.single.method, 'PATCH');
      expect(requests.single.path, '/api/v1/departments/dept1');
      expect(requests.single.data, {
        'departmentName': 'Updated',
        'campusName': 'Cagayan de Oro',
        'collegeName': 'College of Engineering and Architecture'
      });
      expect(saved, reject ? 0 : 1);
      if (reject) {
        expect(
            find.text('Verified ownership cannot be changed.'), findsOneWidget);
        await tester.tap(find.text('Cancel'));
        expect(cancelled, 1);
      }
    });
  }
}
