<?php

return [
    'campus' => 'USTP Claveria Campus',
    'campuses' => ['Alubijid', 'Balubal', 'Cagayan de Oro', 'Claveria', 'Jasaan', 'Oroquieta', 'Panaon', 'Villanueva'],
    // Official campus pages checked 2026-11-14. Empty lists mean no named colleges
    // were published there; they do not establish that the campus has no colleges.
    // https://www.ustp.edu.ph/cdeo/ and https://www.ustp.edu.ph/claveria/
    // https://www.ustp.edu.ph/ustp-launches-college-of-nursing-welcomes-88-pioneering-students/
    // Other sources: https://www.ustp.edu.ph/{alubijid,balubal,jasaan,oroquieta,panaon,villanueva}/
    'campusColleges' => [
        'Alubijid' => [],
        'Balubal' => [],
        'Cagayan de Oro' => [
            'College of Engineering and Architecture' => 'CEA - College of Engineering and Architecture',
            'College of Information Technology and Computing' => 'CITC - College of Information Technology and Computing',
            'College of Science and Mathematics' => 'CSM - College of Science and Mathematics',
            'College of Science and Technology Education' => 'CSTE - College of Science and Technology Education',
            'College of Technology' => 'COT - College of Technology',
            'College of Medicine' => 'COM - College of Medicine',
            'College of Nursing' => 'CON - College of Nursing',
        ],
        'Claveria' => [
            'CET' => 'CET - College of Engineering and Technology',
            'College of Agriculture' => 'CA - College of Agriculture',
            'College of Arts and Sciences' => 'CAS - College of Arts and Sciences',
        ],
        'Jasaan' => [],
        'Oroquieta' => [],
        'Panaon' => [],
        'Villanueva' => [],
    ],
    'collegeCode' => 'CET',
    'collegeName' => 'College of Engineering and Technology',
    'departments' => [
        'CET-ABE' => 'Agricultural and Biosystem Engineering',
        'CET-ENV' => 'Environmental Engineering',
        'CET-FPT' => 'Food Processing and Technology',
        'CET-HM' => 'Hospitality Management',
        'CET-IT' => 'Information Technology',
    ],
    'clusters' => ['cluster_a' => 'Cluster A (Claveria Group)', 'cluster_b' => 'Cluster B (Cagayan de Oro/Main Group)'],
    'clusterCampuses' => ['cluster_a' => ['Claveria', 'USTP Claveria Campus'], 'cluster_b' => ['Cagayan de Oro', 'USTP Cagayan de Oro Campus']],
    'responsibilities' => ['department_head', 'department_secretary', 'college_dean', 'college_secretary', 'cluster_administrator', 'faculty_staff'],
    'categories' => [
        ['code' => '1.0', 'name' => 'Academic & Instruction Processes', 'scope' => 'academic_department', 'children' => ['Curriculum & Program Specifications', 'Syllabi & Table of Specifications', 'Instructional Materials', 'Student Assessment & Grades']],
        ['code' => '2.0', 'name' => 'Competency & Infrastructure', 'scope' => 'academic_department', 'children' => ['Faculty Profiles', 'Laboratory & Facilities Control']],
        ['code' => '3.0', 'name' => 'Performance & Feedback', 'scope' => 'academic_department', 'children' => ['Student Evaluations', 'Risk & Opportunity Registers', 'Previous Audit Findings']],
        ['code' => 'D1', 'name' => 'College Governance & Document Control', 'scope' => 'college_office', 'children' => ['College Policies & Memoranda', 'Document Control Register']],
        ['code' => 'D2', 'name' => 'College Planning & Quality Review', 'scope' => 'college_office', 'children' => ['Management Review', 'College Risk & Opportunity Registers']],
    ],
];
