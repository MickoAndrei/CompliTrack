<?php

return [
    'campus' => 'USTP Claveria Campus',
    'collegeCode' => 'CET',
    'collegeName' => 'College of Engineering and Technology',
    'departments' => [
        'CET-ABE' => 'Agricultural and Biosystem Engineering',
        'CET-ENV' => 'Environmental Engineering',
        'CET-FPT' => 'Food Processing and Technology',
        'CET-HM' => 'Hospitality Management',
        'CET-IT' => 'Information Technology',
    ],
    'responsibilities' => ['department_head', 'department_secretary', 'college_dean', 'college_secretary', 'cluster_administrator', 'faculty_staff'],
    'categories' => [
        ['code' => '1.0', 'name' => 'Academic & Instruction Processes', 'scope' => 'academic_department', 'children' => ['Curriculum & Program Specifications', 'Syllabi & Table of Specifications', 'Instructional Materials', 'Student Assessment & Grades']],
        ['code' => '2.0', 'name' => 'Competency & Infrastructure', 'scope' => 'academic_department', 'children' => ['Faculty Profiles', 'Laboratory & Facilities Control']],
        ['code' => '3.0', 'name' => 'Performance & Feedback', 'scope' => 'academic_department', 'children' => ['Student Evaluations', 'Risk & Opportunity Registers', 'Previous Audit Findings']],
        ['code' => 'D1', 'name' => 'College Governance & Document Control', 'scope' => 'college_office', 'children' => ['College Policies & Memoranda', 'Document Control Register']],
        ['code' => 'D2', 'name' => 'College Planning & Quality Review', 'scope' => 'college_office', 'children' => ['Management Review', 'College Risk & Opportunity Registers']],
    ],
];
